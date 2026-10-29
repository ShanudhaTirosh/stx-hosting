# Deliverable J: Prioritized 14-Day Roadmap to First Paid Customer Launch

**Document Identifier**: `DOC-STX-ROADMAP-010`  
**Version**: `1.0.0`  
**Classification**: Strategic Project Execution & Commercial Launch Roadmap  
**Target Systems**: ShanuTechX Storefront (`web`), Status Platform (`status`), Customer Portal, Administrator Operations Console, Coolify Orchestration Engine (`coolify`), Infrastructure Hypervisors  
**Author**: ShanuTechX Executive Leadership & Platform Engineering Group  
**Status**: Approved for Commercial Execution  

---

## 1. Executive Strategic Vision & Launch Mandate

The primary commercial objective of the ShanuTechX Cloud Hosting Platform is to achieve **first paid customer acquisition and successful service onboarding within 14 calendar days**.

### 1.1 The Pragmatic Launch Strategy
Rather than waiting weeks for third-party merchant processor underwriting (e.g. Stripe, PayPal, or Merchant of Record accounts, which frequently subject new hosting startups to rolling reserves, underwriting delays, or outright rejections), the platform executes a **High-Velocity Two-Track Launch Strategy**:

```
                       14-DAY COMMERCIAL LAUNCH STRATEGY
┌─────────────────────────────────────────────────────────────────────────────────┐
│ REVENUE ENGINE: Immediate Cash Flow & Zero Underwriting Delays                  │
│ • Manual Bank Wire, Instant Bank Transfer (LKR/USD), and Cryptocurrency Proofs  │
│ • Atomic Administrative Review Queue with Side-by-Side Proof Viewer             │
│ • 100% Chargeback Immunity on Day 1                                             │
└───────────────────────────────────────┬─────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│ INFRASTRUCTURE: Pre-Provisioned Inventory & SLA Alignment                       │
│ • Pre-allocated pool of dedicated hardware nodes and cloud VMs (vps_inventory) │
│ • Aligned with storefront notice banner: "VPS delivered within 24 to 72 hours"  │
│ • Zero dependency on upstream cloud API quota approvals during launch week      │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. 14-Day Detailed Execution Schedule

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                    14-DAY EXECUTION TIMELINE                                     │
├─────────────────┬─────────────────┬─────────────────┬─────────────────┬──────────────────────────┤
│    DAYS 1 - 3   │    DAYS 4 - 7   │   DAYS 8 - 10   │   DAYS 11 - 12  │       DAYS 13 - 14       │
├─────────────────┼─────────────────┼─────────────────┼─────────────────┼──────────────────────────┤
│ Database Schema │ Manual Proof &  │ Customer Portal │ Security & E2E  │ Staging Canary, Staff    │
│ & Dynamic Store │ Admin Review    │ & Telemetry     │ Hardening       │ Training & First Live    │
│ Wiring          │ Verification    │ Integration     │ Penetration     │ Customer Onboarding      │
└─────────────────┴─────────────────┴─────────────────┴─────────────────┴──────────────────────────┘
```

---

### Sprint Block 1: Database Schema, Core Models & Storefront Wiring (Days 1–3)

#### Day 1: Relational Database Migrations & Eloquent Foundation
- **Milestone 1.1**: Execute Laravel migrations for all 14 core commerce tables in `database/migrations/`:
  - `products`, `product_variants`, `pricing_tiers`, `price_histories`
  - `orders`, `order_items`, `invoices`, `invoice_items`
  - `payment_transactions`, `payment_proofs`
  - `vps_inventory`, `service_instances`, `service_credentials`, `audit_logs`
- **Milestone 1.2**: Generate Eloquent models in `app/Models/Commerce/` with CUID2 UUID auto-generation, strict type casting, and relationship definitions.
- **Milestone 1.3**: Populate `HostingCatalogSeeder.php` with initial production product variants matching `web/app/vps/page.tsx` (CX22, CX32, CX42, CX52) and web hosting tiers.
- **Verification Gate Day 1**: `php artisan migrate:fresh --seed` runs completely clean; all foreign keys, indexes, and unique constraints verified.

#### Day 2: Storefront Dynamic API Integration (`GET /api/products`)
- **Milestone 2.1**: Implement `app/Http/Controllers/Api/Commerce/ProductCatalogController.php`:
  - Returns JSON matching `web/lib/api.ts` interfaces (`Product`, `Plan`, `specsJson`).
  - Supports dual-currency pricing transformation: USD cents (`price_cents`) and LKR cents based on `?currency=` parameter.
  - Implements caching tag `cache:products` with 1-hour TTL.
- **Milestone 2.2**: Update storefront `web/lib/api.ts` to query the live API endpoint.
- **Milestone 2.3**: Verify storefront rendering across `/vps`, `/app-hosting`, `/databases`, and `/pricing`.
- **Verification Gate Day 2**: Visiting `http://localhost:3000/vps` dynamically renders database-backed pricing cards with accurate RAM, CPU, and NVMe specs.

