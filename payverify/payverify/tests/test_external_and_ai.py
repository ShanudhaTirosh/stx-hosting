import base64
import json
from unittest.mock import patch, MagicMock
import httpx
from sqlalchemy import select

from app.config import settings
from app.db import SessionLocal
from app.extraction.ai import GeminiExtractor, parse_model_json
from app.models import ClaimStatus, Order, PaymentClaim
from app.supabase_sync import sync_claim_to_supabase
from .conftest import new_order, submit
from .test_devices import Phone


def test_gemini_extractor_parsing():
    sample_gemini_response = {
        "candidates": [
            {
                "content": {
                    "parts": [
                        {
                            "text": json.dumps({
                                "reference": "CEFT889900",
                                "amount": "3,000.00",
                                "currency": "LKR",
                                "datetime": "2026-11-05 10:15",
                                "sender_name": "SUNIL PERERA",
                                "sender_bank": "COMMERCIAL BANK",
                                "recipient_name": "MY SHOP",
                                "recipient_bank": "COMMERCIAL BANK",
                                "recipient_account_masked": "8******213",
                                "status_text": "Completed",
                                "channel": "CEFTS",
                                "anomalies": [],
                                "field_confidence": {"amount": 0.99, "reference": 0.95}
                            })
                        }
                    ]
                }
            }
        ]
    }

    with patch.object(settings, "gemini_api_key", "test-key-123"):
        with patch.object(settings, "ai_provider", "gemini"):
            extractor = GeminiExtractor()
            with patch("httpx.post") as mock_post:
                mock_resp = MagicMock()
                mock_resp.status_code = 200
                mock_resp.json.return_value = sample_gemini_response
                mock_post.return_value = mock_resp

                receipt = extractor.extract([(b"fake_image", "image/jpeg")], "OCR hint")
                assert receipt.reference == "CEFT889900"
                assert receipt.amount == "3,000.00"
                assert receipt.sender_name == "SUNIL PERERA"
                assert receipt.field_confidence["amount"] == 0.99


def test_admin_quick_verify_button(client, world):
    ids, h = world

    # Create order & claim
    o = new_order(client, h, ids["cust1"], "INV-QUICK-01", amount=500000)
    r_claim = submit(client, h["c1"], o["id"], 500000, ref="REF778899")
    claim_id = r_claim.json()["id"]

    # One-click manual verification from web admin
    r_verify = client.post(
        f"/api/v1/admin/claims/{claim_id}/quick-verify",
        headers=h["fin"],
        json={"reason": "Verified manually via staff dashboard"},
    )
    assert r_verify.status_code == 200, r_verify.text
    data = r_verify.json()
    assert data["status"] == "verified"
    assert data["basis"] in ("bank_match", "admin_override")

    # Verify claim and order status in DB
    with SessionLocal() as db:
        claim = db.get(PaymentClaim, claim_id)
        assert claim.status == ClaimStatus.verified.value
        order = db.get(Order, o["id"])
        assert order.status == "paid"


def test_device_manual_verify(client, world):
    ids, h = world
    phone = Phone(client, h, name="Merchant Mobile App")

    # Create order & claim
    o = new_order(client, h, ids["cust1"], "INV-PHONE-01", amount=250000)
    r_claim = submit(client, h["c1"], o["id"], 250000, ref="REF112233")
    claim_id = r_claim.json()["id"]

    # Manual verify called from enrolled phone app
    r_dev = phone.call(
        "POST",
        f"/api/v1/devices/claims/{claim_id}/verify",
        payload={"reason": "Verified on phone screen"},
    )
    assert r_dev.status_code == 200, r_dev.text
    assert r_dev.json()["status"] == "verified"

    with SessionLocal() as db:
        claim = db.get(PaymentClaim, claim_id)
        assert claim.status == ClaimStatus.verified.value


def test_cross_platform_external_api(client, world):
    ids, h = world

    with patch.object(settings, "external_api_key", "secret-api-key-99"):
        headers = {"X-API-Key": "secret-api-key-99"}

        # 1. Reject without key
        r_unauth = client.post("/api/v1/external/verify", json={"invoice_no": "EXT-1", "amount_minor": 10000})
        assert r_unauth.status_code == 401

        # 1b. Health check endpoint
        r_health = client.get("/api/v1/external/health", headers=headers)
        assert r_health.status_code == 200
        assert r_health.json()["status"] == "online"

        # 1c. Pre-create checkout order to receive bank instructions
        r_order = client.post(
            "/api/v1/external/orders",
            headers=headers,
            json={"invoice_no": "EXT-CHECKOUT-1", "amount_minor": 150000},
        )
        assert r_order.status_code == 200
        assert r_order.json()["payment_code"] is not None
        assert "Commercial Bank" in r_order.json()["instructions"]

        # 2. Submit external verification request
        payload = {
            "invoice_no": "EXT-ORDER-999",
            "amount_minor": 450000,
            "currency": "LKR",
            "reference": "EXTREF12345",
            "customer_email": "ext_customer@store.com",
            "customer_name": "External Store Customer",
            "auto_match": True,
        }
        r = client.post("/api/v1/external/verify", headers=headers, json=payload)
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["invoice_no"] == "EXT-ORDER-999"
        assert data["amount_minor"] == 450000
        claim_id = data["claim_id"]

        # 3. Query status via external API
        r_status = client.get("/api/v1/external/orders/EXT-ORDER-999", headers=headers)
        assert r_status.status_code == 200
        assert r_status.json()["invoice_no"] == "EXT-ORDER-999"

        # 4. Trigger manual verification via external API
        r_ver = client.post(f"/api/v1/external/claims/{claim_id}/verify", headers=headers)
        assert r_ver.status_code == 200
        assert r_ver.json()["status"] == "verified"

        # Check order is marked as paid
        r_paid = client.get("/api/v1/external/orders/EXT-ORDER-999", headers=headers)
        assert r_paid.json()["is_paid"] is True


def test_supabase_sync_handling():
    with patch.object(settings, "supabase_sync_enabled", True):
        with patch.object(settings, "supabase_url", "https://xyzproject.supabase.co"):
            with patch.object(settings, "supabase_service_key", "supabase-test-key"):
                with patch("httpx.Client.post") as mock_post:
                    mock_resp = MagicMock()
                    mock_resp.status_code = 201
                    mock_post.return_value = mock_resp

                    synced = sync_claim_to_supabase({
                        "id": 101,
                        "order_id": 202,
                        "invoice_no": "INV-SUPA-1",
                        "status": "verified",
                        "amount_minor": 300000,
                    })
                    assert synced is True
                    assert mock_post.called
                    call_url = mock_post.call_args[0][0]
                    assert "https://xyzproject.supabase.co/rest/v1/payments" in call_url

