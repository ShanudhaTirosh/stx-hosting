import logging
import os

from sqlalchemy import select
from sqlalchemy.orm import Session

from .. import audit
from ..config import settings
from ..db import SessionLocal
from ..files import read_plain
from ..fraud import norm_ref
from ..matcher import try_match_claim
from ..sms import corroborate_claim
from ..models import AiAnalysis, ClaimStatus as S, FraudSignal, OcrExtraction, Order, PaymentClaim, ProofFile
from ..state import transition
from . import ai as ai_mod
from .engines import ExtractionError, build_ocr, to_images
from .validate import validate

log = logging.getLogger("payverify.extraction")


def build_extractor():  # indirection so tests can swap in a fake
    return ai_mod.build_extractor()


def process_claim(db: Session, claim_id: int) -> None:
    """PROCESSING -> AWAITING_BANK_CONFIRMATION, or MANUAL_REVIEW if the receipt itself contradicts the claim.
    Never produces VERIFIED: extraction output is only ever a fraud/consistency signal."""
    claim = db.get(PaymentClaim, claim_id)
    if not claim or claim.status != S.processing.value:
        return
    order = db.get(Order, claim.order_id)
    signals: list[tuple[str, str, dict]] = []
    best_conf = 0.0
    try:
        extractor = build_extractor()
        if extractor is None:
            raise ExtractionError("no AI provider configured")
        ocr = build_ocr(settings.ocr_engine)
        for pf in claim.files:
            pages = to_images(read_plain(pf.storage_key), pf.mime)
            text = ""
            try:
                text = "\n".join(ocr.read(b) for b, _ in pages)
            except ExtractionError as e:
                signals.append(("ocr_unavailable", "info", {"why": str(e)[:100]}))
            db.add(OcrExtraction(proof_file_id=pf.id, engine=ocr.name, raw_text=text))
            receipt = extractor.extract(pages, text)
            sigs, score = validate(receipt, claim, order, text)
            signals += sigs
            best_conf = max(best_conf, score)
            db.add(AiAnalysis(proof_file_id=pf.id, model=extractor.model, extracted=receipt.model_dump(),
                              overall_confidence=score, extracted_reference_norm=norm_ref(receipt.reference) or None))
            db.flush()
            if not claim.claimed_reference and receipt.reference:
                claim.claimed_reference = receipt.reference.strip()
            ref = norm_ref(receipt.reference)
            if len(ref) >= 6:  # same receipt re-photographed / reused: its printed reference is already on file
                other = db.execute(select(ProofFile.claim_id).join(AiAnalysis, AiAnalysis.proof_file_id == ProofFile.id)
                                   .where(AiAnalysis.extracted_reference_norm == ref,
                                          ProofFile.claim_id != claim.id)).first()
                if other:
                    signals.append(("duplicate_receipt_reference", "block", {"other_claim": other[0]}))
    except (ExtractionError, OSError) as e:
        # No receipt reading must not block the bank-side path: the credit is what verifies.
        log.warning("extraction failed for claim %s: %s", claim_id, e)
        signals.append(("extraction_failed", "info", {"why": str(e)[:120]}))

    for code, sev, detail in signals:
        db.add(FraudSignal(claim_id=claim.id, rule_code=code, severity=sev, detail=detail))
    blocks = [c for c, s, _ in signals if s == "block"]
    audit.log(db, action="claim.extraction", entity="claim", entity_id=claim.id,
              detail={"confidence": best_conf, "signals": [c for c, _, _ in signals]})
    if blocks:
        transition(db, claim, S.manual_review, reason=blocks[0])
    else:
        transition(db, claim, S.awaiting_bank_confirmation)
        corroborate_claim(db, claim)
        try_match_claim(db, claim)
    db.commit()


def run_in_background(claim_id: int) -> None:
    with SessionLocal() as db:
        try:
            process_claim(db, claim_id)
        except Exception:  # last resort: never leave a claim stuck in PROCESSING
            log.exception("pipeline crashed for claim %s", claim_id)
            db.rollback()
            c = db.get(PaymentClaim, claim_id)
            if c and c.status == S.processing.value:
                transition(db, c, S.manual_review, reason="extraction_crashed")
                db.commit()
