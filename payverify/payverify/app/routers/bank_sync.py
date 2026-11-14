"""ComBank WhatsApp Banking integration router.
Handles:
1. Zero-leak OTP relay from enrolled Android devices to the WhatsApp bot.
2. Ingestion of parsed WhatsApp bank statements into BankTransaction.
3. Automated reconciliation of claims against newly ingested deposits.
4. Bot status inspection and trigger controls for staff.
"""
import hashlib
import json
import time
from datetime import datetime, timezone
import httpx
from fastapi import APIRouter, Depends, HTTPException, Header, Request, status
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.orm import Session

from .. import audit, matcher, sms
from ..config import settings
from ..db import get_db
from ..models import (
    BankBalanceSnapshot,
    BankStatementImport,
    BankTransaction,
    ClaimStatus as S,
    Device,
    Order,
    PaymentClaim,
    Role,
    User,
    utcnow,
)
from .devices import device_auth

router = APIRouter(prefix="/api/v1", tags=["bank_sync"])

FINANCE = (Role.finance_admin, Role.super_admin)
ANY_STAFF = (Role.support, Role.reviewer, Role.finance_admin, Role.super_admin)


# ---------- In-Memory Ephemeral OTP State Bridge (Zero Leak) ----------
class OtpBridge:
    def __init__(self):
        self.awaiting: bool = False
        self.expires_at: float = 0.0

    def set_awaiting(self, seconds: int = 60):
        self.awaiting = True
        self.expires_at = time.time() + seconds

    def is_active(self) -> bool:
        if self.awaiting and time.time() < self.expires_at:
            return True
        self.awaiting = False
        return False

    def time_remaining(self) -> int:
        if self.is_active():
            return max(0, int(self.expires_at - time.time()))
        return 0

    def clear(self):
        self.awaiting = False
        self.expires_at = 0.0


otp_bridge = OtpBridge()


# ---------- Device Endpoints (Signed by enrolled phone) ----------
class OtpRelayIn(BaseModel):
    otp: str = Field(pattern=r"^\d{4,8}$")
    sender: str = Field(max_length=64)


@router.get("/devices/otp-session-active")
def otp_session_active(dev: Device = Depends(device_auth)):
    """Android app checks if an OTP is currently expected before relaying."""
    return {
        "awaiting": otp_bridge.is_active(),
        "expires_in_seconds": otp_bridge.time_remaining(),
    }


@router.post("/devices/otp-relay")
async def relay_otp(
    body: OtpRelayIn,
    dev: Device = Depends(device_auth),
    db: Session = Depends(get_db),
):
    """Secure zero-leak OTP relay.
    Transfers OTP in-memory directly to the waiting WhatsApp bot session.
    The OTP is NEVER stored in database tables or logs.
    """
    # 1. Sender validation
    allowed = {
        s.strip().lower()
        for s in settings.sms_sender_allowlist.split(",")
        if s.strip()
    }
    if body.sender.strip().lower() not in allowed:
        raise HTTPException(status.HTTP_400_BAD_REQUEST, "Sender not in allowlist")

    otp_code = body.otp.strip()
    otp_bridge.clear()

    # 2. Forward to WhatsApp bot service in-memory
    bot_url = settings.whatsapp_bot_url.rstrip("/") + "/otp"
    headers = {}
    if settings.whatsapp_bot_secret:
        headers["X-Bot-Secret"] = settings.whatsapp_bot_secret

    delivered = False
    try:
        async with httpx.AsyncClient(timeout=10.0) as client:
            resp = await client.post(bot_url, json={"otp": otp_code}, headers=headers)
            if resp.status_code == 200:
                delivered = resp.json().get("delivered", False)
    except Exception:
        delivered = False

    # Audit log records the event without exposing the actual OTP digits
    audit.log(
        db,
        action="device.otp_relayed",
        entity="device",
        entity_id=dev.id,
        detail={"sender": body.sender, "delivered_to_bot": delivered},
    )
    db.commit()

    return {"relayed": True, "delivered_to_bot": delivered}


# ---------- Bot Endpoints ----------
class AwaitingOtpIn(BaseModel):
    awaiting: bool
    expires_in_seconds: int = 60


@router.post("/bank-sync/session-awaiting-otp")
def set_awaiting_otp(
    body: AwaitingOtpIn,
    x_bot_secret: str | None = Header(None, alias="X-Bot-Secret"),
):
    """WhatsApp bot signals that ComBank requested an OTP."""
    if settings.whatsapp_bot_secret and x_bot_secret != settings.whatsapp_bot_secret:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid bot secret")
    if body.awaiting:
        otp_bridge.set_awaiting(body.expires_in_seconds)
    else:
        otp_bridge.clear()
    return {"ok": True, "awaiting": otp_bridge.is_active()}


class TransactionItem(BaseModel):
    value_date: str
    value_dt: str
    remarks: str
    amount_minor: int
    direction: str = Field(pattern="^(credit|debit)$")
    branch: str | None = None
    channel: str | None = None
    raw: str | None = None


