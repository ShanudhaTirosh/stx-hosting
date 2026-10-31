# Deliverable I: Operations Runbook, Deployment Blueprint & Incident Response Playbooks

**Document Identifier**: `DOC-STX-OPS-009`  
**Version**: `1.0.0`  
**Classification**: Site Reliability Engineering (SRE) & Operational Standard  
**Target Systems**: ShanuTechX Storefront (`web`), Status Platform (`status`), Webmail (`webmail`), STX Hosting Orchestration Engine (`coolify`), PostgreSQL Cluster, Redis Infrastructure, Hypervisors  
**Author**: ShanuTechX Platform Operations & SRE Infrastructure Group  
**Status**: Approved for Production  

---

## 1. Executive Summary & Operational Invariants

This runbook establishes the production deployment architecture, disaster recovery procedures, continuous monitoring, and step-by-step incident response playbooks for the ShanuTechX Commercial Hosting Platform.

### 1.1 Service Level Objectives (SLOs)
- **Platform Availability**: 99.9% monthly uptime across public edge, customer portal, and orchestration plane.
- **Recovery Point Objective (RPO)**: **< 1 hour** (maximum permissible data loss window in any disaster event).
- **Recovery Time Objective (RTO)**: **< 2 hours** (maximum permissible duration to restore full operational services from cold standby).
- **Manual VPS Provisioning SLA**: **< 24 hours** (aligned with public storefront notice banner: 24 to 72 hours).
- **Payment Proof Verification SLA**: **< 4 hours** (during operational hours 08:00–22:00 UTC).

---

## 2. Production Multi-Service Deployment Architecture

The ShanuTechX hosting platform runs as a coordinated multi-container production topology managed via Docker Compose and fronted by an edge Traefik reverse proxy with automated Let's Encrypt wildcard SSL certificates.

### 2.1 Multi-Service Topology Diagram

```
                                  [ INTERNET ]
                                       │
                  ┌────────────────────┴────────────────────┐
                  ▼                                         ▼
         Port 80 (HTTP Redirect)                Port 443 (HTTPS TLS Edge)
                  │                                         │
                  └────────────────────┬────────────────────┘
                                       │
                    ┌──────────────────▼──────────────────┐
                    │       Traefik Reverse Proxy         │
                    │  (Edge Router & Let's Encrypt SSL)  │
                    └──────────────────┬──────────────────┘
                                       │
         ┌──────────────────┬──────────┴──────────┬──────────────────┐
         │ :3000            │ :3003               │ :3002            │ :8000
         ▼                  ▼                     ▼                  ▼
  ┌───────────────┐  ┌───────────────┐     ┌───────────────┐  ┌───────────────┐
  │  Storefront   │  │Status Platform│     │    Webmail    │  │STX Hosting Backend│
  │     (web)     │  │   (status)    │     │   (webmail)   │  │(Commerce/API) │
  │ Next.js 14    │  │ Next.js 15    │     │ Next.js 14    │  │Laravel 12 /   │
  │ App Router    │  │ SQLite DB     │     │ App Router    │  │ Livewire 3    │
  └───────────────┘  └───────┬───────┘     └───────────────┘  └───┬───────┬───┘
                             │                                    │       │
                             │ Telemetry Probes                   │       │
                             └──────────────┐                     │       │
                                            ▼                     ▼       │
                                     ┌─────────────┐       ┌─────────────┐│
                                     │ PostgreSQL  │       │   Redis 7   ││
                                     │  16 Cluster │       │  (Queues &  ││
                                     │(`coolify-db`│       │  Sessions)  ││
                                     └─────────────┘       └─────────────┘│
                                                                  ▲       │
                                                                  │       │
                                                           ┌──────┴───────▼┐
                                                           │Horizon Workers│
                                                           │& Reverb (6001)│
                                                           └───────────────┘
```

### 2.2 Production Docker Compose Specification (`docker-compose.prod.yml`)

