# API Reference & Developer Integration Guide

## Overview

STX Enterprises Cloud Platform exposes two primary API layers:
1. **STX Hosting REST API (`/api/v1/*`)**: Management API for servers, applications, deployments, databases, and teams.
2. **PayVerify External API (`/api/v1/external/*`)**: High-performance payment verification, AI receipt extraction, and settlement API for e-commerce, POS, and ERP integrations.

---

## 1. STX Hosting REST API

### 1.1 Authentication & Token Abilities
All requests to `/api/v1/*` must include a Laravel Sanctum API token in the `Authorization` header:
```bash
Authorization: Bearer 1|abcdef1234567890...
```

Tokens are assigned specific abilities enforced by the `ApiAbility` middleware:
- `*` or `root`: Unrestricted administrative actions across the instance.
- `write`: Create, modify, and delete resources within the token's team.
- `read`: Read-only queries for resources, statuses, and metrics.
- `deploy`: Restricted ability permitting deployment triggers only.

> [!NOTE]
> The `ApiSensitiveData` middleware automatically redacts sensitive fields (internal IDs, decrypted secrets, SSH private keys) for non-owner and non-admin requests.

### 1.2 Core Endpoints Summary

| Method | Endpoint | Ability | Description |
|---|---|---|---|
| `GET` | `/api/v1/servers` | `read` | List all managed servers accessible to the team |
| `GET` | `/api/v1/servers/{uuid}` | `read` | Retrieve specific server configuration and status |
| `GET` | `/api/v1/applications` | `read` | List deployed applications |
| `POST` | `/api/v1/deploy` | `deploy` | Trigger immediate deployment for application or service |
| `GET` | `/api/v1/deployments/{uuid}` | `read` | Query real-time build logs and deployment status |
| `GET` | `/api/v1/databases` | `read` | List standalone managed database instances |

---

## 2. PayVerify External API

The **PayVerify External API** allows external checkout systems (Shopify, WooCommerce, custom web/mobile checkouts) to automate bank transfer reconciliation and verify cash deposit slips.

### 2.1 Authentication
Requests require authentication using either header:
```bash
X-API-Key: your_external_api_key
# or
Authorization: Bearer your_external_api_key
```

### 2.2 Upload & Review Payment Slip
**Endpoint:** `POST /api/v1/external/proofs/upload-and-review`

Submits a base64-encoded receipt photo or PDF for instant multimodal AI vision extraction, visual forensics (SHA-256 and dHash), and deterministic bank reconciliation.

#### Request Payload
```json
{
  "invoice_no": "INV-2026-0042",
  "amount_minor": 450000,
  "proof_base64": "iVBORw0KGgoAAAANSUhEUgAA...",
  "proof_mime": "image/png",
  "proof_filename": "deposit_slip.png",
  "customer": {
    "id": "cust_123",
    "name": "Nimal Perera",
    "email": "nimal@example.com",
    "phone": "+94771234567"
  },
  "payment": {
    "channel": "bank_transfer",
    "reference": "032699400003",
    "bank_name": "Commercial Bank of Ceylon PLC"
  },
  "auto_match": true
}
```

#### Response Payload (Approved)
```json
{
  "is_approved": true,
  "status": "APPROVED",
  "claim_id": 104,
  "verdict": {
    "decision": "approved",
    "confidence_score": 0.98,
    "summary": "Bank match confirmed: STAN 032699400003 credited on 2026-11-14.",
    "discrepancies": []
  },
  "extraction": {
    "amount_minor": 450000,
    "currency": "LKR",
    "reference": "032699400003",
    "transaction_time": "2026-11-14T10:15:00Z",
    "branch": "BATAPOLA-CRM1 BR"
  },
  "forensics": {
    "dhash": "a1b2c3d4e5f60718",
    "sha256": "e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855",
    "duplicate_detected": false
  }
}
```

### 2.3 Query Order Payment Status
**Endpoint:** `GET /api/v1/external/orders/{invoice_no}`

Returns the real-time settlement status of an order.

#### Example Request
```bash
curl -X GET "https://pay.example.com/api/v1/external/orders/INV-2026-0042" \
  -H "X-API-Key: dev_test_key"
```

#### Response
```json
{
  "order_id": "ord_9981",
  "invoice_no": "INV-2026-0042",
  "amount_minor": 450000,
  "amount_formatted": "Rs. 4,500.00",
  "currency": "LKR",
  "status": "PAID",
  "payment_code": "8F3A2B1C",
  "created_at": "2026-11-14T10:00:00Z",
  "settled_at": "2026-11-14T10:16:30Z"
}
```

### 2.4 Health & System Diagnostics
- `GET /api/v1/external/health`: Returns subsystem readiness (PostgreSQL latency, Fernet storage writability, WhatsApp bot connectivity, active Android relays, AI vision model readiness).
- `GET /api/v1/external/system-status`: Returns claim queue metrics, pending reviews count, and average turnaround time.

---

## 3. Outbound Cryptographic Webhooks

When configured (`PV_WEBHOOK_URL`), PayVerify dispatches HTTP POST webhooks to notify external platforms of state changes.

### Security
Every webhook payload includes an **HMAC-SHA256 signature** in the header:
```bash
X-PayVerify-Signature: sha256=d5b3a...
```
Verify the signature by computing `hash_hmac('sha256', $rawBody, $webhookSecret)`.

### Supported Webhook Events
- `claim.created`: Customer uploaded a payment slip; verification underway.
- `payment.approved`: Payment reconciled via bank credit or high-confidence AI.
- `payment.rejected`: Payment slip rejected (duplicate, unreadable, or wrong bank).
- `payment.escalated`: Slip escalated to manual review queue.
