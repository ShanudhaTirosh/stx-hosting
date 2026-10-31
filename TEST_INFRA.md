# STX Hosting Commercial Hardening: End-to-End (E2E) Test Infrastructure Specification

**Document Version**: 1.0.0  
**Status**: ACTIVE / SPECIFICATION & BASELINE  
**Architecture Track**: Dual Track — E2E Testing Track  
**Target Repository**: `stx-enterprises/stx-hosting` (Laravel 12 / Livewire 3 / PostgreSQL 15 / Redis 7)  
**Author**: Teamwork Test Writer (`e2e_test_writer_1`)  
**Scope**: Requirements R1–R7 (`ORIGINAL_REQUEST.md`), Features 1–23 (`PROJECT.md` § Feature Inventory)  

---

## 1. Testing Philosophy & Guiding Principles

### 1.1 Opaque-Box, Requirement-Driven Philosophy
The STX Hosting Commercial Hardening test infrastructure is built upon an uncompromising **opaque-box, requirement-driven methodology**:
1. **Opaque-Box Independence**: Tests interact with the platform strictly through its public interfaces, HTTP/REST endpoints (`/api/v1/*`), Livewire component actions (`$this->call()`, `$this->set()`), authorization gates (`Gate::allows()`, policies), and observable side effects (database persistence, queued jobs, emitted events, and container configurations). Tests never inspect or bind to private object state, ephemeral internal variables, or transient implementation details.
2. **Specification Authority**: The expected outputs, error messages, and state transitions are directly derived from the commercial hardening specification in `ORIGINAL_REQUEST.md` (R1–R7) and the Interface Contracts in `PROJECT.md`.
3. **Immutability of Behavioral Contracts**: Implementation details may evolve (e.g., refactoring database queries, swapping queue drivers, or adjusting UI blade structure), but the behavioral contract specified by tests must remain invariant. If an implementation changes, the tests serve as the ultimate oracle of regression prevention.
4. **Authoritative Derivation of Expectations**:
   - HTTP Statuses: `200 OK` (success), `201 Created` (resource creation), `400 Bad Request` (payload violation), `401 Unauthorized` (missing authentication), `403 Forbidden` (RBAC violation / tenant breach / paywall gate), `422 Unprocessable Content` (quota exceeded / limit validation error).
   - Multi-tenant Isolation: Queries must be scoped to the authenticated tenant/team. Cross-tenant reads and mutations must deterministically yield `403 Forbidden` or `404 Not Found` without disclosing protected resource metadata.
   - Resource Quotas: Resource creation and deployment dispatching must be blocked immediately upon quota saturation unless bypassed by Owner or Administrator privileges.

---

## 2. Multi-Tier Test Architecture (Tiers 1–4 + Tier 5)

The testing infrastructure enforces a four-tier pyramid (plus an adversarial hardening tier) ensuring complete depth and breadth:

```
                      ┌────────────────────────┐
                      │ Tier 5: Adversarial    │
                      │ Hardening & Pentest    │
                      ├────────────────────────┤
                      │ Tier 4: Real-World     │
                      │ Application Scenarios  │
                      ├────────────────────────┤
                      │ Tier 3: Cross-Feature  │
                      │ Combinations (Pairwise)│
                      ├────────────────────────┤
                      │ Tier 2: Boundary &     │
                      │ Corner Cases (Edge)    │
                      ├────────────────────────┤
                      │ Tier 1: Feature        │
                      │ Coverage (Happy Path)  │
                      └────────────────────────┘
```

### Tier 1: Feature Coverage (Baseline & Representative Inputs)
- **Target**: >= 5 test cases per feature across all 23 features (Minimum: 115 test cases).
- **Focus**: Standard operational flows, valid inputs, correct status codes, expected database records, and event emissions.

### Tier 2: Boundary & Corner Cases (Stress & Limits)
- **Target**: >= 5 test cases per feature across all 23 features (Minimum: 115 test cases).
- **Focus**: Extreme input sizes, boundary limits (e.g., limit = 0, limit = 1, limit = INT_MAX), zero-length strings, malicious payload types, unicode/special characters, concurrent race conditions, and network timeout simulations.

### Tier 3: Cross-Feature Combinations (Pairwise Interaction Matrix)
- **Target**: Pairwise combination coverage across the 4 foundational pillars:
  1. RBAC Roles (Owner vs. Admin vs. Client vs. Member)
  2. Limits & Quotas (Within Limit vs. At Limit vs. Exceeded)
  3. Entitlements & Subscriptions (None vs. Active vs. Past Due vs. Canceled vs. Expired)
  4. Template Permissions (Global Allowed vs. Plan-Gated vs. Client-Explicit vs. Forbidden)
- **Minimum Cases**: >= 20 comprehensive interaction scenarios.

