import io
from unittest.mock import patch
from sqlalchemy import select

from app.combank_receipt import (
    extract_receipt_token,
    generate_receipt_pdf,
    process_receipt_url,
    read_receipt_pdf,
)
from app.db import SessionLocal
from app.models import FraudSignal, Order, PaymentClaim, SmsEvent, utcnow
from .conftest import new_order, submit
from .test_devices import Phone

MOCK_COMBANK_RECEIPT = {
    "responseDesc": "Successful",
    "card": "42168917****4498",
    "actualBalance": "0.00",
    "availableBalance": "0.00",
    "appId": "A0000000031010",
    "branch": "BATAPOLA-CRM1 BR",
    "date": "2026-11-05",
    "time": "10:01",
    "terminalId": "RBATAPO1HT",
    "stan": "032699400003",
    "traceNo": "8978",
    "transaction": "CARD DEPOSIT",
    "transactionType": "DEPOSIT",
    "feeAmount": "0.00",
    "accountNumber": "8******213",
    "cardType": "DEBIT",
    "cardless": "CARD",
    "narration": "DEPOSIT-BATAPOLA-CRM1 BR",
    "currency": "LKR",
    "depositDetails": [
        {
            "notes": "3",
            "value": "1000.00",
            "amount": "3000.00",
        }
    ],
    "notes": "1000.00:3|",
    "totalDepositAmount": "3000.00",
    "transactionStatus": "Successful",
}


def test_receipt_token_extraction():
    url = "https://vas.combank.net/rec/ddH0edd4sGGNdHbE"
    token = extract_receipt_token(url)
    assert token == "ddH0edd4sGGNdHbE"

    url_with_query = "https://vas.combank.net/rec/xyz123ABC?src=sms"
    assert extract_receipt_token(url_with_query) == "xyz123ABC"

    assert extract_receipt_token("https://example.com/other") is None


def test_receipt_pdf_generation():
    pdf_bytes = generate_receipt_pdf(MOCK_COMBANK_RECEIPT)
    assert isinstance(pdf_bytes, bytes)
    assert pdf_bytes.startswith(b"%PDF-")
    assert len(pdf_bytes) > 1000


def test_sms_ingest_with_receipt_pdf_and_stan(client, world):
    ids, h = world
    phone = Phone(client, h, name="Merchant Phone")

    # Order expecting 3,000.00 LKR (300,000 cents)
    o = new_order(client, h, ids["cust1"], "INV-REC-01", amount=300000)
    order_id = o["id"]

    # Submit claim referencing the STAN printed on the receipt
    r_claim = submit(client, h["c1"], order_id, 300000, ref="032699400003")
    assert r_claim.status_code == 202
    claim_id = r_claim.json()["id"]

    # Real SMS format from ComBank with vas.combank.net receipt link
    sms_body = (
        "We wish to confirm a CRM Deposit at 10:01 for Rs. 3,000.00 through "
        "BATAPOLA-CRM1 BR to your account 8*****7213 https://vas.combank.net/rec/ddH0edd4sGGNdHbE"
    )

    with patch("app.combank_receipt.fetch_receipt_details", return_value=MOCK_COMBANK_RECEIPT):
        r_sms = phone.call(
            "POST",
            "/api/v1/devices/sms-events",
            payload={"events": [phone.sms(sms_body, sender="COMBANK")]},
        )
        assert r_sms.status_code == 200, r_sms.text
        res = r_sms.json()["results"][0]
        assert res["result"] == "stored"
        sms_id = res["id"]

    # Verify SmsEvent fields were enriched from the receipt
    with SessionLocal() as db:
        ev = db.get(SmsEvent, sms_id)
        assert ev.receipt_stan == "032699400003"
        assert ev.ref == "032699400003" # enriched STAN as reference
        assert ev.branch == "BATAPOLA-CRM1 BR"
        assert ev.amount_minor == 300000
        assert ev.receipt_pdf_key is not None
        assert ev.receipt_data["traceNo"] == "8978"

        # Verify PDF is decryptable and intact
        pdf_data = read_receipt_pdf(ev.receipt_pdf_key)
        assert pdf_data.startswith(b"%PDF-")

        # Verify claim has corroboration signal containing receipt metadata
        signals = db.execute(
            select(FraudSignal).where(FraudSignal.claim_id == claim_id)
        ).scalars().all()
        corrob = [s for s in signals if s.rule_code == "sms_corroborated"]
        assert len(corrob) == 1
        assert corrob[0].detail["receipt_stan"] == "032699400003"
        assert corrob[0].detail["receipt_branch"] == "BATAPOLA-CRM1 BR"

    # Test Admin PDF Receipt Download Endpoint
    r_pdf = client.get(f"/api/v1/admin/sms-events/{sms_id}/receipt-pdf", headers=h["fin"])
    assert r_pdf.status_code == 200
    assert r_pdf.headers["Content-Type"] == "application/pdf"
    assert "combank_receipt_032699400003.pdf" in r_pdf.headers["Content-Disposition"]
    assert r_pdf.content.startswith(b"%PDF-")

