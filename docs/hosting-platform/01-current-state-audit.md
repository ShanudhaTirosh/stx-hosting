# Deliverable A: Current-State Codebase Audit & Storefront Inventory

**Document Reference:** `docs/hosting-platform/01-current-state-audit.md`  
**Repository:** `stx-enterprises/stx-hosting` with Integrated ShanuTechX Ecosystem (`web/` & `status/`)  
**Core Version:** STX Hosting `4.5.4` (`versions.json:4`, `config/constants.php:8`)  
**Date of Audit:** October 2026  
**Auditor:** Teamwork Architecture & Engineering Implementation Group (`worker_m1`)  
**Integrity Mode:** Genuine Development & Production Architecture Audit

---

## 1. Executive Summary & Audit Scope

This document provides an exhaustive, evidence-based architectural, codebase, and infrastructure audit of the current workspace. The repository contains three primary application sub-systems:
1. **STX Hosting Control Plane (Root Repository)**: A self-hosted PaaS and multi-server management control plane built on **Laravel 12.70.1** (using the explicit Laravel 10 directory structure), **PHP 8.4**, **Livewire 3.8.10**, **Tailwind CSS 4.3.3**, **PostgreSQL 15/16**, **Redis 7**, **Laravel Horizon 5.50.0**, and **Laravel Reverb 1.12.0**.
2. **ShanuTechX Public Storefront (`web/`)**: A marketing, catalog, pricing comparison, and developer documentation portal (`@shanutechx/web` v0.1.0) built on **Next.js 14.2.35 (App Router)**, **React 18.3.1**, and **Tailwind CSS 3.4.7** featuring dark glassmorphism styling, Three.js / D3-geo interactive 3D globe visualization, a client-side shopping cart context, and 18 public pages.
3. **ShanuTechX Status Platform (`status/`)**: An autonomous, self-hosted infrastructure uptime monitoring and incident management platform (`@shanutechx/status` v1.0.0) built on **Next.js 15.1.0 (App Router)**, **React 19.0.0**, **Tailwind CSS 4.0.0**, and embedded **SQLite (`sqlite3 5.1.7`)** with a multi-protocol daemon (HTTP/HTTPS, TCP port, ICMP ping), 5-channel notification dispatchers, and a password-protected `/admin` studio.

### Key Audit Conclusions

```
┌──────────────────────────────────────────────────────────────────────────────────┐
│                         PUBLIC STOREFRONT (`web` Port 3000)                      │
│   Next.js 14 App Router | React 18 | Tailwind CSS 3 | Glassmorphism UI           │
│   18 Public Pages: Landing, Pricing, VPS, Apps, Bots, DBs, Docs, Reviews...      │
│   Status: Production-grade UI; delegates checkout/auth via external redirects.   │
└────────────────────────────────────────┬─────────────────────────────────────────┘
                                         │ Browser Navigation / External Redirects
                                         ▼
┌──────────────────────────────────────────────────────────────────────────────────┐
│             MISSING INTERMEDIARY: HOSTING MANAGEMENT API GATEWAY                 │
│   - Multi-tenant Customer Authentication & RBAC (Customer vs Administrator)     │
│   - Order & Invoice State Machine (Pending -> Verifying -> Paid -> Provisioning) │
│   - Payment Proof Submission & Verification Queue (Manual Bank Transfer)         │
│   - Quota Enforcement & Least-Privilege Proxy to STX Hosting REST API                │
└──────────────────────┬───────────────────────────────────┬───────────────────────┘
                       │ Internal Telemetry / Probes       │ Authenticated REST API
                       ▼                                   ▼
┌──────────────────────────────────────┐   ┌──────────────────────────────────────┐
│       STATUS PLATFORM (`status`)     │   │      COOLIFY CONTROL PLANE (Root)    │
│   Next.js 15 App Router | React 19   │   │   Laravel 12 / PHP 8.4 / Livewire 3  │
│   Port 3003 | SQLite (status.db)     │   │   Port 8000 (App) / 6001 (Reverb)    │
│   - Autonomous Monitoring Daemon     │   │   - Cloud Provider Provisioning:     │
│     (HTTP/HTTPS, TCP, ICMP Ping)     │   │     Hetzner, DigitalOcean, Vultr,    │
│   - 5-Channel Alerts (Discord, etc.) │   │     Hostinger Services               │
│   - Admin Incident & Monitor Studio  │   │   - SSH & Docker Provisioning Engine │
│   - Privacy Masking for Public IPs   │   │   - Traefik/Caddy Automated SSL      │
└──────────────────────────────────────┘   └──────────────────────────────────────┘
```

