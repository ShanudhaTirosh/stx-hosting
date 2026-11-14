import enum
from datetime import datetime, timezone

from sqlalchemy import (BigInteger, Boolean, CheckConstraint, DateTime, Double, ForeignKey, Index, Integer,
                        String, Text, UniqueConstraint, JSON)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from .db import Base


def utcnow() -> datetime:
    return datetime.now(timezone.utc)


class Role(str, enum.Enum):
    customer = "customer"
    support = "support"
    reviewer = "reviewer"
    finance_admin = "finance_admin"
    super_admin = "super_admin"


class ClaimStatus(str, enum.Enum):
    pending = "pending"
    processing = "processing"
    awaiting_bank_confirmation = "awaiting_bank_confirmation"
    verified = "verified"
    rejected = "rejected"
    suspicious = "suspicious"
    manual_review = "manual_review"


class User(Base):
    __tablename__ = "users"
    id: Mapped[int] = mapped_column(primary_key=True)
    email: Mapped[str] = mapped_column(String(255), unique=True)
    name: Mapped[str] = mapped_column(String(120), default="")
    phone: Mapped[str | None] = mapped_column(String(32), nullable=True)
    external_id: Mapped[str | None] = mapped_column(String(64), index=True, nullable=True)
    password_hash: Mapped[str] = mapped_column(String(255))
    role: Mapped[str] = mapped_column(String(32), default=Role.customer.value)
    mfa_secret_enc: Mapped[str | None] = mapped_column(Text)
    mfa_enabled: Mapped[bool] = mapped_column(default=False)
    mfa_last_step: Mapped[int] = mapped_column(BigInteger, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class Order(Base):
    __tablename__ = "orders"
    id: Mapped[int] = mapped_column(primary_key=True)
    customer_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    invoice_no: Mapped[str] = mapped_column(String(64), unique=True)
    amount_minor: Mapped[int] = mapped_column(BigInteger)           # invoice amount (LKR cents)
    expected_pay_amount_minor: Mapped[int] = mapped_column(BigInteger)  # may carry unique cents
    unique_amount: Mapped[bool] = mapped_column(default=False)
    payment_code: Mapped[str] = mapped_column(String(16), unique=True)
    status: Mapped[str] = mapped_column(String(16), default="unpaid")  # unpaid | paid
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class PaymentClaim(Base):
    __tablename__ = "payment_claims"
    __table_args__ = (
        # A claim can only be VERIFIED if it records how it was verified.
        CheckConstraint("status != 'verified' OR verification_basis IS NOT NULL", name="ck_verified_has_basis"),
        CheckConstraint("verification_basis IS NULL OR verification_basis IN ('bank_match','admin_override')",
                        name="ck_basis_values"),
    )
    id: Mapped[int] = mapped_column(primary_key=True)
    order_id: Mapped[int] = mapped_column(ForeignKey("orders.id"), index=True)
    customer_id: Mapped[int] = mapped_column(ForeignKey("users.id"), index=True)
    channel: Mapped[str] = mapped_column(String(32))
    claimed_reference: Mapped[str | None] = mapped_column(String(64), index=True)
    claimed_amount_minor: Mapped[int] = mapped_column(BigInteger)
    claimed_paid_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    claimed_sender_name: Mapped[str | None] = mapped_column(String(160))
    status: Mapped[str] = mapped_column(String(32), default=ClaimStatus.pending.value, index=True)
    status_reason_code: Mapped[str | None] = mapped_column(String(64))
    customer_message: Mapped[str | None] = mapped_column(String(255))
    verification_basis: Mapped[str | None] = mapped_column(String(16))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    verified_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    version: Mapped[int] = mapped_column(Integer, default=1)
    purged_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    files: Mapped[list["ProofFile"]] = relationship(back_populates="claim")


class ProofFile(Base):
    __tablename__ = "proof_files"
    id: Mapped[int] = mapped_column(primary_key=True)
    claim_id: Mapped[int] = mapped_column(ForeignKey("payment_claims.id"), index=True)
    storage_key: Mapped[str] = mapped_column(String(255))
    mime: Mapped[str] = mapped_column(String(32))
    size: Mapped[int] = mapped_column(Integer)
    sha256: Mapped[str] = mapped_column(String(64), index=True)
    dhash: Mapped[str | None] = mapped_column(String(16), index=True)
    purged_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    claim: Mapped[PaymentClaim] = relationship(back_populates="files")


class BankStatementImport(Base):
    __tablename__ = "bank_statement_imports"
    id: Mapped[int] = mapped_column(primary_key=True)
    source: Mapped[str] = mapped_column(String(32))
    file_sha256: Mapped[str] = mapped_column(String(64), unique=True)
    imported_by: Mapped[int | None] = mapped_column(ForeignKey("users.id"), nullable=True)
    rows_total: Mapped[int] = mapped_column(Integer, default=0)
    rows_new: Mapped[int] = mapped_column(Integer, default=0)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class BankTransaction(Base):
    __tablename__ = "bank_transactions"
    id: Mapped[int] = mapped_column(primary_key=True)
    import_id: Mapped[int] = mapped_column(ForeignKey("bank_statement_imports.id"))
    bank_txn_ref: Mapped[str | None] = mapped_column(String(64), index=True)
    value_dt: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True)
    amount_minor: Mapped[int] = mapped_column(BigInteger)
    direction: Mapped[str] = mapped_column(String(8))  # credit | debit
    counterparty_name: Mapped[str | None] = mapped_column(String(160))
    remarks: Mapped[str | None] = mapped_column(Text)
    dedupe_key: Mapped[str] = mapped_column(String(64), unique=True)
    raw: Mapped[dict] = mapped_column(JSON, default=dict)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class Allocation(Base):
    """One bank credit pays at most one claim, and one claim is funded by at most one credit."""
    __tablename__ = "allocations"
    __table_args__ = (UniqueConstraint("bank_transaction_id", name="uq_alloc_txn"),
                      UniqueConstraint("claim_id", name="uq_alloc_claim"))
    id: Mapped[int] = mapped_column(primary_key=True)
    bank_transaction_id: Mapped[int] = mapped_column(ForeignKey("bank_transactions.id"))
    claim_id: Mapped[int] = mapped_column(ForeignKey("payment_claims.id"))
    amount_minor: Mapped[int] = mapped_column(BigInteger)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class FraudSignal(Base):
    __tablename__ = "fraud_signals"
    id: Mapped[int] = mapped_column(primary_key=True)
    claim_id: Mapped[int] = mapped_column(ForeignKey("payment_claims.id"), index=True)
    rule_code: Mapped[str] = mapped_column(String(64))
    severity: Mapped[str] = mapped_column(String(16))  # info | warn | block
    detail: Mapped[dict] = mapped_column(JSON, default=dict)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class AuditLog(Base):
    __tablename__ = "audit_log"
    id: Mapped[int] = mapped_column(primary_key=True)
    ts: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    actor_type: Mapped[str] = mapped_column(String(16))   # user | system
    actor_id: Mapped[int | None] = mapped_column(Integer)
    action: Mapped[str] = mapped_column(String(64))
    entity: Mapped[str] = mapped_column(String(32))
    entity_id: Mapped[int | None] = mapped_column(Integer)
    detail: Mapped[dict] = mapped_column(JSON, default=dict)
    hash_prev: Mapped[str] = mapped_column(String(64))
    hash_self: Mapped[str] = mapped_column(String(64))


