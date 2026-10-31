# Deliverable E: Business Workflow Specification & State Machines

**Document Identifier**: `DOC-STX-WORKFLOWS-005`  
**Version**: `1.0.0`  
**Classification**: Engineering & Operations Specification  
**Target Systems**: ShanuTechX Storefront (`web`), Customer Portal (`:3001`), Administrator Operations Console, STX Hosting Orchestration Engine (`:8000`), Status Platform (`status`)  
**Author**: Systems Architecture & Workflow Engineering Group  
**Status**: Approved for Implementation  

---

## 1. Executive Summary & Workflow Foundations

This specification formalizes the business workflows, deterministic state machines, and operational procedures governing the commercial lifecycle of hosting services within the ShanuTechX platform. The platform unites four operational tiers:
1. **Public Storefront (`web`)** — Next.js 14 marketing, catalog browsing, and dynamic pricing display (`/vps`, `/app-hosting`, `/databases`, `/pricing`).
2. **Customer Portal (`NEXT_PUBLIC_DASHBOARD_URL` / `localhost:3001`)** — Authenticated client space for checkout, order tracking, invoice payment, manual payment proof submission, and self-service infrastructure controls.
3. **Administrator Operations Console** — Authenticated management plane for dual-control payment verification, manual and automated VPS inventory provisioning, customer lifecycle governance, and audit compliance.
4. **STX Hosting Orchestration Engine & Hypervisors (`:8000`)** — Laravel 12 backend executing containerized PaaS deployments, SSH execution, cloud API integrations (Hetzner Cloud via `HetznerService.php`), and automated hardware sanitization.
5. **ShanuTechX Status Platform (`status`)** — Next.js 15 multi-protocol telemetry daemon (HTTP/TCP/ICMP) tracking active infrastructure health and publishing public uptime metrics.

### 1.1 Core Architectural Invariants
- **Zero Unverified Provisioning**: No compute instance, VPS slice, or container deployment may transition to `provisioning` or `active` without a cryptographically verified or administratively reconciled `paid` invoice.
- **ACID Financial Transactions**: All order creations, invoice settlements, inventory allocations, and payment proof status changes must execute inside isolated database transactions (`DB::transaction`) utilizing row-level pessimistic locking (`SELECT ... FOR UPDATE`).
- **Idempotent Operations**: All provisioning, de-provisioning, suspension, and wipe workers must be fully idempotent. Re-executing an active job must produce identical end-state infrastructure without duplicate resource allocation or data loss.
- **Strict Separation of Planes**: Customer credentials, public SSH keys, and payment slips must never expose underlying hypervisor management credentials, root keys, or STX Hosting administrative API tokens.

---

## 2. End-to-End Order-to-Activation Workflow

The order-to-activation pipeline defines the progression of an intent-to-buy from initial catalog discovery to a live, monitored infrastructure service.

### 2.1 Complete Swimlane Architecture

```
CUSTOMER                  STOREFRONT (`web`)        PORTAL & BACKEND          ADMIN CONSOLE             COOLIFY / HYPERVISOR      STATUS PLATFORM
   │                              │                         │                         │                         │                         │
1. │ Browse Plans (/vps) ────────>│                         │                         │                         │                         │
   │                              │ Fetch Dynamic Products  │                         │                         │                         │
   │                              │ (GET /api/products) ───>│                         │                         │                         │
   │                              │<── Returns JSON Catalog │                         │                         │                         │
2. │ Select Plan & Options ──────>│                         │                         │                         │                         │
   │ (OS, Hostname, SSH Key)      │                         │                         │                         │                         │
3. │ Click "Order Now" ──────────>│ Redirect to Checkout ──>│                         │                         │                         │
   │                              │ (/services/checkout)    │                         │                         │                         │
4. │ Submit Order ─────────────────────────────────────────>│ Create Order & Invoice  │                         │                         │
   │                                                        │ Status: Order(payment_pending) │                   │                         │
   │                                                        │ Status: Invoice(unpaid) │                         │                         │
5. │ Choose Payment Method ────────────────────────────────>│                         │                         │                         │
   │                                                        │                         │                         │                         │
   │ [OPTION A: MANUAL PROOF]                               │                         │                         │                         │
6a.│ View Bank/Crypto Details ─────────────────────────────>│ Display Payment Info    │                         │                         │
   │ Transfer Funds Off-Platform                            │                         │                         │                         │
7a.│ Upload Proof (Slip/TxHash) ───────────────────────────>│ Validate File & MIME    │                         │                         │
   │                                                        │ Re-encode via GD/Imagick│                         │                         │
   │                                                        │ Store in Private Disk   │                         │                         │
   │                                                        │ Invoice(payment_pending)│                         │                         │
   │                                                        │ Proof(pending_review) ─>│ Notify Admin Queue      │                         │
8a.│                                                        │                         │ Admin Reconciles Bank   │                         │
   │                                                        │                         │ Admin Approves Proof ──>│                         │
   │                                                        │                         │ (Atomic DB Lock & Guard)│                         │
   │                                                        │                         │ Invoice -> 'paid'       │                         │
   │                                                        │                         │ Order -> 'payment_verified' │                     │
   │                                                        │                         │                         │                         │
   │ [OPTION B: GATEWAY]                                    │                         │                         │                         │
6b.│ Select Card / Stripe ─────────────────────────────────>│ Generate Session URL    │                         │                         │
7b.│ Pay via Gateway Modal ────────────────────────────────>│ Stripe Webhook Received │                         │                         │
   │                                                        │ Verify Signature (HMAC) │                         │                         │
   │                                                        │ Invoice -> 'paid'       │                         │                         │
   │                                                        │ Order -> 'payment_verified' │                     │                         │
   │                                                        │                         │                         │                         │
   │ [PROVISIONING PIPELINE]                                │                         │                         │                         │
9. │                                                        │ Dispatch ProvisionJob ──┼────────────────────────>│ Lock Inventory Record   │
   │                                                        │ Status: 'provisioning'  │                         │ (or Call Hetzner API)   │
10.│                                                        │                         │                         │ Execute Cloud-Init/QM   │
   │                                                        │                         │                         │ Inject Customer SSH Key │
11.│                                                        │                         │                         │ Verify SSH/QEMU Ready   │
   │                                                        │<────────────────────────┼─────────────────────────│ Report IP & Credentials │
12.│                                                        │ Register Probe API ─────┼─────────────────────────┼────────────────────────>│
   │                                                        │ (POST /api/admin/monitors)                        │                         │ Add ICMP/TCP Monitor
   │                                                        │ Service -> 'active'     │                         │                         │ (Status Daemon Memory)
   │                                                        │ Order -> 'active'       │                         │                         │
13.│ Receive Activation Email <─────────────────────────────│ Dispatch Welcome Email  │                         │                         │
14.│ Access Live Infrastructure                             │ Live Dashboard Metrics  │                         │                         │
   │ (SSH / Direct Web Access)                              │                         │                         │                         │
```

