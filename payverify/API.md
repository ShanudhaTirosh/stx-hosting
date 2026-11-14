# PayVerify External API Reference & Developer Guide

The **PayVerify External API** allows external platforms—including Shopify, WooCommerce, Magento, custom React / Next.js / mobile applications, ERPs, and Point-of-Sale (POS) terminals—to programmatically create checkout orders, upload and analyze bank deposit slips, receive real-time verification webhooks, and query transaction statuses.

---

## Architecture & Integration Diagrams

### 1. Multi-Vector Verification Architecture

![Multi-Vector Verification Architecture](docs/diagrams/multi-vector-architecture.svg)

#### Architecture Flow (Universal Text Rendering)

```text
+-----------------------------------------------------------------------------------+
|                            EXTERNAL PLATFORMS                                     |
|           Shopify  /  WooCommerce  /  Custom Apps  /  POS Terminals               |
+-----------------------------------------------------------------------------------+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|                        PAYVERIFY EXTERNAL REST API                                |
|   POST /orders  │  POST /proofs/upload-and-review  │  GET /orders/{invoice_no}    |
+-----------------------------------------------------------------------------------+
             │                                              ▲
             ▼                                              │
+───────────────────────────+                 +───────────────────────────+
|   FERNET ENCRYPTED DISK   |                 | OUTBOUND WEBHOOK WORKER   |
|  Encrypted proof storage  |                 | HMAC-SHA256 Signed Events |
+───────────────────────────+                 +───────────────────────────+
             │                                              ▲
             ▼                                              │
+───────────────────────────────────────────────────────────────────────────────────+
|                     CORE DETERMINISTIC SETTLEMENT ENGINE                          |
|                                                                                   |
|  [Evidence 1: AI Vision]          [Forensics]           [Evidence 2: Bank Side]   |
|   Gemini 1.5 Flash / Ollama   SHA-256 & dHash Dupes     ComBank WhatsApp Bot      |
|   Amount & Reference OCR      EXIF Tamper Checks        Android SMS & STAN Advice |
|                                                         Bank Statement CSV / XLSX |
+───────────────────────────────────────────────────────────────────────────────────+
                                         │
                                         ▼
+-----------------------------------------------------------------------------------+
|                    IMMUTABLE CRYPTOGRAPHIC AUDIT LEDGER                           |
|       PostgreSQL / SQLite  --  SHA-256 Hash Chain (hash_prev / hash_self)         |
+-----------------------------------------------------------------------------------+
```

<details>
<summary>View Mermaid Source Specification</summary>

```mermaid
graph TD
    subgraph ExternalSystems["External Platforms"]
        Shopify["Shopify or WooCommerce"]
        MobileApp["Mobile or React App"]
        ERP["ERP or POS Terminal"]
    end

    subgraph ExternalAPI["PayVerify External API (/api/v1/external)"]
        OrdersEndpoint["/orders & /orders/upload"]
        ProofsEndpoint["/proofs/upload-and-review"]
        StatusEndpoint["/orders/{id} & /claims/{id}"]
        WebhookDispatcher["Outbound Webhook Dispatcher"]
    end

    subgraph CoreEngine["Deterministic Verification Engine"]
        FernetStore["Fernet Encrypted Storage (/srv/storage)"]
        AIExtractor["Multimodal AI Vision (Gemini / Ollama)"]
        ForensicsEngine["Forensics Engine (SHA-256 / dHash / EXIF)"]
        Reconciliation["Reconciliation Matcher (Deterministic Settlement)"]
    end

    subgraph SettlementSources["Bank Settlement Sources"]
        WhatsAppBot["ComBank WhatsApp Bot"]
        AndroidRelay["Android SMS Relay (Zero-Leak)"]
        StatementCSV["Bank Statement CSV / XLSX"]
    end

    ExternalSystems --> OrdersEndpoint
    ExternalSystems --> ProofsEndpoint
    ExternalSystems --> StatusEndpoint

    OrdersEndpoint --> FernetStore
    ProofsEndpoint --> FernetStore
    ProofsEndpoint --> AIExtractor
    ProofsEndpoint --> ForensicsEngine

    AIExtractor --> Reconciliation
    ForensicsEngine --> Reconciliation
    SettlementSources --> Reconciliation

    Reconciliation --> WebhookDispatcher
    WebhookDispatcher --> ExternalSystems
```

