# PayVerify Core Backend (Python 3.11, FastAPI)

The **`payverify`** directory contains the core reconciliation engine, database models, multimodal AI vision extractors, ComBank digital receipt advice generator, and REST API routers.

---

## Architectural Principles

1. **Deterministic Bank Settlement**:
   A payment is marked **`verified` ONLY with bank-side evidence**: an allocated credit from an imported or synced bank statement (`basis="bank_match"`), or an explicit, justified manual override by authenticated finance staff (`basis="admin_override"`).
   Receipt uploads, AI extractions, and SMS notifications are strictly supporting evidence (E2) and can **never** verify a payment on their own.

2. **Encryption at Rest**:
   All customer proof files (JPEG, PNG, PDF) and generated ComBank electronic deposit advice PDFs are encrypted using symmetric **Fernet** encryption before being written to `/srv/storage`.
   Sensitive merchant credentials (`account_number_enc`, `id_number_enc` in `MerchantAccountSettings`) are also encrypted at rest using Fernet before persisting to PostgreSQL. Decryption occurs strictly on-the-fly when authorized staff re-authenticate with their password. Files and credentials are decrypted strictly on-the-fly.

3. **Tamper-Evident Cryptographic Ledger**:
   Every state transition, file access, and staff action appends an immutable entry to `AuditLog`. Each record stores `hash_prev` (SHA-256 of the prior log entry) and `hash_self` (SHA-256 of its own fields), creating an unbroken cryptographic hash chain.

4. **Zero-Leak In-Memory OTP Relay**:
   Commercial Bank SMS OTPs received from Android phones are stored in-memory with a 60-second TTL. They are never written to PostgreSQL, never saved to disk, and redacted from application logs.

---

## Core Capabilities & Modules

### 1. Reconciliation Matcher (`app/matcher.py`)
- Implements deterministic matching between unverified claims (`PaymentClaim`) and bank ledger credits (`BankTransaction`).
- Matches on exact amount (`amount_minor`), booking window (typically 7 days), and one of:
  - Matching ComBank CRM transaction reference / STAN.
  - Matching 8-character payment code in remarks (e.g. `8F3A2B1C`).
  - Matching unique-cents amount variation.
- Handles race conditions using database row-level locking (`with_for_update()`).

### 2. Multimodal AI Proof Extractors (`app/extraction/`)
- **`GeminiExtractor` (`gemini.py`)**:
  - Connects to Google AI Studio using a free Gemini API key (`PV_GEMINI_API_KEY`).
  - Utilizes `gemini-1.5-flash` for high-speed multimodal extraction of amount, date, bank reference, recipient account tail, and depositor name.
- **`LocalOllamaExtractor` (`ollama.py`)**:
  - Offline CPU-friendly vision extractor using `llama3.2-vision` running on local Ollama (2–4 GB RAM VPS, zero GPU required).
- **`AnthropicExtractor` (`anthropic.py`)**:
  - Production Claude 3.5 Sonnet multimodal extractor for high-volume enterprise pipelines.

### 3. ComBank Digital Receipt & Advice PDF Generator (`app/combank_receipt.py`)
- Automatically intercepts digital receipt URLs (`https://vas.combank.net/rec/<token>`) from cash deposit machine (CRM) SMS alerts.
- Queries ComBank Digital Receipt API to retrieve official metadata:
  - System Trace Audit Number (STAN).
  - Terminal ID and Branch (`BATAPOLA-CRM1 BR`).
  - Exact currency note breakdown (e.g. 5 x Rs. 1,000, 2 x Rs. 500).
- Generates an official, encrypted **Electronic Deposit Advice PDF** stored securely on disk.

### 4. Merchant Account Settings & Credentials Router (`app/routers/settings.py`)
- Provides secure CRUD management for business bank credentials (`MerchantAccountSettings`):
  - `GET /api/v1/admin/settings/account`: Returns masked credentials (`800****567`, `1995****5678`), bank details, and monitored sender shortcodes (including `2662265`).
  - `POST /api/v1/admin/settings/account/reveal`: Validates staff password re-authentication, appends a cryptographic entry to `AuditLog`, and temporarily returns unmasked credentials.
  - `PUT /api/v1/admin/settings/account`: Encrypts sensitive fields using Fernet, updates masks, configures monitored senders, and records an audit log.

### 5. Extended Supabase Realtime Telemetry (`app/supabase_sync.py`)
- Non-blocking asynchronous sync of:
  - Enrolled device heartbeats and active states (`sync_device_to_supabase`).
  - Bank credit transactions from ComBank WhatsApp bot (`sync_bank_transaction_to_supabase`).
  - Platform health metrics (`sync_platform_health`) across WhatsApp bot, Android relay phones, and bank sync intervals.

### 6. Cross-Platform External API (`app/routers/external.py`)
- Exposes production endpoints under `/api/v1/external/*`:
  - `GET /health`: Deep diagnostics (DB latency, storage writability, WhatsApp bot status, Android relays count, AI readiness).
  - `GET /system-status`: Operational telemetry and queues.
  - `POST /orders`: Pre-create checkout order with deposit instructions or single-request order + base64 proof upload.
  - `POST /orders/upload`: Multipart order creation with direct binary file upload.
  - `POST /proofs/upload-and-review`: Rich proof upload (JSON base64) with customer metadata and instant AI/ledger review verdict.
  - `POST /proofs/upload-and-review-form`: Multipart file upload version of the proof review API.
  - `GET /orders/{invoice_or_id}`: Query live payment status, payment code, and allocated claims.
  - `GET /claims/{claim_id}`: Detailed claim review state (forensics signals, AI extracted fields, bank match details, discrepancies).
  - `POST /claims/{claim_id}/resubmit`: Replacement proof slip submission for flagged claims.
  - `POST /verify`: Traditional payment verification with reference and automatic bank matching.
  - `POST /check-or-verify`: Check payment status or verify with reference/amount.
  - `POST /claims/{claim_id}/verify`: Manual override / admin force verification.
  - `GET /customers/{customer_id_or_email}/payments`: Customer payment history and claims.
  - `POST /trigger-sync`: Trigger immediate ComBank statement fetch via WhatsApp bot.
  - `GET /credentials`: Staff credentials query for authorized integration inspection.

---

## Local Setup & Testing

### 1. Virtual Environment & Dependencies
```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
pip install -r requirements.txt
```

### 2. Environment Variables
Create a local `.env` file or export variables:
```bash
export PV_DB_PASSWORD=your_postgres_password
export PV_JWT_SECRET=your_32_byte_secret
export PV_DATA_KEY=your_fernet_key
export PV_EXTERNAL_API_KEY=your_api_key
export PV_EXTRACTION_ENABLED=true
export PV_AI_PROVIDER=gemini
export PV_GEMINI_API_KEY=your_gemini_key
```

### 3. Running Test Suite
```powershell
python -m pytest tests/ -v
```
**Expected Result**:
```text
============= 102 passed, 2 skipped in 78s =============
```

---

## Data Retention & Privacy (PDPA Compliance)
PayVerify complies with Sri Lanka's Personal Data Protection Act (PDPA):
- Retention script: `python -m app.retention --apply`
- Prunes proof files, raw OCR text, AI-extracted text, and customer phone/names after the retention period (default: 730 days for verified, 90 days for rejected).
- Retains transaction hashes, payment codes, amounts, and the immutable audit log for financial compliance.