```yaml
# docker-compose.prod.yml
version: '3.8'

networks:
  coolify-edge:
    name: coolify-edge
    external: false
  coolify-internal:
    name: coolify-internal
    internal: true

volumes:
  postgres-data:
    name: coolify-postgres-data
  redis-data:
    name: coolify-redis-data
  status-data:
    name: coolify-status-data
  coolify-storage:
    name: coolify-app-storage
  coolify-ssh:
    name: coolify-ssh-keys
  traefik-acme:
    name: coolify-traefik-acme

services:
  # ---------------------------------------------------------------------------
  # Edge Reverse Proxy
  # ---------------------------------------------------------------------------
  traefik:
    image: traefik:v3.1
    container_name: coolify-traefik
    restart: always
    command:
      - "--api.dashboard=false"
      - "--providers.docker=true"
      - "--providers.docker.exposedbydefault=false"
      - "--entrypoints.web.address=:80"
      - "--entrypoints.web.http.redirections.entrypoint.to=websecure"
      - "--entrypoints.web.http.redirections.entrypoint.scheme=https"
      - "--entrypoints.websecure.address=:443"
      - "--certificatesresolvers.letsencrypt.acme.tlschallenge=true"
      - "--certificatesresolvers.letsencrypt.acme.email=${ACME_EMAIL:-admin@shanutechx.com}"
      - "--certificatesresolvers.letsencrypt.acme.storage=/acme/acme.json"
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock:ro
      - traefik-acme:/acme
    networks:
      - coolify-edge

  # ---------------------------------------------------------------------------
  # Core Database Cluster
  # ---------------------------------------------------------------------------
  postgres:
    image: postgres:16-alpine
    container_name: coolify-postgres
    restart: always
    environment:
      POSTGRES_DB: ${DB_DATABASE:-coolify}
      POSTGRES_USER: ${DB_USERNAME:-coolify}
      POSTGRES_PASSWORD: ${DB_PASSWORD:?Database password required}
      PGDATA: /var/lib/postgresql/data/pgdata
    volumes:
      - postgres-data:/var/lib/postgresql/data
    networks:
      - coolify-internal
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME:-coolify} -d ${DB_DATABASE:-coolify}"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ---------------------------------------------------------------------------
  # In-Memory Cache & Horizon Queue Broker
  # ---------------------------------------------------------------------------
  redis:
    image: redis:7-alpine
    container_name: coolify-redis
    restart: always
    command: ["redis-server", "--appendonly", "yes", "--requirepass", "${REDIS_PASSWORD:?Redis password required}"]
    volumes:
      - redis-data:/data
    networks:
      - coolify-internal
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5

  # ---------------------------------------------------------------------------
  # STX Hosting Orchestration & Commerce Core App
  # ---------------------------------------------------------------------------
  coolify:
    image: ghcr.io/stx-enterprises/stx-hosting:latest
    container_name: coolify-core
    restart: always
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
      APP_URL: ${APP_URL:-https://app.shanutechx.com}
      DB_CONNECTION: pgsql
      DB_HOST: postgres
      DB_PORT: 5432
      DB_DATABASE: ${DB_DATABASE:-coolify}
      DB_USERNAME: ${DB_USERNAME:-coolify}
      DB_PASSWORD: ${DB_PASSWORD}
      REDIS_HOST: redis
      REDIS_PASSWORD: ${REDIS_PASSWORD}
      PUSHER_BACKEND_HOST: 127.0.0.1
      PUSHER_BACKEND_PORT: 6001
    ports:
      - "6001:6001"
    volumes:
      - coolify-storage:/var/www/html/storage
      - coolify-ssh:/var/www/html/storage/app/ssh
      - /var/run/docker.sock:/var/run/docker.sock
    networks:
      - coolify-edge
      - coolify-internal
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.coolify.rule=Host(`app.shanutechx.com`)"
      - "traefik.http.routers.coolify.entrypoints=websecure"
      - "traefik.http.routers.coolify.tls.certresolver=letsencrypt"
      - "traefik.http.services.coolify.loadbalancer.server.port=80"
      # Laravel Reverb WebSocket Router (Port 6001)
      - "traefik.http.routers.coolify-ws.rule=Host(`app.shanutechx.com`) && (PathPrefix(`/app`) || PathPrefix(`/reverb`))"
      - "traefik.http.routers.coolify-ws.entrypoints=websecure"
      - "traefik.http.routers.coolify-ws.tls.certresolver=letsencrypt"
      - "traefik.http.routers.coolify-ws.service=coolify-ws"
      - "traefik.http.services.coolify-ws.loadbalancer.server.port=6001"

  # ---------------------------------------------------------------------------
  # Public Storefront (`web`)
  # ---------------------------------------------------------------------------
  storefront:
    build:
      context: ./web
      dockerfile: Dockerfile
    container_name: coolify-storefront
    restart: always
    environment:
      NODE_ENV: production
      NEXT_PUBLIC_APP_URL: https://shanutechx.com
      NEXT_PUBLIC_API_URL: https://app.shanutechx.com/api
      NEXT_PUBLIC_DASHBOARD_URL: https://app.shanutechx.com
      NEXT_PUBLIC_STATUS_URL: https://status.shanutechx.com
    networks:
      - coolify-edge
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.storefront.rule=Host(`shanutechx.com`) || Host(`www.shanutechx.com`)"
      - "traefik.http.routers.storefront.entrypoints=websecure"
      - "traefik.http.routers.storefront.tls.certresolver=letsencrypt"
      - "traefik.http.services.storefront.loadbalancer.server.port=3000"

  # ---------------------------------------------------------------------------
  # Status Platform (`status`)
  # ---------------------------------------------------------------------------
  status-platform:
    build:
      context: ./status
      dockerfile: Dockerfile
    container_name: coolify-status
    restart: always
    environment:
      NODE_ENV: production
      PORT: 3003
      ADMIN_PASSWORD_HASH: ${STATUS_ADMIN_HASH}
      JWT_SECRET: ${STATUS_JWT_SECRET}
    volumes:
      - status-data:/app/data
    networks:
      - coolify-edge
      - coolify-internal
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.status.rule=Host(`status.shanutechx.com`)"
      - "traefik.http.routers.status.entrypoints=websecure"
      - "traefik.http.routers.status.tls.certresolver=letsencrypt"
      - "traefik.http.services.status.loadbalancer.server.port=3003"
```

