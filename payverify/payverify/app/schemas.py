import re
from datetime import datetime

from pydantic import BaseModel, Field, field_validator


class RegisterIn(BaseModel):
    email: str = Field(..., max_length=255)
    name: str = ""
    password: str = Field(min_length=10)

    @field_validator("email")
    @classmethod
    def validate_email(cls, v: str) -> str:
        v = v.strip().lower()
        if not re.match(r"^[^@\s]+@[^@\s]+\.[^@\s]+$", v):
            raise ValueError("Invalid email format")
        return v


class OrderIn(BaseModel):
    customer_id: int
    invoice_no: str
    amount_minor: int = Field(gt=0)
    unique_amount: bool = False


class DecisionIn(BaseModel):
    decision: str = Field(pattern="^(approve|reject)$")
    reason: str = Field(min_length=5)
    bank_transaction_id: int | None = None
    override_justification: str | None = None
    rejection_code: str | None = None
    customer_message: str | None = None


class CustomerClaimOut(BaseModel):
    """Customer-safe view: no fraud signals, no internal reason codes, no bank details."""
    id: int
    order_id: int
    status: str
    message: str | None
    claimed_amount_minor: int
    claimed_reference: str | None
    created_at: datetime
    verified_at: datetime | None
    extracted: dict | None = None
