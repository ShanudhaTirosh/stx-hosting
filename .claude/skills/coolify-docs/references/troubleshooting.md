# Upstream Coolify Troubleshooting & Operational Runbooks

## Overview

The `coolify-docs-main/content/docs/troubleshoot/` directory contains diagnostic runbooks for the most frequent issues encountered when running, deploying, and proxying resources in Coolify.

When troubleshooting application deployments, proxy issues, SSL certificates, or server SSH connections, check these guides first.

---

## Troubleshooting Guide Index

### 1. Application & Proxy Issues (`content/docs/troubleshoot/applications/`)

| Symptom / Error | File | Root Cause & Resolution |
| :--- | :--- | :--- |
| **502 Bad Gateway** | `bad-gateway.mdx` | Container not listening on `0.0.0.0`; port in **Port Exposes** does not match container's listening port; container crashing before proxy connects. |
| **504 Gateway Timeout** | `gateway-timeout.mdx` | Application taking longer than proxy timeout to reply; heavy database query or cold start; increase proxy timeout settings. |
| **Too Many Redirects** | `too-many-redirects.mdx` | SSL redirect loop caused by Cloudflare Flexible SSL combined with proxy HTTPS redirect; switch Cloudflare to "Full (Strict)". |
| **No Available Server (503)** | `no-available-server.mdx` | Proxy cannot find upstream container in Docker network; verify container is running and attached to the `coolify` network. |
| **Failed to Get Token** | `failed-to-get-token.mdx` | GitHub App or git authentication token expired or invalid permissions. |

---

### 2. DNS, SSL & Domains (`content/docs/troubleshoot/dns-and-domains/`)

| Symptom / Error | File | Root Cause & Resolution |
| :--- | :--- | :--- |
| **Let's Encrypt Not Working** | `lets-encrypt-not-working.mdx` | Port 80/443 blocked by firewall; ACME challenge cannot reach proxy; Cloudflare proxying preventing HTTP-01 challenge. |
| **Wildcard SSL Certificates** | `wildcard-ssl-certs.mdx` | Requires DNS-01 challenge via DNS provider API (Cloudflare, DigitalOcean, Route53); cannot use HTTP-01 for wildcards. |
| **Certificate Resolver Doesn't Exist** | `certificate-resolver-doesnt-exist.mdx` | Traefik configuration missing the named certificate resolver in `traefik.yml` or dynamic config. |

---

### 3. Server Connection & Daemon Issues (`content/docs/troubleshoot/server/` & `installation/`)

| Symptom / Error | File | Root Cause & Resolution |
| :--- | :--- | :--- |
| **SSH Connection Issues** | `server/connection-issues.mdx` | Private key permissions too open; stale SSH multiplexing socket (`SshMultiplexingHelper::removeMuxFile()`); firewall blocking SSH port. |
| **Server Validation Issues** | `server/validation-issues.mdx` | Non-root user missing passwordless sudo for Docker commands; Docker daemon not running. |
| **Crash During Build** | `server/crash-during-build.mdx` | Server OOM (Out Of Memory) during Docker build or npm build; add swap memory (min 2-4GB) or configure build server. |
| **2FA Stopped Working** | `server/two-factor-stopped-working.mdx` | Time drift between host server and authenticator app (NTP desync); run `php artisan 2fa:reset` or database query. |
| **Docker Install Failed** | `installation/docker-install-failed.mdx` | Unsupported Linux distribution or conflicting container packages (podman/moby); clean conflicting packages. |

---

## Practical Diagnostic Steps for Agents

1. **Check Container Status & Logs**:
   ```bash
   docker ps -a --filter "name=<app_or_service_name>"
   docker logs --tail 100 <container_id>
   ```

2. **Verify Docker Network Membership**:
   ```bash
   docker network inspect coolify
   ```
   Ensure both the proxy container (`coolify-proxy`) and the target application container are attached to the `coolify` network.

3. **Check Container Listening Socket**:
   ```bash
   docker exec -it <container_id> netstat -tuln # or ss -tuln
   ```
   The application must listen on `0.0.0.0:<port>`, **NOT** `127.0.0.1:<port>`.

4. **Verify SSH Multiplexing & Key**:
   When SSH commands fail with exit code 255, ensure multiplexing sockets are flushed and the key has `0600` permissions.