---

## 3. Backup & Disaster Recovery Runbook

### 3.1 Backup Strategy & Schedule
To meet our RPO < 1 hour and RTO < 2 hours:
1. **PostgreSQL Relational State (`coolify-db`)**:
   - Snapshot frequency: **Every 30 minutes** via automated cron worker.
   - Mechanism: Native `pg_dump` with gzip compression piped to S3-compatible object storage (Cloudflare R2 or AWS S3).
   - Retention: Keep 48 hourly backups (2 days), 30 daily backups (1 month), 12 monthly archives (1 year).
2. **STX Hosting SSH & Encryption Keys**:
   - Backup frequency: **Daily** or immediately upon new key generation.
   - Stored in an encrypted S3 vault using GPG asymmetric encryption.
3. **Status Platform SQLite Database (`status.db`)**:
   - Backup frequency: **Every 6 hours** via SQLite `.backup` API command to prevent lock contention.

### 3.2 Automated Database Backup Script (`scripts/backup-db.sh`)

```bash
#!/usr/bin/env bash
set -euo pipefail

# Configuration
TIMESTAMP=$(date -u +"%Y%m%d_%H%M%SZ")
BACKUP_DIR="/var/backups/coolify"
BACKUP_FILE="${BACKUP_DIR}/coolify_db_${TIMESTAMP}.sql.gz"
S3_BUCKET="s3://stx-platform-backups/database"
DISCORD_WEBHOOK="${DISCORD_OPS_WEBHOOK:-}"

mkdir -p "${BACKUP_DIR}"

echo "[$(date -u)] Initiating PostgreSQL database dump..."

# Execute pg_dump inside the running postgres container
docker exec coolify-postgres pg_dump -U coolify -d coolify | gzip -9 > "${BACKUP_FILE}"

FILE_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
echo "[$(date -u)] Backup created successfully: ${BACKUP_FILE} (${FILE_SIZE})"

# Upload to off-site S3 storage
echo "[$(date -u)] Syncing backup to remote S3 bucket..."
aws s3 cp "${BACKUP_FILE}" "${S3_BUCKET}/coolify_db_${TIMESTAMP}.sql.gz" --storage-class STANDARD_IA

# Prune local backups older than 7 days
find "${BACKUP_DIR}" -type f -name "coolify_db_*.sql.gz" -mtime +7 -delete

echo "[$(date -u)] Backup routine complete."

# Optional Discord notification
if [[ -n "${DISCORD_WEBHOOK}" ]]; then
  curl -s -H "Content-Type: application/json" -X POST \
    -d "{\"content\": \"✅ **PostgreSQL Backup Succeeded**\\n• Snapshot: \`coolify_db_${TIMESTAMP}.sql.gz\`\\n• Size: \`${FILE_SIZE}\`\\n• Destination: \`${S3_BUCKET}\`\"}" \
    "${DISCORD_WEBHOOK}" > /dev/null || true
fi
```