</details>

---

### 2. Single-Request Order & Proof Review Sequence

![Single-Request Order & Proof Review Sequence](docs/diagrams/order-proof-review.svg)

#### Review Workflow (Universal Text Rendering)

```text
1. Client POSTs /proofs/upload-and-review (invoice, amount, base64 receipt)
   │
2. Encrypt proof with symmetric Fernet key & save to /srv/storage
   │
3. Check duplicate SHA-256 & perceptual difference hash (dHash)
   ├─► Duplicate detected ──► Return HTTP 409 Conflict
   │
4. AI Vision extracts (amount, reference, account tail, date)
   │
5. Evaluate discrepancies against order parameters
   ├─► Discrepancy detected ──► Transition to MANUAL_REVIEW
   │
6. Search unsettled ComBank ledger credits (amount + reference/payment_code)
   ├─► Match found ──────► Transition to VERIFIED (basis: bank_match, is_paid: true)
   └─► No match yet ─────► Transition to AWAITING_BANK_CONFIRMATION
```

<details>
<summary>View Mermaid Source Specification</summary>

```mermaid
sequenceDiagram
    autonumber
    actor Client as Merchant Client
    participant API as External API
    participant AI as Multimodal AI Engine
    participant DB as Audit Ledger DB
    participant Bank as Bank Ledger Cache

    Client->>API: POST /proofs/upload-and-review
    API->>DB: Check duplicate SHA256 and perceptual dHash
    alt Duplicate Receipt Found
        API-->>Client: 409 Conflict (Duplicate Receipt)
    else Unique Receipt
        API->>AI: Extract amount, reference, and bank tail
        AI-->>API: Extracted metadata and confidence
        API->>DB: Evaluate discrepancies against order
        API->>Bank: Match against unsettled bank credits
        alt Deterministic Bank Match
            API->>DB: Transition claim to VERIFIED (bank_match)
            API-->>Client: 200 OK (status: auto_verified, is_paid: true)
        else Credit Not Settled
            API->>DB: Transition claim to AWAITING_CONFIRMATION
            API-->>Client: 200 OK (status: awaiting_bank_confirmation)
        else Discrepancy Found
            API->>DB: Transition claim to MANUAL_REVIEW
            API-->>Client: 200 OK (status: manual_review, discrepancies listed)
        end
    end
```

</details>

---

### 3. Outbound Webhook Lifecycle & HMAC-SHA256 Verification

![Outbound Webhook Lifecycle & HMAC-SHA256 Verification](docs/diagrams/webhook-lifecycle.svg)

<details>
<summary>View Mermaid Source Specification</summary>

```mermaid
sequenceDiagram
    autonumber
    participant Engine as Reconciliation Engine
    participant Dispatcher as Webhook Worker
    participant Merchant as Merchant Webhook Server

    Engine->>Dispatcher: Claim transitioned to VERIFIED
    Note over Dispatcher: Compute HMAC-SHA256 signature
    Dispatcher->>Merchant: POST webhook (Signature and Timestamp headers)
    Note over Merchant: Validate timestamp skew and verify HMAC
    alt Signature Valid
        Merchant->>Merchant: Mark Order as PAID and Dispatch
        Merchant-->>Dispatcher: 200 OK
    else Signature Invalid
        Merchant-->>Dispatcher: 401 Unauthorized
    end
```

</details>

---

## Authentication & Headers

All endpoints under `/api/v1/external/` require an API key configured on the server via `PV_EXTERNAL_API_KEY`.

Pass your API key using either:
```http
X-API-Key: YOUR_EXTERNAL_API_KEY
```
or
```http
Authorization: Bearer YOUR_EXTERNAL_API_KEY
```

Authorized staff sessions from the PayVerify Web Dashboard can also authenticate using their session bearer token.

---

## Base URLs

- **Production**: `https://pay.example.com/api/v1/external`
- **Local Development**: `http://localhost:8000/api/v1/external`

---

## Complete Endpoint Reference

### 1. Health Diagnostics
Inspect the real-time operational health, database latency, storage encryption, WhatsApp bot connectivity, Android SMS relays, and AI vision model readiness.

