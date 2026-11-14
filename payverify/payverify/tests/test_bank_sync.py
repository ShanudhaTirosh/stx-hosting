import json
import time
from sqlalchemy import select

from app.db import SessionLocal
from app.models import (
    BankStatementImport,
    BankTransaction,
    ClaimStatus,
    Order,
    PaymentClaim,
    SmsEvent,
    utcnow,
)
from .conftest import new_order, submit
from .test_devices import Phone


def test_whatsapp_history_ingestion_and_auto_match(client, world):
    ids, h = world

    # 1. Create an order and submit a claim with reference "REF123456"
    o = new_order(client, h, ids["cust1"], "INV-WA-101", amount=300000)
    order_id = o["id"]
    amt = o["expected_pay_amount_minor"]

    r_claim = submit(client, h["c1"], order_id, amt, ref="REF123456")
    assert r_claim.status_code == 202, r_claim.text
    claim_id = r_claim.json()["id"]

    with SessionLocal() as db:
        claim = db.get(PaymentClaim, claim_id)
        assert claim.status == ClaimStatus.awaiting_bank_confirmation.value

    now = utcnow()
    today_str = now.date().isoformat()
    today_dt = now.isoformat()

    # 2. Post WhatsApp bank statement retrieved from ComBank WhatsApp
    payload = {
        "account_mask": "8xxxxxx213",
        "account_tail": "213",
        "current_balance_minor": 345070,
        "available_balance_minor": 312470,
        "transactions": [
            {
                "value_date": today_str,
                "value_dt": today_dt,
                "remarks": "DEPOSIT-BATAPOLA-CRM1 BR REF123456",
                "amount_minor": amt,
                "direction": "credit",
                "branch": "BATAPOLA",
                "channel": "CRM",
                "raw": f"{today_str} : DEPOSIT-BATAPOLA-CRM1 BR REF123456 : LKR {amt / 100:.2f}",
            },
            {
                "value_date": today_str,
                "value_dt": today_dt,
                "remarks": "WITHDRAWAL SLR GALLE RM",
                "amount_minor": 70500,
                "direction": "debit",
                "branch": "GALLE",
                "channel": "ATM",
                "raw": f"{today_str} : WITHDRAWAL SLR GALLE RM : LKR 705.00",
            },
        ],
    }

    r = client.post("/api/v1/bank-sync/whatsapp-history", json=payload)
    assert r.status_code == 200, r.text
    data = r.json()
    assert data["status"] == "success"
    assert data["rows_total"] == 2
    assert data["rows_new"] == 2
    assert data["duplicates"] == 0
    assert data["matching"]["verified"] == 1

    # 3. Check BankTransaction records exist in DB and claim is verified
    with SessionLocal() as db:
        txns = db.execute(select(BankTransaction).order_by(BankTransaction.id)).scalars().all()
        assert len(txns) >= 2
        credit_txn = [t for t in txns if t.direction == "credit" and t.amount_minor == amt][0]
        assert "DEPOSIT-BATAPOLA" in credit_txn.remarks

        # Claim should be automatically verified against bank credit!
        claim = db.get(PaymentClaim, claim_id)
        assert claim.status == ClaimStatus.verified.value
        assert claim.verification_basis == "bank_match"

    # 4. Re-posting same payload: Deduplication verification
    r_dup = client.post("/api/v1/bank-sync/whatsapp-history", json=payload)
    assert r_dup.status_code == 200
    data_dup = r_dup.json()
    assert data_dup["rows_new"] == 0
    assert data_dup["duplicates"] == 2


def test_zero_leak_otp_relay(client, world):
    ids, h = world
    phone = Phone(client, h, name="Merchant Phone")

    # Call /api/v1/devices/otp-relay
    r = phone.call(
        "POST",
        "/api/v1/devices/otp-relay",
        payload={"otp": "756614", "sender": "COMBANK"},
    )
    assert r.status_code == 200, r.text
    assert r.json()["relayed"] is True

    # ZERO-LEAK VERIFICATION:
    with SessionLocal() as db:
        # 1. Ensure OTP '756614' was NEVER written to SmsEvent
        sms_matches = db.execute(
            select(SmsEvent).where(SmsEvent.body.contains("756614"))
        ).scalars().all()
        assert len(sms_matches) == 0

    # 2. Non-allowlisted sender is rejected
    r_bad = phone.call(
        "POST",
        "/api/v1/devices/otp-relay",
        payload={"otp": "756614", "sender": "MALICIOUS_SENDER"},
    )
    assert r_bad.status_code == 400
