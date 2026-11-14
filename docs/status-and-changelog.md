# STX Enterprises Cloud Platform - Changelog & Status Report

## Document Version & Release Context
- **Current Core Version**: STX Hosting `4.5.4`
- **Release Status**: Production Commercial Cloud Platform
- **Last Updated**: November 2026

---

## 1. Documentation Alignment & Platform Audit Update (November 2026)

### Added
- **Root `.env.example`**: Created clean, exhaustive environment configuration template with 14 functional sections, detailed descriptions, and zero real secrets.
- **Canonical Architecture Reference (`docs/architecture.md`)**: Full architectural breakdown including Mermaid topology diagrams, port mappings, data flows, and subsystem specifications.
- **Database Schema & RLS Policies (`docs/database-schema-and-rls.md`)**: Complete schema documentation, CUID2 UUID standards, instance sentinel `id=0` rules, and comprehensive Supabase PostgreSQL Row Level Security (RLS) policies.
- **Authentication & Authorization Flows (`docs/auth-flows.md`)**: End-to-end documentation for email/password, email verification, TOTP 2FA, WebAuthn Passkeys, magic links, Socialite OAuth (Google, Discord, GitHub, Azure, etc.), and Supabase GoTrue Auth bridge.
- **Domains, DNS & Automated SSL (`docs/domains-dns-ssl.md`)**: Custom domain lifecycle, DNS verification methods (`dns_get_record`), Cloudflare platform DNS, Traefik auto-SSL, and Caddy TLS.
- **Multi-Node Server Management (`docs/node-management.md`)**: Remote Docker orchestration over SSH, non-root sudo parser (`parseCommandsByLineForSudo`), telemetry probers, and the Server Transfer engine (`ServerTransferExporter`/`ServerTransferImporter`).
- **Mail System & STX Webmail (`docs/mail-system.md`)**: Self-hosted WildDuck 1.55.2 mail stack (Haraka, Zone-MTA, Dovecot, MongoDB 6), pooled storage plans, and containerized `webmail-ui`.
- **Payments & PayVerify AI Verification (`docs/payments-flow.md`)**: Bank transfer and deposit slip checkout, pure-pixel raster sanitization, fast perceptual dHash duplicate check, PayVerify AI (Google Gemini 1.5 Flash and local Ollama CPU), ComBank WhatsApp Banking statement sync, Android zero-leak SMS/OTP relay, confidence scoring rules, and pessimistic row locking (`lockForUpdate()`).
- **GitHub App Integration (`docs/github-integration.md`)**: Public and private repository deployments, installation tokens, webhook event dispatching, and buildpacks.
- **API Reference & Developer Guide (`docs/api-reference.md`)**: REST API (`/api/v1/`), token abilities, data masking, and PayVerify External API (`/api/v1/external/`).
- **Deployment & Operational Runbook (`docs/deployment-operations.md`)**: Master compose file `docker-compose.selfhosted.yml`, multi-target database restores (`RestoreDatabaseBackupJob`), volume snapshots to Cloudflare R2 / S3, and upgrade procedures.
- **Troubleshooting & Known Gotchas (`docs/troubleshooting.md`)**: Resolution runbooks for Pest browser test gotchas, Redis/Horizon issues, SSL failures, mail port blocks, and WhatsApp bot reconnects.
- **`CLAUDE.md`**: Dedicated instructions for Claude Code and AI coding agents.

### Changed & Updated
- **Root `README.md`**: Rewritten into a unified, accurate specification reflecting current architecture, live component statuses, Mermaid diagrams, quickstart, and direct links to all technical docs.
- **`AGENTS.md`**: Restructured and focused on strict invariants, safe areas vs areas not to touch, security rules, multi-tenant scoping, and Definition of Done (DoD).
- **Sub-Project Documentation**: Audited and confirmed alignment across `webmail-ui`, `payverify`, `payverify-android`, `payverify-web`, and `payverify-whatsapp-bot`.

### Removed / Archived
- Legacy assumptions regarding standalone Next.js storefront (`web/`) and status (`status/`) apps: documented their full migration and consolidation into native Laravel 12 / Livewire 3 / Blade on port `8000`.

---

## 2. Current Platform Status Matrix

