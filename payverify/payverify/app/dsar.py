"""Data-subject requests: let a customer see their data, and let a super admin erase it.
Erasure removes personal data but KEEPS financial facts you may be legally required to retain (amounts, bank references,
invoice numbers, status history, audit log). Confirm what you must keep with your accountant/lawyer."""
import secrets

from sqlalchemy import select
from sqlalchemy.orm import Session

from . import audit
from .models import ClaimStatus as S, Order, PaymentClaim, User
from .retention import purge_claim
from .security import hash_password

OPEN = [S.pending.value, S.processing.value, S.awaiting_bank_confirmation.value, S.manual_review.value, S.suspicious.value]


def export_user(db: Session, user: User) -> dict:
    orders = db.execute(select(Order).where(Order.customer_id == user.id).order_by(Order.id)).scalars().all()
    claims = db.execute(select(PaymentClaim).where(PaymentClaim.customer_id == user.id).order_by(PaymentClaim.id)).scalars().all()
    return {
        "account": {"email": user.email, "name": user.name, "created_at": user.created_at},
        "orders": [{"invoice_no": o.invoice_no, "amount_minor": o.amount_minor, "payment_code": o.payment_code,
                    "status": o.status, "created_at": o.created_at} for o in orders],
        "payment_claims": [{"id": c.id, "order_id": c.order_id, "channel": c.channel, "status": c.status,
                            "claimed_amount_minor": c.claimed_amount_minor, "claimed_reference": c.claimed_reference,
                            "claimed_sender_name": c.claimed_sender_name, "claimed_paid_at": c.claimed_paid_at,
                            "submitted_at": c.created_at, "verified_at": c.verified_at,
                            "uploaded_files": [{"type": f.mime, "size_bytes": f.size, "deleted": bool(f.purged_at)} for f in c.files]}
                           for c in claims],
        "note": "Internal security and fraud-prevention data is not included.",
    }


class ErasureRefused(Exception):
    pass


def erase_customer(db: Session, user: User, actor_id: int) -> dict:
    if user.role != "customer":
        raise ErasureRefused("Only customer accounts can be erased here")
    if db.execute(select(PaymentClaim.id).where(PaymentClaim.customer_id == user.id, PaymentClaim.status.in_(OPEN))).first():
        raise ErasureRefused("This customer has a payment still being checked. Finish or reject it first.")
    files = 0
    for c in db.execute(select(PaymentClaim).where(PaymentClaim.customer_id == user.id)).scalars():
        files += purge_claim(db, c)
    user.email = f"erased-{user.id}@invalid.example"
    user.name = ""
    user.password_hash = hash_password(secrets.token_urlsafe(32))   # nobody can sign in again
    audit.log(db, action="dsar.erase", entity="user", entity_id=user.id, actor_id=actor_id, detail={"proof_files_deleted": files})
    return {"user_id": user.id, "proof_files_deleted": files}
