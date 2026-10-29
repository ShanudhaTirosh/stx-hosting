# STX Enterprises — Enterprise Cloud Platform & Hosting Control Plane

<p align="center">
  <strong>High-Performance Cloud Infrastructure, Self-Service Client Portal & Native Telemetry Engine</strong><br>
  <em>Engineered by STX Enterprises &middot; Digital Empire Studio</em>
</p>

---

## Overview

**STX Enterprises Cloud Platform** is an enterprise-grade hosting and orchestration suite that unifies public customer acquisition, self-service container/database provisioning, bank-transfer proof verification, and native real-time infrastructure telemetry into a cohesive control plane.

Built with **Laravel 12**, **Livewire 3**, **Tailwind CSS v4**, and **Next.js**, it bridges commercial hosting operations with developer-first automation.

```
                         STX ENTERPRISES PLATFORM ARCHITECTURE
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ PUBLIC TIER                                                                            │
│  • Modern Storefront (`/`, `/vps`, `/pricing`, `/databases`)                           │
│  • Native Status Telemetry Engine (`/status` with 90-Day Uptime & Masked Nodes)        │
│  • Public Printable Invoices (`/invoices/{uuid}`)                                      │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ CONTROL PLANE (Laravel 12 / Livewire 3 / Tailwind v4)                                  │
│  ┌─────────────────────────────────────┐   ┌─────────────────────────────────────────┐ │
│  │ CLIENT SELF-SERVICE PORTAL          │   │ ADMINISTRATIVE OPERATIONS CONSOLE       │ │
│  │ • 1-Click Git & GitHub Deployments  │   │ • Manual Multi-Provider Fulfillment     │ │
│  │ • Managed Databases (PG/MySQL/Redis)│   │ • Side-by-Side Slip Verification Queue  │ │
│  │ • Curated Discord & Telegram Bots   │   │ • Infrastructure & Hardware Management  │ │
│  │ • Password-Protected Credential Box │   │ • Hypervisor Inventory Tracking         │ │
│  └─────────────────────────────────────┘   └─────────────────────────────────────────┘ │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ TELEMETRY & SECURITY LAYER                                                             │
│  • Native Socket & TCP Port Health Prober (`php artisan status:probe`)                 │
│  • Pure-Pixel Image Re-encoding Pipeline (Scrubbing Polyglots)                         │
│  • NIST SP 800-88 Rev 1 Blast-Radius Disk Wipe Protection                              │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## Core Capabilities

### 1. Unified Multi-Provider Fulfillment (Manual Workflow)
- **Arbitrary Provider Freedom**: Order physical nodes or cloud VPS instances from any preferred provider (Contabo, OVH, Leaseweb, DigitalOcean, Linode) without dependency on automated upstream cloud API quotas.
- **Direct Details Entry (`/admin/operations/provisioning`)**: Input assigned IP address, custom SSH port, OS image, and root password on the spot.
- **AES-256-GCM Credential Encryption**: Customer credentials are encrypted at rest with spatie/laravel-crypto. Root passwords are dots-masked by default and require account password confirmation (`Hash::check`) to decrypt in-memory.

### 2. Native Status & Privacy-Preserving Telemetry (`/status`)
- **Integrated Control Plane**: Embedded natively inside the platform without requiring external daemons.
- **Node Privacy Redaction**: Sensitive host IP addresses, MACs, hypervisor IDs, and internal ports are 100% redacted from public APIs and views. Public visitors see clear cluster labels (`Edge Gateway — Frankfurt`, `Managed DB Cluster — Primary`).
- **90-Day Interactive Heatmaps**: Per-service uptime bars, average latency response metrics, and public incident notices.

### 3. Customer Self-Service Portal (`/portal`)
- **Git & GitHub App Deployments**: Deploy public and private repositories with automated Nixpack/Dockerfile builds.
- **1-Click Managed Databases**: Launch standalone PostgreSQL 16, MySQL 8.4 LTS, and Redis 7.4 with isolated resource quotas.
- **Curated Templates**: Instant deployment of Discord bots, Telegram bots, Next.js web applications, and Express APIs.
- **Billing & Printable Invoices**: Instant access to branded STX Enterprises invoices (`/portal/invoices/{uuid}`) with 1-click print/PDF support.

### 4. Commercial Verification Queue (`/admin/billing/proofs`)
- **Bank Transfer & Cash Deposit Workflow**: Customers upload deposit slips during checkout.
- **Pure-Pixel Sanitization**: Raster uploads undergo server-side decoding and re-encoding (`imagejpeg` / `imagepng`), purging malicious polyglot webshell payloads.
- **Pessimistic Locking Concurrency**: Dual-admin race conditions are prevented with database row-level locking (`lockForUpdate()`), returning HTTP 409 Conflict if an invoice was already processed.

---

## Quickstart & Local Testing

### Prerequisites
- PHP 8.2+ / Composer
- Node.js 20+ & npm
- PostgreSQL 16 & Redis
- Docker Engine

### Starting Development Servers

```bash
# 1. Install dependencies & run migrations
composer install
php artisan migrate --seed

# 2. Seed catalog plans & native status monitors
php artisan db:seed --class=HostingCatalogSeeder
php artisan db:seed --class=NativeStatusMonitorSeeder

# 3. Start marketing storefront
cd web && npm run dev -- -p 3000

# 4. Access Platform URLs
# Storefront:          http://localhost:3000
# Native Status:       http://localhost:8000/status
# Customer Portal:     http://localhost:8000/portal
# Admin Operations:    http://localhost:8000/admin/operations/provisioning
# Proof Verification: http://localhost:8000/admin/billing/proofs
```

### Running Automated Test Suites

```bash
# Adversarial Gate & Verification Suite (70/70 assertions)
python tests/verification/test_adversarial_gate2_verification.py
python tests/verification/test_remediation_verification.py
```

---

## License & Copyright

Copyright &copy; 2026 **STX Enterprises (Pvt) Ltd** &middot; **Digital Empire Studio**. All rights reserved.  
Unauthorized copying, reverse engineering, or redistribution of proprietary commerce modules, billing queues, or internal orchestrators is strictly prohibited.