```http
GET /api/v1/external/health
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/health" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "status": "online",
  "service": "PayVerify External API",
  "version": "2.0.0",
  "database": {
    "status": "connected",
    "latency_ms": 1.45
  },
  "storage": {
    "status": "writable",
    "encryption": "fernet_enabled"
  },
  "whatsapp_bot": {
    "status": "online",
    "connected": true,
    "state": "IDLE",
    "last_sync_at": "2026-11-12T12:00:00Z"
  },
  "android_relay": {
    "enrolled_devices": 1,
    "active_devices": 1,
    "last_sms_received_at": "2026-11-12T12:15:30Z"
  },
  "ai_vision": {
    "provider": "gemini",
    "model": "gemini-1.5-flash",
    "ready": true
  },
  "reconciliation": {
    "unverified_claims": 2,
    "manual_review_claims": 1,
    "settled_today": 14
  }
}
```

---

### 2. Live System Status & Telemetry
Returns operational telemetry, worker queues, enrolled phone relays, and payment statistics.

```http
GET /api/v1/external/system-status
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/system-status" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "status": "healthy",
  "environment": "production",
  "server_time": "2026-11-12T12:30:00Z",
  "devices_online": 1,
  "devices_total": 1,
  "pending_claims_count": 2,
  "unmatched_credits_count": 0,
  "ai_extractor": "gemini",
  "bank_account_tail": "7213"
}
```

---

### 3. Pre-Create Order or Single-Request Upload (JSON)
Create a checkout order and immediately receive bank transfer deposit instructions (account number, bank name, payment reference code). Optionally, submit a base64-encoded receipt in the same request.

```http
POST /api/v1/external/orders
```

#### Request Body Parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Unique merchant order/invoice reference (e.g. `INV-9042`) |
| `amount_minor` | integer | Yes | Expected amount in minor units / cents (e.g. `250000` for Rs. 2,500.00) |
| `customer_email` | string | No | Customer email address |
| `customer_name` | string | No | Customer full name |
| `unique_amount` | boolean | No | Slightly vary cents (e.g. Rs. 2,500.17) for collision-free reconciliation |
| `proof_base64` | string | No | Base64-encoded receipt image (JPEG/PNG) or PDF |
| `proof_mime` | string | No | MIME type (`image/jpeg`, `image/png`, `application/pdf`) |
| `proof_filename` | string | No | Original filename of the proof file |

#### Request Example (Order Pre-Creation)
```bash
curl -X POST "https://pay.example.com/api/v1/external/orders" \
  -H "X-API-Key: your_external_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_no": "ORD-10492",
    "amount_minor": 450000,
    "customer_email": "kasun@example.com",
    "customer_name": "Kasun Perera"
  }'
```

#### Response Example (`200 OK`)
```json
{
  "order_id": 48,
  "invoice_no": "ORD-10492",
  "amount_minor": 450000,
  "amount_formatted": "Rs. 4,500.00",
  "payment_code": "7A8B9C2D",
  "pay_to_bank": "Commercial Bank of Ceylon PLC",
  "pay_to_account_name": "Acme Holdings PVT LTD",
  "pay_to_account_number": "8001234567",
  "status": "pending",
  "instructions": "Please transfer Rs. 4,500.00 to Commercial Bank Account 8001234567 (Acme Holdings PVT LTD) and include reference code '7A8B9C2D' in your transaction remarks."
}
```

---

### 4. Create Order with Binary File Upload (Multipart Form)
Create an order and upload a binary receipt proof file in a single multipart request.

```http
POST /api/v1/external/orders/upload
```

#### Form Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Unique invoice or cart reference |
| `amount_minor` | integer | Yes | Expected amount in cents (e.g. `450000`) |
| `customer_email` | string | No | Customer email |
| `customer_name` | string | No | Customer name |
| `unique_amount` | boolean | No | Whether to vary amount by a few cents (default: `false`) |
| `file` | binary file | Yes | Receipt image (JPEG/PNG) or PDF document |

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/orders/upload" \
  -H "X-API-Key: your_external_api_key" \
  -F "invoice_no=ORD-10493" \
  -F "amount_minor=450000" \
  -F "customer_name=Nimal Silva" \
  -F "file=@/path/to/combank_slip.png;type=image/png"
