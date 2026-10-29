# Deliverable C: Target Architecture & Product Specifications
**Document Reference**: `docs/hosting-platform/03-target-architecture.md`  
**Requirement Mapping**: R3 (Hosting Products Specification and Security Architecture)  
**System Ecosystem**: ShanuTechX Commercial Hosting Platform (`web`, `status`, `webmail`, `coolify`)  
**Status**: Production Architecture Baseline  
**Author**: ShanuTechX Platform Architecture & Systems Engineering  
**Revision**: 1.0.0 (October 2026)  

---

## 1. Executive Summary & Architectural Principles

The ShanuTechX Commercial Hosting Platform transforms an open-source Coolify control plane and a suite of dedicated micro-frontends into an integrated, enterprise-ready Cloud Hosting and Platform-as-a-Service (PaaS) business. By augmenting Coolify's orchestration engine with commercial billing, pre-provisioned bare-metal/hypervisor inventory management, upstream cloud provider APIs, and automated telemetry probes, the platform delivers high-margin Virtual Private Servers (VPS), containerized application hosting, and managed databases.

### 1.1 Core Architecture Tenets

1. **Strict Separation of Planes (Management vs. Tenant Access)**:
   - Under no circumstances shall tenant workloads, customer SSH sessions, or customer container runtimes share direct kernel privileges, Docker sockets, or administrative network interfaces with the Coolify control plane or hypervisor management APIs.
   - The management plane operates inside isolated internal subnets and localhost bindings; tenant access terminates strictly at public network bridges and customer guest boundaries.

