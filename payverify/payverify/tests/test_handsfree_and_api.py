"""Tests for hands-free automated bank reconciliation, outbound webhooks,
and expanded cross-platform external API endpoints (check-or-verify and system telemetry).
"""
import datetime as dt
from unittest.mock import MagicMock, patch

import pytest
from sqlalchemy import select

from app.config import settings
from app.db import SessionLocal
from app.models import (
    BankStatementImport,
    BankTransaction,
    ClaimStatus as S,
    Order,
    PaymentClaim,
    SmsEvent,
)
from app.webhook import compute_signature, dispatch_payment_verified
from .conftest import new_order, submit
from .test_devices import Phone

MOCK_VAS_RECEIPT = {
    "stan": "032699400099",
    "amount": "3,000.00",
    "terminalId": "RBATAPO1HT",
    "traceNo": "9988",
    "transactionDate": "06/11/2026",
    "transactionTime": "10:15:00",
    "branch": "BATAPOLA-CRM1 BR",
    "accountNumber": "8*****7213",
    "accountName": "T. SHANUDHA",
    "depositorName": "TEST CUSTOMER",
    "notes": "1000.00:3|",
}


def test_handsfree_receipt_to_ledger_reconciliation(client, world):
    """Verify that a ComBank CRM deposit SMS with vas.combank.net receipt
    automatically creates an authoritative BankTransaction and reconciles
    the claim to 'verified' with basis='bank_match' hands-free (0 manual exports).
    """
    ids, h = world
    phone = Phone(client, h, name="Relay Phone")

    # 1. Customer creates an order
    o = new_order(client, h, ids["cust1"], "INV-AUTO-01", amount=300000, unique=False)
    order_id = o["id"]

    # 2. Customer submits claim awaiting bank confirmation
    r_claim = submit(client, h["c1"], order_id, 300000, ref="032699400099")
    assert r_claim.status_code == 202
    claim_id = r_claim.json()["id"]

    # 3. ComBank SMS arrives via Android relay with vas receipt URL
    sms_body = (
        "We wish to confirm a CRM Deposit at 10:15 for Rs. 3,000.00 through "
        "BATAPOLA-CRM1 BR to your account 8*****7213 https://vas.combank.net/rec/autoTestToken123"
    )

    with patch("app.combank_receipt.fetch_receipt_details", return_value=MOCK_VAS_RECEIPT):
        r_sms = phone.call(
            "POST",
            "/api/v1/devices/sms-events",
            payload={"events": [phone.sms(sms_body, sender="COMBANK")]},
        )
        assert r_sms.status_code == 200

    # 4. Assert that BankTransaction was created automatically hands-free
    with SessionLocal() as db:
        txn = db.execute(
            select(BankTransaction).where(BankTransaction.bank_txn_ref == "032699400099")
        ).scalar_one_or_none()
        assert txn is not None
        assert txn.amount_minor == 300000
        assert txn.direction == "credit"
        assert "BATAPOLA-CRM1 BR" in txn.remarks

        # Assert claim transitioned directly to VERIFIED with basis='bank_match'
        claim = db.get(PaymentClaim, claim_id)
        assert claim.status == S.verified.value, f"Claim status: {claim.status}, reason: {claim.status_reason_code}"
        assert claim.verification_basis == "bank_match"

        # Assert order marked paid
        order = db.get(Order, order_id)
        assert order.status == "paid"


def test_outbound_webhook_dispatch_on_verification(client, world):
    """Verify that when a claim is verified, an outbound signed webhook is dispatched."""
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-HOOK-01", amount=500000)
    c = submit(client, h["c1"], o["id"], 500000).json()

    # Configure webhook
    orig_url = settings.external_webhook_url
    orig_sec = settings.external_webhook_secret
    settings.external_webhook_url = "https://example.com/api/payment-webhook"
    settings.external_webhook_secret = "super_secret_webhook_key_123"

    try:
        with patch("app.webhook.httpx.Client") as mock_client_cls:
            mock_inst = MagicMock()
            mock_inst.__enter__.return_value = mock_inst
            mock_inst.post.return_value = MagicMock(status_code=200)
            mock_client_cls.return_value = mock_inst

            # Manual quick verify by finance admin
            r = client.post(
                f"/api/v1/admin/claims/{c['id']}/quick-verify",
                headers=h["fin"],
                json={"reason": "Checked counterfoil"},
            )
            assert r.status_code == 200

            # Verify webhook client was called with signature header
            assert mock_inst.post.called
            call_kwargs = mock_inst.post.call_args[1]
            headers = call_kwargs.get("headers", {})
            assert "X-PayVerify-Signature" in headers
            assert headers["X-PayVerify-Event"] == "payment.verified"
    finally:
        settings.external_webhook_url = orig_url
        settings.external_webhook_secret = orig_sec