1. **Storefront & Status Readiness**: The frontend assets (`web/` and `status/`) are aesthetically polished, functionally mature, and directly usable for the commercial hosting platform. `web/` provides complete sales collateral, pricing comparisons (LKR and USD), and developer docs. `status/` provides autonomous infrastructure health checks, multi-channel alerting, and incident tracking.
2. **Core STX Hosting Capabilities**: The STX Hosting backend provides production-ready orchestration for Linux servers, Docker containers, multi-container compose services, standalone databases, S3 backups, and Traefik Let's Encrypt SSL. Crucially, it already contains battle-tested cloud provider provisioning clients (`HetznerService`, `DigitalOceanService`, `VultrService`, `HostingerService`).
3. **Core Commercial Gaps**: Neither STX Hosting nor the Next.js apps currently contain a commercial hosting order-to-activation engine, database models for hosting products/orders/invoices, manual bank transfer payment proof verification queues, or customer tenant isolation boundaries.
4. **Critical Security Imperative**: STX Hosting's native team model grants `Team Admin` and `Team Owner` full root access, arbitrary container bind mounts, and terminal execution on host servers (`SECURITY.md:31-37`). Customers must **never** be granted administrative access to shared STX Hosting host nodes. Customer VPS hosting must be delivered via isolated virtual machines, and application hosting must be mediated by a dedicated customer portal and API gateway.

---

## 2. Technology Stack & Package Inventory

### 2.1 Backend Core (STX Hosting Engine)
- **Runtime & Language**: PHP `^8.4` (`composer.json:14`).
- **Web Framework**: Laravel `12.70.1` (`laravel/framework: ^12.70.1`, `composer.json:20`).
  - Uses Laravel 10 structural layout with dedicated `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`, and `app/Providers/`.
- **Database Engine**: PostgreSQL 15/16 (`docker-compose.prod.yml:58-69`) with `doctrine/dbal: ^4.5.0`.
- **In-Memory Cache & Lock Store**: Redis 7 (`docker-compose.prod.yml:71-80`).
- **Queue Supervisor**: Laravel Horizon `5.50.0` (`laravel/horizon: ^5.50.0`, `composer.json:21`).
  - Active Redis Queues: `high`, `deployments`, `maintenance`, `crons`, `webhooks` (`bootstrap/helpers/shared.php:1078-1129`).
- **Real-Time WebSockets**: Laravel Reverb `1.12.0` (`laravel/reverb: ^1.12.0`, `composer.json:25`) running as an s6 daemon on port `6001`.
- **Terminal WebSockets**: Node.js terminal daemon on port `6002` secured via `App\Services\TerminalSessionService` (`routes/web.php:340-380`).
- **Authentication & Security**:
  - `laravel/fortify: ^1.40.0` (2FA, passkeys, email verification).
  - `@laravel/passkeys: ^0.4.0` (`package.json:21`).
  - `laravel/sanctum: ^4.3.3` (API tokens with `read`, `write`, `deploy`, `read:sensitive`, `write:sensitive`, `root` abilities).
  - `laravel/socialite: ^5.30.0` (Authentik, Clerk, Discord, Google, Infomaniak, Microsoft Azure, Zitadel).
  - `phpseclib/phpseclib: ^3.0.57` (Pure-PHP SSH2 and SFTP client).
  - `visus/cuid2: ^7.0.0` (CUID2 model identifiers).
- **Payment Processing**: `stripe/stripe-php: ^16.6.0` (`composer.json:56`).
- **Domain Actions**: `lorisleiva/laravel-actions: ^2.12.0` (`composer.json:35`).
- **OpenAPI Documentation**: `zircote/swagger-php: ^6.11.0`.

### 2.2 Storefront Platform (`web/`)
- **Package Name & Version**: `@shanutechx/web` v0.1.0 (`web/package.json:2-3`).
- **Framework & Runtime**: Next.js `14.2.35` (App Router) with React `18.3.1` and React DOM `18.3.1`.
- **Styling**: Tailwind CSS `3.4.7` with Autoprefixer `10.4.19` and PostCSS `8.4.40`.
- **Graphics & 3D**: Three.js `0.170.0`, `@react-three/fiber: 8.15.19`, `@react-three/drei: 9.99.7`, `three-globe: 2.31.1`, `d3-geo: 3.1.1`, `topojson-client: 3.1.0`.
- **Animation**: `framer-motion: 11.18.2`.
- **Typography & Icons**: `geist: 1.3.0`, `lucide-react: 1.39.0`.
- **Language**: TypeScript `5.5.4` (`@types/node: 20.14.0`, `@types/react: 18.3.3`).
- **Default Port**: Port `3000` (`next dev -p 3000`, `next start -p 3000`).

