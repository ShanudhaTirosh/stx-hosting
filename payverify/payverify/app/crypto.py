"""Encryption at rest. Without PV_DATA_KEY (dev only) data is stored as-is; production refuses to start without it."""
import base64
import hashlib
import hmac
import struct
import time

from cryptography.fernet import Fernet

from .config import settings

MAGIC = b"PVE1"


def _f() -> Fernet | None:
    return Fernet(settings.data_key.encode()) if settings.data_key else None


def encrypt_bytes(data: bytes) -> bytes:
    f = _f()
    return MAGIC + f.encrypt(data) if f else data


def decrypt_bytes(blob: bytes) -> bytes:
    if not blob.startswith(MAGIC):
        return blob            # legacy / dev plaintext
    f = _f()
    if not f:
        raise RuntimeError("Encrypted data found but PV_DATA_KEY is not set")
    return f.decrypt(blob[len(MAGIC):])


def encrypt_str(s: str) -> str:
    return encrypt_bytes(s.encode()).decode("latin1")


def decrypt_str(s: str) -> str:
    return decrypt_bytes(s.encode("latin1")).decode()


# ---- TOTP (RFC 6238, SHA-1, 30 s, 6 digits): compatible with Google Authenticator, Authy, 1Password ----
def totp(secret_b32: str, for_time: float | None = None, digits: int = 6) -> tuple[str, int]:
    step = int((for_time if for_time is not None else time.time()) // 30)
    key = base64.b32decode(secret_b32.upper() + "=" * (-len(secret_b32) % 8))
    h = hmac.new(key, struct.pack(">Q", step), hashlib.sha1).digest()
    o = h[-1] & 0x0F
    code = (struct.unpack(">I", h[o:o + 4])[0] & 0x7FFFFFFF) % (10 ** digits)
    return str(code).zfill(digits), step


def verify_totp(secret_b32: str, code: str, last_step: int = 0) -> int | None:
    """Accepts the current and adjacent windows. Returns the matched step (to block replay) or None."""
    code = code.strip()
    now = time.time()
    for drift in (-1, 0, 1):
        c, step = totp(secret_b32, now + drift * 30)
        if hmac.compare_digest(c, code) and step > last_step:
            return step
    return None


def new_secret() -> str:
    return base64.b32encode(__import__("os").urandom(20)).decode().rstrip("=")