### Tier 4: Real-World Customer Application Workflows
- **Target**: >= 5 full-lifecycle, multi-step customer workflows reflecting genuine commercial production usage.
- **Minimum Cases**: 5 complex end-to-end integration journeys.

### Tier 5: Adversarial Coverage Hardening
- **Target**: Security penetration probes, privilege escalation attempts, spoofed checkout payloads, Docker escape and volume injection checks, IDOR defenses.

---

## 3. Feature Inventory Coverage Mapping (Features 1–23)

Every feature defined in `PROJECT.md` § Feature Inventory is mapped to explicit test specifications across Tiers 1 and 2:

| Feature # | Feature Name | Milestone | Tier 1 Tests (>=5) | Tier 2 Tests (>=5) | Primary Test File |
|---|---|---|---|---|---|
| **F01** | Baseline Test Suite Stabilization | M1 | F01-T1-1: Compose validator dataset loads cleanly<br>F01-T1-2: Feature suite runs without syntax errors<br>F01-T1-3: Unit suite runs without memory exhaustion<br>F01-T1-4: RefreshDatabase isolation handles foreign keys<br>F01-T1-5: InstanceSettings sentinel id=0 persists | F01-T2-1: Empty dataset in injection test behaves safely<br>F01-T2-2: Corrupt YAML fixture handling<br>F01-T2-3: Keyset pagination around id=0 boundary<br>F01-T2-4: Concurrent test isolation under parallel runner<br>F01-T2-5: Database transaction rollback on unhandled error | `tests/Feature/Hosting/BaselineAndNavigationTest.php` |
| **F02** | Core UI/UX & Navigation Repairs | M1 | F02-T1-1: Dashboard renders authenticated routes<br>F02-T1-2: Server creation modal submits valid payload<br>F02-T1-3: Application settings form saves properties<br>F02-T1-4: Database creation form executes without error<br>F02-T1-5: Lazy components render real content | F02-T2-1: Malformed UUID in route parameters returns 404<br>F02-T2-2: Empty form submission triggers validation errors<br>F02-T2-3: Rapid double-click button submission is debounced<br>F02-T2-4: Huge input string in name fields is truncated/rejected<br>F02-T2-5: Missing Livewire snapshot data fails gracefully | `tests/Feature/Hosting/BaselineAndNavigationTest.php` |
| **F03** | Three-Tier RBAC Core Definition | M2 | F03-T1-1: Owner role enum rank comparison `gt(ADMIN)`<br>F03-T1-2: Admin role enum rank comparison `gt(CLIENT)`<br>F03-T1-3: Client role enum lowest rank comparison<br>F03-T1-4: RbacService correctly classifies Owner user<br>F03-T1-5: RbacService correctly classifies Admin and Client | F03-T2-1: Role comparison against invalid/null string<br>F03-T2-2: User with multiple team roles retains lowest on Client context<br>F03-T2-3: Role serialization in JSON and session<br>F03-T2-4: Role casing insensitivity (lowercase/uppercase)<br>F03-T2-5: Revocation of role immediately updates hierarchy | `tests/Feature/Hosting/RbacThreeTierTest.php` |
| **F04** | Owner Unrestricted Access Engine | M2 | F04-T1-1: Owner accesses root team (`team_id=0`)<br>F04-T1-2: Owner bypasses all resource limits<br>F04-T1-3: Owner overrides server node configuration<br>F04-T1-4: Owner accesses global platform settings<br>F04-T1-5: Owner manages administrators and client accounts | F04-T2-1: Owner with zero limits assigned still creates resources<br>F04-T2-2: Owner deploys template marked globally disabled<br>F04-T2-3: Owner accesses resources belonging to any client team<br>F04-T2-4: Owner modifies immutable system sentinel records<br>F04-T2-5: Owner actions logged with superuser audit flag | `tests/Feature/Hosting/RbacThreeTierTest.php` |
| **F05** | Administrator Delegation & Scoping | M2 | F05-T1-1: Admin accesses authorized administrative modules<br>F05-T1-2: Admin configures client resource limits<br>F05-T1-3: Admin creates and manages hosting products<br>F05-T1-4: Admin views customer projects and deployments<br>F05-T1-5: Admin bypasses client limits for support tasks | F05-T2-1: Admin cannot delete root team (0)<br>F05-T2-2: Admin cannot revoke Owner privileges<br>F05-T2-3: Admin with revoked permission immediately blocked<br>F05-T2-4: Admin cannot modify owner-only security credentials<br>F05-T2-5: Admin audit trails record delegated execution | `tests/Feature/Hosting/RbacThreeTierTest.php` |
| **F06** | Client Tenant Scoping & Isolation | M2 | F06-T1-1: Client views only own projects and servers<br>F06-T1-2: Client creates applications in own environment<br>F06-T1-3: Client requests to other team return 403/404<br>F06-T1-4: Client API token scoped strictly to client team<br>F06-T1-5: Client cannot see administrative settings | F06-T2-1: IDOR attempt on another client's application ID<br>F06-T2-2: Client attempts to modify own role via API<br>F06-T2-3: Client attempts to attach server from another team<br>F06-T2-4: Switching session team does not bypass resource team check<br>F06-T2-5: SQL injection / parameter tampering in tenant filter | `tests/Feature/Hosting/RbacThreeTierTest.php` |
| **F07** | Policy & Middleware Hardening | M2 | F07-T1-1: ServerPolicy rejects unauthorized client access<br>F07-T1-2: ApplicationPolicy validates team ownership<br>F07-T1-3: DatabasePolicy blocks cross-tenant operations<br>F07-T1-4: ApiAbility middleware clamps token abilities<br>F07-T1-5: ApiSensitiveData masks secrets from member roles | F07-T2-1: Token with spoofed `root` ability rejected for non-owner<br>F07-T2-2: Missing policy defaults to deny (deny-by-default)<br>F07-T2-3: Soft-deleted resource policy authorization fails<br>F07-T2-4: Tampered JWT/Bearer token rejected with 401<br>F07-T2-5: Trailing slashes/encoded URIs do not bypass middleware | `tests/Feature/Hosting/RbacThreeTierTest.php` |
| **F08** | Centralized Limits Data Model | M3 | F08-T1-1: Global default limits seeded and readable<br>F08-T1-2: Per-team limit override record created<br>F08-T1-3: Per-plan limit configuration saved<br>F08-T1-4: Limit record associates with correct model type<br>F08-T1-5: Fallback from custom to global limit works | F08-T2-1: Negative limit values rejected by validation<br>F08-T2-2: Fractional CPU values (e.g. 0.25 vCPU) stored accurately<br>F08-T2-3: Unlimited indicator (e.g. -1 or null) handled correctly<br>F08-T2-4: Conflicting plan vs team limit resolves to most specific<br>F08-T2-5: Extreme integer limit values (overflow protection) | `tests/Feature/Hosting/ResourceLimitsEnforcementTest.php` |
| **F09** | Multi-Dimensional Quota Calculation | M3 | F09-T1-1: Project count aggregation matches database<br>F09-T1-2: Running container count calculation is accurate<br>F09-T1-3: Total allocated RAM sum calculated across apps<br>F09-T1-4: CPU allocation calculated across team services<br>F09-T1-5: Standalone database count calculated accurately | F09-T2-1: Stopped containers excluded from running container quota<br>F09-T2-2: Preview deployments counted against deployment limit<br>F09-T2-3: Failed deployments cleaned up from active quota<br>F09-T2-4: Concurrent creations calculate quota atomically<br>F09-T2-5: Mixed-unit memory conversions (MB, GB, MiB, GiB) | `tests/Feature/Hosting/ResourceLimitsEnforcementTest.php` |
| **F10** | Server-Side Creation & Deployment Limit Gate | M3 | F10-T1-1: App creation allowed when under project limit<br>F10-T1-2: App creation blocked with 422 when at project limit<br>F10-T1-3: Deployment queueing blocked when container limit reached<br>F10-T1-4: Owner bypasses all limit gate checks<br>F10-T1-5: Admin bypasses all limit gate checks | F10-T2-1: Race condition: 2 simultaneous creates when limit has 1 slot<br>F10-T2-2: Client reaching RAM limit cannot deploy higher-RAM service<br>F10-T2-3: Detailed error payload contains exact exceeded metric<br>F10-T2-4: Batch/import resource creation halted on limit breach<br>F10-T2-5: Temporary quota increase expires and re-locks gating | `tests/Feature/Hosting/ResourceLimitsEnforcementTest.php` |
| **F11** | Container Runtime Limit Injection | M3 | F11-T1-1: Docker Compose output includes `mem_limit`<br>F11-T1-2: Docker Compose output includes `cpus` constraint<br>F11-T1-3: Memory swap limit configured in compose output<br>F11-T1-4: Client-specified limits clamped to plan maximum<br>F11-T1-5: Unrestricted templates injected with default bounds | F11-T2-1: Client attempting compose override of `cpus` is overridden<br>F11-T2-2: Memory limit below container minimum handled safely<br>F11-T2-3: NanoCPUs calculation conversion precision<br>F11-T2-4: Compose parser snapshot comparison matches baseline<br>F11-T2-5: Multiple containers in service get distributed limits | `tests/Feature/Hosting/ResourceLimitsEnforcementTest.php` |
| **F12** | Admin Resource Limits Management UI | M3 | F12-T1-1: Admin views global limits table<br>F12-T1-2: Admin edits global default CPU limit<br>F12-T1-3: Admin searches client and opens custom limit modal<br>F12-T1-4: Admin saves custom RAM override for specific client<br>F12-T1-5: Changes reflect immediately in client quota display | F12-T2-1: Non-admin accessing limits UI receives 403<br>F12-T2-2: Invalid string in numerical limit field shows validation error<br>F12-T2-3: Resetting client limit to default deletes override row<br>F12-T2-4: Real-time Livewire validation prevents negative inputs<br>F12-T2-5: Concurrent admin edits handle optimistic locking | `tests/Feature/Hosting/ResourceLimitsEnforcementTest.php` |
| **F13** | Hosting Product & Plan Catalog | M4 | F13-T1-1: List all active hosting products via catalog<br>F13-T1-2: Product plans show correct pricing and billing cycles<br>F13-T1-3: Product details include feature list and quotas<br>F13-T1-4: Admin creates new VPS hosting product<br>F13-T1-5: Admin toggles product active/inactive visibility | F13-T2-1: Inactive products hidden from client catalog<br>F13-T2-2: Duplicate plan slug rejected by unique validation<br>F13-T2-3: Zero-price (free tier) product handles checkout without card<br>F13-T2-4: Multi-currency pricing formatting (USD, EUR, GBP)<br>F13-T2-5: Soft-deleted product retains historical order links | `tests/Feature/Hosting/EntitlementsLifecycleTest.php` |
| **F14** | Entitlement Purchase & Activation Flow | M4 | F14-T1-1: Client places order creating order and invoice<br>F14-T1-2: Invoice payment verification marks invoice paid<br>F14-T1-3: Paid invoice automatically unlocks entitlement<br>F14-T1-4: Entitlement activation updates client quotas<br>F14-T1-5: Client receives access to purchased service type | F14-T2-1: Spoofed client price payload ignored in favor of DB price<br>F14-T2-2: Duplicate payment webhook handled idempotently<br>F14-T2-3: Order creation during maintenance mode handled cleanly<br>F14-T2-4: Payment proof file upload validates MIME and size<br>F14-T2-5: Partial payment does not unlock full entitlement | `tests/Feature/Hosting/EntitlementsLifecycleTest.php` |
| **F15** | Subscription Lifecycle State Machine | M4 | F15-T1-1: Active subscription maintains deployment permissions<br>F15-T1-2: Past-due subscription triggers payment warning<br>F15-T1-3: Canceled subscription revokes deployment access<br>F15-T1-4: Expired subscription disables container auto-start<br>F15-T1-5: Renewal payment transitions back to Active | F15-T2-1: Invalid state transition (e.g. Expired to Past-Due) throws error<br>F15-T2-2: Grace period countdown calculates exact expiration timestamp<br>F15-T2-3: Immediate cancellation vs end-of-period cancellation<br>F15-T2-4: Downgrade to lower quota when current usage exceeds new quota<br>F15-T2-5: Webhook payload with unknown status logs without crashing | `tests/Feature/Hosting/EntitlementsLifecycleTest.php` |
| **F16** | Manual Entitlement Management | M4 | F16-T1-1: Admin manually grants entitlement to client<br>F16-T1-2: Admin manually suspends active entitlement<br>F16-T1-3: Admin manually revokes entitlement<br>F16-T1-4: Admin modifies entitlement expiration date<br>F16-T1-5: Manual actions log admin user ID in audit trail | F16-T2-1: Client attempting manual entitlement grant receives 403<br>F16-T2-2: Suspending already suspended entitlement is idempotent<br>F16-T2-3: Past expiration date on grant immediately triggers expiration<br>F16-T2-4: Revocation immediately kills running deployments for that service<br>F16-T2-5: Admin audit reason mandatory on manual override | `tests/Feature/Hosting/EntitlementsLifecycleTest.php` |
| **F17** | Client Entitlement Dashboard & Paywall | M4 | F17-T1-1: Client views active subscriptions and quotas<br>F17-T1-2: Client sees usage progress bars (e.g. 2/5 apps used)<br>F17-T1-3: Gated service shows paywall modal with plan pricing<br>F17-T1-4: Upgrade button redirects to appropriate checkout<br>F17-T1-5: Paid services show direct link to management view | F17-T2-1: Usage percentage handles divide-by-zero (limit=0)<br>F17-T2-2: XSS payload in product description rendered escaped<br>F17-T2-3: Client cannot click through paywall via disabled UI buttons<br>F17-T2-4: Quota refresh button reflects real-time Redis/DB state<br>F17-T2-5: Responsive display on mobile/small screen viewport | `tests/Feature/Hosting/EntitlementsLifecycleTest.php` |
| **F18** | Global & Plan-Based Template Toggles | M5 | F18-T1-1: Admin disables template globally (hidden from all)<br>F18-T1-2: Admin enables template globally (visible to all)<br>F18-T1-3: Admin binds premium template to Pro Plan<br>F18-T1-4: Client with Pro Plan sees bound template<br>F18-T1-5: Client with Basic Plan cannot see bound template | F18-T2-1: Global disable takes precedence over plan binding<br>F18-T2-2: Owner/Admin still sees globally disabled template in admin list<br>F18-T2-3: Template binding to non-existent plan ID throws 404<br>F18-T2-4: Bulk toggle of multiple templates in one transaction<br>F18-T2-5: Template list caching invalidated on toggle | `tests/Feature/Hosting/TemplateAccessControlTest.php` |
| **F19** | Per-Client Template & Category Permissions | M5 | F19-T1-1: Admin grants specific template directly to Client<br>F19-T1-2: Admin revokes specific template grant<br>F19-T1-3: Admin restricts category (e.g. Databases) for Client<br>F19-T1-4: Client with category restriction sees no DB templates<br>F19-T1-5: Explicit client grant overrides plan restriction | F19-T2-1: Revoking client grant falls back to plan permissions<br>F19-T2-2: Granting expired template permission behaves as revoked<br>F19-T2-3: Special characters in template names sanitized in permissions<br>F19-T2-4: Cross-tenant check: Client A cannot see Client B's grants<br>F19-T2-5: Category name case sensitivity handled consistently | `tests/Feature/Hosting/TemplateAccessControlTest.php` |
| **F20** | Template Gating & Deployment Validation | M5 | F20-T1-1: Client deploys authorized template successfully<br>F20-T1-2: Client deploying unauthorized template blocked 403<br>F20-T1-3: Catalog API returns only authorized templates<br>F20-T1-4: Direct POST to deploy endpoint validates template permission<br>F20-T1-5: Owner/Admin deploys any template regardless of gates | F20-T2-1: Direct API deployment bypass attempt with raw compose file<br>F20-T2-2: Template ID spoofing in deployment payload rejected<br>F20-T2-3: Template authorized at selection but revoked before deployment click<br>F20-T2-4: Template with external Docker hub image validated against policy<br>F20-T2-5: Error response specifies clear upgrade requirement | `tests/Feature/Hosting/TemplateAccessControlTest.php` |
| **F21** | Template Resource Requirements Config | M5 | F21-T1-1: Template schema defines min CPU, RAM, storage<br>F21-T1-2: Client with sufficient quota deploys heavy template<br>F21-T1-3: Client with insufficient RAM quota blocked before deploy<br>F21-T1-4: Requirements displayed clearly in template card<br>F21-T1-5: Admin modifies template minimum RAM requirement | F21-T2-1: Template min RAM greater than client total limit triggers upgrade hint<br>F21-T2-2: Zero requirement fields default to system minimums<br>F21-T2-3: Template requiring 4 CPUs blocked for 2-CPU client limit<br>F21-T2-4: Combined requirement check across multi-container templates<br>F21-T2-5: Requirements validation executes before Docker pull | `tests/Feature/Hosting/TemplateAccessControlTest.php` |
| **F22** | Comprehensive Dual Track E2E Test Suite | M6 | F22-T1-1: Full test suite execution passes cleanly<br>F22-T1-2: Test runner aggregates results across all suites<br>F22-T1-3: Assertion coverage exceeds requirement minimums<br>F22-T1-4: Test execution under 120s for fast CI iteration<br>F22-T1-5: Self-contained test fixtures tear down completely | F22-T2-1: Database transaction leak detection across tests<br>F22-T2-2: Mocked external network calls do not touch internet<br>F22-T2-3: Flaky test resilience under repeated execution (10 runs)<br>F22-T2-4: Test execution in isolated test runner environment<br>F22-T2-5: Memory profiling verifies no PHP memory limit breach | `tests/Feature/Hosting/DualTrackCoverageVerificationTest.php` |
| **F23** | Adversarial Coverage Hardening (Tier 5) | M6 | F23-T1-1: Host root Docker socket mount attempt blocked<br>F23-T1-2: Malicious `.env` injection with shell metacharacters rejected<br>F23-T1-3: Cross-tenant private key export blocked<br>F23-T1-4: API token ability privilege escalation blocked<br>F23-T1-5: Direct database query parameter injection blocked | F23-T2-1: Symlink directory traversal in file volume mount blocked<br>F23-T2-2: Fork bomb / CPU starvation prevented by injected cgroups<br>F23-T2-3: Replay attack on payment webhook rejected<br>F23-T2-4: Blind timing attack on API authentication tokens neutralized<br>F23-T2-5: Prototype pollution / JSON payload bomb in compose parsing | `tests/Feature/E2E/AdversarialIsolationTest.php` |

