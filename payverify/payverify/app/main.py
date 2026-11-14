import logging

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from .config import settings

from .db import Base, engine
from .routers import admin, auth, bank_sync, claims, devices, external

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s %(message)s")


def validate_settings() -> None:
    """Refuse to start in production with unsafe configuration."""
    if settings.env != "production":
        return
    problems = []
    if settings.jwt_secret == "change-me-in-production" or len(settings.jwt_secret) < 32:
        problems.append("PV_JWT_SECRET must be a random string of 32+ characters")
    if not settings.data_key:
        problems.append("PV_DATA_KEY (Fernet key) is required to encrypt proofs and MFA secrets")
    if not settings.bank_account_tail:
        problems.append("PV_BANK_ACCOUNT_TAIL is required")
    if not settings.require_staff_mfa:
        problems.append("PV_REQUIRE_STAFF_MFA must stay true")
    if settings.database_url.startswith("sqlite"):
        problems.append("Use PostgreSQL in production")
    if any(o.startswith("http://") for o in settings.cors_origins.split(",")):
        problems.append("PV_CORS_ORIGINS must be https:// origins")
    if settings.external_api_key and len(settings.external_api_key) < 24:
        problems.append("PV_EXTERNAL_API_KEY must be at least 24 characters in production")
    if settings.external_webhook_url and not settings.external_webhook_secret:
        problems.append("PV_EXTERNAL_WEBHOOK_SECRET is required when PV_EXTERNAL_WEBHOOK_URL is configured")
    if problems:
        raise RuntimeError("Unsafe production configuration: " + "; ".join(problems))


validate_settings()
if settings.env != "production":
    Base.metadata.create_all(engine)  # dev convenience; production uses `alembic upgrade head`

app = FastAPI(title="PayVerify", version="0.1.0")
app.add_middleware(CORSMiddleware, allow_origins=[o.strip() for o in settings.cors_origins.split(",") if o.strip()],
                   allow_methods=["GET", "POST"], allow_headers=["Authorization", "Content-Type"])
app.include_router(auth.router)
app.include_router(claims.router)
app.include_router(admin.router)
app.include_router(devices.router)
app.include_router(bank_sync.router)
app.include_router(external.router)


@app.middleware("http")
async def security_headers(request, call_next):
    resp = await call_next(request)
    resp.headers.setdefault("X-Content-Type-Options", "nosniff")
    resp.headers.setdefault("X-Frame-Options", "DENY")
    resp.headers.setdefault("Referrer-Policy", "no-referrer")
    resp.headers.setdefault("Cache-Control", "no-store")
    if settings.env == "production":
        resp.headers.setdefault("Strict-Transport-Security", "max-age=63072000; includeSubDomains")
    return resp


@app.get("/healthz")
def healthz():
    return {"ok": True}