Index("ix_audit_entity", AuditLog.entity, AuditLog.entity_id)


class OcrExtraction(Base):
    __tablename__ = "ocr_extractions"
    id: Mapped[int] = mapped_column(primary_key=True)
    proof_file_id: Mapped[int] = mapped_column(ForeignKey("proof_files.id"), index=True)
    engine: Mapped[str] = mapped_column(String(32))
    raw_text: Mapped[str] = mapped_column(Text, default="")
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class AiAnalysis(Base):
    __tablename__ = "ai_analyses"
    id: Mapped[int] = mapped_column(primary_key=True)
    proof_file_id: Mapped[int] = mapped_column(ForeignKey("proof_files.id"), index=True)
    model: Mapped[str] = mapped_column(String(64))
    extracted: Mapped[dict] = mapped_column(JSON, default=dict)
    overall_confidence: Mapped[float] = mapped_column(Double, default=0.0)
    extracted_reference_norm: Mapped[str | None] = mapped_column(String(64), index=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class EnrolmentCode(Base):
    __tablename__ = "enrolment_codes"
    id: Mapped[int] = mapped_column(primary_key=True)
    code_hash: Mapped[str] = mapped_column(String(64), unique=True)
    created_by: Mapped[int] = mapped_column(ForeignKey("users.id"))
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True))
    used_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))


class Device(Base):
    __tablename__ = "devices"
    id: Mapped[int] = mapped_column(primary_key=True)
    name: Mapped[str] = mapped_column(String(80))
    public_key_der_b64: Mapped[str] = mapped_column(Text)   # ECDSA P-256 SubjectPublicKeyInfo
    enrolled_by: Mapped[int] = mapped_column(ForeignKey("users.id"))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    last_heartbeat_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    status: Mapped[dict] = mapped_column(JSON, default=dict)   # last heartbeat payload
    revoked_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))


