"""Payment state machine. The ONLY code path that may set a claim to VERIFIED."""
from sqlalchemy import select
from sqlalchemy.orm import Session

from . import audit
from .models import Allocation, ClaimStatus as S, Order, PaymentClaim, utcnow

ALLOWED: dict[str, set[str]] = {
    S.pending.value: {S.processing.value, S.awaiting_bank_confirmation.value, S.manual_review.value,
                      S.suspicious.value, S.rejected.value},
    S.processing.value: {S.awaiting_bank_confirmation.value, S.manual_review.value, S.suspicious.value,
                         S.rejected.value},
    S.awaiting_bank_confirmation.value: {S.verified.value, S.manual_review.value, S.suspicious.value,
                                         S.rejected.value},
    S.manual_review.value: {S.verified.value, S.rejected.value, S.suspicious.value,
                            S.awaiting_bank_confirmation.value},
    S.suspicious.value: {S.manual_review.value, S.rejected.value},
    S.verified.value: set(),
    S.rejected.value: set(),
}

# Customer-safe wording. Internal fraud reasons are never exposed.
CUSTOMER_MESSAGES = {
    S.pending.value: "We received your payment proof.",
    S.processing.value: "We are reading your payment proof.",
    S.awaiting_bank_confirmation.value: "Waiting for the payment to appear in our bank account.",
    S.manual_review.value: "Our team is checking your payment. No action needed from you.",
    S.suspicious.value: "Our team is checking your payment. No action needed from you.",
    S.verified.value: "Payment confirmed. Thank you!",
    S.rejected.value: "We could not verify this payment. Please check the details and submit again.",
}


class TransitionError(Exception):
    pass


def transition(db: Session, claim: PaymentClaim, to: S, *, actor_id: int | None = None, reason: str = "",
               basis: str | None = None, extra: dict | None = None, customer_message: str | None = None):
    frm = claim.status
    if to.value not in ALLOWED.get(frm, set()):
        raise TransitionError(f"{frm} -> {to.value} not allowed")
    if to is S.verified:
        if basis == "bank_match":
            alloc = db.execute(select(Allocation).where(Allocation.claim_id == claim.id)).scalar_one_or_none()
            if alloc is None:
                raise TransitionError("bank_match verification requires an allocation")
        elif basis == "admin_override":
            if not actor_id:
                raise TransitionError("override requires a human actor")
        else:
            raise TransitionError("VERIFIED requires a verification basis")
        claim.verification_basis = basis
        claim.verified_at = utcnow()
        order = db.get(Order, claim.order_id)
        order.status = "paid"
    claim.status = to.value
    claim.status_reason_code = reason or None
    claim.customer_message = customer_message or CUSTOMER_MESSAGES[to.value]
    claim.version += 1
    audit.log(db, action="claim.transition", entity="claim", entity_id=claim.id, actor_id=actor_id,
              detail={"from": frm, "to": to.value, "reason": reason, "basis": basis, **(extra or {})})
    try:
        from .supabase_sync import sync_claim_to_supabase
        order = db.get(Order, claim.order_id)
        sync_claim_to_supabase({
            "id": claim.id,
            "order_id": claim.order_id,
            "invoice_no": order.invoice_no if order else None,
            "status": claim.status,
            "amount_minor": claim.claimed_amount_minor,
            "claimed_reference": claim.claimed_reference,
            "channel": claim.channel,
            "verification_basis": claim.verification_basis,
            "verified_at": claim.verified_at.isoformat() if claim.verified_at else None,
            "updated_at": utcnow().isoformat(),
        })
    except Exception:
        pass
    if to is S.verified:
        try:
            from .webhook import dispatch_payment_verified
            order = db.get(Order, claim.order_id)
            dispatch_payment_verified(order, claim, db=db)
        except Exception:
            pass
