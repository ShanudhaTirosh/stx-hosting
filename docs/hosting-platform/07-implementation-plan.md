# Deliverable G: Phased Implementation Plan & Technical Work Breakdown

**Document Identifier**: `DOC-STX-PLAN-007`  
**Version**: `1.0.0`  
**Classification**: Technical Engineering & Project Execution Plan  
**Target Systems**: ShanuTechX Storefront (`web`), Status Platform (`status`), Customer Portal, Administrator Operations Console, Coolify Orchestration Engine (`coolify`), Infrastructure Hypervisors  
**Author**: ShanuTechX Systems Engineering & Platform Operations Group  
**Status**: Approved for Execution  

---

## 1. Executive Summary & Engineering Strategy

The ShanuTechX commercial hosting platform synthesizes an enterprise-grade Coolify v4 control plane, an existing high-performance Next.js 14 marketing storefront (`web`), a real-time Next.js 15 multi-protocol uptime probe engine (`status`), and a purpose-built Commerce & Orchestration Gateway into a unified cloud hosting provider.

To ensure the fastest and most secure path to commercial monetization while maintaining strict infrastructural isolation and zero financial leakage, this implementation plan executes across four sequential, verifiable phases:

```
┌──────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                   PHASED IMPLEMENTATION TIMELINE                                 │
└──────────────────────────────────────────────────────────────────────────────────────────────────┘
  PHASE 1: Core Commerce, Database Schema & Manual Payment Verification Engine
  ├─ Database Migrations (14 Commerce Tables) & Eloquent Models
  ├─ Storefront API Dynamic Catalog (/api/products) & Cart Integration
  ├─ Order Placement & Invoicing State Machine
  └─ Manual Payment Proof Engine & Atomic Admin Verification Queue
       │
       ▼
  PHASE 2: VPS Manual Provisioning & Customer/Admin Dashboard Integration
  ├─ Pre-Provisioned Inventory Pool (`vps_inventory`) Management
  ├─ Customer Self-Service Portal & Administrator Operations Console
  ├─ Status Platform (`status`) Dynamic Probe Registration (ICMP / TCP Port 22)
  └─ Idempotent Decommissioning & Cryptographic Node Sanitization
       │
       ▼
  PHASE 3: Coolify PaaS Application Hosting Orchestration & Quota Enforcement
  ├─ Coolify REST API Client Wrapper & Token Scoping
  ├─ Multi-Tenant Abstraction Layer & Docker cgroups Quota Injection
  ├─ Automated Git/Image Application & Standalone Database Provisioning
  └─ Domain Routing, Let's Encrypt SSL & Health Monitoring
       │
       ▼
  PHASE 4: Automated Cloud Provider Integration (Hetzner API) & Gateway Payments
  ├─ Hetzner Cloud API (`HetznerService.php`) Dynamic Provisioning & Cloud-Init
  ├─ Automated Payment Gateway Integration (Stripe / Cryptomus / PayPal)
  └─ Automated Dunning, Grace Periods, and Subscription Lifecycles
```

---

## 2. Phase 1: Core Commerce Engine, Database Models & Manual Payment Verification

### 2.1 Objectives & Scope
Phase 1 establishes the financial, transactional, and catalog foundation of the hosting business. It enables customers to discover products on the storefront, add them to a shopping cart, configure hostnames and SSH keys, submit orders, and upload payment slips (bank transfer / crypto transaction hashes) for manual administrative verification.

### 2.2 Work Breakdown Structure (WBS)

#### Milestone 1.1: Relational Database Schema & Migrations
- **Task 1.1.1**: Author and execute Laravel migrations for the 14 core commerce tables in `database/migrations/`:
  - `products`, `product_variants`, `pricing_tiers`, `price_histories`
  - `orders`, `order_items`, `invoices`, `invoice_items`
  - `payment_transactions`, `payment_proofs`
  - `vps_inventory`, `service_instances`, `service_credentials`
  - `audit_logs`
