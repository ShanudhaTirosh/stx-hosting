"""SMS ingestion and corroboration. Hands-free automated receipt-to-ledger reconciliation."""
import hashlib
from datetime import datetime, timedelta, timezone

from sqlalchemy import select
from sqlalchemy.orm import Session

from . import audit, combank_receipt
from .config import settings
from .models import BankStatementImport, BankTransaction, ClaimStatus as S, FraudSignal, Order, PaymentClaim, SmsEvent
from .sms_parse import contains_secret, parse


def _aware(d: datetime) -> datetime:
    return d if d.tzinfo else d.replace(tzinfo=timezone.utc)


def sender_allowed(sender: str) -> bool:
    allow = {a.strip().lower() for a in settings.sms_sender_allowlist.split(",") if a.strip()}
    allow.add("2662265")
    return sender.strip().lower() in allow


def ingest_receipt_to_ledger(db: Session, ev: SmsEvent) -> BankTransaction | None:
    """Automatically convert a verified ComBank digital receipt from vas.combank.net
    into an authoritative BankTransaction so payment can be verified without manual exports.
    """
    if not ev.receipt_stan or not ev.amount_minor or ev.direction != "credit":
        return None

    dedupe_key = hashlib.sha256(f"combank_vas_{ev.receipt_stan}_{ev.amount_minor}".encode()).hexdigest()
    existing = db.execute(
        select(BankTransaction).where(BankTransaction.dedupe_key == dedupe_key)
    ).scalar_one_or_none()
    if existing:
        ev.matched_bank_txn_id = existing.id
        return existing

    import_sha = hashlib.sha256(f"combank_vas_receipt_{ev.receipt_stan}".encode()).hexdigest()
    imp = db.execute(
        select(BankStatementImport).where(BankStatementImport.file_sha256 == import_sha)
    ).scalar_one_or_none()
    if not imp:
        imp = BankStatementImport(
            source="combank_receipt",
            file_sha256=import_sha,
            rows_total=1,
            rows_new=1,
        )
        db.add(imp)
        db.flush()

    branch = ev.branch or (ev.receipt_data.get("branch") if ev.receipt_data else "CRM")
    term = ev.receipt_data.get("terminalId") if ev.receipt_data else ""
    remarks = f"CRM Deposit {branch} STAN {ev.receipt_stan}"
    if term:
        remarks += f" Term {term}"

    txn = BankTransaction(
        import_id=imp.id,
        bank_txn_ref=ev.receipt_stan,
        value_dt=_aware(ev.received_at),
        amount_minor=ev.amount_minor,
        direction="credit",
        counterparty_name=None,
        remarks=remarks,
        dedupe_key=dedupe_key,
        raw=ev.receipt_data or {},
    )
    db.add(txn)
    db.flush()
    ev.matched_bank_txn_id = txn.id
    return txn


def corroborate_claim(db: Session, claim: PaymentClaim) -> bool:
    order = db.get(Order, claim.order_id)
    lo = _aware(order.created_at) - timedelta(days=1)
    ev = db.execute(select(SmsEvent).where(
        SmsEvent.direction == "credit", SmsEvent.amount_minor == order.expected_pay_amount_minor,
        SmsEvent.received_at >= lo).order_by(SmsEvent.id.desc()).limit(1)).scalar_one_or_none()
    if not ev:
        return False
    if db.execute(select(FraudSignal.id).where(FraudSignal.claim_id == claim.id,
                                               FraudSignal.rule_code == "sms_corroborated")).first():
        return True
    detail = {"sms_event_id": ev.id, "note": "supporting evidence only"}
    if ev.receipt_stan:
        detail["receipt_stan"] = ev.receipt_stan
    if ev.receipt_pdf_key:
        detail["receipt_pdf_key"] = ev.receipt_pdf_key
    if ev.receipt_data:
        detail["receipt_terminal"] = ev.receipt_data.get("terminalId")
        detail["receipt_branch"] = ev.receipt_data.get("branch")
    db.add(FraudSignal(claim_id=claim.id, rule_code="sms_corroborated", severity="info",
                       detail=detail))
    return True


