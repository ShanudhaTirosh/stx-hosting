# Upstream Coolify Services Catalog Reference

## Overview

Coolify supports over 340 one-click open-source services. The authoritative documentation for these services is housed in:

- **Markdown/MDX Service Docs**: `coolify-docs-main/content/docs/services/` (347 individual `.mdx` files)
- **Aggregated Service Index**: `coolify-docs-main/src/generated/services.json`

Use this reference when curating, troubleshooting, or creating compose templates in `templates/compose/`.

---

## Service File Structure

Each service file in `coolify-docs-main/content/docs/services/{service-name}.mdx` contains:

1. **Frontmatter**:
   - `title`: Display name of the service
   - `description`: Short summary
   - `category`: Category (e.g., Database, Development, Analytics, Productivity, Media)
   - `icon`: Icon asset path
2. **Architecture Notes & Default Credentials**:
   - Outlines default usernames, passwords, and tokens.
   - Example: Supabase anon keys, MinIO root credentials, Vaultwarden admin token.
3. **Environment Variables**:
   - Required vs optional environment variables.
   - Special prefix rules (e.g. `SERVICE_SUPABASEANON_KEY`, `SERVICE_PASSWORD_POSTGRES`).
4. **Port & Proxy Configuration**:
   - Internal ports to map in Traefik or Caddy.
   - Host port exposure instructions (e.g. database ports `${POSTGRES_PORT:-5432}:${POSTGRES_PORT:-5432}`).
5. **Persistent Volumes**:
   - Required named volumes or bind mounts to preserve data across restarts and upgrades.

---

## High-Frequency Services & Upstream Docs

| Service | Upstream MDX Path | Core Components |
| :--- | :--- | :--- |
| **Supabase** | `content/docs/services/supabase.mdx` | Studio, Kong, Auth (GoTrue), PostgREST, Realtime, Storage, Postgres |
| **WordPress** | `content/docs/services/wordpress.mdx` | WordPress PHP-FPM / Apache, MySQL / MariaDB |
| **MinIO** | `content/docs/services/minio.mdx` | MinIO Server, MinIO Console (S3 compatible object storage) |
| **Vaultwarden** | `content/docs/services/vaultwarden.mdx` | Lightweight Bitwarden alternative with WebSocket notifications |
| **Ghost** | `content/docs/services/ghost.mdx` | Node.js Ghost publishing platform + MySQL |
| **Plausible** | `content/docs/services/plausible.mdx` | ClickHouse, PostgreSQL, Plausible analytics engine |
| **Nextcloud** | `content/docs/services/nextcloud.mdx` | Nextcloud server, Redis cache, PostgreSQL |
| **Grafana / Prometheus** | `content/docs/services/grafana.mdx` | Metrics dashboard, Prometheus scraper |
| **N8N** | `content/docs/services/n8n.mdx` | Workflow automation tool |
| **Uptime Kuma** | `content/docs/services/uptime-kuma.mdx` | Self-hosted monitoring tool |
| **Directus** | `content/docs/services/directus.mdx` | Headless CMS and API engine |
| **Appwrite** | `content/docs/services/appwrite.mdx` | End-to-end BaaS platform |

---

## How to Query `services.json`

`coolify-docs-main/src/generated/services.json` contains structured JSON data for all services.

### Find a service by name or keyword
```powershell
$services = Get-Content "coolify-docs-main\src\generated\services.json" | ConvertFrom-Json
$services | Where-Object { $_.name -match "redis" -or $_.title -match "redis" }
```

### List all categories
```powershell
$services = Get-Content "coolify-docs-main\src\generated\services.json" | ConvertFrom-Json
$services | Select-Object -ExpandProperty category -Unique
```

---

## STX Template Invariant Reminder

In STX Platform:
- Curated production compose templates are stored in `templates/compose/` (31 active templates).
- Unused/archived templates reside in `templates/not-used/`.
- Do not blindly import raw uncurated compose templates into `templates/compose/` without testing them on the STX Docker environment. Always check `coolify-docs-main/content/docs/services/` for configuration requirements and known port/volume pitfalls.