### 3.3 Cold Disaster Recovery Procedure (Step-by-Step)
In the event of total server loss or datacenter destruction:

1. **Provision New Base Server**:
   - Spin up a clean Ubuntu 24.04 LTS host.
   - Install Docker Engine and Docker Compose v2.
   - Clone platform repository to `/opt/coolify-platform`.
2. **Retrieve Latest Encrypted Backup from S3**:
   ```bash
   aws s3 cp s3://stx-platform-backups/database/latest.sql.gz /tmp/restore.sql.gz
   aws s3 sync s3://stx-platform-backups/keys/ /var/lib/docker/volumes/coolify-ssh-keys/_data/
   ```
3. **Start PostgreSQL Container**:
   ```bash
   docker compose -f docker-compose.prod.yml up -d postgres
   docker compose -f docker-compose.prod.yml exec -T postgres pg_isready -U coolify
   ```
4. **Restore Database Snapshot**:
   ```bash
   gunzip -c /tmp/restore.sql.gz | docker exec -i coolify-postgres psql -U coolify -d coolify
   ```
5. **Verify Database Integrity**:
   ```bash
   docker exec -it coolify-postgres psql -U coolify -d coolify -c "SELECT COUNT(*) FROM users; SELECT COUNT(*) FROM orders; SELECT COUNT(*) FROM service_instances;"
   ```
6. **Launch Full Application Stack**:
   ```bash
   docker compose -f docker-compose.prod.yml up -d
   ```
7. **Verify DNS & Routing**:
   - Update Cloudflare DNS A records to new host IP.
   - Confirm Traefik re-issues Let's Encrypt certificates.
   - Verify storefront, customer portal, and status platform are healthy.

---

## 4. Monitoring, Health Probes & Alerting Runbook

### 4.1 Telemetry Sources & Thresholds
The platform aggregates metrics across three layers:
1. **Public Edge Probes (`status` platform)**:
   - Evaluates HTTP response codes and latency from independent monitoring locations.
   - Failure Condition: 2 consecutive failed probes trigger incident declaration.
2. **Sentinel Container & Host Agent**:
   - Evaluates host memory, CPU load, and disk utilization.
   - Alarms trigger if host RAM > 85%, host CPU > 90% for 5 minutes, or disk space > 80%.
3. **Laravel Horizon Queue Monitoring**:
   - Monitors queue backlog on `deployments`, `billing`, and `default`.
   - Alert triggers if `failed_jobs` count > 0 or job wait time > 180 seconds.

### 4.2 Multi-Channel Alert Routing
- **P0 Outage Alerts (Immediate Wake-Up)**: Routed to Telegram On-Call Bot and SMS gateway.
- **P1 Degraded / Security Alerts**: Routed to Discord `#ops-alerts` channel.
- **P2 Transaction / Proof Uploads**: Routed to Discord `#billing-queue` channel.