```

#### Response Example (`200 OK`)
```json
{
  "order_id": 49,
  "invoice_no": "ORD-10493",
  "amount_minor": 450000,
  "amount_formatted": "Rs. 4,500.00",
  "payment_code": "9F1E2D3C",
  "status": "processing",
  "claim_id": 62,
  "claim_status": "awaiting_bank_confirmation",
  "file_id": 81,
  "file_name": "combank_slip.png",
  "instructions": "Proof slip uploaded and encrypted successfully. Awaiting bank ledger match."
}
```

---

### 5. Dedicated Proof Upload & Instant Review API (JSON Base64)
Submit customer payment details and proof slip to trigger instant AI extraction, visual forensics (duplicate SHA-256 and perceptual dHash checks), discrepancy evaluation, and deterministic bank matching in a single call.

```http
POST /api/v1/external/proofs/upload-and-review
```

#### Request Body Parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Merchant order invoice reference |
| `amount_minor` | integer | Yes | Expected payment amount in cents |
| `proof_base64` | string | Yes | Base64-encoded receipt image or PDF |
| `proof_mime` | string | No | `image/jpeg`, `image/png`, or `application/pdf` (default: `image/jpeg`) |
| `proof_filename` | string | No | Filename of the uploaded slip |
| `customer_email` | string | No | Customer email |
| `customer_name` | string | No | Customer name |
| `customer_phone` | string | No | Customer mobile phone |
| `payment_reference` | string | No | Bank transaction reference / STAN |
| `channel` | string | No | `bank_transfer`, `atm_cash_deposit`, `online_banking` |

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/proofs/upload-and-review" \
  -H "X-API-Key: your_external_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_no": "ORD-99120",
    "amount_minor": 350000,
    "proof_base64": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",
    "proof_mime": "image/png",
    "proof_filename": "slip.png",
    "customer_name": "Kasun Perera"
  }'
```

#### Response Example (`200 OK` - Auto Verified via Bank Match)
```json
{
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "claim_id": 63,
  "is_paid": true,
  "status": "auto_verified",
  "claim_status": "verified",
  "verification_basis": "bank_match",
  "amount_minor": 350000,
  "amount_formatted": "Rs. 3,500.00",
  "message": "Payment verified automatically via matching bank credit.",
  "ai_analysis": {
    "extracted_amount_minor": 350000,
    "extracted_reference": "032699400003",
    "extracted_account_tail": "7213",
    "confidence": 0.98
  },
  "discrepancies": []
}
```

#### Response Example (`200 OK` - Discrepancy Flagged for Manual Review)
```json
{
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "claim_id": 63,
  "is_paid": false,
  "status": "manual_review",
  "claim_status": "manual_review",
  "verification_basis": null,
  "amount_minor": 350000,
  "amount_formatted": "Rs. 3,500.00",
  "message": "Payment flagged for manual review due to discrepancy.",
  "ai_analysis": {
    "extracted_amount_minor": 250000,
    "extracted_reference": "032699400003",
    "extracted_account_tail": "7213",
    "confidence": 0.95
  },
  "discrepancies": [
    "Amount mismatch: Order requires Rs. 3,500.00, but receipt shows Rs. 2,500.00"
  ]
}
```

---

### 6. Dedicated Proof Upload & Instant Review API (Multipart Form)
Submit binary file upload along with order parameters for immediate AI analysis and bank matching.

```http
POST /api/v1/external/proofs/upload-and-review-form
```

#### Form Parameters
| Field | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Unique invoice number |
| `amount_minor` | integer | Yes | Expected amount in cents |
| `file` | binary file | Yes | Receipt slip (JPEG/PNG/PDF) |
| `customer_email` | string | No | Customer email |
| `customer_name` | string | No | Customer name |
| `customer_phone` | string | No | Customer phone |
| `payment_reference` | string | No | Bank transaction reference / STAN |
| `channel` | string | No | `bank_transfer`, `atm_cash_deposit`, `online_banking` |

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/proofs/upload-and-review-form" \
  -H "X-API-Key: your_external_api_key" \
  -F "invoice_no=ORD-99121" \
  -F "amount_minor=350000" \
  -F "file=@/path/to/receipt.jpg;type=image/jpeg"