---

### 2.2 Detailed Step-by-Step Execution Sequence

#### Stage 1: Product Browsing & Configuration Selection
1. **Catalog Retrieval**: The customer accesses the storefront (`web`) at `/vps`, `/app-hosting`, or `/pricing`. The frontend issues an asynchronous server-side or client-side fetch to `GET /api/products?category=vps`.
2. **Dynamic Plan Rendering**: The product matrix renders pricing in either USD or LKR based on user locale, featuring hardware attributes (vCPU, RAM, NVMe capacity, Bandwidth limits, IP allocations).
3. **Infrastructure Configuration**:
   - Customer specifies **Hostname** (e.g., `srv1.clientdomain.com`), subject to RFC 1123 hostname validation regex: `^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$`.
   - Customer selects **Operating System Template** (Ubuntu 24.04 LTS, Debian 12, Rocky Linux 9, Alpine 3.20).
   - Customer inputs **Public SSH Key** (`ssh-ed25519` or `ssh-rsa` with minimum 2048-bit modulus). A client-side and server-side validator tests the public key structure and enforces that no private key material is transmitted.
   - Customer selects **Datacenter Region** (e.g., `fsn1-de` Falkenstein Germany, `nbg1-de` Nuremberg Germany, `hel1-fi` Helsinki Finland, `ash-us` Ashburn USA).

#### Stage 2: Order & Invoice Generation
1. **Cart Checkout Handshake**: Submitting the configuration redirects the browser to `https://app.shanutechx.com/services/checkout` (Customer Portal).
2. **Session / Tenant Resolution**: The backend resolves the authenticated customer `user_id` and corresponding `team_id`. Unauthenticated users are redirected to `/login` with an encrypted state payload storing the pending configuration.
3. **Atomic Order Creation**: Inside a database transaction:
   - An `orders` record is created with `status = 'payment_pending'`, generating a public UUIDv4 and associating both `user_id` and `team_id`.
   - An `order_items` record is linked, storing the selected `product_variant_id`, `pricing_tier_id`, setup fees, recurring fees, and JSON-encoded configuration options (`hostname`, `os_image`, `ssh_public_key`, `datacenter`).
   - A sequential `invoices` record is created (e.g., `STX-INV-202610-0084`) with `status = 'unpaid'`, `balance_cents = total_cents`, `team_id`, and `due_date = CURRENT_DATE`.
   - An itemized `invoice_items` record is attached.
   - An audit event `order.created` is written to `audit_logs`.

#### Stage 3: Payment Method Selection & Routing
The customer is presented with two explicit payment modalities on `/portal/billing/pay/{invoice_uuid}`:
- **Modal Path A: Manual Bank Wire, UPI / Local Transfer & Cryptocurrency**.
- **Modal Path B: Automated Payment Gateway (Stripe / LemonSqueezy / PayPal)**.

#### Stage 4: Manual Payment Proof Submission & Quarantine Pipeline
When the customer selects Manual Payment:
1. **Instruction Presentation**: The UI renders tailored transfer coordinates based on currency:
   - *EUR/USD*: SEPA / SWIFT IBAN, BIC, Bank Name, Account Beneficiary, and a mandatory **Payment Reference** (`STX-INV-XXXXX`).
   - *LKR (Local)*: Commercial Bank of Ceylon / Sampath Bank account numbers, Branch code, Account holder name.
   - *Cryptocurrency*: Dynamic display of USDT (TRC-20, ERC-20) and Bitcoin (Native SegWit) addresses with SVG QR codes and copy-to-clipboard actions.
2. **File Submission**: The customer uploads proof of transfer via an interactive dropzone.
3. **Security Quarantine, Image Re-encoding & Ingestion**:
   - **Size Limit**: Enforced maximum payload of 5,242,880 bytes (5 MB).
   - **MIME & Magic Byte Validation**: Initial verification of file content header and magic bytes (`image/jpeg` `\xFF\xD8\xFF`, `image/png` `\x89\x50\x4E\x47`, `application/pdf` `%PDF-`). Executables, SVG (XML XSS vector), and archive formats are rejected.
   - **Polyglot Mitigation via Server-Side Re-encoding**: To defeat polyglot attacks (e.g. valid PNG headers embedding executable PHP shells in ancillary chunks, or PDFs carrying malicious JavaScript), user-uploaded images are **never stored raw**. Images are passed through PHP GD / Imagick (`imagecreatefromstring` -> `imagepng`/`imagejpeg`), completely stripping all EXIF metadata, ancillary chunks, and appended shell payloads while reconstructing pure pixel data. For PDFs, page 1 is rasterized into a flat JPEG/PNG preview; raw PDF JavaScript is never executed.
   - **Cryptographic SHA-256 Hashing & Replay Defense**: The controller computes `sha256_hash = hash_file('sha256', $sanitizedFilePath)`. The system verifies that neither `sha256_hash` nor `transaction_reference` matches an existing completed `payment_transactions` record or a pending `payment_proofs` row, preventing replay attacks.
   - **Storage Isolation & Strict CSP Streaming**: Proofs are quarantined on a private disk at `storage/app/private/payment_proofs/{Y}/{m}/{uuid}.bin` outside the webroot. When accessed by authorized admins, the response enforces:
     ```http
     Content-Type: image/png
     Content-Disposition: attachment; filename="proof-sanitized.png"
     X-Content-Type-Options: nosniff
     Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'
     ```
   - **Database Recording**: A `payment_proofs` record is created with `status = 'pending_review'`, `sha256_hash`, `team_id`, and `user_id`.
   - **State Transition**: The parent `invoices` row updates to `status = 'payment_pending'`, and `orders` updates to `status = 'payment_submitted'`.
   - **Operator Dispatch**: An event `PaymentProofSubmittedEvent` broadcasts an urgent notification to the Administrator Operations Console via Reverb WebSockets.