#### Discord Webhook Notification Schema:
```json
{
  "username": "STX Platform SRE",
  "avatar_url": "https://shanutechx.com/logo.png",
  "embeds": [
    {
      "title": "🚨 P0 PLATFORM ALERT: Host Node Unreachable",
      "color": 15158332,
      "fields": [
        { "name": "Affected Node", "value": "node-fsn1-kvm01 (198.51.100.22)", "inline": true },
        { "name": "Impacted Tenants", "value": "14 Active VPS Instances", "inline": true },
        { "name": "Status", "value": "TCP Ping Failed (Port 22)", "inline": false }
      ],
      "timestamp": "2026-10-29T16:00:00Z"
    }
  ]
}
```

### 4.3 Proactive TLS/SSL Certificate Expiration Telemetry Daemon

To prevent sudden production outages caused by silent ACME/Let's Encrypt renewal failures (such as customer DNS changes or Cloudflare flexible SSL interference), the platform executes a daily proactive TLS probe (`CheckSslCertificateExpiryJob`):
1. **Direct TLS SNI Handshake & Certificate Inspection**:
   - The worker establishes a TLS connection to each managed endpoint and customer domain using OpenSSL:
     ```php
     $g = stream_context_create(["ssl" => ["capture_peer_cert" => true, "verify_peer" => false]]);
     $r = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $g);
     $cert = openssl_x509_parse(stream_context_get_params($r)['options']['ssl']['peer_certificate']);
     $validTo = \Carbon\Carbon::createFromTimestamp($cert['validTo_time_t']);
     $daysRemaining = now()->diffInDays($validTo, false);
     ```
2. **Two-Stage Expiration Alerting Thresholds**:
   - **Warning Threshold (14 Days Remaining, `days <= 14`)**:
     - Dispatches automated email to customer: *"Notice: SSL certificate for {$domain} expires in {$days} days. Please verify DNS A-records point to our ingress IP to permit automatic Let's Encrypt renewal."*
     - Logs warning event to Discord `#ops-alerts`.
   - **Critical Alarm Threshold (7 Days Remaining, `days <= 7`)**:
     - Triggers P1 alert to on-call SRE via Telegram and Discord.
     - Automatically verifies DNS A-record via `dns_get_record($domain, DNS_A)`. If DNS points away, alerts ops to engage customer before browser security warnings appear (`NET::ERR_CERT_DATE_INVALID`).
3. **ACME Pre-Flight DNS Validation**:
   - Before submitting domains to Traefik's Let's Encrypt resolver, the system validates DNS resolution. If records are unpropagated or point elsewhere, the ACME resolver is held back to prevent exceeding Let's Encrypt's 5-failures-per-hour rate limit.

---

## 5. Operational Incident Response Playbooks

---

### Playbook 1: Manual VPS Provisioning & Customer Onboarding

#### Trigger:
An invoice for a VPS product has been marked `'paid'` (either via admin manual proof verification or gateway webhook), and a provisioning task appears in `/admin/operations/provisioning`.

#### SLA:
Execution within **< 24 hours** from invoice settlement.

#### Step-by-Step Operator Procedure:

