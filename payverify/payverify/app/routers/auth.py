import hashlib
from datetime import timedelta

from fastapi import APIRouter, Depends, HTTPException, Request
from pydantic import BaseModel, Field
from sqlalchemy import func, select
from sqlalchemy.orm import Session

from .. import audit
from ..config import settings
from ..crypto import decrypt_str, encrypt_str, new_secret, verify_totp
from ..db import get_db
from ..models import LoginAttempt, Role, User, utcnow
from ..schemas import RegisterIn
from ..security import STAFF, current_user, hash_password, make_token, mfa_setup_user, require_roles, verify_password

router = APIRouter(prefix="/api/v1/auth", tags=["auth"])
_DUMMY = hash_password("not-a-real-password")   # equalises timing for unknown emails


class LoginIn(BaseModel):
    email: str = Field(..., max_length=255)
    password: str
    otp: str | None = None


def _ip(request: Request) -> str:
    # Behind a reverse proxy, run uvicorn with --proxy-headers and --forwarded-allow-ips so this is the real client IP.
    return request.client.host if request.client else "unknown"


def _throttled(db: Session, eh: str, ip: str) -> bool:
    since = utcnow() - timedelta(minutes=settings.login_window_minutes)
    by_email = db.scalar(select(func.count(LoginAttempt.id)).where(LoginAttempt.email_hash == eh, LoginAttempt.success.is_(False), LoginAttempt.ts >= since))
    by_ip = db.scalar(select(func.count(LoginAttempt.id)).where(LoginAttempt.ip == ip, LoginAttempt.success.is_(False), LoginAttempt.ts >= since))
    return by_email >= settings.login_max_failures or by_ip >= settings.login_max_failures * 6


def _record(db: Session, eh: str, ip: str, ok: bool):
    db.add(LoginAttempt(email_hash=eh, ip=ip, success=ok))
    db.commit()


@router.post("/register", status_code=201)
def register(body: RegisterIn, db: Session = Depends(get_db)):
    if db.execute(select(User.id).where(User.email == body.email.lower())).first():
        raise HTTPException(409, "Email already registered")
    u = User(email=body.email.lower(), name=body.name, password_hash=hash_password(body.password),
             role=Role.customer.value)  # self-registration can only ever create customers
    db.add(u)
    db.flush()
    audit.log(db, action="user.register", entity="user", entity_id=u.id)
    db.commit()
    return {"id": u.id}


@router.post("/login")
def login(body: LoginIn, request: Request, db: Session = Depends(get_db)):
    email = body.email.lower()
    eh, ip = hashlib.sha256(email.encode()).hexdigest(), _ip(request)
    if _throttled(db, eh, ip):
        raise HTTPException(429, "Too many failed attempts. Try again in a few minutes.")
    u = db.execute(select(User).where(User.email == email)).scalar_one_or_none()
    pw_ok = verify_password(body.password, u.password_hash if u else _DUMMY)
    if not u or not pw_ok:
        _record(db, eh, ip, False)
        raise HTTPException(401, "Invalid credentials")
    if u.role in STAFF:
        if u.mfa_enabled:
            if not body.otp:
                raise HTTPException(401, "mfa_required")
            step = verify_totp(decrypt_str(u.mfa_secret_enc), body.otp, u.mfa_last_step)
            if step is None:
                _record(db, eh, ip, False)
                raise HTTPException(401, "invalid_otp")
            u.mfa_last_step = step      # a code can be used only once
        elif settings.require_staff_mfa:
            _record(db, eh, ip, True)
            return {"access_token": make_token(u, "mfa_setup"), "token_type": "bearer", "role": u.role, "scope": "mfa_setup"}
    _record(db, eh, ip, True)
    audit.log(db, action="auth.login", entity="user", entity_id=u.id, actor_id=u.id)
    db.commit()
    return {"access_token": make_token(u), "token_type": "bearer", "role": u.role, "scope": "full"}


@router.post("/mfa/setup")
def mfa_setup(user: User = Depends(mfa_setup_user), db: Session = Depends(get_db)):
    if user.role not in STAFF:
        raise HTTPException(403, "MFA is for staff accounts")
    if user.mfa_enabled:
        raise HTTPException(409, "MFA is already enabled. Ask a super admin to reset it.")
    secret = new_secret()
    user.mfa_secret_enc = encrypt_str(secret)
    db.commit()
    return {"secret": secret, "otpauth_uri": f"otpauth://totp/PayVerify:{user.email}?secret={secret}&issuer=PayVerify"}


class OtpIn(BaseModel):
    otp: str


@router.post("/mfa/confirm")
def mfa_confirm(body: OtpIn, user: User = Depends(mfa_setup_user), db: Session = Depends(get_db)):
    if user.mfa_enabled or not user.mfa_secret_enc:
        raise HTTPException(409, "Start MFA setup first")
    step = verify_totp(decrypt_str(user.mfa_secret_enc), body.otp)
    if step is None:
        raise HTTPException(401, "invalid_otp")
    user.mfa_enabled, user.mfa_last_step = True, step
    audit.log(db, action="auth.mfa_enabled", entity="user", entity_id=user.id, actor_id=user.id)
    db.commit()
    return {"access_token": make_token(user), "token_type": "bearer", "role": user.role, "scope": "full"}


@router.post("/users/{user_id}/mfa-reset")
def mfa_reset(user_id: int, admin: User = Depends(require_roles(Role.super_admin)), db: Session = Depends(get_db)):
    u = db.get(User, user_id)
    if not u or u.id == admin.id:
        raise HTTPException(404, "Not found")
    u.mfa_enabled, u.mfa_secret_enc, u.mfa_last_step = False, None, 0
    audit.log(db, action="auth.mfa_reset", entity="user", entity_id=u.id, actor_id=admin.id)
    db.commit()
    return {"id": u.id, "mfa_enabled": False}


@router.get("/me")
def me(user: User = Depends(current_user)):
    return {"id": user.id, "email": user.email, "role": user.role}