```

---

### 7. Query Order Payment Status
Poll live order status, expected amount, payment instructions, and allocated settlement claims.

```http
GET /api/v1/external/orders/{invoice_or_id}
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/orders/ORD-99120" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "order_status": "paid",
  "is_paid": true,
  "expected_amount_minor": 350000,
  "payment_code": "7A8B9C2D",
  "created_at": "2026-11-12T12:00:00Z",
  "claim": {
    "id": 63,
    "status": "verified",
    "verification_basis": "bank_match",
    "verified_at": "2026-11-12T12:02:15Z",
    "reference": "032699400003",
    "amount_minor": 350000
  }
}
```

---

### 8. Inspect Detailed Claim Review State
Fetch forensic signals, AI extraction results, discrepancy notes, and bank credit allocation for a specific claim.

```http
GET /api/v1/external/claims/{claim_id}
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/claims/63" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "claim_id": 63,
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "status": "verified",
  "verification_basis": "bank_match",
  "claimed_amount_minor": 350000,
  "claimed_reference": "032699400003",
  "channel": "bank_transfer",
  "created_at": "2026-11-12T12:01:00Z",
  "verified_at": "2026-11-12T12:02:15Z",
  "ai_analysis": {
    "provider": "gemini",
    "confidence": 0.98,
    "extracted_amount_minor": 350000,
    "extracted_reference": "032699400003",
    "extracted_account_tail": "7213",
    "raw_text": "COMMERCIAL BANK TRANSFER SUCCESSFUL REF: 032699400003 AMOUNT: LKR 3,500.00"
  },
  "discrepancies": [],
  "fraud_signals": [],
  "bank_transaction": {
    "id": 112,
    "bank_reference": "032699400003",
    "amount_minor": 350000,
    "booking_date": "2026-11-12",
    "description": "CEFTS CR KASUN PERERA 032699400003"
  }
}
```

---

### 9. Resubmit Replacement Proof Slip
When a claim is flagged as `manual_review` or `rejected` due to an unclear image or incorrect amount, submit a replacement slip to re-trigger AI analysis and reconciliation.

```http
POST /api/v1/external/claims/{claim_id}/resubmit
```

#### Request Body Parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `proof_base64` | string | Yes | Base64-encoded replacement receipt image or PDF |
| `proof_mime` | string | No | MIME type (`image/jpeg`, `image/png`, `application/pdf`) |
| `proof_filename` | string | No | Replacement file name |
| `payment_reference` | string | No | Corrected bank transaction reference |
| `amount_minor` | integer | No | Corrected payment amount in cents |

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/claims/63/resubmit" \
  -H "X-API-Key: your_external_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "proof_base64": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==",
    "proof_mime": "image/png",
    "payment_reference": "032699400004"
  }'
```

#### Response Example (`200 OK`)
```json
{
  "claim_id": 63,
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "status": "awaiting_bank_confirmation",
  "verification_basis": null,
  "message": "Replacement slip uploaded and processed. Claim status updated to awaiting_bank_confirmation."
}
```

---

### 10. Direct Verification / Payment Submission
Standard endpoint for submitting customer payments with optional automatic bank matching.

```http
POST /api/v1/external/verify
```

#### Request Body Parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Order invoice reference |
| `amount_minor` | integer | Yes | Expected amount in cents (e.g. `300000`) |
| `reference` | string | No | Bank transaction reference or STAN |
| `customer_email` | string | No | Customer email |
| `customer_name` | string | No | Customer name |
| `proof_base64` | string | No | Base64-encoded receipt image or PDF |
| `proof_mime` | string | No | MIME type (default: `image/jpeg`) |
| `channel` | string | No | `bank_transfer`, `atm_cash_deposit`, `online_banking` |
| `auto_match` | boolean | No | Match immediately against unsettled bank credits (default: `true`) |
| `manual_override` | boolean | No | Force manual verification (default: `false`) |

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/verify" \
  -H "X-API-Key: your_external_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_no": "ORD-99120",
    "amount_minor": 300000,
    "reference": "032699400003",
    "auto_match": true
  }'
