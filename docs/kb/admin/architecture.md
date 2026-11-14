---
title: How it fits together
summary: The pieces, the flows, services, and architecture overview.
order: 8
---
## The shape

The platform is built on the robust core engine (teams, servers, applications, databases, reverse proxies, and scheduled tasks) combined with a high-performance commercial hosting layer, self-hosted Supabase BaaS, WildDuck email hosting, and native status telemetry.

Customers are tenant teams isolated on shared or dedicated nodes; administrators and platform owners belong to the root team (`team_id === 0`).

---

## Order to service lifecycle

1. **Cart and checkout**: Customer selects a product/plan on the storefront or portal. `OrderService` generates the order and invoice with canonical prices verified directly from the database.
2. **Receipt upload & sanitization**: Customer uploads bank transfer or cash deposit slip (`ProofStorageService`). Raster uploads are re-encoded via pure-pixel processing (`imagejpeg`/`imagepng`) to scrub polyglot webshells, stored privately, and quarantined.
3. **Pessimistic staff review**: Staff reviews the slip in `ProofReviewQueue` using pessimistic row-level locking (`lockForUpdate()`). Approval marks the invoice `paid` and activates the service entitlement.
4. **Deployment gating**: `DeploymentGate` and `LimitEnforcementService` enforce server-side quotas on CPU, RAM, container counts, and template authorizations before queueing or starting containers.
5. **VPS provisioning**: VPS orders enter the provisioning queue (`VpsFulfillmentService`) for manual server allocation, encrypted root credential storage, and customer delivery notification.
6. **Automated renewals & suspensions**: 
   - 7 days before plan expiry, `GenerateRenewalInvoicesJob` automatically issues renewal invoices and notifies customers.
   - If an invoice remains unpaid after the 4-day grace period, `ProcessOverdueSuspensionsJob` suspends active services and restricts deployment gates.
7. **Refunds**: Platform staff can issue full or partial refunds via `RefundService`, logging audit entries and rolling back entitlements.

---

## Where code lives

| Area | Location | Key Classes / Description |
|---|---|---|
| Permissions & RBAC | `config/stx-permissions.php`, `app/Services/RbacService.php` | 3-tier hierarchy (Owner, Administrator, Client) |
| Commerce & Orders | `app/Services/Commerce/`, `app/Models/Commerce/` | Orders, Invoices, PaymentProofs, Entitlements, Refunds |
| Supabase Integration | `app/Services/Supabase/`, `docker/supabase/` | `SupabaseClient`, `SupabaseQueryBuilder`, `SupabaseAuthService` |
| Email Hosting | `app/Services/Commerce/WildDuckProvisioningService.php`, `docker/mail/`, `webmail-ui/` | WildDuck API, Mailbox/Domain models, STX Webmail UI |
| VPS Management | `app/Services/Vps/`, `app/Livewire/Portal/VpsPanel.php` | Hypervisor inventory, telemetry polling, delivery SLA |
| Platform Backups | `app/Services/Backups/`, `app/Jobs/RestoreDatabaseBackupJob.php` | Multi-target DB restores, S3/R2 volume backup snapshots |
| Support & Chat | `app/Services/Chat/`, `app/Livewire/Support/` | Real-time live chat with Reverb WebSockets and attachments |
| Notifications & Emails | `app/Services/Notifications/`, `app/Jobs/Commerce/` | Dynamic email template catalog, Discord webhooks, scheduled jobs |
| Storefront & Status | `app/Http/Controllers/Storefront/`, `app/Livewire/Status/` | Native PHP storefront on port 8000, 90-day status telemetry |
| Curated Templates | `templates/compose/`, `app/Livewire/Admin/Commerce/TemplateCatalogStudio.php` | 31 verified templates with cgroups injection |

---

## Background queues and schedules

- **Queue**: Critical customer emails and urgent alerts use the `high` queue. Background deployments, DNS polling, and restorations run on standard Redis queues supervised by Laravel Horizon.
- **Scheduled Tasks**:
  - `php artisan status:probe`: Probes node ports and calculates telemetry metrics every minute.
  - `php artisan vps:collect-stats`: Collects CPU/RAM/disk usage across managed nodes every minute.
  - `php artisan vps:check-delivery-sla`: Verifies pending VPS delivery turnaround times hourly.
  - `GenerateRenewalInvoicesJob`: Evaluates expiring entitlements daily and generates renewal invoices.
  - `ProcessOverdueSuspensionsJob`: Evaluates overdue unpaid invoices daily and enforces suspensions.
  - `PollCustomDomainDnsJob`: Periodically checks custom domain DNS propagation.

---

## Production capabilities & extensions

- **Self-Hosted Supabase**: Deployable via `docker-compose.selfhosted.yml` or dedicated templates; complete with Kong Gateway, GoTrue, PostgREST, Realtime, Storage, and Studio.
- **WildDuck Email Hosting**: Pooled storage plans with Haraka SMTP, Zone-MTA, Dovecot IMAP, and containerized STX Webmail UI.
- **Automated Reverse Proxying**: Traefik handles dynamic application routing and automated Let's Encrypt certificates; Caddy manages mail and webmail TLS endpoints.
- **Multi-Target Database Restores**: Restore any database backup to its original container, a newly spawned standalone container, or a custom target database.
- **Volume Backups**: Schedule customer container persistent volume snapshots to local storage or Cloudflare R2 / S3.
- **Status Alert Subscriptions**: Public visitors can subscribe to incident and maintenance email notifications with one-click verification.
