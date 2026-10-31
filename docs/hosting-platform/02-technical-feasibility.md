# Deliverable B: STX Hosting Integration Feasibility & Technical Research Report

**Document Reference:** `docs/hosting-platform/02-technical-feasibility.md`  
**Target Platform:** STX Hosting v4 Core Engine (`Laravel 12 / Livewire 3 / Sanctum / Reverb / Sentinel / Traefik / Docker`)  
**Integration Scope:** Technical Feasibility, REST API Architecture, Cloud Provisioning, Security Boundaries, and Verification Matrix for the Commercial Hosting Business Platform  
**Date:** October 2026  
**Author:** Teamwork Architecture & Engineering Implementation Group (`worker_m1`)  
**Integrity Mode:** Genuine Development & Production Architecture Specification

---

## 1. Executive Feasibility Assessment

This technical research report provides the authoritative engineering specification for integrating STX Hosting v4 as the core infrastructure execution and container orchestration engine for a commercial hosting business platform.

### Summary Assessment: **FEASIBLE WITH AN INTERMEDIATE GATEWAY LAYER**

STX Hosting v4 provides a native, robust, and production-tested REST API at `/api/v1` authenticated via Laravel Sanctum bearer tokens with fine-grained ability scopes (`read`, `write`, `deploy`, `read:sensitive`, `write:sensitive`, `root`). It provides fully automated lifecycle management for Linux servers, Docker network destinations, Git-based and image-based applications, 9 standalone database engines, multi-container Docker Compose stacks, S3-backed automated backups, and Traefik/Caddy automated Let's Encrypt SSL certificates. Furthermore, STX Hosting includes pre-built REST clients for automated cloud VPS provisioning on Hetzner, DigitalOcean, Vultr, and Hostinger.

However, because STX Hosting was architected as an internal DevOps control plane rather than a multi-tenant commercial public cloud, **direct exposure of STX Hosting's API, WebSockets, or UI to untrusted end-customers is strictly insecure and architecturally prohibited**:
1. **Administrative Privilege Hazard**: STX Hosting grants root SSH capabilities and arbitrary Docker bind mounts on managed servers to any user or API token with `write` and `deploy` abilities (`SECURITY.md:31-37`).
2. **Missing Multi-Tenant Commercial Constructs**: STX Hosting has no native API endpoints or database models for end-customer user registration, customer teams, product catalog pricing tiers, order processing, sequential invoice generation, manual bank payment proof workflows, or plan quota enforcement.
3. **Mandatory Architectural Pattern**: The hosting business platform must implement a dedicated **Hosting Management API Gateway & Orchestrator**. This gateway sits between the customer-facing storefronts/dashboards and STX Hosting, acting as a security firewall, tenant isolator, billing state machine, and least-privilege API proxy.

```
┌────────────────────────────────────────────────────────┐
│             Customer Client Layer (Browser)            │
│  - ShanuTechX Web Storefront (`web` Port 3000)         │
│  - Customer Dashboard (Client Space Port 3001)         │
│  - ZERO access to STX Hosting tokens, SSH keys, or root    │
└───────────────────────────┬────────────────────────────┘
                            │ HTTPS API Calls (Customer Auth)
┌───────────────────────────▼────────────────────────────┐
│      Hosting Business API Gateway & Orchestrator       │
│  - Validates customer session and ownership            │
│  - Enforces plan limits (vCPU, RAM, Disk, Databases)   │
│  - Maintains billing, order & invoice state machine    │
│  - Injects server/destination UUIDs unknown to client  │
│  - Holds master STX Hosting tokens & cloud provider secrets│
└───────────────────────────┬────────────────────────────┘
                            │ Internal Authenticated Calls
┌───────────────────────────▼────────────────────────────┐
│         STX Hosting Core Orchestration Engine              │
│  - Authenticated via Team-Scoped API Token             │
│  - Manages Docker containers, networks, proxies, SSL   │
│  - SSH connection multiplexing with remote hosts       │
└────────────────────────────────────────────────────────┘
```

---

## 2. STX Hosting REST API Surface & Authentication Architecture

### 2.1 API Endpoint Structure & Protocol
- **Base URL**: `https://<coolify-instance>/api/v1`
- **Route Definitions**: `routes/api.php:1-120`
- **Controller Directory**: `app/Http/Controllers/Api/`
- **Payload Requirements**: All write operations (`POST`, `PATCH`, `PUT`) require `Content-Type: application/json` and non-empty valid JSON.
- **Default 404 Response**: Unmatched routes return `{"message": "Not found.", "docs": "https://stxhosting.com/docs"}` with HTTP 404.

