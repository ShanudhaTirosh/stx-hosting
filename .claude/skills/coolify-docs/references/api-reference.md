# Upstream Coolify REST API Reference & OpenAPI Guide

## Overview

Coolify provides a comprehensive REST API rooted at `/api/v1/`. The complete, formal OpenAPI 3.0 specification is stored in:

```text
coolify-docs-main/config/openapi.json
```

Use this file and the narrative guides in `coolify-docs-main/content/docs/api/` as the single source of truth when developing or refactoring controllers in `app/Http/Controllers/Api/v1/` or defining API routes in `routes/api.php`.

---

## Key API Endpoints & Spec Locations

### 1. Servers (`/api/v1/servers`)
- **List / Create Servers**:
  - `GET /servers`: Returns all servers accessible by the authenticated token's team.
  - `POST /servers`: Provision or register a new server.
  - Look up schema definitions in `openapi.json` under `/servers` path.
- **Server Details & Operations**:
  - `GET /servers/{uuid}`: Server hardware specs, IP, SSH port, user, settings.
  - `PATCH /servers/{uuid}`: Update server configuration, proxy type (Traefik / Caddy), Docker cleanup settings.
  - `DELETE /servers/{uuid}`: Server de-provisioning.
  - `GET /servers/{uuid}/resources`: Resources (apps, DBs, services) deployed on the server.
  - `GET /servers/{uuid}/domains`: List domains routed through this server's proxy.
  - `POST /servers/{uuid}/validate`: Trigger SSH connection and Docker availability check.

### 2. Applications (`/api/v1/applications`)
- **List / Create Applications**:
  - `GET /applications`
  - `POST /applications/public`: Deploy from a public Git repository.
  - `POST /applications/private`: Deploy from private GitHub / GitLab / Bitbucket repo with deploy key.
  - `POST /applications/dockerfile`: Deploy from raw Dockerfile.
  - `POST /applications/dockercompose`: Deploy from Docker Compose.
  - `POST /applications/dockerimage`: Deploy from pre-built Docker image.
- **Application Lifecycle & Configuration**:
  - `GET /applications/{uuid}`: Full metadata, build pack, ports, domain, status.
  - `PATCH /applications/{uuid}`: Update ports, domains, health check paths, build pack.
  - `DELETE /applications/{uuid}`: Stop and delete application containers and volumes.
  - `POST /deploy?uuid={uuid}&force=true`: Trigger asynchronous build & deployment.
  - `POST /applications/{uuid}/restart`: Restart running container.
  - `POST /applications/{uuid}/stop`: Stop container.
  - `GET /applications/{uuid}/logs`: Stream or fetch container stdout/stderr.

### 3. Databases (`/api/v1/databases`)
- **Engines**: PostgreSQL, MySQL, MariaDB, Redis, Dragonfly, KeyDB, ClickHouse, MongoDB.
- **Endpoints**:
  - `POST /databases/postgresql`
  - `POST /databases/mysql`
  - `POST /databases/redis`
  - `POST /databases/clickhouse`
  - `GET /databases/{uuid}`: Connection string, internal/external ports, credentials.
  - `POST /databases/{uuid}/start`, `POST /databases/{uuid}/stop`, `POST /databases/{uuid}/restart`.
  - `GET /databases/{uuid}/backups`: Backup history, timestamps, S3 upload status.
  - `POST /databases/{uuid}/backups/execute`: Trigger instant ad-hoc backup.

### 4. Services (`/api/v1/services`)
- **One-Click Stacks**:
  - `POST /services`: Deploy from a pre-defined template.
  - `GET /services/{uuid}`: Compose components, sub-services, environment variables.
  - `POST /services/{uuid}/start`, `POST /services/{uuid}/stop`, `POST /services/{uuid}/restart`.

### 5. Deployments & Queues (`/api/v1/deployments`)
- `GET /deployments`: List recent and running deployments.
- `GET /deployments/{uuid}`: Status (`in_progress`, `finished`, `failed`, `cancelled`), build logs.
- `POST /deployments/{uuid}/cancel`: Abort an active build/deployment.

---

## Searching `openapi.json` Efficiently

Because `openapi.json` is ~1 MB in size, use targeted PowerShell queries to locate schemas:

### Find Path Definition
```powershell
$json = Get-Content "coolify-docs-main\config\openapi.json" | ConvertFrom-Json
$json.paths.PSObject.Properties | Where-Object { $_.Name -match "servers" } | Select-Object Name
```

### Inspect Parameters & Request Body for an Endpoint
```powershell
$json = Get-Content "coolify-docs-main\config\openapi.json" | ConvertFrom-Json
$endpoint = $json.paths."/servers/{uuid}".patch
$endpoint.requestBody.content."application/json".schema
```

---

## Authentication & Headers Contract

Refer to `coolify-docs-main/content/docs/api/authorization.mdx` and `making-requests.mdx`:
- **Auth Header**: `Authorization: Bearer <personal_access_token>`
- **Content Type**: `Content-Type: application/json`
- **Accept**: `Accept: application/json`
- **Error Format**: JSON response containing `message` and optional `errors` map.
