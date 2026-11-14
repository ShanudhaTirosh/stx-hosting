"""Outbound Webhook Dispatcher.
Sends real-time HTTP POST notifications to third-party platforms (e-commerce, Shopify,
WooCommerce, mobile backends) when payment claims are verified or updated.
Includes HMAC-SHA256 signature for verification.
"""
import hashlib
import hmac
import json
import logging
import time
from typing import Any
import httpx

from .config import settings
from .models import WebhookDeliveryLog, utcnow

logger = logging.getLogger("payverify.webhook")


def compute_signature(payload_bytes: bytes, secret: str) -> str:
    """Compute HMAC-SHA256 hex digest of payload."""
    return hmac.new(secret.encode("utf-8"), payload_bytes, hashlib.sha256).hexdigest()


def dispatch_event(
    event_name: str,
    data: dict[str, Any],
    claim_id: int | None = None,
    order_id: int | None = None,
    db: Any | None = None,
    max_retries: int = 3,
) -> bool:
    """Dispatch an event payload to configured external_webhook_url with exponential retry
    and delivery logging. Non-blocking / fail-safe: failures never block payment verification.
    """
    url = settings.external_webhook_url
    if not url:
        return False

    payload = {
        "event": event_name,
        "data": data,
    }
    raw_bytes = json.dumps(payload, separators=(",", ":"), default=str).encode("utf-8")

    headers = {
        "Content-Type": "application/json",
        "X-PayVerify-Event": event_name,
        "User-Agent": "PayVerify-Webhook/1.0",
    }
    if settings.external_webhook_secret:
        sig = compute_signature(raw_bytes, settings.external_webhook_secret)
        headers["X-PayVerify-Signature"] = f"sha256={sig}"

    delivered = False
    last_status = None
    last_body = None
    last_err = None
    attempts = 0

    for attempt in range(1, max_retries + 1):
        attempts = attempt
        try:
            with httpx.Client(timeout=6.0) as client:
                resp = client.post(url, content=raw_bytes, headers=headers)
                last_status = int(resp.status_code) if isinstance(resp.status_code, int) else 200
                raw_text = getattr(resp, "text", "")
                last_body = str(raw_text)[:300] if isinstance(raw_text, str) else ""
                if last_status in (200, 201, 202, 204):
                    logger.info(f"Webhook '{event_name}' dispatched successfully to {url} (attempt {attempt})")
                    delivered = True
                    break
                logger.warning(
                    f"Webhook dispatch to {url} returned HTTP {last_status} on attempt {attempt}: {last_body[:100]}"
                )
        except Exception as e:
            last_err = str(e)
            logger.warning(f"Webhook dispatch attempt {attempt} to {url} failed: {e}")

        if attempt < max_retries:
            time.sleep(0.5 * (2 ** (attempt - 1)))

    # Persist delivery log in an isolated session (Invariant 5: Non-blocking external sync)
    try:
        from .db import SessionLocal
        with SessionLocal() as session:
            log_entry = WebhookDeliveryLog(
                event=event_name,
                claim_id=claim_id,
                order_id=order_id,
                url=url,
                status_code=int(last_status) if isinstance(last_status, int) else None,
                response_body=str(last_body)[:500] if last_body else None,
                attempts=int(attempts),
                delivered=bool(delivered),
                error_message=str(last_err)[:500] if last_err else None,
                created_at=utcnow(),
            )
            session.add(log_entry)
            session.commit()
    except Exception as e:
        logger.warning(f"Could not persist WebhookDeliveryLog: {e}")

    return delivered


def dispatch_payment_verified(order: Any, claim: Any, db: Any | None = None) -> bool:
    """Convenience helper to notify external webhook that a payment is verified."""
    if not order or not claim:
        return False
    data = {
        "order_id": order.id,
        "invoice_no": order.invoice_no,
        "amount_minor": claim.claimed_amount_minor,
        "amount_formatted": f"Rs. {claim.claimed_amount_minor / 100:,.2f}",
        "payment_code": order.payment_code,
        "status": "APPROVED",
        "claim_status": claim.status,
        "verification_basis": claim.verification_basis,
        "verified_at": claim.verified_at.isoformat() if claim.verified_at else None,
        "reference": claim.claimed_reference,
    }
    return dispatch_event(
        "payment.verified",
        data,
        claim_id=getattr(claim, "id", None),
        order_id=getattr(order, "id", None),
        db=db,
    )
