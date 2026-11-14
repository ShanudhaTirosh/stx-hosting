# CLAUDE.md: AI Coding Agent Operating Guide

This repository contains the **STX Enterprises Cloud Platform & Hosting Control Plane**.

For complete architecture, invariants, and guidelines, refer to the authoritative [AGENTS.md](AGENTS.md) and [docs/](docs/).

---

## Essential Principles for Claude & AI Agents

1. **Architecture & Framework**:
   - **Laravel 12** running in the explicit **Laravel 10 directory structure** (`app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php`). Do NOT introduce a `bootstrap/app.php` slim layout.
   - PHP 8.4+ with strict types, constructor property promotion, and explicit return types.
   - Livewire 3 with Alpine.js and Tailwind CSS v4.

2. **Strict Invariants**:
   - **Instance Sentinel Rows (`id = 0`)**: `Team::find(0)` (Root Team), `Server::find(0)` (Localhost), `InstanceSettings::find(0)`. Never resequence, delete, or migrate these rows to positive integers.
   - **Zero Secret Leaks**: Never hardcode API keys, service role keys, passwords, or tokens.
   - **Zero-Leak OTP Policy**: Banking OTPs must remain strictly in transient memory (60s TTL) and never touch SQLite, PostgreSQL, or persistent logs.
   - **Pure-Pixel Sanitization**: User-uploaded receipts must be re-encoded (`imagejpeg`/`imagepng`) before writing to storage.
   - **Row-Level Concurrency Locks**: Invoices, payment proofs, and entitlements must be updated using `lockForUpdate()`.
   - **Multi-Tenant Scoping**: Once a resource is loaded, always scope permissions and child lookups to `$resource->team_id`, never the session team.

3. **Key Commands**:
   ```bash
   # Run automated test suites (Pest 4)
   php artisan test --compact
   php artisan test --compact tests/Feature/Commerce/
   php artisan test --compact tests/v4/Browser/LoginTest.php

   # Format PHP code
   vendor/bin/pint --dirty --format agent

   # Asset compilation
   npm run build
   ```

4. **Reference Documentation**:
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