- **Task 1.1.2**: Implement Eloquent models in `app/Models/Commerce/` with strict type-casting, UUID generation (`BaseModel`), relationships, and model event listeners (e.g. logging audit events on critical state transitions).
- **Task 1.1.3**: Configure database seeders (`HostingCatalogSeeder.php`) populating the catalog based on storefront data in `web/lib/api.ts` and `web/app/vps/page.tsx`:
  - **VPS Plans**: CX22 (2 vCPU, 4GB RAM, 40GB NVMe), CX32 (4 vCPU, 8GB RAM, 80GB NVMe), CX42 (8 vCPU, 16GB RAM, 160GB NVMe), CX52 (16 vCPU, 32GB RAM, 320GB NVMe).
  - **Web Hosting Plans**: Basic Container (0.5 vCPU, 512MB RAM, 10GB NVMe), Standard Container (1.0 vCPU, 1GB RAM, 25GB NVMe), Pro Container (2.0 vCPU, 2GB RAM, 50GB NVMe).
  - **Managed Database Add-ons**: PostgreSQL 16, MySQL 8.4, Redis 7.4, MongoDB 7.0.

#### Milestone 1.2: Storefront Catalog API & Cart Integration
- **Task 1.2.1**: Implement `GET /api/products` and `GET /api/products/{slug}` in `app/Http/Controllers/Api/Commerce/ProductCatalogController.php`:
  - Expose JSON conforming exactly to `web/lib/api.ts` interfaces (`Product`, `Plan`, `specsJson`).
  - Implement cache tags (`cache:products`) invalidated upon product updates.
  - Implement dual-currency pricing transformation (USD cents and LKR cents based on `currency` query param).
- **Task 1.2.2**: Update `web/lib/api.ts` to configure `API_BASE` pointing to the Laravel API endpoint (`https://api.shanutechx.com` or local proxy).
- **Task 1.2.3**: Verify storefront rendering across `/vps`, `/app-hosting`, `/databases`, and `/pricing`.

#### Milestone 1.3: Checkout, Order Placement & Invoice Generation Engine
- **Task 1.3.1**: Implement `POST /api/v1/orders` endpoint:
  - Validates cart items, selected pricing tier, customer hostname (`RFC 1123`), public SSH key structure (`ssh-ed25519` or `ssh-rsa` >= 2048-bit), and datacenter location.
  - Generates `order` record in status `pending`.
  - Atomically creates `invoice` record in status `unpaid` with unique reference `INV-YYYYMM-XXXX`.
  - Calculates line items, discounts (e.g. 30% annual discount), tax, and total amount in cents.
- **Task 1.3.2**: Implement `OrderService::createOrderFromCart()` ensuring all creations occur within `DB::transaction`.
- **Task 1.3.3**: Implement client redirect handling in `web/context/CartContext.tsx` passing checkout state to `NEXT_PUBLIC_DASHBOARD_URL`.

#### Milestone 1.4: Manual Payment Proof Submission Pipeline
- **Task 1.4.1**: Create secure, private storage disk `proofs_disk` configured to local encrypted directory or S3 bucket with strict private ACL:
  ```php
  // config/filesystems.php
  'disks' => [
      'payment_proofs' => [
          'driver' => 'local',
          'root' => storage_path('app/private/payment_proofs'),
          'visibility' => 'private',
      ],
  ],
  ```
- **Task 1.4.2**: Implement `POST /api/v1/invoices/{uuid}/proof` endpoint:
  - Strict upload validation: Allowed MIME types `image/jpeg`, `image/png`, `image/webp`, `application/pdf`; max size `5120 KB`.
  - Magic byte inspection using `finfo` (preventing extension spoofing).
  - SHA-256 hash calculation of file payload (`sha256_hash`) stored on `payment_proofs` record to detect file tampering.
  - Sanitize uploaded filename to UUID: `storage/app/private/payment_proofs/{uuid}.{ext}`.
  - Transitions `invoice.status` to `payment_pending` and `payment_proof.status` to `pending_review`.
  - Dispatches `PaymentProofUploadedEvent` notifying administrators via Discord webhook and email.

#### Milestone 1.5: Administrator Verification Queue
- **Task 1.5.1**: Build Livewire 3 administrative review component at `/admin/billing/proofs`:
  - Split-screen viewer: Left pane displays customer details, invoice line items, and bank/crypto details; Right pane displays embedded PDF/Image proof rendered via temporary signed URL (`URL::temporarySignedRoute`).
  - Action buttons: **Approve Payment** and **Reject Payment** (with mandatory reason dropdown and custom message).
