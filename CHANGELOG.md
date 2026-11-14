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
| **Cloud Automation** | Automated VPS API Delivery | **IN PROGRESS** | Provider API clients exist; commercial flow currently uses manual review queue while quotas are hardened. |
| **Payment Gateways** | Direct Card / Stripe Webhooks | **IN PROGRESS** | Stripe webhook function exists; bank deposit slips remain primary active checkout flow. |
| **Architecture** | Upstream Coolify v5 Split | **PLANNED** | ADRs in `docs/v5/` exploring flux connection broker and coold agent boundaries. |

---

## 3. Product Roadmap

### Immediate Roadmap (Next Sprint)
- [ ] Implement automated cloud VPS provisioning via Hetzner and DigitalOcean APIs directly into isolated customer virtual machines.
- [ ] Add direct credit card processing via Stripe Checkout for international customers alongside bank transfer verification.
- [ ] Deploy native push notifications in the Customer Portal via Reverb WebSockets and service workers.

### Medium-Term Roadmap (Q1 2027)
- [ ] Native iOS message interceptor app for PayVerify matching the Android client's hardware-backed keypair authentication.
- [ ] Expand PayVerify statement parsers to support additional Sri Lankan banks (Sampath Bank, Hatton National Bank, Bank of Ceylon).
- [ ] Multi-region cluster federation allowing customer applications to be deployed across geographically distributed server nodes.

### Long-Term Roadmap (Q2 2027+)
- [ ] Transition control plane to Coolify v5 architecture (Flux connection broker and Coold host agent boundary).
- [ ] Automated edge Anycast routing integration for global load balancing.

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
