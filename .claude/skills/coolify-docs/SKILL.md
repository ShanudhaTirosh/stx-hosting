---
name: coolify-docs
description: "Use this skill whenever working on Coolify core engine features, upstream sync, application deployment pipelines (Dockerfile, Compose, Railpack, Static), reverse proxy configurations (Traefik, Caddy, Cloudflare SSL), database management and backups, 340+ compose service templates, REST API v1 endpoints and OpenAPI schemas, Coolify CLI commands, or troubleshooting deployment and server connection issues. Guides agents and developers to consult the authoritative official Coolify documentation located locally in coolify-docs-main."
license: MIT
metadata:
  author: coolify
---

# Upstream Coolify Documentation Skill

## Overview

The `coolify-docs-main/` directory in the repository root contains the complete, official, original documentation for Coolify (deployed upstream at `https://coolify.io/docs`).

Whenever you need to verify upstream Coolify behaviors, Docker Compose service definitions, environment variable mappings, reverse proxy labels, database backup flows, or API contracts, **consult `coolify-docs-main/` directly** instead of guessing or searching external websites.

## Documentation Index & Location Map

All Markdown/MDX documentation files are located under `coolify-docs-main/content/docs/`:

```text
coolify-docs-main/
├── config/
│   └── openapi.json                  # Complete OpenAPI 3.0 specification for Coolify API v1
├── content/docs/
│   ├── api/                          # REST API authentication, tokens, errors, and guide
│   ├── applications/                 # Application build packs, deployments, and configs
│   ├── cli/                          # CLI reference, contexts, and backup commands
│   ├── core/                         # Architecture, infrastructure, servers, networking, security
│   ├── databases/                    # Supported database engines, backups, restore, and SSL
│   ├── integrations/                 # Networking and security integrations
│   ├── knowledge-base/               # Guides on zero-downtime, load balancing, DNS, and scaling
│   ├── mcp/                          # Coolify MCP server documentation and capabilities
│   ├── services/                     # 340+ curated one-click service templates and configs
│   └── troubleshoot/                 # Common errors (502, 504, SSL, Docker daemon, SSH)
└── src/
    └── generated/
        └── services.json             # Structured JSON catalog of all services and categories
```

---

## Key Reference Areas

For in-depth lookup instructions, consult the reference files:
- `references/api-reference.md` — REST API v1 endpoints and `config/openapi.json`
- `references/services-catalog.md` — 340+ services in `content/docs/services/` and `src/generated/services.json`
- `references/troubleshooting.md` — Diagnosing and fixing proxy, build, database, and SSH errors

---

### 1. Applications & Build Packs (`content/docs/applications/`)

When working with application deployment logic in `app/Actions/Application/` or `app/Jobs/ApplicationDeploymentJob.php`:

| Topic | File Path | Key Concepts Covered |
| :--- | :--- | :--- |
| **Build Options** | `applications/builds/overview.mdx` | Comparison of Dockerfile, Docker Compose, Railpack, Nixpacks, Static |
| **Dockerfile** | `applications/builds/dockerfile.mdx` | Multi-stage builds, target stages, build args |
| **Docker Compose** | `applications/builds/docker-compose.mdx` | Custom compose files, multi-container applications |
| **Railpack** | `applications/builds/railpack.mdx` | Modern buildpack alternative to Nixpacks |
| **Static Sites** | `applications/builds/static.mdx` | Nginx-based static site hosting and SPA fallbacks |
| **Environment Variables** | `applications/configuration/environment-variables.mdx` | Build-time vs runtime variables, escaping, secrets |
| **Health Checks** | `applications/configuration/health-checks.mdx` | HTTP, TCP, and shell health checks for zero-downtime |
| **Persistent Storage** | `applications/configuration/persistent-storage.mdx` | Docker volume mounts, file storages, bind mounts |
| **Preview Deployments** | `applications/deployments/preview-deployments.mdx` | Ephemeral pull request previews and cleanup |
| **Rolling Updates** | `applications/deployments/rolling-updates.mdx` | Zero-downtime container swaps and Traefik/Caddy reload |

---

### 2. Core Engine & Networking (`content/docs/core/`)

When modifying server provisioning, proxy configuration, or orchestration:

