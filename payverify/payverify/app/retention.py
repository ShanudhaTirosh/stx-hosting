"""Retention: delete personal data when its period ends, keep what fraud checks and accounting still need.

Deleted: proof image/PDF bytes, OCR text, the model's extracted fields, claimed sender name, SMS message text, old login attempts.
Kept: amounts, bank references, SHA-256/dHash of proofs (so a deleted receipt cannot be re-used), status history, audit log.
Only finished claims (verified/rejected) are touched; anything open is never purged.
Run:  python -m app.retention          (dry run, prints counts)
      python -m app.retention --apply  (do it; schedule daily with cron/systemd/compose)"""
import os
import sys
from datetime import timedelta

from sqlalchemy import delete, func, or_, select, and_
from sqlalchemy.orm import Session

from . import audit
from .config import settings
from .db import SessionLocal
from .models import AiAnalysis, ClaimStatus as S, LoginAttempt, OcrExtraction, PaymentClaim, ProofFile, SmsEvent, utcnow


def purge_claim(db: Session, c: PaymentClaim, now=None) -> int:
    """Delete one claim's personal data (files, OCR text, extracted fields, sender name). Keeps hashes and financial facts."""
    now = now or utcnow()
    n = 0
    for pf in c.files:
        if pf.purged_at:
            continue
        n += 1
        try:
            os.remove(os.path.join(settings.storage_dir, pf.storage_key))
        except FileNotFoundError:
            pass
        pf.purged_at = now
        for o in db.execute(select(OcrExtraction).where(OcrExtraction.proof_file_id == pf.id)).scalars():
            o.raw_text = ""
        for a in db.execute(select(AiAnalysis).where(AiAnalysis.proof_file_id == pf.id)).scalars():
            a.extracted = {"purged": True}      # reference_norm column is kept for duplicate detection
    c.claimed_sender_name = None
    c.purged_at = now
    return n


def run(db: Session, *, apply: bool = False, now=None) -> dict:
    now = now or utcnow()
    due = or_(
        and_(PaymentClaim.status == S.verified.value,
             func.coalesce(PaymentClaim.verified_at, PaymentClaim.created_at) < now - timedelta(days=settings.retention_verified_days)),
        and_(PaymentClaim.status == S.rejected.value,
             PaymentClaim.created_at < now - timedelta(days=settings.retention_rejected_days)))
    claims = db.execute(select(PaymentClaim).where(PaymentClaim.purged_at.is_(None), due)).scalars().all()
    files = 0
    for c in claims:
        files += sum(1 for pf in c.files if not pf.purged_at)
        if apply:
            purge_claim(db, c, now)
    sms_q = select(SmsEvent).where(SmsEvent.body != "", SmsEvent.created_at < now - timedelta(days=settings.retention_sms_body_days))
    sms_rows = db.execute(sms_q).scalars().all()
    attempts = db.scalar(select(func.count(LoginAttempt.id)).where(
        LoginAttempt.ts < now - timedelta(days=settings.retention_login_attempt_days)))
    if apply:
        for e in sms_rows:
            e.body = ""
        db.execute(delete(LoginAttempt).where(LoginAttempt.ts < now - timedelta(days=settings.retention_login_attempt_days)))
    result = {"applied": apply, "claims": len(claims), "proof_files": files, "sms_bodies": len(sms_rows), "login_attempts": attempts}
    if apply:
        audit.log(db, action="retention.run", entity="system", entity_id=None, detail=result)
        db.commit()
    return result


if __name__ == "__main__":
    with SessionLocal() as s:
        print(run(s, apply="--apply" in sys.argv))
