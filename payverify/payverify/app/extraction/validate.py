"""Deterministic checks on extracted receipt fields with tolerance rules,
multilingual status recognition, and structured per-field validation results.
"""
import re
from datetime import datetime, timedelta, timezone
from typing import Any, Dict, List, Optional, Tuple

from ..bank_import import ImportError_, parse_dt, parse_money
from ..config import settings
from ..fraud import norm_ref
from ..models import Order, PaymentClaim
from .ai import Receipt

FAIL_WORDS = re.compile(
    r"fail|declin|reject|unsuccess|pending|reversed|error|cancel|"
    r"ප්‍රතික්ෂේප|අසාර්ථක|අවලංගු|දෝෂ|"
    r"தோல்வி|நிராகரி|ரத்து|பிழை",
    re.I
)
SUCCESS_WORDS = re.compile(
    r"success|complete|approved|accepted|paid|processed|"
    r"සාර්ථක|ගෙවන ලදී|"
    r"வெற்றி|செலுத்தப்பட்டது",
    re.I
)
KEY_FIELDS = ["reference", "amount", "datetime", "recipient_account_masked"]

Signal = Tuple[str, str, dict]  # (rule_code, severity, detail)


def _aware(d: datetime) -> datetime:
    return d if d.tzinfo else d.replace(tzinfo=timezone.utc)


def _digits(s: str | None) -> str:
    return re.sub(r"\D", "", s or "")


class ValidationResult(tuple):
    """Tuple subclass returning (signals, score) for 100% backward compatibility
    while exposing detailed per-field results, confidence levels, and methods.
    """
    signals: List[Signal]
    score: float
    confidence_level: str
    field_results: Dict[str, Any]
    verification_methods: List[str]
    reason: str

    def __new__(cls, signals: List[Signal], score: float,
                confidence_level: str = "medium",
                field_results: Optional[Dict[str, Any]] = None,
                verification_methods: Optional[List[str]] = None,
                reason: str = ""):
        inst = super().__new__(cls, (signals, score))
        inst.signals = signals
        inst.score = score
        inst.confidence_level = confidence_level
        inst.field_results = field_results or {}
        inst.verification_methods = verification_methods or ["gemini_vision", "rule_tolerance"]
        inst.reason = reason
        return inst

    @property
    def valid(self) -> bool:
        has_block = any(s == "block" for _, s, _ in self.signals)
        amount_mismatch = any(code == "receipt_amount_mismatch" for code, _, _ in self.signals)
        recipient_mismatch = any(code == "recipient_account_mismatch" for code, _, _ in self.signals)
        status_failed = any(code == "receipt_status_not_success" for code, _, _ in self.signals)
        amount_missing = any(code == "receipt_amount_missing" for code, _, _ in self.signals)
        date_expired = any(code == "receipt_date_expired" for code, _, _ in self.signals)
        return not (has_block or amount_mismatch or recipient_mismatch or status_failed or amount_missing or date_expired) and self.confidence_level in ("high", "medium")

    @property
    def confidence(self) -> str:
        return self.confidence_level

    @property
    def reasons(self) -> List[str]:
        return [code for code, _, _ in self.signals]