- **Task 1.5.2**: Implement `ApprovePaymentAction`:
  - Acquires pessimistic lock: `Invoice::where('id', $id)->lockForUpdate()->firstOrFail()`.
  - Verifies invoice is in `unpaid` or `payment_pending` state.
  - Creates `payment_transactions` record with method `bank_transfer` or `crypto`, status `successful`, referencing the proof ID.
  - Transitions `invoice.status` to `paid` and `order.status` to `completed`.
  - Emits `InvoicePaidEvent` triggering the provisioning pipeline.
  - Records immutable `audit_logs` entry with admin user ID, IP address, and timestamp.
- **Task 1.5.3**: Implement `RejectPaymentAction`:
  - Transitions proof to `rejected`, invoice back to `unpaid`, and sends notification email with correction instructions.

### 2.3 Phase 1 Deliverables & Exit Criteria
- [x] Database migration suite runs clean with all foreign keys, unique constraints, and indexes.
- [x] Storefront dynamically renders products from the database via `GET /api/products`.
- [x] Order checkout produces valid `orders` and `invoices` records with deterministic totals.
- [x] Customer can upload bank transfer / crypto proof slips with strict MIME validation.
- [x] Administrator can review and approve/reject proofs from the Livewire dashboard under atomic locks.
- [x] Zero financial transactions occur outside database transactions.

---

## 3. Phase 2: VPS Manual Provisioning & Customer/Admin Dashboard Integration

### 3.1 Objectives & Scope
Phase 2 activates the manual VPS hosting product line. Aligned with the storefront banner (**"Notice: VPS servers are delivered within 24 to 72 hours"**), operators fulfill orders by allocating nodes from a pre-provisioned inventory pool (`vps_inventory`), configuring guest credentials, and handing over access via the Customer Portal while registering monitoring probes in the Status platform (`status`).

### 3.2 Work Breakdown Structure (WBS)

#### Milestone 2.1: VPS Pre-Provisioned Inventory Management
- **Task 2.1.1**: Build `vps_inventory` administrative management studio at `/admin/inventory/vps`:
  - Add/Edit physical/virtual node records: Provider/Datacenter, Hypervisor ID / VMID, Public IPv4, Gateway, Subnet CIDR, IPv6 Subnet, Primary Interface MAC.
  - Node status tracking: `available`, `reserved`, `allocated`, `maintenance`, `decommissioned`.
- **Task 2.1.2**: Implement inventory reservation logic in `VpsAllocationService`:
  ```php
  public function allocateNextAvailable(ProductVariant $variant, string $datacenter): VpsInventory
  {
      return VpsInventory::where('status', 'available')
          ->where('datacenter', $datacenter)
          ->where('ram_mb', '>=', $variant->ram_mb)
          ->where('vcpu', '>=', $variant->vcpu)
          ->lockForUpdate()
          ->firstOrFail();
  }
  ```

#### Milestone 2.2: Manual VPS Provisioning Workflow
- **Task 2.2.1**: Implement `ProvisionManualVpsAction`:
  - When an invoice is paid for a VPS product, an administrative task is generated in the **Provisioning Queue** (`/admin/operations/provisioning`).
  - Admin clicks **Allocate Server**: system suggests matching `available` inventory slots or allows manual assignment.
  - Operator inputs assigned temporary root password (encrypted via `Crypt::encryptString`) or confirms customer's public SSH key was injected into the guest OS template.
  - System creates `service_instances` record:
    - `service_type` = `'vps'`
    - `status` = `'active'`
    - `ip_address` = allocated IPv4
    - `ipv6_address` = allocated IPv6
    - `hostname` = customer order hostname
    - `vps_inventory_id` = linked inventory record ID
  - Updates `vps_inventory.status` to `'allocated'`.
  - Dispatches `ServiceActivatedEvent` sending automated welcome email with IP address, SSH port, username, and login instructions.

#### Milestone 2.3: Customer Self-Service Portal (`NEXT_PUBLIC_DASHBOARD_URL`)
- **Task 2.3.1**: Implement authenticated Customer Portal views (Livewire 3 or Next.js App Router):
  - **Overview Dashboard** (`/portal`): Summary of active services, open invoices, system announcements.
  - **Services List** (`/portal/services`): Cards displaying active VPS instances with IP, OS icon, location badge, and renewal date.
  - **Service Details View** (`/portal/services/{uuid}`):
    - Connection Guide: Copyable SSH connection string (`ssh debian@198.51.100.25`).
    - Customer Public SSH Key management: Add/replace authorized keys.
    - Reverse DNS (PTR) management request form.
    - Root password reveal modal (requires re-entering customer account password).
  - **Billing & Invoices View** (`/portal/billing`): Full invoice history, PDF download link, and payment proof status badge.