def test_external_api_system_status(client, world):
    """Verify GET /api/v1/external/system-status returns comprehensive health telemetry."""
    orig_key = settings.external_api_key
    settings.external_api_key = "test_telemetry_key_999"

    try:
        r = client.get(
            "/api/v1/external/system-status",
            headers={"X-API-Key": "test_telemetry_key_999"},
        )
        assert r.status_code == 200
        data = r.json()
        assert data["status"] == "online"
        assert data["service"] == "PayVerify Core Engine"
        assert data["database"]["status"] == "healthy"
        assert "latency_ms" in data["database"]
        assert "android_relays" in data
        assert "automation" in data
        assert data["automation"]["handsfree_receipt_to_ledger"] is True
        assert "telemetry" in data
        assert "unverified_claims_count" in data["telemetry"]
    finally:
        settings.external_api_key = orig_key


def test_external_api_check_or_verify_flow(client, world):
    """Test POST /api/v1/external/check-or-verify from external systems."""
    orig_key = settings.external_api_key
    settings.external_api_key = "test_check_verify_key_888"

    try:
        headers = {"X-API-Key": "test_check_verify_key_888"}

        # 1. First check: Order does not exist yet -> registers order & claim, returns PENDING with instructions
        payload = {
            "invoice_no": "INV-EXT-SHOP-77",
            "amount": 2500.00,
            "customer_phone": "0771234567",
            "customer_email": "customer77@shop.com",
            "reference": "STAN7700",
        }
        r1 = client.post("/api/v1/external/check-or-verify", headers=headers, json=payload)
        assert r1.status_code == 200
        d1 = r1.json()
        assert d1["is_approved"] is False
        assert d1["status"] == "PENDING"
        assert d1["amount_minor"] == 250000
        assert d1["amount_formatted"] == "Rs. 2,500.00"
        assert "instructions" in d1
        order_id = d1["order_id"]

        # 2. Simulate matching bank transaction arriving
        with SessionLocal() as db:
            from app.models import BankStatementImport
            imp = BankStatementImport(source="test", file_sha256="ext_test_imp_77")
            db.add(imp)
            db.flush()
            txn = BankTransaction(
                import_id=imp.id,
                bank_txn_ref="STAN7700",
                value_dt=dt.datetime.now(dt.timezone.utc),
                amount_minor=250000,
                direction="credit",
                remarks="Deposit STAN7700",
                dedupe_key="stan_7700_key",
            )
            db.add(txn)
            db.commit()

        # 3. Second check with same invoice -> auto-matches and returns APPROVED
        r2 = client.post("/api/v1/external/check-or-verify", headers=headers, json=payload)
        assert r2.status_code == 200
        d2 = r2.json()
        assert d2["is_approved"] is True
        assert d2["status"] == "APPROVED"
        assert d2["verification_basis"] == "bank_match"
        assert d2["verified_at"] is not None
        assert "Payment verified" in d2["message"]

        # 4. Third check when order already paid -> returns APPROVED immediately
        r3 = client.post("/api/v1/external/check-or-verify", headers=headers, json=payload)
        assert r3.status_code == 200
        assert r3.json()["is_approved"] is True
    finally:
        settings.external_api_key = orig_key


