import secrets
import string

import os

from fastapi import APIRouter, Depends, File, Form, HTTPException, Query, UploadFile
from fastapi.responses import Response
from pydantic import BaseModel

from ..files import read_plain
from sqlalchemy import func, or_, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from .. import audit
from ..bank_import import ImportError_, import_statement
from ..config import settings
from ..db import get_db
from ..matcher import run_all_awaiting
from ..sms import link_to_bank
from ..models import (AiAnalysis, Allocation, AuditLog, BankTransaction, ClaimStatus as S, FraudSignal, Order, PaymentClaim,
                      ProofFile, Role, User)
from ..schemas import DecisionIn, OrderIn
from ..security import require_roles
from ..state import TransitionError, transition

router = APIRouter(prefix="/api/v1/admin", tags=["admin"])
ANY_STAFF = (Role.support, Role.reviewer, Role.finance_admin, Role.super_admin)
FINANCE = (Role.finance_admin, Role.super_admin)


def _code() -> str:
    return "PV" + "".join(secrets.choice(string.ascii_uppercase + string.digits) for _ in range(6))


@router.post("/orders", status_code=201)
def create_order(body: OrderIn, user: User = Depends(require_roles(*FINANCE)), db: Session = Depends(get_db)):
    if not db.get(User, body.customer_id):
        raise HTTPException(404, "Customer not found")
    expected = body.amount_minor
    if body.unique_amount:  # add 1-99 cents so the credit identifies the order; retry on collision
        for _ in range(50):
            cand = body.amount_minor + secrets.randbelow(99) + 1
            if not db.scalar(select(Order.id).where(Order.expected_pay_amount_minor == cand,
                                                    Order.status == "unpaid")):
                expected = cand
                break
        else:
            raise HTTPException(409, "Could not allocate a unique amount")
    o = Order(customer_id=body.customer_id, invoice_no=body.invoice_no, amount_minor=body.amount_minor,
              expected_pay_amount_minor=expected, unique_amount=body.unique_amount, payment_code=_code())
    db.add(o)
    try:
        db.flush()
    except IntegrityError:
        raise HTTPException(409, "Invoice number already exists")
    audit.log(db, action="order.create", entity="order", entity_id=o.id, actor_id=user.id)
    db.commit()
    return {"id": o.id, "payment_code": o.payment_code, "expected_pay_amount_minor": expected}


@router.post("/bank/statement-imports")
async def upload_statement(file: UploadFile = File(...), account_tail: str | None = Form(None),
                           user: User = Depends(require_roles(*FINANCE)), db: Session = Depends(get_db)):
    data = await file.read(10 * 1024 * 1024 + 1)
    if len(data) > 10 * 1024 * 1024:
        raise HTTPException(413, "Statement file too large")
    try:
        result = import_statement(db, data=data, source="statement_xlsx" if data.startswith(b"PK") else "statement_csv", user_id=user.id, account_tail=account_tail)
    except ImportError_ as e:
        db.rollback()
        raise HTTPException(400, str(e))
    result["sms_linked"] = link_to_bank(db)
    result["matching"] = run_all_awaiting(db)  # new credits may satisfy waiting claims
    db.commit()
    return result


