import base64
import hashlib
import json
import secrets
import time
from datetime import datetime, timedelta, timezone

from cryptography.exceptions import InvalidSignature
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import ec
from fastapi import APIRouter, Depends, HTTPException, Request, Response
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.orm import Session

from .. import audit, combank_receipt
from ..config import settings
from ..db import get_db
from ..models import Device, EnrolmentCode, Role, SmsEvent, User, utcnow
from ..security import require_roles
from .. import sms

router = APIRouter(prefix="/api/v1", tags=["devices"])
FINANCE = (Role.finance_admin, Role.super_admin)
ANY_STAFF = (Role.support, Role.reviewer, Role.finance_admin, Role.super_admin)
ALPHABET = "ABCDEFGHJKMNPQRSTUVWXYZ23456789"
MAX_SKEW = 300


def _h(s: str) -> str:
    return hashlib.sha256(s.encode()).hexdigest()


# ---------- device request signing: ECDSA P-256 / SHA-256 over "METHOD\nPATH\nTIMESTAMP\nSHA256(body)" ----------
def canonical(method: str, path: str, ts: str, body: bytes) -> bytes:
    return f"{method}\n{path}\n{ts}\n{hashlib.sha256(body).hexdigest()}".encode()


async def device_auth(request: Request, db: Session = Depends(get_db)) -> Device:
    h = request.headers
    try:
        dev_id, ts, sig = int(h["x-device-id"]), h["x-timestamp"], base64.b64decode(h["x-signature"])
        if abs(time.time() - int(ts)) > MAX_SKEW:
            raise ValueError("clock skew")
    except (KeyError, ValueError):
        raise HTTPException(401, "Bad device credentials")
    dev = db.get(Device, dev_id)
    if not dev or dev.revoked_at:
        raise HTTPException(401, "Bad device credentials")
    pub = serialization.load_der_public_key(base64.b64decode(dev.public_key_der_b64))
    try:
        pub.verify(sig, canonical(request.method, request.url.path, ts, await request.body()), ec.ECDSA(hashes.SHA256()))
    except InvalidSignature:
        raise HTTPException(401, "Bad device credentials")
    return dev


# ---------- enrolment ----------
@router.post("/admin/devices/enrolment-codes", status_code=201, tags=["admin"])
def new_code(user: User = Depends(require_roles(*FINANCE)), db: Session = Depends(get_db)):
    code = "".join(secrets.choice(ALPHABET) for _ in range(10))
    db.add(EnrolmentCode(code_hash=_h(code), created_by=user.id, expires_at=utcnow() + timedelta(minutes=15)))
    audit.log(db, action="device.code_issued", entity="device", entity_id=None, actor_id=user.id)
    db.commit()
    return {"code": code, "expires_in_minutes": 15}   # shown once; only its hash is stored


class EnrolIn(BaseModel):
    code: str
    name: str = Field(min_length=1, max_length=80)
    public_key_der_b64: str


@router.post("/devices/enrol", status_code=201)
def enrol(body: EnrolIn, db: Session = Depends(get_db)):
    ec_row = db.execute(select(EnrolmentCode).where(EnrolmentCode.code_hash == _h(body.code.strip().upper()))
                        ).scalar_one_or_none()
    exp = ec_row and (ec_row.expires_at if ec_row.expires_at.tzinfo else ec_row.expires_at.replace(tzinfo=timezone.utc))
    if not ec_row or ec_row.used_at or exp < utcnow():
        raise HTTPException(400, "Invalid or expired enrolment code")
    try:
        key = serialization.load_der_public_key(base64.b64decode(body.public_key_der_b64))
        assert isinstance(key, ec.EllipticCurvePublicKey) and key.curve.name == "secp256r1"
    except Exception:
        raise HTTPException(422, "public_key_der_b64 must be a P-256 SubjectPublicKeyInfo")
    ec_row.used_at = utcnow()
    dev = Device(name=body.name, public_key_der_b64=body.public_key_der_b64, enrolled_by=ec_row.created_by)
    db.add(dev)
    db.flush()
    audit.log(db, action="device.enrolled", entity="device", entity_id=dev.id, actor_id=ec_row.created_by,
              detail={"name": body.name})
    db.commit()
    return {"device_id": dev.id}