---

## 4. Tier 3: Cross-Feature Interaction Matrix (Pairwise)

The Tier 3 test suite (`tests/Feature/E2E/CrossFeatureCombinationsTest.php`) covers the combinatorial space across the 4 foundational dimensions. Every pair of dimensions is verified under realistic, intersecting conditions:

| Case ID | RBAC Dimension | Limit / Quota Dimension | Entitlement Dimension | Template Dimension | Expected System Outcome |
|---|---|---|---|---|---|
| **C3-01** | Platform Owner | Quota Exceeded (10/5 Apps) | Expired Entitlement | Premium Gated Template | **Allowed**: Platform Owner bypasses all limits, ignores entitlement expiration, and deploys any template without restriction. |
| **C3-02** | Administrator | Quota At Limit (5/5 Apps) | Suspended Entitlement | Plan-Restricted Template | **Allowed**: Administrator acting on behalf of system retains bypass privileges to manage infrastructure. |
| **C3-03** | Client Owner | Quota Under Limit (2/5 Apps) | Active Entitlement | Authorized Plan Template | **Allowed**: Valid purchase, active entitlement, sufficient quota, and plan authorization permit successful deployment. |
| **C3-04** | Client Owner | Quota Exceeded (5/5 Apps) | Active Entitlement | Authorized Plan Template | **Blocked (422)**: Active entitlement cannot override resource limits; client must upgrade plan or free resources. |
| **C3-05** | Client Owner | Quota Under Limit (1/5 Apps) | Expired Entitlement | Authorized Plan Template | **Blocked (403)**: Unpaid/expired subscription instantly revokes deployment capabilities even if quota is available. |
| **C3-06** | Client Owner | Quota Under Limit (1/5 Apps) | Active Entitlement | Globally Disabled Template | **Blocked (403)**: Global administrative disable takes absolute precedence over client plan entitlement. |
| **C3-07** | Client Member | Quota Under Limit (1/5 Apps) | Active Entitlement | Authorized Plan Template | **Blocked (403)**: Client team member has read-only access; cannot trigger new resource creation or deployments. |
| **C3-08** | Client Owner | Quota Under Limit (1/5 Apps) | Active Entitlement | Template Requiring 4GB (Limit 2GB) | **Blocked (422)**: Template resource requirement validation halts deployment before queueing when client limit < requirement. |
| **C3-09** | Client Owner | Quota Under Limit (1/5 Apps) | Active (Custom Explicit Grant) | Category Restricted Template | **Allowed**: Explicit per-client template grant overrides general category restriction. |
| **C3-10** | Client Owner | Quota Under Limit (1/5 Apps) | Active Entitlement | Unbound Free Template | **Allowed**: Free/community template accessible under any active tier. |
| **C3-11** | Cross-Tenant Client | Quota Under Limit (0/5 Apps) | Active Entitlement | Other Tenant's Custom Template | **Blocked (404/403)**: Multi-tenant boundary strictly isolates custom templates and private catalogs. |
| **C3-12** | Client Owner | Quota Under Limit (1/5 Apps) | Canceled Entitlement | Premium Gated Template | **Blocked (403)**: Canceled subscription immediately halts deployment and locks paywalled templates. |
| **C3-13** | Administrator | Custom Client Limit Config | Active Entitlement | Modified Requirements | **Verified**: Admin updates client limit; client immediately gains ability to deploy higher-requirement template. |
| **C3-14** | Client Owner | Concurrently Spawning 2 Apps | 1 Slot Remaining in Quota | Authorized Plan Template | **Atomic Isolation**: Exactly 1 application created; 2nd concurrent request deterministically rejected with 422. |
| **C3-15** | Client Owner | Quota Under Limit (1/5 Apps) | Past-Due Entitlement | Authorized Plan Template | **Grace Period Warning / Block**: If within grace period, allows read/restart but blocks new creations. |
| **C3-16** | Client Owner | Session Switched to Team B | Active in Team A | Authorized in Team A | **Blocked (403)**: Resource team validation enforces Team A context; prevents cross-team entitlement leakage. |
| **C3-17** | Client Owner | Quota Exceeded for Databases | Active VPS Entitlement | Standalone Postgres Template | **Blocked (422)**: Multi-dimensional quota evaluates database dimension specifically; blocks DB creation while allowing apps. |
| **C3-18** | Platform Owner | Quota = 0 Configured | None / No Entitlements | Enterprise Stack Template | **Allowed**: Zero-quota configuration explicitly bypassed for Owner role. |
| **C3-19** | Client Owner | Quota Under Limit (1/5 Apps) | Active Entitlement | Template with Missing Image | **Controlled Error**: Passes entitlement/limit gate, fails at container engine with structured error, quota preserved. |
| **C3-20** | Client Owner | Quota Restored via Deletion | Active Entitlement | Authorized Plan Template | **Allowed**: Deleting an application instantly decreases active count and unlocks deployment slot. |

