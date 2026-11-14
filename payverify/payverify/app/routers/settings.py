"""Merchant Account Settings & Security Management.
Handles:
1. Configuration of merchant bank details (bank, account number, branch, NIC, ComBank numbers).
2. Fernet encryption at rest for sensitive credentials (account number, NIC).
3. Masked responses by default with on-demand reveal upon password re-authentication.
4. Tamper-evident audit logging of settings updates and reveals.
5. Explicit usage indicators showing where each detail is applied in reconciliation.
"""
from datetime import datetime, timezone
from fastapi import APIRouter, Depends, HTTPException, status
from pydantic import BaseModel, Field
from sqlalchemy import select
from sqlalchemy.orm import Session

from .. import audit, crypto
from ..config import settings
from ..db import get_db
from ..models import MerchantAccountSettings, Role, User, utcnow
from ..security import require_roles, verify_password

router = APIRouter(prefix="/api/v1/admin/settings", tags=["settings"])

FINANCE = (Role.finance_admin, Role.super_admin)


def _mask_account(acc: str | None, tail: str | None) -> str:
    if tail:
        return f"****{tail}"
    if not acc:
        return "Not configured"
    digits = "".join(ch for ch in acc if ch.isdigit())
    return f"****{digits[-4:]}" if len(digits) >= 4 else "****"


def _mask_id(id_num: str | None) -> str:
    if not id_num:
        return "Not configured"
    cleaned = id_num.strip()
    if len(cleaned) <= 4:
        return "****"
    return f"{cleaned[:2]}****{cleaned[-3:]}"


def _get_or_init_settings(db: Session) -> MerchantAccountSettings:
    row = db.execute(select(MerchantAccountSettings).order_by(MerchantAccountSettings.id.desc())).scalar_one_or_none()
    if not row:
        acc_enc = crypto.encrypt_str(settings.pay_to_account_number) if settings.pay_to_account_number else None
        id_enc = crypto.encrypt_str(settings.combank_nic) if settings.combank_nic else None
        row = MerchantAccountSettings(
            full_name=settings.pay_to_account_name or "Merchant Admin",
            bank_name=settings.pay_to_bank or "Commercial Bank of Ceylon PLC",
            branch_name="Main Branch",
            account_number_enc=acc_enc,
            account_number_tail=settings.bank_account_tail or (settings.pay_to_account_number[-4:] if len(settings.pay_to_account_number) >= 4 else ""),
            id_number_enc=id_enc,
            id_number_mask=_mask_id(settings.combank_nic) if settings.combank_nic else "",
            combank_whatsapp_number=settings.combank_whatsapp_number or "+94 77 123 4567",
            monitored_senders="COMBANK,ComBank,Commercial Bank,2662265",
            updated_at=utcnow(),
        )
        db.add(row)
        db.commit()
        db.refresh(row)
    return row


class AccountSettingsOut(BaseModel):
    id: int
    full_name: str
    bank_name: str
    branch_name: str
    account_number_masked: str
    account_number_tail: str
    id_number_masked: str
    mobile_number: str | None
    combank_whatsapp_number: str | None
    monitored_senders: list[str]
    usage_indicators: dict[str, str]
    updated_at: datetime


@router.get("/account", response_model=AccountSettingsOut)
def get_account_settings(
    user: User = Depends(require_roles(*FINANCE)),
    db: Session = Depends(get_db),
):
    """Retrieve merchant account settings with sensitive credentials masked."""
    row = _get_or_init_settings(db)
    senders = [s.strip() for s in (row.monitored_senders or "").split(",") if s.strip()]
    if "2662265" not in senders:
        senders.append("2662265")

    usage = {
        "account_number": "Displayed to customers on payment page and matched against bank statement credit rows.",
        "id_number": "Sent to ComBank WhatsApp Banking bot for automated mini-statement authentication.",
        "combank_whatsapp_number": "Target destination phone number for ComBank WhatsApp Banking session initiation.",
        "monitored_senders": "Allowlisted SMS senders & phone shortcodes accepted by Android Relay and Windows Phone Link.",
    }

    return AccountSettingsOut(
        id=row.id,
        full_name=row.full_name or "",
        bank_name=row.bank_name or "Commercial Bank of Ceylon PLC",
        branch_name=row.branch_name or "",
        account_number_masked=_mask_account(None, row.account_number_tail),
        account_number_tail=row.account_number_tail or "",
        id_number_masked=row.id_number_mask or _mask_id(None),
        mobile_number=row.mobile_number,
        combank_whatsapp_number=row.combank_whatsapp_number,
        monitored_senders=senders,
        usage_indicators=usage,
        updated_at=row.updated_at,
    )