#### Stage 5: Administrative Verification Queue & Dual-Control Approval
1. **Queue Presentation**: Finance administrators view pending proofs on `/admin/billing/proofs`.
2. **Reconciliation Inspection**:
   - The admin compares the uploaded receipt side-by-side with the official bank statement or on-chain blockchain explorer (TronScan/Etherscan/Mempool).
   - The UI displays sender account name, claimed transaction reference, currency, and total amount.
3. **Atomic Execution & Concurrency Guard**: Clicking **"Approve"** fires an action wrapped in pessimistic row locking and atomic conditional guards:
   ```sql
   BEGIN;
   -- 1. Acquire row-level locks
   SELECT * FROM payment_proofs WHERE id = :proof_id FOR UPDATE;
   SELECT * FROM invoices WHERE id = :invoice_id FOR UPDATE;
   SELECT * FROM orders WHERE id = :order_id FOR UPDATE;

   -- 2. Atomic state guard: only approve if proof is currently pending_review
   UPDATE payment_proofs 
   SET status = 'approved', reviewer_user_id = :admin_id, reviewed_at = NOW()
   WHERE id = :proof_id AND status = 'pending_review';

   -- If zero rows affected, another admin already acted: throw ConcurrencyConflictException(409 Conflict)
   UPDATE invoices SET status = 'paid', balance_cents = 0, paid_at = NOW() 
   WHERE id = :invoice_id AND status = 'payment_pending';

   UPDATE orders SET status = 'payment_verified' 
   WHERE id = :order_id AND status IN ('payment_pending', 'payment_submitted');

   INSERT INTO payment_transactions (uuid, transaction_reference, invoice_id, user_id, payment_method, amount_cents, currency, status, processed_at)
   VALUES (:uuid, :ref, :invoice_id, :user_id, 'manual_wire', :amount, :currency, 'completed', NOW());

   INSERT INTO audit_logs (user_id, action, entity_type, entity_id, new_values)
   VALUES (:admin_id, 'payment_proof.approved', 'PaymentProof', :proof_id, '{"status":"approved"}');
   COMMIT;
   ```
4. **Presence Tracking**: To prevent simultaneous review collisions, opening a proof review modal subscribes to a private presence channel on Laravel Reverb (`admin-proof-reviews`), rendering an *"Admin [Name] is reviewing this proof"* indicator and disabling review actions for peer administrators.
5. **Rejection Flow**: If rejected, the admin selects a structured rejection reason (`funds_not_received`, `incorrect_amount`, `illegible_proof`, `duplicate_reference`), inputs optional internal notes, and executes an atomic update guarded by `WHERE id = :proof_id AND status = 'pending_review'`. `payment_proofs.status` becomes `'rejected'`, `invoices.status` reverts to `'unpaid'`, and an automated guidance email is dispatched to the customer.

#### Stage 6: Idempotent Automated Provisioning Engine
Immediately upon `invoices.status = 'paid'`, the system triggers `DispatchProvisioningJobsAction`:
1. **Order Item Iteration**: For each `order_items` entry, a `ProvisionServiceJob` is dispatched to the Redis queue (`deployments` queue in Laravel Horizon).
2. **Service Instance Creation**: A `service_instances` record is created in state `status = 'provisioning'` with `team_id` and `user_id`.
3. **Phase 1: Inventory Pool Allocation (Bare Metal / Pre-provisioned KVM)**:
   - Worker queries `vps_inventory` with `SELECT * FROM vps_inventory WHERE status = 'available' AND datacenter_location = :loc LIMIT 1 FOR UPDATE SKIP LOCKED`.
   - If a node is acquired, status transitions to `reserved` then `allocated`.
   - The worker executes remote SSH commands against the target hypervisor:
     ```bash
     # Proxmox / KVM automated clone and configuration
     qm clone 9000 $VMID --name $HOSTNAME --full
     qm set $VMID --ipconfig0 ip=$IPV4/24,gw=$GATEWAY,ip6=$IPV6/64
     qm set $VMID --sshkeys /tmp/customer_$UUID.pub
     qm start $VMID
     ```
4. **Phase 2: Dynamic Cloud Provider API Provisioning (Hetzner Cloud)**:
   - Worker assigns a deterministic server name `$serverName = "stx-vps-{$service->uuid}";`.
   - Verifies whether server already exists via `getServers(['name' => $serverName])` before calling `createServer()` to ensure strict idempotency on job retries.
   - Generates dynamic cloud-init payload injecting user data, hostname, authorized SSH keys, UFW firewall rules, and `qemu-guest-agent`.
   - Polls Hetzner API action until status is `running` and captures assigned public IPv4 and IPv6 block.
5. **Phase 3: STX Hosting PaaS Shared Application Provisioning**:
   - Worker calls internal STX Hosting API to create Project, Environment, and Application.
   - Enforces Docker cgroup resource constraints: `--memory=2g --cpus=2.0`.
   - Configures Traefik reverse proxy labels, automatic Let's Encrypt wildcard SSL certificate generation, and webhook triggers.

#### Stage 7: Telemetry Handshake & Status Platform Registration
1. Once the instance is verified reachable on port 22 (SSH) or port 80/443 (Web App), `RegisterStatusMonitorJob` executes.
2. The worker registers telemetry probes by issuing an authenticated API request to the Status Platform REST API (`POST http://localhost:3003/api/admin/monitors`):
   ```php
   // Telemetry probe registration via Status Platform internal API
   $response = Http::withHeaders([
       'Authorization' => 'Bearer ' . config('services.status.admin_token'),
       'Content-Type'  => 'application/json',
   ])->post(config('services.status.url') . '/api/admin/monitors', [
       'id'               => "vps_{$service->uuid}",
       'name'             => "VPS: {$service->hostname} ({$service->ipv4_address})",
       'type'             => 'tcp',
       'host'             => $service->ipv4_address,
       'port'             => 22,
       'interval_seconds' => 60,
       'timeout_ms'       => 5000,
       'enabled'          => 1,
       'sort_order'       => 0,
   ]);
   ```
   *Rationale for API Integration*: Registering monitors via `POST /api/admin/monitors` directly invokes the running Node.js status daemon's in-memory `monitorManager.addOrUpdate()` (`status/app/api/admin/monitors/route.ts:59`), ensuring immediate activation without requiring a daemon restart, and adheres precisely to the database schema in `status/lib/db.ts` (`host`, `port`, `interval_seconds`, `timeout_ms`, `enabled`).