---

## 5. Tier 4: Real-World Customer Scenarios (Full Lifecycle)

The Tier 4 test suite (`tests/Feature/E2E/RealWorldScenariosTest.php`) executes 5 complete, multi-step customer journeys:

### Scenario 1: Complete Client Onboarding to Full-Stack Production Deployment
1. **Registration & Tenant Initialization**: A new customer registers via Fortify, auto-provisioning their private Team and default Client role (`Role::CLIENT`).
2. **Catalog Browsing & Product Selection**: Client browses the public/client hosting catalog, selects the "Pro Application Hosting" plan (includes 5 Apps, 4GB RAM, 2 CPUs, Postgres).
3. **Checkout & Invoice Generation**: Client submits order payload. Server validates price canonicality from DB, creates `Order` (pending) and `Invoice` (unpaid).
4. **Payment Proof & Administrative Approval**: Client uploads bank payment receipt. Platform Admin reviews and approves the transaction, marking invoice `paid`.
5. **Entitlement Unlocking & Quota Provisioning**: System state machine triggers `EntitlementService`, creating active `HostingEntitlement` and setting custom limits (4GB RAM, 5 Apps).
6. **Full-Stack Application Deployment**: Client deploys a Next.js frontend with PostgreSQL database. System verifies limit clearance, generates compose file with injected cgroup limits, and dispatches deployment job.