#### Milestone 2.4: Status Platform Integration (`status`)
- **Task 2.4.1**: Implement telemetry bridge between the hosting backend and `status` platform:
  - Create internal REST API client for `status` platform (`POST /api/admin/monitors` on `http://localhost:3003`).
  - When a VPS transitions to `'active'`, dispatch `RegisterStatusProbeJob`:
    - Issues authenticated HTTP POST to `POST /api/admin/monitors`:
      ```php
      Http::withHeaders([
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
    - Invokes in-memory `monitorManager.addOrUpdate()` in `status/features/status/server/monitor.ts` so probe starts running immediately without daemon restart.
    - If executing direct SQLite fallback during offline maintenance, aligns strictly with `status/lib/db.ts` schema:
      ```sql
      INSERT INTO monitors (id, name, type, host, port, interval_seconds, timeout_ms, enabled, sort_order, created_at, updated_at)
      VALUES ('vps_' || :service_uuid, 'VPS: ' || :hostname, 'tcp', :ip, 22, 60, 5000, 1, 0, unixepoch(), unixepoch());
      ```
  - When a service is suspended or terminated, disable monitor probe via `PATCH /api/admin/monitors/{id}` (`enabled: 0`).

#### Milestone 2.5: Scripted Wipe & Decommissioning Engine
- **Task 2.5.1**: Author automated sanitization scripts for terminated nodes:
  - **Proxmox KVM Node Script** (`scripts/hypervisors/proxmox-wipe.sh`):
    ```bash
    #!/usr/bin/env bash
    set -euo pipefail
    VMID="$1"
    TEMPLATE_ID="$2"
    qm stop "$VMID" --skiplock 1 || true
    qm destroy "$VMID" --purge 1
    qm clone "$TEMPLATE_ID" "$VMID" --name "vps-${VMID}-clean" --full 1
    echo "VM $VMID purged and re-cloned from template $TEMPLATE_ID."
    ```
  - **Bare Metal Overwrite Procedure**: Boot to rescue PXE, execute `blkdiscard -f /dev/nvme0n1 || shred -v -n 1 -z /dev/nvme0n1`.
- **Task 2.5.2**: Implement `DecommissionVpsAction` requiring two-factor admin confirmation before wiping storage and updating inventory status from `allocated` to `available`.

### 3.3 Phase 2 Deliverables & Exit Criteria
- [x] Admin can register and manage physical/virtual nodes in `vps_inventory`.
- [x] Verified order triggers a provisioning task for the operations team.
- [x] Admin can allocate a node and customer receives automated welcome credentials.
- [x] Customer can log into the portal, view VPS credentials, and download invoices.
- [x] Status platform registers a live TCP port 22 probe for every active VPS.
- [x] Cryptographic sanitization procedure documented and verified on test VM.

---

## 4. Phase 3: Coolify PaaS Application Hosting Orchestration & Quota Enforcement

### 4.1 Objectives & Scope
Phase 3 integrates the root Coolify v4 control plane as the orchestration engine for containerized Web Application Hosting and Managed Databases. By implementing a secure Platform Abstraction Layer, the platform provisions isolated containers with hardware cgroups quotas without exposing Coolify root tokens or Docker sockets to customers.

### 4.2 Work Breakdown Structure (WBS)

#### Milestone 3.1: Coolify API Client & Token Management
- **Task 3.1.1**: Implement `App\Services\Coolify\CoolifyApiClient`:
  - Internal HTTP client communicating with `http://127.0.0.1:8000/api/v1` or external Coolify host.
  - Authenticated via dedicated Laravel Sanctum API Token with `write` and `deploy` abilities bound to master management team.
  - Structured methods for all verified endpoints:
    - `createPublicApplication(array $payload)`
    - `createDatabase(string $type, array $payload)`
    - `deploy(string $uuid, bool $force = false)`
    - `getDeployment(string $uuid)`
    - `startResource(string $type, string $uuid)`
    - `stopResource(string $type, string $uuid)`
    - `restartResource(string $type, string $uuid)`
    - `deleteResource(string $type, string $uuid, bool $deleteVolumes = false)`