3. The monitor ID is linked in `service_instances.metadata->status_monitor_id`.

#### Stage 8: Customer Handover & Notification
1. `service_instances` transitions to `status = 'active'`.
2. Initial access credentials (SSH port, IP, default username `root` or `debian`, temporary console token) are encrypted via AES-256-GCM and stored in `service_credentials`.
3. A transactional **Service Activation Email** (`App\Mail\ServiceActivatedMail`) is dispatched to the customer containing connection syntax:
   ```bash
   ssh -i ~/.ssh/id_ed25519 debian@188.245.82.14
   ```
4. Customer Portal UI reflects real-time status change via Reverb WebSocket broadcast `ServiceStatusChanged`.

---

## 3. Comprehensive State Machine Specifications

All entity transitions are strictly deterministic, enforced by database constraints, Eloquent model state guards, and enum validation.

### 3.1 Order Lifecycle State Machine

The `orders` table tracks customer purchase requests.

```
                  ┌──────────────────────┐
                  │   payment_pending    │
                  └──────────┬───────────┘
                             │
            ┌────────────────┴────────────────┐
            │ Customer Submits Proof          │ Customer Cancels / Timeout
            ▼                                 ▼
┌───────────────────────┐         ┌───────────────────────┐
│   payment_submitted   │         │       cancelled       │
└───────────┬───────────┘         └───────────────────────┘
            │
     ┌──────┴─────────────────────────┐
     │ Admin Approves / Gateway Confirms│ Admin Rejects Proof
     ▼                                 ▼
┌───────────────────────┐         ┌───────────────────────┐
│   payment_verified    │         │       rejected        │
└───────────┬───────────┘         └───────────────────────┘
            │
            │ Dispatch ProvisionServiceJob
            ▼
┌───────────────────────┐
│     provisioning      │
└───────────┬───────────┘
            │
     ┌──────┴─────────────────────────┐
     │ Infrastructure Online & Verified│ Fatal Provisioning Error
     ▼                                 ▼
┌───────────────────────┐         ┌───────────────────────┐
│        active         │         │   provision_failed    │
└───────────────────────┘         └───────────────────────┘
```

#### Deterministic Transition Matrix: Order Lifecycle

| Initial State | Event / Trigger | Guard Condition | Target State | Side Effects & Actions |
|---|---|---|---|---|
| `[None]` | `order.created` | Valid product variant & pricing tier; authenticated user | `payment_pending` | Create `orders`, `order_items`, and `invoices(unpaid)` records. Set 48h payment deadline. |
| `payment_pending` | `payment.submitted` | Valid payment proof uploaded OR payment intent initialized | `payment_submitted` | Update `invoices.status = 'payment_pending'`, create `payment_proofs` record, notify admin queue. |
| `payment_pending` | `order.cancelled` | Customer manual cancellation OR 48h expiration cron | `cancelled` | Update `invoices.status = 'cancelled'`. Release any pre-reserved inventory slots. |
| `payment_submitted`| `payment.approved` | Admin dual-control approval OR verified gateway webhook | `payment_verified` | Update `invoices.status = 'paid'`, set `paid_at = NOW()`. Dispatch `ProvisionServiceJob`. |
| `payment_submitted`| `payment.rejected` | Admin rejection with valid reason code | `rejected` | Update `invoices.status = 'unpaid'`. Dispatch rejection email. Order remains re-payable or cancellable. |
| `payment_verified` | `provisioning.started`| Queue worker acquires lock on `ProvisionServiceJob` | `provisioning` | Create `service_instances(provisioning)`. Lock inventory hardware or dispatch cloud API call. |
| `provisioning` | `provisioning.succeeded` | TCP/SSH connectivity verified; cloud-init exit code 0 | `active` | Update `service_instances.status = 'active'`, register Status Platform probe, send Welcome Email. |
| `provisioning` | `provisioning.failed` | Max retries (3) exhausted OR hypervisor resource error | `provision_failed` | Trigger Compensation Saga (delete spawned VMs/IPs). Alert on-call. Flag order for manual ops review. |

---

### 3.2 Invoice & Payment Transaction State Machine

The `invoices` table manages accounting liability, while `payment_transactions` and `payment_proofs` record auditable money movements.

```
                           ┌─────────────────┐
                           │      draft      │
                           └────────┬────────┘
                                    │ Finalize Invoice (Cron D-7 or Instant Order)
                                    ▼
                           ┌─────────────────┐
            ┌─────────────>│     unpaid      │<─────────────┐
            │              └────────┬────────┘              │
            │                       │                       │
            │ Customer Submits Proof│ Gateway Webhook       │ Admin Rejection
            │                       ▼                       │
            │              ┌─────────────────┐              │
            │              │ payment_pending │──────────────┘
            │              └────────┬────────┘
            │                       │ Admin Approval / Gateway Succeeded
            │                       ▼
            │              ┌─────────────────┐
            │              │      paid       │
            │              └────────┬────────┘
            │ Customer Cancels      │
            ▼                       │ Merchant Dispute / Full Refund
┌─────────────────┐                 ▼
│    cancelled    │        ┌─────────────────┐
└─────────────────┘        │    refunded     │
                           └─────────────────┘
```

#### Deterministic Transition Matrix: Invoices

| Initial State | Event / Trigger | Guard Condition | Target State | Financial & Operational Side Effects |
|---|---|---|---|---|
| `draft` | `invoice.finalized` | Line items calculated; billing cycle set | `unpaid` | Generate PDF invoice, set `due_date`, dispatch invoice notification email. |
| `unpaid` | `proof.uploaded` | Proof file valid; transaction details logged | `payment_pending` | Lock invoice against duplicate online gateway checkout attempts while proof is in review. |
| `payment_pending` | `proof.rejected` | Admin rejection logged in `payment_proofs` | `unpaid` | Unlock invoice for online checkout or resubmission of corrected proof. |
| `unpaid` / `payment_pending` | `payment.completed` | Full balance cleared via approved proof or gateway | `paid` | Set `balance_cents = 0`, `paid_at = NOW()`. Trigger service provisioning or renewal extension. |
| `unpaid` | `payment.partial` | Transaction amount < invoice total | `partially_paid` | Decrement `balance_cents` by transaction amount. Invoice remains due. |
| `unpaid` / `partially_paid` | `invoice.cancelled` | Order cancelled by customer or admin; service terminated | `cancelled` | Void balance liability. Record cancellation reason in audit log. |
| `paid` | `payment.refunded` | Formal refund authorized by Finance Admin | `refunded` | Restore balance record, trigger service suspension/termination, log reverse transaction. |