### Scenario 2: Traffic Surge, Quota Exhaustion & Instant Self-Service Upgrade
1. **Saturation**: Client reaches maximum configured container limit (e.g., 3/3 active containers).
2. **Blockage**: Client attempts to launch a staging environment for a high-priority bugfix. System limit gate halts request with structured HTTP 422 error detailing exceeded metric.
3. **Paywall Presentation**: Client UI displays contextual paywall modal offering "Scale-Up Pack" (+5 Containers, +8GB RAM).
4. **Instant Upgrade Checkout**: Client purchases upgrade. Payment processor confirms transaction.
5. **Atomic Quota Expansion**: Entitlement service immediately applies upgrade delta.
6. **Successful Deployment**: Client re-submits staging deployment without leaving the page; request immediately succeeds.

### Scenario 3: Subscription Expiration, Dunning Grace Period & Graceful Suspension
1. **Billing Cycle Expiry**: An active client subscription reaches end-of-period without automated renewal.
2. **Transition to Past-Due**: State machine transitions entitlement to `past_due`. Client dashboard displays prominent payment banner; existing apps remain online.
3. **Grace Period Expiration**: After configured grace period (e.g. 72 hours), status transitions to `expired`.
4. **Enforcement Gate Lock**: Middleware halts all new deployments and scale actions with HTTP 403 Paywall error.
5. **Container Preservation**: Running containers are placed in managed maintenance state rather than destroyed immediately.
6. **Reactivation**: Client pays outstanding invoice; entitlement reverts to `active`, deployment locks clear instantly.