@router.get("/bank/transactions")
def list_txns(unmatched: bool = False, limit: int = Query(100, le=500),
              user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    q = select(BankTransaction).where(BankTransaction.direction == "credit")
    if unmatched:
        q = q.where(BankTransaction.id.not_in(select(Allocation.bank_transaction_id)))
    rows = db.execute(q.order_by(BankTransaction.value_dt.desc()).limit(limit)).scalars()
    return [{"id": t.id, "ref": t.bank_txn_ref, "value_dt": t.value_dt, "amount_minor": t.amount_minor,
             "remarks": t.remarks} for t in rows]


@router.get("/claims")
def list_claims(status: str | None = None, q: str | None = None, limit: int = Query(100, le=500),
                user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    stmt = select(PaymentClaim, User).outerjoin(User, User.id == PaymentClaim.customer_id)
    if status:
        stmt = stmt.where(PaymentClaim.status == status)
    if q:
        like = f"%{q}%"
        conds = [
            PaymentClaim.claimed_reference.ilike(like),
            User.email.ilike(like),
            User.name.ilike(like),
            User.phone.ilike(like),
            User.external_id == q,
        ]
        if q.isdigit():
            conds += [PaymentClaim.id == int(q), PaymentClaim.customer_id == int(q), PaymentClaim.order_id == int(q)]
        stmt = stmt.where(or_(*conds))
    rows = db.execute(stmt.order_by(PaymentClaim.id.desc()).limit(limit)).all()
    return [{
        "id": c.id,
        "order_id": c.order_id,
        "customer_id": c.customer_id,
        "customer_email": u.email if u else None,
        "customer_name": u.name if u else None,
        "customer_phone": getattr(u, "phone", None) if u else None,
        "customer_external_id": getattr(u, "external_id", None) if u else None,
        "status": c.status,
        "reason": c.status_reason_code,
        "amount_minor": c.claimed_amount_minor,
        "reference": c.claimed_reference,
        "created_at": c.created_at,
    } for c, u in rows]


@router.get("/claims/{claim_id}")
def claim_detail(claim_id: int, user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    c = db.get(PaymentClaim, claim_id)
    if not c:
        raise HTTPException(404, "Not found")
    order = db.get(Order, c.order_id)
    cust = db.get(User, c.customer_id)
    alloc = db.execute(select(Allocation).where(Allocation.claim_id == c.id)).scalar_one_or_none()
    cands = []
    if c.status in (S.manual_review.value, S.awaiting_bank_confirmation.value, S.suspicious.value):
        from ..matcher import _candidates
        cands = [{"id": t.id, "ref": t.bank_txn_ref, "value_dt": t.value_dt, "amount_minor": t.amount_minor,
                  "remarks": t.remarks} for t in _candidates(db, c, order)]

    logs = db.execute(
        select(AuditLog)
        .where(
            or_(
                (AuditLog.entity == "claim") & (AuditLog.entity_id == c.id),
                (AuditLog.entity == "order") & (AuditLog.entity_id == order.id),
            )
        )
        .order_by(AuditLog.id.desc())
        .limit(30)
    ).scalars().all()

    audit_trail = [
        {
            "id": l.id,
            "ts": l.ts.isoformat() if l.ts else None,
            "action": l.action,
            "actor_type": l.actor_type,
            "actor_id": l.actor_id,
            "entity": l.entity,
            "entity_id": l.entity_id,
            "detail": l.detail,
            "hash_self": l.hash_self,
            "hash_prev": l.hash_prev,
        }
        for l in logs
    ]

    return {"claim": {"id": c.id, "status": c.status, "reason": c.status_reason_code, "basis": c.verification_basis,
                      "channel": c.channel, "claimed": {"amount_minor": c.claimed_amount_minor,
                                                        "reference": c.claimed_reference,
                                                        "sender": c.claimed_sender_name, "paid_at": c.claimed_paid_at}},
            "order": {"id": order.id, "invoice_no": order.invoice_no,
                      "expected_pay_amount_minor": order.expected_pay_amount_minor,
                      "payment_code": order.payment_code},
            "customer": {
                "id": cust.id,
                "email": cust.email,
                "name": cust.name,
                "phone": getattr(cust, "phone", None),
                "external_id": getattr(cust, "external_id", None),
            } if cust else None,
            "files": [{"id": f.id, "mime": f.mime, "size": f.size, "sha256": f.sha256, "dhash": f.dhash, "purged": bool(f.purged_at)} for f in c.files],
            "fraud_signals": [{"rule": s.rule_code, "severity": s.severity, "detail": s.detail}
                              for s in db.execute(select(FraudSignal).where(FraudSignal.claim_id == c.id)).scalars()],
            "ai_analyses": [{"file_id": a.proof_file_id, "model": a.model, "confidence": a.overall_confidence,
                             "extracted": a.extracted} for a in db.execute(
                select(AiAnalysis).join(ProofFile, ProofFile.id == AiAnalysis.proof_file_id)
                .where(ProofFile.claim_id == c.id)).scalars()],
            "allocated_bank_transaction_id": alloc.bank_transaction_id if alloc else None,
            "candidate_bank_transactions": cands,
            "audit_trail": audit_trail}


@router.post("/claims/{claim_id}/decision")
def decide(claim_id: int, body: DecisionIn, user: User = Depends(require_roles(Role.reviewer, *FINANCE)),
           db: Session = Depends(get_db)):
    c = db.get(PaymentClaim, claim_id)
    if not c:
        raise HTTPException(404, "Not found")
    try:
        if body.decision == "reject":
            extra = {"note": body.reason}
            if body.rejection_code:
                extra["rejection_code"] = body.rejection_code
            if body.customer_message:
                extra["customer_message"] = body.customer_message
                c.customer_message = body.customer_message
            transition(db, c, S.rejected, actor_id=user.id, reason=body.rejection_code or "admin_reject", extra=extra)
        elif body.bank_transaction_id:
            order = db.get(Order, c.order_id)
            t = db.execute(select(BankTransaction).where(BankTransaction.id == body.bank_transaction_id)
                           .with_for_update()).scalar_one_or_none()
            if not t or t.direction != "credit" or t.amount_minor != order.expected_pay_amount_minor:
                raise HTTPException(422, "Bank transaction must be a credit equal to the expected amount")
            try:
                with db.begin_nested():
                    db.add(Allocation(bank_transaction_id=t.id, claim_id=c.id, amount_minor=t.amount_minor))
                    db.flush()
            except IntegrityError:
                raise HTTPException(409, "That bank transaction (or this claim) is already allocated")
            transition(db, c, S.verified, actor_id=user.id, basis="bank_match", reason="admin_approve_linked",
                       extra={"bank_transaction_id": t.id, "note": body.reason})
        else:
            if user.role not in {r.value for r in FINANCE}:
                raise HTTPException(403, "Approving without a bank transaction needs a finance admin")
            if not body.override_justification or len(body.override_justification) < 20:
                raise HTTPException(422, "Override needs a written justification (20+ chars)")
            transition(db, c, S.verified, actor_id=user.id, basis="admin_override", reason="admin_override",
                       extra={"note": body.reason, "justification": body.override_justification})
    except TransitionError as e:
        db.rollback()
        raise HTTPException(409, str(e))
    db.commit()
    return {"id": c.id, "status": c.status, "basis": c.verification_basis}


class QuickVerifyIn(BaseModel):
    reason: str = "Manual confirmation by staff"


@router.post("/claims/{claim_id}/quick-verify")
def quick_verify_claim(
    claim_id: int,
    body: QuickVerifyIn = QuickVerifyIn(),
    user: User = Depends(require_roles(Role.reviewer, *FINANCE)),
    db: Session = Depends(get_db),
):
    """One-click manual verification for staff from web or app."""
    c = db.get(PaymentClaim, claim_id)
    if not c:
        raise HTTPException(404, "Claim not found")
    if c.status == S.verified.value:
        return {"id": c.id, "status": "verified", "already_verified": True}

    order = db.get(Order, c.order_id)
    from ..matcher import _candidates
    cands = _candidates(db, c, order)

    # 1. Try bank candidate allocation
    if cands:
        t = cands[0]
        try:
            with db.begin_nested():
                db.add(Allocation(bank_transaction_id=t.id, claim_id=c.id, amount_minor=t.amount_minor))
                db.flush()
            transition(
                db,
                c,
                S.verified,
                actor_id=user.id,
                basis="bank_match",
                reason="admin_quick_verify",
                extra={"bank_transaction_id": t.id, "note": body.reason},
            )
            db.commit()
            return {"id": c.id, "status": "verified", "basis": "bank_match"}
        except IntegrityError:
            pass

    # 2. Otherwise approve via override
    transition(
        db,
        c,
        S.verified,
        actor_id=user.id,
        basis="admin_override",
        reason="admin_quick_verify",
        extra={"note": body.reason, "justification": f"Quick manual verification by {user.email}"},
    )
    db.commit()
    return {"id": c.id, "status": "verified", "basis": "admin_override"}


@router.get("/dashboard")
def dashboard(user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    counts = dict(db.execute(select(PaymentClaim.status, func.count()).group_by(PaymentClaim.status)).all())
    value = db.scalar(select(func.coalesce(func.sum(Allocation.amount_minor), 0))) or 0
    unmatched = db.scalar(select(func.count(BankTransaction.id)).where(
        BankTransaction.direction == "credit", BankTransaction.id.not_in(select(Allocation.bank_transaction_id))))
    return {"total_claims": sum(counts.values()), "by_status": counts, "verified_value_minor": value,
            "unmatched_bank_credits": unmatched}


@router.get("/audit")
def audit_trail(entity: str | None = None, entity_id: int | None = None, limit: int = Query(200, le=1000),
                user: User = Depends(require_roles(Role.finance_admin, Role.super_admin)),
                db: Session = Depends(get_db)):
    stmt = select(AuditLog)
    if entity:
        stmt = stmt.where(AuditLog.entity == entity)
    if entity_id is not None:
        stmt = stmt.where(AuditLog.entity_id == entity_id)
    return [{"id": a.id, "ts": a.ts, "actor": a.actor_id, "action": a.action, "entity": a.entity,
             "entity_id": a.entity_id, "detail": a.detail} for a in db.execute(stmt.order_by(AuditLog.id.desc()).limit(limit)).scalars()]


@router.get("/audit/verify")
def audit_verify(user: User = Depends(require_roles(Role.finance_admin, Role.super_admin)), db: Session = Depends(get_db)):
    ok, bad = audit.verify_chain(db)
    return {"intact": ok, "first_bad_id": bad}


@router.get("/files/{file_id}")
def proof_file(file_id: int, user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    pf = db.get(ProofFile, file_id)
    if not pf:
        raise HTTPException(404, "Not found")
    if pf.purged_at:
        raise HTTPException(410, "This proof was deleted under the retention policy")
    audit.log(db, action="proof.view", entity="claim", entity_id=pf.claim_id, actor_id=user.id,
              detail={"file_id": pf.id})
    db.commit()
    return Response(read_plain(pf.storage_key), media_type=pf.mime,
                    headers={"X-Content-Type-Options": "nosniff", "Content-Disposition": "inline",
                                 "Cache-Control": "private, no-store"})


@router.post("/retention/run")
def retention_run(apply: bool = False, user: User = Depends(require_roles(Role.super_admin)), db: Session = Depends(get_db)):
    from .. import retention
    return retention.run(db, apply=apply)   # dry run unless apply=true


@router.post("/customers/{customer_id}/erase")
def erase_customer_data(customer_id: int, user: User = Depends(require_roles(Role.super_admin)), db: Session = Depends(get_db)):
    from ..dsar import ErasureRefused, erase_customer
    target = db.get(User, customer_id)
    if not target:
        raise HTTPException(404, "Not found")
    try:
        out = erase_customer(db, target, user.id)
    except ErasureRefused as e:
        db.rollback()
        raise HTTPException(409, str(e))
    db.commit()
    return out