| Subsystem | Feature | Status | Notes |
|---|---|---|---|
| **Core PaaS** | 1-Click App Deployment | **DONE** | 31 active curated templates in `templates/compose/`. |
| **Core PaaS** | Multi-Node Management | **DONE** | Remote Docker over SSH; non-root sudo parsing. |
| **Core PaaS** | Domains & DNS Verification | **DONE** | `CustomDomainService` & `PollCustomDomainDnsJob`. |
| **Core PaaS** | Automated SSL / HTTPS | **DONE** | Traefik auto-SSL for apps; Caddy auto-SSL for mail/webmail. |
| **Backend BaaS** | Self-Hosted Supabase | **DONE** | Kong, GoTrue, PostgREST, Realtime, Storage, Studio, Postgres 15. |
| **Backend BaaS** | Supabase RLS Policies | **DONE** | Granular RLS across profiles, teams, orders, invoices, storage. |
| **Authentication** | Email & Password Verification | **DONE** | Fortify registration, hashing, and email verification. |
| **Authentication** | 2FA (TOTP) & Passkeys | **DONE** | Authenticator app pairing & WebAuthn biometric passkeys. |
| **Authentication** | OAuth Social Logins | **DONE** | Google, Discord, GitHub, Azure, Authentik, Clerk, Zitadel, OIDC. |
| **Authentication** | Password Reset & Magic Links | **DONE** | Signed reset tokens & rate-limited passwordless links. |
| **Mail System** | WildDuck Mail Server | **DONE** | Haraka (25), Zone-MTA (587), Dovecot (993), MongoDB 6.0. |
| **Mail System** | Mailbox & Domain Provisioning | **DONE** | Provisioning via `WildDuckProvisioningService.php` with DNS checks. |
| **Mail System** | STX Webmail UI | **DONE** | Standalone PHP 8.3 / Nginx client with multi-account switching. |
| **Commerce** | Template Catalog & Limits | **DONE** | Curated catalog with cgroups CPU/RAM bounds. |
| **Commerce** | Email Hosting Plans | **DONE** | Pooled storage plans (5GB - 100GB) + modular add-ons. |
| **Commerce** | VPS Fulfillment Workflow | **DONE** | Manual delivery queue with AES-256-GCM encrypted root passwords. |
| **Commerce** | Invoicing & Renewals | **DONE** | Printable PDF invoices, 7-day renewals, 4-day grace suspensions. |
| **Payments** | PayVerify AI Engine | **DONE** | Gemini 1.5 Flash multimodal vision & local Ollama CPU fallback. |
| **Payments** | ComBank Statement Sync | **DONE** | Baileys WhatsApp bot, 8-digit pairing code, 60s buffered OTP queue. |
| **Payments** | Android SMS Relay App | **DONE** | Kotlin/Compose app with hardware ECDSA P-256 and zero-leak OTP. |
| **Payments** | Rules & Staff Workbench | **DONE** | Auto-approval (>=0.90), hard reject (<0.60), review workbench. |
| **GitHub App** | Repository Deployments | **DONE** | Public and private repo builds via GitHub App with webhooks. |
| **Dashboards** | Customer Portal | **DONE** | `/portal` (Deploy wizard, apps, mail, domains, backups, billing). |
| **Dashboards** | Operations & Admin Studio | **DONE** | `/admin` (Provisioning queue, proof review, catalog studio). |
| **Dashboards** | Public Status Page | **DONE** | `/status` with 90-day heatmap, masked nodes, and alerts. |
| **Cloud Automation** | Automated VPS API Delivery | **PLANNED (FUTURE)** | Provider API clients exist (Hetzner, DigitalOcean, Vultr, Hostinger); commercial flow remains manual review and fulfillment (24 to 72 hours) until user growth triggers automated quotas. |
| **Payment Gateways** | Direct Card / Stripe Webhooks | **PLANNED (FUTURE)** | Stripe webhook function exists (`supabase/functions/stripe-webhook`); bank transfer with PayVerify AI slip verification remains the live payment method. |
| **Architecture** | Upstream Coolify v5 Split | **PLANNED (FUTURE)** | Architectural design and ADRs isolated in `docs/v5/` so it does not affect the active v4 production release. |

---

## 3. Product Roadmap & Future Features

The roadmap has been re-prioritized to freeze expansion and harden existing subsystems while overhauling the public storefront for high-converting crawlability, technical SEO, and AI search visibility (GEO/AEO).