def ingest(db: Session, device_id: int, items: list[dict]) -> list[dict]:
    results = []
    for it in items:
        key, body, sender = it["idempotency_key"], it["body"], it["sender"]
        if db.execute(select(SmsEvent.id).where(SmsEvent.idempotency_key == key)).first():
            results.append({"key": key, "result": "duplicate"})
            continue
        if contains_secret(body):   # never store anything that looks like an OTP, PIN or password message
            results.append({"key": key, "result": "dropped_sensitive"})
            continue
        source = it.get("source", "sms")
        if source == "sms" and not sender_allowed(sender):
            results.append({"key": key, "result": "dropped_sender"})
            continue
        p = parse(body)
        receipt_stan = None
        receipt_pdf_key = None
        receipt_data = None
        if p.receipt_url:
            rec = combank_receipt.process_receipt_url(p.receipt_url)
            if rec:
                receipt_stan = rec.get("stan")
                receipt_pdf_key = rec.get("pdf_storage_key")
                receipt_data = rec.get("details")
                if receipt_stan and not p.ref:
                    p.ref = receipt_stan
                if receipt_data and receipt_data.get("branch"):
                    p.branch = receipt_data.get("branch")

        ev = SmsEvent(device_id=device_id, idempotency_key=key, source=source, sender=sender[:64], body=body[:1000],
                      received_at=datetime.fromisoformat(it["received_at"].replace("Z", "+00:00")),
                      direction=p.direction, amount_minor=p.amount_minor, ref=p.ref, branch=p.branch,
                      receipt_url=p.receipt_url, receipt_stan=receipt_stan, receipt_pdf_key=receipt_pdf_key,
                      receipt_data=receipt_data, parsed_ok=p.ok)
        db.add(ev)
        db.flush()
        if p.ok and p.direction == "credit":
            if settings.auto_receipt_to_ledger and ev.receipt_stan:
                ingest_receipt_to_ledger(db, ev)
            from . import matcher
            for c in db.execute(select(PaymentClaim).join(Order, Order.id == PaymentClaim.order_id).where(
                    PaymentClaim.status.in_([S.awaiting_bank_confirmation.value, S.manual_review.value]),
                    Order.expected_pay_amount_minor == p.amount_minor)).scalars():
                corroborate_claim(db, c)
                if settings.auto_receipt_to_ledger and ev.receipt_stan:
                    matcher.try_match_claim(db, c)
        results.append({"key": key, "result": "stored" if p.ok else "stored_unparsed", "id": ev.id})
    audit.log(db, action="sms.ingest", entity="device", entity_id=device_id,
              detail={"received": len(items), "stored": sum(r["result"].startswith("stored") for r in results)})
    return results


def link_to_bank(db: Session) -> int:
    """After a statement import: tie unmatched credit SMS to the bank credit they describe (same amount, within a day)."""
    n = 0
    for ev in db.execute(select(SmsEvent).where(SmsEvent.matched_bank_txn_id.is_(None), SmsEvent.direction == "credit",
                                                SmsEvent.amount_minor.is_not(None))).scalars():
        lo, hi = _aware(ev.received_at) - timedelta(days=2), _aware(ev.received_at) + timedelta(days=2)
        cands = [t for t in db.execute(select(BankTransaction).where(
            BankTransaction.direction == "credit", BankTransaction.amount_minor == ev.amount_minor,
            BankTransaction.value_dt >= lo, BankTransaction.value_dt <= hi)).scalars()]
        by_ref = [t for t in cands if (ev.ref and ev.ref == (t.bank_txn_ref or "").upper()) or
                                      (ev.receipt_stan and ev.receipt_stan in (t.remarks or "")) or
                                      (ev.branch and ev.branch.upper() in (t.remarks or "").upper())]
        pick = by_ref[0] if by_ref else cands[0] if len(cands) == 1 else None
        if pick:
            ev.matched_bank_txn_id = pick.id
            n += 1
    return n
