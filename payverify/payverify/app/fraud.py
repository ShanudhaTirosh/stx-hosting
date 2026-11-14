"""Intake-time fraud checks (Phase 1: duplicates and reuse). Image forensics arrives in Phase 3."""
import re

from sqlalchemy import select
from sqlalchemy.orm import Session

from .models import ClaimStatus as S, FraudSignal, PaymentClaim, ProofFile


def norm_ref(ref: str | None) -> str:
    return re.sub(r"[^A-Z0-9]", "", (ref or "").upper())


def run_intake_checks(db: Session, claim: PaymentClaim, sha256s: list[str]) -> list[FraudSignal]:
    signals: list[FraudSignal] = []

    def add(code: str, sev: str, **detail):
        s = FraudSignal(claim_id=claim.id, rule_code=code, severity=sev, detail=detail)
        db.add(s)
        signals.append(s)

    # Same file already used on another claim.
    for h in sha256s:
        rows = db.execute(select(PaymentClaim, ProofFile).join(ProofFile, ProofFile.claim_id == PaymentClaim.id)
                          .where(ProofFile.sha256 == h, PaymentClaim.id != claim.id)).all()
        for other, _pf in rows:
            if other.status == S.rejected.value and other.customer_id == claim.customer_id:
                continue  # customer legitimately resubmitting their own rejected proof
            sev = "block" if other.customer_id != claim.customer_id or other.order_id != claim.order_id else "warn"
            add("duplicate_file", sev, other_claim=other.id, same_customer=other.customer_id == claim.customer_id)

    # Same transaction reference already claimed elsewhere (not rejected).
    ref = norm_ref(claim.claimed_reference)
    if ref:
        others = db.execute(select(PaymentClaim).where(PaymentClaim.id != claim.id,
                                                       PaymentClaim.status != S.rejected.value)).scalars()
        for o in others:
            if norm_ref(o.claimed_reference) == ref:
                add("duplicate_reference", "block", other_claim=o.id)
                break
    return signals


def has_block(db: Session, claim_id: int) -> bool:
    return db.execute(select(FraudSignal.id).where(FraudSignal.claim_id == claim_id,
                                                   FraudSignal.severity == "block")).first() is not None