### 2.3 Status Platform (`status/`)
- **Package Name & Version**: `@shanutechx/status` v1.0.0 (`status/package.json:2-3`).
- **Framework & Runtime**: Next.js `15.1.0` (App Router) with React `19.0.0` and React DOM `19.0.0`.
- **Styling**: Tailwind CSS `4.0.0` with `@tailwindcss/postcss: 4.0.0`, `clsx: 2.1.1`, `tailwind-merge: 2.5.5`.
- **Database**: SQLite 3 (`sqlite3: 5.1.7` stored at `status/data/status.db`).
- **Authentication**: `jose: 6.2.3` (HS256 JWT) + Node.js native `crypto.scrypt`.
- **Email Dispatcher**: `nodemailer: 6.9.16`.
- **Validation**: `zod: 3.24.1`.
- **Language**: TypeScript `5.7.2` (`@types/node: 22.10.2`, `@types/react: 19.0.2`).
- **Default Port**: Port `3003` (`next dev -p 3003`, `next start -p 3003`).

### 2.4 Comparative Technology Inventory Matrix

| Technology Dimension | STX Hosting Core | ShanuTechX Web (`web/`) | ShanuTechX Status (`status/`) |
|---|---|---|---|
| **Role in Platform** | Infrastructure Orchestrator & PaaS | Public Storefront & Marketing | Public Status & Fleet Health |
| **Language & Engine** | PHP 8.4 (Laravel 12) | TypeScript (Node.js 20+) | TypeScript (Node.js 22+) |
| **Frontend Framework** | Livewire 3.8.10 + Alpine.js | Next.js 14.2.35 (React 18) | Next.js 15.1.0 (React 19) |
| **CSS System** | Tailwind CSS v4.3.3 | Tailwind CSS v3.4.7 | Tailwind CSS v4.0.0 |
| **Primary Persistence** | PostgreSQL 15/16 (`coolify-db`) | Stateless (Browser `localStorage`) | SQLite 3 (`status.db`) |
| **Queue / Asynchrony** | Redis 7 + Laravel Horizon | Client Promises | Server Lifecycle Daemon (`register()`) |
| **Auth Mechanism** | Fortify + Sanctum Bearer Tokens | Redirects to Dashboard URL | Jose HS256 JWT (`sp_session` cookie) |
| **WebSockets / Events**| Laravel Reverb (Port 6001) | None (Polled API) | Polling & SSE-ready |
| **Containerization** | Docker Engine & Docker Compose | Dockerfile (Standalone) | Dockerfile & Docker Compose |

---

## 3. Storefront & Frontend Inventory (`web/`)

### 3.1 Route Hierarchy & Page Breakdown
Inspection of `web/app/` reveals 18 public pages and structured routing:

```
web/app/
├── layout.tsx                  # Root layout: Geist font, dynamic metadata, CartProvider wrapper
├── page.tsx                    # Main landing page: OrbitHero, Marquee, BentoGrid, Pricing, Testimonials
├── pricing/page.tsx            # Multi-category pricing plans & feature comparison matrix
├── vps/page.tsx                # Cloud VPS showcase: provider tabs (DO, Linode, Hetzner), duration toggle
├── app-hosting/page.tsx        # Git & ZIP web application deployment showcase
├── bot-hosting/page.tsx        # 24/7 Discord, Telegram, & WhatsApp bot runtime showcase
├── databases/page.tsx          # Managed PostgreSQL, MySQL, Redis, & MongoDB showcase
├── catalog/page.tsx            # 1-Click preconfigured Docker Compose templates (WordPress, N8n, Plausible)
├── v2ray/page.tsx              # Store notice directing consumer proxy users to store.shanutechx.com
├── docs/page.tsx               # Client Documentation Hub with 7 developer guides & code examples
├── contact/page.tsx            # Support channels, hotline numbers, WhatsApp link, inquiry form
├── about/page.tsx              # Company mission, global edge infrastructure footprint, and SLAs
├── reviews/page.tsx            # Customer review wall with rating filters and review submission modal
├── status/page.tsx             # Built-in status consumer page polling status API
├── terms/page.tsx              # Terms of Service covering VPS reselling, upstream providers, SLAs
├── privacy/page.tsx            # Privacy Policy
├── refund/page.tsx             # Refund Policy
├── cookies/page.tsx            # Cookie Usage Policy
├── 403/page.tsx, 503/page.tsx  # Custom HTTP error pages
├── error.tsx, not-found.tsx    # Next.js App Router error handling boundaries
├── robots.ts, sitemap.ts       # Dynamic SEO metadata endpoints
```