### 2.2 Authentication & Token Abilities
Authentication utilizes Laravel Sanctum HTTP Bearer tokens (`Authorization: Bearer <token_string>`). Tokens are stored in `personal_access_tokens` and are explicitly bound to a `team_id`.

#### Token Ability Scopes (`app/Http/Middleware/ApiAbility.php`):
| Ability Scope | Description | Permitted Team Roles |
|---|---|---|
| `read` | Read non-sensitive resource metadata, configurations, and public status. | Member, Admin, Owner |
| `read:sensitive` | Exposes sensitive fields: `.env` secret values, SSH private keys, database passwords, container execution logs, S3 access secrets. | Admin, Owner only |
| `write` | Create, update, or soft-delete resources, servers, projects, env vars, and backup schedules. | Admin, Owner only |
| `write:sensitive` | Modify sensitive instance settings (e.g. SMTP configuration). | Admin, Owner only |
| `deploy` | Trigger deployments, container start/stop/restart, execute commands on remote servers, install Docker on hosts, trigger immediate backups. | Admin, Owner only |
| `root` | Unrestricted super-administrator bypass across all endpoints. | Admin/Owner on Root Team (0) |

#### Strict Role-Ability Enforcement (`ApiAbility.php:27-35`):
```php
if ($teamId !== null && ! $request->user()->isAdminOfTeam((int) $teamId)) {
    $disallowed = array_filter(self::MEMBER_DISALLOWED_ABILITIES, fn (string $ability) => $token->can($ability));
    if (! empty($disallowed)) {
        return response()->json([
            'message' => 'This API token has permissions (' . implode(', ', $disallowed) . ') that exceed your current role as a team member. Members are restricted to read-only API access...',
        ], 403);
    }
}
```
**Key Finding**: Any API token with `write`, `deploy`, or `read:sensitive` abilities **must** belong to an Admin or Owner of the team. Team members are restricted to read-only API access.

### 2.3 Sensitive Data Masking & Serialization (`ApiSensitiveData.php` & `bootstrap/helpers/api.php`)
1. **Dynamic Serialization**: `serializeApiResponse()` masks or strips `last_saved_proxy_configuration`, DNS API tokens, server validation logs, and raw database IDs unless the request context has the `can_read_sensitive` attribute set to true.
2. **One-Way Write Environment Variables (`is_shown_once`)**: When set to `true`, environment variable values are written to database and container configurations, but subsequent GET requests mask them (`real_value` is omitted).
3. **Log Sanitization**: Application, database, and backup logs run through `sanitizeLogsForExport()`, redacting known environment variable values and passwords before transmission.

---

## 3. Server Registration & Lifecycle Orchestration

### 3.1 Private SSH Key Management (`/api/v1/security/keys`)
- **`POST /api/v1/security/keys`**: Accepts `{ "name": "string", "description": "string", "private_key": "string" }`.
- **`GET /api/v1/security/keys` / `GET /api/v1/security/keys/{uuid}`**: Lists private keys. The actual `private_key` text is masked unless the token has `read:sensitive`.
- **`PATCH /api/v1/security/keys/{uuid}`**: Updates name, description, or private key text.
- **`DELETE /api/v1/security/keys/{uuid}`**: Removes private key (fails if attached to an active server).

### 3.2 Remote Server Registration (`/api/v1/servers`)
- **`POST /api/v1/servers`**: Registers a remote Linux host.
  - **Required Payload**: `ip` (IPv4 or hostname), `private_key_uuid`.
  - **Optional Parameters**: `name`, `description`, `port` (default `22`), `user` (default `root`), `server_role` (`deployment`, `build`, or `both`), `proxy_type` (`traefik`, `caddy`, `none`), `instant_validate` (bool).
  - **Validation**: Normalizes IP; returns HTTP 400 if a server with that IP already exists in STX Hosting.
- **`POST /api/v1/servers/{uuid}/validate`**:
  - Without `{ "install": true }`: Lightweight SSH test (`uptime`) checking connectivity.
  - With `{ "install": true }` (requires `deploy` ability): Dispatches `ValidateAndInstallServerJob`:
    1. Validates SSH connectivity and verifies supported Linux distribution (Ubuntu, Debian, CentOS, RHEL, Fedora, Alpine, Rocky, AlmaLinux, Arch).
    2. Installs required system packages (`curl`, `wget`, `git`, `jq`).
    3. Detects or installs Docker Engine and Docker Compose.
    4. Creates the `coolify` Docker network.
    5. Starts the selected reverse proxy (`Traefik` or `Caddy`).
    6. Starts the `Sentinel` host telemetry agent.
    7. Dispatches `ServerValidated` and `ServerReachabilityChanged` events.