2. **Pragmatic Two-Phase Infrastructure Evolution**:
   - **Phase 1 (Immediate Commercial Launch)**: Leverages a manual allocation model over a pre-provisioned inventory pool (`vps_inventory`) of dedicated hardware nodes and KVM hypervisors. This matches the public storefront's explicit SLA (**"Notice: VPS servers are delivered within 24 to 72 hours"**) and eliminates upstream API dependencies, billing account limits, or third-party merchant underwriting delays on Day 1.
   - **Phase 2 (Automated Elastic Cloud)**: Introduces event-driven, zero-touch API provisioning directly into upstream cloud providers (specifically Hetzner Cloud via Coolify's verified `App\Services\HetznerService` and DigitalOcean) using dynamic cloud-init injection.

3. **Multi-Tenancy Without Privilege Escalation**:
   - Standard Coolify team administration grants full Docker socket access to team admins (allowing host root compromise via custom Docker Compose bind-mounts). Therefore, shared application hosting is implemented via a **Platform Abstraction Layer** (Option C), where customers manage applications through a curated portal while the backend orchestrates isolated containers under strict Linux cgroups quotas.
   - Customers requiring full root Docker control are provisioned with dedicated VPS instances running their own dedicated Coolify or bare OS instances (Option B).

4. **Synchronized Telemetry and Status Observability**:
   - Every active VPS and hosted application automatically registers health probes with the existing ShanuTechX Status Platform (`status`), ensuring end-to-end SLA tracking, public transparency, and automated incident alerting.

5. **Financial Integrity and Auditability**:
   - All state transitions from order submission, invoice creation, manual payment proof upload, administrative verification, to service provisioning are executed within ACID database transactions, governed by immutable audit logging and zero-loss ledger principles.

---

## 2. Unified 4-Tier Ecosystem Topology

The platform architecture organizes components into four distinct functional tiers, communicating over secure internal networks and reverse proxies.

```
═══════════════════════════════════════════════════════════════════════════════════════
                             TIER 1: PUBLIC EDGE & INGRESS
═══════════════════════════════════════════════════════════════════════════════════════
                                  [ INTERNET ]
                                       │
                    ┌──────────────────┴──────────────────┐
                    ▼                                     ▼
        Cloudflare Edge Anycast               Traefik Reverse Proxy
         (DDoS / WAF / DNS)               Ports 80 / 443 (Let's Encrypt TLS)
                    │                                     │
    ┌───────────────┼───────────────┬─────────────────────┼─────────────────────┐
    ▼               ▼               ▼                     ▼                     ▼
┌───────────┐ ┌───────────┐ ┌───────────┐         ┌───────────────┐     ┌───────────────┐
│Storefront │ │  Status   │ │  Webmail  │         │Customer Portal│     │ Admin Console │
│   (web)   │ │ (status)  │ │ (webmail) │         │ (Livewire 3)  │     │ (Livewire 3)  │
│ Port 3000 │ │ Port 3003 │ │ Port 3002 │         │   Port 8000   │     │   Port 8000   │
└─────┬─────┘ └─────┬─────┘ └─────┬─────┘         └───────┬───────┘     └───────┬───────┘
══════╪═════════════╪═════════════╪═══════════════════════╪═════════════════════╪═════
      │             │             │   TIER 2: APPLICATION & API GATEWAY         │
══════╪═════════════╪═════════════╪═══════════════════════╪═════════════════════╪═════
      │             │             │                       │                     │
      │   GET /api/products       │                       │  Session & RBAC     │
      └─────────────┼─────────────┼───────────────────────┼─────────┐           │
                    │             │                       │         │           │
                    ▼             ▼                       ▼         ▼           ▼
        ┌───────────────────────────────────────────────────────────────────────────┐
        │                 Hosting Platform API & Core Orchestration                 │
        │               (Laravel 12 / PHP 8.3 / Sanctum / Reverb WS)                │
        │                                                                           │
        │ • REST API: /api/v1/ (Products, Orders, Invoices, Proofs, Service Mgmt)   │
        │ • Customer Portal Livewire Views: /portal/services, /portal/billing       │
        │ • Admin Control Studio: /admin/billing/proofs, /admin/inventory/vps       │
        │ • Real-time Event Broadcaster: Laravel Reverb (Port 6001)                 │
        └─────────────────────────────────────┬─────────────────────────────────────┘
                                              │
══════════════════════════════════════════════╪════════════════════════════════════════
                             TIER 3: DATA & MESSAGING PLANE
══════════════════════════════════════════════╪════════════════════════════════════════
                    ┌─────────────────────────┴─────────────────────────┐
                    ▼                                                   ▼
        ┌───────────────────────┐                           ┌───────────────────────┐
        │  PostgreSQL 16 Cluster│                           │  Redis 7 In-Memory    │
        │     (`coolify-db`)    │                           │  (Queues & Sessions)  │
        │ • 14 Core Commerce Tbl│                           │ • Horizon Queue Engine│
        │ • Server & App Config │                           │ • Cache & Broadcasts  │
        │ • Encrypted Secrets   │                           │ • Rate Limiting Locks │
        └───────────────────────┘                           └───────────┬───────────┘
                                                                        │
════════════════════════════════════════════════════════════════════════╪══════════════
                             TIER 4: INFRASTRUCTURE & EXECUTION         │
════════════════════════════════════════════════════════════════════════╪══════════════
                                                                        ▼
                                                            ┌───────────────────────┐
                                                            │ Laravel Horizon Queue │
                                                            │  Background Workers   │
                                                            └───────────┬───────────┘
                                                                        │
               ┌────────────────────────────────────────────────────────┴─────────────────────────────────┐
               ▼                                                        ▼                                 ▼
┌──────────────────────────────┐                         ┌──────────────────────────────┐  ┌──────────────────────────────┐
│  Phase 1: VPS Inventory Pool │                         │ Phase 2: Cloud Provider API  │  │ PaaS Application Cluster     │
│  (Bare Metal & KVM Nodes)    │                         │ (Hetzner Cloud & DO API)     │  │ (Docker Engine over SSH)     │
├──────────────────────────────┤                         ├──────────────────────────────┤  ├──────────────────────────────┤
│ • Pre-provisioned IP blocks  │                         │ • `App\Services\Hetzner`     │  │ • Multi-tenant Bridge Net    │
│ • Proxmox `qm` API & CLI     │                         │ • Dynamic Cloud-Init Engine  │  │ • Strict cgroups Quotas      │
│ • Secure PXE Disk Sanitizer  │                         │ • Auto PTR & Network Setup   │  │ • Automated Traefik Routing  │
│ • 24–72h Delivery Alignment  │                         │ • Instant (<120s) Delivery   │  │ • Isolated Volume Mounts     │
└──────────────────────────────┘                         └──────────────────────────────┘  └──────────────────────────────┘
```

### 2.1 Service Breakdown & Port Map

| Component | Repository Path | Framework / Stack | Listening Port | Public FQDN / Routing | Functional Responsibility |
|---|---|---|---|---|---|
| **Public Storefront** | `web/` | Next.js 14 (App Router), Tailwind CSS v4, Three.js | `3000` | `shanutechx.com`, `www.shanutechx.com` | Marketing, dynamic catalog display (`/vps`, `/app-hosting`, `/databases`), documentation (`/docs`), pricing comparison (`/pricing`). Consumes backend via `web/lib/api.ts`. |
| **Status Platform** | `status/` | Next.js 15, Tailwind CSS, SQLite (`data/status.db`) | `3003` | `status.shanutechx.com` | Real-time probe telemetry (ICMP ping, TCP port, HTTP/S checks), 90-day uptime bars, incident publishing, `/admin` monitor management studio. |
| **Webmail Service** | `webmail/` | Next.js 14, Tailwind CSS | `3002` | `mail.shanutechx.com`, `webmail.shanutechx.com` | Web-based email client portal for hosted domain email accounts. |
| **Control Plane & API** | root (`coolify`) | Laravel 12, Livewire 3, Sanctum, Reverb | `8000` | `portal.shanutechx.com`, `api.shanutechx.com` | Core commercial engine, customer portal, admin management console, REST API gateway (`/api/v1/`), Coolify orchestration engine. |
| **Reverb WebSockets** | root (`coolify`) | Laravel Reverb WebSocket Server | `6001` | `ws.shanutechx.com` | Real-time browser updates for terminal, deployment logs, service power state changes, and payment verification toasts. |
| **PostgreSQL Database** | root (`postgres`) | PostgreSQL 16 Alpine | `5432` (Internal) | `postgres:5432` (Docker Network) | Persistent storage for Coolify control plane and ShanuTechX commercial commerce data (14 relational tables). |
| **Redis Cache & Queue** | root (`redis`) | Redis 7 Alpine | `6379` (Internal) | `redis:6379` (Docker Network) | High-throughput job queue management for deployment pipelines, email notifications, dunning sweeps, and cache. |

---

## 3. VPS Hosting Technical Architecture

The ShanuTechX VPS product architecture follows a two-phase implementation, strategically harmonizing business viability, fraud prevention, and operational agility.

```
┌────────────────────────────────────────────────────────────────────────────────────────┐
│                                 VPS LIFECYCLE PIPELINE                                 │
└────────────────────────────────────────────────────────────────────────────────────────┘

 [ Storefront Catalog ] ──> [ Customer Checkout ] ──> [ Payment Verification Queue ]
                                                                 │
                                                       (Atomic DB Approval)
                                                                 │
                                   ┌─────────────────────────────┴─────────────────────────────┐
                                   ▼                                                           ▼
                      [ PHASE 1: INVENTORY POOL ]                                 [ PHASE 2: CLOUD API ENGINE ]
                      • Select matching slot from `vps_inventory`                 • Call `HetznerService::createServer()`
                      • Verify node status = 'available'                          • Generate dynamic cloud-init payload
                      • Inject customer SSH key via KVM agent                     • Poll server status until 'running'
                      • Assign IPv4/IPv6 & record hostname                        • Extract public IPv4 and IPv6 block
                                   │                                                           │
                                   └─────────────────────────────┬─────────────────────────────┘
                                                                 │
                                                                 ▼
                                                  [ TELEMETRY & HANDSHAKE ]
                                                  • Register TCP 22 / Ping in Status Platform
                                                  • Encrypt initial root credentials (AES-256)
                                                  • Dispatch Activation Welcome Email
                                                  • Customer Portal displays "Active" & metrics
```

### 3.1 Phase 1: Pre-Provisioned Inventory Pool Model (MVP Launch)

#### Strategic Justification
1. **Zero External Blocker**: Launching immediately without waiting for cloud provider credit approvals, high initial API rate limits, or verification hurdles.
2. **SLA Alignment**: Perfectly fulfills the storefront's established delivery guarantee shown on `web/app/vps/page.tsx` and `VpsCatalogShowcase.tsx`:
   > **Notice: VPS servers are delivered within 24 to 72 hours.**
3. **Hardware Maximization**: Allows utilization of leased dedicated physical servers (Hetzner Auction, OVH, Leaseweb) carved into KVM slices via Proxmox VE or libvirt.
4. **Billing Duration Fit**: Perfectly handles non-renewable `30-day` and `50-day` fixed durations specified in the storefront catalog.

#### Inventory Pool Management (`vps_inventory`)
The operator maintains an inventory table (`vps_inventory`) populated with pre-configured virtual machine slots or bare-metal allocations across designated datacenter regions (`fsn1-de`, `nbg1-de`, `hel1-fi`, `ash-us`).

Each record contains:
- Unique hardware identifier (`server_identifier`, e.g., `node-fsn1-kvm-012`)
- Network coordinates (`ipv4_address`, `ipv6_address`, `gateway`, `subnet_mask`, `mac_address`)
- Hypervisor / KVM reference ID (`vmid`)
- State machine flag (`status`: `available`, `reserved`, `allocated`, `wiping`, `maintenance`, `retired`)
- Encrypted root management credential (`encrypted_admin_password`, `encrypted_root_ssh_key`)

#### Scripted Sanitization & Disk Wipe Engine
When a customer terminates a Phase 1 VPS, the node must be forensically wiped to prevent cross-tenant data recovery:
1. **Virtual Machine (KVM/Proxmox) Reset Script**:
   ```bash
   #!/usr/bin/env bash
   set -euo pipefail

   VMID="$1"
   TEMPLATE_ID="$2"
   IPV4="$3"
   GW="$4"
   HOSTNAME="$5"

   echo "[$(date -u)] Initiating automated sanitization for VMID ${VMID}..."
   
   # Stop and unregister existing guest
   qm stop "${VMID}" --skiplock 1 || true
   sleep 3
   qm destroy "${VMID}" --purge 1

   # Full clone from pristine golden template (Ubuntu 24.04 LTS / Debian 12)
   qm clone "${TEMPLATE_ID}" "${VMID}" --name "${HOSTNAME}" --full 1

   # Re-configure network interfaces and cloud-init parameters
   qm set "${VMID}" \
     --ipconfig0 "ip=${IPV4}/24,gw=${GW}" \
     --nameserver "1.1.1.1 8.8.8.8" \
     --sshkeys /dev/null \
     --agent 1

   # Start cleaned VM
   qm start "${VMID}"
   echo "[$(date -u)] VMID ${VMID} cleanly provisioned and ready for inventory pool."
   ```
2. **Dedicated Bare-Metal Node Sanitization (PXE Rescue Mode)**:
   ```bash
   #!/usr/bin/env bash
   # Executed via SSH in network rescue mode (Debian live)
   set -euo pipefail
   TARGET_DEV="/dev/nvme0n1"

   # Cryptographic discard / fast NVMe sanitize
   blkdiscard -f "${TARGET_DEV}" || shred -v -n 1 -z "${TARGET_DEV}"

   # Re-install base operating system via debootstrap or provider autoinstaller
   parted -s "${TARGET_DEV}" mklabel gpt
   parted -s "${TARGET_DEV}" mkpart primary ext4 1MiB 100%
   mkfs.ext4 -F "${TARGET_DEV}p1"
   mount "${TARGET_DEV}p1" /mnt
   debootstrap --arch amd64 noble /mnt http://archive.ubuntu.com/ubuntu/
   ```
3. **Database Handshake**:
   Only after the sanitization script returns exit code `0` and a fresh SSH handshake verifies connectivity does the database update `vps_inventory.status = 'available'` and `last_wiped_at = NOW()`.

---

### 3.2 Phase 2: Automated Cloud Provider API Integration

Phase 2 introduces instant, zero-touch provisioning for high-volume scale, triggering on-demand VM creation through cloud provider APIs.

#### Upstream Engine: `App\Services\HetznerService`
The platform directly utilizes Coolify's verified `HetznerService` implementation (`app/Services/HetznerService.php`), which provides:
- Secure bearer token authentication with API rate-limit backoff handling (HTTP 429 reset header calculation).
- Server lifecycle management: `getLocations()`, `getServerTypes()`, `getImages()`, `createServer()`, `getServer()`, `powerOnServer()`, `deleteServer()`, `enableServerBackup()`, and `findServerByIp()`.

#### Dynamic Cloud-Init Generator
When an order transitions to `paid`, `ProvisionVpsJob` generates a hardened `user-data` cloud-init configuration injected into `HetznerService::createServer()`:

```yaml
#cloud-config
hostname: {{ $hostname }}
fqdn: {{ $hostname }}.{{ config('hosting.default_domain', 'shanutechx.net') }}
manage_etc_hosts: true

users:
  - name: {{ $adminUsername }}
    gecos: ShanuTechX Administrative User
    groups: sudo, adm
    shell: /bin/bash
    sudo: ['ALL=(ALL) NOPASSWD:ALL']
    ssh_authorized_keys:
      - {{ $customerSshPublicKey }}

disable_root: false
ssh_pwauth: false

packages:
  - qemu-guest-agent
  - ufw
  - fail2ban
  - curl
  - htop
  - iotop
  - tar
  - unattended-upgrades

write_files:
  - path: /etc/issue
    content: |
      ****************************************************************
      * Welcome to ShanuTechX Cloud VPS Infrastructure               *
      * Hostname: {{ $hostname }}                                    *
      * Provisioned: {{ now()->toIso8601String() }}                  *
      * Notice: Unauthorized access is strictly prohibited.          *
      ****************************************************************

runcmd:
  - systemctl enable --now qemu-guest-agent
  - systemctl enable --now fail2ban
  - ufw default deny incoming
  - ufw default allow outgoing
  - ufw allow 22/tcp comment 'SSH Port'
  - ufw allow 80/tcp comment 'HTTP Web Port'
  - ufw allow 443/tcp comment 'HTTPS Secure Web Port'
  - ufw --force enable
  - sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
  - systemctl restart sshd
```

#### Provisioning Telemetry & Polling Worker
1. `ProvisionVpsJob` submits server creation to Hetzner API:
   ```php
   $server = $hetzner->createServer([
       'name' => $service->hostname,
       'server_type' => $service->productVariant->sku, // e.g. 'cx22'
       'location' => $service->datacenter_location,   // e.g. 'fsn1'
       'image' => $service->os_image ?? 'ubuntu-24.04',
       'user_data' => $cloudInitYaml,
       'labels' => [
           'managed_by' => 'shanutechx_platform',
           'service_uuid' => $service->uuid,
           'tenant_id' => (string)$service->user_id,
       ],
   ]);
   ```
2. The job registers a delayed polling task (`PollVpsStatusJob`) checking `HetznerService::getServer($serverId)` every 10 seconds.
3. Once the server status reaches `running`, the job captures public IPv4 (`public_net.ipv4.ip`) and IPv6 network (`public_net.ipv6.ip`), stores them in `service_instances`, updates status to `active`, and dispatches `RegisterStatusMonitorJob`.

---

## 4. Coolify Application Hosting Architecture (PaaS)

Coolify's native architecture manages Docker-based deployments, proxy configs, Git webhooks, and databases. To turn Coolify into a commercial hosting PaaS, the multi-tenancy model must be architected with extreme security rigor.

### 4.1 Multi-Tenant Model Evaluation & Decision Matrix

| Model | Structural Mapping | Pros | Cons & Security Threat Analysis | Architecture Decision |
|---|---|---|---|---|
| **Option A: Team-per-Customer on Shared Server** | Each customer receives a Coolify Team (`team_id > 0`) sharing a single host Docker daemon. | Native Coolify Livewire UI can be exposed directly; minimal custom frontend code. | **CRITICAL SECURITY RISK**: In Coolify, any Team Admin can add arbitrary Docker Compose files. A customer can mount `/var/run/docker.sock` or the host root filesystem (`/:/host`), compromise the entire server kernel, inspect other tenants' database containers, and capture platform secrets. | **STRICTLY REJECTED** for shared environments. |
| **Option B: Dedicated VM per Customer** | Each customer is provisioned their own independent VPS (via Phase 1 or 2) with Coolify pre-installed. | Hardware-level KVM kernel isolation; customer receives unrestricted root Coolify access safely; zero cross-tenant risk. | Higher resource threshold (requires minimum 2 vCPU and 2GB RAM per customer); higher cost baseline. | **APPROVED** for "Dedicated PaaS / Coolify Cloud VPS" tier. |
| **Option C: Platform Abstraction Layer on Managed Clusters** | Platform backend acts as the sole operator of Coolify clusters. Customers interact exclusively with the curated Customer Portal (`NEXT_PUBLIC_DASHBOARD_URL`). Platform communicates with Coolify via internal API. | Absolute isolation; customer never touches Docker socket or raw Compose; fine-grained resource limits enforced; custom domains and env vars safely validated. | Custom portal UI required for application controls. | **APPROVED** for shared "Web App Hosting" and "Managed Database" tiers. |

### 4.2 Native Docker Resource Quota Enforcement

Under Option C, the platform leverages Coolify's built-in application resource limit attributes (verified in `app/Models/Application.php` lines 83–89 and `app/Jobs/ApplicationDeploymentJob.php`). Every application provisioned within the shared cluster has strict Linux cgroups constraints enforced:

```php
// Enforced programmatically during application configuration generation
$application->update([
    'limits_memory' => $variant->ram_limit_string,             // e.g. '1024m'
    'limits_memory_swap' => $variant->ram_swap_string,         // e.g. '1024m' (disables swap amplification)
    'limits_memory_reservation' => $variant->ram_soft_string,  // e.g. '512m' (soft threshold)
    'limits_cpus' => (string)$variant->cpu_limit_fraction,     // e.g. '1.5' (1.5 cores)
    'limits_cpu_shares' => $variant->cpu_shares_integer,       // e.g. 1024 (standard CFS share)
]);
```

#### Storage & Network Isolation
1. **XFS Project Quotas**:
   To prevent a tenant from exhausting server disk space via runaway logs or uploads, tenant persistent volumes are bounded using Linux XFS project quotas:
   ```bash
   xfs_quota -x -c 'project -s -p /var/lib/docker/volumes/app_{uuid} 1001' /var/lib/docker
   xfs_quota -x -c 'limit -p bsoft=9G bhard=10G 1001' /var/lib/docker
   ```
2. **Per-Tenant Network Sandboxing**:
   Each tenant's container group runs inside an isolated Docker bridge network (`net_{tenant_uuid}`). Inter-container communication (`icc=false`) is disabled between tenants. The only ingress into tenant containers is mediated through Traefik reverse proxy routes.

### 4.3 Database & Compose Service Provisioning Model

1. **Standalone Databases**:
   - Built on Coolify's standalone database models (`StandalonePostgresql`, `StandaloneMysql`, `StandaloneMariadb`, `StandaloneMongodb`, `StandaloneRedis`).
   - Databases are deployed with isolated volume mounts, randomly generated 32-character alphanumeric passwords, internal-only port bindings (or Traefik TCP proxy with TLS SNI), and automated daily S3 backup jobs (`ScheduledDatabaseBackupJob`).
2. **Pre-configured Compose Templates**:
   - Supports 1-click deployments from curated templates (`templates/compose/`) such as WordPress, Ghost, Nextcloud, and Directus.
   - All Compose templates undergo automated sanitation to strip dangerous parameters (`privileged: true`, host bind mounts, network mode `host`) before execution.

---

## 5. Commercial Product Catalog Specification

The platform defines three primary product families, fully harmonized with the existing storefront catalogs and schemas.

### 5.1 Cloud VPS Hosting Family

Designed for developers, networking engineers, and businesses requiring full OS root access. Matches the storefront filters (`30-day`, `50-day`, non-renewable):

| Plan Name | SKU | Hardware Specifications | Duration / Billing Type | Retail Price (USD) | Storefront Badge |
|---|---|---|---|---|---|
| **VPS Standard 1** | `vps-std-1` | 1 vCPU, 2 GB RAM, 40 GB NVMe, 20 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | 30-Day (Non-Renewable) | **$6.00** | Budget Pick |
| **VPS Standard 2** | `vps-std-2` | 2 vCPU, 4 GB RAM, 80 GB NVMe, 20 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | 30-Day (Non-Renewable) | **$12.00** | Top Value |
| **VPS Standard 3** | `vps-std-3` | 4 vCPU, 8 GB RAM, 160 GB NVMe, 20 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | 30-Day (Non-Renewable) | **$24.00** | Popular |
| **VPS Extended 1** | `vps-ext-1` | 1 vCPU, 2 GB RAM, 40 GB NVMe, 20 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | 50-Day (Non-Renewable) | **$9.50** | Extended Run |
| **VPS Extended 2** | `vps-ext-2` | 2 vCPU, 4 GB RAM, 80 GB NVMe, 20 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | 50-Day (Non-Renewable) | **$18.50** | Best Efficiency |
| **VPS Pro Enterprise**| `vps-pro-ent`| 8 vCPU, 16 GB RAM, 320 GB NVMe, 30 TB Bandwidth, 1 Dedicated IPv4, /64 IPv6 | Monthly Recurring | **$48.00** | High Performance |

### 5.2 Coolify Application Hosting (PaaS) Family

Designed for developers deploying modern web applications, microservices, and JAMstack backends without infrastructure maintenance:

| Plan Name | SKU | Resource Quotas | Capabilities & Ingress | Retail Price (USD) | Storefront Badge |
|---|---|---|---|---|---|
| **App Starter** | `app-starter` | 0.5 vCPU, 512 MB RAM, 5 GB NVMe Disk | 1 Custom Domain, Automated SSL, Git Push to Deploy, Traefik CDN Ingress | **$3.50 / mo** | Starter |
| **App Developer** | `app-developer`| 1.0 vCPU, 2 GB RAM, 20 GB NVMe Disk | 5 Custom Domains, Automated SSL, Deploy Previews, Daily Backups | **$9.00 / mo** | Most Popular |
| **App Production**| `app-prod` | 2.0 vCPU, 4 GB RAM, 50 GB NVMe Disk | Unlimited Domains, Automated SSL, High Availability Ingress, 6-Hour Backups | **$19.00 / mo**| Enterprise Ready |

### 5.3 Managed Database Add-On Family

| Database Engine | Engine Versions | Default Specs | Backup Frequency | Monthly Price (USD) |
|---|---|---|---|---|
| **PostgreSQL Cloud** | PostgreSQL 15, 16 | 1 vCPU, 1 GB RAM, 15 GB NVMe | Daily Automated to S3 | **$7.00 / mo** |
| **MySQL / MariaDB** | MySQL 8.0, MariaDB 11 | 1 vCPU, 1 GB RAM, 15 GB NVMe | Daily Automated to S3 | **$7.00 / mo** |
| **Redis In-Memory** | Redis 7.2 | 0.5 vCPU, 512 MB RAM, Persistence | Snapshot Daily | **$4.00 / mo** |
| **MongoDB Document**| MongoDB 7.0 | 1 vCPU, 2 GB RAM, 25 GB NVMe | Daily Automated to S3 | **$10.00 / mo** |

---

## 6. Storefront, Status & Webmail Integration Architecture

### 6.1 Storefront Dynamic Catalog Contract
The public storefront (`web/lib/api.ts`) fetches plans dynamically using:
```typescript
GET /api/products?category={category}
```
The Hosting Platform backend implements this exact schema, mapping `products`, `product_variants`, and `pricing_tiers` into the required response structure:

```json
{
  "products": [
    {
      "id": "prod_vps_hetzner",
      "category": "vps",
      "name": "Hetzner Cloud VPS",
      "slug": "hetzner-cloud-vps",
      "description": "High-speed European cloud compute with dedicated NVMe and 10Gbps uplinks.",
      "isPopular": true,
      "badge": "Top Performance",
      "isActive": true,
      "plans": [
        {
          "id": "plan_vps_std_2",
          "productId": "prod_vps_hetzner",
          "name": "VPS Standard 2",
          "price": 12.00,
          "currency": "USD",
          "billingType": "30-day",
          "isPopular": true,
          "badge": "Top Value",
          "specsJson": {
            "vCPU": "2 vCPU",
            "RAM": "4 GB RAM",
            "Storage": "80 GB NVMe",
            "Bandwidth": "20 TB Traffic",
            "Network": "1 Gbps Public Port",
            "IPv4": "1 Dedicated IPv4",
            "IPv6": "/64 Dedicated IPv6",
            "Delivery": "24 to 72 Hours"
          },
          "isActive": true
        }
      ]
    }
  ]
}
```

### 6.2 Status Platform Probing & Incident Automation
1. **Automated Probe Registration**:
   Upon service activation, `RegisterStatusMonitorJob` communicates with the ShanuTechX Status Platform (`status/lib/db.ts` SQLite database or internal API):
   - For VPS: Injects an ICMP Ping monitor and TCP Port 22 connectivity check.
   - For Web App: Injects an HTTPS probe verifying customer domain SSL certificates and 200–399 HTTP response codes.
2. **Degraded State Handshake**:
   When status monitors detect prolonged downtime (> 5 failed probes), `status` fires an internal webhook to `/api/v1/webhooks/status-alert`. The hosting backend logs the incident, displays a maintenance banner in the customer portal, and notifies the DevOps on-call engineer.

### 6.3 Webmail Domain Routing
For customers with web hosting plans, domain DNS records (`MX`, `DKIM`, `SPF`, `DMARC`) are automatically provisioned. Traefik routes webmail traffic for `mail.{customer_domain}` directly to the dedicated `webmail` service container (listening on port `3002`).

---

## 7. Deliverable C Summary & Architectural Sign-off

Deliverable C establishes a concrete, cohesive, and resilient target architecture that harmonizes ShanuTechX's existing Next.js web applications with Coolify's orchestration engine. By enforcing strict separation of planes, adopting a realistic two-phase VPS rollout, and deploying a secure Platform Abstraction Layer for shared application hosting, the platform guarantees enterprise security, zero cross-tenant compromise, and maximum commercial velocity.