### 3.2 Visual Design System & Component Library
The storefront implements a dark-theme glassmorphism UI defined in `web/tailwind.config.js`:
- **Palette**: `bg` (`#0A0A14`), `surface` (`#13131C`), `surface-2` (`#1A1A28`), `accent-violet` (`#7C3AED`), `accent-teal` (`#0EA5E9`), `accent-magenta` (`#D946EF`), `text-primary` (`#F5F5F7`), `text-muted` (`#9A9AA5`), `border` (`rgba(255,255,255,0.08)`).
- **Core Reusable Components**:
  - `Navbar.tsx` (1,011 lines): Responsive header with scroll-reactive padding, mega-menu dropdowns, Ctrl+K modal search, cart drawer trigger, and user profile state.
  - `OrbitHero.tsx` & `HeroCanvas.tsx`: High-impact animated hero with glowing background meshes, real-time floating metric pills, and call-to-action buttons.
  - `GlobeOrb.tsx`: Interactive D3-geo canvas rendering an orthographic 3D globe with 12 international server locations (Colombo, Singapore, Tokyo, Frankfurt, London, Los Angeles, New York) and dynamic curved telemetry packet arcs.
  - `BentoGrid.tsx` (1,800 lines): Multi-tiered feature grid highlighting VPS, Git deployments, bot runtimes, and databases with interactive hover lighting.
  - `Pricing.tsx` & `VpsCatalogShowcase.tsx`: Plan specification cards, feature tables, LKR/USD price formatters, and annual discount calculations.
  - `ReviewCarousel.tsx`: Auto-scrolling verified customer review carousel with review submission modal.
  - `Footer.tsx` (`StxFooter.tsx`): 500-line comprehensive footer with sitemap links, real-time operational status badge, and newsletter capture.
  - `motion/*`: Utility motion components (`Reveal.tsx`, `TiltCard.tsx`, `BorderBeam.tsx`, `CountUp.tsx`, `MagneticButton.tsx`).

### 3.3 Dynamic Catalog Contract & Data Models (`web/lib/api.ts`)
The storefront communicates with an API backend via typed interfaces:
```typescript
export interface Plan {
  id: string;
  productId: string;
  name: string;
  price: number; // in cents or smallest currency unit
  currency: string;
  billingType: string;
  isPopular?: boolean;
  badge?: string | null;
  specsJson?: Record<string, any> | null;
  isActive: boolean;
}

export interface Product {
  id: string;
  category: string;
  name: string;
  slug: string;
  description?: string | null;
  iconUrl?: string | null;
  isPopular?: boolean;
  badge?: string | null;
  isActive: boolean;
  plans?: Plan[];
}
```

### 3.4 Shopping Cart Context (`web/context/CartContext.tsx`)
- Items stored in `localStorage` under `stx_web_cart`.
- Manages `items`, `addItem()`, `removeItem()`, `updateQuantity()`, `clearCart()`, `totalItems`, and `totalPrice`.
- **Checkout Handoff Logic**:
  ```typescript
  const proceedToCheckout = () => {
    const dashboardBase = process.env.NEXT_PUBLIC_DASHBOARD_URL || 'http://localhost:3001';
    if (items.length === 0) return;
    const firstItem = items[0];
    const planIds = items.map((i) => i.id).join(',');
    const totalPrice = items.reduce((sum, i) => sum + i.price * i.quantity, 0);
    const targetUrl = `${dashboardBase}/orders?planId=${encodeURIComponent(firstItem.id)}&planIds=${encodeURIComponent(planIds)}&price=${totalPrice}`;
    window.location.href = targetUrl;
  };
  ```

