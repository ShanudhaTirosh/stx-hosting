from datetime import datetime, timedelta, timezone

from fastapi import APIRouter, BackgroundTasks, Depends, File, Form, HTTPException, UploadFile
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from .. import audit
from ..config import settings
from ..db import get_db
from ..files import save_upload
from ..extraction.pipeline import run_in_background
from ..forensics import analyse_files
from ..fraud import run_intake_checks
from ..matcher import try_match_claim
from ..sms import corroborate_claim
from ..models import AiAnalysis, ClaimStatus as S, Order, PaymentClaim, ProofFile, Role, User
from ..schemas import CustomerClaimOut
from ..security import current_user, require_roles
from ..state import transition

router = APIRouter(prefix="/api/v1", tags=["claims"])
CHANNELS = {"atm_cash_deposit", "bank_transfer", "online_banking", "mobile_banking", "other"}


def _extracted(db: Session, c: PaymentClaim) -> dict | None:
    row = db.execute(select(AiAnalysis).join(ProofFile, ProofFile.id == AiAnalysis.proof_file_id)
                     .where(ProofFile.claim_id == c.id).order_by(AiAnalysis.id).limit(1)).scalar_one_or_none()
    if not row:
        return None
    e = row.extracted  # only fields the customer already knows; no scores, anomalies or bank details
    return {"reference": e.get("reference"), "amount": e.get("amount"), "datetime": e.get("datetime"),
            "sender_name": e.get("sender_name")}


def _out(c: PaymentClaim, db: Session) -> CustomerClaimOut:
    return CustomerClaimOut(extracted=_extracted(db, c), id=c.id, order_id=c.order_id, status=c.status, message=c.customer_message,
                            claimed_amount_minor=c.claimed_amount_minor, claimed_reference=c.claimed_reference,
                            created_at=c.created_at, verified_at=c.verified_at)


@router.post("/orders/{order_id}/payment-claims", status_code=202, response_model=CustomerClaimOut)
async def submit_claim(order_id: int, channel: str = Form(...), amount_minor: int = Form(..., gt=0),
                       reference: str | None = Form(None), paid_at: datetime | None = Form(None),
                       sender_name: str | None = Form(None), files: list[UploadFile] = File(...),
                       background: BackgroundTasks = None,
                       user: User = Depends(require_roles(Role.customer)), db: Session = Depends(get_db)):
    order = db.get(Order, order_id)
    if not order or order.customer_id != user.id:
        raise HTTPException(404, "Order not found")  # same answer for missing and not-yours
    if order.status == "paid":
        raise HTTPException(409, "This order is already paid")
    if channel not in CHANNELS:
        raise HTTPException(422, f"channel must be one of {sorted(CHANNELS)}")
    if not 1 <= len(files) <= 3:
        raise HTTPException(422, "Upload 1 to 3 files")
    hour_ago = datetime.now(timezone.utc) - timedelta(hours=1)
    recent = db.scalar(select(func.count(PaymentClaim.id)).where(PaymentClaim.order_id == order_id,
                                                                 PaymentClaim.created_at >= hour_ago))
    if recent >= settings.claims_per_order_per_hour:
        raise HTTPException(429, "Too many submissions for this order. Please try again later.")
    open_claim = db.scalar(select(PaymentClaim.id).where(
        PaymentClaim.order_id == order_id,
        PaymentClaim.status.in_([S.pending.value, S.awaiting_bank_confirmation.value, S.manual_review.value,
                                 S.suspicious.value, S.processing.value])))
    if open_claim:
        raise HTTPException(409, "A payment for this order is already being checked")

    saved = [await save_upload(f) for f in files]
    claim = PaymentClaim(order_id=order_id, customer_id=user.id, channel=channel, claimed_amount_minor=amount_minor,
                         claimed_reference=(reference or "").strip() or None, claimed_paid_at=paid_at,
                         claimed_sender_name=sender_name, status=S.pending.value,
                         customer_message="We received your payment proof.")
    db.add(claim)
    db.flush()
    plains = [s.pop("_plain") for s in saved]
    for s in saved:
        db.add(ProofFile(claim_id=claim.id, **s))
    audit.log(db, action="claim.submit", entity="claim", entity_id=claim.id, actor_id=user.id,
              detail={"order_id": order_id, "channel": channel, "files": len(saved)})
    db.flush()
    signals = run_intake_checks(db, claim, [s["sha256"] for s in saved])
    signals += analyse_files(db, claim, [(s["mime"], s["dhash"], p) for s, p in zip(saved, plains)])
    if any(s.severity == "block" for s in signals):
        transition(db, claim, S.suspicious, reason="intake_block")
    elif settings.extraction_enabled:
        # Receipt reading runs in the background; it can only add signals, never verify.
        transition(db, claim, S.processing)
        db.commit()
        background.add_task(run_in_background, claim.id)
        return _out(claim, db)
    else:
        transition(db, claim, S.awaiting_bank_confirmation)
        corroborate_claim(db, claim)
        try_match_claim(db, claim)
        if settings.bot_sync_on_claim and claim.status == S.awaiting_bank_confirmation.value and background:
            from .bank_sync import trigger_bot_sync_internal
            background.add_task(trigger_bot_sync_internal)
    db.commit()
    return _out(claim, db)


@router.get("/payment-claims/{claim_id}", response_model=CustomerClaimOut)
def get_claim(claim_id: int, user: User = Depends(current_user), db: Session = Depends(get_db)):
    c = db.get(PaymentClaim, claim_id)
    if not c or (user.role == Role.customer.value and c.customer_id != user.id):
        raise HTTPException(404, "Not found")
    return _out(c, db)


@router.get("/my/orders")
def my_orders(user: User = Depends(require_roles(Role.customer)), db: Session = Depends(get_db)):
    rows = db.execute(select(Order).where(Order.customer_id == user.id).order_by(Order.id.desc())).scalars()
    return [{"id": o.id, "invoice_no": o.invoice_no, "amount_minor": o.expected_pay_amount_minor,
             "payment_code": o.payment_code, "status": o.status} for o in rows]


@router.get("/orders/{order_id}")
def get_order(order_id: int, user: User = Depends(require_roles(Role.customer)), db: Session = Depends(get_db)):
    o = db.get(Order, order_id)
    if not o or o.customer_id != user.id:
        raise HTTPException(404, "Order not found")
    return {"id": o.id, "invoice_no": o.invoice_no, "amount_minor": o.expected_pay_amount_minor,
            "payment_code": o.payment_code, "status": o.status,
            "pay_to": {"bank": settings.pay_to_bank, "account_name": settings.pay_to_account_name,
                       "account_number": settings.pay_to_account_number}}


@router.get("/orders/{order_id}/payment-claims", response_model=list[CustomerClaimOut])
def order_claims(order_id: int, user: User = Depends(require_roles(Role.customer)), db: Session = Depends(get_db)):
    o = db.get(Order, order_id)
    if not o or o.customer_id != user.id:
        raise HTTPException(404, "Order not found")
    rows = db.execute(select(PaymentClaim).where(PaymentClaim.order_id == order_id)
                      .order_by(PaymentClaim.id.desc())).scalars()
    return [_out(c, db) for c in rows]


@router.get("/my/data-export")
def my_data_export(user: User = Depends(require_roles(Role.customer)), db: Session = Depends(get_db)):
    from ..dsar import export_user
    audit.log(db, action="dsar.export", entity="user", entity_id=user.id, actor_id=user.id)
    db.commit()
    return export_user(db, user)
