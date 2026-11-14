---
title: Email Hosting & Mail Stack Operations
summary: Administrator guide for managing the WildDuck mail stack, pooled email plans, and STX Webmail UI.
audience: admin
order: 8
---

STX Hosting includes an enterprise-grade, self-hosted email infrastructure powered by the **WildDuck API**, **Haraka**, **Zone-MTA**, **Dovecot**, and the containerized **STX Webmail UI**.

---

## 1. Architecture Overview

```
                      STX EMAIL HOSTING INFRASTRUCTURE
┌────────────────────────────────────────────────────────────────────────┐
│ INBOUND / OUTBOUND TLS TRAFFIC (Automated ACME Certificates via Caddy) │
└───────────────────┬───────────────────────────────┬────────────────────┘
                    │ Port 25 / 465 / 587           │ Port 993
                    ▼                               ▼
     ┌─────────────────────────────┐  ┌─────────────────────────────┐
     │ HARAKA INBOUND MTA          │  │ DOVECOT IMAP SERVER         │
     │ SpamAssassin / ClamAV Checks│  │ Authenticated Mail Access   │
     └──────────────┬──────────────┘  └──────────────┬──────────────┘
                    │                                │
                    ▼                                ▼
┌────────────────────────────────────────────────────────────────────────┐
│ WILDDUCK API (Port 8080) & MONGODB / REDIS                             │
│ • Mailbox storage, user authentication, and message indexing in MongoDB│
│ • Zone-MTA for high-reputation outbound delivery and DKIM auto-signing │
└───────────────────▲───────────────────────────────▲────────────────────┘
                    │                               │
                    │ Internal API (Token Auth)     │ Internal API
                    │                               │
     ┌──────────────┴──────────────┐  ┌─────────────┴───────────────┐
     │ STX WEBMAIL UI (`webmail-ui`)│  │ STX HOSTING CONTROL PLANE   │
     │ Fast Nginx / PHP-FPM Webmail│  │ MailPlanStudio & Dashboard  │
     └─────────────────────────────┘  └─────────────────────────────┘
```

---

## 2. Mail Plan Studio (`/admin/commerce/mail`)

Platform administrators configure sellable pooled email plans and modular add-ons in the Mail Plan Studio:

### Pre-Configured Pooled Storage Plans:
1. **Starter Mail** (`starter`): 5 GB Pooled Storage, 2 Mailboxes, 1 Domain, 5 Aliases — $3.00/mo ($30.00/yr).
2. **Basic Mail** (`basic`): 15 GB Pooled Storage, 5 Mailboxes, 2 Domains, 15 Aliases — $6.00/mo ($60.00/yr).
3. **Pro Mail** (`pro`): 50 GB Pooled Storage, 15 Mailboxes, 5 Domains, 50 Aliases — $15.00/mo ($150.00/yr).
4. **Business Mail** (`business`): 100 GB Pooled Storage, 50 Mailboxes, 15 Domains, Unlimited Aliases — $30.00/mo ($300.00/yr).

### Configurable Add-ons:
- **Extra Mailbox**: Add additional active mailboxes per month.
- **Extra Storage**: Add extra storage chunks (e.g. +10 GB or +50 GB).
- **Extra Custom Domain**: Allow routing additional company domains to the pooled quota.

---

## 3. Provisioning & WildDuck Service Integration

All mailbox, domain, and alias lifecycles are orchestrated by `app/Services/Commerce/WildDuckProvisioningService.php`:

- **Domain Registration**: When a customer links a domain in `/portal/mail`, the service registers the domain with WildDuck API and retrieves the unique DKIM public key selector.
- **Mailbox Creation**: Validates that mailbox storage allocation does not exceed the plan's pooled storage quota. Passwords are set with bcrypt/argon2 hashing on the WildDuck backend.
- **Password Updates & Quota Adjustments**: Administrators or clients can update mailbox passwords and adjust storage caps in real time.
- **Service Suspension**: If an email hosting subscription enters overdue suspension, `WildDuckProvisioningService` disables login access while preserving mailbox contents.

---

## 4. DNS Verification Standards

For reliable email deliverability, custom domains must configure standard DNS records:

| Record Type | Host | Target / Value | Purpose |
|---|---|---|---|
| **MX** | `@` | `mail.<your-hosting-domain>.com` (Priority 10) | Directs inbound email to Haraka MTA |
| **TXT (SPF)** | `@` | `v=spf1 mx include:<your-hosting-domain>.com ~all` | Authorizes outbound mail servers |
| **TXT (DKIM)**| `s2026._domainkey` | `v=DKIM1; k=rsa; p=<generated-public-key>` | Cryptographic sender signature |
| **TXT (DMARC)**| `_dmarc` | `v=DMARC1; p=quarantine; pct=100; rua=mailto:postmaster@...` | Email policy and spoof prevention |

Clients can view their exact, pre-filled DNS records directly in the portal.

---

## 5. STX Webmail UI (`webmail-ui`)

The STX Webmail UI is a lightweight, zero-dependency PHP 8.3/8.4 web application running under Nginx and PHP-FPM:
- **Session Security**: Session tokens are encrypted with `AEAD_AES_256_GCM` using `APP_KEY`.
- **Portal Deep-Linking**: From `/portal/mail`, clicking "Open Webmail" redirects directly to the webmail login screen with pre-filled mailbox credentials.
- **Multi-Account Support**: Users can switch between up to 5 email accounts in a single browser session.
- **Attachment Handling**: Uploads and downloads are streamed efficiently with base64 memory bounds checks.

