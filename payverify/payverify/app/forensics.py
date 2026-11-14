"""Image/PDF tamper heuristics. These are HEURISTICS: they catch careless edits and reuse, not skilled forgeries.
Nothing here can verify a payment, and only exact-file reuse and near-duplicates of another customer's proof escalate."""
import io
import re

from sqlalchemy import select
from sqlalchemy.orm import Session

from .models import FraudSignal, PaymentClaim, ProofFile

EDITORS = re.compile(r"photoshop|gimp|canva|snapseed|pixlr|picsart|paint\.net|affinity|lightroom|fotor|photopea|illustrator", re.I)


def dhash(data: bytes, mime: str) -> str | None:
    """64-bit difference hash (hex): tolerant of resizing/recompression, so a re-saved screenshot still matches."""
    if mime == "application/pdf":
        return None
    try:
        from PIL import Image
        im = Image.open(io.BytesIO(data)).convert("L").resize((9, 8))
        px = im.tobytes()   # mode L: one byte per pixel
        bits = "".join("1" if px[r * 9 + c] > px[r * 9 + c + 1] else "0" for r in range(8) for c in range(8))
        return f"{int(bits, 2):016x}"
    except Exception:
        return None


def hamming(a: str, b: str) -> int:
    return bin(int(a, 16) ^ int(b, 16)).count("1")


def _image_flags(data: bytes) -> list[tuple[str, str, dict]]:
    out = []
    try:
        from PIL import Image
        im = Image.open(io.BytesIO(data))
        exif = im.getexif()
        software = str(exif.get(0x0131, "") or im.info.get("Software", ""))
        if EDITORS.search(software):
            out.append(("edited_with_editor", "warn", {"software": software[:60]}))
        if im.format == "JPEG" and not exif:
            out.append(("no_camera_metadata", "info", {}))   # normal for screenshots; only a weak hint
        w, h = im.size
        if min(w, h) < 300:
            out.append(("tiny_image", "info", {"size": [w, h]}))
    except Exception:
        out.append(("image_unreadable", "warn", {}))
    return out


def _pdf_flags(data: bytes) -> list[tuple[str, str, dict]]:
    out = []
    head = data[:2_000_000]
    m = re.search(rb"/Producer\s*\((.{0,80}?)\)", head) or re.search(rb"/Creator\s*\((.{0,80}?)\)", head)
    if m and EDITORS.search(m.group(1).decode("latin1")):
        out.append(("edited_with_editor", "warn", {"software": m.group(1).decode("latin1")[:60]}))
    if head.count(b"%%EOF") > 1:
        out.append(("pdf_modified_after_creation", "warn", {}))   # incremental update = edited after being generated
    return out


def analyse_files(db: Session, claim: PaymentClaim, files: list[tuple[str, str | None, bytes]]) -> list[FraudSignal]:
    sigs: list[FraudSignal] = []
    for mime, dh, data in files:
        flags = _pdf_flags(data) if mime == "application/pdf" else _image_flags(data)
        if dh:  # visually near-identical image already on another customer's claim
            rows = db.execute(select(ProofFile.claim_id, ProofFile.dhash).where(
                ProofFile.dhash.is_not(None), ProofFile.claim_id != claim.id)).all()
            for cid, other in rows:
                if hamming(dh, other) <= 2:
                    other_claim = db.get(PaymentClaim, cid)
                    if other_claim and other_claim.customer_id != claim.customer_id:
                        flags.append(("near_duplicate_image", "warn", {"other_claim": cid}))
                        break
        for code, sev, detail in flags:
            s = FraudSignal(claim_id=claim.id, rule_code=code, severity=sev, detail=detail)
            db.add(s)
            sigs.append(s)
    return sigs
