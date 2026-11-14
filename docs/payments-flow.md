# Payments, PayVerify AI Verification & Billing Operations

## Overview

STX Enterprises Cloud Platform implements a deterministic, fraud-resistant billing workflow specializing in bank deposits, wire transfers, and automated statement reconciliation (tailored for **Commercial Bank of Ceylon PLC - ComBank**).

The engine eliminates manual reconciliation bottlenecks by combining **pure-pixel raster sanitization**, **fast perceptual dHash duplicate detection**, **Google Gemini 1.5 Flash multimodal vision AI**, **automated ComBank WhatsApp Banking statement synchronization**, **zero-leak Android SMS/OTP relays**, and a **pessimistic-locked staff review workbench**.

---

## 1. End-to-End Billing & Settlement Lifecycle

```mermaid
flowchart TD
    subgraph CustomerPortal["1. Customer Checkout & Submission"]
        Checkout["Storefront / Portal Checkout"]
        InvoiceCreated["Invoice Generated (status: unpaid)"]
        UploadSlip["Upload Bank Deposit Slip / Transfer Photo"]
    end

    subgraph SecurityPipeline["2. Forensic Ingestion & Sanitization"]
        PurePixel["Pure-Pixel Re-encoding (imagejpeg / imagepng)"]
        DHashCheck["Fast Perceptual dHash Duplicate Check"]
    end

    subgraph VerificationJob["3. Asynchronous Verification Job (VerifyPaymentProofJob)"]
        PayVerifyCall["Dispatch to PayVerify External API"]
        par["Parallel Vector Analysis"]
            AIVision["Multimodal AI Vision (Gemini 1.5 Flash / Ollama)"]
            BankStatements["ComBank WhatsApp Statement & SMS Engine"]
        end
        DecisionEngine{"Evaluate Confidence & Rules"}
    end

    subgraph Outcomes["4. Settlement Decisions"]
        AutoApprove["Auto-Approve (status: auto_verified)"]
        HardReject["Hard Reject (status: rejected)"]
        ManualQueue["Escalate to Manual Queue (status: pending_review)"]
    end

    subgraph StaffWorkbench["5. Staff Review (/admin/billing/proofs)"]
        LockRow["Acquire Row Lock (lockForUpdate)"]
        SideBySide["Side-by-Side Visual Diff & Bank Candidates"]
        StaffDecision{"Staff Review Decision"}
        ManualApprove["Approve (basis: admin_override)"]
        ManualReject["Reject with Custom Reason"]
    end

    subgraph Activation["6. Entitlement & Lifecycle"]
        Unlock["Unlock Service Quotas & Activate Entitlement"]
        Renewals["Renewal Invoices (7 Days Prior)"]
        Suspensions["Overdue Suspension (After 4-Day Grace)"]
    end

    Checkout --> InvoiceCreated --> UploadSlip
    UploadSlip --> PurePixel --> DHashCheck
    DHashCheck -->|Duplicate Found| HardReject
    DHashCheck -->|Unique Slip| PayVerifyCall
    PayVerifyCall --> par
    par --> DecisionEngine

    DecisionEngine -->|Bank Match OR Confidence >= 0.90| AutoApprove
    DecisionEngine -->|Duplicate / Wrong Bank / Confidence < 0.60| HardReject
    DecisionEngine -->|Score 0.60 - 0.89 OR Discrepancy| ManualQueue

    ManualQueue --> LockRow --> SideBySide --> StaffDecision
    StaffDecision -->|Approve| ManualApprove
    StaffDecision -->|Reject| ManualReject

    AutoApprove --> Unlock
    ManualApprove --> Unlock
    Unlock --> Renewals
    Renewals --> Suspensions
```

---

## 2. Ingestion Security & Forensics

### 2.1 Pure-Pixel Sanitization (`ProofStorageService.php`)
Uploaded raster images (JPEG, PNG, WEBP) are systematically decoded and re-encoded using server-side graphics processing (`imagecreatefromstring` ➔ `imagejpeg` / `imagepng`).
- This permanently strips malicious polyglot webshell payloads, embedded executable code, and malformed EXIF metadata.
- Sanitized files are saved to the private proof storage disk (`PAYMENT_PROOFS_DISK`).

### 2.2 Perceptual Difference Hash (`DHashService.php`)
- Computes a 64-bit perceptual difference hash (`dHash`) of every receipt image.
- Compares against historical proofs in the database using Hamming distance.
- If a matching slip is found with a distance `<= 5`, the submission is immediately flagged as a duplicate and rejected, preventing receipt re-use across multiple invoices.

---

## 3. PayVerify AI & Multi-Vector Engine

The platform integrates with the **PayVerify** microservice suite (`payverify/`):

