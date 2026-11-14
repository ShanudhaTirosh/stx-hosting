# AGENTS.md: Developer & AI Agent Operating Manual

This document defines the architectural invariants, security rules, and development guidelines that **all developers and AI coding agents MUST strictly follow** when modifying or extending the PayVerify codebase.

---

## 1. Core Architectural Invariants

### Invariant 1: Zero-Leak OTP Guarantee
- ComBank SMS OTP codes are **never persisted to disk, never stored in SQLite on Android, never written to PostgreSQL tables, and never printed to persistent logs**.
- The OTP relay endpoint (`POST /api/v1/devices/otp-relay`) keeps OTP codes strictly in an in-memory dictionary with a maximum TTL of 60 seconds.
- Once injected into the WhatsApp bot session or expired, the memory buffer is wiped immediately.

### Invariant 2: Deterministic Financial Settlement
- **No AI model or score may ever directly verify a payment.**
- The only code path allowed to mark a `PaymentClaim` as `verified` is [`payverify/app/state.py::transition`](payverify/app/state.py).
- A payment claim can only become `verified` through:
  1. `basis="bank_match"`: An allocated, unique, matching bank ledger credit (`BankTransaction`).
  2. `basis="admin_override"`: An explicit manual action triggered by an authenticated finance staff member or authorized API key with written justification.
- AI extractions (`AiAnalysis`) and SMS corroborations (`SmsEvent`) are strictly **supporting advisory evidence (E2)**.

### Invariant 3: Encryption at Rest
- All uploaded proof files (receipt images, PDFs) and generated electronic deposit advice PDFs MUST be encrypted using Fernet symmetric encryption before being written to `/srv/storage` or `settings.storage_dir`.
- Decryption occurs strictly on-the-fly when authorized staff request file inspection.

### Invariant 4: Tamper-Evident Cryptographic Audit Ledger
- Every state transition, claim creation, file view, and manual override MUST append an entry to `AuditLog`.
- Every entry stores `hash_prev` (the SHA-256 hash of the previous log row) and `hash_self` (the SHA-256 hash of its own serialized fields), creating a verifiable tamper-evident blockchain-style hash chain.

### Invariant 5: Non-Blocking External Sync
- Synchronization to Supabase (`payverify/app/supabase_sync.py`) or external webhooks MUST be executed asynchronously or within a try-except block.
- Any outage or rate limit from Supabase or external networks must **never** fail the primary database transaction or delay order verification.

---

## 2. Directory Layout & Module Ownership

| Path | Language | Purpose | Key Files |
|---|---|---|---|
| `payverify/` | Python 3.11 | Core API, reconciliation engine, DB models, AI extractors | `matcher.py`, `state.py`, `models.py`, `combank_receipt.py`, `routers/external.py` |
| `payverify-whatsapp-bot/` | TypeScript / Node.js | Multi-device Baileys WhatsApp bot & ComBank state machine | `combankSession.ts`, `parser.ts`, `server.ts` |
| `payverify-web/` | Next.js 16 (React / TS) | Web Admin dashboard and BFF authentication proxy | `app/admin/`, `app/bff/api/[...path]/route.ts` |
| `payverify-android/` | Kotlin / Android SDK | Zero-leak SMS interceptor, device key enrollment | `Prefilter.kt`, `Capture.kt`, `Api.kt` |
| `deploy/` | Docker Compose / Caddy | Production orchestration and container configuration | `docker-compose.prod.yml`, `.env.example` |

---

## 3. Running Verification & Tests

### Backend Test Suite
Whenever you modify backend code, models, or routers, you **must** run the test suite:
```powershell
& "C:\Users\tiros\AppData\Local\Programs\Python\Python311\python.exe" -m pytest payverify/tests/ -v
```
**Acceptance Criteria**: 84 passed, 2 skipped (Postgres concurrency skips on SQLite), 0 failed.

### WhatsApp Bot Parser Test Suite
```powershell
Set-Location payverify-whatsapp-bot
npx tsx --test src/parser.test.ts
```
**Acceptance Criteria**: All subtests pass with 0 failures.

### Frontend Production Build
```powershell
Set-Location payverify-web
npm run build
```
**Acceptance Criteria**: All 14 static and dynamic routes compile cleanly with zero TypeScript errors.

---

## 4. Coding Standards & Conventions

1. **Database Schema & Migrations**:
   - Do NOT edit existing Alembic version files.
   - When modifying `payverify/app/models.py`, generate a new migration with `alembic revision --autogenerate -m "description"`.
   - Ensure new columns are nullable or have sensible server defaults.

2. **Cross-Platform External API**:
   - Endpoints under `/api/v1/external/*` require authentication via header `X-API-Key` or `Authorization: Bearer <token>`.
   - Always return consistent JSON envelopes with `order_id`, `invoice_no`, `amount_minor`, `amount_formatted`, `status`, and `payment_code`.
   - Use `_get_system_actor_id(db)` for automated overrides so missing staff accounts never cause unhandled exceptions.

3. **Git Commits**:
   - Follow Conventional Commits format (`feat(...)`, `fix(...)`, `chore(...)`, `test(...)`).
   - Group related modifications into coherent, atomic commits rather than staging unrelated files together.

