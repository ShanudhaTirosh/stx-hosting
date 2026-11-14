# STX Enterprises — Enterprise Cloud Platform & Hosting Control Plane

<p align="center">
  <strong>High-Performance Cloud Infrastructure, Self-Service Client Portal, Native Telemetry & Self-Hosted BaaS / Mail Hosting</strong><br>
  <em>Engineered by STX Enterprises &middot; Digital Empire Studio</em>
</p>

---

## Overview

**STX Enterprises Cloud Platform** is an enterprise-grade hosting and orchestration suite that unifies public customer acquisition, self-service container/database provisioning, managed email hosting, self-hosted Supabase BaaS, bank-transfer proof verification, and native real-time infrastructure telemetry into a cohesive control plane.

Built with **Laravel 12** (Laravel 10 structure), **Livewire 3**, and **Tailwind CSS v4**, the entire platform — including marketing storefront, client portal, status telemetry, and operations console — runs natively in high-performance PHP on port `8000`.

```
                         STX ENTERPRISES PLATFORM ARCHITECTURE
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ PUBLIC TIER (Native PHP / Livewire 3 / Tailwind v4 on Port 8000)                       │
│  • High-Performance Storefront (`/`, `/vps`, `/pricing`, `/databases`, `/email-hosting`)│
│  • Native Status Telemetry Engine (`/status` with 90-Day Uptime & Masked Nodes)        │
│  • Public Status Alert Subscriptions (Email Incident Notifications)                   │
│  • Public Printable Invoices (`/invoices/{uuid}`)                                      │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ CONTROL PLANE (Laravel 12 / Livewire 3 / Tailwind v4)                                  │
│  ┌─────────────────────────────────────┐   ┌─────────────────────────────────────────┐ │
│  │ CLIENT SELF-SERVICE PORTAL          │   │ ADMINISTRATIVE OPERATIONS CONSOLE       │ │
│  │ • 1-Click Git & Docker Deployments  │   │ • Manual Multi-Provider Fulfillment     │ │
│  │ • Curated 31-Template App Catalog   │   │ • Side-by-Side Slip Verification Queue  │ │
│  │ • Managed Databases (PG/MySQL/Redis)│   │ • Template & Mail Plan Studios          │ │
│  │ • Managed Email Hosting & Webmail   │   │ • Automated Renewals & Suspension Engine│ │
│  │ • Multi-Target Database Restores    │   │ • Refund Management & Quota Reversals   │ │
│  │ • Volume Backups (Local & S3/R2)    │   │ • Infrastructure & Hypervisor Inventory │ │
│  │ • Custom Domains & Automated HTTPS  │   │ • Live Support Chat with File Attachments│ │
│  └─────────────────────────────────────┘   └─────────────────────────────────────────┘ │
└───────────────────────────────────────────┬────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ SELF-HOSTED SERVICES & INFRASTRUCTURE TIER                                             │
│  • Self-Hosted Supabase: Kong Gateway, GoTrue Auth, PostgREST, Realtime, Storage,     │
│    Edge Functions, Studio, and PostgreSQL 15 with pg_cron                              │
│  • WildDuck Mail Stack: WildDuck API, Haraka SMTP, Zone-MTA, Dovecot IMAP, MongoDB    │
│  • STX Webmail UI: Containerized PHP-FPM/Nginx Client with Portal Deep-Linking         │
│  • Automated Reverse Proxy: Traefik & Caddy Automated TLS Engine                       │
│  • Native Socket Health Prober (`php artisan status:probe`)                            │
│  • Pure-Pixel Image Re-encoding Pipeline (Scrubbing Polyglots)                         │
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
- **Status Subscriptions & Alerts**: Visitors can subscribe to automated email incident alerts with email verification.

### 3. Customer Self-Service Portal (`/portal`)
- **Git & GitHub App Deployments**: Deploy public and private repositories with automated Nixpack/Dockerfile builds across 3 fixed-price tiers (`git-starter`, `git-standard`, `git-pro`).
- **Curated 31-Template Catalog**: Verified 1-click deployments (Supabase, n8n, WordPress, Ghost, Plausible, Nextcloud, Strapi, Uptime Kuma) with automated HTTPS and cgroups resource limits.
- **1-Click Managed Databases**: Launch standalone PostgreSQL 16, MySQL 8.4 LTS, and Redis 7.4 with isolated resource quotas.
- **Multi-Target Database Restores & Volume Backups**: Restore backups to original containers, new standalone databases, or custom target schemas; create on-demand and scheduled application volume snapshots to local or Cloudflare R2/S3 storage.
- **Custom Domains & Automated HTTPS**: Instant DNS status verification and Caddy/Traefik SSL certificate provisioning.

### 4. Managed Email Hosting & STX Webmail UI (`/portal/mail`)
- **Powered by WildDuck API & Caddy TLS**: Enterprise-grade mail hosting with Haraka inbound SMTP, Zone-MTA outbound delivery, Dovecot IMAP, and MongoDB storage.
- **Sellable Pooled Plans**: Starter (5 GB), Basic (15 GB), Pro (50 GB), and Business (100 GB) with modular mailbox, domain, and storage add-ons.
- **Domain DNS Verification**: Clear guides and status badges for MX, SPF, DKIM, and DMARC DNS records.
- **Integrated STX Webmail UI**: Containerized Nginx/PHP-FPM webmail client (`webmail-ui`) featuring thread navigation, multi-account support, contact suggestions, and direct single-click portal deep-linking.

### 5. Self-Hosted Supabase Backend-as-a-Service
- **Full-Stack Containerized BaaS**: Complete stack with Kong API Gateway, Supabase GoTrue Auth, PostgREST, Realtime engine, Storage API, Studio UI, and PostgreSQL 15 with `pg_cron` extensions.
- **Dual-Mode Operation**: Use Supabase as the primary platform database/auth provider or deploy dedicated self-hosted Supabase instances for clients via curated templates.
- **Native SDK Integration**: Built-in `SupabaseClient`, `SupabaseQueryBuilder`, and `SupabaseAuthService` bridging Laravel with Supabase APIs.

### 6. Commercial Operations, Verification Queue & Automation
- **Bank Transfer & Cash Deposit Workflow**: Customers upload deposit slips during checkout (`/portal/invoices/{uuid}`).
- **Pure-Pixel Sanitization**: Raster uploads undergo server-side decoding and re-encoding (`imagejpeg` / `imagepng`), purging malicious polyglot webshell payloads.
- **Pessimistic Locking Concurrency**: Dual-admin race conditions are prevented with database row-level locking (`lockForUpdate()`), returning HTTP 409 Conflict if an invoice was already processed.
- **Automated Renewals & Grace Periods**: Automated renewal invoice generation 7 days prior to expiry (`GenerateRenewalInvoicesJob`) and overdue service suspension after a 4-day grace period (`ProcessOverdueSuspensionsJob`).
- **Administrative Refund Engine**: Full or partial refund workflow (`RefundService`) with audit logging, entitlement revocation, and customer notifications.
- **Live Support Chat**: Real-time client-to-staff chat with file attachments and Reverb WebSockets broadcasting.

---

## Quickstart & Local Testing

### Prerequisites
- PHP 8.2+ / Composer
- Node.js 20+ & npm
- PostgreSQL 16 & Redis
- Docker Engine & Docker Compose

### Starting Development Servers

```bash
# 1. Install dependencies & run migrations
composer install
npm install
npm run build
php artisan migrate --seed

