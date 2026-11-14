# STX Enterprises Cloud Platform Architecture Reference

## Overview

STX Enterprises Cloud Platform is an enterprise-grade cloud hosting and orchestration control plane built on **Laravel 12** (operating in the explicit Laravel 10 structure), **PHP 8.2+ / 8.4**, **Livewire 3**, and **Tailwind CSS v4**.

The platform provides a unified control plane uniting:
1. **Public Storefront & Telemetry**: Marketing, catalog, pricing, documentation, and a native 90-day status telemetry engine running on port `8000`.
2. **Customer Self-Service Portal (`/portal`)**: 1-click Git and Docker deployments, 31 curated application templates, managed standalone databases, domain management, multi-target database restores, volume backups, and email hosting.
3. **Administrative Operations Console (`/admin`)**: Manual multi-provider VPS fulfillment, side-by-side bank transfer verification queue, template catalog curation, mail plan configuration, and hypervisor health monitoring.
4. **Self-Hosted BaaS & Mail Stack**: Integrated containerized Supabase (Kong, GoTrue, PostgREST, Realtime, Storage, Studio) and WildDuck Mail (Haraka, Zone-MTA, Dovecot, MongoDB) with the containerized STX Webmail client.
5. **Multi-Vector Payment Verification (PayVerify)**: Microservice suite featuring Google Gemini 1.5 Flash multimodal vision, ComBank WhatsApp statement synchronization, and zero-leak Android OTP relay.

---

## Architectural Topology Diagram

```mermaid
flowchart TD
    subgraph Public["Public Tier (Internet Ingress)"]
        ClientBrowser["Client Web Browser / Mobile App"]
        WebhookSenders["GitHub / Webhook Clients"]
        MailClients["SMTP / IMAP Email Clients"]
    end

    subgraph IngressProxies["Reverse Proxies & TLS Termination"]
        TraefikProxy["Traefik Proxy (App Nodes - Ports 80 / 443)"]
        CaddyProxy["Caddy Auto-SSL (Mail & Webmail - Ports 80 / 443)"]
    end

    subgraph ControlPlane["STX Hosting Control Plane (Port 8000)"]
        LaravelApp["Laravel 12 / PHP 8.4 Engine"]
        ReverbWS["Laravel Reverb WebSockets (Port 6001)"]
        TerminalWS["Node.js Terminal WebSocket (Port 6002)"]
        LivewirePortal["Livewire 3 Client Portal & Admin Studio"]
        HorizonQueues["Laravel Horizon (Redis Queues)"]
    end

    subgraph DataTier["Data & Cache Layer"]
        PostgresDB[("PostgreSQL 15 / 16 (stx db)")]
        RedisCache[("Redis 7 (Cache, Queue, Sessions)")]
    end

    subgraph SupabaseBaaS["Self-Hosted Supabase BaaS"]
        KongGW["Kong API Gateway (Port 8000 / 8443)"]
        GoTrueAuth["GoTrue Auth Service"]
        PostgREST["PostgREST API"]
        RealtimeSvc["Realtime WebSocket Service"]
        StorageSvc["Supabase Storage Engine"]
        SupabaseDB[("PostgreSQL 15 (Supabase Core + pg_cron)")]
        StudioUI["Supabase Studio Web UI"]
    end

    subgraph MailStack["WildDuck Mail Stack"]
        HarakaMTA["Haraka Inbound SMTP (Port 25)"]
        ZoneMTA["Zone-MTA Outbound SMTP (Port 587)"]
        DovecotIMAP["Dovecot IMAP Server (Port 993)"]
        WildDuckAPI["WildDuck Core API (Port 8080)"]
        MailMongo[("MongoDB 6.0 Mail Store")]
        WebmailUI["STX Webmail UI (PHP-FPM / Nginx)"]
    end

    subgraph ManagedNodes["Managed Deployment Nodes (Docker over SSH)"]
        Node1["Worker Node 1 (Docker / Traefik / Containers)"]
        NodeN["Worker Node N (Docker / Traefik / Containers)"]
    end

    subgraph PayVerifyEcosystem["PayVerify Verification Suite"]
        PVFastAPI["PayVerify Core API (FastAPI Port 8000)"]
        PVWhatsApp["WhatsApp Bot Daemon (Baileys Port 3100)"]
        PVWeb["Next.js 16 Web Dashboard (Port 3000)"]
        PVAndroid["Android SMS Relay & Bank Viewer"]
    end

    ClientBrowser -->|HTTPS :443| IngressProxies
    ClientBrowser -->|HTTP / WS :8000/:6001| ControlPlane
    WebhookSenders -->|POST /webhooks| ControlPlane
    MailClients -->|SMTP :25/:587, IMAP :993| MailStack

    IngressProxies --> ControlPlane
    IngressProxies --> WebmailUI
    IngressProxies --> ManagedNodes

    ControlPlane --> PostgresDB
    ControlPlane --> RedisCache
    ControlPlane --> HorizonQueues
    ControlPlane -->|SSH / Docker API| ManagedNodes
    ControlPlane -->|REST API| SupabaseBaaS
    ControlPlane -->|REST API :8080| WildDuckAPI
    ControlPlane -->|REST API /proofs| PVFastAPI

    PVAndroid -.->|ECDSA Signed TLS| PVFastAPI
    PVWhatsApp <-->|WebSockets| PVFastAPI
    PVFastAPI --> PostgresDB
```