### 3.3 Server Telemetry Agent (`Sentinel`) & WebSockets (`Reverb`)
1. **Sentinel Agent**: A lightweight Go binary running on managed servers:
   - Posts telemetry data to `POST /api/v1/sentinel/push` authenticated with an encrypted server token.
   - Pushes container status list every 60 seconds; STX Hosting computes an `xxh128` hash of `[name, state, health_status, restart_count]` to deduplicate and only dispatches `PushServerUpdateJob` when state changes or after 300 seconds.
2. **Laravel Reverb WebSockets (Port 6001)**:
   - Supervised s6 service running inside the STX Hosting container.
   - Private channel: `team.{teamId}`.
   - Dispatches broadcast events: `ApplicationStatusChanged`, `DatabaseStatusChanged`, `ServiceStatusChanged`, `ProxyStatusChanged`, `ServerValidated`, `ServerReachabilityChanged`, `BackupCreated`.

---

## 4. Cloud Provider Provisioning Engine (Pre-Built Assets)

STX Hosting v4 contains native integrations with major cloud infrastructure providers in `app/Services/` and `app/Http/Controllers/Api/`:

### 4.1 Supported Cloud Providers & Methods
- **Hetzner Cloud (`App\Services\HetznerService`)**:
  - Catalogs: `GET /api/v1/hetzner/locations`, `/server-types`, `/images`, `/firewalls`, `/networks`.
  - Provisioning: `POST /api/v1/servers/hetzner`. Accepts `location`, `server_type`, `image`, `private_key_uuid`, `cloud_provider_token_uuid`.
- **DigitalOcean (`App\Services\DigitalOceanService`)**:
  - Catalogs: `GET /api/v1/digitalocean/regions`, `/sizes`, `/images`.
  - Provisioning: `POST /api/v1/servers/digitalocean`.
- **Vultr (`App\Services\VultrService`)**:
  - Catalogs: `GET /api/v1/vultr/regions`, `/plans`, `/os`.
  - Provisioning: `POST /api/v1/servers/vultr`.
- **Hostinger (`App\Services\HostingerService`)**:
  - Catalogs: `GET /api/v1/hostinger/data-centers`, `/catalog`, `/templates`.
  - Provisioning: `POST /api/v1/servers/hostinger`.

### 4.2 Automated Provisioning Workflow (`ValidateNewCloudServerJob`)
When a cloud server creation endpoint is invoked:
1. STX Hosting validates the provider token and calls the provider REST API to spin up the virtual machine.
2. STX Hosting injects the selected SSH public key into the VM during creation.
3. STX Hosting dispatches `ValidateNewCloudServerJob` to the `high` queue.
4. The job polls the cloud provider API up to 40 attempts (20 minutes) until the VM transitions to `running` and reports a public IPv4 address.
5. Once reachable, it automatically transitions into `ValidateAndInstallServerJob` to configure Docker, Traefik, and Sentinel.

---

## 5. Projects, Environments, Destinations & Resources

STX Hosting organizes all compute resources under a strict multi-tier hierarchy:
```
Team (Multi-tenant security boundary)
 └── Project (Logical system grouping)
      └── Environment (e.g. production, staging)
           └── Destination (StandaloneDocker network on a Server)
                ├── Applications (Git, Dockerfile, Docker Image)
                ├── Databases (Postgres, MySQL, Redis, Mongo, etc.)
                └── Services (Compose templates like WordPress, Plausible)
```

### 5.1 Applications API (`/api/v1/applications`)
Supports 5 build archetypes:
1. `POST /applications/public`: Public Git repository (GitHub, GitLab, Bitbucket).
2. `POST /applications/private-github-app`: Authenticated GitHub App integration.
3. `POST /applications/private-deploy-key`: Git over SSH with attached private key.
4. `POST /applications/dockerfile`: Inlined Dockerfile or repository Dockerfile.
5. `POST /applications/dockerimage`: Pre-built image from Docker Hub or private registry.

#### Resource Allocation & Plan Quotas:
When creating an application, the hosting gateway can inject resource constraints to enforce customer hosting plan quotas:
- `limits_cpus`: CPU limit (e.g. `"1.0"`, `"2.0"`).
- `limits_memory`: Memory limit (e.g. `"512m"`, `"1024m"`, `"2048m"`).
- `limits_memory_swap`: Swap memory limit.
- `limits_memory_reservation`: Minimum reserved memory.
- `limits_cpu_shares`: CPU priority weight.