# 2. Seed catalog plans, templates & native status monitors
php artisan db:seed --class=HostingCatalogSeeder
php artisan db:seed --class=NativeStatusMonitorSeeder

# 3. Start local development server
php artisan serve --port=8000

# 4. Access Platform URLs (All on Port 8000)
# Storefront:          http://localhost:8000
# Native Status:       http://localhost:8000/status
# Customer Portal:     http://localhost:8000/portal
# Mail Dashboard:      http://localhost:8000/portal/mail
# Deploy Wizard:       http://localhost:8000/portal/deploy
# Admin Operations:    http://localhost:8000/admin/operations/provisioning
# Proof Verification: http://localhost:8000/admin/billing/proofs
# Template Studio:     http://localhost:8000/admin/commerce/templates
# Mail Plan Studio:    http://localhost:8000/admin/commerce/mail
```

### Self-Hosted Production Stack (Docker Compose)

For a complete self-hosted deployment including STX Hosting, Supabase, WildDuck Mail, and Caddy TLS:

```bash
# 1. Copy self-hosted environment examples
cp .env.selfhosted.example .env
cp .env.supabase.example .env.supabase

# 2. Start the unified stack
docker compose -f docker-compose.selfhosted.yml up -d
```

### Running Automated Test Suites

```bash
# Run Pest test suite
php artisan test --compact

# Key Feature Test Suites:
php artisan test --compact tests/Feature/Commerce/
php artisan test --compact tests/Feature/Supabase/SupabaseIntegrationTest.php
php artisan test --compact tests/Feature/UpstreamSyncAndAdaptationTest.php
php artisan test --compact tests/Feature/DockerComposeInjectionCompatibilityTest.php

# Adversarial Gate & Verification Suite
python tests/verification/test_adversarial_gate2_verification.py
python tests/verification/test_remediation_verification.py
```

---

## License & Copyright

Copyright &copy; 2026 **STX Enterprises (Pvt) Ltd** &middot; **Digital Empire Studio**. All rights reserved.  
Unauthorized copying, reverse engineering, or redistribution of proprietary commerce modules, billing queues, or internal orchestrators is strictly prohibited.