### Scenario 4: Enterprise Delegation, Custom Limits & Restricted Template Delivery
1. **Administrative Delegation**: Platform Owner assigns `Administrator` role to team member with specific hosting management privileges.
2. **Custom Quota Allocation**: Administrator provisions a tailored quota for an Enterprise client (16GB RAM, 10 Databases, Unlimited Deployments).
3. **Catalog Gating**: Administrator binds proprietary "Enterprise Odoo Stack" template exclusively to this enterprise client.
4. **Client Team Scoping**: Enterprise Client Owner invites a developer as a `Member`.
5. **Role-Based Execution**: Developer attempts to modify server infrastructure (blocked with 403); Client Owner successfully deploys the enterprise template.

### Scenario 5: Adversarial Boundary Penetration & Zero-Trust Tenant Isolation
1. **IDOR Probe**: Malicious Client attempts to view environment variables and database passwords of another client by enumerating UUIDs (deterministic 404/403).
2. **Pricing Tampering**: Client attempts checkout with modified `$0.01` price parameter; server overwrites with authoritative database price.
3. **Docker Socket Mount**: Client attempts deployment using a custom Compose file mounting `/var/run/docker.sock` to escape container; parser strips mount and logs security event.
4. **Privilege Escalation**: Client crafts bearer token with `write:sensitive` ability; middleware clamps abilities according to client's role and denies execution.
5. **Resource Starvation**: Client attempts to start container requesting 64GB RAM on 2GB plan; runtime limit injection clamps container cgroup limits to 2GB.