### 3.1 Active Hardening Program (6 Sequential Phases)
1. **Phase 1: Security & Data Foundation**: Fortify sessions, Supabase Row Level Security (RLS) across all tables, RBAC isolation, data masking, input validation, rate limiting, and automated backups.
2. **Phase 2: Core Hosting**: Validation of 31 curated compose templates, multi-node SSH placement, DNS verification, Traefik/Caddy auto-SSL, deploy logs, and cgroups resource limits.
3. **Phase 3: Money Flow**: Cart checkout, PayVerify AI slip verification (Gemini 1.5 Flash + Ollama), manual review queue with pessimistic locking, anti-double activation, 24–72 hour VPS fulfillment queue, and mail hosting plans.
4. **Phase 4: Mail & Communication**: WildDuck mail stack, mailbox and alias management, SPF/DKIM/DMARC deliverability, STX Webmail UI, and automated transactional emails.
5. **Phase 5: Dashboards & Status**: Customer portal (`/portal`), Admin operations console (`/admin`), and public status page (`/status`) with real-time health telemetry.
6. **Phase 6: Related Apps**: PayVerify Android SMS relay app (hardware ECDSA P-256) and WhatsApp Banking bot state machine stability.

### 3.2 Future Features Catalog (Deferred to Scale Triggers)
These items remain preserved in the codebase as groundwork, but are strictly gated/disabled from customer checkout:

- **Automated Cloud VPS API Provisioning** (`PLANNED (FUTURE)`):
  - *What It Is*: Programmatic VM creation via Hetzner, DigitalOcean, Vultr, and Hostinger APIs.
  - *Why Deferred*: Upstream provider quota ceilings, runaway cloud billing risks, and IP fraud prevention on young accounts.
  - *Start Trigger*: Reaching **100 active VPS subscriptions** or **>$2,500 monthly recurring revenue (MRR)**.
  - *What Exists*: Provider client classes in `app/Services/` and controllers in `app/Http/Controllers/Api/`.
  - *Live Alternative*: Manual review queue and inventory allocation with an SLA of **24 to 72 hours**.
- **Direct Payment Gateways (Stripe & Credit Cards)** (`PLANNED (FUTURE)`):
  - *What It Is*: Direct card checkout with automated webhooks.
  - *Why Deferred*: Merchant underwriting holdbacks, rolling reserves (10–20% held for 180 days), and chargeback risks.
  - *Start Trigger*: Reaching **500+ successful monthly orders** or international volume exceeding 40% of total revenue.
  - *What Exists*: Stripe webhook handler in `supabase/functions/stripe-webhook`.
  - *Live Alternative*: Bank transfer in LKR and USD verified by **PayVerify AI** and staff review in `/admin/billing/proofs`.
- **Platform Version 5 Split** (`PLANNED (FUTURE)`):
  - *What It Is*: Separation into Flux connection broker and Coold host agent.
  - *Why Deferred*: The current monolithic Laravel 12 architecture is proven and stable.
  - *Start Trigger*: Management of >25 remote hypervisors or multi-region cluster federation.
  - *What Exists*: Architectural specifications and ADRs isolated in `docs/v5/`.

---

## 4. Known Issues & Operational Gotchas

1. **Pest Browser Test In-Process HTTP Server**:
   - Host PHP without `phpredis` will throw `Class "Redis" not found` because maintenance mode store defaults to redis. Fix: `config()->set('app.maintenance.store', 'array');` in test `beforeEach`.
2. **User Onboarding Cache**:
   - Freshly created users redirect to `/onboarding`. In tests, update `Team::query()->update(['show_boarding' => false]);` and flush `Cache::flush();`.
3. **Instance Sentinel Rows (`id = 0`)**:
   - Keyset pagination with `id > $cursor` starting at 0 skips sentinel records. Always use `chunkById()`.
4. **Outbound Mail Port 25**:
   - Cloud providers block outbound port 25 by default. Outbound mail requires either provider port 25 unblocking or configuring a smart host relay in Zone-MTA.
5. **WhatsApp Bot Multi-Device Sockets**:
   - Network interruptions can trigger WhatsApp connection status `428` or `515`. The bot auto-reconnects with exponential backoff, and re-pairing can be completed in the Admin dashboard using the 8-digit pairing code.
