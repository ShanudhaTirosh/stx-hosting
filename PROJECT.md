# Project: STX Hosting Production Commercial Hardening & Cloud Platform

## Architecture
The platform is built on Laravel 12 (Laravel 10 structure), Livewire 3, Tailwind CSS v4, PostgreSQL 15/16, Horizon (Redis queues), and Reverb (WebSockets). It is engineered into a production-ready, commercial-grade hosting and deployment platform featuring:

1. **Three-Tier Role-Based Access Control (RBAC)**:
   - **Owner (Platform/Root)**: Root team (`team_id === 0`) / instance owners with unrestricted platform-wide access, unlimited resources, global configuration, template & server management, limit override capability.
   - **Administrator**: Platform administrators with granted administrative access across clients, projects, deployments, services, template catalogs, and servers; client limit management; bypasses client limits.
   - **Client**: Strictly restricted to their own projects, servers, deployments, and purchased services; bounded by configurable limits and active entitlements.
2. **Centralized Client Restrictions & Resource Management**:
   - Global default limits with overrides per-client, per-plan, and per-product.
   - Server-side enforcement across projects, deployments, running containers, applications, CPU, RAM, storage, bandwidth, servers, databases, and SMTP.
   - Owners and Administrators bypass client limits.
3. **Purchase-Based Hosting & Entitlement System**:
   - Models: `HostingProduct`, `HostingPlan`, `HostingOrder`, `HostingInvoice`, `HostingEntitlement`, `ServiceInstance`, `ServiceCredential`.
   - Active purchases automatically unlock deployment permissions and resource quotas.
   - Subscription lifecycle management: upgrades, renewals, overdue suspensions, expirations, cancellations with server-side revocation.
   - Manual admin management (grant, revoke, suspend, modify, refund).
4. **Curated Deployment Template & Catalog Access Control**:
   - Curated active 31-template catalog (`templates/compose/`) featuring Supabase, n8n, WordPress, Ghost, Plausible, Nextcloud, Strapi, and developer tools.
   - Inactive templates archived safely in `templates/not-used/`; active SVG logos curated to 61 in `public/svgs/` with automatic fallback in `getServiceLogo()`.
   - Template Catalog Studio (`/admin/commerce/templates`) with global toggles, plan bindings, category filters, and container cgroups resource limits.
5. **Self-Hosted Supabase Backend-as-a-Service**:
   - Dedicated self-hosted containerized Supabase stack (`docker/supabase/docker-compose.yml`) with Kong API Gateway, Supabase GoTrue Auth, PostgREST, Realtime, Storage, Studio UI, and PostgreSQL 15 with `pg_cron`.
   - Native integration via `SupabaseClient`, `SupabaseQueryBuilder`, and `SupabaseAuthService`.
6. **Managed Email Hosting & STX Webmail UI**:
   - WildDuck mail stack (`docker/mail/docker-compose.yml`) with WildDuck API, Haraka SMTP, Zone-MTA, Dovecot IMAP, and MongoDB.
   - 4 pooled storage plans (Starter, Basic, Pro, Business) with modular mailbox, domain, and storage add-ons (`MailPlan`, `MailSubscription`, `MailMailbox`, `MailDomain`, `MailAlias`, `MailAddon`).
   - STX Webmail UI (`webmail-ui`): containerized PHP-FPM/Nginx client with portal deep-linking.
   - Automated TLS certificates managed via Caddy reverse proxy (`docker/caddy/Caddyfile`).
7. **Multi-Target Database Restores & Volume Backups**:
   - Multi-target restore engine (`RestoreDatabaseBackupJob.php`) allowing restore to original container, new standalone database, or custom target schema.
   - Application volume backups to local disks and Cloudflare R2/S3 storage with scheduling and retention quotas.
8. **Automated Commercial Billing & Lifecycles**:
   - Slip verification queue (`/admin/billing/proofs`) with pure-pixel image sanitization (`imagejpeg`/`imagepng`) and pessimistic row locking (`lockForUpdate()`).
   - Automated renewal invoices generated 7 days before expiry (`GenerateRenewalInvoicesJob`).
   - Automated overdue service suspension after a 4-day grace period (`ProcessOverdueSuspensionsJob`).
   - Administrative refund engine (`RefundService`) with ledger tracking and entitlement rollbacks.