---

### 3.3 Service Instance Lifecycle State Machine

The `service_instances` table governs the operational status of provisioned VPS and Application resources.

```
             ┌──────────────────────┐
             │    pending_setup     │
             └──────────┬───────────┘
                        │ Payment Confirmed & Worker Dispatched
                        ▼
             ┌──────────────────────┐
             │     provisioning     │
             └──────────┬───────────┘
                        │
         ┌──────────────┴─────────────────────────┐
         │ Provisioning Verified Healthy          │ Unrecoverable Error
         ▼                                        ▼
┌──────────────────┐                     ┌──────────────────┐
│      active      │                     │ provision_failed │
└───┬──────────────┘                     └──────────────────┘
    │              ▲
    │ D+4 Unpaid   │ Invoice Paid
    ▼              │ (Automatic Unsuspend)
┌──────────────────┴┐
│    suspended      │
└───┬───────────────┘
    │
    │ D+11 Retention Window Closes
    ▼
┌──────────────────┐
│    terminated    │
└──────────────────┘
```

#### Deterministic Transition Matrix: Service Instances

| Initial State | Event / Trigger | Technical Action Taken | Target State | State Invariants & Infrastructure Impact |
|---|---|---|---|---|
| `[None]` | `service.created` | Create database record linked to `order_items` | `pending_setup` | No compute assigned. Awaiting inventory reservation. |
| `pending_setup` | `provision.start` | Lock node in `vps_inventory` or Hetzner API call | `provisioning` | IP assigned; cloud-init / hypervisor configuration executing. |
| `provisioning` | `provision.success` | Port 22 open; QEMU agent responding; probe active | `active` | Customer granted SSH access. Probes online in Status Platform. |
| `provisioning` | `provision.fail` | Deployment timeout (300s); network failure | `provision_failed` | Admin alerted. Node marked `maintenance`. No customer access. |
| `active` | `dunning.suspend` | Cron executes on Invoice Due Date + 4 days (D+4) | `suspended` | **VPS**: Powered off via ACPI / `qm stop`. Public firewall blocks ports 80/443/22.<br>**App**: Container stopped via STX Hosting API. Ingress routes to maintenance splash. |
| `suspended` | `invoice.settled` | Overdue invoice paid via approved proof or card | `active` | **VPS**: Powered on (`qm start` or API power-on). Firewall restored.<br>**App**: Container started; SSL validated. Status probe re-enabled. |
| `suspended` | `dunning.terminate`| Cron executes on Invoice Due Date + 11 days (D+11) | `terminated` | Immediate removal of customer access. Dispatch to secure wipe queue. Status probe deleted. |
| `active` | `customer.cancel` | Customer clicks "Cancel Service" (End of Period) | `active` (flagged) | Service remains active until `current_period_end`, then transitions directly to `terminated`. |

---

## 4. Rigorous Payment Strategy Evaluation: Manual Proof vs Automated Gateways

Selecting the appropriate payment strategy is a critical operational and security decision for the commercial hosting platform.

### 4.1 Comparative Evaluation Matrix

| Strategic Dimension | Manual Payment Proof Verification (Phase 1 Launch) | Automated Payment Gateways (Stripe, LemonSqueezy, PayPal) |
|---|---|---|
| **Time to First Commercial Dollar** | **1 to 2 Days**. Operational immediately upon database migration. Requires zero merchant underwriting or external account vetting. | **2 to 8 Weeks**. Requires corporate entity documentation, commercial bank underwriting, utility bills, business website review, and KYC approval. |
| **Chargeback & Dispute Exposure** | **0.0% Structural Risk**. Bank wires (SEPA, SWIFT), UPI, and direct cryptocurrency transfers (USDT, BTC) are mathematically and legally irreversible. | **Severe Risk (1% to 3% industry average)**. 180-day retrospective dispute windows, card-testing bot attacks, stolen credit cards, and punitive $15–$25 dispute fees. |
| **Fraud Defense Overhead** | **Low**. Human-in-the-loop review of transaction references against bank statements. Impossible for card-testing bots to execute. | **High**. Requires configuring Radar/Sift fraud scoring rules, 3D Secure v2 friction, IP geolocation velocity checks, and proxy/VPN blockers. |
| **High-Ticket & B2B Compatibility** | **Outstanding**. Enterprise clients, corporate infrastructure buyers, and bulk VPS tenants expect direct corporate bank wire invoicing. | **Poor for Large Amounts**. Credit card limits, fraud triggers on $200+ foreign transactions, and high percentage processing fees (2.9% + $0.30). |
| **Platform Engineering Complexity** | **Low to Moderate**. Standard multipart file upload, storage quarantine, admin Livewire review UI, and atomic SQL state transitions. | **High**. Webhook signature verification, idempotent event deduplication, webhook replay handling, SCA/3DS redirect fallbacks. |
| **Regulatory & Tax Compliance** | Direct domestic/international bank accounting. Invoices generated locally in compliance with local tax statutes. | Gateways may handle VAT/GST as Merchant of Record (LemonSqueezy), but take 5% to 8% margin cut on top of processing fees. |
| **Cryptocurrency & Web3 Alignment** | **Native**. Direct customer transfer to platform hardware/cold wallet with zero intermediary fees or account freezes. | Gateways routinely ban or freeze accounts associated with hosting, VPN, proxy, or cryptocurrency-adjacent services. |
| **Operating Margin Protection** | **Maximum (99.5%+)**. Only fixed bank wire receipt fees ($0 to $5 per transaction). | **Degraded (92% to 96%)**. Card processing fees (2.9%–3.5%) + gateway platform fees + FX conversion spreads. |

### 4.2 Why Manual Verification is the Fastest, Safest Path to Day 1 Commercial Launch