### 5.2 Standalone Databases API (`/api/v1/databases`)
Supports 9 database engines:
- `POST /databases/postgresql`
- `POST /databases/mysql`
- `POST /databases/mariadb`
- `POST /databases/mongodb`
- `POST /databases/redis`
- `POST /databases/clickhouse`
- `POST /databases/dragonfly`
- `POST /databases/keydb`
- `POST /databases/sqlite`

#### Public Database Exposure:
Databases support `is_public` (boolean) and `public_port` (integer 1024-65535). When enabled, STX Hosting provisions a Traefik TCP proxy router or exposes host port forwarding.

### 5.3 Deployments Engine (`/api/v1/deploy`)
- **Trigger**: `POST /api/v1/deploy` accepting `uuid` (resource UUID) or `tag` (tag name), `force=true` (rebuilds cache).
- **Execution**: Handled asynchronously on Redis queue via `ApplicationDeploymentJob`.
- **Status Tracking**: `GET /api/v1/deployments/{uuid}` returns status (`queued`, `in_progress`, `finished`, `failed`, `cancelled_by_user`) and build logs.
- **Cancellation**: `POST /api/v1/deployments/{uuid}/cancel`.

---

## 6. Domains, Routing, and Automated SSL

### 6.1 Traefik & Caddy Automated Let's Encrypt SSL
When `domains` is configured on an application or service (e.g. `https://clientapp.shanutechx.com`):
1. STX Hosting validates domain syntax using `ValidationPatterns::applicationDomainRules()`.
2. STX Hosting performs **Domain Conflict Detection**: if another resource on the server or instance claims the domain, it returns **HTTP 409 Conflict** with conflict metadata. The gateway can pass `force_domain_override=true` to override.
3. During deployment, STX Hosting generates dynamic Traefik labels attached to the container:
   - HTTP-to-HTTPS redirection middlewares.
   - TLS SNI matchers.
   - Let's Encrypt certificate resolver (`traefik_certificate_resolver`).
4. Traefik automatically negotiates SSL certificates via Let's Encrypt ACME HTTP-01 or DNS-01 challenges.

---

## 7. Backups and Disaster Recovery

### 7.1 Database Backups (`ScheduledDatabaseBackup`)
- **`POST /api/v1/databases/{uuid}/backups`**: Schedules automated database dumps:
  - `frequency`: Cron expression (e.g. `0 2 * * *` for daily at 2:00 AM).
  - `save_s3`: Boolean indicating upload to external S3 storage.
  - `s3_storage_uuid`: Target S3 bucket.
  - `disable_local_backup`: When true, removes local dump file after S3 upload (requires `save_s3=true`).
  - `retention_amount_locally`, `retention_days_locally`, `retention_amount_s3`, `retention_days_s3`.
  - `backup_now=true`: Triggers an immediate backup job (`DatabaseBackupJob`).
- **Restoration**: `POST /api/v1/databases/{uuid}/imports` executes dump restore from local path or S3.

### 7.2 Persistent Volume Backups (`VolumeBackupsController`)
- `PUT /applications/{uuid}/storages/{storage_uuid}/backups`
- Supports `stop_during_backup=true` (temporarily pauses container to guarantee data consistency; requires `deploy` ability).

---

## 8. Verification Matrix: Native REST API vs. Custom Backend Requirements

The following matrix authoritatively separates capabilities verified through STX Hosting's official REST API from operations that **must** be implemented in custom backend code for the hosting business platform:

| Functional Area | Operation | Supported via Native REST API? | API Endpoint / Method | Custom Backend Implementation Requirement |
|---|---|:---:|---|---|
| **Identity & Tenant** | Register customer user | ❌ **No** | None | Hosting platform customer auth (Fortify / Sanctum / JWT). |
| | Create isolated customer Team | ❌ **No** | None | Custom backend command/job: `Team::create(...)` or dedicated tenancy schema. |
| | Issue team-scoped API token | ❌ **No** | None (Livewire UI only) | Backend helper: `User::createToken($name, $abilities, $expiresAt, $teamId)`. |
| **Catalog & Billing** | Product Catalog & Pricing tiers | ❌ **No** | None | Custom `hosting_products` & `hosting_product_prices` schema. |
| | Orders & Invoices | ❌ **No** | None | Custom `hosting_orders`, `hosting_invoices`, `hosting_order_items`. |
| | Manual Payment Proof Workflow | ❌ **No** | None | Bank receipt upload, admin verification queue, idempotency lock. |
| | Gateway Payment Webhooks | ❌ **No** | None | Stripe / PayHere webhook listeners triggering order activation. |
| **Servers & VPS** | Register existing remote server | ✅ **Yes** | `POST /api/v1/servers` | Validates host IP, creates record, assigns private key. |
| | Install Docker & bootstrap proxy | ✅ **Yes** | `POST /api/v1/servers/{uuid}/validate` | `install=true` triggers `ValidateAndInstallServerJob`. |
| | Provision VPS at Cloud Provider | ✅ **Yes** | `POST /api/v1/servers/hetzner` (etc.) | STX Hosting creates VM and auto-validates; or custom cloud API call. |
| | Customer SSH Isolation on VPS | ❌ **No** | None | Custom cloud-init / Ansible script injecting customer user SSH keys separately from STX Hosting admin root key. |
| **App Hosting** | Create App from Git / Docker | ✅ **Yes** | `POST /api/v1/applications/{type}` | Fully automated via REST API. |
| | Deploy App & stream build logs | ✅ **Yes** | `POST /api/v1/deploy`, `GET /deployments/{uuid}` | Fully automated; frontend polls or bridges WebSockets. |
| | Enforce Plan Resource Quotas | ❌ **No** | None | Hosting backend must inject `limits_cpus` and `limits_memory` into the creation payload. |
| **Databases** | Create Standalone Database | ✅ **Yes** | `POST /api/v1/databases/{type}` | Fully automated (Postgres, MySQL, Redis, Mongo, etc.). |
| | Start / Stop / Restart Database | ✅ **Yes** | `POST /api/v1/databases/{uuid}/{action}` | Fully automated via REST API. |
| | Scheduled S3 Database Backups | ✅ **Yes** | `POST /api/v1/databases/{uuid}/backups` | Configures automated cron backup directly into S3. |
| **Domains & SSL** | Custom Domain & Let's Encrypt | ✅ **Yes** | `domains` on App / Service | Automated Traefik/Caddy Let's Encrypt certificate resolution. |
| | Domain Conflict Prevention | ✅ **Yes** | Returns 409 Conflict | Gateway handles conflict error and informs customer. |
| **Realtime Telemetry** | Stream deployment & status events | ⚠️ **Partial** | Reverb `team.{teamId}` WebSocket | Requires backend token bridge or SSE proxy since customer cannot directly connect to STX Hosting Reverb. |

---

## 9. 3-Tier Least-Privilege Security Architecture

To guarantee that master STX Hosting API keys, cloud provider tokens, SSH private keys, and host credentials are never exposed to customers or browser frontends, the platform enforces a **3-Tier Least-Privilege Security Architecture**:

```
+-----------------------------------------------------------------------------------+
|                        TIER 1: CUSTOMER CLIENT LAYER (Browser)                    |
|  - Storefront (`web` Port 3000) & Customer Portal (Client Space Port 3001)        |
|  - Authenticated via Customer JWT / Session Cookie                                |
|  - Can only view assigned services, invoices, and limited resource metrics        |
|  - ZERO direct access to STX Hosting API, WebSockets, or root infrastructure          |
+-----------------------------------------------------------------------------------+
                                          |
                      HTTPS API Calls (Customer Auth Tokens)
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|               TIER 2: HOSTING BUSINESS API GATEWAY & ORCHESTRATOR                 |
|  - Enforces Role-Based Access Control (Customer vs Administrator)                 |
|  - Maintains Product Catalog, Order State Machine, and Sequential Invoices        |
|  - Manages Manual Payment Proof Uploads & Administrative Verification Queue       |
|  - Enforces Hosting Plan Quotas: Injects CPU/RAM limits into STX Hosting payloads     |
|  - Holds Master STX Hosting Sanctum Tokens & Cloud Provider API Credentials           |
|  - Maps Customer IDs to STX Hosting Project/Application UUIDs (Redacting Internal IDs)|
+-----------------------------------------------------------------------------------+
                                          |
                  Internal HTTPS Calls (Team-Scoped Sanctum Tokens)
                                          |
                                          v
+-----------------------------------------------------------------------------------+
|                     TIER 3: COOLIFY CORE ORCHESTRATION ENGINE                     |
|  - Isolated on Internal Network (e.g. `http://coolify:8000` or loopback)          |
|  - Executes Docker container lifecycles, Traefik SSL routing, and backups         |
|  - Multiplexes SSH connections to managed host nodes and VPS instances            |
|  - Localhost Server (ID 0) and Internal DB (ID 0) Strictly Protected              |
+-----------------------------------------------------------------------------------+
```

### 9.1 Security Boundary Mandates
1. **Frontend Isolation**: No STX Hosting Sanctum token is ever returned in a response to the customer client. All client requests terminate at the Hosting API Gateway.
2. **Infrastructure Segregation**:
   - **Shared Application Hosting**: Customer containers run unprivileged under Linux cgroups and namespaces on shared nodes. Host Docker socket (`/var/run/docker.sock`) is never mounted into customer containers.
   - **Customer VPS Hosting**: Dedicated virtual machines provisioned via Hetzner Cloud, DigitalOcean, or KVM. Customers receive root credentials *only* for their dedicated VM. The STX Hosting control plane host remains on a completely isolated network.
3. **Database Credentials**: Connection strings shown to customers use the provisioned container credentials (`internal_db_url` / `external_db_url`), never administrative credentials to the host PostgreSQL instance.

---

## 10. Complete Features & Endpoints Reference Table

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|---|---|---|---|---|---|---|
| 1 | Auth | Bearer Token Auth | Sanctum API Token authentication scoped to team | `Authorization: Bearer <token>` | Authenticated Request | 401 Unauthenticated, 400 Invalid Token | `routes/api.php:66` |
| 2 | Auth | Ability Authorization | Granular permission enforcement (`read`, `write`, `deploy`, `read:sensitive`) | Token abilities & route middleware | Route access granted | 403 Forbidden with missing abilities | `app/Http/Middleware/ApiAbility.php` |
| 3 | Auth | Role-Ability Clamp | Restricts team members to read-only API access even if token has write abilities | Team ID and user role | Access granted or 403 response | 403 Member role exceeded | `ApiAbility.php:27-35` |
| 4 | Auth | Sensitive Masking | Automatically hides secrets, raw compose, private keys, passwords | Request attribute `can_read_sensitive` | Serialized JSON with masked secrets | Hides fields from response | `ApiSensitiveData.php` |
| 5 | Servers | Server Registration | Adds an existing remote Linux server | `ip`, `port`, `user`, `private_key_uuid`, `server_role`, `proxy_type` | `{ "uuid": "..." }` | 422 Validation error, 400 Duplicate IP | `ServersController::create_server` |
| 6 | Servers | Server Validation | Checks SSH connectivity and optionally bootstraps Docker Engine, Compose, and proxy | `uuid`, `{ "install": bool }` | 201 Validation started | 404 Not Found, 422 Not Validatable | `ServersController::validate_server` |
| 7 | Servers | Hetzner VPS | Provisions VM via Hetzner Cloud API, registers in STX Hosting, installs Docker | `location`, `server_type`, `image`, `private_key_uuid`, `cloud_provider_token_uuid` | Created server UUID & IP | 422 Invalid parameters, 429 Rate limit, 502 Provider error | `HetznerController::createServer` |
| 8 | Servers | DO/Vultr VPS | Provisions VM via DigitalOcean, Vultr, or Hostinger APIs | Provider parameters, token UUID | Created server UUID & IP | 422 / 502 on provider failure | `DigitalOceanController`, `VultrController` |
| 9 | Keys | Private SSH Keys | Manages SSH private keys used for server connection and Git access | `name`, `description`, `private_key` (PEM/OpenSSH) | `{ "uuid": "..." }` | 422 Validation error | `SecurityController::create_key` |
| 10 | Projects | Project & Env | Organizes resources into team projects and environments | `name`, `description` | Project / Environment UUIDs | 409 Environment already exists, 400 Has resources | `ProjectController` |
| 11 | Destinations | Docker Destinations | Defines Docker bridge networks attached to servers for container communication | `server_uuid`, `network`, `name` | Destination UUID | 422 Reserved network name (`bridge`, `host`) | `DestinationsController` |
| 12 | Apps | Public Git App | Builds and deploys app from public Git repository | `project_uuid`, `server_uuid`, `git_repository`, `git_branch`, `build_pack` | `{ "uuid": "..." }` | 422 Validation error | `ApplicationsController` |
| 13 | Apps | Private Git App | Deploys from private repo using GitHub App or Deploy Key | `github_app_uuid` or `private_key_uuid` | `{ "uuid": "..." }` | 404 Key/App not found | `ApplicationsController` |
| 14 | Apps | Dockerfile / Image | Deploys container directly from Dockerfile or pre-built image | `dockerfile` or `docker_registry_image_name` | `{ "uuid": "..." }` | 422 Invalid format | `ApplicationsController` |
| 15 | Deploy | Trigger Deployment | Queues build and deploy process for application or tagged group | `uuid` or `tag`, `force=bool`, `pull_request_id` | `{ "deployments": [...] }` | 404 Resource not found, 400 Bad request | `DeployController::deploy` |
| 16 | Deploy | Deployment Status | Polls deployment queue progress and build log output | `deployment_uuid` | Deployment model with status and logs | 404 Deployment not found, logs null without sensitive | `DeployController::deployment_by_uuid` |
| 17 | Deploy | Cancel Deployment | Aborts in-flight build and cleans up containers | `deployment_uuid` | Status updated to `cancelled_by_user` | 400 If already finished or failed | `DeployController::cancel_deployment` |
| 18 | Databases | Standalone DBs | Deploys dedicated PostgreSQL, MySQL, MariaDB, Mongo, Redis, Clickhouse, Dragonfly, KeyDB, SQLite | `server_uuid`, `project_uuid`, credentials, ports | Database UUID and connection URLs | 422 Validation error, 400 Port in use | `DatabasesController` |
| 19 | Databases | DB Lifecycle | Controls database container start/stop/restart | `uuid` | Queue confirmation message | 409 Operation in progress, 400 Already stopped/running | `DatabasesController` |
| 20 | Databases | Scheduled Backups | Configures cron backups with local and S3 storage retention | `frequency`, `save_s3`, `s3_storage_uuid`, `retention_*` | Backup configuration UUID | 422 Invalid cron or missing S3 config | `DatabasesController::create_backup` |
| 21 | Databases | Instant Backup | Triggers immediate backup dump job | `backup_now=true` | Dispatches `DatabaseBackupJob` | 403 Requires deploy ability | `DatabasesController` |
| 22 | Databases | Backup Restore | Restores SQL dump into database from upload, S3, or local path | `source`, `upload_id`, `s3_storage_uuid`, `path` | Import activity ID | 409 Operation in progress, 403 Requires deploy | `DatabasesController::create_import` |
| 23 | Services | Compose Stacks | Deploys multi-container service from catalog template or raw compose | `type` or `docker_compose_raw`, `project_uuid`, `server_uuid` | Service UUID | 422 Cannot provide both type and raw compose | `ServicesController::create_service` |
| 24 | Services | Sub-services | Starts, stops, restarts, or reads logs for individual service components | `service_uuid`, `app_uuid` or `database_uuid` | Success response or logs | 404 Resource not found | `ServiceApplicationsController` |
| 25 | Proxy | Proxy Settings | Updates Traefik/Caddy proxy settings, SSL resolver, and redirect rules | `traefik_certificate_resolver`, `redirect_url`, `proxy_type` | Updated proxy settings | 403 Requires deploy for proxy_type change | `ServerProxyController::update` |
| 26 | Proxy | Automated SSL | Auto-issues Let's Encrypt certificates for defined FQDNs | `domains` on application or service | Traefik routers & TLS certs | 409 Domain conflict (unless override) | `ApplicationsController` |
| 27 | Envs | Environment Vars | Manages env vars across resource, environment, project, team, and server | `key`, `value`, `is_literal`, `is_multiline`, `is_shown_once` | Saved env variable record | 409 Key already exists, 422 Invalid key format | `SharedEnvironmentVariablesController` |
| 28 | Volumes | Storage Mounts | Configures persistent volumes and directory/file mounts | `mount_path`, `name` (persistent) or `content` (file) | Storage volume object | 422 Read-only storage violation | `ApplicationsController` |
| 29 | Volumes | Volume Backups | Schedules automated backups for application, database, and service volumes | `frequency`, `storage_uuid`, `save_s3`, `stop_during_backup` | Schedule response | 403 Deploy required for stop_during_backup | `VolumeBackupsController` |
| 30 | S3 | S3 Integrations | Registers external S3 buckets for backups (AWS, Cloudflare R2, MinIO, Wasabi) | `name`, `endpoint`, `bucket`, `region`, `key`, `secret` | S3 Storage UUID | 422 Validation failure | `S3StoragesController` |
| 31 | S3 | S3 Test | Tests bucket connectivity and credentials against provider API | `uuid` | `{ "valid": bool, "error": null }` | 400 Bad connection | `S3StoragesController::validateStorage` |
| 32 | Monitoring | Sentinel Push | High-frequency container health and resource metrics push | Encrypted token, containers array | 200 OK | 401 Invalid token / non-functional server | `SentinelController::push` |
| 33 | Monitoring | Reverb Events | WebSockets event streaming on private team channels | Reverb client connection to `team.{teamId}` | Broadcast event payloads | 403 Channel authentication failure | `app/Events/*`, `routes/channels.php` |

---

## 11. Discovered Edge Cases & Handling Blueprint

| # | Feature Area | Condition / Input | Observed Behavior in STX Hosting | Handling Blueprint for Hosting Platform |
|---|---|---|---|---|
| 1 | Server Registration | Duplicate IP address provided | STX Hosting returns HTTP 400: `"A server with this IP/Domain is already in use."` | Gateway checks server inventory table before dispatching registration. |
| 2 | Server Deletion | Server still contains running resources | STX Hosting returns HTTP 400: `"Server has resources. Use ?force=true to delete..."` | Gateway must cascade-delete all child resources before server decommissioning. |
| 3 | Localhost Protection | Attempting to delete Server ID 0 (`is_coolify_host`) | STX Hosting returns HTTP 400: `"Local server cannot be deleted."` | Sentinel Server ID 0 is strictly excluded from any customer allocation logic. |
| 4 | Project Deletion | Project contains environments with active resources | STX Hosting returns HTTP 400: `"Project has resources, so it cannot be deleted."` | Deprovisioning workflow verifies child resources are stopped and removed. |
| 5 | Domain Collisions | Domain assigned to another application | STX Hosting returns HTTP 409 Conflict with conflict metadata. | Catch 409 in Gateway; return clear error to customer asking for verification. |
| 6 | Transferred Server | Server marked as transferred away | STX Hosting returns HTTP 422: `"This server was transferred to another STX Hosting instance..."` | Prevent lifecycle actions on transferred servers (`Server::isTransferredAway()`). |
| 7 | Secret Masking | Env var saved with `is_shown_once=true` | STX Hosting omits `value` and `real_value` from response payloads. | Present secret as write-only in customer portal; require overwrite to change. |
| 8 | Server Role Mismatch | Deploying resource to a server with role `build` | STX Hosting returns HTTP 422: `"The specified server is configured as a build server..."` | VPS allocator only selects servers with role `deployment` or `both`. |
| 9 | Local Backup Suppression | `disable_local_backup=true` with `save_s3=false` | STX Hosting returns HTTP 422: local backup cannot be disabled without S3. | Customer UI disables local backup checkbox until valid S3 bucket is chosen. |
| 10 | Non-Root SSH User | Remote server configured with non-root user (e.g. `ubuntu`) | STX Hosting parses commands using `sudo` prefixes. | Ensure target VM user has passwordless sudo (`NOPASSWD: ALL`) configured. |
| 11 | Deploy Ability Check | Token with `read` ability attempts `/deploy` or `/start` | STX Hosting returns HTTP 403: `"Missing required permissions: deploy"`. | Backend token manager must use Admin tokens with `deploy` ability. |
| 12 | Deployment Cancellation | Cancelling finished or failed deployment | STX Hosting returns HTTP 400: `"Deployment cannot be cancelled. Current status: finished"`. | Customer UI only enables cancel button when status is `queued` or `in_progress`. |
| 13 | Service Clashing | Passing both `type` template and `docker_compose_raw` | STX Hosting returns HTTP 422: `"You cannot provide both service type and docker_compose_raw."` | UI separates 1-click catalog templates from custom compose editors. |
| 14 | Database Port Conflict | Public port already allocated to another database on host | STX Hosting returns HTTP 400: `"Public port already used by another database."` | Gateway tracks port offsets (10000-60000) and assigns next available port. |
| 15 | Reverb WebSockets | Direct browser connection to `team.{teamId}` | Handshake rejected with 403 / pusher authentication error. | Gateway proxies telemetry to frontend via Server-Sent Events (SSE). |

---

## 12. Strategic Implementation Roadmap

Based on the verified capabilities of STX Hosting v4 and the survey findings:

1. **Phase 1: Rapid Commercial Launch (Manual VPS + App Hosting)**:
   - Implement the Hosting API Gateway database models (`hosting_products`, `hosting_orders`, `hosting_invoices`, `payment_proofs`, `hosting_services`).
   - Launch with **Manual VPS Allocation** (assigning pre-warmed IPs from `vps_inventories`) and **Application Hosting** provisioned via STX Hosting REST API.
   - Support instant Stripe card payments + Manual Bank Wire Transfer with customer proof upload and an administrative verification queue.
2. **Phase 2: Automated Cloud VPS Provisioning**:
   - Activate automated cloud VM provisioning leveraging the pre-existing `HetznerService` and `DigitalOceanService` clients.
   - Automate customer SSH key injection and provisioning status polling via `ValidateNewCloudServerJob`.
3. **Phase 3: Deep Telemetry & Fleet Monitoring Integration**:
   - Integrate `status` (Next.js 15) to monitor STX Hosting host nodes, Traefik ingress, and customer application URLs, feeding real-time status data to both public status pages and the customer dashboard.