def test_customer_identity_and_payment_lookup(client, world):
    """Test customer identification (email, customer_id, phone, name)
    and querying customer payments via external API and mobile device API.
    """
    ids, h = world
    phone = Phone(client, h, name="Merchant App")
    orig_key = settings.external_api_key
    settings.external_api_key = "test_customer_key_777"

    try:
        headers = {"X-API-Key": "test_customer_key_777"}

        # 1. Create order with full customer details
        payload = {
            "invoice_no": "INV-VIP-500",
            "amount": 7500.00,
            "customer_id": "SHOPIFY-CUST-888",
            "customer_email": "vip.buyer@store.lk",
            "customer_name": "Kasun Silva",
            "customer_phone": "0779988776",
            "reference": "REF888",
        }
        r = client.post("/api/v1/external/check-or-verify", headers=headers, json=payload)
        assert r.status_code == 200
        data = r.json()
        assert data["customer"]["email"] == "vip.buyer@store.lk"
        assert data["customer"]["name"] == "Kasun Silva"
        assert data["customer"]["phone"] == "0779988776"
        assert data["customer"]["external_id"] == "SHOPIFY-CUST-888"

        # 2. Query customer payments by Email
        r_email = client.get("/api/v1/external/customers/vip.buyer@store.lk/payments", headers=headers)
        assert r_email.status_code == 200
        d_email = r_email.json()
        assert d_email["customer"]["email"] == "vip.buyer@store.lk"
        assert d_email["total_orders"] == 1
        assert d_email["orders"][0]["invoice_no"] == "INV-VIP-500"
        assert d_email["orders"][0]["amount_formatted"] == "Rs. 7,500.00"

        # 3. Query customer payments by External Customer ID
        r_ext = client.get("/api/v1/external/customers/SHOPIFY-CUST-888/payments", headers=headers)
        assert r_ext.status_code == 200
        d_ext = r_ext.json()
        assert d_ext["customer"]["external_id"] == "SHOPIFY-CUST-888"

        # 4. Verify Admin claims list search and customer fields
        r_admin = client.get("/api/v1/admin/claims?q=SHOPIFY-CUST-888", headers=h["fin"])
        assert r_admin.status_code == 200
        claims = r_admin.json()
        assert len(claims) >= 1
        assert claims[0]["customer_email"] == "vip.buyer@store.lk"
        assert claims[0]["customer_name"] == "Kasun Silva"

        # 5. Verify Mobile Device claims list returns customer info
        r_dev = phone.call("GET", "/api/v1/devices/claims")
        assert r_dev.status_code == 200
        dev_claims = r_dev.json()
        matching = [c for c in dev_claims if c["invoice_no"] == "INV-VIP-500"]
        assert len(matching) == 1
        assert matching[0]["customer"]["email"] == "vip.buyer@store.lk"
        assert matching[0]["customer"]["external_id"] == "SHOPIFY-CUST-888"
    finally:
        settings.external_api_key = orig_key


def test_enhanced_health_check_endpoint(client, world):
    """Test the upgraded real-time health and diagnostics endpoint."""
    orig_key = settings.external_api_key
    settings.external_api_key = "test-health-key"
    headers = {"X-API-Key": "test-health-key"}

    try:
        r = client.get("/api/v1/external/health", headers=headers)
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["status"] == "online"
        assert data["version"] == "1.2.0"
        assert "database" in data
        assert data["database"]["status"] == "online"
        assert "storage" in data
        assert data["storage"]["status"] == "online"
        assert "android_relays" in data
        assert "reconciliation" in data
    finally:
        settings.external_api_key = orig_key


