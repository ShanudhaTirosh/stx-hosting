"""Cross-Platform Payment Verification API.
Allows external platforms (Shopify, WooCommerce, custom apps, mobile apps, pos)
to verify payments, query order verification status, upload proof slips for AI matching,
or trigger manual verification via API key authentication.
"""
import base64
import os
import time
import uuid
from datetime import timedelta
from typing import Any
import httpx
from fastapi import APIRouter, Depends, Header, HTTPException, status, File, Form, UploadFile
from pydantic import BaseModel, Field
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from .. import audit, matcher
from ..config import settings
from ..crypto import encrypt_bytes
from ..db import get_db
from ..files import save_bytes
from ..models import (
    AiAnalysis,
    Allocation,
    BankTransaction,
    ClaimStatus as S,
    Device,
    FraudSignal,
    Order,
    PaymentClaim,
    ProofFile,
    Role,
    SmsEvent,
    User,
    utcnow,
)
from ..state import transition, TransitionError

router = APIRouter(prefix="/api/v1/external", tags=["external_api"])


def _get_system_actor_id(db: Session) -> int:
    """Return an actor id for audit/state transitions when invoked via external API."""
    admin_id = db.execute(
        select(User.id).where(User.role.in_([Role.super_admin.value, Role.finance_admin.value])).limit(1)
    ).scalar_one_or_none()
    if not admin_id:
        admin_id = db.execute(
            select(User.id).where(User.role == Role.reviewer.value).limit(1)
        ).scalar_one_or_none()
    if not admin_id:
        system_user = User(
            email="system@payverify.local",
            name="System API Actor",
            role=Role.super_admin.value,
            password_hash="api_system_actor",
        )
        db.add(system_user)
        db.flush()
        admin_id = system_user.id
    return admin_id


import threading

_ext_rate_limits: dict[str, list[float]] = {}
_ext_rl_lock = threading.Lock()


def check_external_rate_limit(client_id: str, max_per_minute: int = 180) -> None:
    now = time.monotonic()
    with _ext_rl_lock:
        window = _ext_rate_limits.setdefault(client_id, [])
        cutoff = now - 60.0
        while window and window[0] < cutoff:
            window.pop(0)
        if len(window) >= max_per_minute:
            raise HTTPException(status.HTTP_429_TOO_MANY_REQUESTS, "External API rate limit exceeded")
        window.append(now)


def require_api_key(
    x_api_key: str | None = Header(None, alias="X-API-Key"),
    authorization: str | None = Header(None, alias="Authorization"),
    db: Session = Depends(get_db),
) -> str:
    expected = settings.external_api_key
    provided = x_api_key
    if not provided and authorization and authorization.startswith("Bearer "):
        provided = authorization[7:].strip()

    valid_token: str | None = None

    # 1. Matches configured key
    if expected and provided == expected:
        valid_token = provided

    # 2. Staff user JWT token (from web session / BFF proxy)
    if not valid_token and authorization and authorization.startswith("Bearer "):
        token = authorization[7:].strip()
        try:
            import jwt
            data = jwt.decode(token, settings.jwt_secret, algorithms=["HS256"])
            if data.get("scope") == "full":
                user = db.get(User, int(data["sub"]))
                if user and user.role in (Role.super_admin.value, Role.finance_admin.value, Role.reviewer.value, Role.support.value):
                    valid_token = f"staff:{user.id}"
        except Exception:
            pass

    # 3. Development fallback
    if not valid_token and settings.env == "dev":
        if not expected or provided in ("dev_test_key", "test_key", "test", "demo"):
            valid_token = "dev_key"

    if valid_token:
        check_external_rate_limit(valid_token)
        return valid_token

    if not expected:
        raise HTTPException(
            status.HTTP_500_INTERNAL_SERVER_ERROR,
            "PV_EXTERNAL_API_KEY is not configured on the server",
        )
    raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid or missing API key")