### 3.1 Multimodal Vision Extractors
- **Google Gemini 1.5 Flash (`GeminiExtractor`)**: Free, high-speed multimodal AI vision analyzing receipt images and PDFs. Extracts amount, transaction reference, timestamp, depositor name, and destination account tail.
- **Local Ollama CPU Extractor (`LocalOllamaExtractor`)**: Offline fallback utilizing `llama3.2-vision` engineered for 2–4 GB RAM servers without GPU requirements.
- **Forensic Signals**: Evaluates EXIF modification software traces and PDF creation timestamps.

### 3.2 ComBank WhatsApp Banking Synchronization
- The `payverify-whatsapp-bot` links as an authorized companion device via the `@whiskeysockets/baileys` multi-device protocol using an **8-digit pairing code** or QR scan.
- Automates conversational banking statements with ComBank (`+94 77 123 4567`).
- Implements a **60-second in-memory buffered OTP queue** to prevent race conditions when incoming SMS OTPs arrive before the bank's prompt.
- Extracts ATM/CRM branches (`BATAPOLA-CRM1 BR`), STAN numbers, amounts, directions (`Cr`/`Dr`), and balances.

### 3.3 Zero-Leak Android Interceptor (`payverify-android`)
- Native Kotlin Android app deployed on business relay smartphones.
- Captures incoming SMS alerts from `COMBANK` or keypad numeric code `2662265`.
- **Zero-Leak Guarantee**: OTP codes are strictly held in-memory and relayed directly to the active bot session over TLS; never persisted to SQLite, flash storage, or logs.
- Dispatches signed HTTP requests to the PayVerify API using **hardware-backed ECDSA P-256** keys in Android Keystore.

---

## 4. Confidence Evaluation & Decision Rules

`App\Jobs\Commerce\VerifyPaymentProofJob` evaluates verification payloads according to strict thresholds:

| Decision Case | Trigger Conditions | Resulting Status | Action Taken |
|---|---|---|---|
| **Case 1: Auto-Approval** | `is_approved === true` (Bank ledger credit matched) **OR** (`auto_approve_enabled` && `confidence >= 0.90` && no duplicates && no wrong recipient && no amount mismatch && no discrepancies) | `auto_verified` | Marks invoice paid, order paid, grants entitlements, dispatches `PaymentApprovedNotification`. |
| **Case 2: Hard Rejection** | Duplicate proof detected **OR** unrecognized receiving bank account **OR** `confidence < 0.60` **OR** amount mismatch with confidence `>= 0.80` | `rejected` | Reverts invoice to `unpaid`, sets customer rejection reason, sends email prompting clean re-upload. |
| **Case 3: Manual Review** | Confidence between `0.60` and `0.89` **OR** unconfirmed transaction reference **OR** minor date skew | `pending_review` | Escalates proof to `/admin/billing/proofs` and dispatches high-priority staff alert. |

---

## 5. Staff Review Workbench (`/admin/billing/proofs`)

Platform finance staff review escalated payments in `App\Livewire\Admin\Billing\ProofReviewQueue`:

### Workbench Features
1. **Pessimistic Concurrency Locking**: Review operations invoke `lockForUpdate()` on both `PaymentProof` and `Invoice` rows, returning HTTP 409 Conflict if another administrator processed the invoice concurrently.
2. **Side-by-Side Visual Diff**: Compares customer-entered amounts and references against AI-extracted values with visual discrepancy highlights.
3. **Interactive Proof Viewer**: High-contrast pan, zoom, and rotate controls for inspecting fine print and rubber stamps.
4. **Bank Candidates Matcher**: Displays unmatched bank statement credits within the 7-day payment window for 1-click allocation.
5. **Audit Logging**: Every manual approval or rejection records an immutable entry in `CommerceAuditLog` with staff user ID, IP address, and written reason.

---

## 6. Subscription Lifecycles & Automated Renewals

### Automated Renewal Invoices (`GenerateRenewalInvoicesJob`)
- Runs daily in the scheduled task pipeline.
- Scans `HostingEntitlement` records expiring within **7 days**.
- Automatically generates renewal invoices, locks canonical pricing, and notifies customers with payment instructions.

### Overdue Suspensions & Grace Periods (`ProcessOverdueSuspensionsJob`)
- Grants a **4-day grace period** following invoice due dates.
- If renewal remains unpaid upon grace period expiration, automatically suspends associated service entitlements (`status = 'suspended'`).
- Enforces deployment gating: suspended services cannot be started, deployed, or modified until payment is settled.

### Refunds & Entitlement Rollbacks (`RefundService.php`)
- Authorized staff can issue full or partial refunds.
- Reverses customer quota entitlements, updates invoice statuses to `refunded`, logs financial ledger entries, and notifies the client.