def test_upload_and_review_json_with_bank_match(client, world):
    """Test POST /api/v1/external/proofs/upload-and-review:
    Uploads slip with full details, runs forensics, matches ledger credit, and returns structured verdict.
    """
    import base64
    from .conftest import PNG

    ids, h = world
    orig_key = settings.external_api_key
    settings.external_api_key = "test-merchant-key-review"
    headers = {"X-API-Key": "test-merchant-key-review"}

    try:
        # Pre-seed a bank ledger credit
        with SessionLocal() as db:
            imp = BankStatementImport(
                source="csv_upload",
                file_sha256="fake_sha_review_12345",
                imported_by=ids["fin"],
                rows_total=1,
                rows_new=1,
            )
            db.add(imp)
            db.flush()
            txn = BankTransaction(
                import_id=imp.id,
                bank_txn_ref="TXN-REVIEW-9911",
                value_dt=dt.datetime.now(dt.timezone.utc),
                amount_minor=420000,
                direction="credit",
                remarks="TRANSFER FROM RUWAN PERERA",
                dedupe_key="dedupe_review_9911",
            )
            db.add(txn)
            db.commit()

        b64_proof = base64.b64encode(PNG).decode("ascii")

        payload = {
            "invoice_no": "INV-REV-001",
            "amount_minor": 420000,
            "currency": "LKR",
            "customer": {
                "name": "Ruwan Perera",
                "email": "ruwan.perera@client.lk",
                "phone": "+94771234567",
                "customer_id": "STORE-CUST-109",
            },
            "payment": {
                "channel": "bank_transfer",
                "reference": "TXN-REVIEW-9911",
                "bank_name": "Commercial Bank",
                "notes": "Annual subscription payment",
            },
            "proof_base64": b64_proof,
            "proof_mime": "image/png",
            "auto_match": True,
        }

        r = client.post("/api/v1/external/proofs/upload-and-review", headers=headers, json=payload)
        assert r.status_code == 200, r.text
        res = r.json()

        assert res["invoice_no"] == "INV-REV-001"
        assert res["status"] == "APPROVED"
        assert res["is_approved"] is True
        assert res["verdict"]["decision"] == "verified"
        assert res["verdict"]["basis"] == "bank_match"
        assert res["forensics"]["duplicate_detected"] is False
        assert "Commercial Bank" in res["bank_details"]["bank_name"]
        assert res["customer"]["email"] == "ruwan.perera@client.lk"
        assert res["settlement"]["allocated_credit_id"] is not None

        # Verify query claim endpoint works
        claim_id = res["claim_id"]
        r_claim = client.get(f"/api/v1/external/claims/{claim_id}", headers=headers)
        assert r_claim.status_code == 200
        assert r_claim.json()["claim_id"] == claim_id
        assert r_claim.json()["status"] == "APPROVED"
    finally:
        settings.external_api_key = orig_key


def test_upload_and_review_multipart_form(client, world):
    """Test POST /api/v1/external/proofs/upload-and-review-form with multipart file upload."""
    import io
    from .conftest import PNG

    orig_key = settings.external_api_key
    settings.external_api_key = "test-merchant-key-review"
    headers = {"X-API-Key": "test-merchant-key-review"}

    try:
        file_payload = {"file": ("bank_slip.png", io.BytesIO(PNG), "image/png")}
        form_data = {
            "invoice_no": "INV-FORM-002",
            "amount_minor": 275000,
            "customer_name": "Kasun Silva",
            "customer_email": "kasun@customer.lk",
            "reference": "SLIP-FORM-8899",
            "channel": "bank_transfer",
        }

        r = client.post(
            "/api/v1/external/proofs/upload-and-review-form",
            headers=headers,
            data=form_data,
            files=file_payload,
        )
        assert r.status_code == 200, r.text
        res = r.json()
        assert res["invoice_no"] == "INV-FORM-002"
        assert res["status"] in ("PENDING_VERIFICATION", "APPROVED")
        assert res["forensics"]["mime_type"] == "image/png"
        assert res["customer"]["email"] == "kasun@customer.lk"
        assert "bank_details" in res
    finally:
        settings.external_api_key = orig_key


def test_claim_resubmit_endpoint(client, world):
    """Test POST /api/v1/external/claims/{claim_id}/resubmit allows replacement proof."""
    import base64
    from .conftest import PNG, PNG2

    orig_key = settings.external_api_key
    settings.external_api_key = "test-merchant-key-review"
    headers = {"X-API-Key": "test-merchant-key-review"}

    try:
        b64_proof = base64.b64encode(PNG).decode("ascii")
        b64_proof_2 = base64.b64encode(PNG2).decode("ascii")

        # 1. Create first claim
        r1 = client.post(
            "/api/v1/external/proofs/upload-and-review",
            headers=headers,
            json={
                "invoice_no": "INV-RESUB-001",
                "amount_minor": 150000,
                "proof_base64": b64_proof,
                "auto_match": False,
            },
        )
        assert r1.status_code == 200
        claim_id = r1.json()["claim_id"]

        # 2. Resubmit with updated reference and replacement proof
        r_resub = client.post(
            f"/api/v1/external/claims/{claim_id}/resubmit",
            headers=headers,
            json={
                "invoice_no": "INV-RESUB-001",
                "amount_minor": 150000,
                "proof_base64": b64_proof_2,
                "payment": {"reference": "NEW-CORRECTED-STAN-999"},
                "auto_match": False,
            },
        )
        assert r_resub.status_code == 200, r_resub.text
        res_data = r_resub.json()
        assert res_data["claim_id"] == claim_id
        assert res_data["status"] == "PENDING_VERIFICATION"
    finally:
        settings.external_api_key = orig_key