class SmsEvent(Base):
    """Supporting evidence only (E2). Sender IDs can be spoofed, so this never verifies a payment."""
    __tablename__ = "sms_events"
    id: Mapped[int] = mapped_column(primary_key=True)
    device_id: Mapped[int] = mapped_column(ForeignKey("devices.id"), index=True)
    idempotency_key: Mapped[str] = mapped_column(String(64), unique=True)
    source: Mapped[str] = mapped_column(String(16))        # sms | notification
    sender: Mapped[str] = mapped_column(String(64))
    body: Mapped[str] = mapped_column(Text)
    received_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), index=True)
    direction: Mapped[str | None] = mapped_column(String(8))
    amount_minor: Mapped[int | None] = mapped_column(BigInteger, index=True)
    ref: Mapped[str | None] = mapped_column(String(64))
    branch: Mapped[str | None] = mapped_column(String(64))
    receipt_url: Mapped[str | None] = mapped_column(String(300))
    receipt_stan: Mapped[str | None] = mapped_column(String(64), index=True, nullable=True)
    receipt_pdf_key: Mapped[str | None] = mapped_column(String(255), nullable=True)
    receipt_data: Mapped[dict | None] = mapped_column(JSON, nullable=True)
    parsed_ok: Mapped[bool] = mapped_column(default=False)
    matched_bank_txn_id: Mapped[int | None] = mapped_column(ForeignKey("bank_transactions.id"))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)


class LoginAttempt(Base):
    __tablename__ = "login_attempts"
    id: Mapped[int] = mapped_column(primary_key=True)
    email_hash: Mapped[str] = mapped_column(String(64), index=True)
    ip: Mapped[str] = mapped_column(String(64), index=True)
    ts: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow, index=True)
    success: Mapped[bool] = mapped_column(default=False)


class BankBalanceSnapshot(Base):
    """Vector 3: Corroborating bank balance snapshots captured from ComBank WhatsApp Banking or APIs."""
    __tablename__ = "bank_balance_snapshots"
    id: Mapped[int] = mapped_column(primary_key=True)
    source: Mapped[str] = mapped_column(String(32), default="combank_whatsapp")
    account_mask: Mapped[str | None] = mapped_column(String(64), nullable=True)
    account_tail: Mapped[str | None] = mapped_column(String(16), index=True, nullable=True)
    current_balance_minor: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    available_balance_minor: Mapped[int | None] = mapped_column(BigInteger, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow, index=True)


class WebhookDeliveryLog(Base):
    """Audit log of outbound webhook notifications dispatched to external merchant websites."""
    __tablename__ = "webhook_delivery_logs"
    id: Mapped[int] = mapped_column(primary_key=True)
    event: Mapped[str] = mapped_column(String(64), default="payment.verified")
    claim_id: Mapped[int | None] = mapped_column(ForeignKey("payment_claims.id"), nullable=True, index=True)
    order_id: Mapped[int | None] = mapped_column(ForeignKey("orders.id"), nullable=True, index=True)
    url: Mapped[str] = mapped_column(String(500))
    status_code: Mapped[int | None] = mapped_column(Integer, nullable=True)
    response_body: Mapped[str | None] = mapped_column(Text, nullable=True)
    attempts: Mapped[int] = mapped_column(Integer, default=1)
    delivered: Mapped[bool] = mapped_column(Boolean, default=False, index=True)
    error_message: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow, index=True)


class MerchantAccountSettings(Base):
    """Encrypted merchant bank account and identity configuration.
    Sensitive credentials (account number, NIC) are stored Fernet-encrypted at rest.
    """
    __tablename__ = "merchant_account_settings"
    id: Mapped[int] = mapped_column(primary_key=True)
    full_name: Mapped[str] = mapped_column(String(160), default="")
    bank_name: Mapped[str] = mapped_column(String(120), default="Commercial Bank of Ceylon PLC")
    branch_name: Mapped[str] = mapped_column(String(100), default="")
    account_number_enc: Mapped[str | None] = mapped_column(Text, nullable=True)
    account_number_tail: Mapped[str | None] = mapped_column(String(16), nullable=True)
    id_number_enc: Mapped[str | None] = mapped_column(Text, nullable=True)
    id_number_mask: Mapped[str | None] = mapped_column(String(32), nullable=True)
    mobile_number: Mapped[str | None] = mapped_column(String(32), nullable=True)
    combank_whatsapp_number: Mapped[str | None] = mapped_column(String(32), nullable=True)
    monitored_senders: Mapped[str] = mapped_column(Text, default="COMBANK,ComBank,Commercial Bank,2662265")
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), default=utcnow)
    updated_by: Mapped[int | None] = mapped_column(ForeignKey("users.id"), nullable=True)

