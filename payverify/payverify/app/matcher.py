"""Deterministic reconciliation. No AI score ever reaches this module's decision."""
from datetime import timedelta
from difflib import SequenceMatcher

from sqlalchemy import select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from . import audit
from .config import settings
from .fraud import has_block, norm_ref
from .models import Allocation, BankTransaction, ClaimStatus as S, Order, PaymentClaim
from .state import transition


def _is_strong(txn: BankTransaction, claim: PaymentClaim, order: Order) -> list[str]:
    """Return the identity signals linking this credit to this claim (empty = weak)."""
    sig = []
    hay = norm_ref(txn.remarks)
    ref = norm_ref(claim.claimed_reference)
    if ref and len(ref) >= 4 and (ref == norm_ref(txn.bank_txn_ref) or ref in hay):
        sig.append("reference")
    if norm_ref(order.payment_code) in hay:
        sig.append("payment_code")
    if order.unique_amount:
        sig.append("unique_amount")
    if txn.bank_txn_ref and ref and (ref == norm_ref(txn.bank_txn_ref) or ref in norm_ref(txn.bank_txn_ref) or norm_ref(txn.bank_txn_ref) in ref):
        if "reference" not in sig:
            sig.append("reference")
    for pf in getattr(claim, "files", []):
        for ai in getattr(pf, "ai_analyses", []):
            ext_ref = norm_ref(ai.extracted_reference_norm or "")
            if ext_ref and len(ext_ref) >= 4 and (ext_ref == norm_ref(txn.bank_txn_ref) or ext_ref in hay):
                if "ai_reference" not in sig:
                    sig.append("ai_reference")
    return sig


def _candidates(db: Session, claim: PaymentClaim, order: Order) -> list[BankTransaction]:
    lo = order.created_at - timedelta(days=settings.match_window_before_days)
    hi = claim.created_at + timedelta(days=settings.match_window_after_days)
    allocated = select(Allocation.bank_transaction_id)
    q = select(BankTransaction).where(
        BankTransaction.direction == "credit",
        BankTransaction.amount_minor == order.expected_pay_amount_minor,
        BankTransaction.value_dt >= lo, BankTransaction.value_dt <= hi,
        BankTransaction.id.not_in(allocated)).order_by(BankTransaction.value_dt)
    return list(db.execute(q).scalars())


def try_match_claim(db: Session, claim: PaymentClaim) -> str:
    """Returns: verified | manual_review | waiting."""
    if claim.status != S.awaiting_bank_confirmation.value:
        return "skipped"
    order = db.get(Order, claim.order_id)
    if claim.claimed_amount_minor != order.expected_pay_amount_minor:
        transition(db, claim, S.manual_review, reason="amount_mismatch")
        return "manual_review"
    cands = _candidates(db, claim, order)
    if not cands:
        return "waiting"
    strong = [(t, _is_strong(t, claim, order)) for t in cands]
    strong = [(t, s) for t, s in strong if s]
    if len(strong) == 1 and not has_block(db, claim.id):
        txn, signals = strong[0]
        try:
            with db.begin_nested():
                locked = db.execute(select(BankTransaction).where(BankTransaction.id == txn.id)
                                    .with_for_update()).scalar_one()
                if db.execute(select(Allocation.id).where(Allocation.bank_transaction_id == locked.id)).first():
                    raise IntegrityError("already allocated", None, None)
                db.add(Allocation(bank_transaction_id=locked.id, claim_id=claim.id,
                                  amount_minor=locked.amount_minor))
                db.flush()
        except IntegrityError:
            transition(db, claim, S.manual_review, reason="txn_already_allocated")
            return "manual_review"
        sim = None
        if claim.claimed_sender_name and txn.counterparty_name:
            sim = round(SequenceMatcher(None, claim.claimed_sender_name.lower(),
                                        txn.counterparty_name.lower()).ratio(), 2)
        transition(db, claim, S.verified, basis="bank_match", reason="bank_match",
                   extra={"bank_transaction_id": txn.id, "signals": signals, "sender_similarity": sim})
        return "verified"
    # Candidates exist but identity is not unambiguous, or a blocking fraud signal exists: a human decides.
    reason = "fraud_signal" if has_block(db, claim.id) else "ambiguous_match" if strong else "weak_match"
    transition(db, claim, S.manual_review, reason=reason, extra={"candidate_ids": [t.id for t in cands]})
    return "manual_review"


def run_all_awaiting(db: Session) -> dict:
    out = {"verified": 0, "manual_review": 0, "waiting": 0}
    for c in db.execute(select(PaymentClaim).where(
            PaymentClaim.status == S.awaiting_bank_confirmation.value)).scalars().all():
        r = try_match_claim(db, c)
        if r in out:
            out[r] += 1
    return out
