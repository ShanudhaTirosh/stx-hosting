import os
from datetime import timedelta

from app import retention
from app.config import settings
from app.db import SessionLocal
from app.models import AiAnalysis, PaymentClaim, ProofFile, Role, SmsEvent, utcnow

from .conftest import PNG, login, make_user, new_order, submit


def age(claim_id, days):
    with SessionLocal() as db:
        c = db.get(PaymentClaim, claim_id)
        c.created_at = utcnow() - timedelta(days=days)
        if c.verified_at:
            c.verified_at = c.created_at
        db.commit()


def test_rejected_proofs_purged_after_period_but_hashes_stay(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "R-1")
    c = submit(client, h["c1"], o["id"], 450000, content=PNG, ref="REF-KEEP-123456", ).json()
    client.post(f"/api/v1/admin/claims/{c['id']}/decision", headers=h["rev"], json={"decision": "reject", "reason": "test reject"})
    age(c["id"], settings.retention_rejected_days + 1)
    with SessionLocal() as db:
        dry = retention.run(db)
        assert dry["applied"] is False and dry["proof_files"] == 1
        assert db.query(ProofFile).one().purged_at is None          # dry run changes nothing
        key = db.query(ProofFile).one().storage_key
        assert os.path.exists(os.path.join(settings.storage_dir, key))
        done = retention.run(db, apply=True)
        assert done["claims"] == 1
        pf = db.query(ProofFile).one()
        assert pf.purged_at and pf.sha256 and not os.path.exists(os.path.join(settings.storage_dir, key))
        assert db.get(PaymentClaim, c["id"]).claimed_sender_name is None
        assert retention.run(db, apply=True)["claims"] == 0         # idempotent
    fid = client.get(f"/api/v1/admin/claims/{c['id']}", headers=h["rev"]).json()["files"][0]
    assert fid["purged"] is True
    assert client.get(f"/api/v1/admin/files/{fid['id']}", headers=h["rev"]).status_code == 410


def test_open_and_recent_claims_are_never_purged(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "R-2")
    o2 = new_order(client, h, ids["cust2"], "R-3")
    open_claim = submit(client, h["c1"], o1["id"], 450000).json()           # still awaiting the bank
    rej = submit(client, h["c2"], o2["id"], 450000, content=PNG + b"x").json()
    client.post(f"/api/v1/admin/claims/{rej['id']}/decision", headers=h["rev"], json={"decision": "reject", "reason": "test reject"})
    age(open_claim["id"], 5000)       # very old but unresolved: must survive
    age(rej["id"], 5)                 # rejected but recent: must survive
    with SessionLocal() as db:
        assert retention.run(db, apply=True)["claims"] == 0


def test_sms_text_wiped_but_amount_and_reference_kept(client, world):
    ids, h = world
    with SessionLocal() as db:
        from app.models import Device
        d = Device(name="p", public_key_der_b64="x", enrolled_by=ids["fin"]); db.add(d); db.flush()
        db.add(SmsEvent(device_id=d.id, idempotency_key="k" * 20, source="sms", sender="COMBANK", body="Rs. 4,500.00 credited ...",
                        received_at=utcnow(), direction="credit", amount_minor=450000, ref="CEFT1", parsed_ok=True,
                        created_at=utcnow() - timedelta(days=settings.retention_sms_body_days + 1)))
        db.commit()
        assert retention.run(db, apply=True)["sms_bodies"] == 1
        e = db.query(SmsEvent).one()
        assert e.body == "" and e.amount_minor == 450000 and e.ref == "CEFT1"


def test_only_super_admin_can_trigger_and_default_is_dry_run(client, world):
    ids, h = world
    make_user("root2@example.com", Role.super_admin)
    hs = login(client, "root2@example.com")
    assert client.post("/api/v1/admin/retention/run", headers=h["fin"]).status_code == 403
    assert client.post("/api/v1/admin/retention/run", headers=hs).json()["applied"] is False