#### Day 3: Shopping Cart Persistence, Order Placement & Invoice Generation
- **Milestone 3.1**: Implement `POST /api/v1/orders` endpoint:
  - Validates cart contents, pricing tier UUID, customer hostname (`RFC 1123`), and public SSH key format (`ssh-ed25519` or `ssh-rsa` >= 2048-bit).
  - Creates `orders` record in status `pending`.
  - Atomically creates `invoices` record in status `unpaid` with unique reference `INV-YYYYMM-XXXX`.
  - Calculates line items, discounts, and total cents.
- **Milestone 3.2**: Wire storefront checkout button in `web/components/Pricing.tsx` to redirect to portal checkout with populated parameters.
- **Verification Gate Day 3**: Placing a test order creates matching `order` and `invoice` records in PostgreSQL.

---

### Sprint Block 2: Manual Payment Verification Queue & Inventory Pool (Days 4–7)

#### Day 4: Payment Proof Upload Engine & Tamper-Evident Storage
- **Milestone 4.1**: Configure private storage disk `proofs_disk` at `storage/app/private/payment_proofs/`.
- **Milestone 4.2**: Implement `POST /api/v1/invoices/{uuid}/proof`:
  - Enforces strict file validation: Allowed MIME types `image/jpeg`, `image/png`, `image/webp`, `application/pdf`; max size `5120 KB`.
  - Uses `finfo` magic byte inspection to reject disguised scripts or webshells.
  - Computes SHA-256 hash (`sha256_hash`) stored on `payment_proofs` record to detect duplicated or tampered slips.
  - Generates secure random UUID filename.
  - Transitions `invoice.status` to `'payment_pending'` and `payment_proof.status` to `'pending_review'`.
- **Verification Gate Day 4**: Uploading a valid JPEG receipt stores the file in the private disk and sets status to `payment_pending`; uploading a disguised PHP script is rejected with HTTP 422.

#### Day 5: Administrative Verification Queue Livewire Interface
- **Milestone 5.1**: Build Livewire 3 administrative review studio at `/admin/billing/proofs`:
  - Split-screen layout: Left pane displays customer info, invoice items, and payment method details; Right pane renders proof slip via temporary signed URL (`URL::temporarySignedRoute` with 15-minute TTL).
  - Action buttons: **Approve Payment** and **Reject Payment** (with required reason dropdown).
- **Milestone 5.2**: Implement `ApprovePaymentAction`:
  - Acquires pessimistic lock: `Invoice::where('id', $id)->lockForUpdate()->firstOrFail()`.
  - Creates `payment_transactions` record.
  - Transitions invoice to `'paid'` and order to `'completed'`.
  - Dispatches `InvoicePaidEvent` triggering provisioning.
  - Writes immutable audit log entry.
- **Verification Gate Day 5**: Admin clicking "Approve" atomically updates invoice to `paid` and order to `completed` under database lock.

#### Day 6: Pre-Provisioned VPS Inventory Pool Management
- **Milestone 6.1**: Build `vps_inventory` administrative management studio at `/admin/inventory/vps`:
  - CRUD for physical/virtual node inventory: Datacenter location, Hypervisor ID, VMID, Public IPv4, Gateway, Subnet CIDR, MAC address.
  - State tracking: `available`, `reserved`, `allocated`, `maintenance`, `decommissioned`.
- **Milestone 6.2**: Seed initial inventory pool with at least 5 available nodes across Falkenstein (`fsn1-de`) and Nuremberg (`nbg1-de`).
- **Milestone 6.3**: Implement `VpsAllocationService` with `lockForUpdate()` query to safely reserve matching nodes.
- **Verification Gate Day 6**: Admin can view, add, and filter inventory nodes; allocation query locks and reserves nodes without concurrency races.

#### Day 7: Operational Provisioning Task Queue & Credential Vault
- **Milestone 7.1**: Build Operational Provisioning Queue at `/admin/operations/provisioning`:
  - Displays paid orders awaiting server allocation.
  - Operator selects matching `available` node from inventory pool.
  - Operator inputs assigned temporary root credentials (encrypted via `Crypt::encryptString`) or confirms customer's public SSH key was injected.
- **Milestone 7.2**: Implement `ConfirmProvisioningAction`:
  - Creates `service_instances` record with status `'active'`.
  - Updates `vps_inventory.status` to `'allocated'`.
  - Dispatches `ServiceActivatedEvent` sending automated welcome email with IP address, SSH port, and connection guide.
- **Verification Gate Day 7**: Completing the provisioning action updates the inventory node to `allocated` and sends welcome email to the customer.

---

### Sprint Block 3: Customer Portal & Observability Integration (Days 8–10)

