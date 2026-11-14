"""Deterministic checks on extracted receipt fields. Pure code: no model decides anything here."""
import re
from datetime import datetime, timedelta, timezone

from ..bank_import import ImportError_, parse_dt, parse_money
from ..config import settings
from ..fraud import norm_ref
from ..models import Order, PaymentClaim
from .ai import Receipt

FAIL_WORDS = re.compile(r"fail|declin|reject|unsuccess|pending|reversed|error|cancel", re.I)
KEY_FIELDS = ["reference", "amount", "datetime", "recipient_account_masked"]
def _aware(d: datetime) -> datetime:  # SQLite hands back naive datetimes; treat them as UTC
    return d if d.tzinfo else d.replace(tzinfo=timezone.utc)


Signal = tuple[str, str, dict]  # (rule_code, severity, detail)


def _digits(s: str | None) -> str:
    return re.sub(r"\D", "", s or "")


def validate(r: Receipt, claim: PaymentClaim, order: Order, ocr_text: str) -> tuple[list[Signal], float]:
    sig: list[Signal] = []
    conf = r.field_confidence

    # Amount: must parse, match the claim, and actually appear in the OCR text (guards against model invention).
    amt = None
    try:
        amt = parse_money(r.amount)
    except ImportError_:
        sig.append(("receipt_amount_unreadable", "warn", {}))
    if amt is None and r.amount is None:
        sig.append(("receipt_amount_missing", "warn", {}))
    if amt is not None:
        if amt != claim.claimed_amount_minor:
            sev = "block" if conf.get("amount", 0.0) >= 0.6 else "warn"
            sig.append(("receipt_amount_mismatch", sev, {"receipt": amt, "claimed": claim.claimed_amount_minor}))
        if ocr_text.strip():
            flat = re.sub(r"[\s,]", "", ocr_text)
            if f"{amt / 100:.2f}" not in flat and f"{amt // 100}" not in flat:
                sig.append(("amount_not_in_ocr", "warn", {}))

    # Recipient account: your configured account tail must be what the receipt shows.
    tail = settings.bank_account_tail
    shown = _digits(r.recipient_account_masked)
    if tail:
        if not shown:
            sig.append(("recipient_account_missing", "info", {}))
        elif not shown.endswith(tail[-len(shown):] if len(shown) < len(tail) else tail):
            sig.append(("recipient_account_mismatch", "block", {}))

    if r.status_text and FAIL_WORDS.search(r.status_text):
        sig.append(("receipt_status_not_success", "block", {"status": r.status_text[:60]}))
    elif not r.status_text:
        sig.append(("receipt_status_missing", "info", {}))

    if r.datetime:
        try:
            d = parse_dt(r.datetime)
            now = datetime.now(timezone.utc)
            if d > now + timedelta(minutes=10):
                sig.append(("receipt_date_in_future", "warn", {}))
            if d < _aware(order.created_at) - timedelta(days=7):
                sig.append(("receipt_date_before_order", "warn", {}))
        except ImportError_:
            sig.append(("receipt_date_unreadable", "info", {}))

    cr, rr = norm_ref(claim.claimed_reference), norm_ref(r.reference)
    if cr and rr and cr not in rr and rr not in cr:
        sig.append(("reference_differs", "warn", {}))

    for a in r.anomalies:
        sig.append(("ai_anomaly", "warn", {"note": a}))

    base = sum(conf.get(k, 0.0) for k in KEY_FIELDS) / len(KEY_FIELDS)
    score = max(0.0, base - 0.15 * sum(1 for _, s, _ in sig if s == "warn") - 0.4 * any(s == "block" for _, s, _ in sig))
    if score < 0.5:
        sig.append(("low_confidence", "info", {"score": round(score, 2)}))
    return sig, round(score, 2)