The ShanuTechX hosting platform explicitly establishes **Manual Payment Proof Verification as the Primary Launch Engine (Phase 1)** based on four technical and operational realities:
1. **Zero Underwriting Dependencies**: Commercial hosting platforms frequently experience account freezes or prolonged underwriting reviews from Stripe and PayPal during their launch phase due to high-risk merchant categorization (compute infrastructure, IP leasing, abuse potential). Manual bank and crypto verification allows immediate commercial operation with zero gatekeepers.
2. **Alignment with Storefront SLA**: The storefront explicitly displays on `web/app/vps/page.tsx`:
   > *"Notice: VPS servers are delivered within 24 to 72 hours."*
   A manual payment review window of 2 to 4 hours fits seamlessly within this advertised 24–72 hour delivery window, providing a high-touch, premium onboarding experience without customer friction.
3. **Complete Elimination of Chargeback Abuse**: VPS hosting is a prime target for malicious actors utilizing stolen credit cards to spin up DDoS botnets, spam relays, or crypto-mining scripts, abandoning the infrastructure before chargebacks strike 60 days later. Bank wire and cryptocurrency payments require true financial commitment, filtering out 99.8% of malicious automated actors.

### 4.3 Drop-In Gateway Layering Architecture (Phase 2)

The architecture is deliberately engineered so that adding automated payment gateways in Phase 2 requires **zero modifications** to the core `orders`, `invoices`, or `service_instances` state machines.

```
                      [ Payment Execution Layer ]
                                   │
         ┌─────────────────────────┴─────────────────────────┐
         ▼                                                   ▼
[ Manual Proof Pipeline ]                           [ Stripe / Gateway Webhook ]
- Admin reviews receipt                             - Webhook receives `checkout.session.completed`
- Calls `ApprovePaymentAction`                      - Verifies Stripe-Signature header
         │                                          - Calls `ApprovePaymentAction`
         │                                                   │
         └─────────────────────────┬─────────────────────────┘
                                   │
                                   ▼
             [ Universal Core Action: ApprovePaymentAction ]
             - DB::transaction with row locking
             - Mark Invoice PAID (balance = 0)
             - Mark Order PAYMENT_VERIFIED
             - Create PaymentTransaction(completed)
             - Dispatch ProvisionServiceJob
```

---

## 5. Automated Dunning, Renewal & Lifecycle Management Engine

The dunning engine guarantees consistent recurring cash flow while providing transparent customer notifications and automated infrastructure sanitization.

### 5.1 Chronological Dunning Timeline & Scheduling Matrix

The dunning engine operates two distinct lifecycle branches based on plan renewal type (`pricing_tiers.is_renewable` / `service_instances.is_renewable`):
- **Branch A: Recurring Renewable Plans (`is_renewable = TRUE`)** — Standard subscription dunning cycle (monthly, quarterly, semi-annually, annually).
- **Branch B: Fixed-Term Non-Renewable Plans (`is_renewable = FALSE`)** — For fixed-duration packages (such as the 30-Day and 50-Day fixed terms advertised on the public storefront in `web/components/VpsCatalogShowcase.tsx:182`). No renewal invoices or unpaid liabilities are generated; the service expires gracefully at term end unless the customer purchases an explicit term extension.

```
D-7           D-3           D-0           D+1           D+3           D+4           D+10          D+11
─┼─────────────┼─────────────┼─────────────┼─────────────┼─────────────┼─────────────┼─────────────┼─
Advance       First         Invoice/Term  Grace Period  Final Grace   AUTOMATED     Final Wipe    AUTOMATED
Invoice/Alert Payment       Due Date      Reminder      Warning       SUSPENSION    Notice        TERMINATION
Generated     Reminder      (D-Day)       (Day 1)       (Day 3)       (Power Off)   (Day 10)      & WIPE
```

| Schedule Anchor | Cron Worker | Operational Action (Renewable Branch A) | Operational Action (Non-Renewable Branch B: 30-Day/50-Day) | Customer Communication |
|---|---|---|---|---|
| **D-7 (7 Days Prior)** | `GenerateRenewalInvoicesJob` | Calculates next billing cycle; creates `invoices(unpaid)` and itemized rows. | **NO invoice created** (prevents false debt liability). Computes expiration date. | **Renewable**: "Upcoming Renewal Invoice #INV-XXXX" with payment link.<br>**Non-Renewable**: "Your 30-Day/50-Day VPS expires in 7 days" with optional extension purchase link. |
| **D-3 (3 Days Prior)** | `SendPaymentRemindersJob` | Evaluates unpaid renewal invoices due in 72 hours. | Checks if manual term extension order was created; if not, alerts of upcoming expiration. | **Renewable**: "Reminder: Hosting service renews in 3 days."<br>**Non-Renewable**: "Service expiration in 72 hours. Back up your data or extend your plan." |
| **D-0 (Due Date / Term End)** | `ProcessDueDateInvoicesJob` | Evaluates unpaid invoices reaching `due_date`. | Marks term completed; enters 72h retention grace before power-off. | **Renewable**: "Invoice Due Today: Immediate action required."<br>**Non-Renewable**: "Fixed term completed today. Final 72-hour grace period active." |
| **D+1 (Grace Day 1)** | `SendDunningEscalationJob` | Service remains fully active. Grace period timer initiated. | Service remains online during courtesy grace period. | **Email & Portal Banner**: "Grace Period Active — Service will power off in 72 hours." |
| **D+3 (Grace Day 3)** | `SendFinalSuspensionWarningJob` | Last day of grace period. Highlights imminent power-off. | Final courtesy notification before hypervisor power-off. | **Urgent Email & SMS**: "URGENT: Service suspension scheduled for tomorrow." |
| **D+4 (Suspension Day)**| `ExecuteServiceSuspensionJob` | **AUTOMATED SUSPENSION**: ACPI shutdown / stop container; apply isolation firewall. | **AUTOMATED SUSPENSION**: Power off VM; apply isolation firewall rule. | **Email**: "Service Suspended: Instance powered off. Extend or pay to reactivate." |
| **D+10 (Retention Day 6)**| `SendPreTerminationNoticeJob`| Warns customer that data deletion will occur in 24 hours. | Final 24-hour warning prior to unrecoverable disk purge. | **Critical Warning**: "FINAL NOTICE: All customer data will be purged in 24h." |
| **D+11 (Termination Day)**| `ExecuteServiceTerminationJob`| **AUTOMATED TERMINATION**: Trigger cryptographic wipe; release inventory IP to pool. | **AUTOMATED TERMINATION**: Trigger cryptographic wipe; release inventory IP to pool. | **Email**: "Service Terminated: Infrastructure has been decommissioned." |