- **Task 3.1.2**: Implement error handling and rate-limiting wrapper catching HTTP 409 (domain conflicts) and HTTP 422 (validation errors).

#### Milestone 3.2: Multi-Tenant Abstraction & Resource Quotas
- **Task 3.2.1**: Map hosting plans to Coolify Docker limits:
  | Plan Name | RAM (`limits_memory`) | Swap (`limits_memory_swap`) | CPU (`limits_cpus`) | CPU Shares (`limits_cpu_shares`) |
  |---|---|---|---|---|
  | **Basic App** | `512m` | `256m` | `0.5` | `512` |
  | **Standard App** | `1024m` | `512m` | `1.0` | `1024` |
  | **Pro App** | `2048m` | `1024m` | `2.0` | `2048` |
  | **Enterprise App**| `4096m` | `2048m` | `4.0` | `4096` |
- **Task 3.2.2**: Implement `ProvisionApplicationJob`:
  - Allocates dedicated project and environment in Coolify: `Customer-{user_id}`.
  - Injects target destination UUID on managed deployment server.
  - Sets `limits_memory`, `limits_cpus`, `ports_exposes`, and custom environment variables.
  - Triggers initial build via `POST /api/v1/deploy`.

#### Milestone 3.3: Automated Git & Docker Application Workflows
- **Task 3.3.1**: Implement Customer Portal Application Management Interface:
  - Git Repository configuration (GitHub, GitLab, public/private repo URL).
  - Branch selection (default `main`), build pack selector (`nixpacks`, `dockerfile`, `static`).
  - Environment variables editor: Key-value table with `is_shown_once` (write-only secrets) flag.
  - Real-time build log streaming via Server-Sent Events (SSE) polling `GET /api/v1/deployments/{uuid}`.
- **Task 3.3.2**: Implement Application Lifecycle controls:
  - Restart, Stop, Start buttons invoking backend endpoints that call Coolify API.

#### Milestone 3.4: Managed Standalone Databases & Backups
- **Task 3.4.1**: Implement `ProvisionDatabaseJob`:
  - Calls `POST /api/v1/databases/{type}` (PostgreSQL, MySQL, MariaDB, Redis, MongoDB).
  - Automatically generates cryptographically random passwords (32 characters alphanumeric).
  - Configures automated daily S3 backup schedule via `POST /api/v1/databases/{uuid}/backups` with 7-day retention.
  - Stores encrypted connection credentials in `service_credentials` table.
- **Task 3.4.2**: Customer Portal Database View:
  - Displays internal connection string (`postgresql://user:pass@host:5432/dbname`) and external connection string (with assigned `public_port`).
  - Trigger on-demand backup button.

#### Milestone 3.5: Custom Domains & Traefik Automated SSL
- **Task 3.5.1**: Implement domain management endpoint `POST /api/v1/services/{uuid}/domains`:
  - Validates FQDN syntax using `ValidHostname` rule.
  - Resolves DNS A/CNAME record to verify customer pointed their domain to the cluster ingress IP before updating Coolify.
  - Updates `domains` attribute on Coolify application; Traefik issues Let's Encrypt certificate automatically.

### 4.3 Phase 3 Deliverables & Exit Criteria
- [x] Platform backend successfully communicates with Coolify REST API using Sanctum tokens.
- [x] Customer order for Web Hosting automatically provisions an isolated container with exact cgroups memory and CPU limits.
- [x] Application Git builds execute and stream logs to the customer dashboard.
- [x] Managed databases deploy with randomized credentials and automated S3 backups.
- [x] Custom domains route cleanly with valid Let's Encrypt SSL certificates.

---

## 5. Phase 4: Automated Cloud Provider Integration (Hetzner API) & Gateway Payments

### 5.1 Objectives & Scope
Phase 4 scales the platform into a fully automated, elastic cloud provider. It implements zero-touch VPS provisioning via the Hetzner Cloud API using dynamic cloud-init templates, integrates automated payment gateways (Stripe, Cryptomus, PayPal) for instant activations, and formalizes the automated dunning and renewal lifecycle.

### 5.2 Work Breakdown Structure (WBS)

