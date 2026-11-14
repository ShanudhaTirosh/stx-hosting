import datetime as dt

import pytest
from sqlalchemy.exc import IntegrityError

from app import audit
from app.db import SessionLocal
from app.models import AuditLog, ClaimStatus, PaymentClaim

from .conftest import PNG, PNG2, PNG3, new_order, statement, submit


def today():
    return dt.datetime.now().strftime("%d/%m/%Y")


def upload(client, h, content):
    return client.post("/api/v1/admin/bank/statement-imports", headers=h["fin"],
                       files={"file": ("s.csv", content)})


def status_of(client, h, who, claim_id):
    return client.get(f"/api/v1/payment-claims/{claim_id}", headers=h[who]).json()["status"]


def test_screenshot_alone_never_verifies(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-1")
    r = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"], ref="REF123456")
    assert r.status_code == 202
    assert r.json()["status"] == "awaiting_bank_confirmation"


def test_statement_with_payment_code_auto_verifies(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-2")
    c = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"]).json()
    csv = statement(f"{today()},CEFTS CR {o['payment_code']} KAMAL,CEFT99887766,,4500.00,10000.00")
    res = upload(client, h, csv).json()
    assert res["rows_new"] == 1 and res["matching"]["verified"] == 1
    assert status_of(client, h, "c1", c["id"]) == "verified"
    detail = client.get(f"/api/v1/admin/claims/{c['id']}", headers=h["fin"]).json()
    assert detail["claim"]["basis"] == "bank_match" and detail["allocated_bank_transaction_id"]


def test_matching_credit_arrives_before_claim(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-3")
    upload(client, h, statement(f"{today()},CEFTS CR {o['payment_code']},CEFT1,,4500.00,100.00"))
    r = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"])
    assert r.json()["status"] == "verified"


def test_weak_match_goes_to_manual_review_not_verified(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-4")
    upload(client, h, statement(f"{today()},CEFTS CR UNKNOWN,CEFT2,,4500.00,100.00"))  # amount only
    r = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"])
    assert r.json()["status"] == "manual_review"


def test_one_bank_transaction_cannot_pay_two_orders(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "INV-5")
    o2 = new_order(client, h, ids["cust2"], "INV-6")
    upload(client, h, statement(f"{today()},CEFTS CR UNKNOWN,CEFT3,,4500.00,100.00"))
    c1 = submit(client, h["c1"], o1["id"], 450000, content=PNG).json()
    c2 = submit(client, h["c2"], o2["id"], 450000, content=PNG2).json()
    txn = client.get("/api/v1/admin/bank/transactions", headers=h["fin"]).json()[0]["id"]
    ok = client.post(f"/api/v1/admin/claims/{c1['id']}/decision", headers=h["rev"],
                     json={"decision": "approve", "reason": "checked statement", "bank_transaction_id": txn})
    assert ok.status_code == 200 and ok.json()["status"] == "verified"
    dup = client.post(f"/api/v1/admin/claims/{c2['id']}/decision", headers=h["rev"],
                      json={"decision": "approve", "reason": "checked statement", "bank_transaction_id": txn})
    assert dup.status_code == 409
    assert status_of(client, h, "c2", c2["id"]) == "manual_review"


def test_same_screenshot_from_other_customer_is_suspicious(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "INV-7")
    o2 = new_order(client, h, ids["cust2"], "INV-8")
    submit(client, h["c1"], o1["id"], 450000, content=PNG)
    r = submit(client, h["c2"], o2["id"], 450000, content=PNG)
    # customer sees neutral wording, never the word "suspicious" or a fraud reason
    assert r.json()["status"] == "suspicious"
    assert "fraud" not in (r.json()["message"] or "").lower()
    assert "reason" not in r.json()


def test_duplicate_reference_blocked(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "INV-9")
    o2 = new_order(client, h, ids["cust2"], "INV-10")
    submit(client, h["c1"], o1["id"], 450000, content=PNG, ref="TXN-ABC-123456")
    r = submit(client, h["c2"], o2["id"], 450000, content=PNG2, ref="txn abc 123456")
    assert r.json()["status"] == "suspicious"


def test_amount_mismatch_goes_to_review(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-11")
    r = submit(client, h["c1"], o["id"], 400000)
    assert r.json()["status"] == "manual_review"


def test_unique_amount_identifies_order(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-12", unique=True)
    assert o["expected_pay_amount_minor"] != 450000
    amt = f"{o['expected_pay_amount_minor'] / 100:.2f}"
    upload(client, h, statement(f"{today()},CEFTS CR SOMEONE,CEFT4,,{amt},100.00"))
    assert submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"]).json()["status"] == "verified"


def test_fake_file_type_rejected(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-13")
    r = submit(client, h["c1"], o["id"], 450000, content=b"MZ\x90\x00 not an image", name="receipt.png")
    assert r.status_code == 415


def test_customer_cannot_see_or_use_others_data(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-14")
    assert submit(client, h["c2"], o["id"], 450000).status_code == 404
    c = submit(client, h["c1"], o["id"], 450000).json()
    assert client.get(f"/api/v1/payment-claims/{c['id']}", headers=h["c2"]).status_code == 404
    assert client.get("/api/v1/admin/claims", headers=h["c1"]).status_code == 403


def test_override_requires_finance_and_justification(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-15")
    c = submit(client, h["c1"], o["id"], 450000).json()
    body = {"decision": "approve", "reason": "cash at counter"}
    assert client.post(f"/api/v1/admin/claims/{c['id']}/decision", headers=h["rev"], json=body).status_code == 403
    assert client.post(f"/api/v1/admin/claims/{c['id']}/decision", headers=h["fin"], json=body).status_code == 422
    body["override_justification"] = "Verified in person at branch with bank slip original"
    r = client.post(f"/api/v1/admin/claims/{c['id']}/decision", headers=h["fin"], json=body)
    assert r.json()["basis"] == "admin_override"


def test_database_refuses_verified_without_basis(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-16")
    c = submit(client, h["c1"], o["id"], 450000).json()
    with SessionLocal() as db:
        claim = db.get(PaymentClaim, c["id"])
        claim.status = ClaimStatus.verified.value
        with pytest.raises(IntegrityError):
            db.commit()


def test_statement_reimport_and_overlap_are_idempotent(client, world):
    ids, h = world
    rows = [f"{today()},CR A,R1,,100.00,1", f"{today()},CR A,R1,,100.00,1", f"{today()},CR B,R2,,200.00,2"]
    first = upload(client, h, statement(*rows)).json()
    assert first["rows_new"] == 3  # two identical deposits are both real
    assert upload(client, h, statement(*rows)).json()["status"] == "duplicate_file"
    overlap = upload(client, h, statement(*rows, f"{today()},CR C,R3,,300.00,3")).json()
    assert overlap["rows_new"] == 1 and overlap["duplicates"] == 3


def test_unparseable_rows_are_reported(client, world):
    ids, h = world
    res = upload(client, h, statement(f"{today()},ok,R1,,100.00,1", "notadate,bad,R2,,50.00,2")).json()
    assert res["rows_new"] == 1 and len(res["errors"]) == 1


def test_audit_chain_detects_tampering(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-17")
    submit(client, h["c1"], o["id"], 450000)
    assert client.get("/api/v1/admin/audit/verify", headers=h["fin"]).json()["intact"] is True
    with SessionLocal() as db:
        row = db.query(AuditLog).filter(AuditLog.action == "claim.submit").one()
        row.detail = {"order_id": 999}
        db.commit()
        ok, bad = audit.verify_chain(db)
    assert ok is False and bad == row.id


def test_rate_limit_per_order(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-18")
    codes = []
    for i in range(7):
        content = b"\x89PNG\r\n\x1a\n" + f"img{i}".encode() * 10
        r = submit(client, h["c1"], o["id"], 450000, content=content)
        codes.append(r.status_code)
        if r.status_code == 202:  # reject so another attempt is allowed
            cid = r.json()["id"]
            client.post(f"/api/v1/admin/claims/{cid}/decision", headers=h["rev"],
                        json={"decision": "reject", "reason": "test reject"})
    assert 429 in codes


def test_customer_order_view_and_claim_list(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-20")
    view = client.get(f"/api/v1/orders/{o['id']}", headers=h["c1"]).json()
    assert view["payment_code"] == o["payment_code"] and "pay_to" in view
    assert client.get(f"/api/v1/orders/{o['id']}", headers=h["c2"]).status_code == 404
    submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"])
    lst = client.get(f"/api/v1/orders/{o['id']}/payment-claims", headers=h["c1"]).json()
    assert len(lst) == 1 and "reason" not in lst[0]


def test_staff_can_view_proof_and_it_is_audited(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "INV-21")
    c = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"]).json()
    fid = client.get(f"/api/v1/admin/claims/{c['id']}", headers=h["rev"]).json()["files"][0]["id"]
    r = client.get(f"/api/v1/admin/files/{fid}", headers=h["rev"])
    assert r.status_code == 200 and r.headers["content-type"] == "image/png"
    assert r.headers["x-content-type-options"] == "nosniff"
    assert client.get(f"/api/v1/admin/files/{fid}", headers=h["c1"]).status_code == 403
    trail = client.get("/api/v1/admin/audit?entity=claim&entity_id=%d" % c["id"], headers=h["fin"]).json()
    assert any(a["action"] == "proof.view" for a in trail)