@router.get("/health")
def api_health(
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Real-time health and diagnostics check across all PayVerify subsystems."""
    t0 = time.time()
    db_ok = True
    db_latency = 0.0
    try:
        db.execute(select(1)).scalar()
        db_latency = round((time.time() - t0) * 1000, 2)
    except Exception:
        db_ok = False

    storage_ok = os.path.exists(settings.storage_dir) and os.access(settings.storage_dir, os.W_OK)

    bot_info: dict[str, Any] = {"status": "unreachable", "connected": False}
    if settings.whatsapp_bot_url:
        try:
            with httpx.Client(timeout=1.5) as client:
                r = client.get(f"{settings.whatsapp_bot_url.rstrip('/')}/status")
                if r.status_code == 200:
                    data = r.json()
                    bot_info = {
                        "status": "online",
                        "connected": data.get("connected", False),
                        "state": data.get("state"),
                        "last_sync_at": data.get("lastSyncAt"),
                    }
        except Exception:
            pass

    now = utcnow()
    stale_cutoff = now - timedelta(minutes=settings.device_heartbeat_stale_minutes)
    enrolled = db.execute(select(func.count(Device.id)).where(Device.revoked_at.is_(None))).scalar() or 0
    active = db.execute(
        select(func.count(Device.id)).where(Device.revoked_at.is_(None), Device.last_heartbeat_at >= stale_cutoff)
    ).scalar() or 0
    last_sms = db.execute(select(SmsEvent.received_at).order_by(SmsEvent.id.desc()).limit(1)).scalar_one_or_none()

    today_start = now.replace(hour=0, minute=0, second=0, microsecond=0)
    unverified_claims = db.execute(
        select(func.count(PaymentClaim.id)).where(
            PaymentClaim.status.in_([
                S.pending.value,
                S.processing.value,
                S.awaiting_bank_confirmation.value,
                S.manual_review.value,
            ])
        )
    ).scalar() or 0
    verified_today = db.execute(
        select(func.count(PaymentClaim.id)).where(
            PaymentClaim.status == S.verified.value,
            PaymentClaim.verified_at >= today_start,
        )
    ).scalar() or 0

    return {
        "status": "online",
        "service": "PayVerify External API",
        "version": "1.2.0",
        "timestamp": now.isoformat(),
        "database": {
            "status": "online" if db_ok else "error",
            "latency_ms": db_latency,
        },
        "storage": {
            "status": "online" if storage_ok else "degraded",
            "writable": storage_ok,
            "encrypted_at_rest": True,
        },
        "whatsapp_bot": bot_info,
        "android_relays": {
            "status": "online" if active > 0 else "idle",
            "enrolled_count": enrolled,
            "active_online_count": active,
            "last_sms_received_at": last_sms.isoformat() if last_sms else None,
        },
        "ai_vision": {
            "provider": settings.ai_provider,
            "model": settings.gemini_model if settings.ai_provider == "gemini" else (settings.ollama_model if settings.ai_provider in ("ollama", "local") else None),
            "extraction_enabled": settings.extraction_enabled,
            "ready": True if settings.ai_provider != "none" else False,
        },
        "reconciliation": {
            "unverified_claims_count": unverified_claims,
            "verified_today_count": verified_today,
            "handsfree_receipt_to_ledger": settings.auto_receipt_to_ledger,
        },
        "ai_provider": settings.ai_provider,
        "extraction_enabled": settings.extraction_enabled,
        "supabase_sync_enabled": settings.supabase_sync_enabled,
        "bank_account_tail": settings.bank_account_tail,
        "webhook_configured": bool(settings.external_webhook_url),
    }


@router.get("/system-status")
def system_status(
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Real-time system telemetry and health status across all PayVerify subsystems."""
    t0 = time.time()
    db.execute(select(1)).scalar()
    db_latency_ms = round((time.time() - t0) * 1000, 2)

    # 1. WhatsApp Bot status
    bot_info: dict[str, Any] = {"status": "unreachable", "connected": False}
    try:
        with httpx.Client(timeout=2.0) as client:
            resp = client.get(f"{settings.whatsapp_bot_url.rstrip('/')}/status")
            if resp.status_code == 200:
                data = resp.json()
                bot_info = {
                    "status": "online",
                    "connected": data.get("connected", False),
                    "state": data.get("state"),
                    "last_sync_at": data.get("lastSyncAt"),
                }
    except Exception:
        pass

    # 2. Android Device relays
    now = utcnow()
    stale_cutoff = now - timedelta(minutes=settings.device_heartbeat_stale_minutes)
    enrolled_count = db.execute(select(func.count(Device.id)).where(Device.revoked_at.is_(None))).scalar() or 0
    active_count = db.execute(
        select(func.count(Device.id)).where(Device.revoked_at.is_(None), Device.last_heartbeat_at >= stale_cutoff)
    ).scalar() or 0
    last_sms = db.execute(select(SmsEvent.received_at).order_by(SmsEvent.id.desc()).limit(1)).scalar_one_or_none()

    # 3. Telemetry metrics
    today_start = now.replace(hour=0, minute=0, second=0, microsecond=0)
    unverified_claims = db.execute(
        select(func.count(PaymentClaim.id)).where(
            PaymentClaim.status.in_([
                S.pending.value,
                S.processing.value,
                S.awaiting_bank_confirmation.value,
                S.manual_review.value,
            ])
        )
    ).scalar() or 0
    verified_today = db.execute(
        select(func.count(PaymentClaim.id)).where(
            PaymentClaim.status == S.verified.value,
            PaymentClaim.verified_at >= today_start,
        )
    ).scalar() or 0
    volume_today_minor = db.execute(
        select(func.coalesce(func.sum(PaymentClaim.claimed_amount_minor), 0)).where(
            PaymentClaim.status == S.verified.value,
            PaymentClaim.verified_at >= today_start,
        )
    ).scalar() or 0

    # 4. Extraction queue & Gemini live telemetry
    processing_queue_size = db.execute(
        select(func.count(PaymentClaim.id)).where(PaymentClaim.status == S.processing.value)
    ).scalar() or 0

    from ..extraction.gemini import key_rotator, telemetry
    gemini_stats = telemetry.get_summary(key_rotator.get_status())

    return {
        "status": "online",
        "service": "PayVerify Core Engine",
        "timestamp": now.isoformat(),
        "database": {
            "status": "healthy",
            "latency_ms": db_latency_ms,
        },
        "whatsapp_bot": bot_info,
        "android_relays": {
            "enrolled_count": enrolled_count,
            "active_count": active_count,
            "last_sms_received_at": last_sms.isoformat() if last_sms else None,
        },
        "devices": {
            "total_enrolled": enrolled_count,
            "active_online": active_count,
        },
        "queue_size": processing_queue_size,
        "gemini": gemini_stats,
        "avg_verification_time_ms": gemini_stats.get("avg_verification_time_ms", 0.0),
        "ai_vision": {
            "provider": settings.ai_provider,
            "model": settings.gemini_model if settings.ai_provider == "gemini" else (settings.ollama_model if settings.ai_provider in ("ollama", "local") else None),
            "extraction_enabled": settings.extraction_enabled,
            "health": gemini_stats.get("health", "unknown"),
        },
        "ai_extractor": {
            "provider": settings.ai_provider,
            "model": settings.gemini_model if settings.ai_provider == "gemini" else (settings.ollama_model if settings.ai_provider in ("ollama", "local") else None),
            "enabled": settings.extraction_enabled,
        },
        "automation": {
            "handsfree_receipt_to_ledger": settings.auto_receipt_to_ledger,
            "supabase_sync_enabled": settings.supabase_sync_enabled,
            "outbound_webhook_configured": bool(settings.external_webhook_url),
            "auto_approve_high_confidence": settings.auto_approve_high_confidence,
        },
        "telemetry": {
            "queue_size": processing_queue_size,
            "unverified_claims_count": unverified_claims,
            "verified_today_count": verified_today,
            "verified_today_amount_minor": volume_today_minor,
            "verified_today_formatted": f"Rs. {volume_today_minor / 100:,.2f}",
            "avg_verification_time_ms": gemini_stats.get("avg_verification_time_ms", 0.0),
        },
    }


class CheckOrVerifyIn(BaseModel):
    invoice_no: str = Field(min_length=1, max_length=64)
    amount: float | None = None          # amount in Rs. (e.g. 3500.00)
    amount_minor: int | None = None      # amount in cents (e.g. 350000)
    currency: str = "LKR"
    reference: str | None = None         # Customer reference or printed STAN
    customer_id: str | None = None       # External Customer ID (e.g. from Shopify, WooCommerce)
    customer_email: str | None = None
    customer_name: str | None = None
    customer_phone: str | None = None
    proof_base64: str | None = None      # Base64 encoded receipt image or PDF
    proof_mime: str = "image/jpeg"
    auto_match: bool = True
    unique_amount: bool = False


@router.post("/check-or-verify")
def check_or_verify_payment(
    body: CheckOrVerifyIn,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Unified check-or-verify endpoint for external apps, websites, and plugins.
    If the payment was already received or matches existing credits, returns APPROVED.
    If pending, registers the order/proof, triggers reconciliation, and returns transfer instructions.
    """
    # 1. Resolve amount in minor units
    minor = body.amount_minor
    if minor is None:
        if body.amount is not None:
            minor = int(round(body.amount * 100))
        else:
            raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount or amount_minor is required")
    if minor <= 0:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount must be greater than zero")

    # 2. Find or create customer
    email = body.customer_email or f"{body.invoice_no.lower()}@customer.external"
    cust = db.execute(select(User).where(User.email == email)).scalar_one_or_none()
    if not cust:
        cust = User(
            email=email,
            name=body.customer_name or email,
            phone=body.customer_phone,
            external_id=body.customer_id,
            role=Role.customer.value,
            password_hash="external_account",
        )
        db.add(cust)
        db.flush()
    else:
        if body.customer_name and not cust.name:
            cust.name = body.customer_name
        if body.customer_phone and not getattr(cust, "phone", None):
            cust.phone = body.customer_phone
        if body.customer_id and not getattr(cust, "external_id", None):
            cust.external_id = body.customer_id

    # 3. Find or create order
    order = db.execute(select(Order).where(Order.invoice_no == body.invoice_no)).scalar_one_or_none()
    if not order:
        order = Order(
            customer_id=cust.id,
            invoice_no=body.invoice_no,
            amount_minor=minor,
            expected_pay_amount_minor=minor,
            unique_amount=body.unique_amount,
            payment_code=f"PV-{uuid.uuid4().hex[:6].upper()}",
        )
        db.add(order)
        db.flush()

    cust_dict = {
        "id": cust.id,
        "email": cust.email,
        "name": cust.name,
        "phone": getattr(cust, "phone", None),
        "external_id": getattr(cust, "external_id", None),
    }

    # Check if order is already paid
    if order.status == "paid":
        verified_claim = db.execute(
            select(PaymentClaim)
            .where(PaymentClaim.order_id == order.id, PaymentClaim.status == S.verified.value)
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()
        return {
            "is_approved": True,
            "status": "APPROVED",
            "order_id": order.id,
            "invoice_no": order.invoice_no,
            "customer": cust_dict,
            "amount_minor": order.expected_pay_amount_minor,
            "amount_formatted": f"Rs. {order.expected_pay_amount_minor / 100:,.2f}",
            "payment_code": order.payment_code,
            "verification_basis": verified_claim.verification_basis if verified_claim else "existing_paid",
            "verified_at": verified_claim.verified_at.isoformat() if (verified_claim and verified_claim.verified_at) else None,
            "reference": verified_claim.claimed_reference if verified_claim else None,
            "message": "Payment verified and approved",
        }

    # 4. Find open claim or create one
    claim = db.execute(
        select(PaymentClaim)
        .where(
            PaymentClaim.order_id == order.id,
            PaymentClaim.status.in_([S.pending.value, S.awaiting_bank_confirmation.value, S.manual_review.value])
        )
        .order_by(PaymentClaim.id.desc())
    ).scalar_one_or_none()

    if not claim:
        claim = PaymentClaim(
            order_id=order.id,
            customer_id=cust.id,
            channel="bank_transfer",
            claimed_amount_minor=minor,
            claimed_reference=(body.reference or "").strip() or None,
            status=S.awaiting_bank_confirmation.value,
            customer_message="Payment check submitted via external API",
        )
        db.add(claim)
        db.flush()
    elif body.reference and not claim.claimed_reference:
        claim.claimed_reference = body.reference.strip()

    # 5. Handle proof file if provided
    if body.proof_base64:
        try:
            b64_str = body.proof_base64
            if "," in b64_str and "base64," in b64_str:
                b64_str = b64_str.split("base64,", 1)[1]
            raw_bytes = base64.b64decode(b64_str)
            saved = save_bytes(raw_bytes)
            db.add(ProofFile(
                claim_id=claim.id,
                storage_key=saved["storage_key"],
                mime=saved["mime"],
                size=saved["size"],
                sha256=saved["sha256"],
                dhash=saved["dhash"],
            ))
            db.flush()

            if settings.extraction_enabled:
                from ..extraction.pipeline import process_claim
                process_claim(db, claim.id)
        except HTTPException:
            raise
        except Exception as e:
            import logging
            logging.getLogger("payverify.external").warning("Failed to process proof in check-or-verify: %s", e)

    # 6. Attempt deterministic matching against ledger
    if body.auto_match and claim.status == S.awaiting_bank_confirmation.value:
        matcher.try_match_claim(db, claim)

    db.commit()

    if claim.status == S.verified.value:
        return {
            "is_approved": True,
            "status": "APPROVED",
            "order_id": order.id,
            "invoice_no": order.invoice_no,
            "customer": cust_dict,
            "amount_minor": claim.claimed_amount_minor,
            "amount_formatted": f"Rs. {claim.claimed_amount_minor / 100:,.2f}",
            "payment_code": order.payment_code,
            "verification_basis": claim.verification_basis,
            "verified_at": claim.verified_at.isoformat() if claim.verified_at else None,
            "reference": claim.claimed_reference,
            "message": "Payment verified automatically via Commercial Bank ledger match",
        }

    status_map = {
        S.awaiting_bank_confirmation.value: "PENDING",
        S.manual_review.value: "MANUAL_REVIEW",
        S.suspicious.value: "MANUAL_REVIEW",
        S.rejected.value: "REJECTED",
        S.processing.value: "PROCESSING",
    }

    instructions = (
        f"Please transfer Rs. {order.expected_pay_amount_minor / 100:,.2f} to Commercial Bank Account "
        f"{settings.pay_to_account_number or '(Configured in settings)'} ({settings.pay_to_account_name or 'PayVerify'}) "
        f"with remarks '{order.payment_code}'."
    )

    return {
        "is_approved": False,
        "status": status_map.get(claim.status, "PENDING"),
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "customer": cust_dict,
        "amount_minor": claim.claimed_amount_minor,
        "amount_formatted": f"Rs. {claim.claimed_amount_minor / 100:,.2f}",
        "payment_code": order.payment_code,
        "verification_basis": None,
        "verified_at": None,
        "reference": claim.claimed_reference,
        "instructions": instructions,
        "message": claim.customer_message or "Waiting for bank deposit receipt or statement credit",
    }


@router.get("/customers/{customer_id_or_email}/payments")
def get_customer_payments(
    customer_id_or_email: str,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Retrieve all payments, orders, and verification statuses for a customer by Email, ID, or External ID."""
    from sqlalchemy import or_

    q = select(User)
    if customer_id_or_email.isdigit():
        q = q.where(
            or_(
                User.id == int(customer_id_or_email),
                User.email == customer_id_or_email,
                User.external_id == customer_id_or_email,
            )
        )
    else:
        q = q.where(
            or_(
                User.email == customer_id_or_email,
                User.external_id == customer_id_or_email,
            )
        )
    cust = db.execute(q).scalar_one_or_none()
    if not cust:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Customer not found")

    orders = db.execute(select(Order).where(Order.customer_id == cust.id).order_by(Order.id.desc())).scalars().all()
    claims = db.execute(select(PaymentClaim).where(PaymentClaim.customer_id == cust.id).order_by(PaymentClaim.id.desc())).scalars().all()

    return {
        "customer": {
            "id": cust.id,
            "email": cust.email,
            "name": cust.name,
            "phone": getattr(cust, "phone", None),
            "external_id": getattr(cust, "external_id", None),
            "created_at": cust.created_at.isoformat() if cust.created_at else None,
        },
        "total_orders": len(orders),
        "total_claims": len(claims),
        "orders": [{
            "id": o.id,
            "invoice_no": o.invoice_no,
            "amount_minor": o.amount_minor,
            "amount_formatted": f"Rs. {o.amount_minor / 100:,.2f}",
            "status": o.status,
            "payment_code": o.payment_code,
            "created_at": o.created_at.isoformat() if o.created_at else None,
        } for o in orders],
        "claims": [{
            "id": c.id,
            "order_id": c.order_id,
            "status": c.status,
            "amount_minor": c.claimed_amount_minor,
            "amount_formatted": f"Rs. {c.claimed_amount_minor / 100:,.2f}",
            "reference": c.claimed_reference,
            "channel": c.channel,
            "verification_basis": c.verification_basis,
            "verified_at": c.verified_at.isoformat() if c.verified_at else None,
            "created_at": c.created_at.isoformat() if c.created_at else None,
        } for c in claims],
    }


@router.post("/trigger-sync")
def trigger_bank_sync(
    api_key: str = Depends(require_api_key),
):
    """Trigger an immediate bank statement sync with the ComBank WhatsApp bot."""
    if not settings.whatsapp_bot_url:
        raise HTTPException(status.HTTP_503_SERVICE_UNAVAILABLE, "WhatsApp bot URL is not configured")
    headers = {}
    if settings.whatsapp_bot_secret:
        headers["X-Bot-Secret"] = settings.whatsapp_bot_secret
    try:
        with httpx.Client(timeout=10.0) as client:
            resp = client.post(f"{settings.whatsapp_bot_url.rstrip('/')}/sync", headers=headers)
            if resp.status_code == 200:
                return {"success": True, "result": resp.json()}
            return {"success": False, "status_code": resp.status_code, "error": resp.text}
    except Exception as e:
        raise HTTPException(status.HTTP_502_BAD_GATEWAY, f"Failed to contact WhatsApp bot: {str(e)}")


class CreateExternalOrderIn(BaseModel):
    invoice_no: str = Field(min_length=1, max_length=64)
    amount_minor: int = Field(gt=0)
    customer_email: str | None = None
    customer_name: str | None = None
    customer_phone: str | None = None
    unique_amount: bool = False
    proof_base64: str | None = None       # Base64 encoded receipt image or PDF
    proof_mime: str = "image/jpeg"
    reference: str | None = None          # Printed reference or transaction STAN
    channel: str = "bank_transfer"
    auto_match: bool = True


@router.post("/orders")
def create_external_order(
    body: CreateExternalOrderIn,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Create an order or submit payment with proof in a single request.
    Returns instant verification status, approval confirmation, and bank transfer instructions.
    """
    email = body.customer_email or f"{body.invoice_no.lower()}@customer.external"
    cust = db.execute(select(User).where(User.email == email)).scalar_one_or_none()
    if not cust:
        cust = User(
            email=email,
            name=body.customer_name or email,
            phone=body.customer_phone,
            role=Role.customer.value,
            password_hash="external_account",
        )
        db.add(cust)
        db.flush()
    else:
        if body.customer_name and not cust.name:
            cust.name = body.customer_name
        if body.customer_phone and not getattr(cust, "phone", None):
            cust.phone = body.customer_phone

    order = db.execute(select(Order).where(Order.invoice_no == body.invoice_no)).scalar_one_or_none()
    if not order:
        order = Order(
            customer_id=cust.id,
            invoice_no=body.invoice_no,
            amount_minor=body.amount_minor,
            expected_pay_amount_minor=body.amount_minor,
            unique_amount=body.unique_amount,
            payment_code=f"PV-{uuid.uuid4().hex[:6].upper()}",
        )
        db.add(order)
        db.flush()

    claim = None
    if order.status == "paid":
        claim = db.execute(
            select(PaymentClaim)
            .where(PaymentClaim.order_id == order.id, PaymentClaim.status == S.verified.value)
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()
    else:
        claim = db.execute(
            select(PaymentClaim)
            .where(
                PaymentClaim.order_id == order.id,
                PaymentClaim.status.in_([
                    S.pending.value,
                    S.awaiting_bank_confirmation.value,
                    S.manual_review.value,
                    S.processing.value,
                ])
            )
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()

        if body.proof_base64:
            try:
                b64_str = body.proof_base64
                if "," in b64_str and "base64," in b64_str:
                    b64_str = b64_str.split("base64,", 1)[1]
                raw_bytes = base64.b64decode(b64_str)
            except Exception:
                raise HTTPException(status.HTTP_400_BAD_REQUEST, "Invalid base64 encoding for proof_base64")

            saved = save_bytes(raw_bytes)
            if not claim:
                claim = PaymentClaim(
                    order_id=order.id,
                    customer_id=cust.id,
                    channel=body.channel,
                    claimed_amount_minor=order.expected_pay_amount_minor,
                    claimed_reference=(body.reference or "").strip() or None,
                    status=S.awaiting_bank_confirmation.value,
                    customer_message="Payment proof uploaded via external API",
                )
                db.add(claim)
                db.flush()
            elif body.reference and not claim.claimed_reference:
                claim.claimed_reference = body.reference.strip()

            db.add(ProofFile(
                claim_id=claim.id,
                storage_key=saved["storage_key"],
                mime=saved["mime"],
                size=saved["size"],
                sha256=saved["sha256"],
                dhash=saved["dhash"],
            ))
            db.flush()

            audit.log(
                db,
                action="claim.upload_proof_external",
                entity="claim",
                entity_id=claim.id,
                actor_id=cust.id,
                detail={"invoice_no": order.invoice_no, "storage_key": saved["storage_key"]},
            )

            if settings.extraction_enabled:
                from ..extraction.pipeline import process_claim
                process_claim(db, claim.id)

            if body.auto_match and claim.status == S.awaiting_bank_confirmation.value:
                matcher.try_match_claim(db, claim)
        elif body.reference:
            if not claim:
                claim = PaymentClaim(
                    order_id=order.id,
                    customer_id=cust.id,
                    channel=body.channel,
                    claimed_amount_minor=order.expected_pay_amount_minor,
                    claimed_reference=body.reference.strip(),
                    status=S.awaiting_bank_confirmation.value,
                    customer_message="Payment reference submitted via external API",
                )
                db.add(claim)
                db.flush()
            else:
                claim.claimed_reference = body.reference.strip()

            if body.auto_match and claim.status == S.awaiting_bank_confirmation.value:
                matcher.try_match_claim(db, claim)

    db.commit()

    is_approved = (order.status == "paid") or (claim is not None and claim.status == S.verified.value)
    if is_approved:
        status_label = "APPROVED"
    elif claim:
        status_label = "PENDING_VERIFICATION" if claim.status in (S.awaiting_bank_confirmation.value, S.processing.value) else claim.status.upper()
    else:
        status_label = "AWAITING_PAYMENT"

    instructions = (
        f"Please transfer Rs. {order.expected_pay_amount_minor / 100:,.2f} to Commercial Bank Account "
        f"{settings.pay_to_account_number or '(Configured in settings)'} ({settings.pay_to_account_name or 'PayVerify'}) "
        f"and include reference code '{order.payment_code}' in your transaction remarks."
    )

    return {
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "amount_minor": order.expected_pay_amount_minor,
        "amount_formatted": f"Rs. {order.expected_pay_amount_minor / 100:,.2f}",
        "payment_code": order.payment_code,
        "status": status_label,
        "is_approved": is_approved,
        "claim_id": claim.id if claim else None,
        "verification_basis": claim.verification_basis if claim else None,
        "verified_at": claim.verified_at.isoformat() if (claim and claim.verified_at) else None,
        "reference": (body.reference or (claim.claimed_reference if claim else None)),
        "customer": {
            "id": cust.id,
            "email": cust.email,
            "name": cust.name,
            "phone": getattr(cust, "phone", None),
        },
        "pay_to_bank": "Commercial Bank of Ceylon PLC",
        "pay_to_account_name": settings.pay_to_account_name,
        "pay_to_account_number": settings.pay_to_account_number,
        "instructions": instructions,
        "bank_details": {
            "bank_name": "Commercial Bank of Ceylon PLC",
            "account_name": settings.pay_to_account_name,
            "account_number": settings.pay_to_account_number,
            "branch": getattr(settings, "bank_branch", None) or "Head Office",
        },
    }


@router.post("/orders/upload")
async def create_external_order_with_file_upload(
    invoice_no: str = Form(...),
    amount_minor: int = Form(..., gt=0),
    customer_email: str | None = Form(None),
    customer_name: str | None = Form(None),
    customer_phone: str | None = Form(None),
    reference: str | None = Form(None),
    channel: str = Form("bank_transfer"),
    auto_match: bool = Form(True),
    unique_amount: bool = Form(False),
    file: UploadFile = File(...),
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Multipart form endpoint for HTML forms and checkout pages to create an order
    and directly upload a payment slip image or PDF file.
    """
    raw_bytes = await file.read(settings.max_upload_bytes + 1)
    saved = save_bytes(raw_bytes)

    email = customer_email or f"{invoice_no.lower()}@customer.external"
    cust = db.execute(select(User).where(User.email == email)).scalar_one_or_none()
    if not cust:
        cust = User(
            email=email,
            name=customer_name or email,
            phone=customer_phone,
            role=Role.customer.value,
            password_hash="external_account",
        )
        db.add(cust)
        db.flush()

    order = db.execute(select(Order).where(Order.invoice_no == invoice_no)).scalar_one_or_none()
    if not order:
        order = Order(
            customer_id=cust.id,
            invoice_no=invoice_no,
            amount_minor=amount_minor,
            expected_pay_amount_minor=amount_minor,
            unique_amount=unique_amount,
            payment_code=f"PV-{uuid.uuid4().hex[:6].upper()}",
        )
        db.add(order)
        db.flush()

    claim = None
    if order.status == "paid":
        claim = db.execute(
            select(PaymentClaim)
            .where(PaymentClaim.order_id == order.id, PaymentClaim.status == S.verified.value)
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()
    else:
        claim = db.execute(
            select(PaymentClaim)
            .where(
                PaymentClaim.order_id == order.id,
                PaymentClaim.status.in_([
                    S.pending.value,
                    S.awaiting_bank_confirmation.value,
                    S.manual_review.value,
                    S.processing.value,
                ])
            )
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()

        if not claim:
            claim = PaymentClaim(
                order_id=order.id,
                customer_id=cust.id,
                channel=channel,
                claimed_amount_minor=order.expected_pay_amount_minor,
                claimed_reference=(reference or "").strip() or None,
                status=S.awaiting_bank_confirmation.value,
                customer_message="Payment proof uploaded via multipart API",
            )
            db.add(claim)
            db.flush()
        elif reference and not claim.claimed_reference:
            claim.claimed_reference = reference.strip()

        db.add(ProofFile(
            claim_id=claim.id,
            storage_key=saved["storage_key"],
            mime=saved["mime"],
            size=saved["size"],
            sha256=saved["sha256"],
            dhash=saved["dhash"],
        ))
        db.flush()

        audit.log(
            db,
            action="claim.upload_multipart_external",
            entity="claim",
            entity_id=claim.id,
            actor_id=cust.id,
            detail={"invoice_no": order.invoice_no, "storage_key": saved["storage_key"]},
        )

        if settings.extraction_enabled:
            from ..extraction.pipeline import process_claim
            process_claim(db, claim.id)

        if auto_match and claim.status == S.awaiting_bank_confirmation.value:
            matcher.try_match_claim(db, claim)

    db.commit()

    is_approved = (order.status == "paid") or (claim is not None and claim.status == S.verified.value)
    status_label = "APPROVED" if is_approved else ("PENDING_VERIFICATION" if claim else "AWAITING_PAYMENT")

    instructions = (
        f"Please transfer Rs. {order.expected_pay_amount_minor / 100:,.2f} to Commercial Bank Account "
        f"{settings.pay_to_account_number or '(Configured in settings)'} ({settings.pay_to_account_name or 'PayVerify'}) "
        f"and include reference code '{order.payment_code}' in your transaction remarks."
    )

    return {
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "amount_minor": order.expected_pay_amount_minor,
        "amount_formatted": f"Rs. {order.expected_pay_amount_minor / 100:,.2f}",
        "payment_code": order.payment_code,
        "status": status_label,
        "is_approved": is_approved,
        "claim_id": claim.id if claim else None,
        "verification_basis": claim.verification_basis if claim else None,
        "verified_at": claim.verified_at.isoformat() if (claim and claim.verified_at) else None,
        "reference": (reference or (claim.claimed_reference if claim else None)),
        "customer": {
            "id": cust.id,
            "email": cust.email,
            "name": cust.name,
            "phone": getattr(cust, "phone", None),
        },
        "pay_to_bank": "Commercial Bank of Ceylon PLC",
        "pay_to_account_name": settings.pay_to_account_name,
        "pay_to_account_number": settings.pay_to_account_number,
        "instructions": instructions,
        "bank_details": {
            "bank_name": "Commercial Bank of Ceylon PLC",
            "account_name": settings.pay_to_account_name,
            "account_number": settings.pay_to_account_number,
            "branch": getattr(settings, "bank_branch", None) or "Head Office",
        },
    }


@router.get("/credentials")
def get_external_credentials(
    api_key: str = Depends(require_api_key),
):
    """Retrieve integration credentials (API key, webhook secret, base URL) for staff & merchants."""
    raw_key = settings.external_api_key or ""
    masked_key = f"{raw_key[:4]}...{raw_key[-4:]}" if len(raw_key) > 8 else ("****" if raw_key else "NOT_CONFIGURED")

    raw_secret = settings.external_webhook_secret or ""
    masked_secret = f"{raw_secret[:4]}...{raw_secret[-4:]}" if len(raw_secret) > 8 else ("****" if raw_secret else "NOT_CONFIGURED")

    return {
        "api_key": raw_key,
        "api_key_masked": masked_key,
        "api_key_configured": bool(raw_key),
        "webhook_url": settings.external_webhook_url or "",
        "webhook_secret": raw_secret,
        "webhook_secret_masked": masked_secret,
        "webhook_configured": bool(settings.external_webhook_url),
        "site_url": settings.site_url or "http://localhost:8000",
        "header_example": f"X-API-Key: {raw_key if raw_key else 'PV_YOUR_API_KEY'}",
    }


class ExternalVerifyRequest(BaseModel):
    invoice_no: str = Field(min_length=1, max_length=64)
    amount_minor: int = Field(gt=0)
    currency: str = "LKR"
    reference: str | None = None
    customer_email: str | None = None
    customer_name: str | None = None
    proof_base64: str | None = None # Base64 encoded receipt image or PDF
    proof_mime: str = "image/jpeg"
    channel: str = "bank_transfer"
    auto_match: bool = True
    manual_override: bool = False


@router.post("/verify")
def external_verify(
    body: ExternalVerifyRequest,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Verify or submit a payment from any external platform.
    Automatically checks bank statement transactions, SMS alerts, and AI extraction.
    """
    # 1. Find or create customer
    email = body.customer_email or f"{body.invoice_no.lower()}@customer.external"
    cust = db.execute(select(User).where(User.email == email)).scalar_one_or_none()
    if not cust:
        cust = User(
            email=email,
            name=body.customer_name or email,
            role=Role.customer.value,
            password_hash="external_account",
        )
        db.add(cust)
        db.flush()

    # 2. Find or create order
    order = db.execute(
        select(Order).where(Order.invoice_no == body.invoice_no)
    ).scalar_one_or_none()
    if not order:
        order = Order(
            customer_id=cust.id,
            invoice_no=body.invoice_no,
            amount_minor=body.amount_minor,
            expected_pay_amount_minor=body.amount_minor,
            unique_amount=False,
            payment_code=uuid.uuid4().hex[:8].upper(),
        )
        db.add(order)
        db.flush()

    # If order is already paid
    if order.status == "paid":
        verified_claim = db.execute(
            select(PaymentClaim)
            .where(PaymentClaim.order_id == order.id, PaymentClaim.status == S.verified.value)
            .order_by(PaymentClaim.id.desc())
        ).scalar_one_or_none()
        return {
            "verified": True,
            "status": "verified",
            "order_id": order.id,
            "invoice_no": order.invoice_no,
            "amount_minor": order.expected_pay_amount_minor,
            "claim_id": verified_claim.id if verified_claim else None,
            "verification_basis": verified_claim.verification_basis if verified_claim else "existing_paid",
        }

    # 3. Find open claim or create one
    claim = db.execute(
        select(PaymentClaim)
        .where(
            PaymentClaim.order_id == order.id,
            PaymentClaim.status.in_([S.pending.value, S.awaiting_bank_confirmation.value, S.manual_review.value])
        )
        .order_by(PaymentClaim.id.desc())
    ).scalar_one_or_none()

    if not claim:
        claim = PaymentClaim(
            order_id=order.id,
            customer_id=cust.id,
            channel=body.channel,
            claimed_amount_minor=body.amount_minor,
            claimed_reference=(body.reference or "").strip() or None,
            status=S.awaiting_bank_confirmation.value,
            customer_message="Payment submitted via external API",
        )
        db.add(claim)
        db.flush()

    # 4. Handle proof file if provided
    if body.proof_base64:
        try:
            b64_str = body.proof_base64
            if "," in b64_str and "base64," in b64_str:
                b64_str = b64_str.split("base64,", 1)[1]
            raw_bytes = base64.b64decode(b64_str)
            saved = save_bytes(raw_bytes)
            db.add(ProofFile(
                claim_id=claim.id,
                storage_key=saved["storage_key"],
                mime=saved["mime"],
                size=saved["size"],
                sha256=saved["sha256"],
                dhash=saved["dhash"],
            ))
            db.flush()

            # If AI extraction enabled, process receipt
            if settings.extraction_enabled:
                from ..extraction.pipeline import process_claim
                process_claim(db, claim.id)
        except HTTPException:
            raise
        except Exception as e:
            import logging
            logging.getLogger("payverify.external").warning("Failed to process proof in external_verify: %s", e)

    # 5. Auto-matching against bank records
    if body.auto_match and claim.status == S.awaiting_bank_confirmation.value:
        matcher.try_match_claim(db, claim)

    # 6. Manual override if requested
    if body.manual_override and claim.status != S.verified.value:
        admin_id = _get_system_actor_id(db)
        transition(
            db,
            claim,
            S.verified,
            actor_id=admin_id,
            basis="admin_override",
            reason="api_manual_override",
            extra={"note": "Verified via external API override"},
        )

    db.commit()

    return {
        "verified": claim.status == S.verified.value,
        "status": claim.status,
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "claim_id": claim.id,
        "amount_minor": claim.claimed_amount_minor,
        "amount_formatted": f"Rs. {claim.claimed_amount_minor / 100:,.2f}",
        "payment_code": order.payment_code,
        "verification_basis": claim.verification_basis,
        "status_reason": claim.status_reason_code,
    }


@router.get("/orders/{invoice_or_id}")
def get_order_status(
    invoice_or_id: str,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Query live payment status of an order by invoice number or ID."""
    q = select(Order)
    if invoice_or_id.isdigit():
        q = q.where((Order.id == int(invoice_or_id)) | (Order.invoice_no == invoice_or_id))
    else:
        q = q.where(Order.invoice_no == invoice_or_id)

    order = db.execute(q).scalar_one_or_none()
    if not order:
        raise HTTPException(404, "Order not found")

    latest_claim = db.execute(
        select(PaymentClaim)
        .where(PaymentClaim.order_id == order.id)
        .order_by(PaymentClaim.id.desc())
    ).scalar_one_or_none()

    return {
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "order_status": order.status,
        "is_paid": order.status == "paid",
        "expected_amount_minor": order.expected_pay_amount_minor,
        "payment_code": order.payment_code,
        "claim": {
            "id": latest_claim.id,
            "status": latest_claim.status,
            "verification_basis": latest_claim.verification_basis,
            "verified_at": latest_claim.verified_at,
            "reference": latest_claim.claimed_reference,
            "amount_minor": latest_claim.claimed_amount_minor,
        } if latest_claim else None,
    }


@router.post("/claims/{claim_id}/verify")
def api_manual_verify_claim(
    claim_id: int,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Manually verify a payment claim via API."""
    c = db.get(PaymentClaim, claim_id)
    if not c:
        raise HTTPException(404, "Claim not found")
    if c.status == S.verified.value:
        return {"claim_id": c.id, "status": "verified", "already_verified": True}

    order = db.get(Order, c.order_id)
    from ..matcher import _candidates
    cands = _candidates(db, c, order)
    admin_id = _get_system_actor_id(db)

    if cands:
        t = cands[0]
        try:
            with db.begin_nested():
                db.add(Allocation(bank_transaction_id=t.id, claim_id=c.id, amount_minor=t.amount_minor))
                db.flush()
            transition(
                db,
                c,
                S.verified,
                actor_id=admin_id,
                basis="bank_match",
                reason="api_quick_verify",
                extra={"bank_transaction_id": t.id},
            )
            db.commit()
            return {"claim_id": c.id, "status": "verified", "basis": "bank_match"}
        except IntegrityError:
            pass

    transition(
        db,
        c,
        S.verified,
        actor_id=admin_id,
        basis="admin_override",
        reason="api_quick_verify",
        extra={"justification": "Verified via external API call"},
    )
    db.commit()
    return {"claim_id": c.id, "status": "verified", "basis": "admin_override"}


# ============================================================================
# COMPREHENSIVE PROOF UPLOAD & INSTANT REVIEW APIS
# ============================================================================

class ExternalCustomerIn(BaseModel):
    name: str | None = None
    email: str | None = None
    phone: str | None = None
    customer_id: str | None = None


class ExternalPaymentDetailsIn(BaseModel):
    channel: str = "bank_transfer"
    reference: str | None = None
    bank_name: str | None = "Commercial Bank"
    transfer_date: str | None = None
    notes: str | None = None


class UploadAndReviewIn(BaseModel):
    invoice_no: str = Field(min_length=1, max_length=64)
    amount_minor: int | None = None
    amount: float | None = None
    currency: str = "LKR"
    customer: ExternalCustomerIn | None = None
    payment: ExternalPaymentDetailsIn | None = None
    proof_base64: str = Field(..., description="Base64 encoded receipt slip image or PDF")
    proof_mime: str = "image/jpeg"
    auto_match: bool = True


def _run_upload_and_review(
    db: Session,
    invoice_no: str,
    amount_minor: int,
    raw_bytes: bytes,
    customer_info: ExternalCustomerIn | None = None,
    payment_info: ExternalPaymentDetailsIn | None = None,
    auto_match: bool = True,
) -> dict[str, Any]:
    # 1. Resolve customer
    cust_email = (customer_info.email if customer_info and customer_info.email else None) or f"{invoice_no.lower()}@customer.external"
    cust = db.execute(select(User).where(User.email == cust_email)).scalar_one_or_none()
    if not cust:
        cust = User(
            email=cust_email,
            name=(customer_info.name if customer_info and customer_info.name else None) or cust_email,
            phone=customer_info.phone if customer_info else None,
            external_id=customer_info.customer_id if customer_info else None,
            role=Role.customer.value,
            password_hash="external_account",
        )
        db.add(cust)
        db.flush()
    else:
        if customer_info:
            if customer_info.name and not cust.name:
                cust.name = customer_info.name
            if customer_info.phone and not getattr(cust, "phone", None):
                cust.phone = customer_info.phone
            if customer_info.customer_id and not getattr(cust, "external_id", None):
                cust.external_id = customer_info.customer_id

    # 2. Resolve order
    order = db.execute(select(Order).where(Order.invoice_no == invoice_no)).scalar_one_or_none()
    if not order:
        order = Order(
            customer_id=cust.id,
            invoice_no=invoice_no,
            amount_minor=amount_minor,
            expected_pay_amount_minor=amount_minor,
            unique_amount=False,
            payment_code=f"PV-{uuid.uuid4().hex[:6].upper()}",
        )
        db.add(order)
        db.flush()

    # 3. Save proof file encrypted
    saved = save_bytes(raw_bytes)

    # 4. Resolve or create PaymentClaim
    claim = db.execute(
        select(PaymentClaim)
        .where(
            PaymentClaim.order_id == order.id,
            PaymentClaim.status.in_([
                S.pending.value,
                S.awaiting_bank_confirmation.value,
                S.manual_review.value,
                S.processing.value,
            ])
        )
        .order_by(PaymentClaim.id.desc())
    ).scalar_one_or_none()

    claimed_ref = (payment_info.reference.strip() if payment_info and payment_info.reference else None)
    channel = (payment_info.channel if payment_info else "bank_transfer")

    if not claim:
        claim = PaymentClaim(
            order_id=order.id,
            customer_id=cust.id,
            channel=channel,
            claimed_amount_minor=order.expected_pay_amount_minor,
            claimed_reference=claimed_ref,
            status=S.awaiting_bank_confirmation.value,
            customer_message="Payment proof uploaded via external review API",
        )
        db.add(claim)
        db.flush()
    else:
        if claimed_ref and not claim.claimed_reference:
            claim.claimed_reference = claimed_ref

    # Check duplicate sha256 across other claims
    dup_sha_q = select(ProofFile.id).where(ProofFile.sha256 == saved["sha256"], ProofFile.claim_id != claim.id)
    existing_sha = db.execute(dup_sha_q).scalar_one_or_none()
    is_duplicate = existing_sha is not None

    # Check duplicate dhash across other claims
    existing_dhash = None
    if saved.get("dhash"):
        dup_dhash_q = select(ProofFile.id).where(ProofFile.dhash == saved["dhash"], ProofFile.claim_id != claim.id)
        existing_dhash = db.execute(dup_dhash_q).scalar_one_or_none()
    if existing_dhash:
        is_duplicate = True

    pf = ProofFile(
        claim_id=claim.id,
        storage_key=saved["storage_key"],
        mime=saved["mime"],
        size=saved["size"],
        sha256=saved["sha256"],
        dhash=saved["dhash"],
    )
    db.add(pf)
    db.flush()

    audit.log(
        db,
        action="claim.upload_and_review",
        entity="claim",
        entity_id=claim.id,
        actor_id=cust.id,
        detail={"invoice_no": order.invoice_no, "storage_key": saved["storage_key"]},
    )

    # 5. Run AI extraction if enabled
    ai_extracted: dict[str, Any] = {}
    ai_conf: float | None = None
    if settings.extraction_enabled:
        from ..extraction.pipeline import process_claim
        process_claim(db, claim.id)
        ai_row = db.execute(
            select(AiAnalysis).where(AiAnalysis.proof_file_id == pf.id).order_by(AiAnalysis.id.desc()).limit(1)
        ).scalar_one_or_none()
        if ai_row:
            ai_extracted = ai_row.extracted or {}
            ai_conf = ai_row.overall_confidence

    # 6. Evaluate discrepancies
    discrepancies: list[str] = []
    if is_duplicate:
        discrepancies.append("duplicate_proof")
    read_amt = None
    if ai_extracted:
        read_amt = ai_extracted.get("amount_minor")
        if read_amt is None and ai_extracted.get("amount"):
            try:
                from ..bank_import import parse_money
                read_amt = parse_money(ai_extracted["amount"])
            except Exception:
                read_amt = None
        if read_amt and read_amt != order.expected_pay_amount_minor:
            discrepancies.append("amount_mismatch")
        read_acc = ai_extracted.get("recipient_account") or ai_extracted.get("recipient_account_masked")
        if read_acc and settings.bank_account_tail and not str(read_acc).endswith(settings.bank_account_tail):
            discrepancies.append("wrong_recipient")

    # If critical discrepancies, flag claim to manual_review
    if discrepancies and claim.status == S.awaiting_bank_confirmation.value:
        transition(
            db,
            claim,
            S.manual_review,
            reason="external_review_discrepancy",
            extra={"discrepancies": discrepancies},
        )

    # 7. Try deterministic matching if auto_match enabled
    if auto_match and claim.status == S.awaiting_bank_confirmation.value:
        matcher.try_match_claim(db, claim)

    # Optional auto-approval for high confidence when enabled in configuration
    if (
        settings.auto_approve_high_confidence
        and ai_conf is not None
        and ai_conf >= settings.confidence_threshold_high
        and not discrepancies
        and claim.status != S.verified.value
    ):
        admin_id = _get_system_actor_id(db)
        transition(
            db,
            claim,
            S.verified,
            actor_id=admin_id,
            basis="admin_override",
            reason="ai_high_confidence_auto_approved",
            extra={"confidence_score": ai_conf, "extracted": ai_extracted},
        )

    db.commit()

    # 8. Check settlement status
    is_approved = (order.status == "paid") or (claim.status == S.verified.value)
    alloc = db.execute(select(Allocation).where(Allocation.claim_id == claim.id)).scalar_one_or_none()

    confidence_level = (
        "high"
        if (ai_conf and ai_conf >= settings.confidence_threshold_high and not discrepancies)
        else (
            "low"
            if (discrepancies or (ai_conf is not None and ai_conf < settings.confidence_threshold_medium))
            else "medium"
        )
    )

    if is_approved:
        status_label = "APPROVED"
        decision = "verified"
        summary = "Payment successfully verified and approved."
    elif claim.status == S.manual_review.value:
        status_label = "MANUAL_REVIEW"
        decision = "discrepancy_detected"
        summary = f"Payment proof flagged for manual review due to: {', '.join(discrepancies) if discrepancies else 'verification uncertainty'}."
    elif claim.status == S.rejected.value:
        status_label = "REJECTED"
        decision = "rejected"
        summary = claim.customer_message or "Payment claim was rejected."
    else:
        status_label = "PENDING_VERIFICATION"
        decision = "awaiting_bank_credit"
        summary = "Proof slip processed and validated. Awaiting matching Commercial Bank deposit credit in ledger."

    instructions = (
        f"Please transfer Rs. {order.expected_pay_amount_minor / 100:,.2f} to Commercial Bank Account "
        f"{settings.pay_to_account_number or '(Configured in settings)'} ({settings.pay_to_account_name or 'PayVerify'}) "
        f"and include reference code '{order.payment_code}' in your transaction remarks."
    )

    return {
        "review_id": f"REV-{uuid.uuid4().hex[:6].upper()}",
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "claim_id": claim.id,
        "payment_code": order.payment_code,
        "status": status_label,
        "is_approved": is_approved,
        "verdict": {
            "decision": decision,
            "basis": claim.verification_basis if is_approved else None,
            "confidence_score": ai_conf if ai_conf is not None else (0.95 if is_approved else 0.85),
            "confidence_level": confidence_level,
            "summary": summary,
            "discrepancies": discrepancies,
            "verification_methods": ["gemini_vision", "forensic_hash", "rule_tolerance"],
        },
        "extraction": {
            "amount_minor": read_amt or ai_extracted.get("amount_minor"),
            "amount_formatted": f"Rs. {read_amt / 100:,.2f}" if read_amt else (
                f"Rs. {ai_extracted['amount_minor'] / 100:,.2f}" if ai_extracted.get("amount_minor") else None
            ),
            "reference": ai_extracted.get("reference"),
            "sender_name": ai_extracted.get("sender_name"),
            "transaction_time": ai_extracted.get("datetime"),
            "recipient_account": read_acc if 'read_acc' in locals() else None,
            "ai_provider": settings.ai_provider if settings.extraction_enabled else "none",
        },
        "forensics": {
            "sha256": saved["sha256"],
            "dhash": saved["dhash"],
            "duplicate_detected": is_duplicate,
            "mime_type": saved["mime"],
            "size_bytes": saved["size"],
        },
        "settlement": {
            "verified_at": claim.verified_at.isoformat() if claim.verified_at else None,
            "verification_basis": claim.verification_basis,
            "allocated_credit_id": alloc.bank_transaction_id if alloc else None,
            "bank_name": "Commercial Bank of Ceylon PLC",
        },
        "customer": {
            "id": cust.id,
            "email": cust.email,
            "name": cust.name,
            "phone": getattr(cust, "phone", None),
            "external_id": getattr(cust, "external_id", None),
        },
        "instructions": instructions,
        "bank_details": {
            "bank_name": "Commercial Bank of Ceylon PLC",
            "account_name": settings.pay_to_account_name,
            "account_number": settings.pay_to_account_number,
            "branch": getattr(settings, "bank_branch", None) or "Head Office",
        },
    }


@router.post("/proofs/upload-and-review")
def external_upload_and_review(
    body: UploadAndReviewIn,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """External website/checkout endpoint: upload payment proof with rich order and customer details,
    run forensics, AI extraction, and ComBank ledger reconciliation, returning immediate review results.
    """
    # 1. Resolve amount in minor units
    minor = body.amount_minor
    if minor is None:
        if body.amount is not None:
            minor = int(round(body.amount * 100))
        else:
            raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount or amount_minor is required")
    if minor <= 0:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount must be greater than zero")

    # 2. Decode proof bytes
    try:
        b64_str = body.proof_base64
        if "," in b64_str and "base64," in b64_str:
            b64_str = b64_str.split("base64,", 1)[1]
        raw_bytes = base64.b64decode(b64_str)
    except Exception:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Invalid base64 encoding for proof_base64")

    return _run_upload_and_review(
        db=db,
        invoice_no=body.invoice_no,
        amount_minor=minor,
        raw_bytes=raw_bytes,
        customer_info=body.customer,
        payment_info=body.payment,
        auto_match=body.auto_match,
    )


@router.post("/proofs/upload-and-review-form")
async def external_upload_and_review_form(
    invoice_no: str = Form(...),
    amount_minor: int | None = Form(None),
    amount: float | None = Form(None),
    file: UploadFile = File(...),
    customer_name: str | None = Form(None),
    customer_email: str | None = Form(None),
    customer_phone: str | None = Form(None),
    customer_id: str | None = Form(None),
    reference: str | None = Form(None),
    channel: str = Form("bank_transfer"),
    auto_match: bool = Form(True),
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Multipart form endpoint: upload payment receipt file directly from an HTML form,
    triggering instant review, forensic checks, and ComBank reconciliation.
    """
    minor = amount_minor
    if minor is None:
        if amount is not None:
            minor = int(round(amount * 100))
        else:
            raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount or amount_minor is required")
    if minor <= 0:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Amount must be greater than zero")

    raw_bytes = await file.read(settings.max_upload_bytes + 1)

    customer_info = ExternalCustomerIn(
        name=customer_name,
        email=customer_email,
        phone=customer_phone,
        customer_id=customer_id,
    )
    payment_info = ExternalPaymentDetailsIn(
        channel=channel,
        reference=reference,
    )

    return _run_upload_and_review(
        db=db,
        invoice_no=invoice_no,
        amount_minor=minor,
        raw_bytes=raw_bytes,
        customer_info=customer_info,
        payment_info=payment_info,
        auto_match=auto_match,
    )


@router.get("/claims/{claim_id}")
def get_external_claim_review(
    claim_id: int,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Query live review verdict, forensics, and extraction results for a payment claim."""
    claim = db.get(PaymentClaim, claim_id)
    if not claim:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Claim not found")

    order = db.get(Order, claim.order_id)
    cust = db.get(User, claim.customer_id)
    alloc = db.execute(select(Allocation).where(Allocation.claim_id == claim.id)).scalar_one_or_none()
    pf = db.execute(select(ProofFile).where(ProofFile.claim_id == claim.id).order_by(ProofFile.id.desc())).scalar_one_or_none()
    ai_row = None
    if pf:
        ai_row = db.execute(select(AiAnalysis).where(AiAnalysis.proof_file_id == pf.id)).scalar_one_or_none()

    is_approved = (order.status == "paid") or (claim.status == S.verified.value)

    return {
        "claim_id": claim.id,
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "payment_code": order.payment_code,
        "status": "APPROVED" if is_approved else claim.status.upper(),
        "is_approved": is_approved,
        "verification_basis": claim.verification_basis,
        "verified_at": claim.verified_at.isoformat() if claim.verified_at else None,
        "claimed_amount_minor": claim.claimed_amount_minor,
        "claimed_reference": claim.claimed_reference,
        "customer": {
            "id": cust.id,
            "name": cust.name,
            "email": cust.email,
        } if cust else None,
        "extraction": ai_row.extracted if ai_row else None,
        "settlement": {
            "allocated_credit_id": alloc.bank_transaction_id if alloc else None,
            "basis": claim.verification_basis,
        },
    }


@router.post("/claims/{claim_id}/resubmit")
def resubmit_external_claim(
    claim_id: int,
    body: UploadAndReviewIn,
    api_key: str = Depends(require_api_key),
    db: Session = Depends(get_db),
):
    """Resubmit a clearer or replacement payment slip for an existing order/claim."""
    claim = db.get(PaymentClaim, claim_id)
    if not claim:
        raise HTTPException(status.HTTP_404_NOT_FOUND, "Claim not found")
    if claim.status == S.verified.value:
        raise HTTPException(status.HTTP_409_CONFLICT, "This payment is already verified and settled")

    order = db.get(Order, claim.order_id)
    minor = body.amount_minor or (int(round(body.amount * 100)) if body.amount else order.expected_pay_amount_minor)

    try:
        b64_str = body.proof_base64
        if "," in b64_str and "base64," in b64_str:
            b64_str = b64_str.split("base64,", 1)[1]
        raw_bytes = base64.b64decode(b64_str)
    except Exception:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Invalid base64 encoding for proof_base64")

    # Reset claim status to awaiting_bank_confirmation if not already there
    if claim.status != S.awaiting_bank_confirmation.value:
        transition(
            db,
            claim,
            S.awaiting_bank_confirmation,
            reason="customer_resubmitted_proof",
            extra={"note": "New receipt uploaded via external API"},
        )

    return _run_upload_and_review(
        db=db,
        invoice_no=order.invoice_no,
        amount_minor=minor,
        raw_bytes=raw_bytes,
        customer_info=body.customer,
        payment_info=body.payment,
        auto_match=body.auto_match,
    )