def test_external_order_with_proof_base64(client, world):
    """Test creating an order with base64 proof directly via POST /api/v1/external/orders."""
    import base64
    from .conftest import PNG

    orig_key = settings.external_api_key
    settings.external_api_key = "test_merchant_secret_key"
    headers = {"X-API-Key": "test_merchant_secret_key"}

    try:
        b64_proof = base64.b64encode(PNG).decode("ascii")

        # Create order with proof
        r = client.post(
            "/api/v1/external/orders",
            headers=headers,
            json={
                "invoice_no": "EXT-INV-PROOF-01",
                "amount_minor": 250000,
                "customer_name": "Slip Customer",
                "customer_email": "slip_cust@shop.com",
                "proof_base64": b64_proof,
                "proof_mime": "image/png",
                "reference": "DEP-STAN-9900",
            },
        )
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["invoice_no"] == "EXT-INV-PROOF-01"
        assert data["amount_minor"] == 250000
        assert data["payment_code"].startswith("PV-")
        assert data["claim_id"] is not None
        assert data["status"] in ("PENDING_VERIFICATION", "AWAITING_BANK_CONFIRMATION")
        assert data["is_approved"] is False
        assert "bank_details" in data
        assert data["bank_details"]["account_name"] == settings.pay_to_account_name

        # Query order status
        r_ord = client.get(f"/api/v1/external/orders/{data['order_id']}", headers=headers)
        assert r_ord.status_code == 200
        assert r_ord.json()["claim"]["id"] == data["claim_id"]
    finally:
        settings.external_api_key = orig_key


def test_external_order_multipart_upload(client, world):
    """Test creating an order with binary file upload via POST /api/v1/external/orders/upload."""
    import io
    from .conftest import PNG

    orig_key = settings.external_api_key
    settings.external_api_key = "test_merchant_secret_key"
    headers = {"X-API-Key": "test_merchant_secret_key"}

    try:
        file_payload = {"file": ("receipt.png", io.BytesIO(PNG), "image/png")}
        form_data = {
            "invoice_no": "EXT-INV-MULTIPART-01",
            "amount_minor": 185000,
            "customer_name": "Multipart User",
            "customer_email": "multipart@shop.com",
            "reference": "STAN-MULTI-123",
        }

        r = client.post(
            "/api/v1/external/orders/upload",
            headers=headers,
            data=form_data,
            files=file_payload,
        )
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["invoice_no"] == "EXT-INV-MULTIPART-01"
        assert data["amount_minor"] == 185000
        assert data["claim_id"] is not None
        assert data["status"] == "PENDING_VERIFICATION"
        assert data["reference"] == "STAN-MULTI-123"
    finally:
        settings.external_api_key = orig_key


def test_external_credentials_endpoint(client, world):
    """Test GET /api/v1/external/credentials returns masked and full keys for admin/staff."""
    orig_key = settings.external_api_key
    orig_secret = settings.external_webhook_secret
    settings.external_api_key = "test_merchant_secret_key_12345"
    settings.external_webhook_secret = "whsec_abcdef1234567890"
    headers = {"X-API-Key": "test_merchant_secret_key_12345"}

    try:
        r = client.get("/api/v1/external/credentials", headers=headers)
        assert r.status_code == 200, r.text
        data = r.json()
        assert data["api_key_configured"] is True
        assert data["api_key"] == "test_merchant_secret_key_12345"
        assert data["api_key_masked"].startswith("test...")
        assert data["webhook_secret_masked"].startswith("whse...")
    finally:
        settings.external_api_key = orig_key
        settings.external_webhook_secret = orig_secret



