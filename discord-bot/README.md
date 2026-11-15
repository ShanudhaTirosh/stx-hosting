# STX Enterprises Cloud Platform - Discord Bot

An enterprise, highly scalable Discord bot for the STX Enterprises Cloud Platform community and storefront. Built on Node.js 22 with `discord.js` v14 and Supabase PostgreSQL.

---

## 🌟 Key Features

1. **Automated Onboarding & Anti-Alt Checks**:
   - Welcome embed in `#welcome` with member count and rules link.
   - Goodbye notice in `#goodbye`.
   - Account-age quarantine check: flags new/young accounts to `#mod-logs`.
   - Verification gate: interactive button verification before granting full member access.
2. **Scam & Bot Protection**:
   - **Honeypot Trap Channel (`#do-not-type-here`)**: A clearly labeled decoy trap channel. Regular members are warned never to type there. Any non-staff member or compromised account posting there is immediately banned, recent messages are purged across channels (`deleteMessageSeconds: 604800`), an audit entry is recorded, and an alert is broadcast to moderators.
   - **Anti-Raid Velocity Monitor**: Circular buffer tracks join frequency. Spikes exceeding thresholds trigger automated server lockdown.
   - **Anti-Spam & Mass Mention Suppression**: SHA-256 message hashing detects rapid duplicate spam. Mass mentions (>5) or unauthorized `@everyone` / `@here` trigger immediate message deletion and timeouts.
   - **Phishing & Scam Link Filter**: Regex scanner checks for known fake Discord Nitro gift domains, Steam trade scams, and unauthorized invite links.
3. **Invoices & Customer Notifications**:
   - Automatic DM delivery of paid invoice receipts upon payment approval with formatted amount, currency, and portal links.
   - **Closed DM Fallback**: When direct messages are closed (`DiscordAPIError 50007`), the bot routes the alert to an active private ticket channel or customer dashboard notice—**never** exposing invoice numbers or customer details in public channels.
   - Account Linking (`/link`): Cryptographic 6-character one-time codes link Discord Snowflake IDs to STX platform user profiles.
4. **Support Ticket Desk with Staff Claiming**:
   - Interactive dropdown panel (`/ticket panel`) supporting multiple categories: Billing, Cloud VPS, Game Hosting, Web/App Hosting, Email Accounts, and Other.
   - Sequential ticket numbering (`ticket-0142`) in private channels.
   - Staff Claiming Workflow: Claim, Unclaim, Transfer, Add/Remove users, and Priority setting.
   - Automated Inactivity Reminders and response-time metrics.
   - **Full Transcripts**: Standalone HTML and plain text transcripts generated on close, sent to customer DM, and archived in `#ticket-logs`.
   - Post-close 1–5 star customer satisfaction survey.
5. **Platform Store & Telemetry**:
   - `/products [query] [category]`: Real-time catalog search with vCPU, RAM, Storage, and pricing tiers.
   - `/store`: Category directory overview.
   - `/order <order_id> <email>`: **Strictly ephemeral** order lookup with email verification and an anti-guessing brute force rate limiter (3 attempts / 15 minutes).
   - `/status`: Real-time infrastructure, database, and proxy telemetry reading from platform monitors and incidents.
   - `/suggest <idea>`: Suggestions board with interactive community voting and staff status updates.
   - Right-click Context Menu -> **Report Message**: Flags suspicious messages to staff in `#mod-logs`.
6. **Staff & Administration Tools**:
   - Moderation suite: `/warn`, `/timeout`, `/kick`, `/ban`, `/unban`, `/purge`, `/modlogs`, `/note`.
   - Automated setup wizard: `/setup` configures channels and permissions in one step.
   - Config manager: `/config` to adjust channels, roles, and thresholds.

---

## 🛠️ Architecture & Scalability

```text
discord-bot/
├── src/
│   ├── index.ts                # Service entrypoint & graceful shutdown
│   ├── shard.ts                # ShardingManager for multi-shard clustering
│   ├── client.ts               # Discord Client initialization & intent declarations
│   ├── config/                 # Zod environment schemas & constants
│   ├── database/               # Supabase PostgreSQL client & typed repositories
│   ├── commands/               # Slash commands & Message context menus
│   ├── events/                 # Gateway event handlers (ready, interaction, message, joins)
│   ├── services/               # Core business logic (honeypot, tickets, onboarding, invoices)
│   ├── queue/                  # Asynchronous task queue with rate-limit retries
│   └── http/                   # HTTP server (:3050) for health checks & invoice webhooks
├── tests/                      # Automated unit and integration test suite
├── Dockerfile                  # Production container image
├── docker-compose.yml          # Standalone or integrated service definition
└── ecosystem.config.cjs        # PM2 process file with cluster/sharding support
```

### Discord Developer Portal Setup
1. Create a new Application at [Discord Developer Portal](https://discord.com/developers/applications).
2. Under **Bot**, toggle ON:
   - **Server Members Intent** (GuildMembers)
   - **Message Content Intent** (MessageContent)
3. Under **OAuth2 -> URL Generator**, select scopes:
   - `bot`
   - `applications.commands`
4. Select Permissions (Least-Privilege — **Do NOT select Administrator**):
   - Manage Roles, Manage Channels, Kick Members, Ban Members, Timeout Members, View Audit Log, View Channels, Send Messages, Manage Messages, Embed Links, Attach Files, Read Message History, Add Reactions, Use External Emojis.
5. Role Hierarchy: In your server's **Server Settings -> Roles**, drag the Bot's role **above** regular member roles.

---

## 🚀 Quickstart & Deployment

### 1. Local Development
```bash
cd discord-bot
npm install
cp .env.example .env
# Fill in DISCORD_BOT_TOKEN, DISCORD_CLIENT_ID, SUPABASE_URL, etc.
npm run dev
```

### 2. Running Automated Tests
```bash
npm test
```

### 3. Deploy Slash Commands
```bash
npm run deploy-commands
```

### 4. Docker Deployment
```bash
docker compose up -d --build
```

### 5. PM2 Production Deployment
```bash
npm run build
pm2 start ecosystem.config.cjs
```

---

## 🔒 Security Invariants & Principles
- **No Administrator Permission**: The bot operates strictly within its assigned role permissions and respects Discord role hierarchy.
- **Pure Ephemeral Order Data**: Order lookups via `/order` are strictly ephemeral and rate-limited. Mismatched emails return generic errors to prevent IDOR enumeration.
- **Honeypot Perms**: `#do-not-type-here` must allow `Send Messages` for `@everyone` so that automated scrapers trigger the trap, while human members are warned. Staff are strictly exempt.
- **Zero Public Invoice Leaks**: Invoices are delivered only via direct message or private ticket threads.