### 3.5 External Dashboard Redirection Mapping (`web/next.config.js`)
The storefront delegates client management to an external dashboard (`NEXT_PUBLIC_DASHBOARD_URL`, default `http://localhost:3001`):
```javascript
async redirects() {
  const dashboardUrl = process.env.NEXT_PUBLIC_DASHBOARD_URL || 'http://localhost:3001';
  return [
    { source: '/dashboard', destination: `${dashboardUrl}/dashboard`, permanent: false },
    { source: '/dashboard/:path*', destination: `${dashboardUrl}/dashboard/:path*`, permanent: false },
    { source: '/login', destination: `${dashboardUrl}/login`, permanent: false },
    { source: '/register', destination: `${dashboardUrl}/login`, permanent: false },
    { source: '/reset-password', destination: `${dashboardUrl}/reset-password`, permanent: false },
    { source: '/orders', destination: `${dashboardUrl}/orders`, permanent: false },
    { source: '/services', destination: `${dashboardUrl}/services`, permanent: false },
    { source: '/tickets', destination: `${dashboardUrl}/tickets`, permanent: false },
    { source: '/settings', destination: `${dashboardUrl}/settings`, permanent: false },
    { source: '/webmail', destination: `${dashboardUrl}/webmail`, permanent: false },
  ];
}
```

---

## 4. Status Platform Inventory (`status/`)

### 4.1 Architecture & Runtime Engine
`status` runs an autonomous monitoring daemon embedded directly in Next.js 15:
- **Server Lifecycle Hook (`status/instrumentation.ts`)**:
  Initializes SQLite schema, runs database migrations, seeds default monitors, elevates `UV_THREADPOOL_SIZE` to `64`, configures `dns.setDefaultResultOrder("ipv4first")`, and starts `monitorManager.start()`.
- **SQLite Database (`status/data/status.db`) Schema**:
  1. `checks`: Historical execution logs (`id`, `site_id`, `ts`, `ok`, `latency_ms`, `status_code`, `error`). Indexed on `(site_id, ts)`.
  2. `monitors`: Target configurations (`id`, `name`, `type` [http/tcp/ping], `url`, `host`, `port`, `method`, `expected_status`, `keyword`, `interval_seconds`, `timeout_ms`, `enabled`, `sort_order`).
  3. `settings`: Key-value JSON configuration (`branding`, `notifications`, `retention`, `admin`).
  4. `incidents`: Disruption and maintenance events (`id`, `title`, `type` [incident/maintenance], `status`, `impact`, `monitors`, `scheduled_start`, `scheduled_end`, `resolved_at`).
  5. `incident_updates`: Timeline update messages for incidents (`id`, `incident_id`, `status`, `message`, `created_at`).

### 4.2 Multi-Protocol Probing Engine (`status/features/status/server/`)
- **HTTP/HTTPS Prober (`checkHttp`)**: Uses persistent keep-alive agents, follows redirects (up to 5 hops), detects anti-bot challenges (`cf-mitigated`, `x-vercel-mitigated`), validates status codes and body keywords.
- **TCP Socket Prober (`checkTcp`)**: Opens raw `net.Socket` to `host:port` measuring connection latency.
- **ICMP Ping Prober (`checkPing`)**: Spawns `ping -c 1` to parse round-trip latency.
- **State Change & Grace Period**: Retries failed checks after a 1-second delay before confirming a transition to prevent false alerts.
- **Retention Pruning**: Daily cron prunes check logs older than the configured threshold (default: 90 days).

### 4.3 Alert Dispatchers & Notification Engine
Supports 5 alerting channels upon state change (`status/features/notifications/server/notifications.ts`):
1. **Email (SMTP)**: Configurable SMTP host, port, TLS, and sender/recipient addresses.
2. **Discord Webhook**: Sends styled rich embeds with status colors (green `0x2ecc71`, red `0xe74c3c`, orange `0xe67e22`), affected monitor names, and timestamps.
3. **Microsoft Teams**: Dispatches formatted `MessageCard` payloads.
4. **Google Chat**: Dispatches structured cards via webhook.
5. **Telegram Bot**: Dispatches notifications via Telegram Bot API (`sendMessage`).

### 4.4 Admin Studio (`status/app/admin/`)
- **Route**: `/admin`, `/admin/incidents`, `/admin/settings`.
- **Security**: Edge-compatible HS256 JWT cookie (`sp_session`) verified via `jose` and passwords hashed using `crypto.scrypt`.
- **Management Capabilities**: Monitor CRUD, incident declaration, maintenance window scheduling, timeline updates, branding settings, notification channel configuration, and data retention rules.
- **Public Privacy Safeguard**: `status/app/api/dashboard/route.ts` strips private IP addresses and internal hostnames from public payloads to prevent infrastructure leakage.

---

## 5. Core STX Hosting Backend & Infrastructure Inventory

