from app import supabase_sync
from app.config import settings


def test_supabase_extended_sync_non_blocking_when_disabled(monkeypatch):
    monkeypatch.setattr(settings, "supabase_sync_enabled", False)
    assert not supabase_sync.sync_device_to_supabase({"id": 1, "name": "Test Phone"})
    assert not supabase_sync.sync_bank_transaction_to_supabase({"id": 10, "amount_minor": 500000})
    assert not supabase_sync.sync_platform_health({"status": "healthy"})


def test_supabase_extended_sync_handles_exceptions_gracefully(monkeypatch):
    monkeypatch.setattr(settings, "supabase_sync_enabled", True)
    monkeypatch.setattr(settings, "supabase_url", "http://invalid-supabase-url-that-does-not-exist:9999")
    monkeypatch.setattr(settings, "supabase_service_key", "test-key")

    # Invariant 5: Any outage must never raise or fail the caller
    assert not supabase_sync.sync_device_to_supabase({"id": 1, "name": "Test Phone"})
    assert not supabase_sync.sync_bank_transaction_to_supabase({"id": 10, "amount_minor": 500000})
    assert not supabase_sync.sync_platform_health({"status": "healthy"})

