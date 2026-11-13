# Coolify Releases & Changelog (Post October 29, 2026)

This document provides a comprehensive log of all changes, features added, security hardening, breaking changes, and bug fixes in **Coolify** ([coollabsio/coolify](https://github.com/coollabsio/coolify/releases)) released after **October 29, 2026**.

---

## 📋 Overview of Releases

Between October 29, 2026 and November 14, 2026, **7 releases** were published:

| Version | Release Date | Type | Key Highlights |
| :--- | :--- | :--- | :--- |
| [**v4.7.5**](https://github.com/coollabsio/coolify/releases/tag/v4.7.5) | Nov 10, 2026 | Patch | Fixes API token crash & SFTP backup IPv6 / connection error messages. |
| [**v4.7.4**](https://github.com/coollabsio/coolify/releases/tag/v4.7.4) | Nov 10, 2026 | Patch | Auto-cleanup for failed backups & preview deployment runtime variable retention. |
| [**v4.7.3**](https://github.com/coollabsio/coolify/releases/tag/v4.7.3) | Nov 09, 2026 | Patch | Vulnerability report export (Markdown/CSV/JSON), scanner time limits & backup permissions. |
| [**v4.7.2**](https://github.com/coollabsio/coolify/releases/tag/v4.7.2) | Nov 09, 2026 | Patch | Redeploy/Stop destination scoping, interactive breadcrumbs, image scanner memory fixes. |
| [**v4.7.1**](https://github.com/coollabsio/coolify/releases/tag/v4.7.1) | Nov 09, 2026 | Patch | Image scan error logs, maintenance mode custom labels/wildcards fix. |
| [**v4.7.0**](https://github.com/coollabsio/coolify/releases/tag/v4.7.0) | Nov 09, 2026 | Minor Feature Release | Auto-cancel queued webhook builds, container vulnerability scanning (Syft/Grype), Valkey support, SFTP/FTPS/WebDAV backup storages, GitHub Deployments sync. |
| [**v4.6.0**](https://github.com/coollabsio/coolify/releases/tag/v4.6.0) | Nov 02, 2026 | Minor Feature Release | Fine-grained RBAC & API token scopes, `delete` ability, Update Channels (Stable/Preview/Nightly), interactive container console, CI workflow hardening, zstd compression. |

---

## 🚨 Breaking Changes Summary

### In v4.7.0
1. **Auto-Cancellation of Queued Webhook Deployments**:
   - By default, older queued webhook deployments of an application are now cancelled when a newer push arrives, ensuring only the latest commit is built.
   - Can be turned off in `Advanced` > `Deployment` or via `is_auto_cancel_queued_deployments_enabled` in the API.
   - Manual, API, restart, and rollback deployments are never cancelled automatically.
2. **Cloud Provider Token API Restricted**:
   - Limited strictly to team admins and owners (`403` returned for other members).
3. **Resend Email Configuration**:
   - Moved Resend sender configuration to new `resend_from_address` and `resend_from_name` fields.

### In v4.6.0
1. **API / MCP Delete Permissions (`delete` ability)**:
   - Deletions via the API and MCP server now strictly require the new `delete` ability or `root`.
   - An API token with `write` alone can no longer delete resources, environment variables, storages, backups, scheduled tasks, or tags.

---

## 🚀 Key Features Added Across Releases

### 1. Security & Vulnerability Scanning
- **Built-in Vulnerability Scanning (Syft & Grype)**:
  - Opt-in scanning per server and per application running locally on your server (no data sent to third parties).
  - Displays security posture, version recommendations, and an optional deployment gate.
  - Vulnerability lists can be exported as a Markdown table, CSV, or JSON file.
  - Dedicated API endpoints (`/api/v1/servers/{uuid}/image-scanning`, `/api/v1/vulnerabilities`) and MCP tools (`get_vulnerabilities`, `manage_image_scanning`).
  - Scan error log drawer detailing failed steps, exit codes, and output.
  - Customizable CPU, memory, and timeout limits (Syft and Grype).

### 2. Permissions, Access Control & API Tokens
- **Granular Scopes for Members and Tokens**:
  - Restrict team members and API tokens to specific projects, environments, or resources.
  - Tiered access levels: `read`, `read sensitive`, `deploy`, `write`, `delete`, and `root`.
  - Token access cannot exceed owner privileges and revokes if the owner loses access.
- **Token Lifecycle Controls**:
  - Ability to edit description, permissions, access scopes, and expiry without changing token value.
  - Ability to pause/resume tokens and roll token secrets in-place.
  - Warning notification 7 days prior to token expiration.

### 3. Deployments & Git Integrations
- **GitHub Deployments Sync**: Syncs application and preview deployments with GitHub Deployments, linking logs and environments directly.
- **Forgejo & Codeberg Support**: Integrated directly into Gitea App sources, with webhook compatibility for `X-Forgejo-*` headers and dedicated API/MCP endpoints.
- **Git over HTTPS with Username & Password / Token**: Clone private repositories from custom Git hosts without embedding secrets into repository URLs.
- **Deploy Options via MCP**: Support for `cancel_previous` (`none`, `queued`, `building`) on `/api/v1/deploy` and MCP `deploy`.

### 4. Backups & Storage
- **Expanded Backup Protocols**: Support for **SFTP**, **FTPS**, and **WebDAV** backup storages for database and volume backups (including downloads, retention, and restores).
- **S3 TLS Skip Verification**: Allows backup and restore operations to S3 storage endpoints using self-signed or private CA certificates.
- **Backup Retention Cleanup**: Daily automated cleanup of failed database backups older than 60 days.

### 5. Services & Database Engine Additions
- **Valkey Support**: Introduced Valkey as an engine option for Redis, setting `valkey/valkey:9-alpine` as the default for new Redis database resources.
- **Updated Defaults**: Updated default database images for new instances to PostgreSQL 18, MongoDB 9, MySQL 9.7, MariaDB 12.3, ClickHouse 26.8, and Supabase PostgreSQL 17.11.
- **New One-Click Templates**:
  - **Orbit**: Self-hosted team workspace (tasks, docs, chat, MCP server).
  - **Hi.Events**: Event management platform.

### 6. Updates & Release Channels
- **Selectable Update Channels**: Choose between `stable`, `preview` (betas / release candidates), `nightly` (built twice daily), or custom preview images (`pr-<number>`).
- CLI tool `php artisan app:update-channel` and `install.sh --preview` / `install.sh --nightly` flags.

### 7. Diagnostics, Monitoring & Terminal
- **Interactive Container Main Process Console**: Direct stdin/stdout interactive console next to the container shell for processes that keep stdin open (e.g. game servers, REPLs).
- **Amazon CloudWatch Logs Drain**: Stream server logs directly to AWS CloudWatch Logs using the Docker `awslogs` driver.
- **Fleet Metrics Acceleration**: Benchmark performance improved 10x to 19x by multiplexing metric queries into single SSH commands.
- **Scheduled Tasks without Shell**: "Run in a shell" toggle to allow distroless or scratch containers without `sh` to execute tasks directly.

---

## 🔒 Security Hardening

- **SSH Hardening**:
  - Generation of new SSH keys defaults to Ed25519 with stricter private key validation.
  - Host key pinning on first server connection (`SSH_HOST_KEY_CHECKING`: `warn`, `enforce`, or `off`).
  - Published SSH host keys pinned across deployments for GitHub, GitLab, and Bitbucket.
  - Enforced SSH connection options and multiplexing fixes.
  - Dedicated key rotation feature to replace keys across all assigned servers.
- **Credential Protection**:
  - Stored private keys kept out of browser responses.
  - Anonymization of personal data and credentials from audit logs upon user deletion.
  - Sentinel tokens removed from process CLI arguments (now injected via env files).
  - Locked environment variable values hidden from resource listings in API endpoints.
- **Rate Limiting & Abuse Prevention**:
  - API rate limit of 30 failed requests/minute per token/IP (`API_FAILED_REQUEST_RATE_LIMIT`), returning `429 Too Many Requests`.
- **Infrastructure & Supply Chain**:
  - Hardened GitHub Actions CI with full commit SHA pinning and `.github/workflows/actions.lock`.
  - Attestation of SBOM (Software Bill of Materials) attached to all published container images.
  - Strict branch and tag protection rules preventing updates or deletions of release tags and branches.
  - Image layer compression migrated to `zstd` for faster pulls and smaller footprint.

---

## 🛠️ Detailed Release-by-Release Notes

### Release v4.7.5 (2026-11-10)
#### Fixed
- **API Tokens Page Crash**: Fixed a 500 error on `Keys & Tokens` > `API Tokens` caused by tokens with scopes saved in an unexpected format.
- **SFTP Backup Connection Errors**: Resolved failed connections to SFTP backup storages using IPv6 addresses, and ensured meaningful network error reasons (e.g., `Connection refused`, `Connection timed out`) are surfaced.

---

### Release v4.7.4 (2026-11-10)
#### Fixed
- **Failed Backup Retention**: Prevented indefinitely retaining failed database backup records; daily cleanup now purges failed backups older than 60 days.
- **Preview Deployment Variables**: Fixed issue where production runtime variables were missing in preview deployments when no matching preview-specific variable existed. Deployment logs now clearly note any variable exclusions.

---

### Release v4.7.3 (2026-11-09)
#### Added
- **Vulnerability Exports**: Added `Export` menu on vulnerability lists (resource, server, and team security pages) supporting Markdown table copy, CSV download, and JSON download. Vulnerability IDs can also be copied directly.
#### Fixed
- **Notification Links**: Corrected broken links in backup notifications for instance databases and service databases.
- **Instance Backup Permissions**: Fixed an issue denying root team owners and admins ability to modify and download instance backups on `Settings` > `Backup` ([#12503](https://github.com/coollabsio/coolify/issues/12503), [#12505](https://github.com/coollabsio/coolify/issues/12505)).
- **Scanner Timeouts**: Resolved timeouts when scanning large images with Syft/Grype by introducing customizable timeout limits (`image_scan_syft_timeout`, `image_scan_grype_timeout`) and CPU limits.

---

### Release v4.7.2 (2026-11-09)
#### Security
- **Destination Scoping**: Restricted `Redeploy` and `Stop` actions of an application destination strictly to the servers that the application actually runs on.
#### Changed
- **Top Breadcrumbs**: Breadcrumb names now link directly to their respective views (Servers, Projects, Environments, Resources, Storages, Tags).
#### Fixed
- **Root Setup Window**: Fixed a bug where a fresh installation closed the root account setup window prematurely, preventing initial account registration.
- **Scanner OOM Issues**: Scanner now frees memory earlier; added `Scanner memory limit` / `image_scan_memory_mb`.
- **Nightly Channel Visibility**: Added hints on the `Settings` > `Updates` page and upgrade dialog to help users discover the Nightly update channel ([#12492](https://github.com/coollabsio/coolify/issues/12492)).

---

### Release v4.7.1 (2026-11-09)
#### Added
- **Image Scan Error Logs**: Error details for failed image scans are now viewable in a `Show error log` section on the Security page and recorded in the Laravel log.
#### Fixed
- **Self-Hosted Image Scans**: Resolved scanner failures on self-hosted instances caused by missing scanner templates in the Coolify image.
- **Maintenance Mode Labels**: Fixed ignored custom container labels and wildcard domains when activating maintenance mode ([#12498](https://github.com/coollabsio/coolify/issues/12498)).
- **Custom Theme Warning Callouts**: Callouts now correctly use yellow warning styling rather than the theme's accent color.

---

### Release v4.7.0 (2026-11-09)
#### Breaking Changes
- Auto-cancel older queued webhook deployments when a newer push arrives.
- Cloud provider token API limited exclusively to team owners and admins.
- Migration of Resend email sender fields to `resend_from_address` and `resend_from_name`.
#### Added
- Syft & Grype vulnerability scanning for servers and applications.
- Valkey added as a first-class Redis database alternative.
- GitHub Deployments synchronization for pull request previews and builds.
- SFTP, FTPS, and WebDAV support for volume and database backups.
- `Run in a shell` toggle for scheduled tasks (distroless / scratch support).
- Automatic update notifications sent to root team.
- SSH Key rotation mechanism across servers.
- Star button on servers and resources for quick dashboard access.
- Orbit service template.
- Skip TLS certificate verification setting for S3 backup destinations.
- Docker Compose subdirectory warning with automatic fix button.
#### Fixed Highlights (Selected from 60+ Fixes)
- Prevented deleting applications on all destinations when only removing a secondary destination server.
- Resolved database backup worker timeout issues leaving dump files on the server.
- Fixed Docker Compose preview network isolation to prevent orphan containers.
- Fixed high CPU usage in `dockerd` while streaming large container logs.
- Added retry mechanism for GitHub API calls on 5xx / connection timeouts.
- Fixed Docker installation on Pop!_OS, Linux Mint, and Raspbian 13 (Trixie).
- Fixed WebSocket handshake `413` errors caused by oversized cookie headers.
- Fixed LiteLLM, Convex, Ente, Bugsink, Usesend, and AppFlowy templates.
- Fixed multi-line environment variables losing trailing newlines.

---

### Release v4.6.0 (2026-11-02)
#### Breaking Changes
- API and MCP deletions require the explicit `delete` capability or `root`.
#### Security
- CI workflows locked to exact commit SHAs via `actions.lock`.
- Protection rules enabled for releases, tags, and archive branches.
- Reverb WebSocket path restricted to `/app/`, preventing unintended exposure of `/apps`.
- Locked environment variable values hidden from resource listing API endpoints.
#### Added
- Granular permission system (scopes per project/environment/resource).
- Update channels: Stable, Preview (betas/RCs), Nightly (twice daily).
- Interactive main container console for stdin-based containers.
- CloudWatch logs integration using the `awslogs` driver.
- DNS record management, integration tokens, and secret manager support in API and MCP.
- Hi.Events one-click service template.
- SBOM attestation attached to all published container images.
#### Changed
- Redesigned release workflow (`release.json` driven).
- Switched Docker image layer compression from gzip to `zstd`.
- Accelerated Fleet Metrics queries (up to 19x faster).
- SQLite image upgraded to `3.54.1-v0.2.0`.
#### Removed
- Removed legacy Windows Docker Desktop support files (`docker-compose.windows.yml`).
- Removed `next` and `sha-*` image tags on Docker Hub in favor of `ghcr.io` nightlies and prereleases.

---

*Changelog compiled from the official [Coolify GitHub Releases](https://github.com/coollabsio/coolify/releases).*