```
[Step 1: Intake]
Operator opens `/admin/operations/provisioning` and selects the pending order.
Inspects order details: Variant (e.g. CX22), Customer Hostname, Customer Public SSH Key, OS (e.g. Ubuntu 24.04).
       │
       ▼
[Step 2: Inventory Selection]
Operator clicks "Allocate Node".
System filters `vps_inventory` for `status = 'available'` matching specifications in the requested datacenter.
Operator selects Node (e.g. IPv4: `198.51.100.44`, Hypervisor: `pve-node-01`, VMID: `1044`).
       │
       ▼
[Step 3: Hypervisor Guest Configuration]
Operator logs into Proxmox VE console (or executes automated CLI):
1. Clone gold template:
   qm clone 9000 1044 --name "vps-client-1044" --full
2. Set CPU & Memory resources:
   qm set 1044 --cores 2 --memory 4096
3. Inject Customer Public SSH Key:
   qm set 1044 --sshkeys /tmp/customer_key.pub
4. Configure Static Network:
   qm set 1044 --ipconfig0 ip=198.51.100.44/24,gw=198.51.100.1
5. Start the Guest VM:
   qm start 1044
       │
       ▼
[Step 4: Connectivity & Health Validation]
Operator verifies guest connectivity (MANDATORY: Disable SSH Agent Forwarding to prevent guest root hijacking):
ssh -o ForwardAgent=no -o StrictHostKeyChecking=accept-new -i /opt/ops/admin_key debian@198.51.100.44 "uptime"
(Preferred out-of-band validation via QEMU Guest Agent: `qm guest exec 1044 -- uptime`)
Verifies QEMU guest agent is reporting IP to hypervisor.
       │
       ▼
[Step 5: System Activation & Telemetry Registration]
In the Admin Console, operator clicks "Confirm Provisioning":
- `vps_inventory.status` transitions from `reserved` to `allocated`.
- `service_instances.status` transitions to `active`.
- Automated TCP probe (port 22) registered via Status Platform REST API (`POST /api/admin/monitors`).
- Automated welcome email dispatched to customer containing IP and connection instructions.
```

---

### Playbook 2: Manual VPS Deprovisioning & Cryptographic Wipe

#### Trigger:
A customer cancels a VPS, or an overdue service exceeds the **7-day suspension grace period** without payment.

#### Purpose:
Completely sanitize customer data, destroy disk volumes, and return hardware to the `available` inventory pool without residual data leakage.

#### Step-by-Step Operator Procedure:

1. **Verify Deprovisioning Authorization**:
   - Check that `service_instances.status == 'suspended'`.
   - Confirm suspension date was at least 7 days ago, or customer explicitly clicked "Terminate Service" with 2FA confirmation.
2. **Execute Hypervisor Purge (Proxmox KVM)**:
   ```bash
   # Connect to target hypervisor
   ssh root@pve-node-01.mgmt.shanutechx.internal
   
   # Stop VM immediately, skipping lock
   qm stop 1044 --skiplock 1 || true
   
   # Destroy VM and purge all allocated ZFS/LVM disks
   qm destroy 1044 --purge 1
   ```
3. **Bare Metal Disk Sanitization (If applicable)**:
   - For dedicated physical hosts, reboot node into Network PXE Rescue mode:
   ```bash
   # Execute hardware TRIM / discard on NVMe drive
   blkdiscard -f /dev/nvme0n1
   
   # Overwrite drive with single-pass pseudorandom data + zero-fill
   shred -v -n 1 -z /dev/nvme0n1
   ```
4. **Re-clone Pristine Base Template**:
   ```bash
   qm clone 9000 1044 --name "vps-clean-1044" --full 1
   ```
5. **Inventory Release**:
   - In Admin Console, navigate to `/admin/inventory/vps/{id}`.
   - Click **Mark Wiped & Available**.
   - `vps_inventory.status` transitions to `'available'`.
   - Remove monitor probe from `status` platform.

---

### Playbook 3: Fraudulent or Tampered Payment Proof Handling

#### Trigger:
An uploaded payment slip appears altered (font mismatch, altered transaction ID, duplicated SHA-256 hash, or invalid bank statement match).

#### Step-by-Step Operator Procedure:

1. **Place Freeze on Order & Invoice**:
   - In `/admin/billing/proofs`, click **Flag as Suspicious**.
   - Order transitions to `frozen`; invoice remains `payment_pending`.
   - Prevents automated provisioning jobs from processing this invoice.
