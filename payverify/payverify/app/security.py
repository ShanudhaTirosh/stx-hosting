from datetime import datetime, timedelta, timezone

import jwt
from argon2 import PasswordHasher
from argon2.exceptions import VerifyMismatchError
from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer
from sqlalchemy.orm import Session

from .config import settings
from .db import get_db
from .models import Role, User

_ph = PasswordHasher()
_bearer = HTTPBearer(auto_error=False)
STAFF = {Role.support.value, Role.reviewer.value, Role.finance_admin.value, Role.super_admin.value}


def hash_password(pw: str) -> str:
    return _ph.hash(pw)


def verify_password(pw: str, h: str) -> bool:
    try:
        return _ph.verify(h, pw)
    except VerifyMismatchError:
        return False


def make_token(user: User, scope: str = "full") -> str:
    ttl = settings.jwt_ttl_minutes if scope == "full" else 10   # setup tokens are short-lived
    exp = datetime.now(timezone.utc) + timedelta(minutes=ttl)
    return jwt.encode({"sub": str(user.id), "scope": scope, "exp": exp}, settings.jwt_secret, algorithm="HS256")


def _user_from(creds: HTTPAuthorizationCredentials | None, db: Session, allow_setup: bool) -> User:
    if not creds:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Not authenticated")
    try:
        data = jwt.decode(creds.credentials, settings.jwt_secret, algorithms=["HS256"])
    except jwt.PyJWTError:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Invalid or expired token")
    if data.get("scope") != "full" and not (allow_setup and data.get("scope") == "mfa_setup"):
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Token not valid here")
    user = db.get(User, int(data["sub"]))
    if not user:
        raise HTTPException(status.HTTP_401_UNAUTHORIZED, "Unknown user")
    return user  # role is always read from the DB, never trusted from the token


def current_user(creds: HTTPAuthorizationCredentials | None = Depends(_bearer), db: Session = Depends(get_db)) -> User:
    return _user_from(creds, db, allow_setup=False)


def mfa_setup_user(creds: HTTPAuthorizationCredentials | None = Depends(_bearer), db: Session = Depends(get_db)) -> User:
    return _user_from(creds, db, allow_setup=True)


def require_roles(*roles: Role):
    allowed = {r.value for r in roles}

    def dep(user: User = Depends(current_user)) -> User:
        if user.role not in allowed:
            raise HTTPException(status.HTTP_403_FORBIDDEN, "Insufficient role")
        return user
    return dep