9. **Native High-Performance Storefront & Privacy Telemetry**:
   - High-performance marketing storefront and documentation running natively on PHP / Blade on port 8000 (`/`, `/vps`, `/pricing`, `/databases`, `/game-servers`, `/email-hosting`, `/docs`).
   - Public Status platform (`/status`) with 90-day uptime bars, privacy-preserving host node masking, incident history, and subscriber alert broadcasts.
10. **Upstream Coolify Compatibility**:
    - Continuously synced with post-Oct-29 Coolify upstream releases (v4.6.0 through v4.7.5) with STX Hosting adaptations and regression test coverage (`UpstreamSyncAndAdaptationTest.php`).

---

## Code Layout

- `app/Enums/`:
  - `Role.php`: Three-tier role definitions (Owner, Administrator, Client) with hierarchical comparisons.
  - `EntitlementStatus.php`, `ProductType.php`, `LimitType.php`, `ContainerStatusTypes.php`.
- `app/Models/`:
  - `User.php`, `Team.php`, `Server.php`, `Project.php`, `Environment.php`, `Application.php`, `Service.php`.
  - `HostingProduct.php`, `HostingPlan.php`, `HostingOrder.php`, `HostingInvoice.php`, `HostingEntitlement.php`, `HostingLimit.php`, `TemplatePermission.php`, `CustomDomain.php`, `BackupRestore.php`.
  - `Commerce/`: `Order.php`, `Invoice.php`, `PaymentProof.php`, `ServiceInstance.php`, `ServiceCredential.php`, `StatusSubscription.php`, `MailPlan.php`, `MailSubscription.php`, `MailDomain.php`, `MailMailbox.php`, `MailAlias.php`, `MailAddon.php`.
- `app/Services/`:
  - `RbacService.php`: Three-tier authorization logic, platform role checks, admin permissions.
  - `LimitEnforcementService.php`: Centralized resource calculation, limit validation, and server-side blocking.
  - `EntitlementService.php`: Purchase validation, entitlement lifecycle, quota unlocking.
  - `TemplateAccessService.php`: Catalog filtering, plan-based template gating, and deployment permission validation.
  - `Commerce/`: `OrderService.php`, `PaymentReviewService.php`, `ProofStorageService.php`, `RefundService.php`, `CustomDomainService.php`, `AppProvisioningService.php`, `VpsFulfillmentService.php`, `WildDuckProvisioningService.php`.
  - `Supabase/`: `SupabaseClient.php`, `SupabaseQueryBuilder.php`, `SupabaseAuthService.php`.
  - `Backups/`: `BackupPolicyService.php`.
  - `Status/`: `StatusAlertService.php`.
  - `Notifications/`: `EmailTemplates.php`, `StaffEvents.php`.
- `app/Jobs/`:
  - `Commerce/`: `GenerateRenewalInvoicesJob.php`, `ProcessOverdueSuspensionsJob.php`, `PollCustomDomainDnsJob.php`.
  - `RestoreDatabaseBackupJob.php`, `ApplicationDeploymentJob.php`.
- `app/Http/Controllers/`:
  - `Storefront/StorefrontController.php`: Native PHP marketing storefront routes and cart management.
  - `Status/StatusSubscriptionController.php`: Public status alert subscription signups and verification.
  - `Api/Commerce/`: `OrderCheckoutController.php`, `ProductCatalogController.php`.
- `app/Livewire/`:
  - `Admin/`: `Billing/ProofReviewQueue.php`, `Commerce/TemplateCatalogStudio.php`, `Commerce/MailPlanStudio.php`, `Status/StatusStudio.php`, `Operations/ProvisioningQueue.php`.
  - `Portal/`: `DeployWizard.php`, `Apps/AppList.php`, `Backups.php`, `Domains.php`, `Mail/MailDashboard.php`, `BillingOverview.php`, `VpsPanel.php`.
  - `Status/PublicStatus.php`.
- `docker/`:
  - `caddy/`: Caddyfile automated reverse proxy for mail and webmail TLS.
  - `mail/`: WildDuck, Haraka, Zone-MTA, Dovecot, MongoDB stack.
  - `supabase/`: Kong API gateway configuration and full Supabase stack.
  - `webmail/`: STX Webmail UI containerization.
  - `docker-compose.selfhosted.yml`: All-in-one unified self-hosted compose orchestrator.