```

#### Response Example (`200 OK`)
```json
{
  "verified": true,
  "status": "verified",
  "order_id": 50,
  "invoice_no": "ORD-99120",
  "claim_id": 63,
  "amount_minor": 300000,
  "amount_formatted": "Rs. 3,000.00",
  "payment_code": "7A8B9C2D",
  "verification_basis": "bank_match",
  "status_reason": "bank_match"
}
```

---

### 11. Check or Verify Payment
Query payment status by reference and amount, or initiate verification.

```http
POST /api/v1/external/check-or-verify
```

#### Request Body Parameters
| Parameter | Type | Required | Description |
|---|---|---|---|
| `invoice_no` | string | Yes | Invoice number |
| `amount_minor` | integer | Yes | Amount in cents |
| `reference` | string | No | Reference code to match |
| `auto_match` | boolean | No | Perform matching (default: `true`) |

---

### 12. Manual Override (Admin Force Verification)
Programmatically force-verifies a claim with written justification. Appends an immutable cryptographic record to the audit chain.

```http
POST /api/v1/external/claims/{claim_id}/verify
```

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/claims/63/verify" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "claim_id": 63,
  "status": "verified",
  "basis": "admin_override"
}
```

---

### 13. Customer Payment History
Retrieve historical orders and claims for a specific customer.

```http
GET /api/v1/external/customers/{customer_id_or_email}/payments
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/customers/kasun@example.com/payments" \
  -H "X-API-Key: your_external_api_key"
```

---

### 14. Trigger On-Demand Bank Statement Sync
Instructs the ComBank WhatsApp Banking bot to initiate a statement sync immediately.

```http
POST /api/v1/external/trigger-sync
```

#### Request Example
```bash
curl -X POST "https://pay.example.com/api/v1/external/trigger-sync" \
  -H "X-API-Key: your_external_api_key"
```

#### Response Example (`200 OK`)
```json
{
  "status": "sync_triggered",
  "service": "payverify-whatsapp-bot"
}
```

---

### 15. Staff Credentials Inspection
Authorized staff members can inspect server environment credentials and public API configurations.

```http
GET /api/v1/external/credentials
```

#### Request Example
```bash
curl -X GET "https://pay.example.com/api/v1/external/credentials" \
  -H "X-API-Key: your_external_api_key"
```

---

## Outbound Webhooks Specification

When a payment claim transitions to `verified`, PayVerify dispatches an HTTP POST event to your configured webhook URL (`PV_WEBHOOK_URL`).

### Webhook Headers
| Header | Description |
|---|---|
| `Content-Type` | `application/json` |
| `X-PayVerify-Event` | Event type: `payment.verified` |
| `X-PayVerify-Timestamp` | Unix epoch timestamp in seconds (e.g. `1794508800`) |
| `X-PayVerify-Signature` | Hex-encoded HMAC-SHA256 signature of `${timestamp}.${raw_payload}` |

### Webhook Payload Example (`payment.verified`)
```json
{
  "event": "payment.verified",
  "timestamp": 1794508800,
  "data": {
    "order_id": 50,
    "invoice_no": "ORD-99120",
    "amount_minor": 350000,
    "amount_formatted": "Rs. 3,500.00",
    "payment_code": "7A8B9C2D",
    "claim_id": 63,
    "verification_basis": "bank_match",
    "verified_at": "2026-11-12T12:02:15Z",
    "bank_reference": "032699400003"
  }
}
```

### Signature Verification Examples

#### Node.js / TypeScript
```typescript
import crypto from "crypto";

function verifyWebhook(
  rawBody: string,
  signatureHeader: string,
  timestampHeader: string,
  secret: string
): boolean {
  // 1. Prevent replay attacks (5-minute tolerance)
  const currentTimestamp = Math.floor(Date.now() / 1000);
  const eventTimestamp = parseInt(timestampHeader, 10);
  if (Math.abs(currentTimestamp - eventTimestamp) > 300) {
    return false;
  }

  // 2. Compute expected HMAC-SHA256 signature
  const signedPayload = `${timestampHeader}.${rawBody}`;
  const expectedSignature = crypto
    .createHmac("sha256", secret)
    .update(signedPayload)
    .digest("hex");

  // 3. Constant-time comparison
  return crypto.timingSafeEqual(
    Buffer.from(signatureHeader, "hex"),
    Buffer.from(expectedSignature, "hex")
  );
}
```