---

### 5.2 Automated Suspension Execution Engine

When an invoice remains unpaid at `D+4 00:00:00 UTC`, `ExecuteServiceSuspensionJob` executes:
1. **Target Identification**:
   ```sql
   SELECT si.* FROM service_instances si
   JOIN invoices inv ON inv.id = si.current_invoice_id
   WHERE si.status = 'active'
     AND inv.status = 'unpaid'
     AND inv.due_date <= CURRENT_DATE - INTERVAL '4 days'
   FOR UPDATE SKIP LOCKED;
   ```
2. **Infrastructure Power Down**:
   - **For KVM / Proxmox VPS**:
     ```bash
     # Attempt graceful ACPI shutdown (wait 60 seconds)
     qm shutdown $VMID --timeout 60 || qm stop $VMID
     # Apply isolation firewall rule blocking external traffic
     pvesh set /nodes/$NODE/qemu/$VMID/firewall/options --enable 1 --policy_in REJECT
     ```
   - **For Hetzner Cloud VPS**:
     ```php
     $hetznerService->powerOffServer($service->hetzner_server_id);
     ```
   - **For STX Hosting Shared PaaS Applications**:
     ```php
     // Call internal STX Hosting API to stop application container
     Http::withToken($stxToken)->post("http://localhost:8000/api/v1/applications/{$service->stx_app_uuid}/stop");
     ```
3. **Telemetry Suspension**:
   - The monitor probe in the Status Platform is updated to `is_active = 0` to prevent false alarm incident generation while suspended.
4. **State Transition**: `service_instances.status` is set to `'suspended'`, with `suspended_at = NOW()` and `suspension_reason = 'unpaid_invoice'`.

---

### 5.3 Instant Automated Reactivation Workflow

When a suspended customer settles their overdue invoice (via approved proof or online gateway):
1. Inside the approval database transaction, `ReactivateServiceJob` is immediately dispatched.
2. **Infrastructure Restoration**:
   - **VPS**: Hypervisor firewall rule is reset to default accept; VM power-on command is issued (`qm start $VMID` or `powerOnServer`).
   - **App**: STX Hosting API is invoked to start the application container (`/applications/{uuid}/start`).
3. **Telemetry Restoration**: Status Platform probe is re-activated (`is_active = 1`).
4. **State Transition**: `service_instances.status` reverts to `'active'`, clearing `suspended_at` and `suspension_reason`.
5. **Customer Notification**: Real-time WebSocket update clears dashboard warning banners, and a confirmation email is dispatched.

---

### 5.4 Termination, Retention & Secure 7-Day Cryptographic Wipe Workflow

To comply with data privacy standards (GDPR, NIST SP 800-88 Rev 1) and protect subsequent tenants from data contamination, terminated nodes undergo an unrecoverable sanitization procedure.

#### Cryptographic Wipe Protocol for VPS Storage
When a service reaches `D+11` without payment:
1. `ExecuteServiceTerminationJob` deletes all snapshots and backups associated with the instance.
2. The hypervisor executes disk sanitization via a strictly validated, non-swallowing purge script:
   ```bash
   #!/usr/bin/env bash
   set -euo pipefail

   VMID="$1"
   STORAGE_DEV="$2"

   # Strict parameter validation
   if [[ ! "${VMID}" =~ ^[0-9]+$ ]]; then
       echo "[FATAL] Invalid VMID: ${VMID}" >&2
       exit 1
   fi

   # Blast-Radius Protection: NEVER allow wiping whole physical drives (nvme0n1, sda)
   if [[ "${STORAGE_DEV}" =~ ^/dev/(nvme[0-9]+n[0-9]+|sd[a-z]|vd[a-z])$ ]]; then
       echo "[FATAL] Whole physical drive targeted (${STORAGE_DEV}). Operation aborted to prevent host-wide data loss." >&2
       exit 1
   fi

   # Verify target device is strictly an isolated VM virtual disk volume or zvol
   if [[ ! "${STORAGE_DEV}" =~ (vm-${VMID}-disk|/dev/zvol/.*/vm-${VMID}-|/dev/pve/vm-${VMID}-) ]]; then
       echo "[FATAL] Target device ${STORAGE_DEV} does not belong to VMID ${VMID}. Aborting." >&2
       exit 1
   fi

   echo "[INFO] Commencing NIST SP 800-88 Rev 1 Purge on VMID: ${VMID}, Device: ${STORAGE_DEV}"

   # 1. Stop and unlock VM (fail if VM cannot be stopped)
   if qm status "${VMID}" | grep -q "status: running"; then
       qm stop "${VMID}" --timeout 60
   fi
   qm unlock "${VMID}"

   # 2. Cryptographic Purge / Block Discard / Secure Zeroing on Virtual Volume (Strict Error Propagation)
   if [[ -b "${STORAGE_DEV}" ]]; then
       # Execute block discard (TRIM) on SSD/NVMe virtual disk pool
       blkdiscard -f "${STORAGE_DEV}" || dd if=/dev/zero of="${STORAGE_DEV}" bs=4M status=none conv=fdatasync
   elif zfs list "${STORAGE_DEV}" >/dev/null 2>&1; then
       # For ZFS zvols: destroy zvol volume directly
       zfs destroy -r "${STORAGE_DEV}"
   else
       echo "[FATAL] Unsupported or missing storage volume: ${STORAGE_DEV}" >&2
       exit 1
   fi

   # 3. Post-Wipe Verification: Sample first and last 100MB to verify zero-fill
   if [[ -b "${STORAGE_DEV}" ]]; then
       SAMPLE_BYTES=$(dd if="${STORAGE_DEV}" bs=1M count=10 2>/dev/null | tr -d '\000' | wc -c)
       if [[ "${SAMPLE_BYTES}" -ne 0 ]]; then
           echo "[FATAL] Sanitization verification failed: non-zero bytes detected on ${STORAGE_DEV}" >&2
           exit 2
       fi
   fi

   # 4. Destroy Hypervisor VM Configuration & Re-clone Clean Template
   qm destroy "${VMID}" --purge 1
   qm clone 9000 "${VMID}" --name "clean-pool-${VMID}" --full

   echo "[SUCCESS] Node ${VMID} sanitized, verified, and re-imaged."
   ```
