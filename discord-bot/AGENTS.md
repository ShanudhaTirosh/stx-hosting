# AGENTS.md: Developer & AI Agent Operating Manual

This document defines the architectural invariants, security boundaries, and coding standards that **all developers and AI coding agents MUST strictly follow** when modifying or extending the STX Discord Bot codebase.

---

## 1. Core Architectural Invariants

### Invariant 1: Least-Privilege & No Administrator Permission
- The bot process MUST NEVER require or be granted Discord's `Administrator` permission.
- All actions (channel creation, member bans, message deletion, timeouts) must execute using granular Discord permissions (`ManageChannels`, `ModerateMembers`, `BanMembers`, `ManageMessages`).
- When assigning roles or moderating, always verify `member.moderatable` and handle role hierarchy exceptions gracefully.

### Invariant 2: Honeypot Decoy Trap Mechanism
- The decoy trap channel (e.g. `#do-not-type-here`) **must have `Send Messages` ALLOWED** for `@everyone`.
- If `Send Messages` is denied in Discord permissions, bots cannot post and the trap is rendered completely useless.
- Staff and bot admins (`staff_role_ids`, `admin_role_ids`, `ManageGuild`) must be strictly exempt from honeypot sanctions to prevent accidental lockouts.
- When triggered by an unverified account, always execute `member.ban({ deleteMessageSeconds: 604800 })` to purge recent spam, record an entry in `discord_mod_logs`, and notify moderators in `#mod-logs`.

### Invariant 3: Order Data Confidentiality & Anti-Enumeration
- Order lookups via `/order` MUST ALWAYS reply ephemerally (`ephemeral: true`).
- A case-insensitive comparison against `profiles.email` is mandatory before returning any order data.
- Failed lookups must be bounded by the sliding-window rate limiter (`ORDER_RATE_LIMIT_ATTEMPTS = 3` per 15 minutes).
- Error messages on mismatch must be completely generic (`"No matching order was found with that ID and email combination"`). Never leak whether the order ID exists in the database.

### Invariant 4: Zero Public Invoice Leaks
- Paid invoice embeds must only be sent to the customer's direct messages (`user.send()`).
- If direct messages are closed (`DiscordAPIError 50007`), the fallback destination is strictly an active private ticket channel or the web customer portal dashboard.
- Under NO circumstance may invoice numbers, customer names, or monetary totals be sent to public server channels.

### Invariant 5: Stateless Process & Database Ground Truth
- The bot process is stateless. All persistent configurations, tickets, moderation logs, FAQs, and links reside in Supabase PostgreSQL.
- Hot caches (guild configs, join velocity, rate limits) use TTL-bounded in-memory maps or Redis and must never prevent horizontal multi-shard scaling.

---

## 2. Directory Layout & Module Ownership

| Path | Purpose |
|---|---|
| `src/commands/` | Slash commands & Context menu commands (store, help, tickets, mod, admin) |
| `src/events/` | Discord Gateway listeners (ready, interactionCreate, messageCreate, guildMemberAdd, guildMemberRemove) |
| `src/services/` | Core business logic (honeypot, antiRaid, antiSpam, phishing, tickets, onboarding, invoices) |
| `src/database/` | Supabase client and typed repositories (GuildConfig, Tickets, ModLogs, Store, Accounts) |
| `src/queue/` | Asynchronous worker task queue for slow operations |
| `src/http/` | Internal HTTP server (:3050) for `/health` probe and `/api/webhook/invoice-paid` |
| `tests/` | Unit and integration test suite (`node --test`) |

---

## 3. Running Verification & Tests

Whenever code or commands are modified, run the automated test suite:
```bash
cd discord-bot
npm test
```
**Acceptance Criteria**: All test suites pass with 0 failures.