#### Python
```python
import hmac
import hashlib
import time

def verify_webhook(raw_body: bytes, signature_header: str, timestamp_header: str, secret: str) -> bool:
    # 1. Prevent replay attacks (5-minute tolerance)
    current_time = int(time.time())
    event_time = int(timestamp_header)
    if abs(current_time - event_time) > 300:
        return False

    # 2. Compute expected HMAC-SHA256 signature
    signed_payload = f"{timestamp_header}.".encode("utf-8") + raw_body
    expected_signature = hmac.new(
        secret.encode("utf-8"),
        signed_payload,
        hashlib.sha256
    ).hexdigest()

    # 3. Constant-time comparison
    return hmac.compare_digest(signature_header, expected_signature)
```

#### PHP
```php
<?php
function verify_webhook($raw_body, $signature_header, $timestamp_header, $secret) {
    // 1. Prevent replay attacks (5-minute tolerance)
    $current_time = time();
    $event_time = intval($timestamp_header);
    if (abs($current_time - $event_time) > 300) {
        return false;
    }

    // 2. Compute expected signature
    $signed_payload = $timestamp_header . '.' . $raw_body;
    $expected_signature = hash_hmac('sha256', $signed_payload, $secret);

    // 3. Constant-time comparison
    return hash_equals($expected_signature, $signature_header);
}
?>
```

---

## Production Integration Code Examples

### 1. JavaScript / TypeScript (Node.js Fetch)

```typescript
const API_URL = "https://pay.example.com/api/v1/external";
const API_KEY = "your_external_api_key";

// Step 1: Pre-create checkout order to get payment code & instructions
async function createCheckoutOrder(invoiceNo: string, amountMinor: number) {
  const response = await fetch(`${API_URL}/orders`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-API-Key": API_KEY,
    },
    body: JSON.stringify({
      invoice_no: invoiceNo,
      amount_minor: amountMinor,
      customer_name: "Customer Name",
    }),
  });
  return await response.json();
}

// Step 2: Upload receipt and request instant review
async function uploadAndReviewSlip(invoiceNo: string, amountMinor: number, fileBuffer: Buffer) {
  const base64Data = fileBuffer.toString("base64");
  const response = await fetch(`${API_URL}/proofs/upload-and-review`, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-API-Key": API_KEY,
    },
    body: JSON.stringify({
      invoice_no: invoiceNo,
      amount_minor: amountMinor,
      proof_base64: base64Data,
      proof_mime: "image/png",
      proof_filename: "receipt.png",
    }),
  });
  return await response.json();
}
```

---

### 2. Python (`httpx` / `requests`)

```python
import httpx
import base64

API_URL = "https://pay.example.com/api/v1/external"
API_KEY = "your_external_api_key"

headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json"
}

def upload_and_review(invoice_no: str, amount_minor: int, image_path: str):
    with open(image_path, "rb") as f:
        proof_b64 = base64.b64encode(f.read()).decode("utf-8")

    payload = {
        "invoice_no": invoice_no,
        "amount_minor": amount_minor,
        "proof_base64": proof_b64,
        "proof_mime": "image/jpeg",
        "proof_filename": "slip.jpg"
    }

    with httpx.Client(timeout=10.0) as client:
        response = client.post(f"{API_URL}/proofs/upload-and-review", json=payload, headers=headers)
        return response.json()
```

---

### 3. PHP (cURL)

```php
<?php
$apiUrl = 'https://pay.example.com/api/v1/external';
$apiKey = 'your_external_api_key';

function uploadAndReview($invoiceNo, $amountMinor, $filePath) {
    global $apiUrl, $apiKey;

    $fileData = file_get_contents($filePath);
    $base64 = base64_encode($fileData);

    $payload = json_encode([
        'invoice_no' => $invoiceNo,
        'amount_minor' => $amountMinor,
        'proof_base64' => $base64,
        'proof_mime' => 'image/png',
        'proof_filename' => basename($filePath)
    ]);

    $ch = curl_init($apiUrl . '/proofs/upload-and-review');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-API-Key: ' . $apiKey
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
?>
```