# ---------- device calls ----------
class Heartbeat(BaseModel):
    app_version: str = ""
    sms_permission: bool = False
    listener_enabled: bool = False
    battery_optimised: bool = False
    queue_depth: int = 0
    last_error: str | None = Field(None, max_length=300)


@router.post("/devices/heartbeat")
def heartbeat(body: Heartbeat, dev: Device = Depends(device_auth), db: Session = Depends(get_db)):
    dev.last_heartbeat_at = utcnow()
    dev.status = body.model_dump()
    db.commit()
    return {"ok": True}


@router.get("/devices/config")
def config(dev: Device = Depends(device_auth)):
    return {"sender_allowlist": [s.strip() for s in settings.sms_sender_allowlist.split(",") if s.strip()],
            "notification_packages": [s.strip() for s in settings.notification_package_allowlist.split(",") if s.strip()]}


class SmsItem(BaseModel):
    idempotency_key: str = Field(min_length=16, max_length=64)
    source: str = Field("sms", pattern="^(sms|notification)$")
    sender: str = Field(max_length=64)
    body: str = Field(max_length=1000)
    received_at: str


class SmsBatch(BaseModel):
    events: list[SmsItem] = Field(max_length=100)


@router.post("/devices/sms-events")
def sms_events(batch: SmsBatch, dev: Device = Depends(device_auth), db: Session = Depends(get_db)):
    res = sms.ingest(db, dev.id, [e.model_dump() for e in batch.events])
    db.commit()
    return {"results": res}


# ---------- admin ----------
@router.get("/admin/devices", tags=["admin"])
def list_devices(user: User = Depends(require_roles(*ANY_STAFF)), db: Session = Depends(get_db)):
    now = utcnow()
    out = []
    for d in db.execute(select(Device).order_by(Device.id)).scalars():
        hb = d.last_heartbeat_at and (d.last_heartbeat_at if d.last_heartbeat_at.tzinfo else d.last_heartbeat_at.replace(tzinfo=timezone.utc))
        stale = (not hb) or (now - hb) > timedelta(minutes=settings.device_heartbeat_stale_minutes)
        out.append({"id": d.id, "name": d.name, "revoked": bool(d.revoked_at), "last_heartbeat_at": d.last_heartbeat_at,
                    "stale": stale and not d.revoked_at, "status": d.status})
    return out


@router.post("/admin/devices/{device_id}/revoke", tags=["admin"])
def revoke(device_id: int, user: User = Depends(require_roles(*FINANCE)), db: Session = Depends(get_db)):
    d = db.get(Device, device_id)
    if not d:
        raise HTTPException(404, "Not found")
    d.revoked_at = utcnow()
    audit.log(db, action="device.revoked", entity="device", entity_id=d.id, actor_id=user.id)
    db.commit()
    return {"id": d.id, "revoked": True}


@router.get("/admin/sms-events", tags=["admin"])
def list_sms(unparsed: bool = False, limit: int = 100, user: User = Depends(require_roles(*FINANCE)),
             db: Session = Depends(get_db)):
    q = select(SmsEvent).order_by(SmsEvent.id.desc()).limit(min(limit, 500))
    if unparsed:
        q = q.where(SmsEvent.parsed_ok.is_(False))
    return [{"id": e.id, "device_id": e.device_id, "source": e.source, "sender": e.sender, "body": e.body,
             "received_at": e.received_at, "direction": e.direction, "amount_minor": e.amount_minor, "ref": e.ref,
             "parsed_ok": e.parsed_ok, "branch": e.branch, "receipt_url": e.receipt_url,
             "receipt_stan": e.receipt_stan, "has_receipt_pdf": bool(e.receipt_pdf_key),
             "receipt_data": e.receipt_data, "matched_bank_txn_id": e.matched_bank_txn_id}
            for e in db.execute(q).scalars()]