---

## 6. Test Suite Directory Layout & File Organization

The tests are organized into the `tests/` tree following Pest / PHPUnit standard conventions:

```
tests/
├── CreatesApplication.php
├── Pest.php                                    # Test configuration, uses(TestCase::class), global hooks
├── TestCase.php                                # Base test case, container reset, facade cleanup
├── Feature/
│   ├── E2E/                                    # Multi-feature, scenarios, and adversarial tests
│   │   ├── RealWorldScenariosTest.php          # Tier 4: 5 Customer Journeys
│   │   ├── CrossFeatureCombinationsTest.php    # Tier 3: 20 Pairwise Combinations
│   │   └── AdversarialIsolationTest.php        # Tier 5: Security, IDOR, Docker socket injection
│   ├── Hosting/                                # Domain-specific feature suites (Tiers 1 & 2)
│   │   ├── RbacThreeTierTest.php               # Features 3, 4, 5, 6, 7 (RBAC & Isolation)
│   │   ├── ResourceLimitsEnforcementTest.php   # Features 8, 9, 10, 11, 12 (Limits & Quotas)
│   │   ├── EntitlementsLifecycleTest.php       # Features 13, 14, 15, 16, 17 (Commerce & Subscriptions)
│   │   ├── TemplateAccessControlTest.php       # Features 18, 19, 20, 21 (Template Catalogs & Gating)
│   │   ├── BaselineAndNavigationTest.php       # Features 1, 2 (Test suite stability & UI routes)
│   │   └── DualTrackCoverageVerificationTest.php # Features 22, 23 (Meta-test verifying coverage & thresholds)
│   └── Commerce/                               # Existing commerce feature tests
└── Fixtures/
    └── parser/                                 # Compose parser golden reference fixtures
```

