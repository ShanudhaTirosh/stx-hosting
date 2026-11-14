import datetime as dt
import io
import json
from unittest.mock import MagicMock, patch
import pytest
from PIL import Image, ImageDraw, ImageFilter
import fitz

from app.bank_import import parse_dt, parse_money
from app.config import settings
from app.db import SessionLocal
from app.extraction.ai import GeminiExtractor, Receipt
from app.extraction.gemini import (
    GeminiAPIError,
    GeminiQuotaExhaustedError,
    GeminiVisionClient,
    KeyRotator,
    RateLimiter,
    telemetry,
)
from app.extraction.preprocess import assess_image_quality, pdf_to_images, preprocess_image
from app.extraction.validate import validate_receipt
from app.files import sniff, validate_file_integrity
from app.models import BankStatementImport, BankTransaction, ClaimStatus, Order, PaymentClaim
from .conftest import new_order, submit


def create_slip_image(
    format: str = "PNG",
    bg_color: tuple = (255, 255, 255),
    text_color: tuple = (0, 0, 0),
    title: str = "COMMERCIAL BANK OF CEYLON",
    lines: list = None,
    size: tuple = (600, 750),
    blur: bool = False,
    exif_orientation: int = None,
) -> bytes:
    """Helper to generate realistic synthetic payment slip images in memory."""
    img = Image.new("RGB", size, color=bg_color)
    draw = ImageDraw.Draw(img)

    # Header banner
    draw.rectangle([10, 10, size[0] - 10, 70], fill=(0, 64, 128) if bg_color[0] > 100 else (30, 45, 65))
    draw.text((25, 30), title, fill=(255, 255, 255))

    y = 90
    if lines is None:
        lines = [
            ("Transaction Date", "14-Nov-2026 10:30:15"),
            ("Reference Number", "CEFT202611140088"),
            ("Transfer Channel", "CEFTS Instant Transfer"),
            ("Sender Name", "KASUN SILVA"),
            ("Sender Account", "8001****456"),
            ("Beneficiary Name", "PAYVERIFY DEMO STORE"),
            ("Beneficiary Account", "100987654321"),
            ("Beneficiary Bank", "COMMERCIAL BANK"),
            ("Amount Transferred", "LKR 15,000.00"),
            ("Status", "SUCCESSFUL / COMPLETED"),
            ("Remarks", "INV-2026-9900"),
        ]

    for label, val in lines:
        draw.text((30, y), f"{label}:", fill=text_color)
        draw.text((230, y), val, fill=text_color)
        draw.line([30, y + 25, size[0] - 30, y + 25], fill=(200, 200, 200) if bg_color[0] > 100 else (60, 60, 60))
        y += 45

    if blur:
        img = img.filter(ImageFilter.GaussianBlur(radius=6))

    bio = io.BytesIO()
    save_kwargs = {}
    if exif_orientation:
        exif = img.getexif()
        exif[274] = exif_orientation  # 274 is EXIF Orientation tag
        save_kwargs["exif"] = exif

    img.save(bio, format=format, **save_kwargs)
    return bio.getvalue()


def create_multipage_pdf_bytes(pages: list) -> bytes:
    """Helper to generate multi-page PDF e-receipts."""
    doc = fitz.open()
    for page_lines in pages:
        page = doc.new_page(width=595, height=842)  # A4
        y = 50
        page.insert_text((50, y), "OFFICIAL E-RECEIPT / DEPOSIT ADVICE", fontsize=14)
        y += 40
        for line in page_lines:
            page.insert_text((50, y), line, fontsize=10)
            y += 25
    pdf_bytes = doc.tobytes()
    doc.close()
    return pdf_bytes


