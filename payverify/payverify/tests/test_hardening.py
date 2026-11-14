import base64
import io
import os
import random
import time

import pytest
from cryptography.fernet import Fernet
from PIL import Image

from app import crypto
from app.config import settings
from app.main import validate_settings
from app.models import Role

from .conftest import login, make_user, new_order, submit


def test_totp_matches_rfc6238_vector():
    secret = base64.b32encode(b"12345678901234567890").decode()
    assert crypto.totp(secret, for_time=59)[0] == "287082"
    assert crypto.totp(secret, for_time=1111111109)[0] == "081804"


def test_login_throttling_blocks_guessing_but_not_other_users(client, world):
    ids, h = world
    for _ in range(5):
        assert client.post("/api/v1/auth/login", json={"email": "c1@example.com", "password": "wrong-password-x"}).status_code == 401
    r = client.post("/api/v1/auth/login", json={"email": "c1@example.com", "password": "correct-horse-battery"})
    assert r.status_code == 429          # even the right password is refused while locked
    assert client.post("/api/v1/auth/login", json={"email": "c2@example.com", "password": "correct-horse-battery"}).status_code == 200


def test_staff_mfa_enrolment_login_and_replay(client, world, monkeypatch):
    ids, h = world
    monkeypatch.setattr(settings, "require_staff_mfa", True)
    r = client.post("/api/v1/auth/login", json={"email": "rev@example.com", "password": "correct-horse-battery"}).json()
    assert r["scope"] == "mfa_setup"
    setup_h = {"Authorization": f"Bearer {r['access_token']}"}
    assert client.get("/api/v1/admin/dashboard", headers=setup_h).status_code == 401      # setup token is useless elsewhere
    secret = client.post("/api/v1/auth/mfa/setup", headers=setup_h).json()["secret"]
    assert client.post("/api/v1/auth/mfa/confirm", headers=setup_h, json={"otp": "000000"}).status_code == 401
    code = crypto.totp(secret)[0]
    full = client.post("/api/v1/auth/mfa/confirm", headers=setup_h, json={"otp": code}).json()
    assert full["scope"] == "full"
    assert client.get("/api/v1/admin/dashboard", headers={"Authorization": f"Bearer {full['access_token']}"}).status_code == 200
    body = {"email": "rev@example.com", "password": "correct-horse-battery"}
    assert client.post("/api/v1/auth/login", json=body).json().get("detail") == "mfa_required"
    assert client.post("/api/v1/auth/login", json={**body, "otp": code}).status_code == 401    # same code cannot be replayed
    nxt = crypto.totp(secret, time.time() + 30)[0]
    assert client.post("/api/v1/auth/login", json={**body, "otp": nxt}).json()["scope"] == "full"


def test_super_admin_can_reset_mfa(client, world):
    ids, h = world
    sa = make_user("root@example.com", Role.super_admin)
    hs = login(client, "root@example.com")
    assert client.post(f"/api/v1/auth/users/{ids['rev']}/mfa-reset", headers=hs).status_code == 200
    assert client.post(f"/api/v1/auth/users/{ids['rev']}/mfa-reset", headers=h["rev"]).status_code == 403
    assert client.post(f"/api/v1/auth/users/{sa}/mfa-reset", headers=hs).status_code == 404   # cannot reset yourself


def test_proofs_are_encrypted_on_disk_and_readable_by_staff(client, world, monkeypatch):
    ids, h = world
    monkeypatch.setattr(settings, "data_key", Fernet.generate_key().decode())
    o = new_order(client, h, ids["cust1"], "E-1")
    png = b"\x89PNG\r\n\x1a\n" + b"secret-receipt-bytes" * 5
    c = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"], content=png).json()
    on_disk = [open(os.path.join(settings.storage_dir, f), "rb").read() for f in os.listdir(settings.storage_dir)]
    assert any(b.startswith(b"PVE1") and b"secret-receipt-bytes" not in b for b in on_disk)
    fid = client.get(f"/api/v1/admin/claims/{c['id']}", headers=h["rev"]).json()["files"][0]["id"]
    assert client.get(f"/api/v1/admin/files/{fid}", headers=h["rev"]).content == png


def test_production_refuses_unsafe_config(monkeypatch):
    monkeypatch.setattr(settings, "env", "production")
    monkeypatch.setattr(settings, "jwt_secret", "change-me-in-production")
    with pytest.raises(RuntimeError) as e:
        validate_settings()
    assert "PV_JWT_SECRET" in str(e.value) and "PV_DATA_KEY" in str(e.value)


def test_security_headers(client):
    r = client.get("/healthz")
    assert r.headers["x-content-type-options"] == "nosniff" and r.headers["x-frame-options"] == "DENY"


# ---------------- forensics ----------------
def blocks_image(seed, fmt="JPEG", quality=92, size=400, exif_software=None):
    rnd = random.Random(seed)
    im = Image.new("RGB", (size, size))
    for y in range(0, size, 25):
        for x in range(0, size, 25):
            im.paste((rnd.randrange(256), rnd.randrange(256), rnd.randrange(256)), (x, y, x + 25, y + 25))
    buf = io.BytesIO()
    kw = {}
    if exif_software:
        ex = Image.Exif(); ex[0x0131] = exif_software; kw["exif"] = ex
    im.save(buf, fmt, quality=quality, **kw)
    return buf.getvalue()


def rules(client, h, claim_id):
    d = client.get(f"/api/v1/admin/claims/{claim_id}", headers=h["rev"]).json()
    return {s["rule"] for s in d["fraud_signals"]}


def test_photoshop_exif_is_flagged(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "F-1")
    c = submit(client, h["c1"], o["id"], 450000, content=blocks_image(1, exif_software="Adobe Photoshop 25.0"), name="r.jpg").json()
    assert "edited_with_editor" in rules(client, h, c["id"])


def test_pdf_with_editor_producer_and_incremental_update_is_flagged(client, world):
    ids, h = world
    o = new_order(client, h, ids["cust1"], "F-2")
    pdf = b"%PDF-1.4\n1 0 obj<</Producer (Adobe Photoshop)>>endobj\n%%EOF\nupdate\n%%EOF\n"
    c = submit(client, h["c1"], o["id"], 450000, content=pdf, name="r.pdf").json()
    assert {"edited_with_editor", "pdf_modified_after_creation"} <= rules(client, h, c["id"])


def test_resaved_copy_of_another_customers_image_is_flagged_but_different_image_is_not(client, world):
    ids, h = world
    o1 = new_order(client, h, ids["cust1"], "F-3")
    o2 = new_order(client, h, ids["cust2"], "F-4")
    submit(client, h["c1"], o1["id"], 450000, content=blocks_image(7, quality=92), name="a.jpg")
    copy = submit(client, h["c2"], o2["id"], 450000, content=blocks_image(7, quality=60), name="b.jpg").json()   # re-compressed, different bytes
    assert "near_duplicate_image" in rules(client, h, copy["id"])
    o3 = new_order(client, h, ids["cust2"], "F-5")
    other = submit(client, h["c2"], o3["id"], 450000, content=blocks_image(99), name="c.jpg").json()
    assert "near_duplicate_image" not in rules(client, h, other["id"])