class RevealIn(BaseModel):
    password: str = Field(min_length=1)


class RevealOut(BaseModel):
    account_number: str
    id_number: str


@router.post("/account/reveal", response_model=RevealOut)
def reveal_sensitive_account_settings(
    body: RevealIn,
    user: User = Depends(require_roles(*FINANCE)),
    db: Session = Depends(get_db),
):
    """Re-authenticate with staff password to temporarily reveal sensitive account credentials.
    Access is logged to the tamper-evident audit ledger without storing the plaintext values.
    """
    if not verify_password(body.password, user.password_hash):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid password for re-authentication")

    row = _get_or_init_settings(db)

    plain_account = crypto.decrypt_str(row.account_number_enc) if row.account_number_enc else ""
    plain_id = crypto.decrypt_str(row.id_number_enc) if row.id_number_enc else ""

    # Immutable audit trail
    audit.log(
        db,
        action="settings.credentials_revealed",
        entity="merchant_settings",
        entity_id=row.id,
        actor_id=user.id,
        detail={"revealed_fields": ["account_number", "id_number"]},
    )
    db.commit()

    return RevealOut(
        account_number=plain_account,
        id_number=plain_id,
    )


class AccountSettingsUpdateIn(BaseModel):
    full_name: str | None = None
    bank_name: str | None = None
    branch_name: str | None = None
    account_number: str | None = None
    id_number: str | None = None
    mobile_number: str | None = None
    combank_whatsapp_number: str | None = None
    monitored_senders: list[str] | str | None = None


@router.put("/account")
def update_account_settings(
    body: AccountSettingsUpdateIn,
    user: User = Depends(require_roles(*FINANCE)),
    db: Session = Depends(get_db),
):
    """Update merchant account configuration with at-rest encryption and audit trail."""
    row = _get_or_init_settings(db)
    updated_fields: list[str] = []

    if body.full_name is not None:
        row.full_name = body.full_name.strip()
        updated_fields.append("full_name")

    if body.bank_name is not None:
        row.bank_name = body.bank_name.strip()
        updated_fields.append("bank_name")

    if body.branch_name is not None:
        row.branch_name = body.branch_name.strip()
        updated_fields.append("branch_name")

    if body.account_number is not None and body.account_number.strip():
        clean_acc = body.account_number.strip()
        row.account_number_enc = crypto.encrypt_str(clean_acc)
        digits = "".join(ch for ch in clean_acc if ch.isdigit())
        row.account_number_tail = digits[-4:] if len(digits) >= 4 else digits
        updated_fields.append("account_number")

    if body.id_number is not None and body.id_number.strip():
        clean_id = body.id_number.strip().upper()
        row.id_number_enc = crypto.encrypt_str(clean_id)
        row.id_number_mask = _mask_id(clean_id)
        updated_fields.append("id_number")

    if body.mobile_number is not None:
        row.mobile_number = body.mobile_number.strip()
        updated_fields.append("mobile_number")

    if body.combank_whatsapp_number is not None:
        row.combank_whatsapp_number = body.combank_whatsapp_number.strip()
        updated_fields.append("combank_whatsapp_number")

    if body.monitored_senders is not None:
        if isinstance(body.monitored_senders, list):
            s_list = [s.strip() for s in body.monitored_senders if s.strip()]
        else:
            s_list = [s.strip() for s in body.monitored_senders.split(",") if s.strip()]
        if "2662265" not in s_list:
            s_list.append("2662265")
        row.monitored_senders = ",".join(s_list)
        updated_fields.append("monitored_senders")

    row.updated_at = utcnow()
    row.updated_by = user.id

    audit.log(
        db,
        action="settings.account_updated",
        entity="merchant_settings",
        entity_id=row.id,
        actor_id=user.id,
        detail={"updated_fields": updated_fields},
    )
    db.commit()

    return {"status": "success", "updated_fields": updated_fields}