def validate(r: Receipt, claim: PaymentClaim, order: Order, ocr_text: str) -> ValidationResult:
    sig: List[Signal] = []
    conf = r.field_confidence or {}
    field_results: Dict[str, Any] = {}
    methods: List[str] = ["gemini_vision", "rule_tolerance"]
    if ocr_text and ocr_text.strip():
        methods.append("ocr_crosscheck")

    # 1. Amount: parse with currency tolerance, compare with expected order amount and OCR text
    amt = None
    try:
        amt = parse_money(r.amount)
    except ImportError_:
        sig.append(("receipt_amount_unreadable", "warn", {"raw": r.amount}))
    if amt is None and r.amount is None:
        sig.append(("receipt_amount_missing", "warn", {}))

    amount_status = "missing"
    if amt is not None:
        expected_amt = order.expected_pay_amount_minor if order else claim.claimed_amount_minor
        if amt == claim.claimed_amount_minor or (order and amt == order.expected_pay_amount_minor):
            amount_status = "match"
        elif order and order.unique_amount and abs(amt - order.amount_minor) < 100:
            # Tolerated matching base amount when unique cents were assigned
            amount_status = "match_base_with_tolerance"
        else:
            amount_status = "mismatch"
            sig.append(("receipt_amount_mismatch", "block", {"receipt": amt, "claimed": claim.claimed_amount_minor}))

        # Cross-check against OCR text if available
        if ocr_text.strip():
            flat = re.sub(r"[\s,]", "", ocr_text)
            if f"{amt / 100:.2f}" not in flat and f"{amt // 100}" not in flat:
                sig.append(("amount_not_in_ocr", "warn", {}))

    field_results["amount"] = {
        "status": amount_status,
        "extracted_minor": amt,
        "raw": r.amount,
        "confidence": conf.get("amount", 0.0),
    }

    # 2. Recipient account: check against configured tail or pay_to_account_number
    tail = settings.bank_account_tail
    expected_acc = settings.pay_to_account_number
    shown = _digits(r.recipient_account_masked)
    acc_status = "missing"
    if tail:
        if not shown:
            acc_status = "missing"
            sig.append(("recipient_account_missing", "info", {}))
        elif shown.endswith(tail[-len(shown):] if len(shown) < len(tail) else tail):
            acc_status = "match"
        elif expected_acc and _digits(expected_acc).endswith(shown):
            acc_status = "match_partial"
        else:
            acc_status = "mismatch"
            sig.append(("recipient_account_mismatch", "block", {"shown": shown, "expected_tail": tail}))
    field_results["recipient_account"] = {
        "status": acc_status,
        "extracted": r.recipient_account_masked,
        "digits": shown,
        "confidence": conf.get("recipient_account_masked", 0.0),
    }

    # 3. Status text check: support English, Sinhala, and Tamil
    status_state = "unknown"
    if r.status_text:
        if FAIL_WORDS.search(r.status_text):
            status_state = "failed"
            sig.append(("receipt_status_not_success", "block", {"status": r.status_text[:60]}))
        elif SUCCESS_WORDS.search(r.status_text):
            status_state = "successful"
        else:
            status_state = "other"
    else:
        sig.append(("receipt_status_missing", "info", {}))
    field_results["status"] = {
        "state": status_state,
        "extracted": r.status_text,
        "confidence": conf.get("status_text", 0.0),
    }

    # 4. Date and time check: check formatting, future dates, and order creation window
    date_status = "missing"
    if r.datetime:
        try:
            d = parse_dt(r.datetime)
            now = datetime.now(timezone.utc)
            if d > now + timedelta(minutes=10):
                sig.append(("receipt_date_in_future", "warn", {"extracted": r.datetime}))
                date_status = "future_date"
            elif order and d < _aware(order.created_at) - timedelta(days=30):
                sig.append(("receipt_date_expired", "block", {"extracted": r.datetime}))
                date_status = "expired_date"
            elif order and d < _aware(order.created_at) - timedelta(days=settings.match_window_before_days):
                sig.append(("receipt_date_before_order", "warn", {"extracted": r.datetime}))
                date_status = "stale_date"
            else:
                date_status = "valid"
        except ImportError_:
            sig.append(("receipt_date_unreadable", "info", {"raw": r.datetime}))
            date_status = "unreadable"
    field_results["datetime"] = {
        "status": date_status,
        "extracted": r.datetime,
        "confidence": conf.get("datetime", 0.0),
    }

    # 5. Reference & Payment Code comparison
    cr, rr = norm_ref(claim.claimed_reference), norm_ref(r.reference)
    ref_status = "not_provided"
    if cr and rr:
        if cr in rr or rr in cr:
            ref_status = "match"
        else:
            ref_status = "differs"
            sig.append(("reference_differs", "warn", {"claimed": cr, "receipt": rr}))
    elif rr:
        ref_status = "extracted_only"
    if order and order.payment_code:
        pcode = norm_ref(order.payment_code)
        if rr and pcode in rr:
            field_results["payment_code_in_receipt"] = True
    field_results["reference"] = {
        "status": ref_status,
        "extracted": r.reference,
        "stan": r.stan,
        "confidence": conf.get("reference", 0.0),
    }

    # 6. Anomalies from model (font mismatches, digital edit hints)
    for a in r.anomalies:
        a_low = a.lower()
        if any(w in a_low for w in ("photoshop", "edit", "tamper", "fake", "manipulat", "forg")):
            sig.append(("ai_anomaly_tampering", "block", {"note": a}))
        else:
            sig.append(("ai_anomaly", "warn", {"note": a}))

    # 7. Confidence Score & Confidence Level calculation
    field_confs = []
    for k in KEY_FIELDS:
        val = getattr(r, k, None)
        if k in conf and conf[k] is not None:
            field_confs.append(float(conf[k]))
        elif val:
            field_confs.append(0.9)
        else:
            field_confs.append(0.0)
    base = sum(field_confs) / len(KEY_FIELDS)
    warn_count = sum(1 for _, s, _ in sig if s == "warn")
    has_block = any(s == "block" for _, s, _ in sig)

    score = max(0.0, base - 0.15 * warn_count - 0.4 * float(has_block))
    score = round(score, 2)

    # Determine confidence level and human-readable reason
    if has_block:
        level = "low"
        block_reasons = [code for code, s, _ in sig if s == "block"]
        reason = f"Critical discrepancy detected: {', '.join(block_reasons)}"
    elif score >= settings.confidence_threshold_high and amount_status in ("match", "match_base_with_tolerance"):
        level = "high"
        reason = "All printed payment details match order specifications cleanly."
    elif score >= settings.confidence_threshold_medium:
        level = "medium"
        reason = "Proof details partially match; routed to manual review for verification."
    else:
        level = "low"
        reason = f"Low confidence reading (score: {score}); unclear or incomplete proof details."
        sig.append(("low_confidence", "info", {"score": score}))

    return ValidationResult(
        signals=sig,
        score=score,
        confidence_level=level,
        field_results=field_results,
        verification_methods=methods,
        reason=reason,
    )


def validate_receipt(
    receipt: Receipt,
    expected_amount_minor: Optional[int] = None,
    expected_currency: str = "LKR",
    expected_recipient_account_tail: Optional[str] = None,
    expected_reference: Optional[str] = None,
    order_created_at: Optional[datetime] = None,
    ocr_text: str = "",
) -> ValidationResult:
    """Validate a Receipt directly against expected parameters without requiring DB models."""
    class _SimpleOrder:
        def __init__(self):
            self.amount_minor = expected_amount_minor or 0
            self.expected_pay_amount_minor = expected_amount_minor or 0
            self.unique_amount = False
            self.created_at = order_created_at or datetime.now(timezone.utc)
            self.payment_code = expected_reference

    class _SimpleClaim:
        def __init__(self):
            self.claimed_amount_minor = expected_amount_minor or 0
            self.claimed_reference = expected_reference

    orig_tail = settings.bank_account_tail
    if expected_recipient_account_tail is not None:
        settings.bank_account_tail = expected_recipient_account_tail
    try:
        return validate(receipt, _SimpleClaim(), _SimpleOrder(), ocr_text)
    finally:
        if expected_recipient_account_tail is not None:
            settings.bank_account_tail = orig_tail

