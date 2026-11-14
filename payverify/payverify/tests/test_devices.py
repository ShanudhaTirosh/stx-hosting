import base64
import datetime as dt
import hashlib
import json
import time
import uuid

from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec

from app.routers.devices import canonical

from .conftest import new_order, statement, submit


class Phone:
    def __init__(self, client, h, name="Test phone"):
        self.c, self.key = client, ec.generate_private_key(ec.SECP256R1())
        pub = self.key.public_key().public_bytes(serialization.Encoding.DER, serialization.PublicFormat.SubjectPublicKeyInfo)
        code = client.post("/api/v1/admin/devices/enrolment-codes", headers=h["fin"]).json()["code"]
        r = client.post("/api/v1/devices/enrol", json={"code": code, "name": name,
                                                       "public_key_der_b64": base64.b64encode(pub).decode()})
        assert r.status_code == 201, r.text
        self.id = r.json()["device_id"]
        self.code = code

    def call(self, method, path, payload=None, ts=None, key=None):
        body = json.dumps(payload).encode() if payload is not None else b""
        ts = str(ts or int(time.time()))
        sig = (key or self.key).sign(canonical(method, path, ts, body), ec.ECDSA(hashes.SHA256()))
        headers = {"X-Device-Id": str(self.id), "X-Timestamp": ts, "X-Signature": base64.b64encode(sig).decode(),
                   "Content-Type": "application/json"}
        return self.c.request(method, path, content=body, headers=headers)

    def sms(self, body, sender="COMBANK", source="sms"):
        return {"idempotency_key": uuid.uuid4().hex, "source": source, "sender": sender, "body": body,
                "received_at": dt.datetime.now(dt.timezone.utc).isoformat()}


def test_enrolment_code_is_single_use_and_signing_works(client, world):
    ids, h = world
    p = Phone(client, h)
    r = client.post("/api/v1/devices/enrol", json={"code": p.code, "name": "x", "public_key_der_b64": "AAAA"})
    assert r.status_code == 400
    assert p.call("POST", "/api/v1/devices/heartbeat", {"app_version": "1.0", "sms_permission": True}).status_code == 200
    assert "COMBANK" in p.call("GET", "/api/v1/devices/config").json()["sender_allowlist"]


def test_bad_signatures_stale_timestamps_and_revoked_devices_are_refused(client, world):
    ids, h = world
    p = Phone(client, h)
    other = ec.generate_private_key(ec.SECP256R1())
    assert p.call("POST", "/api/v1/devices/heartbeat", {}, key=other).status_code == 401
    assert p.call("POST", "/api/v1/devices/heartbeat", {}, ts=int(time.time()) - 3600).status_code == 401
    assert client.post("/api/v1/devices/heartbeat", json={}).status_code == 401
    # tampered body: signature was made for different bytes
    body = b'{"app_version":"1"}'
    ts = str(int(time.time()))
    sig = p.key.sign(canonical("POST", "/api/v1/devices/heartbeat", ts, b'{"app_version":"2"}'), ec.ECDSA(hashes.SHA256()))
    r = client.post("/api/v1/devices/heartbeat", content=body, headers={
        "X-Device-Id": str(p.id), "X-Timestamp": ts, "X-Signature": base64.b64encode(sig).decode(), "Content-Type": "application/json"})
    assert r.status_code == 401
    assert client.post(f"/api/v1/admin/devices/{p.id}/revoke", headers=h["fin"]).status_code == 200
    assert p.call("POST", "/api/v1/devices/heartbeat", {}).status_code == 401


def test_otp_and_foreign_senders_never_stored(client, world):
    ids, h = world
    p = Phone(client, h)
    batch = {"events": [p.sms("Your OTP is 482911. Do not share."), p.sms("Rs. 1,000.00 credited to a/c", sender="Scammer"),
                        p.sms("Rs. 4,500.00 credited to your account. Ref: CEFT889977")]}
    res = p.call("POST", "/api/v1/devices/sms-events", batch).json()["results"]
    assert [r["result"] for r in res] == ["dropped_sensitive", "dropped_sender", "stored"]
    rows = client.get("/api/v1/admin/sms-events", headers=h["fin"]).json()
    assert len(rows) == 1 and rows[0]["amount_minor"] == 450000 and rows[0]["ref"] == "CEFT889977"


def test_sms_retries_are_idempotent(client, world):
    ids, h = world
    p = Phone(client, h)
    ev = p.sms("Rs. 100.00 credited. Ref: ABC123456")
    for expected in ("stored", "duplicate"):
        assert p.call("POST", "/api/v1/devices/sms-events", {"events": [ev]}).json()["results"][0]["result"] == expected


def test_sms_corroborates_but_never_verifies(client, world):
    ids, h = world
    p = Phone(client, h)
    o = new_order(client, h, ids["cust1"], "S-1")
    c = submit(client, h["c1"], o["id"], o["expected_pay_amount_minor"]).json()
    p.call("POST", "/api/v1/devices/sms-events", {"events": [p.sms("Rs. 4,500.00 credited to your account. Ref: CEFT111222")]})
    det = client.get(f"/api/v1/admin/claims/{c['id']}", headers=h["rev"]).json()
    assert "sms_corroborated" in {s["rule"] for s in det["fraud_signals"]}
    assert det["claim"]["status"] == "awaiting_bank_confirmation"      # SMS alone is not bank confirmation
    # a spoofed SMS from the allowed sender name still cannot verify anything
    assert client.get(f"/api/v1/payment-claims/{c['id']}", headers=h["c1"]).json()["status"] != "verified"


def test_sms_linked_to_bank_credit_after_import(client, world):
    ids, h = world
    p = Phone(client, h)
    p.call("POST", "/api/v1/devices/sms-events", {"events": [p.sms("Rs. 4,500.00 credited. Ref: CEFT555666")]})
    d = dt.datetime.now().strftime("%d/%m/%Y")
    res = client.post("/api/v1/admin/bank/statement-imports", headers=h["fin"], files={"file": ("s.csv", statement(
        f"{d},CEFTS CR,CEFT555666,,4500.00,1.00"))}).json()
    assert res["sms_linked"] == 1


def test_device_health_shows_stale(client, world):
    ids, h = world
    p = Phone(client, h)
    assert client.get("/api/v1/admin/devices", headers=h["fin"]).json()[0]["stale"] is True   # never reported
    p.call("POST", "/api/v1/devices/heartbeat", {"queue_depth": 3})
    d = client.get("/api/v1/admin/devices", headers=h["fin"]).json()[0]
    assert d["stale"] is False and d["status"]["queue_depth"] == 3