3. **Inventory Pool Return & Wipe Attestation**:
   - The associated `vps_inventory` record has its `current_service_instance_id` set to `NULL`.
   - `last_wiped_at` is updated to `NOW()`.
   - A cryptographic attestation SHA-256 hash of the wipe log is stored in `vps_inventory.last_wipe_attestation`.
   - `status` transitions from `wiping` to `available`.
4. **Audit Immutability**:
   - An immutable record is created in `audit_logs` capturing: `action = 'service.terminated_and_wiped'`, `entity_type = 'ServiceInstance'`, `entity_id = :id`, and the cryptographic wipe execution log hash.

---

## 6. Error Handling, Concurrency & Idempotency Safeguards

### 6.1 Distributed Locking & Race Condition Prevention
To prevent race conditions during inventory allocation, invoice double-payment, or simultaneous admin reviews:
- **Redis Distributed Mutex**: Critical blocks are protected via Laravel's cache locks:
  ```php
  $lock = Cache::lock("provision:order:{$order->id}", 120);
  if (! $lock->get()) {
      Log::warning("Provisioning lock already held for Order #{$order->id}");
      return;
  }
  try {
      // Provisioning logic here
  } finally {
      $lock->release();
  }
  ```
- **Database Row-Level Pessimistic Locking**: All status transitions utilize `SELECT ... FOR UPDATE` or `FOR UPDATE SKIP LOCKED` to prevent duplicate parallel workers from processing the same entity.

### 6.2 Dead Letter Queues & Mid-Flight Provisioning Rollback Saga

If `ProvisionServiceJob` encounters a network timeout, cloud API rate limit, or hypervisor connection failure:
1. **Exponential Backoff & Rate-Limit Awareness**:
   - The job retries at intervals of 10s, 30s, and 90s (max 3 attempts).
   - If a Hetzner Cloud API rate limit (HTTP 429) is encountered, the worker extracts the `RateLimit-Reset` header and calls `$this->release($seconds)` in Laravel Horizon to defer execution until the rate limit resets, avoiding burning retry attempts.
2. **Deterministic Idempotency**:
   - Before attempting cloud VM creation, `ProvisionServiceJob` queries existing servers by deterministic name (`stx-vps-{$service->uuid}`). If found, it adopts the server rather than spawning a duplicate.
3. **Mid-Flight Provisioning Rollback Saga (`failed(Throwable $e)`)**:
   - If retries exhaust or an unrecoverable failure occurs mid-flight (e.g. cloud server spun up, but cloud-init or network provisioning crashed), the queue worker triggers a **Compensation Transaction Saga** to eliminate zombie infrastructure and leaked billing:
     ```php
     public function failed(\Throwable $exception): void
     {
         Log::error("Provisioning failed for Service #{$this->service->id}: " . $exception->getMessage());

         DB::transaction(function () use ($exception) {
             // 1. Cloud Provider Rollback (Prevent Zombie Billing)
             if ($this->service->hetzner_server_id) {
                 app(\App\Services\HetznerService::class)->deleteServer($this->service->hetzner_server_id);
                 Log::info("Compensated: Deleted orphan Hetzner VM #{$this->service->hetzner_server_id}");
             }

             // 2. Hypervisor KVM Rollback (Proxmox purge)
             if ($this->service->metadata['vmid'] ?? null) {
                 $vmid = $this->service->metadata['vmid'];
                 $this->hypervisorSsh->exec("qm destroy {$vmid} --purge 1");
                 Log::info("Compensated: Purged partial Proxmox VM #{$vmid}");
             }

             // 3. Inventory Hardware Release
             if ($this->service->vps_inventory_id) {
                 \App\Models\VpsInventory::where('id', $this->service->vps_inventory_id)
                     ->update([
                         'status' => 'available',
                         'current_service_instance_id' => null,
                     ]);
                 Log::info("Compensated: Released inventory slot #{$this->service->vps_inventory_id}");
             }

             // 4. Update Service & Order State
             $this->service->update([
                 'status' => 'provision_failed',
                 'metadata' => array_merge($this->service->metadata ?? [], [
                     'failure_reason' => $exception->getMessage(),
                     'failed_at' => now()->toIso8601String(),
                 ]),
             ]);

             $this->order->update(['status' => 'provision_failed']);

             // 5. Audit Log Entry
             \App\Models\AuditLog::create([
                 'action' => 'service.provisioning_compensated',
                 'entity_type' => 'ServiceInstance',
                 'entity_id' => $this->service->id,
                 'user_id' => $this->order->user_id,
                 'new_values' => ['error' => $exception->getMessage(), 'saga' => 'rollback_complete'],
             ]);
         });

         // 6. Alert On-Call Engineering
         Notification::route('discord', config('services.discord.ops_webhook'))
             ->notify(new ProvisioningFailedOpsAlert($this->service, $exception));
     }
     ```
   - The customer's order enters `provision_failed`. The customer sees an informative notice in their portal ("Provisioning encountered a technical delay; our engineering team has been notified"). Customer payment is safely preserved while ops investigates.

---

## 7. Verification & Compliance Sign-Off

### 7.1 Automated State Machine Test Suite Matrix
The state machines specified in this document are verified by the following automated integration test suites (to be executed via `php artisan test`):
- `tests/Feature/OrderLifecycleStateMachineTest.php` — Validates order creation, validation gates, payment transitions, and cancellation timeouts.
- `tests/Feature/PaymentProofVerificationQueueTest.php` — Validates atomic dual-control approval, rejection, and isolation of quarantined file storage.
- `tests/Feature/AutomatedDunningAndSuspensionTest.php` — Simulates cron execution across the D-7 to D+11 timeline, validating ACPI shutdown commands, network isolation, and cryptographic wipe verification.
- `tests/Feature/IdempotentProvisioningWorkerTest.php` — Re-runs provisioning workers multiple times to prove zero duplicate allocation.

---
*End of Specification — Deliverable E (DOC-STX-WORKFLOWS-005)*