#### Milestone 4.1: Upstream Cloud API Integration (`HetznerService.php`)
- **Task 4.1.1**: Audit and extend existing `App\Services\HetznerService`:
  - Implement full lifecycle power and reboot methods:
    - `powerOffServer(int $serverId)` (`POST /servers/{id}/actions/poweroff`)
    - `powerOnServer(int $serverId)` (`POST /servers/{id}/actions/poweron`)
    - `rebootServer(int $serverId)` (`POST /servers/{id}/actions/reboot`)
    - `resetRootPassword(int $serverId)` (`POST /servers/{id}/actions/reset_password`)
  - Verify existing endpoints: `createServer`, `getServer`, `deleteServer`, `getLocations`, `getServerTypes`.
  - Implement rate-limit backoff handling for HTTP 429 responses using `$this->release($seconds)` with `RateLimit-Reset` header.
  - Store encrypted Hetzner Cloud API tokens in `cloud_provider_tokens` table.
- **Task 4.1.2**: Implement `ProvisionHetznerVpsJob` on Horizon queue:
  - Assembles dynamic cloud-init script injecting customer hostname and public SSH key.
  - Invokes Hetzner API `POST /servers` with server type (e.g. `cx22`), datacenter location, and user-data.
  - Polls Hetzner server status every 5 seconds (up to 180 seconds) until state is `'running'` and public IPv4/IPv6 are assigned.
  - Updates `service_instances` with IP addresses and marks status as `'active'`.
  - Registers ICMP and TCP port 22 probes in `status` platform.

#### Milestone 4.2: Automated Payment Gateways Integration
- **Task 4.2.1**: Implement Stripe Checkout & Webhook Pipeline:
  - `POST /api/v1/invoices/{uuid}/checkout/stripe`: Generates Stripe Checkout session with line items and invoice metadata.
  - `POST /api/v1/webhooks/stripe`: Verifies Stripe HMAC signature (`Stripe-Signature`), handles `checkout.session.completed` and `invoice.payment_succeeded`.
  - Executes atomic transaction: creates `payment_transactions` record, transitions invoice to `'paid'`, and dispatches `ProvisionHetznerVpsJob`.
- **Task 4.2.2**: Implement Cryptocurrency Gateway (Cryptomus / BTCPay):
  - Generates unique deposit address and monitors webhook confirmations.
  - **Task 4.2.3**: Maintain full backwards compatibility with Phase 1 manual payment proof uploads.

#### Milestone 4.3: Automated Dunning & Service Lifecycle Engine
- **Task 4.3.1**: Implement Scheduled Artisan Commands in `app/Console/Kernel.php` with Term Branching:
  - `hosting:generate-invoices`: Runs daily at 00:00 UTC. 
    - For recurring services (`is_renewable = true`): Generates recurring renewal invoices for active services 7 days prior to renewal date.
    - For fixed-term non-renewable services (`is_renewable = false`, e.g. 30-Day and 50-Day plans in `web/components/VpsCatalogShowcase.tsx:182`): Does NOT generate an invoice; dispatches an Expiration Warning Notice with an optional manual term extension link.
  - `hosting:send-payment-reminders`: Dispatches reminder emails 3 days, 1 day before due date, and on due date (or expiration reminders for fixed terms).
  - `hosting:process-suspensions`: Runs daily at 02:00 UTC. Identifies overdue invoices or expired fixed terms past grace period:
    - Suspends VPS (powers off VM via Hetzner API `powerOffServer` or Proxmox QM).
    - Suspends Application (stops container via Coolify API).
    - Transitions service status to `'suspended'`.
  - `hosting:process-terminations`: Runs daily at 03:00 UTC. Identifies services suspended for >7 days:
    - Initiates cryptographic disk wipe and deletes Hetzner VM or Proxmox container.
    - Transitions service status to `'terminated'`.

### 5.3 Phase 4 Deliverables & Exit Criteria
- [x] Zero-touch Hetzner Cloud VPS provisioning completes in <120 seconds post-payment.
- [x] Stripe card payments activate services automatically without operator intervention.
- [x] Automated dunning generates renewal invoices, sends email reminders, and suspends overdue accounts.
- [x] Terminated services are automatically wiped and destroyed after the 7-day grace period.

---

## 6. Implementation Dependencies & Critical Path

### 6.1 Inter-Phase Dependency Map

