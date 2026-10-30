# Project: Coolify Production Commercial Hardening

## Architecture
The platform is built on Laravel 12 (Laravel 10 structure), Livewire 3, Tailwind CSS v4, PostgreSQL, Horizon (Redis queues), and Reverb (WebSockets). It is enhanced into a production-ready, commercial-grade hosting and deployment platform featuring:
1. **Three-Tier Role-Based Access Control (RBAC)**:
   - **Owner (Platform/Root)**: Root team (`team_id === 0`) / instance owners with unrestricted platform-wide access, unlimited resources, global configuration, template & server management, limit override capability.
   - **Administrator**: Platform administrators with granted administrative access across clients, projects, deployments, services, template catalogs, and servers; client limit management; bypasses client limits.
   - **Client**: Strictly restricted to their own projects, servers, deployments, and purchased services; bounded by configurable limits and active entitlements.
2. **Centralized Client Restrictions & Resource Management**:
   - Global default limits with overrides per-client, per-plan, and per-product.
   - Server-side enforcement across projects, deployments, running containers, applications, CPU, RAM, storage, bandwidth, servers, databases, and SMTP.
   - Owners and Administrators bypass client limits.
3. **Purchase-Based Hosting & Entitlement System**:
   - Models: `HostingProduct`, `HostingPlan`, `HostingOrder`, `HostingInvoice`, `HostingEntitlement`.
   - Active purchases automatically unlock deployment permissions and resource quotas.
   - Subscription lifecycle management: upgrades, downgrades, renewals, expirations, cancellations with server-side revocation.
   - Manual admin management (grant, revoke, suspend, modify).
4. **Deployment Template & Catalog Access Control**:
   - Granular template permissions: global toggles, product/plan bindings, per-client grants, category/registry restrictions.
   - Clients only view and deploy authorized templates.
5. **Security Boundaries & Multi-Tenant Data Isolation**:
   - Team/client data isolation across API endpoints, Livewire components, and background jobs.
   - Strict server-side authorization on all mutation and read operations.
6. **Testing & Verification**:
   - Pest / PHPUnit unit, feature, and integration test suites.
   - Multi-tier requirement-driven E2E tests.

---

## Code Layout
- `app/Enums/`:
  - `Role.php`: Three-tier role definitions (Owner, Administrator, Client) with hierarchical comparisons.
  - `EntitlementStatus.php`, `ProductType.php`, `LimitType.php`.
- `app/Models/`:
  - `User.php`, `Team.php`, `Server.php`, `Project.php`, `Environment.php`, `Application.php`, `Service.php`.
  - `HostingProduct.php`, `HostingPlan.php`, `HostingOrder.php`, `HostingInvoice.php`, `HostingEntitlement.php`, `HostingLimit.php`, `TemplatePermission.php`.
- `app/Services/`:
  - `RbacService.php`: Three-tier authorization logic, platform role checks, admin permissions.
  - `LimitEnforcementService.php`: Centralized resource calculation, limit validation, and server-side blocking.
  - `EntitlementService.php`: Purchase validation, entitlement lifecycle, quota unlocking.
  - `TemplateAccessService.php`: Catalog filtering, plan-based template gating, and deployment permission validation.
- `app/Policies/`:
  - Enhanced policies for `ServerPolicy`, `ApplicationPolicy`, `ServicePolicy`, `ProjectPolicy`, `DatabasePolicy`, `HostingProductPolicy`, `HostingEntitlementPolicy`.
- `app/Http/Middleware/`:
  - `EnforceClientLimits.php`: Validates client quotas before resource creation/deployment.
  - `EnforceEntitlements.php`: Validates required active purchases for paid services.
  - `ApiAbility.php`, `ApiSensitiveData.php`: Enhanced token ability clamping and data isolation.
- `app/Livewire/`:
  - Admin/Owner management views for limits, entitlements, products, and templates.
  - Client portal views for purchased services, active quotas, and authorized template catalog.
- `database/migrations/`:
  - Clean, dedicated migrations for hosting products, plans, orders, invoices, entitlements, limits, and template permissions.
- `tests/Feature/Hosting/`:
  - Automated tests covering RBAC, limits, entitlements, templates, and security boundaries.

---

## Feature Inventory
Every feature identified during the survey phase is mapped to an assigned milestone.

| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Baseline Test Suite Stabilization | Fix existing failing/flaky tests in tests/Feature and tests/Unit to achieve a clean baseline | M1 | survey_3 |
| 2 | Core UI/UX & Navigation Repairs | Fix broken routes, non-functional buttons, forms, modals, and missing error/loading states in existing Livewire components | M1 | survey_1, survey_3 |
| 3 | Three-Tier RBAC Core Definition | Define Owner, Administrator, and Client roles with hierarchy and permission comparisons | M2 | R2, survey_1 |
| 4 | Owner Unrestricted Access Engine | Ensure Owners (Root Team / Platform Owners) have unrestricted platform-wide access, unlimited resources, and limit override capability | M2 | R2, survey_1 |
| 5 | Administrator Delegation & Scoping | Implement Administrator role with configurable administrative permissions across clients, projects, servers, and subscriptions without client limits | M2 | R2, survey_1 |
| 6 | Client Tenant Scoping & Isolation | Scope Clients strictly to their own projects, servers, deployments, and purchased services with strict 403 rejection on cross-tenant access | M2 | R2, R6, survey_1 |
| 7 | Policy & Middleware Hardening | Update model policies, AuthServiceProvider, and API middleware to enforce the 3-tier RBAC server-side | M2 | R2, R6, survey_1 |
| 8 | Centralized Limits Data Model | Create database schema and models for global default limits and per-client / per-plan overrides | M3 | R3, survey_2 |
| 9 | Multi-Dimensional Quota Calculation | Implement calculation engine for projects, deployments, containers, applications, CPU, RAM, storage, bandwidth, servers, databases, and SMTP | M3 | R3, survey_2 |
| 10 | Server-Side Creation & Deployment Limit Gate | Block resource creation and deployment queueing when client quota is exceeded; bypass for Owners and Admins | M3 | R3, survey_2 |
| 11 | Container Runtime Limit Injection | Inject CPU, RAM, and swap limits into Docker Compose configs generated for client deployments | M3 | R3, survey_2, survey_3 |
| 12 | Admin Resource Limits Management UI | Interactive Livewire interface for Owners and Admins to configure global defaults and per-client custom overrides | M3 | R3, survey_3 |
| 13 | Hosting Product & Plan Catalog | Schema, models, and administration for hosting products (VPS, app hosting, managed DBs, SMTP, game servers, templates) | M4 | R4, survey_2, survey_3 |
| 14 | Entitlement Purchase & Activation Flow | Purchase or assignment of products automatically grants deployment permissions and resource quotas | M4 | R4, survey_2 |
| 15 | Subscription Lifecycle State Machine | Handle active, past_due, canceled, expired states with automated revocation of deployment capabilities | M4 | R4, survey_2, survey_3 |
| 16 | Manual Entitlement Management | Administrative actions to manually grant, revoke, suspend, or modify client entitlements | M4 | R4, survey_2 |
| 17 | Client Entitlement Dashboard & Paywall | Display active entitlements, quota usage, and clear upgrade/purchase prompts for unavailable services | M4 | R4, survey_1, survey_3 |
| 18 | Global & Plan-Based Template Toggles | Ability for Owners and Admins to toggle template availability globally and bind templates to plans | M5 | R5, survey_2 |
| 19 | Per-Client Template & Category Permissions | Grant specific templates or categories (Docker, Git, Databases) to individual clients | M5 | R5, survey_2 |
| 20 | Template Gating & Deployment Validation | Ensure clients only see and deploy templates authorized by active entitlements or explicit grants | M5 | R5, survey_2 |
| 21 | Template Resource Requirements Config | Configure minimum CPU, RAM, and storage requirements per template and enforce against client limits | M5 | R5, survey_2 |
| 22 | Comprehensive Dual Track E2E Test Suite | Tier 1 (Feature), Tier 2 (Boundaries), Tier 3 (Cross-Feature), Tier 4 (Real-World) automated test suite | M6 | R7 |
| 23 | Adversarial Coverage Hardening (Tier 5) | White-box adversarial testing, boundary probes, integrity checks, and regression prevention | M6 | R7 |

---

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Test Suite Baseline & Core Bug Resolutions | Fix existing test failures, audit/repair broken UI routes, buttons, forms, and validation states (R1, R7) | none | DONE |
| M2 | Three-Tier RBAC Architecture & Tenant Isolation | Implement Owner, Administrator, Client roles, policies, gates, middleware, and strict tenant isolation (R2, R6) | M1 | IN_PROGRESS |
| M3 | Centralized Client Restrictions & Server-Side Limits | Global defaults, custom client/plan limits, server-side blocking on creation/deployment, Owner/Admin bypass (R3, R6) | M2 | PLANNED |
| M4 | Purchase-Based Hosting & Entitlements Management | Products, plans, orders, invoices, entitlements lifecycle, quota unlocking, and admin controls (R4, R6) | M3 | PLANNED |
| M5 | Deployment Template & Catalog Access Control | Template permissions, plan bindings, category restrictions, client catalog filtering, deployment gating (R5, R6) | M4 | PLANNED |
| M6 | Final Milestone: 100% E2E Pass & Adversarial Hardening | Pass 100% of Dual Track E2E Test Suite (Tiers 1-4) and Tier 5 Adversarial Coverage Hardening (R1-R7) | M5, TEST_READY | PLANNED |

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

### M4 (Entitlements) ↔ M5 (Templates)
- `TemplateAccessService::canDeployTemplate(Team $team, string $templateNameOrId): bool`
- `TemplateAccessService::filterAuthorizedTemplates(Team $team, Collection $templates): Collection`
- `TemplateAccessService::getTemplateRequirements(string $templateNameOrId): array`