# ---------------------------------------------------------------------------
# 1. Bank transfer receipt (PNG) - Standard Light Theme
# ---------------------------------------------------------------------------
def test_bank_transfer_png_light_theme():
    raw_bytes = create_slip_image(format="PNG", bg_color=(255, 255, 255), text_color=(20, 20, 20))
    assert raw_bytes.startswith(b"\x89PNG\r\n\x1a\n")

    # Integrity verification
    validate_file_integrity(raw_bytes, "image/png")
    assert sniff(raw_bytes) == "image/png"

    # Preprocessing
    proc_bytes, out_mime = preprocess_image(raw_bytes, "image/png")
    assert out_mime == "image/png"
    p_img = Image.open(io.BytesIO(proc_bytes))
    assert p_img.width <= 2048 and p_img.height <= 2048

    # Quality check
    metrics = assess_image_quality(p_img)
    assert metrics["sharpness"] > 0
    assert metrics["contrast"] > 20

    # Rule-based validation
    receipt = Receipt(
        reference="CEFT202611140088",
        amount="15,000.00",
        currency="LKR",
        datetime="2026-11-14 10:30:15",
        sender_name="KASUN SILVA",
        sender_bank="COMMERCIAL BANK",
        recipient_name="PAYVERIFY DEMO STORE",
        recipient_account_masked="******4321",
        status_text="SUCCESSFUL",
        channel="CEFTS",
    )
    order_dt = dt.datetime(2026, 11, 14, 10, 0, tzinfo=dt.timezone.utc)
    res = validate_receipt(
        receipt,
        expected_amount_minor=1500000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is True
    assert res.confidence == "high"
    assert "amount" in res.field_results
    assert res.field_results["amount"]["status"] == "match"


# ---------------------------------------------------------------------------
# 2. Mobile Banking Dark Mode App Screenshot (JPEG)
# ---------------------------------------------------------------------------
def test_mobile_banking_jpeg_dark_mode():
    raw_bytes = create_slip_image(
        format="JPEG",
        bg_color=(15, 20, 30),
        text_color=(230, 235, 245),
        title="HNB DIGITAL BANKING - CONFIRMATION",
    )
    validate_file_integrity(raw_bytes, "image/jpeg")
    assert sniff(raw_bytes) == "image/jpeg"

    # Preprocessing enhances dark mode contrast
    proc_bytes, out_mime = preprocess_image(raw_bytes, "image/jpeg")
    metrics = assess_image_quality(proc_bytes)
    assert metrics["contrast"] > 0

    receipt = Receipt(
        reference="HNB-FT-998822",
        amount="25,500.00",
        currency="LKR",
        datetime="2026-11-14 11:15:00",
        recipient_account_masked="******4321",
        status_text="COMPLETED",
    )
    order_dt = dt.datetime(2026, 11, 14, 11, 0, tzinfo=dt.timezone.utc)
    res = validate_receipt(
        receipt,
        expected_amount_minor=2550000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is True
    assert res.confidence == "high"


# ---------------------------------------------------------------------------
# 3. Mobile Wallet Receipt (WEBP)
# ---------------------------------------------------------------------------
def test_mobile_wallet_webp():
    raw_bytes = create_slip_image(
        format="WEBP",
        bg_color=(245, 248, 250),
        text_color=(10, 30, 60),
        title="FRIMI / EZ CASH TRANSFER ADVICE",
    )
    validate_file_integrity(raw_bytes, "image/webp")
    assert sniff(raw_bytes) == "image/webp"

    proc_bytes, out_mime = preprocess_image(raw_bytes, "image/webp")
    assert len(proc_bytes) > 0
    assert out_mime in ("image/png", "image/jpeg")

    receipt = Receipt(
        reference="EZCASH-771122",
        amount="3,250.00",
        currency="LKR",
        datetime="2026-11-14 12:00:00",
        recipient_account_masked="077***4321",
        status_text="SUCCESS",
    )
    order_dt = dt.datetime(2026, 11, 14, 11, 30, tzinfo=dt.timezone.utc)
    res = validate_receipt(
        receipt,
        expected_amount_minor=325000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is True
    assert res.confidence == "high"


# ---------------------------------------------------------------------------
# 4. ATM / CDM Physical Deposit Slip (Rotated & Phone Photo)
# ---------------------------------------------------------------------------
def test_atm_cdm_rotated_phone_photo():
    # EXIF orientation 6 = 90 degrees CCW (needs 90 CW transpose)
    raw_bytes = create_slip_image(
        format="JPEG",
        bg_color=(235, 230, 220),
        text_color=(40, 35, 30),
        title="BOC CDM CASH DEPOSIT MACHINE RECEIPT",
        lines=[
            ("Machine ID", "CDM-COLOMBO-04"),
            ("Date / Time", "14/11/2026 09:45"),
            ("STAN", "901234"),
            ("Account No", "0001234321"),
            ("Deposited Amount", "LKR 50,000.00"),
            ("Status", "SUCCESS"),
        ],
        size=(500, 700),
        exif_orientation=6,
    )
    validate_file_integrity(raw_bytes, "image/jpeg")

    proc_bytes, out_mime = preprocess_image(raw_bytes, "image/jpeg")
    p_img = Image.open(io.BytesIO(proc_bytes))
    # Auto-rotation should transpose dimensions from (500, 700) with orientation 6 to width > height (700, 500)
    assert p_img.width >= p_img.height

    receipt = Receipt(
        reference="901234",
        amount="50,000.00",
        currency="LKR",
        datetime="2026-11-14 09:45:00",
        recipient_account_masked="0001234321",
        status_text="SUCCESS",
    )
    order_dt = dt.datetime(2026, 11, 14, 9, 0, tzinfo=dt.timezone.utc)
    res = validate_receipt(
        receipt,
        expected_amount_minor=5000000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is True
    assert res.confidence == "high"


# ---------------------------------------------------------------------------
# 5. Multi-page PDF E-Receipt
# ---------------------------------------------------------------------------
def test_multipage_pdf_e_receipt():
    pdf_bytes = create_multipage_pdf_bytes([
        [
            "Bank: National Development Bank (NDB)",
            "Transaction Type: Interbank Fund Transfer (CEFTS)",
            "Transaction Reference: NDB202611149911",
            "Date: 14-Nov-2026 14:10:00",
        ],
        [
            "Sender: John Doe",
            "Beneficiary Account: *******4321",
            "Transfer Amount: LKR 80,000.00",
            "Transaction Status: SUCCESSFUL",
        ],
    ])
    validate_file_integrity(pdf_bytes, "application/pdf")

    # Verify PyMuPDF converts both pages
    pages = pdf_to_images(pdf_bytes, max_pages=3)
    assert len(pages) == 2
    for p_bytes, p_mime in pages:
        assert len(p_bytes) > 100
        assert p_mime == "image/png"


# ---------------------------------------------------------------------------
# 6. Multilingual Slip Parsing: Sinhala (රු. 15,500/=, අසාර්ථකයි)
# ---------------------------------------------------------------------------
def test_multilingual_sinhala_currency_and_status():
    # Parsing money notation with Sinhala currency prefix
    amt = parse_money("රු. 15,500/=")
    assert amt == 1550000

    amt2 = parse_money("රු.2,500.50")
    assert amt2 == 250050

    # Status failure detection in Sinhala
    failed_receipt = Receipt(
        reference="CEFT-SINHALA-01",
        amount="15,500.00",
        status_text="ගනුදෙනුව අසාර්ථකයි (Failed)",
        recipient_account_masked="******4321",
    )
    order_dt = dt.datetime.now(dt.timezone.utc)
    res = validate_receipt(
        failed_receipt,
        expected_amount_minor=1550000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is False
    assert "receipt_status_not_success" in res.reasons
    assert res.confidence == "low"


# ---------------------------------------------------------------------------
# 7. Multilingual Slip Parsing: Tamil (ரூ. 25,000/-, தோல்வி)
# ---------------------------------------------------------------------------
def test_multilingual_tamil_currency_and_status():
    amt = parse_money("ரூ. 25,000/-")
    assert amt == 2500000

    amt2 = parse_money("ரூ 100,000.00")
    assert amt2 == 10000000

    # Status failure detection in Tamil
    failed_receipt = Receipt(
        reference="CEFT-TAMIL-01",
        amount="25,000.00",
        status_text="பரிவர்த்தனை தோல்வி (Failed)",
        recipient_account_masked="******4321",
    )
    order_dt = dt.datetime.now(dt.timezone.utc)
    res = validate_receipt(
        failed_receipt,
        expected_amount_minor=2500000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is False
    assert "receipt_status_not_success" in res.reasons
    assert res.confidence == "low"


# ---------------------------------------------------------------------------
# 8. Bad Case: Blurry Low-Quality Slip Detection
# ---------------------------------------------------------------------------
def test_bad_case_blurry_slip():
    clean_bytes = create_slip_image(format="JPEG", blur=False)
    blur_bytes = create_slip_image(format="JPEG", blur=True)
    proc_clean, _ = preprocess_image(clean_bytes, "image/jpeg")
    proc_blur, _ = preprocess_image(blur_bytes, "image/jpeg")
    clean_metrics = assess_image_quality(proc_clean)
    blur_metrics = assess_image_quality(proc_blur)
    assert blur_metrics["sharpness"] < clean_metrics["sharpness"]

    # Receipt without extracted amount or ref flags low confidence
    incomplete_receipt = Receipt(
        reference=None,
        amount=None,
        status_text="unknown",
    )
    res = validate_receipt(
        incomplete_receipt,
        expected_amount_minor=500000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=dt.datetime.now(dt.timezone.utc),
    )
    assert res.valid is False
    assert "receipt_amount_missing" in res.reasons
    assert res.confidence == "low"


# ---------------------------------------------------------------------------
# 9. Bad Case: Wrong Amount Discrepancy
# ---------------------------------------------------------------------------
def test_bad_case_wrong_amount():
    receipt = Receipt(
        reference="CEFT-WRONG-AMT",
        amount="1,000.00",  # Slip is Rs 1,000
        currency="LKR",
        datetime="2026-11-14 10:00:00",
        recipient_account_masked="******4321",
        status_text="SUCCESS",
    )
    # Order expected Rs 10,000 (1,000,000 minor)
    res = validate_receipt(
        receipt,
        expected_amount_minor=1000000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=dt.datetime.now(dt.timezone.utc),
    )
    assert res.valid is False
    assert "receipt_amount_mismatch" in res.reasons
    assert res.confidence == "low"
    assert res.field_results["amount"]["status"] == "mismatch"


# ---------------------------------------------------------------------------
# 10. Bad Case: Wrong Recipient Account Discrepancy
# ---------------------------------------------------------------------------
def test_bad_case_wrong_recipient_account():
    receipt = Receipt(
        reference="CEFT-WRONG-ACC",
        amount="5,000.00",
        currency="LKR",
        recipient_account_masked="******9999",  # Transferred to someone else
        status_text="SUCCESS",
    )
    res = validate_receipt(
        receipt,
        expected_amount_minor=500000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=dt.datetime.now(dt.timezone.utc),
    )
    assert res.valid is False
    assert "recipient_account_mismatch" in res.reasons
    assert res.confidence == "low"
    assert res.field_results["recipient_account"]["status"] == "mismatch"


# ---------------------------------------------------------------------------
# 11. Bad Case: Expired Date Outside Allowed Window
# ---------------------------------------------------------------------------
def test_bad_case_expired_date():
    receipt = Receipt(
        reference="CEFT-OLD-DATE",
        amount="5,000.00",
        currency="LKR",
        datetime="2026-09-01 10:00:00",  # 2 months old
        recipient_account_masked="******4321",
        status_text="SUCCESS",
    )
    order_dt = dt.datetime(2026, 11, 14, 10, 0, tzinfo=dt.timezone.utc)
    res = validate_receipt(
        receipt,
        expected_amount_minor=500000,
        expected_currency="LKR",
        expected_recipient_account_tail="4321",
        order_created_at=order_dt,
    )
    assert res.valid is False
    assert "receipt_date_expired" in res.reasons or "receipt_date_before_order" in res.reasons


# ---------------------------------------------------------------------------
# 12. Corrupted and Unsupported File Rejection
# ---------------------------------------------------------------------------
def test_corrupted_and_unsupported_file_rejection():
    from fastapi import HTTPException

    # Unsupported MIME type
    with pytest.raises(HTTPException) as exc1:
        validate_file_integrity(b"plain text document " * 20, "text/plain")
    assert exc1.value.status_code == 400
    assert "Unsupported file type" in exc1.value.detail

    # Corrupted image bytes (starts with PNG header but has garbage data)
    corrupted_png = b"\x89PNG\r\n\x1a\n" + b"CorruptedDataTruncatedStream" * 50
    with pytest.raises(HTTPException) as exc2:
        validate_file_integrity(corrupted_png, "image/png")
    assert exc2.value.status_code == 400
    assert "Corrupted or invalid image" in exc2.value.detail


# ---------------------------------------------------------------------------
# 13. Multi-Key Rotation and Rate Limiting
# ---------------------------------------------------------------------------
def test_multi_key_rotation_and_rate_limiting():
    # Test KeyRotator
    rotator = KeyRotator(["key_alpha", "key_beta", "key_gamma"])
    st = rotator.get_status()
    assert st["total_keys"] == 3
    assert st["healthy_keys"] == 3

    # Round robin
    k1, count1 = rotator.get_key()
    k2, count2 = rotator.get_key()
    assert k1 != k2
    assert count1 == 3

    # Simulate 429 quota exhaustion on key_beta
    rotator.mark_cooldown(k2, custom_cooldown=60.0)
    st2 = rotator.get_status()
    assert st2["healthy_keys"] == 2
    assert st2["in_cooldown"] == 1
    next_k, _ = rotator.get_key()
    assert next_k != k2

    # Rate limiter token bucket throttle
    limiter = RateLimiter(rpm=60)
    success = limiter.acquire(timeout=2.0)
    assert success is True


# ---------------------------------------------------------------------------
# 14. Platform Integration: Configurable Auto-Approval Policy
# ---------------------------------------------------------------------------
def test_platform_auto_approval_policy(client, world):
    """Verifies that auto_approve_high_confidence config controls whether
    a high-confidence slip auto-approves or holds for manual review.
    """
    import base64
    from .conftest import PNG, PNG2

    ids, h = world
    orig_key = settings.external_api_key
    settings.external_api_key = "test-auto-approve-key"
    headers = {"X-API-Key": "test-auto-approve-key"}

    b64_proof = base64.b64encode(PNG).decode("ascii")

    # Policy 1: Default (Safe) -> auto_approve_high_confidence=False
    # High confidence extraction without ledger match stays in REVIEW
    settings.auto_approve_high_confidence = False
    payload_safe = {
        "invoice_no": "INV-SAFE-01",
        "amount_minor": 100000,
        "currency": "LKR",
        "customer": {"name": "Kamal", "email": "kamal@safe.lk"},
        "payment": {"reference": "REF-SAFE-01", "bank_name": "Commercial Bank"},
        "proof_base64": b64_proof,
        "proof_mime": "image/png",
    }
    r1 = client.post("/api/v1/external/proofs/upload-and-review", headers=headers, json=payload_safe)
    assert r1.status_code == 200
    res1 = r1.json()
    assert res1["status"] in ("PENDING_VERIFICATION", "MANUAL_REVIEW")
    assert res1["is_approved"] is False

    # Policy 2: Merchant Opt-In -> auto_approve_high_confidence=True
    # Mock AI to return a high-confidence valid receipt
    orig_ext = settings.extraction_enabled
    orig_ai = settings.ai_provider
    settings.extraction_enabled = True
    settings.ai_provider = "gemini"
    settings.auto_approve_high_confidence = True
    payload_optin = {
        "invoice_no": "INV-OPTIN-02",
        "amount_minor": 150000,
        "currency": "LKR",
        "customer": {"name": "Sunil", "email": "sunil@optin.lk"},
        "payment": {"reference": "REF-OPTIN-02", "bank_name": "Commercial Bank"},
        "proof_base64": base64.b64encode(PNG2).decode("ascii"),
        "proof_mime": "image/png",
    }

    # Patch extraction to produce a high-confidence matching receipt
    mock_extractor = MagicMock()
    mock_extractor.model = "gemini-2.5-flash"
    mock_extractor.extract.return_value = Receipt(
        reference="REF-OPTIN-02",
        amount="1,500.00",
        currency="LKR",
        status_text="SUCCESS",
        recipient_account_masked="******4321",
        field_confidence={
            "amount": 0.98,
            "reference": 0.95,
            "datetime": 0.92,
            "recipient_account_masked": 0.95,
        },
    )
    with patch("app.extraction.pipeline.build_extractor", return_value=mock_extractor):
        r2 = client.post("/api/v1/external/proofs/upload-and-review", headers=headers, json=payload_optin)
        assert r2.status_code == 200
        res2 = r2.json()
        assert res2["status"] == "APPROVED"
        assert res2["is_approved"] is True
        assert res2["verdict"]["decision"] == "verified"
        assert res2["verdict"]["confidence_level"] == "high"

    # Restore settings
    settings.extraction_enabled = orig_ext
    settings.ai_provider = orig_ai
    settings.auto_approve_high_confidence = False
    settings.external_api_key = orig_key


# ---------------------------------------------------------------------------
# 15. Comprehensive Accuracy Benchmark & Confusion Matrix
# ---------------------------------------------------------------------------
def test_accuracy_benchmark():
    """Evaluates accuracy across a balanced battery of 20 test cases:
    - 10 Legitimate slips across different banks, languages, formats, and channels
    - 10 Fraudulent / Invalid slips (blurry, tampered, wrong amounts, bad accounts, expired)
    Calculates precision, recall, specificity, and total accuracy.
    """
    now = dt.datetime.now(dt.timezone.utc)

    # 10 Legitimate cases
    ground_truth_positives = [
        # 1. Commercial Bank CEFTS (PNG)
        Receipt(reference="CEFT-POS-1", amount="5,000.00", currency="LKR", recipient_account_masked="*4321", status_text="SUCCESS", datetime=now.isoformat()),
        # 2. HNB Mobile Banking Dark Mode (JPG)
        Receipt(reference="HNB-POS-2", amount="12,500.00", currency="LKR", recipient_account_masked="*4321", status_text="COMPLETED", datetime=now.isoformat()),
        # 3. Sampath Vishwa Web e-Receipt (PDF)
        Receipt(reference="SMP-POS-3", amount="7,500.00", currency="LKR", recipient_account_masked="*4321", status_text="SUCCESSFUL", datetime=now.isoformat()),
        # 4. BOC CDM Slip (Physical photo)
        Receipt(reference="BOC-POS-4", amount="50,000.00", currency="LKR", recipient_account_masked="*4321", status_text="DEPOSITED", datetime=now.isoformat()),
        # 5. FriMi Wallet transfer (WEBP)
        Receipt(reference="FRM-POS-5", amount="2,000.00", currency="LKR", recipient_account_masked="*4321", status_text="SUCCESS", datetime=now.isoformat()),
        # 6. Dialog eZ Cash (WEBP)
        Receipt(reference="EZC-POS-6", amount="3,400.00", currency="LKR", recipient_account_masked="*4321", status_text="PAID", datetime=now.isoformat()),
        # 7. Peoples Bank Digital Wave (PNG)
        Receipt(reference="PB-POS-7", amount="18,000.00", currency="LKR", recipient_account_masked="*4321", status_text="SUCCESSFUL", datetime=now.isoformat()),
        # 8. Seylan Bank Mobile (JPG)
        Receipt(reference="SEY-POS-8", amount="9,900.00", currency="LKR", recipient_account_masked="*4321", status_text="COMPLETED", datetime=now.isoformat()),
        # 9. Nations Trust Bank SLIP (JPG)
        Receipt(reference="NTB-POS-9", amount="45,000.00", currency="LKR", recipient_account_masked="*4321", status_text="SUCCESS", datetime=now.isoformat()),
        # 10. Commercial Bank Sinhala Slip (PNG)
        Receipt(reference="CEFT-SIN-10", amount="15,000.00", currency="LKR", recipient_account_masked="*4321", status_text="සාර්ථකයි", datetime=now.isoformat()),
    ]

    # Expected parameters for positives
    pos_expectations = [
        (500000, "LKR", "4321"),
        (1250000, "LKR", "4321"),
        (750000, "LKR", "4321"),
        (5000000, "LKR", "4321"),
        (200000, "LKR", "4321"),
        (340000, "LKR", "4321"),
        (1800000, "LKR", "4321"),
        (990000, "LKR", "4321"),
        (4500000, "LKR", "4321"),
        (1500000, "LKR", "4321"),
    ]

    # 10 Invalid / Fraudulent cases
    ground_truth_negatives = [
        # 1. Underpaid amount (Rs 1,000 instead of Rs 5,000)
        (Receipt(reference="NEG-1", amount="1,000.00", recipient_account_masked="*4321", status_text="SUCCESS"), 500000, "4321", now),
        # 2. Overpaid/mismatched amount (Rs 15,000 instead of Rs 10,000)
        (Receipt(reference="NEG-2", amount="15,000.00", recipient_account_masked="*4321", status_text="SUCCESS"), 1000000, "4321", now),
        # 3. Wrong recipient account (transferred to fraudster account)
        (Receipt(reference="NEG-3", amount="5,000.00", recipient_account_masked="*9999", status_text="SUCCESS"), 500000, "4321", now),
        # 4. Failed transaction status
        (Receipt(reference="NEG-4", amount="5,000.00", recipient_account_masked="*4321", status_text="FAILED"), 500000, "4321", now),
        # 5. Sinhala failed status (අසාර්ථකයි)
        (Receipt(reference="NEG-5", amount="5,000.00", recipient_account_masked="*4321", status_text="අසාර්ථකයි"), 500000, "4321", now),
        # 6. Tamil failed status (தோல்வி)
        (Receipt(reference="NEG-6", amount="5,000.00", recipient_account_masked="*4321", status_text="தோல்வி"), 500000, "4321", now),
        # 7. Stale date (60 days ago)
        (Receipt(reference="NEG-7", amount="5,000.00", recipient_account_masked="*4321", status_text="SUCCESS", datetime=(now - dt.timedelta(days=60)).isoformat()), 500000, "4321", now),
        # 8. Missing amount (unreadable/blurry)
        (Receipt(reference="NEG-8", amount=None, recipient_account_masked="*4321", status_text="SUCCESS"), 500000, "4321", now),
        # 9. Tampered layout anomaly flagged
        (Receipt(reference="NEG-9", amount="5,000.00", recipient_account_masked="*4321", status_text="SUCCESS", anomalies=["photoshop_edited", "font_mismatch"]), 500000, "4321", now),
        # 10. Missing reference ID
        (Receipt(reference=None, amount="5,000.00", recipient_account_masked="*4321", status_text="SUCCESS"), 500000, "4321", now),
    ]

    tp = sum(
        1
        for r, (amt, cur, acc) in zip(ground_truth_positives, pos_expectations)
        if validate_receipt(r, amt, cur, acc, order_created_at=now).valid is True
    )
    fn = len(ground_truth_positives) - tp

    tn = sum(
        1
        for r, amt, acc, o_dt in ground_truth_negatives
        if validate_receipt(r, amt, "LKR", acc, order_created_at=o_dt).valid is False
    )
    fp = len(ground_truth_negatives) - tn

    total = len(ground_truth_positives) + len(ground_truth_negatives)
    accuracy = (tp + tn) / total * 100.0
    precision = tp / (tp + fp) * 100.0 if (tp + fp) else 0
    recall = tp / (tp + fn) * 100.0 if (tp + fn) else 0

    print(f"\n================ ACCURACY BENCHMARK ================")
    print(f"Total Slips Tested: {total}")
    print(f"True Positives: {tp}/{len(ground_truth_positives)}")
    print(f"True Negatives: {tn}/{len(ground_truth_negatives)}")
    print(f"False Positives: {fp} (Zero Tolerance Target: 0)")
    print(f"False Negatives: {fn}")
    print(f"Accuracy Rate: {accuracy:.1f}%")
    print(f"Precision: {precision:.1f}%")
    print(f"Recall: {recall:.1f}%")
    print(f"====================================================\n")

    assert fp == 0, f"False positives detected: {fp}"
    assert accuracy >= 95.0, f"Accuracy {accuracy}% below 95% target"