#### Day 8: Customer Self-Service Portal (`/portal`)
- **Milestone 8.1**: Implement authenticated Customer Portal views (Livewire 3):
  - **Overview Dashboard** (`/portal`): Active services count, pending invoices, recent orders.
  - **Services List** (`/portal/services`): Cards displaying active VPS instances with IP, OS, location badge, and renewal date.
  - **Service Details View** (`/portal/services/{uuid}`):
    - Copyable SSH connection string (`ssh debian@198.51.100.44`).
    - Customer Public SSH Key management.
    - Secure password reveal modal (requires re-entering account password).
  - **Billing & Invoices** (`/portal/billing`): Complete invoice history and payment proof status badges.
- **Verification Gate Day 8**: Customer can log into the portal, view their allocated VPS, copy the SSH string, and view their paid invoice.

#### Day 9: Status Platform Telemetry Bridge (`status`)
- **Milestone 9.1**: Build telemetry bridge connecting hosting backend to `status/data/status.db`.
- **Milestone 9.2**: Implement `RegisterStatusProbeJob`:
  - When a VPS transitions to `'active'`, injects monitor into `monitors` table in `status.db`:
    - Target: Allocated IPv4.
    - Type: `tcp` on port 22, interval 60s, timeout 5s.
- **Milestone 9.3**: Implement monitor deactivation when service is suspended or terminated.
- **Verification Gate Day 9**: Newly provisioned VPS appears on public status page `http://localhost:3003` with green operational status.

#### Day 10: Operational Sanitization Engine & Hypervisor Automation
- **Milestone 10.1**: Finalize and test automated sanitization scripts:
  - Proxmox KVM node script (`scripts/hypervisors/proxmox-wipe.sh`).
  - Bare-metal disk shred procedure (`blkdiscard || shred`).
- **Milestone 10.2**: Implement `DecommissionVpsAction` requiring two-factor admin confirmation before wiping storage and updating inventory status from `allocated` to `available`.
- **Verification Gate Day 10**: Executing wipe script on a test VM purges customer data, restores fresh template, and re-enters inventory pool.

---

### Sprint Block 4: Security Hardening & Full-Stack Testing (Days 11–12)

#### Day 11: Multi-Tenant Boundary & Security Penetration Testing
- **Milestone 11.1**: Execute automated security test suite:
  - Verify Tenant A cannot view or download Tenant B's invoices or service details (assert HTTP 403 / 404).
  - Verify customer SSH keys are never written to host authorized_keys, only to dedicated guest VM.
  - Test malicious file uploads: Verify extension spoofing (e.g. `exploit.php.jpg`) is rejected by magic byte inspection.
  - Verify signed proof URLs expire and reject tampered signatures.
- **Milestone 11.2**: Implement rate-limiting middleware on auth endpoints (5 attempts/min) and proof upload endpoints (10 uploads/hour).
- **Verification Gate Day 11**: All security tests in `tests/Security/` pass with zero vulnerabilities.

#### Day 12: Playwright End-to-End Browser Suite Execution
- **Milestone 12.1**: Execute complete browser test suite (`HostingOrderToActivationE2ETest`):
  - Catalog browsing ➔ Order placement ➔ Invoice creation ➔ Proof upload ➔ Admin review queue ➔ Payment approval ➔ Inventory allocation ➔ Customer portal verification.
- **Milestone 12.2**: Inspect generated Playwright screenshot artifacts in `tests/Browser/Screenshots/`.
- **Milestone 12.3**: Resolve all visual UI glitches, mobile responsiveness defects, or broken links.
- **Verification Gate Day 12**: 100% of Pest unit, integration, security, and Playwright E2E browser tests pass without warnings or failures.

---

### Sprint Block 5: Soft Launch, Staff Training & First Customer Onboarding (Days 13–14)

#### Day 13: Staging Canary Deployment & Staff Operational Drills
- **Milestone 13.1**: Deploy full multi-service stack using `docker-compose.prod.yml` to production staging host.
- **Milestone 13.2**: Configure Traefik wildcard Let's Encrypt certificates for `*.shanutechx.com`.
- **Milestone 13.3**: Conduct staff operational drills:
  - Drill 1: Execute Runbook 1 (Manual VPS Provisioning & Customer Onboarding).
  - Drill 2: Execute Runbook 3 (Tampered Payment Slip Detection & Handling).
  - Drill 3: Execute Database S3 Backup & Test Cold Restore (`scripts/backup-db.sh`).
- **Verification Gate Day 13**: Operations team completes end-to-end simulated order fulfillment in < 15 minutes.