### 5.1 Route Hierarchy & Access Gating
Inspection of `routes/web.php` establishes that the STX Hosting root repository is strictly an administrative control plane:
- **Root Route (`/`)**:
  ```php
  // routes/web.php:210
  Route::middleware(['auth', 'verified'])->group(function () {
      Route::get('/', Dashboard::class)->name('dashboard');
  ```
  Unauthenticated visitors are unconditionally redirected to `/login` (`routes/web.php:680-686`).
- **Control Plane Navigation (`resources/views/components/navbar.blade.php`)**:
  Exclusively links to internal administrative entities: `/projects`, `/servers`, `/canvas`, `/storages`, `/shared-variables`, `/team`, `/settings`, `/terminal`, `/profile`.
- **Zero Commercial Hosting Routes**: No public storefront, cart, order review, or customer service management views exist in the root repository.

### 5.2 Subscription & Billing Architecture (STX Hosting Native)
STX Hosting contains a production Stripe integration (`app/Actions/Stripe/`, `app/Jobs/StripeProcessJob.php`), but it is hard-coded for **STX Hosting Cloud's SaaS subscription** (charging $5/month base + $3/month per connected server limit):
- Component: `App\Livewire\Subscription\PricingPlans` (`app/Livewire/Subscription/PricingPlans.php:1-69`).
- Gating: Disabled in self-hosted mode (`if (! isCloud()) return redirect(RouteServiceProvider::HOME);`).
- Quantity Sync: Automatically updates Stripe subscription quantity as servers are added (`UpdateSubscriptionQuantity.php`).
- **Deficiencies for Commercial Hosting**:
  - Zero support for selling fixed VPS tiers or application hosting plans.
  - Zero support for one-time orders, setup fees, or manual bank wire transfers.
  - Zero support for customer payment proof uploads or administrative payment approval queues.
  - Invoices are not stored or generated internally in PostgreSQL; relies entirely on external Stripe invoice objects.

### 5.3 Identity, Teams & Sentinel IDs
- **User Model (`app/Models/User.php`)**: Users belong to teams via `team_user` pivot with roles `member`, `admin`, `owner`.
- **Sentinel IDs (`AGENTS.md:Instance sentinels (id = 0)`)**:
  - `User ID 0`: Instance root administrator.
  - `Team ID 0`: Instance root team (`isAdminFromSession()` evaluates membership in Team 0).
  - `Server ID 0`: Localhost server where STX Hosting runs.
  - `InstanceSettings ID 0`: Global instance configuration singleton.
  - `StandalonePostgresql ID 0`: STX Hosting's internal database (`coolify-db`).

### 5.4 Pre-Built Cloud Provider Provisioning Engine (Reusable Asset!)
STX Hosting contains production-grade REST clients for major cloud VPS providers in `app/Services/`:
- **Hetzner Cloud (`App\Services\HetznerService`)**:
  - Fully implemented methods: `getServerTypes()`, `getLocations()`, `getImages()`, `getSshKeys()`, `createServer()`, `powerOnServer()`, `deleteServer()`, `enableServerBackup()`.
  - Automated backoff and rate-limit (`429`) handling.
- **DigitalOcean (`App\Services\DigitalOceanService`)**:
  - Implemented methods: `getSizes()`, `getRegions()`, `getImages()`, `getSshKeys()`, `createDroplet()`, `deleteDroplet()`.
- **Vultr (`App\Services\VultrService`)**:
  - Implemented methods: `getPlans()`, `getRegions()`, `getOperatingSystems()`, `createServer()`, `deleteServer()`.
- **Hostinger (`App\Services\HostingerService`)**:
  - Implemented methods: `getCatalog()`, `getDataCenters()`, `getTemplates()`, `createServer()`, `deleteServer()`.
- **Validation Job (`ValidateAndInstallServerJob`)**:
  - Verifies SSH reachability, validates OS distribution, installs prerequisites (`curl`, `wget`, `git`, `docker`, `docker-compose`), provisions Traefik/Caddy proxy, and configures the `coolify` Docker network.

---

## 6. Comprehensive Gap Analysis & Component Matrix

