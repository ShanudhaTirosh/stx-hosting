import os

from app.config import settings
from app.db import SessionLocal
from app.models import PaymentClaim, ProofFile, Role, User

from .conftest import login, make_user, new_order, submit


def test_customer_exports_only_their_own_data(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "D-1"); o2 = new_order(client, h, ids["cust2"], "D-2")
    submit(client, h["c1"], o1["id"], 450000, ref="MY-REF-123456")
    submit(client, h["c2"], o2["id"], 450000, content=b"\x89PNG\r\n\x1a\nother")
    d = client.get("/api/v1/my/data-export", headers=h["c1"]).json()
    assert d["account"]["email"] == "c1@example.com" and len(d["orders"]) == 1 and len(d["payment_claims"]) == 1
    assert "D-2" not in str(d) and "fraud" not in str(d).lower().replace("fraud-prevention", "")
    assert client.get("/api/v1/my/data-export", headers=h["fin"]).status_code == 403   # staff use the admin tools


def test_erasure_needs_super_admin_and_refuses_while_a_payment_is_open(client, world):
    ids, h = world
    make_user("root@example.com", Role.super_admin); hs = login(client, "root@example.com")
    o = new_order(client, h, ids["cust1"], "D-3")
    c = submit(client, h["c1"], o["id"], 450000).json()
    assert client.post(f"/api/v1/admin/customers/{ids['cust1']}/erase", headers=h["fin"]).status_code == 403
    assert client.post(f"/api/v1/admin/customers/{ids['cust1']}/erase", headers=hs).status_code == 409   # claim still open
    assert client.post(f"/api/v1/admin/customers/{ids['fin']}/erase", headers=hs).status_code == 409     # staff cannot be erased here
    assert c["status"] == "awaiting_bank_confirmation"


def test_erasure_removes_personal_data_but_keeps_financial_facts(client, world):
    ids, h = world
    make_user("root@example.com", Role.super_admin); hs = login(client, "root@example.com")
    o = new_order(client, h, ids["cust1"], "D-4")
    c = submit(client, h["c1"], o["id"], 450000, ref="KEEP-REF-123456").json()
    client.post(f"/api/v1/admin/claims/{c['id']}/decision", headers=h["rev"], json={"decision": "reject", "reason": "test reject"})
    with SessionLocal() as db:
        key = db.query(ProofFile).one().storage_key
    assert os.path.exists(os.path.join(settings.storage_dir, key))
    r = client.post(f"/api/v1/admin/customers/{ids['cust1']}/erase", headers=hs)
    assert r.status_code == 200 and r.json()["proof_files_deleted"] == 1
    assert not os.path.exists(os.path.join(settings.storage_dir, key))
    with SessionLocal() as db:
        u = db.get(User, ids["cust1"]); cl = db.get(PaymentClaim, c["id"])
        assert u.email.startswith("erased-") and u.name == ""
        assert cl.claimed_amount_minor == 450000 and cl.claimed_reference == "KEEP-REF-123456" and cl.claimed_sender_name is None
        assert db.query(ProofFile).one().sha256                         # hash kept so the receipt can't be reused
    assert client.post("/api/v1/auth/login", json={"email": "c1@example.com", "password": "correct-horse-battery"}).status_code == 401
    trail = client.get("/api/v1/admin/audit?entity=user&entity_id=%d" % ids["cust1"], headers=hs).json()
    assert any(a["action"] == "dsar.erase" for a in trail)