class StatementIn(BaseModel):
    account_mask: str | None = None
    account_tail: str | None = None
    current_balance_minor: int | None = None
    available_balance_minor: int | None = None
    transactions: list[TransactionItem] = Field(default_factory=list)


@router.post("/bank-sync/whatsapp-history")
def ingest_whatsapp_history(
    body: StatementIn,
    x_bot_secret: str | None = Header(None, alias="X-Bot-Secret"),
    db: Session = Depends(get_db),
):
    """Ingest transactions retrieved from ComBank WhatsApp Banking.
    Stores as authoritative bank statement records and triggers deterministic claim matching.
    """
    if settings.whatsapp_bot_secret and x_bot_secret != settings.whatsapp_bot_secret:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid bot secret")

    # Account tail verification if configured
    if settings.bank_account_tail and body.account_tail:
        if body.account_tail != settings.bank_account_tail:
            raise HTTPException(
                status.HTTP_400_BAD_REQUEST,
                "Account tail does not match configured merchant receiving account",
            )

    # Find a default admin user ID for audit/ownership
    admin_user = db.execute(
        select(User.id)
        .where(User.role.in_([Role.super_admin.value, Role.finance_admin.value]))
        .limit(1)
    ).scalar_one_or_none()

    # Create import record
    raw_bytes = json.dumps(
        [t.model_dump() for t in body.transactions], sort_keys=True
    ).encode()
    file_sha = hashlib.sha256(
        raw_bytes + str(time.time()).encode()
    ).hexdigest()

    imp = BankStatementImport(
        source="combank_whatsapp",
        file_sha256=file_sha,
        imported_by=admin_user,
        rows_total=len(body.transactions),
    )
    db.add(imp)
    db.flush()

    total_new = 0
    total_dups = 0

    for t in body.transactions:
        try:
            dt = datetime.fromisoformat(t.value_dt.replace("Z", "+00:00"))
            if dt.tzinfo is None:
                dt = dt.replace(tzinfo=timezone.utc)
        except Exception:
            dt = utcnow()

        # Stable deduplication key
        norm_desc = " ".join(t.remarks.split()).strip().upper()
        base = (dt.date().isoformat(), t.amount_minor, t.direction, norm_desc)
        key = hashlib.sha256(repr(base).encode()).hexdigest()

        if db.execute(
            select(BankTransaction.id).where(BankTransaction.dedupe_key == key)
        ).first():
            total_dups += 1
            continue

        txn = BankTransaction(
            import_id=imp.id,
            bank_txn_ref=t.branch,
            value_dt=dt,
            amount_minor=t.amount_minor,
            direction=t.direction,
            counterparty_name=None,
            remarks=t.remarks,
            dedupe_key=key,
        )
        db.add(txn)
        total_new += 1

    imp.rows_new = total_new
    db.flush()

    # Vector 3: Record bank balance snapshot if available
    if body.current_balance_minor is not None or body.available_balance_minor is not None:
        snap = BankBalanceSnapshot(
            source="combank_whatsapp",
            account_mask=body.account_mask,
            account_tail=body.account_tail,
            current_balance_minor=body.current_balance_minor,
            available_balance_minor=body.available_balance_minor,
            created_at=utcnow(),
        )
        db.add(snap)
        db.flush()

    # Link incoming SMS alerts to bank credits
    sms.link_to_bank(db)

    # Deterministic Reconciliation: Trigger run_all_awaiting on pending claims
    matching_summary = {"verified": 0, "manual_review": 0, "waiting": 0}
    if total_new > 0:
        matching_summary = matcher.run_all_awaiting(db)

    audit.log(
        db,
        action="bank_sync.whatsapp_ingested",
        entity="bank_statement_import",
        entity_id=imp.id,
        detail={
            "transactions_new": total_new,
            "duplicates": total_dups,
            "matching": matching_summary,
            "current_balance_minor": body.current_balance_minor,
        },
    )
    db.commit()

    return {
        "status": "success",
        "import_id": imp.id,
        "rows_total": len(body.transactions),
        "rows_new": total_new,
        "duplicates": total_dups,
        "matching": matching_summary,
    }


class BalanceIn(BaseModel):
    account_mask: str | None = None
    account_tail: str | None = None
    current_balance_minor: int | None = None
    available_balance_minor: int | None = None
    source: str = "combank_whatsapp"


@router.post("/bank-sync/balance")
def ingest_balance(
    body: BalanceIn,
    x_bot_secret: str | None = Header(None, alias="X-Bot-Secret"),
    db: Session = Depends(get_db),
):
    """Ingest balance snapshot from WhatsApp bot or API."""
    if settings.whatsapp_bot_secret and x_bot_secret != settings.whatsapp_bot_secret:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid bot secret")

    snap = BankBalanceSnapshot(
        source=body.source,
        account_mask=body.account_mask,
        account_tail=body.account_tail,
        current_balance_minor=body.current_balance_minor,
        available_balance_minor=body.available_balance_minor,
        created_at=utcnow(),
    )
    db.add(snap)
    db.commit()
    return {
        "status": "success",
        "snapshot_id": snap.id,
        "current_balance_minor": snap.current_balance_minor,
        "available_balance_minor": snap.available_balance_minor,
    }


