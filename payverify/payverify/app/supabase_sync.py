"""Supabase Realtime & Database Sync (Free Tier & Self-Hosted).
Syncs verified payments, devices, bank transactions, and platform health telemetry
to Supabase PostgreSQL / REST API and Realtime engine.
Works seamlessly with both Supabase Cloud (free tier) and Self-Hosted Supabase Docker instances.
"""
import logging
from typing import Any
import httpx

from .config import settings

logger = logging.getLogger(__name__)


def is_supabase_enabled() -> bool:
    return bool(settings.supabase_sync_enabled and settings.supabase_url and settings.supabase_service_key)


def _supabase_headers() -> dict[str, str]:
    return {
        "apikey": settings.supabase_service_key,
        "Authorization": f"Bearer {settings.supabase_service_key}",
        "Content-Type": "application/json",
        "Prefer": "resolution=merge-duplicates",
    }


def sync_claim_to_supabase(claim_data: dict[str, Any]) -> bool:
    """Upsert payment claim record to Supabase table 'payments'."""
    if not is_supabase_enabled():
        return False

    url = f"{settings.supabase_url.rstrip('/')}/rest/v1/payments"
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
            resp = client.post(url, json=payload, headers=_supabase_headers())
            if resp.status_code in (200, 201, 204):
                logger.info(f"Synced claim #{claim_data.get('id')} to Supabase")
                return True
            logger.warning(f"Supabase sync returned HTTP {resp.status_code}: {resp.text[:120]}")
    except Exception as e:
        logger.error(f"Failed to sync to Supabase: {str(e)}")
    return False


def sync_device_to_supabase(device_data: dict[str, Any]) -> bool:
    """Upsert enrolled device telemetry to Supabase table 'devices'."""
    if not is_supabase_enabled():
        return False

    url = f"{settings.supabase_url.rstrip('/')}/rest/v1/devices"
    payload = {
        "id": device_data.get("id"),
        "name": device_data.get("name"),
        "status": device_data.get("status"),
        "last_heartbeat_at": device_data.get("last_heartbeat_at"),
        "revoked": device_data.get("revoked", False),
        "updated_at": device_data.get("updated_at"),
    }
    try:
        with httpx.Client(timeout=8.0) as client:
            resp = client.post(url, json=payload, headers=_supabase_headers())
            if resp.status_code in (200, 201, 204):
                return True
            logger.warning(f"Supabase device sync HTTP {resp.status_code}: {resp.text[:120]}")
    except Exception as e:
        logger.error(f"Failed to sync device to Supabase: {str(e)}")
    return False


def sync_bank_transaction_to_supabase(txn_data: dict[str, Any]) -> bool:
    """Upsert bank transaction to Supabase table 'transactions'."""
    if not is_supabase_enabled():
        return False

    url = f"{settings.supabase_url.rstrip('/')}/rest/v1/transactions"
    payload = {
        "id": txn_data.get("id"),
        "bank_txn_ref": txn_data.get("bank_txn_ref"),
        "value_dt": txn_data.get("value_dt"),
        "amount_minor": txn_data.get("amount_minor"),
        "direction": txn_data.get("direction"),
        "remarks": txn_data.get("remarks"),
        "created_at": txn_data.get("created_at"),
    }
    try:
        with httpx.Client(timeout=8.0) as client:
            resp = client.post(url, json=payload, headers=_supabase_headers())
            if resp.status_code in (200, 201, 204):
                return True
            logger.warning(f"Supabase transaction sync HTTP {resp.status_code}: {resp.text[:120]}")
    except Exception as e:
        logger.error(f"Failed to sync transaction to Supabase: {str(e)}")
    return False


def sync_platform_health(health_data: dict[str, Any]) -> bool:
    """Sync overall system telemetry to Supabase table 'platform_health'."""
    if not is_supabase_enabled():
        return False

    url = f"{settings.supabase_url.rstrip('/')}/rest/v1/platform_health"
    try:
        with httpx.Client(timeout=8.0) as client:
            resp = client.post(url, json=health_data, headers=_supabase_headers())
            return resp.status_code in (200, 201, 204)
    except Exception as e:
        logger.error(f"Failed to sync platform health to Supabase: {str(e)}")
    return False