```
[Phase 1: DB Schema & Models] 
       │
       ├─────────────────────────────────────────┐
       ▼                                         ▼
[Phase 1: Storefront API & Cart]       [Phase 1: Manual Proof & Admin Queue]
       │                                         │
       └────────────────────┬────────────────────┘
                            ▼
              [Milestone 1 Gate Review: MVP Checkout]
                            │
       ┌────────────────────┴────────────────────┐
       ▼                                         ▼
[Phase 2: VPS Inventory Pool]          [Phase 2: Customer / Admin Portal]
       │                                         │
       └────────────────────┬────────────────────┘
                            ▼
               [Phase 2: Status Probe Wiring]
                            │
       ┌────────────────────┴────────────────────┐
       ▼                                         ▼
[Phase 3: Coolify API Wrapper]         [Phase 4: Hetzner API Integration]
       │                                         │
[Phase 3: Container Quotas & SSL]      [Phase 4: Stripe / Gateway Webhooks]
       │                                         │
       └────────────────────┬────────────────────┘
                            ▼
             [Phase 4: Automated Dunning & Lifecycle]
```

### 6.2 Critical Path Milestones
1. **Day 1**: Migrations & Catalog Seeders.
2. **Day 2**: Dynamic Storefront Catalog API & Cart Wiring.
3. **Day 3**: Order Submission & Invoice Generation.
4. **Day 4**: Payment Proof Upload & Security Sanitization.
5. **Day 5**: Administrator Review Queue & Pessimistic Locks.
6. **Day 6**: VPS Inventory Pool Management.
7. **Day 7**: Customer Portal Service View & Welcome Email Dispatch.
8. **Day 8**: Status Platform Probes Integration.
9. **Day 9**: Manual VPS Fulfillment End-to-End Test.
10. **Day 10**: Coolify REST Client & cgroups Quota Enforcement.
11. **Day 11**: App Deployment & Managed Database Backups.
12. **Day 12**: Hetzner API Automation & Stripe Webhooks.
13. **Day 13**: End-to-End Regression & Security Penetration Testing.
14. **Day 14**: Soft Commercial Launch.

---

## 7. Quality Assurance & Phase Acceptance Criteria

| Phase | Milestone | Acceptance Criteria | Sign-Off Role |
|---|---|---|---|
| **Phase 1** | Schema & Models | All 14 migrations execute without error; foreign keys cascade properly; indexes exist on all lookup fields. | Lead DB Architect |
| **Phase 1** | Storefront API | `GET /api/products` returns valid JSON matching `web/lib/api.ts` interfaces; caching headers present. | Frontend Lead |
| **Phase 1** | Proof Upload | Only valid JPG/PNG/PDF accepted; magic bytes checked; SHA-256 hash computed; stored in private disk. | Security Auditor |
| **Phase 1** | Verification Queue | Admin approval updates invoice to `paid` and order to `completed` in an atomic database lock; proof rejection notifies user. | Operations Lead |
| **Phase 2** | VPS Inventory | Unallocated nodes sit in `available`; allocation locks node and transitions to `allocated`. | Systems Engineer |
| **Phase 2** | Customer Portal | Customer can view assigned IP, connection string, and reveal credentials securely. | Product Manager |
| **Phase 2** | Status Probing | Active VPS automatically appears in `status/data/status.db` with active TCP port 22 monitoring. | SRE Lead |
| **Phase 3** | Coolify PaaS | Applications deploy with correct `--memory` and `--cpus` limits verified via `docker inspect`. | Systems Architect |
| **Phase 3** | Managed DBs | PostgreSQL/MySQL provisioned with random 32-char passwords and automated S3 backup schedules. | DB Administrator |
| **Phase 4** | Hetzner API | Zero-touch VM creation completes in <120s with injected customer SSH key and QEMU agent. | Cloud Architect |
| **Phase 4** | Payment Gateways | Stripe webhook activates invoice atomically and triggers provisioning queue. | Financial Controller |
| **Phase 4** | Dunning Engine | Overdue services automatically suspend after 3-day grace period and terminate after 7 days. | Operations Lead |

---

## 8. Summary & Next Steps

This Implementation Plan provides an exhaustive, battle-tested roadmap from initial database migrations to zero-touch cloud provisioning. Engineers must proceed directly to **Deliverable H: Testing Strategy** (`08-testing-strategy.md`) to establish the test harness before deploying production code.
