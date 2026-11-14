# AGENTS.md: Developer & AI Agent Operating Manual

This document provides definitive guidance for software engineers and AI coding agents working in the **STX Enterprises Cloud Platform** repository.

---

## 1. Project Overview & Architecture

STX Enterprises Cloud Platform is an enterprise cloud PaaS, managed database, email hosting, self-hosted BaaS, and billing control plane built on:
- **Framework**: Laravel 12 (running within the explicit **Laravel 10 directory structure**; not Laravel 11+ slim structure).
- **Backend Language**: PHP 8.2+ / 8.4 with strict typing.
- **Frontend**: Livewire 3, Alpine.js, Tailwind CSS v4, Monaco Editor, XTerm.js.
- **Data & State**: PostgreSQL 15/16, Redis 7 (Horizon queue broker and cache).
- **WebSockets**: Laravel Reverb (port 6001), Node.js Terminal WebSocket daemon (port 6002).
- **Sub-Projects**:
  - `webmail-ui/`: Lightweight PHP 8.3 / Nginx WildDuck email client.
  - `payverify/`: Multi-vector AI bank deposit verification suite (FastAPI, Next.js 16, Baileys WhatsApp bot, Android relay).
  - `docker/`: Unified compose stacks (Supabase BaaS, WildDuck Mail, Caddy TLS).

For detailed subsystem architecture and diagrams, consult:
- [Platform Architecture Reference](docs/architecture.md)
- [Database Schema & RLS Policies](docs/database-schema-and-rls.md)
- [Payments & PayVerify AI Verification](docs/payments-flow.md)
- [Mail System & STX Webmail](docs/mail-system.md)

---

## 2. Directory Layout & Module Ownership

```text
├── app/
│   ├── Actions/             # Domain actions (AsAction trait: object, job, or controller)
│   ├── Enums/               # PHP Enums (Role, EntitlementStatus, ProductType, LimitType)
│   ├── Http/Controllers/    # Storefront and REST API controllers (/api/v1/)
│   ├── Jobs/                # Background queue jobs (Horizon Redis queues)
│   ├── Livewire/            # UI components (Portal/, Admin/, Storefront/, Status/)
│   ├── Models/              # Eloquent models extending BaseModel (Auto-CUID2 UUIDs)
│   ├── Rules/               # Custom validation rules (ValidGitRepositoryUrl, etc.)
│   └── Services/            # Business logic (Commerce/, Supabase/, Backups/, RbacService)
├── bootstrap/helpers/       # Global helper functions (shared.php, services.php, socialite.php)
├── config/                  # Configuration files (commerce.php, supabase.php, hosting.php)
├── database/migrations/     # PostgreSQL schema migrations
├── docker/                  # Self-hosted services (caddy/, mail/, supabase/, webmail/)
├── docs/                    # Technical documentation and operational runbooks
├── payverify/               # PayVerify AI payment verification suite
├── routes/                  # Route definitions (web.php, api.php, ai.php, channels.php)
├── templates/compose/       # 31 active curated production templates
├── tests/                   # Pest 4 and PHPUnit 11 test suites
└── webmail-ui/              # Lightweight PHP 8.3 / Nginx WildDuck webmail client
```

---

## 3. Safe Areas vs Protected Areas (Do Not Touch)

### Safe Areas to Modify
- Application logic in `app/` (Actions, Models, Services, Livewire components, Jobs).
- Blade templates in `resources/views/`.
- New database migrations in `database/migrations/`.
- Automated test suites in `tests/`.
- Configuration files in `config/` (when adding verified options).

### Protected Areas (Strict Invariants)
- **Instance Sentinel Rows (`id = 0`)**: `Team::find(0)` (Root Team), `Server::find(0)` (Localhost), `InstanceSettings::find(0)`, `StandalonePostgresql::find(0)` (`stx-db`). **Never migrate, delete, resequence, or convert these to positive IDs.**
- **Existing Migration Files**: Never modify committed migrations. Always generate new migrations with `php artisan make:migration`.
- **Templates Archive (`templates/not-used/`)**: Contains 357 archived compose templates. Do not move or restore uncurated templates into `templates/compose/`.
- **Upstream Sync & Core Engine**: Never alter core Coolify sync primitives without verifying regression passes in `tests/Feature/UpstreamSyncAndAdaptationTest.php`.
- **Proxy Configuration**: Never add a `TRUSTED_PROXIES` setting or modify `TrustProxies`. Fix IP rate limits at call sites.
- **Future Features Policy & Expansion Freeze**: Provider API clients (`HetznerService`, `DigitalOceanService`, `VultrService`, `HostingerService`), Stripe payment gateways, and v5 architecture are preserved as groundwork but must remain strictly disabled/gated from customer checkout. The commercial fulfillment flow is strictly manual VPS review and delivery (24 to 72 hours) and bank transfer with PayVerify AI slip verification. Never promise deferred features in the UI, pricing pages, or documentation as available today.
- **Storefront & SEO Integrity Rules**: Never introduce hidden text, fake customer reviews, or keyword stuffing. Public storefront pages must be server-rendered Blade templates (crawlable without client JavaScript execution). Structured data (Schema.org JSON-LD) must accurately mirror visible on-page content.