---

## 7. Test Runner Invocation Commands & Execution Matrix

All tests run in the Dockerized STX Hosting development container environment (`coolify`), backed by PostgreSQL (`coolify-db`) and Redis (`coolify-redis`).

### 7.1 Running Full Test Suites
```bash
# Execute entire E2E and Hosting test suite via Pest
docker exec coolify vendor/bin/pest tests/Feature/E2E tests/Feature/Hosting

# Execute via Laravel Artisan test runner (compact output)
docker exec coolify php artisan test --compact tests/Feature/E2E tests/Feature/Hosting
```

### 7.2 Running Specific Test Suites
```bash
# RBAC & Tenant Isolation (Features 3-7)
docker exec coolify vendor/bin/pest tests/Feature/Hosting/RbacThreeTierTest.php

# Resource Limits & Quotas (Features 8-12)
docker exec coolify vendor/bin/pest tests/Feature/Hosting/ResourceLimitsEnforcementTest.php

# Entitlements & Subscriptions (Features 13-17)
docker exec coolify vendor/bin/pest tests/Feature/Hosting/EntitlementsLifecycleTest.php

# Template Permissions & Gating (Features 18-21)
docker exec coolify vendor/bin/pest tests/Feature/Hosting/TemplateAccessControlTest.php

# Tier 3: Cross-Feature Interaction Matrix
docker exec coolify vendor/bin/pest tests/Feature/E2E/CrossFeatureCombinationsTest.php

# Tier 4: Real-World Scenarios
docker exec coolify vendor/bin/pest tests/Feature/E2E/RealWorldScenariosTest.php

# Tier 5: Adversarial & Security Isolation
docker exec coolify vendor/bin/pest tests/Feature/E2E/AdversarialIsolationTest.php
```

### 7.3 Running by Test Tier Groups
```bash
# Run Tier 1 Feature Coverage tests
docker exec coolify vendor/bin/pest --group=tier1

# Run Tier 2 Boundary & Edge tests
docker exec coolify vendor/bin/pest --group=tier2

# Run Tier 3 Combinatorial tests
docker exec coolify vendor/bin/pest --group=tier3

# Run Tier 4 Real-World Workflows
docker exec coolify vendor/bin/pest --group=tier4

# Run Tier 5 Adversarial Hardening tests
docker exec coolify vendor/bin/pest --group=tier5
```

### 7.4 Filtering by Specific Feature or Case
```bash
# Filter tests for a specific feature (e.g. Feature 10 limit gates)
docker exec coolify vendor/bin/pest --filter="F10"

# Filter by scenario name
docker exec coolify vendor/bin/pest --filter="Scenario 1"
```

---

## 8. Verification & Pass Criteria

A build or pull request is certified **TEST READY** and eligible for milestone signoff only when:
1. **100% Pass Rate**: Every scaffolded test case in `tests/Feature/E2E` and `tests/Feature/Hosting` passes without unhandled exceptions or assertion failures.
2. **Threshold Compliance**:
   - Tier 1: >= 5 tests per feature (minimum 115 tests).
   - Tier 2: >= 5 tests per feature (minimum 115 tests).
   - Tier 3: >= 20 cross-feature interaction scenarios.
   - Tier 4: >= 5 full-lifecycle real-world customer workflows.
   - Tier 5: >= 10 adversarial security checks.
   - Total Suite Count: >= 265 automated test cases.
3. **Execution Time**: The combined test suite executes within 180 seconds under standard Docker CI environments.
4. **Data Isolation**: Tests use `RefreshDatabase` and explicit model teardowns. No test leaves persistent state in PostgreSQL, Redis, or local file storage.
5. **No Implementation Leaks**: All assertions evaluate observable contracts (HTTP response status, JSON schemas, database counts, model attributes, and permission evaluations).