---

## Subsystem Details & Port Allocations

| Subsystem | Service Name | Internal Port | External Port | Protocol | Purpose |
|---|---|---|---|---|---|
| **Control Plane** | `stx` | `8000` | `8000` | HTTP | Main Laravel 12 application, Storefront, Portal, Admin |
| **Broadcasting** | `reverb` | `6001` | `6001` | WebSocket | Real-time live status updates, notifications, chat |
| **Terminal Daemon** | `terminal` | `6002` | `6002` | WebSocket | Interactive SSH and container xterm shell sessions |
| **Database** | `postgres` | `5432` | `5432` | TCP | Primary platform relational data store (PostgreSQL 15/16) |
| **Cache & Queues** | `redis` | `6379` | `6379` | TCP | Redis cache, rate limiting, and Horizon job queue broker |
| **App Reverse Proxy** | `traefik` | `80`, `443` | `80`, `443` | HTTP/HTTPS | Ingress routing and Let's Encrypt certificates for apps |
| **Mail Edge Proxy** | `caddy` | `80`, `443` | `80`, `443` | HTTP/HTTPS | Automated ACME TLS reverse proxy for mail & webmail |
| **Supabase Kong** | `supabase-kong` | `8000`, `8443` | `8443` | HTTP/HTTPS | API gateway for Supabase REST, Auth, and Storage |
| **Supabase Auth** | `supabase-auth` | `9999` | Internal | HTTP | GoTrue authentication service |
| **Supabase Studio**| `supabase-studio`| `3000` | Internal | HTTP | Web SQL studio and table manager |
| **WildDuck API** | `wildduck` | `8080` | Internal | HTTP | Mail server management API |
| **Haraka SMTP** | `haraka` | `25` | `25` | SMTP | Inbound mail receiving agent |
| **Zone-MTA** | `zonemta` | `587` | `587` | SMTP | Outbound authenticated mail delivery agent |
| **Dovecot IMAP** | `dovecot` | `993` | `993` | IMAPS | Secure IMAP mail retrieval server |
| **Webmail Client** | `webmail` | `80` | Port via Caddy | HTTP | Lightweight PHP 8.3 / Nginx webmail UI |
| **PayVerify API** | `payverify-api` | `8000` | `8088` (or routed) | HTTP | FastAPI verification API and receipt extraction |
| **WhatsApp Daemon** | `whatsapp-bot` | `3100` | `3100` | HTTP | Baileys multi-device statement sync daemon |

---

## Data Flow Sequences