---

## 4. Security Rules & Invariants

1. **Zero Secret Leaks**:
   - Never commit API keys, private keys, passwords, database credentials, or Supabase service-role keys.
   - Use `.env.example` templates with descriptive placeholders.
2. **Zero-Leak OTP Policy**:
   - Banking SMS OTP codes are held strictly in transient server memory with a 60-second TTL.
   - Never write OTPs to SQLite, PostgreSQL, persistent flash, or application logs.
3. **Pure-Pixel Proof Sanitization**:
   - All customer-uploaded deposit slips are decoded and re-encoded (`imagejpeg` / `imagepng`) in `ProofStorageService`, permanently purging malicious polyglot webshell payloads.
4. **Pessimistic Locking on Financial State**:
   - Invoices, payment proofs, and entitlements must be updated inside database transactions using row-level pessimistic locks (`lockForUpdate()`) to eliminate race conditions.
5. **Data Masking**:
   - The `ApiSensitiveData` middleware masks sensitive keys and internal IDs from responses unless requested by instance owners/admins.
   - In the UI, banking credentials require account password re-authentication to reveal for 30 seconds.

---

## 5. Multi-Tenant Scoping & Permission Rules

1. **Authorize Server-Side**:
   - Never rely on UI `@can` directives for security. Always enforce authorization with policies, gates, or `$this->authorize(...)`.
2. **Session Team vs Resource Team**:
   - Use the session team (`currentTeam()`, `Model::ownedByCurrentTeam()`) **only** for top-level index listings and creating new resources.
   - Once an existing resource is loaded, **all authorization and related queries must use that resource's team** (`$resource->team_id`). Users can switch teams in another tab while a page stays open.
3. **Role Hierarchy**:
   - `Role::OWNER` (3) > `Role::ADMIN` (2) > `Role::MEMBER` (1).
   - Use `RbacService::isOwner()`, `RbacService::isAdmin()`, or `RbacService::isClient()`.

---

## 6. Coding Conventions

- **Laravel 10 File Structure**:
  - Middleware in `app/Http/Middleware/`.
  - Kernels: `app/Http/Kernel.php` and `app/Console/Kernel.php`.
  - Exception handler: `app/Exceptions/Handler.php`.
  - No `bootstrap/app.php` application configuration.
- **Validation**:
  - Use inline `Validator::make()` in controllers and Livewire components, or custom rules in `app/Rules/`. Do not use Form Request classes.
- **Eloquent Models**:
  - Use Eloquent queries: prefer `Model::query()` over raw `DB::` queries.
  - Define model attribute casts in a `casts()` method rather than the legacy `$casts` property.
- **Formatting**:
  - Run Laravel Pint before finalizing changes: `vendor/bin/pint --dirty --format agent`.
- **Nixpacks**:
  - Nixpacks is deprecated. Never recommend Nixpacks to users. Recommend Dockerfile or Railpack instead.
- **Non-Root Sudo Shell Commands**:
  - Remote commands execute through `parseCommandsByLineForSudo()`. Test pipelines, redirects, and subshells against non-root users.

---

## 7. Common Pitfalls & Operational Traps

1. **Sentinel ID = 0 Pagination Trap**:
   - In PHP, `empty(0)` evaluates to `true`. Keyset pagination `where('id', '>', $cursor)` starting at 0 skips `id = 0`. Use `chunkById()` or pagination queries without a strict positive lower bound.
2. **Browser Test `Class "Redis" not found`**:
   - The Pest browser in-process amphp server requires array maintenance store if host PHP lacks phpredis:
     ```php
     config()->set('app.maintenance.store', 'array');
     ```
3. **Browser Test Fresh User Onboarding Loop**:
   - Fresh users redirect to `/onboarding`. In `beforeEach`, update team boarding and flush cache:
     ```php
     Team::query()->update(['show_boarding' => false]);
     Cache::flush();
     ```
4. **Stale Playwright Processes**:
   - If browser tests hang, kill orphan Playwright server instances:
     ```powershell
     Get-Process -Name node | Where-Object { $_.CommandLine -match "playwright" } | Stop-Process -Force
     ```
5. **Livewire Component Key Stability**:
   - In dynamic lists, always give Livewire components stable keys based on record UUIDs or IDs. Never use `$loop->index`.
   - Never broadcast a single refresh event to both parent and dynamically inserted children.

---

## 8. Definition of Done (DoD)

Before marking any task, feature, or bug fix complete:

1. **Functional Correctness**: The code satisfies all specified behavioral requirements without regressions.
2. **Security & Boundary Enforcement**: Tenant isolation is strictly preserved; authorization runs server-side; row locks protect state transitions; no secrets are exposed.
3. **Test Verification**:
   - Pest feature or unit tests are written or updated covering happy paths, edge cases, and failure modes.
   - Tests execute and pass cleanly: `php artisan test --compact [test-path]`.
4. **Code Style & Formatting**:
   - Code formatted with Pint: `vendor/bin/pint --dirty --format agent`.
5. **Documentation Alignment**:
   - If architecture, schemas, environment variables, or workflows changed, the corresponding files in `docs/` and `README.md` are updated.