# ---------- Admin Staff Controls ----------
async def trigger_bot_sync_internal() -> dict:
    """Internal helper to request statement sync from the WhatsApp bot in a non-blocking manner."""
    bot_url = settings.whatsapp_bot_url.rstrip("/") + "/sync"
    headers = {}
    if settings.whatsapp_bot_secret:
        headers["X-Bot-Secret"] = settings.whatsapp_bot_secret

    try:
        async with httpx.AsyncClient(timeout=120.0) as client:
            resp = await client.post(bot_url, headers=headers)
            return resp.json()
    except Exception as e:
        return {"success": False, "error": str(e)}


@router.post("/bank-sync/trigger")
async def trigger_whatsapp_sync(
    request: Request,
    db: Session = Depends(get_db),
):
    """Trigger an on-demand WhatsApp bank sync session."""
    # Verify user has finance/admin session
    from ..security import current_user
    user = current_user(request, db)
    if not user or user.role not in [Role.finance_admin.value, Role.super_admin.value]:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Finance admin required")

    data = await trigger_bot_sync_internal()
    if not data.get("success", False):
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY,
            data.get("error", f"Could not reach WhatsApp bot service at {settings.whatsapp_bot_url}"),
        )
    return data


@router.get("/bank-sync/status")
async def get_whatsapp_status(
    request: Request,
    db: Session = Depends(get_db),
):
    """Get WhatsApp bot connection status and latest sync information."""
    from ..security import current_user
    user = current_user(request, db)
    if not user or user.role not in [r.value for r in ANY_STAFF]:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Staff authentication required")

    bot_url = settings.whatsapp_bot_url.rstrip("/") + "/status"
    bot_status = {
        "connected": False,
        "state": "UNREACHABLE",
        "lastSyncAt": None,
        "lastError": "Bot service offline",
    }
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(bot_url)
            if resp.status_code == 200:
                bot_status = resp.json()
    except Exception:
        pass

    # Fetch latest imports
    latest_imports = (
        db.execute(
            select(BankStatementImport)
            .where(BankStatementImport.source == "combank_whatsapp")
            .order_by(BankStatementImport.id.desc())
            .limit(5)
        )
        .scalars()
        .all()
    )

    return {
        "bot": bot_status,
        "config": {
            "sync_interval_minutes": 60,
            "bot_sync_on_claim": settings.bot_sync_on_claim,
            "combank_number": settings.combank_whatsapp_number or "+94 77 123 4567",
        },
        "recent_imports": [
            {
                "id": imp.id,
                "rows_new": imp.rows_new,
                "rows_total": imp.rows_total,
                "created_at": imp.created_at,
            }
            for imp in latest_imports
        ],
    }


@router.get("/bank-sync/qr")
async def get_whatsapp_qr(
    request: Request,
    db: Session = Depends(get_db),
):
    """Retrieve WhatsApp QR code for pairing."""
    from ..security import current_user
    user = current_user(request, db)
    if not user or user.role not in [Role.finance_admin.value, Role.super_admin.value]:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Finance admin required")

    bot_url = settings.whatsapp_bot_url.rstrip("/") + "/qr"
    try:
        async with httpx.AsyncClient(timeout=5.0) as client:
            resp = await client.get(bot_url)
            return resp.json()
    except Exception as e:
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY,
            f"Could not reach WhatsApp bot: {str(e)}",
        )


class PairingCodeIn(BaseModel):
    phone_number: str = Field(min_length=8, max_length=20)


@router.post("/bank-sync/pairing-code")
async def request_whatsapp_pairing_code(
    body: PairingCodeIn,
    request: Request,
    db: Session = Depends(get_db),
):
    """Request an 8-digit WhatsApp linking pairing code using phone number."""
    from ..security import current_user
    user = current_user(request, db)
    if not user or user.role not in [Role.finance_admin.value, Role.super_admin.value]:
        raise HTTPException(status.HTTP_403_FORBIDDEN, "Finance admin required")

    bot_url = settings.whatsapp_bot_url.rstrip("/") + "/pairing-code"
    headers = {}
    if settings.whatsapp_bot_secret:
        headers["X-Bot-Secret"] = settings.whatsapp_bot_secret

    try:
        async with httpx.AsyncClient(timeout=15.0) as client:
            resp = await client.post(bot_url, json={"phoneNumber": body.phone_number}, headers=headers)
            if resp.status_code == 200:
                return resp.json()
            err_detail = resp.json().get("error", resp.text) if resp.headers.get("content-type", "").startswith("application/json") else resp.text
            raise HTTPException(resp.status_code, err_detail)
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(
            status.HTTP_502_BAD_GATEWAY,
            f"Could not reach WhatsApp bot service: {str(e)}",
        )