| Category | Component / Feature | Codebase Status | Evidence / File Path | Reusability / Action Required |
|---|---|:---:|---|---|
| **Storefront** | Public Marketing Landing Page | ✅ **Working** | `web/app/page.tsx`, `web/components/OrbitHero.tsx` | Ready to deploy as public face; update CTAs to API Gateway. |
| **Storefront** | Multi-Category Pricing Page | ✅ **Working** | `web/app/pricing/page.tsx`, `Pricing.tsx` | Fully functional UI; connect to backend `/api/products` endpoint. |
| **Storefront** | Cloud VPS Infrastructure Page | ✅ **Working** | `web/app/vps/page.tsx`, `VpsCatalogShowcase.tsx` | Fully functional UI; maps hardware specs (`specsJson`). |
| **Storefront** | Developer Documentation Hub | ✅ **Working** | `web/app/docs/page.tsx` | Complete 7-guide hub with interactive code snippets. |
| **Storefront** | Shopping Cart Context & Drawer | ⚠️ **Partial** | `web/context/CartContext.tsx`, `Navbar.tsx:867` | Drawer renders placeholder text; wire up items and checkout button. |
| **Storefront** | Customer Reviews & Submission | ⚠️ **Partial** | `web/app/reviews/page.tsx`, `ReviewCarousel.tsx` | Carousel works with fallbacks; submission endpoint needs backend route. |
| **Storefront** | STX Hosting Root Public Storefront | ❌ **Missing** | `routes/web.php:210,680` | Root route redirects to `/login`. Storefront served by `web/`. |
| **Fleet Status** | Multi-Protocol Health Prober | ✅ **Working** | `status/features/status/server/monitor.ts` | Complete HTTP/HTTPS, TCP, and ICMP monitoring daemon. |
| **Fleet Status** | SQLite Persistence & Metrics | ✅ **Working** | `status/lib/db.ts:149-223` | Schema auto-migrates; checks logged with latency and status. |
| **Fleet Status** | Multi-Channel Notifications | ✅ **Working** | `status/features/notifications/server/` | Dispatches alerts to Discord, Telegram, Email, Teams, Google Chat. |
| **Fleet Status** | Admin Incident & Monitor Studio | ✅ **Working** | `status/app/admin/` | Complete monitor CRUD, incident management, maintenance windows. |
| **Fleet Status** | Built-in Status Page in Storefront | ⚠️ **Partial** | `web/app/status/page.tsx` | 766 lines of UI; fetches `/api/status/public` (needs endpoint adapter). |
| **Billing** | STX Hosting SaaS Server Limits Billing | ✅ **Working** | `app/Actions/Stripe/CreateCheckoutSession.php` | Reusable for Stripe Checkout; only handles SaaS server limits. |
| **Billing** | Dynamic Hosting Product Catalog | ❌ **Missing** | Missing in root DB | Must create `hosting_products` & `hosting_product_prices` schema. |
| **Billing** | Hosting Order & Invoice Schema | ❌ **Missing** | Missing in root DB | Must create `hosting_orders`, `hosting_invoices`, `hosting_order_items`. |
| **Billing** | Manual Bank Transfer & Proof Upload | ❌ **Missing** | Missing across all repos | Must build customer proof upload and admin verification queue. |
| **Security** | Role-Based Access Control | ⚠️ **Partial** | `app/Enums/Role.php`, `app/Policies/` | Only supports team-scoped Member/Admin/Owner. Needs Customer role. |
| **Security** | Customer VPS Isolation | ❌ **Missing** | Missing in STX Hosting | Must configure customer user SSH keys separately from root key. |
| **Orchestration**| Hetzner Cloud Provisioning | ✅ **Working** | `app/Services/HetznerService.php` | 100% production-ready for automated VPS VM creation. |
| **Orchestration**| DigitalOcean Provisioning | ✅ **Working** | `app/Services/DigitalOceanService.php` | 100% production-ready for automated Droplet creation. |
| **Orchestration**| Vultr & Hostinger Provisioning | ✅ **Working** | `app/Services/VultrService.php`, `HostingerService.php` | 100% production-ready for multi-cloud VPS scaling. |
| **Orchestration**| Server Validation & Docker Install | ✅ **Working** | `app/Jobs/ValidateAndInstallServerJob.php` | Reusable; automated SSH test and Docker Engine bootstrap. |
| **Orchestration**| Git & Docker App Deployment | ✅ **Working** | `app/Jobs/ApplicationDeploymentJob.php` | Reusable; pulls Git repos, builds containers, mounts volumes. |
| **Orchestration**| Traefik / Caddy Reverse Proxy & SSL | ✅ **Working** | `app/Services/Proxy/`, `Traefik.php` | Fully automated Let's Encrypt SSL certificate generation. |

---

## 7. Discovered Inconsistencies, Configuration Bugs & Security Risks