2. **Forensic Inspection**:
   - Check `payment_proofs.sha256_hash` against historical database records:
     ```sql
     SELECT id, invoice_id, team_id, user_id, status, created_at 
     FROM payment_proofs 
     WHERE sha256_hash = :hash;
     ```
     *(Aligned with Deliverable D schema: `sha256_hash VARCHAR(64) NOT NULL` indexed via `idx_payment_proofs_hash`).*
   - If hash exists on another user's invoice, **instant proof duplication fraud** is confirmed.
   - Inspect PDF metadata: check creation dates, editing software (Photoshop/Canva indicators), and mismatched font encodings.
   - Log into bank / crypto merchant gateway to verify whether funds landed with matching reference number.
3. **Execution of Fraud Action**:
   - Click **Reject Proof & Suspend Account**:
     - `payment_proof.status` set to `'fraudulent'`.
     - `invoice.status` set to `'cancelled'`.
     - `users.is_active` set to `false`.
     - User IP address added to Cloudflare IP access block rule.
4. **Audit Documentation**:
   - System records entry in `audit_logs` capturing admin ID, reason, and original file hash.

---

### Playbook 4: Host Server Outage & Container Failover

#### Trigger:
Status platform alerts `node-fsn1-kvm01 (198.51.100.22)` is unreachable via ICMP and SSH for > 3 minutes.

#### Step-by-Step Operator Procedure:

1. **Outage Triage & Out-of-Band Verification**:
   - Attempt IPMI / iLO / KVM-over-IP console connection to physical chassis.
   - Check upstream datacenter network status page (e.g. Hetzner Status / OVH Status).
2. **Customer Communication Incident Declaration**:
   - Navigate to Status Platform Admin (`https://status.shanutechx.com/admin/incidents`).
   - Click **Create Incident**:
     - Title: "Service Disruption - Falkenstein Datacenter Compute Node".
     - Status: **Investigating**.
     - Component: Mark "Europe Cloud VPS" as **Major Outage**.
3. **Hardware Recovery Attempt**:
   - If server is kernel-panicked, issue hard reboot via IPMI power cycle:
     ```bash
     ipmitool -H 10.0.10.22 -U admin -P $IPMI_PASS power reset
     ```
   - Monitor reboot sequence via serial-over-LAN (SOL).
4. **Emergency Container Failover (For Application Hosting)**:
   - If node hardware is permanently failed:
     1. Open STX Hosting Admin Console.
     2. Identify applications hosted on failed server destination.
     3. Re-assign applications to standby deployment server `node-fsn1-app02`.
     4. Trigger bulk re-deployment via `POST /api/v1/deploy?tag=failover-group`.
     5. Update Traefik routing or Cloudflare DNS to point customer domains to standby ingress IP.
5. **Post-Recovery Verification**:
   - Verify all guest VMs or containers are in `running:healthy` status.
   - Close incident on Status Platform with detailed post-mortem notes.

---

## 6. Operational Checklist & Routine Maintenance Schedules

| Frequency | Task | Procedure | Responsible Role |
|---|---|---|---|
| **Every 30 Mins** | PostgreSQL S3 Backup | Automated cron script execution & hash check | SRE Daemon |
| **Daily (00:00)** | Dunning & Invoicing Run | `php artisan hosting:generate-invoices` | Horizon Worker |
| **Daily (02:00)** | Overdue Suspension Run | `php artisan hosting:process-suspensions` | Horizon Worker |
| **Daily (06:00)** | TLS/SSL Expiry Sweep | Proactive SNI probe: alert at <= 14d (warn) and <= 7d (critical) | Telemetry Worker |
| **Daily (08:00)** | Inventory Pool Audit | Verify at least 3 nodes per tier in `available` pool | Ops Lead |
| **Weekly (Sun)** | S3 Backup Snapshot Test | Test restore of latest backup into isolated Docker container | SRE Lead |
| **Monthly (1st)** | OS Security Patches | Rolling `apt update && apt upgrade` on host nodes | Infrastructure Team |

---

## 7. Summary

This operations runbook provides actionable, production-ready procedures covering every aspect of ShanuTechX platform administration, from Docker Compose deployment to cold disaster recovery and forensic fraud handling. Proceed to **Deliverable J: Prioritized Roadmap** (`10-prioritized-roadmap.md`) for the 14-day execution schedule to first paid customer onboarding.