@router.get("/admin/sms-events/{sms_id}/receipt-pdf", tags=["admin"])
def get_receipt_pdf(sms_id: int, user: User = Depends(require_roles(*FINANCE)), db: Session = Depends(get_db)):
    ev = db.get(SmsEvent, sms_id)
    if not ev or not ev.receipt_pdf_key:
        raise HTTPException(404, "Receipt PDF not available")
    try:
        pdf_bytes = combank_receipt.read_receipt_pdf(ev.receipt_pdf_key)
    except Exception as e:
        raise HTTPException(500, f"Could not read receipt PDF: {str(e)}")
    stan = ev.receipt_stan or "receipt"
    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={"Content-Disposition": f'inline; filename="combank_receipt_{stan}.pdf"'},
    )


class DeviceVerifyIn(BaseModel):
    reason: str = "Verified by mobile app"


@router.get("/devices/claims")
def device_list_claims(
    status: str | None = None,
    dev: Device = Depends(device_auth),
    db: Session = Depends(get_db),
):
    """Retrieve claims for the mobile app, enriched with customer email, ID, and order details."""
    from ..models import ClaimStatus as S, Order, PaymentClaim, User

    stmt = (
        select(PaymentClaim, Order, User)
        .join(Order, Order.id == PaymentClaim.order_id)
        .outerjoin(User, User.id == PaymentClaim.customer_id)
    )
    if status:
        stmt = stmt.where(PaymentClaim.status == status)
    else:
        stmt = stmt.where(PaymentClaim.status.in_([
            S.pending.value,
            S.awaiting_bank_confirmation.value,
            S.manual_review.value,
            S.verified.value,
        ]))
    rows = db.execute(stmt.order_by(PaymentClaim.id.desc()).limit(50)).all()
    return [{
        "id": c.id,
        "order_id": o.id,
        "invoice_no": o.invoice_no,
        "amount_minor": c.claimed_amount_minor,
        "amount_formatted": f"Rs. {c.claimed_amount_minor / 100:,.2f}",
        "status": c.status,
        "reference": c.claimed_reference,
        "customer": {
            "id": u.id if u else None,
            "email": u.email if u else None,
            "name": u.name if u else None,
            "phone": getattr(u, "phone", None) if u else None,
            "external_id": getattr(u, "external_id", None) if u else None,
        } if u else None,
        "created_at": c.created_at.isoformat() if c.created_at else None,
        "verified_at": c.verified_at.isoformat() if c.verified_at else None,
    } for c, o, u in rows]


@router.post("/devices/claims/{claim_id}/verify")
def device_verify_claim(
    claim_id: int,
    body: DeviceVerifyIn = DeviceVerifyIn(),
    dev: Device = Depends(device_auth),
    db: Session = Depends(get_db),
):
    """Allow enrolled mobile phone app to manually verify a payment claim with hardware key."""
    from ..models import Allocation, BankTransaction, ClaimStatus as S, Order, PaymentClaim
    from ..state import transition
    from sqlalchemy.exc import IntegrityError

    c = db.get(PaymentClaim, claim_id)
    if not c:
        raise HTTPException(404, "Claim not found")
    if c.status == S.verified.value:
        return {"claim_id": c.id, "status": "verified", "already_verified": True}

    order = db.get(Order, c.order_id)
    from ..matcher import _candidates
    cands = _candidates(db, c, order)

    if cands:
        t = cands[0]
        try:
            with db.begin_nested():
                db.add(Allocation(bank_transaction_id=t.id, claim_id=c.id, amount_minor=t.amount_minor))
                db.flush()
            transition(
                db,
                c,
                S.verified,
                actor_id=dev.enrolled_by,
                basis="bank_match",
                reason="device_manual_verify",
                extra={"bank_transaction_id": t.id, "device_id": dev.id, "note": body.reason},
            )
            db.commit()
            return {"claim_id": c.id, "status": "verified", "basis": "bank_match"}
        except IntegrityError:
            pass

    transition(
        db,
        c,
        S.verified,
        actor_id=dev.enrolled_by,
        basis="admin_override",
        reason="device_manual_verify",
        extra={"device_id": dev.id, "justification": f"Manual verify via phone device {dev.name}: {body.reason}"},
    )
    db.commit()
    return {"claim_id": c.id, "status": "verified", "basis": "admin_override"}