### 1. Application Deployment Flow
```mermaid
sequenceDiagram
    autonumber
    actor Client as Customer
    participant Portal as Customer Portal (/portal/deploy)
    participant Gate as LimitEnforcement & TemplateAccess
    participant Queue as Horizon Redis Queue
    participant Worker as ApplicationDeploymentJob
    participant RemoteServer as Managed Docker Server
    participant Traefik as Traefik Ingress

    Client->>Portal: Selects Git Repo or Curated Template
    Portal->>Gate: Evaluates limits (RAM, CPU, container count) and template entitlement
    alt Quota or Entitlement Exceeded
        Gate-->>Portal: Blocks with 422 Unprocessable (Upgrade Required)
        Portal-->>Client: Displays Paywall / Upgrade Prompt
    else Quota Allowed
        Gate-->>Portal: Checks pass
        Portal->>Queue: Dispatches ApplicationDeploymentJob
        Portal-->>Client: Returns Deployment ID and live build stream URL
        Queue->>Worker: Executes job in background worker
        Worker->>RemoteServer: Clones Git repository or renders Compose YAML
        Worker->>RemoteServer: Injects cgroups bounds (mem_limit, cpus)
        Worker->>RemoteServer: Executes docker build / compose up -d
        Worker->>Traefik: Configures Traefik dynamic labels and Let's Encrypt SSL
        Worker-->>Portal: Emits ApplicationStatusChanged via Reverb WebSockets
        Portal-->>Client: Real-time UI updates to "Running" with live domain link
    end
```

### 2. Multi-Vector Payment Verification Flow
```mermaid
sequenceDiagram
    autonumber
    actor Customer
    participant Checkout as Customer Invoice View
    participant Job as VerifyPaymentProofJob
    participant PVClient as PayVerifyClient
    participant PVAPI as PayVerify FastAPI
    participant AI as Gemini 1.5 Flash Vision
    participant Bank as ComBank Statement Engine
    participant Staff as Staff Review Workbench

    Customer->>Checkout: Uploads bank slip photo / PDF
    Checkout->>Checkout: Pure-pixel sanitization (imagejpeg/imagepng) & dHash calculation
    Checkout->>Job: Dispatches VerifyPaymentProofJob (verifying)
    Job->>PVClient: POST /proofs/upload-and-review
    PVClient->>PVAPI: Enters PayVerify External API
    par Forensic & AI Analysis
        PVAPI->>AI: Extracts amount, reference, date, receiving account
        PVAPI->>PVAPI: Computes SHA-256 and perceptual dHash
    and Deterministic Bank Reconciliation
        PVAPI->>Bank: Queries ComBank WhatsApp statements & SMS receipts
    end
    PVAPI-->>Job: Returns confidence score, discrepancies, and bank match status
    alt Bank Match or High Confidence (>= 90%)
        Job->>Job: Auto-approves invoice, unlocks entitlement, marks order paid
        Job-->>Customer: Email: Payment Approved & Service Activated
    else Hard Reject (Duplicate / Wrong Bank / Confidence < 60%)
        Job->>Job: Rejects proof with clear explanation
        Job-->>Customer: Email: Rejection Notice with re-upload link
    else Ambiguous (60% - 89% or Discrepancies)
        Job->>Staff: Escalates to /admin/billing/proofs (pending_review)
        Staff->>Staff: Evaluates side-by-side viewer with row lock (lockForUpdate)
        Staff->>Checkout: Staff explicitly Approves or Rejects with note
    end
```

---

## Architectural Invariants & Security Boundaries

1. **Root Team Sentinel (`team_id === 0`)**: The instance itself and global administrators operate under team ID `0`. All client teams have IDs `> 0`.
2. **Customer Resource Isolation**: Customers never receive SSH access to shared control plane nodes. VPS hosting is provided as isolated remote instances; application hosting is sandboxed within Docker containers with cgroups resource boundaries.
3. **Pessimistic State Transitions**: All payment decisions, order transitions, and quota claims must acquire a row-level pessimistic database lock (`lockForUpdate()`) to eliminate dual-admin or race conditions.
4. **Pure-Pixel Proof Sanitization**: All uploaded bank receipts are decoded and re-encoded using server-side graphics libraries (`imagejpeg`/`imagepng`), stripping embedded polyglot webshells and EXIF exploits.
5. **Zero-Leak OTP Policy**: ComBank two-factor authentication codes intercepted from mobile devices are held strictly in transient server memory with a 60-second TTL. They are never written to disk, SQLite, PostgreSQL, or persistent log sinks.
