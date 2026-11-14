# Contributing to STX Hosting

Welcome to the STX Hosting development guide. To understand the overall architecture and technologies, please refer to the [Tech Stack](TECH_STACK.md) and [Agents Reference](AGENTS.md).

---

## Table of Contents
1. [Prerequisites & Development Setup](#1-prerequisites--development-setup)
2. [Environment Configuration](#2-environment-configuration)
3. [Running the Application](#3-running-the-application)
4. [Self-Hosted Production Stack (Docker Compose)](#4-self-hosted-production-stack-docker-compose)
5. [Database Migrations & Seeders](#5-database-migrations--seeders)
6. [Testing & Verification Suites](#6-testing--verification-suites)
7. [Template & Logo Catalog Guidelines](#7-template--logo-catalog-guidelines)
8. [Dev Tools & Server-Timing](#8-dev-tools--server-timing)
9. [Resetting the Development Environment](#9-resetting-the-development-environment)

---

## 1. Prerequisites & Development Setup

Ensure you have the following installed locally:
- **Docker Engine & Docker Compose v2** (or Docker Desktop / Orbstack)
- **PHP 8.2+** with `pdo_pgsql`, `redis`, `gd`, `curl`, `mbstring`, `fileinfo`
- **Composer 2.x**
- **Node.js 20+** & **npm**

---

## 2. Environment Configuration

1. In the repository root, copy the environment template:
   ```bash
   # Standard local development:
   cp .env.example .env
   # Or using the local dev container defaults:
   cp .env.development.example .env
   ```
2. For self-hosted multi-service deployments (including Supabase and WildDuck Mail):
   ```bash
   cp .env.selfhosted.example .env
   cp .env.supabase.example .env.supabase
   ```
3. Generate your application key if running outside Docker:
   ```bash
   php artisan key:generate
   ```

---

## 3. Running the Application

### Option A: Standard Dev Container Stack (Recommended)
Run the development stack with Docker Compose:
```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up
```

### Option B: Local CLI Server
If running PHP and PostgreSQL directly on your host machine:
```bash
composer install
npm install
npm run build
php artisan migrate --seed
php artisan serve --port=8000
```

### Platform Endpoints (All on Port 8000)
- **Storefront**: `http://localhost:8000`
- **Customer Portal**: `http://localhost:8000/portal`
- **Email Dashboard**: `http://localhost:8000/portal/mail`
- **Application Deploy Wizard**: `http://localhost:8000/portal/deploy`
- **Public Status Telemetry**: `http://localhost:8000/status`
- **Admin Provisioning Operations**: `http://localhost:8000/admin/operations/provisioning`
- **Payment Verification Queue**: `http://localhost:8000/admin/billing/proofs`
- **Template Catalog Studio**: `http://localhost:8000/admin/commerce/templates`
- **Mail Plan Studio**: `http://localhost:8000/admin/commerce/mail`
- **Laravel Horizon**: `http://localhost:8000/horizon` (Root account only)
- **Mailpit (Email Catcher)**: `http://localhost:8025`

Default development credentials:
- **Email**: `test@example.com`
- **Password**: `password`

---

## 4. Self-Hosted Production Stack (Docker Compose)

STX Hosting provides a unified production compose file (`docker-compose.selfhosted.yml`) that coordinates:
- **STX Hosting Core Application** (Laravel 12, Reverb, Terminal daemon)
- **PostgreSQL 15** & **Redis 7**
- **Self-Hosted Supabase** (Kong Gateway, GoTrue Auth, PostgREST, Realtime, Storage, Studio)
- **WildDuck Email Hosting** (Haraka SMTP, Zone-MTA, Dovecot IMAP, MongoDB)
- **STX Webmail UI** (`webmail-ui` containerized PHP-FPM / Nginx)
- **Caddy Reverse Proxy** (Automatic ACME TLS management)

To launch the unified self-hosted stack:
```bash
docker compose -f docker-compose.selfhosted.yml up -d
```

---

## 5. Database Migrations & Seeders

Whenever you switch branches or update database schema:
```bash
docker exec -it stx php artisan migrate
```

To seed the commercial hosting catalog, product pricing, and native status monitors:
```bash
docker exec -it stx php artisan db:seed --class=HostingCatalogSeeder
docker exec -it stx php artisan db:seed --class=NativeStatusMonitorSeeder
```

To reset the database completely:
```bash
docker exec -it stx php artisan migrate:fresh --seed
docker exec -it stx php artisan db:seed --class=HostingCatalogSeeder
docker exec -it stx php artisan db:seed --class=NativeStatusMonitorSeeder
```

---

## 6. Testing & Verification Suites

### Running Pest & PHPUnit Tests
STX Hosting uses **Pest 4** for unit and feature testing:

```bash
# Run all tests
php artisan test --compact

# Run specific feature domains
php artisan test --compact tests/Feature/Commerce/
php artisan test --compact tests/Feature/Supabase/SupabaseIntegrationTest.php
php artisan test --compact tests/Feature/UpstreamSyncAndAdaptationTest.php
php artisan test --compact tests/Feature/DockerComposeInjectionCompatibilityTest.php

# Run single test
php artisan test --compact --filter=testName
```

### Browser Tests (Pest Browser Plugin / Playwright)
Browser tests run headless Chromium via Playwright:
```bash
# Run all browser tests
php artisan test --compact tests/v4/Browser/
```

### Adversarial Hardening & Verification Scripts
```bash
python tests/verification/test_adversarial_gate2_verification.py
python tests/verification/test_remediation_verification.py
```

---

## 7. Template & Logo Catalog Guidelines

### Curated Active Templates (`templates/compose/`)
The platform maintains **31 active, verified production templates** (e.g. Supabase, n8n-postgresql, WordPress, Ghost, Plausible, Nextcloud, Strapi, Uptime Kuma).
- Active templates must validate against `DockerComposeInjectionCompatibilityTest`.
- Legacy or unhosted templates (357 items) are archived in `templates/not-used/` to keep the catalog clean and reliable.
- Template resource limits and internal ports are configured in `app/Livewire/Admin/Commerce/TemplateCatalogStudio.php`.

### Curated Logos & SVGs (`public/svgs/`)
The repository maintains **61 active icons/logos** in `public/svgs/`.
- Unused icons are kept in `public/svgs/not-used/`.
- The global helper `getServiceLogo()` (`bootstrap/helpers/services.php`) automatically checks `public/svgs/` first, falling back to `public/svgs/not-used/` or a placeholder SVG to ensure no broken icons are displayed.

---

## 8. Dev Tools & Server-Timing

STX Hosting includes built-in Server-Timing headers and a bottom-right HUD pill on full HTML pages.

| Setting | Effect |
|---|---|
| `APP_ENV=local` and `SERVER_TIMING_ENABLED` unset | **On** (default in dev) |
| `SERVER_TIMING_ENABLED=true` | **On** in any env, including production |
| `SERVER_TIMING_ENABLED=false` | **Off** even when `APP_ENV=local` |

Metrics reported: `app`, `db`, `php`, `dbslow` (ms), `queries`, `html` (bytes), and `mem` (MB).

---

## 9. Resetting the Development Environment

If your Docker containers or database state become corrupted:

1. Stop all containers: `docker compose down` (or `Ctrl + C`).
2. Remove container volumes:
   ```bash
   docker volume rm stx_dev_backups_data stx_dev_postgres_data stx_dev_redis_data stx_dev_stx_data stx_dev_minio_data
   ```
3. Restart the containers and rerun migrations:
   ```bash
   docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
   docker exec -it stx php artisan migrate:fresh --seed
   docker exec -it stx php artisan db:seed --class=HostingCatalogSeeder
   docker exec -it stx php artisan db:seed --class=NativeStatusMonitorSeeder
   ```

---

## 10. Technical Documentation Index

For deep-dives into specific subsystems, see the comprehensive guides in [`docs/`](docs/):
- [Platform Architecture Reference](docs/architecture.md)
- [Database Schema & RLS Policies](docs/database-schema-and-rls.md)
- [Authentication & Authorization Flows](docs/auth-flows.md)
- [Domains, DNS & Automated SSL](docs/domains-dns-ssl.md)
- [Multi-Node Server Management](docs/node-management.md)
- [Mail System & STX Webmail](docs/mail-system.md)
- [Payments & PayVerify AI Verification](docs/payments-flow.md)
- [GitHub App Integration](docs/github-integration.md)
- [API Reference & Developer Guide](docs/api-reference.md)
- [Deployment & Operational Runbook](docs/deployment-operations.md)
- [Troubleshooting & Known Gotchas](docs/troubleshooting.md)
- [AI Coding Agent Guidelines](AGENTS.md)

