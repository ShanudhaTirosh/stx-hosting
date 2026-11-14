# STX Hosting Technology Stack

## Frontend

- **Blade & Livewire 3**: Server-driven reactive component architecture with Alpine.js
- **Tailwind CSS v4**: Utility-first CSS engine with `@tailwindcss/forms` and `@tailwindcss/typography`
- **Monaco Editor**: Embedded browser-based code and configuration editor
- **XTerm.js**: High-performance terminal emulator for container and SSH shell sessions
- **Native Storefront & Status**: High-performance server-rendered Blade storefront and 90-day status telemetry engine operating on port 8000

## Backend & Control Plane

- **Laravel 12**: Core application framework (operating within Laravel 10 directory structure)
- **PHP 8.2+ / 8.4**: Modern typed backend runtime
- **PostgreSQL 15 / 16**: Primary relational database with UUID (CUID2) primary keys
- **Redis 7**: High-throughput cache, queue broker, and real-time pub/sub store
- **Laravel Horizon**: Redis queue monitoring, supervisor balancing, and worker management
- **Laravel Reverb**: First-party WebSocket server (port 6001) for live deployments, notifications, and chat
- **Terminal Daemon**: Node.js WebSocket terminal proxy (port 6002)

## Self-Hosted BaaS & Mail Infrastructure

- **Self-Hosted Supabase Stack**:
  - **Kong Gateway**: API gateway and reverse proxy (port 8000/8443)
  - **GoTrue**: Supabase JWT authentication and user management
  - **PostgREST**: Instant RESTful API reflection over PostgreSQL
  - **Realtime**: Elixir-based WebSocket engine for change broadcasts
  - **Storage**: S3-compatible file storage API
  - **Studio**: Browser administration and SQL studio
  - **PostgreSQL 15**: Database with `pg_cron` extensions
- **WildDuck Email Stack**:
  - **WildDuck API 1.55.2**: Node.js mail server API with MongoDB storage
  - **Haraka**: High-performance inbound SMTP MTA
  - **Zone-MTA**: Outbound SMTP delivery engine with DKIM signing and IP pool management
  - **Dovecot**: IMAP server daemon
  - **MongoDB 6**: Document store for email messages and mailbox metadata
  - **Redis**: Rate limiting, quota tracking, and delivery queues
- **STX Webmail UI (`webmail-ui`)**:
  - Standalone, lightweight PHP-FPM / Nginx webmail client
  - WildDuck API backend integration with secure session storage
  - Deep-linking and single-click access from the client portal
- **Caddy Reverse Proxy**:
  - Automated ACME TLS certificate provisioning and reverse proxying for email endpoints and webmail

## DevOps, Containerization & Infrastructure

- **Docker Engine & Docker Compose v2**: Container orchestration and cgroups resource limits
- **Traefik**: Ingress reverse proxy with automatic SSL certificate management for deployed applications
- **S6 Overlay**: Multi-process supervision inside the core STX container
- **Nixpacks & Cloud Native Buildpacks**: Automated zero-config container builds from Git repositories
- **Cloudflare R2 & Amazon S3**: Offsite encrypted volume and database backup storage targets

## Testing & Quality Assurance

- **Pest 4 & PHPUnit 11**: Automated feature, unit, and integration testing suites
- **Pest Browser Plugin**: Playwright-driven headless Chromium browser verification
- **Adversarial Gate Suites**: Multi-tier security probing, RBAC boundary verification, and regression prevention
