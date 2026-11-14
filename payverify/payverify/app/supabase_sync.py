"""Supabase Realtime & Database Sync (Free Tier & Self-Hosted).
Syncs verified payments, status updates, and claims to Supabase PostgreSQL / REST API.
Works seamlessly with both Supabase Cloud (free tier) and Self-Hosted Supabase Docker instances.
"""
import logging
from typing import Any
import httpx

from .config import settings

logger = logging.getLogger(__name__)


def is_supabase_enabled() -> bool:
    return bool(settings.supabase_sync_enabled and settings.supabase_url and settings.supabase_service_key)


def sync_claim_to_supabase(claim_data: dict[str, Any]) -> bool:
    """Upsert payment claim record to Supabase table 'payments'."""
    if not is_supabase_enabled():
        return False

    url = f"{settings.supabase_url.rstrip('/')}/rest/v1/payments"
    headers = {
        "apikey": settings.supabase_service_key,
        "Authorization": f"Bearer {settings.supabase_service_key}",
        "Content-Type": "application/json",
        "Prefer": "resolution=merge-duplicates",
    }
    payload = {
        "id": claim_data.get("id"),
        "order_id": claim_data.get("order_id"),
        "invoice_no": claim_data.get("invoice_no"),
        "status": claim_data.get("status"),
        "amount_minor": claim_data.get("amount_minor"),
        "claimed_reference": claim_data.get("claimed_reference"),
        "channel": claim_data.get("channel"),
        "verification_basis": claim_data.get("verification_basis"),
        "verified_at": claim_data.get("verified_at"),
        "updated_at": claim_data.get("updated_at"),
    }
    try:
        with httpx.Client(timeout=8.0) as client:
            resp = client.post(url, json=payload, headers=headers)
            if resp.status_code in (200, 201, 204):
                logger.info(f"Synced claim #{claim_data.get('id')} to Supabase")
                return True
            logger.warning(f"Supabase sync returned HTTP {resp.status_code}: {resp.text[:120]}")
    except Exception as e:
        logger.error(f"Failed to sync to Supabase: {str(e)}")
    return False