#### Day 14: Soft Commercial Launch & First Paid Customer Onboarding
- **Milestone 14.1**: Announce platform availability to private beta waitlist / VIP community.
- **Milestone 14.2**: Open storefront checkout for live customer orders.
- **Milestone 14.3**: First customer places order for CX22 Cloud VPS, uploads bank transfer / crypto payment receipt.
- **Milestone 14.4**: Administrator verifies payment receipt in `/admin/billing/proofs` and clicks **Approve**.
- **Milestone 14.5**: Operations allocates first live node from `vps_inventory`; customer receives automated welcome credentials.
- **Milestone 14.6**: Customer connects via SSH; Status Platform verifies live TCP probe!
- **Verification Gate Day 14**: **FIRST PAID HOSTING CUSTOMER ACTIVATED WITH ZERO INJURIES OR INCIDENTS.**

---

## 3. Critical Path Analysis & Milestone Dependencies

```
[Day 1: DB Schema & Models]
       │
       ▼
[Day 2: Storefront API GET /api/products]
       │
       ▼
[Day 3: Checkout, Orders & Invoices]
       │
       ▼
[Day 4: Proof Upload & File Validation]
       │
       ▼
[Day 5: Admin Review Queue Livewire]
       │
       ▼
[Day 6: VPS Inventory Pool Setup]
       │
       ▼
[Day 7: Provisioning Task Dispatch]
       │
       ▼
[Day 8: Customer Portal Dashboard]
       │
       ▼
[Day 9: Status Platform Probe Bridge]
       │
       ▼
[Day 10: Node Wipe & Decommissioning Engine]
       │
       ▼
[Day 11: Security & Isolation Penetration Tests]
       │
       ▼
[Day 12: Playwright E2E Browser Suite Pass]
       │
       ▼
[Day 13: Production Deployment & Drills]
       │
       ▼
[Day 14: FIRST PAID CUSTOMER LAUNCH]
```

### Critical Path Bottlenecks & Zero-Buffer Items:
1. **Day 4 & 5 (Proof Upload & Admin Approval)**: The entire revenue model depends on this workflow. Any delay here delays customer activation.
2. **Day 6 (Inventory Pool Capacity)**: Pre-provisioned hardware nodes must be online, tested, and inventoried prior to launch day.
3. **Day 11 (Security Testing)**: Must strictly pass before opening public checkout.

---

## 4. Risk Assessment & Mitigation Matrix

| Risk Factor | Impact | Likelihood | Mitigation Protocol |
|---|---|---|---|
| **Inventory Pool Depletion** | High | Medium | Set automated alert in Discord when `available` node count < 2. Maintain standby Hetzner Cloud account for rapid emergency manual node provisioning. |
| **Fraudulent Payment Slips** | High | Medium | Enforce SHA-256 hash deduplication; mandate bank transaction ID matching; inspect image metadata before approving proof. |
| **Customer Port Probing Outage** | Medium | Low | Status platform probes configured with 60s intervals and 2-failure retry threshold to prevent false alarm flapping. |
| **Cross-Tenant Data Exposure** | Critical | Low | Strict policy checks on every API route; comprehensive `TenantIsolationTest` in CI pipeline blocks all unauthorized reads/writes. |
| **DNS / SSL Certificate Delays** | Medium | Low | Pre-issue wildcard Let's Encrypt certificates for `*.shanutechx.com` on edge Traefik proxy on Day 13. |

---

## 5. Resource Allocation & Operational Staffing

| Role | Primary Responsibilities | Daily Commitment |
|---|---|---|
| **Lead Backend Engineer** | Database migrations, Commerce API, Livewire review queue, Coolify integration. | 100% (Days 1–14) |
| **Frontend Engineer** | Storefront API wiring, Cart context, Customer Portal views, Playwright tests. | 100% (Days 1–14) |
| **SRE / Systems Engineer** | Docker Compose deployment, Proxmox hypervisor setup, Inventory pool, Status probes. | 100% (Days 4–14) |
| **Operations Reviewer** | Manual payment proof verification, server allocation, customer onboarding. | On-call (Days 5–14) |

---

## 6. Post-Launch Horizon (Days 15–30: Scale & Automation)

Immediately following the Day 14 commercial launch, the platform transitions into Phase 2 automation:
- **Days 15–20**: Implement automated Hetzner Cloud API integration (`HetznerService.php`) with dynamic cloud-init templates for zero-touch VPS delivery.
- **Days 21–25**: Integrate automated payment gateways (Stripe Checkout & Cryptomus Webhooks) for instant, automated activations.
- **Days 26–30**: Roll out Coolify PaaS containerized Web Application Hosting and Managed Databases with automated cgroups quota enforcement.

---

## 7. Summary & Sign-Off

This 14-Day Prioritized Roadmap provides an aggressive, realistic, and highly disciplined plan to launch the ShanuTechX Cloud Hosting Platform. By focusing on manual payment verification and pre-provisioned inventory in Sprint Blocks 1–3, the business eliminates external dependencies and guarantees revenue generation on Day 14.