- `templates/`:
  - `compose/`: 31 active curated production templates.
  - `not-used/`: 357 archived compose templates.
- `tests/Feature/`:
  - `Hosting/`: RBAC, resource limits, entitlements, templates, navigation tests.
  - `Commerce/`: Renewals and suspensions, refund workflow, HTTPS and template deployment, multi-target restore and volume backups, status alerts.
  - `Supabase/SupabaseIntegrationTest.php`.
  - `UpstreamSyncAndAdaptationTest.php`.

---

## Milestones & Status

| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Test Suite Baseline & Core Bug Resolutions | Fix existing test failures, audit/repair broken UI routes, buttons, forms, and validation states | none | COMPLETED |
| M2 | Three-Tier RBAC Architecture & Tenant Isolation | Implement Owner, Administrator, Client roles, policies, gates, middleware, and strict tenant isolation | M1 | COMPLETED |
| M3 | Centralized Client Restrictions & Server-Side Limits | Global defaults, custom client/plan limits, server-side blocking on creation/deployment, Owner/Admin bypass | M2 | COMPLETED |
| M4 | Purchase-Based Hosting & Entitlements Management | Products, plans, orders, invoices, entitlements lifecycle, quota unlocking, and admin controls | M3 | COMPLETED |
| M5 | Deployment Template & Catalog Access Control | Template permissions, plan bindings, category restrictions, client catalog filtering, deployment gating | M4 | COMPLETED |
| M6 | Dual Track E2E Test Suite & Adversarial Hardening | Pass 100% of Dual Track E2E Test Suite (Tiers 1-4) and Tier 5 Adversarial Coverage Hardening | M5 | COMPLETED |
| M7 | Commercial Hosting Platform Launch (Phases 0–8) | Security headers, CSP, proof quarantine, printable PDF invoices, VPS telemetry, live chat, R2 backups | M6 | COMPLETED |
| M8 | Native Storefront, Status Telemetry & Commerce Automation | Native PHP storefront on port 8000, 90-day status heatmap, alert subscriptions, renewal jobs, suspensions, refunds | M7 | COMPLETED |
| M9 | Self-Hosted Supabase BaaS & WildDuck Mail Hosting | Containerized Supabase stack, WildDuck mail hosting, STX Webmail UI, Caddy TLS, and curated 31-template catalog | M8 | COMPLETED |
| M10 | Upstream Coolify Sync & Corpus Maintenance | Integrate post-Oct-29 Coolify upstream changes (v4.6.0 to v4.7.5) with full regression coverage | M9 | COMPLETED |

---

## Interface Contracts

### M2 (RBAC) ↔ M3 (Limits)
- `RbacService::isOwner(User $user, ?Team $team = null): bool`
- `RbacService::isAdmin(User $user, ?Team $team = null): bool`
- `RbacService::isClient(User $user, ?Team $team = null): bool`
- `RbacService::shouldBypassLimits(User $user): bool` — returns `true` if user is Owner or Administrator.

### M3 (Limits) ↔ M4 (Entitlements)
- `LimitEnforcementService::getEffectiveLimits(Team $team): Collection`
- `LimitEnforcementService::canCreateResource(Team $team, string $resourceType, array $attributes = []): LimitCheckResult`
- `LimitEnforcementService::canDeployResource(Team $team, Model $resource): LimitCheckResult`
- `LimitCheckResult`: contains `isAllowed(): bool`, `reason(): ?string`, `currentUsage(): int|float`, `maxLimit(): int|float`.
- `EntitlementService::getActiveEntitlements(Team $team): Collection`
- `EntitlementService::hasCapability(Team $team, string $capability): bool`

### M4 (Entitlements) ↔ M5 (Templates & Commerce)
- `TemplateAccessService::canDeployTemplate(Team $team, string $templateNameOrId): bool`
- `TemplateAccessService::filterAuthorizedTemplates(Team $team, Collection $templates): Collection`
- `TemplateAccessService::getTemplateRequirements(string $templateNameOrId): array`
- `CustomDomainService::verifyDomain(CustomDomain $domain): bool`
- `RefundService::processRefund(Invoice $invoice, ?float $amount, string $reason, User $admin): RefundResult`
- `WildDuckProvisioningService::createMailbox(MailSubscription $subscription, array $data): MailMailbox`
- `SupabaseClient::from(string $table): SupabaseQueryBuilder`