| Topic | File Path | Key Concepts Covered |
| :--- | :--- | :--- |
| **How Coolify Works** | `core/how-coolify-works.mdx` | Control path vs Application traffic path |
| **Networking** | `core/networking-in-coolify.mdx` | Public HTTP/HTTPS, internal Docker networks (`coolify`), host ports |
| **Server Setup** | `core/infrastructure/servers/add-server.mdx` | SSH connection requirements, user permissions, automated Docker setup |
| **Docker Engine** | `core/infrastructure/servers/automated-docker-cleanup.mdx` | Pruning stale containers, builder cache, and images |
| **Docker Swarm & Multi-Server** | `core/infrastructure/scaling/` | Swarm clusters, multi-server deployment models |
| **Backup & Recovery** | `core/backup-and-recovery/` | Instance backups, database dumps, root recovery |
| **Security & RBAC** | `core/security/` and `core/team/` | OAuth providers, API tokens, SSH keys, roles & permissions |
| **Image Scanning** | `core/security/image-scanning.mdx` | Syft/Trivy SBOM vulnerability scanning |

---

### 3. Databases (`content/docs/databases/`)

When implementing database management, backups, or restore workflows:

- **Supported Engines**: PostgreSQL, MySQL, MariaDB, Redis, Dragonfly, KeyDB, ClickHouse, MongoDB, SQLite (`databases/*.mdx`).
- **Automated Backups**: `databases/backups.mdx` — S3 backup destinations, cron frequencies, retention rules.
- **Restore Operations**: `databases/restore.mdx` — Unpacking tarballs/dumps into running database containers.
- **SSL / TLS**: `databases/ssl.mdx` — Generating custom certificates and client encryption.

---

### 4. REST API & Schemas (`content/docs/api/` & `config/openapi.json`)

The single source of truth for Coolify's API contract is:
`coolify-docs-main/config/openapi.json` (OpenAPI 3.0 specification).

When adding, fixing, or extending API endpoints in `routes/api.php` or `app/Http/Controllers/Api/v1/`:
1. Search `openapi.json` for the route path (e.g., `/servers/{uuid}`, `/deploy`).
2. Verify required headers (`Authorization: Bearer <token>`, `Accept: application/json`).
3. Match request payload types, field names, and validation rules.
4. Ensure response codes and error formats match upstream expectations (`content/docs/api/errors.mdx`).

---

### 5. Curated Service Templates (`content/docs/services/`)

`coolify-docs-main/content/docs/services/` contains documentation and troubleshooting for over 340 services.
- Inspect the corresponding MDX file for any service to check required environment variables, default exposed ports, persistent volume directories, and known workarounds.
- Structured overview of all services is in `coolify-docs-main/src/generated/services.json`.

---

### 6. Troubleshooting (`content/docs/troubleshoot/`)

When diagnosing production issues:
- **502 Bad Gateway**: `troubleshoot/applications/bad-gateway.mdx` (port exposes vs listening port, `0.0.0.0` vs `127.0.0.1`, Traefik label errors).
- **504 Gateway Timeout**: `troubleshoot/applications/gateway-timeout.mdx` (proxy timeouts, long-running processes).
- **Let's Encrypt / SSL**: `troubleshoot/dns-and-domains/lets-encrypt-not-working.mdx` (DNS propagation, Cloudflare proxy conflicts, ACME challenge failures).
- **Docker Installation**: `troubleshoot/installation/docker-install-failed.mdx` (unsupported OS, iptables conflicts, mirror issues).
- **SSH / Server Connection**: `troubleshoot/server/connection-issues.mdx` (key permissions, multiplexing socket locks, sudo privileges).

---

## STX Hosting Platform Invariants

While using `coolify-docs-main` as upstream reference, remember the protected invariants of the STX Enterprises Cloud Platform:

1. **Sentinel ID = 0**: Never delete, resequence, or modify `id = 0` sentinel rows (`Team(0)`, `Server(0)`, `InstanceSettings(0)`, `StandalonePostgresql(0)`).
2. **Strict Laravel 10 Directory Layout**: Middleware in `app/Http/Middleware/`, Kernels in `app/Http/Kernel.php` and `app/Console/Kernel.php`.
3. **No Nixpacks**: Nixpacks is deprecated. Always recommend Dockerfile or Railpack.
4. **Pessimistic Locking & Security**: Financial records and entitlements use row-level pessimistic locks (`lockForUpdate()`); customer deposit slips must be pixel-sanitized via `ProofStorageService`.
5. **Frozen Expansion Primitives**: Cloud provider auto-provisioning (Hetzner, DO, Vultr) remains disabled; commercial fulfillment uses manual VPS delivery and PayVerify AI bank transfer verification.