### 7.1 Configuration & Runtime Bugs
1. **Status Docker Compose Port Mismatch**:
   - `status/docker-compose.yaml:6` maps `34500:3000`.
   - `status/Dockerfile:15` sets `ENV PORT=3003` and `status/package.json:9` sets `next start -p 3003`.
   - **Impact**: In Docker, incoming requests to port `34500` fail with connection refused because the containerized Next.js server listens on port `3003`.
   - **Remediation**: Update `docker-compose.yaml` port mapping to `"34500:3003"`.
2. **Web Dockerfile Workspace Build Context**:
   - `web/Dockerfile:14,22` references `COPY apps/web/package.json` and `COPY apps/web`.
   - **Impact**: Building the Docker container directly inside `web/` fails because paths were moved to `./web/`.
   - **Remediation**: Update `web/Dockerfile` to reference `./package.json` and `./`.
3. **ICMP Ping Command Compatibility**:
   - `status/features/status/server/monitor.ts:251` executes `ping -c 1`.
   - **Impact**: On Windows development workstations, `ping -c` is rejected as an invalid flag (Windows requires `ping -n 1`).
   - **Remediation**: Check `process.platform === 'win32'` to toggle between `-n 1` and `-c 1`.
4. **API Contract Mismatch for Storefront Status Page**:
   - `web/app/status/page.tsx` polls `${apiBase}/api/status/public`.
   - `status` exposes `/api/dashboard`, `/api/incidents`, and `/api/settings/public`.
   - **Impact**: The built-in `/status` page in `web` fails to load live health telemetry.
   - **Remediation**: Implement a Next.js rewrite or API proxy route forwarding `/api/status/public` to `status/api/dashboard`.

### 7.2 Critical Security Risks
1. **STX Hosting Shared Host Administrative Access Hazard**:
   - In STX Hosting, `Team Admin` and `Team Owner` roles grant arbitrary root command execution on the managed server (`SECURITY.md:31-37`).
   - **Impact**: If shared application hosting customers are added as STX Hosting team admins on a multi-tenant server, they can access host Docker sockets (`/var/run/docker.sock`) and escalate to host root.
   - **Remediation**: Customers must **never** be given direct STX Hosting administrative credentials or access to the STX Hosting control plane. Customer actions must be mediated by a dedicated Customer Portal proxying restricted commands to the STX Hosting API.
2. **Default Production Credentials in `status/.env`**:
   - `status/.env:3,5` contains hardcoded credentials: `ADMIN_PASSWORD=changeme` and `SESSION_SECRET=please-change-this-to-a-long-random-string`.
   - **Impact**: High risk of unauthorized administrative takeover if deployed without setting environment overrides.
   - **Remediation**: Force mandatory cryptographically random secret generation before deployment.
3. **Unvalidated Query Parameter Price Handoff**:
   - In `web/context/CartContext.tsx:81`, the price is passed via URL query parameter: `?price=${totalPrice}`.
   - **Impact**: Price tampering risk if the receiving dashboard trusts query parameters.
   - **Remediation**: Order creation in the backend must ignore client-submitted prices and calculate authoritative totals from `hosting_product_prices` in the database.

---

## 8. Strategic Recommendations for Business Platform Implementation

1. **Retain and Deploy `web/` as the Public Storefront**:
   - Deploy `web/` (Next.js 14) on port `3000` (or behind Cloudflare/Traefik at `shanutechx.com`).
   - Connect `web/lib/api.ts` to the hosting backend API for dynamic product catalog and pricing retrieval.
   - Refactor `Navbar.tsx` cart drawer to list active cart items and trigger `proceedToCheckout()`.
2. **Deploy `status/` as the Fleet Uptime Monitor**:
   - Deploy `status/` (Next.js 15) on port `3003` (or at `status.shanutechx.com`).
   - Configure monitors pointing to STX Hosting host servers, Traefik proxy ingress, and customer application domains.
   - Expose public telemetry to the storefront via `/api/dashboard`.
3. **Build the Central Hosting API Gateway**:
   - Implement the intermediate hosting backend providing customer authentication, product catalog CRUD, order state machine, payment proof upload, and admin verification queues.
   - Mediate all infrastructure operations to STX Hosting via team-scoped Sanctum tokens with exact abilities.
4. **Phased VPS Rollout**:
   - **Phase 1**: Manual VPS allocation from pre-provisioned server inventory + Application Hosting via STX Hosting.
   - **Phase 2**: Automated VPS provisioning leveraging existing `HetznerService` and `DigitalOceanService` clients.
