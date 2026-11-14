# Troubleshooting & Operational Runbook

## Overview

This guide catalogs known edge cases, common failure modes, diagnostic procedures, and resolutions across STX Enterprises Cloud Platform.

---

## 1. Browser & Pest Test Gotchas

### Issue 1: `Class "Redis" not found` thrown during browser tests
- **Root Cause**: The Pest Browser Plugin boots an in-process amphp HTTP server sharing the host PHP process. If the host machine lacks the `phpredis` extension, Laravel's maintenance-mode driver fails because `config/app.php` sets `'maintenance' => ['store' => 'redis']`.
- **Resolution**: Add this line inside `beforeEach` in browser test files:
  ```php
  config()->set('app.maintenance.store', 'array');
  ```

### Issue 2: Every browser path redirects to `/onboarding`
- **Root Cause**: Freshly seeded test users trigger `DecideWhatToDoWithUser` and `showBoarding()`. Furthermore, `User::currentTeam()` caches the team in-memory for 1 hour.
- **Resolution**: Clear boarding flags and flush the cache in `beforeEach`:
  ```php
  Team::query()->update(['show_boarding' => false]);
  Cache::flush();
  ```

### Issue 3: Stale Playwright servers hanging runs indefinitely
- **Root Cause**: If a previous browser test run was cancelled abruptly, orphan node Playwright processes remain running in the background.
- **Resolution**: Terminate orphan Playwright processes:
  ```powershell
  # Windows PowerShell
  Get-Process -Name node | Where-Object { $_.CommandLine -match "playwright" } | Stop-Process -Force
  
  # Linux / macOS
  pkill -f "playwright run-server"
  ```

### Issue 4: `[wire:loading]` and `[x-cloak]` elements remain visible
- **Root Cause**: Layouts that call `@livewireScripts` manually without calling `@livewireStyles` cause Livewire to disable asset auto-injection.
- **Resolution**: Always ensure both `@livewireStyles` and `@livewireScripts` are present in Blade layout files.

---

## 2. Queue & Laravel Horizon Issues

### Issue 1: Deployment jobs stuck in "Pending"
- **Diagnostic Steps**:
  1. Inspect active Horizon status: `docker exec stx php artisan horizon:status`.
  2. Verify Redis connectivity: `docker exec stx redis-cli ping`.
  3. Inspect Horizon supervisor logs: `docker logs stx | grep horizon`.
- **Resolution**: Restart Horizon workers to pick up updated code and clear deadlock states:
  ```bash
  docker exec stx php artisan horizon:terminate
  ```

### Issue 2: Job timeouts during large Docker image pulls
- **Root Cause**: Heavy Docker builds or slow network transfers exceed default job timeouts.
- **Resolution**: Increase job timeout in `app/Jobs/ApplicationDeploymentJob.php` or configure timeout limits in `config/horizon.php`.

---

## 3. SSL / TLS & Ingress Failures

### Issue 1: Let's Encrypt certificate not provisioning (Pending SSL)
- **Diagnostic Steps**:
  1. Check DNS propagation: `dig +short A app.example.com`. Ensure the IP matches the server.
  2. Check port 80 accessibility: Let's Encrypt HTTP-01 challenge requires port `80` to be accessible from the public internet. Ensure firewalls (UFW, AWS Security Groups) do not block port 80.
  3. Inspect Traefik logs: `docker logs traefik --tail 100`.
- **Resolution**:
  - If Let's Encrypt rate-limited the domain, switch temporarily to Let's Encrypt Staging environment or verify DNS CAA records.
  - For Caddy endpoints, inspect logs via `docker logs caddy --tail 100`.

---

## 4. WildDuck Mail Stack Failures

### Issue 1: Outbound email blocked or dropped
- **Root Cause**: Cloud providers (DigitalOcean, AWS, Linode) block outbound port `25` by default to prevent spam.
- **Resolution**:
  - Request port 25 unblock from your cloud provider support, OR
  - Route outbound mail through a smart host / SMTP relay (Mailgun, Postmark, Amazon SES) configured in Zone-MTA.

### Issue 2: Webmail reports "Nothing was saved" on contacts/signatures
- **Root Cause**: WildDuck user token lacks permission to update `metaData`.
- **Resolution**: Verify that the WildDuck API token configured in `.env` holds admin permissions to write user metadata.

### Issue 3: Inbound mail failing SPF/DKIM verification
- **Diagnostic Step**: Run DNS inspection via `php artisan status:probe`.
- **Resolution**: Confirm the `s1._domainkey` DKIM TXT record matches the public key generated in `docker/mail/`.

---

## 5. PayVerify API & Slip Verification Issues

### Issue 1: WhatsApp Bot reports status `DISCONNECTED` or socket closed `428`
- **Root Cause**: The merchant phone link was unlinked in WhatsApp or WhatsApp Web session timed out.
- **Resolution**:
  1. Open Web Admin Dashboard ➔ WhatsApp Sync (`/admin/whatsapp-sync`).
  2. Request a new **8-digit pairing code** (`POST /pair/code`).
  3. Re-link the bot in WhatsApp: **Linked Devices ➔ Link with phone number instead**.

### Issue 2: Android SMS Relay stopped syncing OTPs
- **Root Cause**: Android OS battery optimization killed the background SMS broadcast receiver.
- **Resolution**:
  1. Open Android Phone Settings ➔ Apps ➔ PayVerify.
  2. Set **Battery Usage** to **Unrestricted**.
  3. Ensure **Allow Background Activity** is enabled.

### Issue 3: Google Gemini API quota exceeded
- **Resolution**: The system automatically falls back to local Ollama CPU (`LocalOllamaExtractor`) or flags the proof for manual staff review (`pending_review`). Obtain a free Gemini API key from Google AI Studio and update `PV_GEMINI_API_KEY`.

---

## 6. Database & Sentinel Row Landmines

### Issue 1: Keyset pagination skips `id = 0` sentinel rows
- **Root Cause**: In PHP/Eloquent, `empty(0)` is `true`. Keyset queries like `where('id', '>', $cursor)` will skip the root team or localhost server if the cursor starts at 0.
- **Resolution**: Use `chunkById()` or queries with no lower bound on the initial page:
  ```php
  // Good:
  Team::query()->orderBy('id')->chunkById(50, function ($teams) { ... });
  ```
