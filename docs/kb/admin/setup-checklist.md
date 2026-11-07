---
title: First-time setup checklist
summary: Everything to configure before taking customers, in order.
order: 1
---
## 1. Install and migrate

Run `php artisan migrate`. Make sure a **queue worker** and the **scheduler** (`schedule:run` every minute) are running. Emails, alerts, backups, restores, VPS usage readings and the delivery reminders depend on them.

## 2. Environment settings

| Setting | What it does |
|---|---|
| `STOREFRONT_NAME`, `STOREFRONT_PARENT_BRAND` | Name shown on the storefront and in emails |
| `STOREFRONT_SUPPORT_EMAIL`, `STOREFRONT_DISCORD_URL`, `STOREFRONT_WHATSAPP` | Contact options; the contact form emails the first one |
| `COMMERCE_BUSINESS_NAME`, `_ADDRESS`, `_EMAIL` | Invoice header |
| `HOSTING_APPS_DOMAIN` | Wildcard domain for client apps, for example `apps.example.com` |
| `HOSTING_INGRESS_IP` | IP shown to clients for custom domains |
| `HOSTING_ENFORCE_ENTITLEMENTS` | Keep `true` in production: paying unlocks deployments |
| `STX_REQUIRE_STAFF_2FA` | Keep `true`: staff need two-factor |
| `BACKUP_DEFAULT_QUOTA_GB`, `BACKUP_STORAGE_UUID` | Backup space per team and an optional pre-chosen storage |
| `PAYMENT_PROOFS_DISK`, `CHAT_DISK` | Where receipts and chat files live (private disks) |

## 3. In the staff area

1. Turn on **two-factor** for every staff account (Profile).
2. **Payment Methods**: add how customers pay, per currency.
3. **Emails**: read and adjust the wording; send yourself a test.
4. **Alert Channels**: add Discord webhooks for orders, proofs, delivery reminders, support and backups.
5. **Backups**: choose the R2 storage and test it.
6. **Products and plans**: check prices (USD and LKR).
7. Install `composer require dompdf/dompdf` for PDF invoices.

## 4. Check

Run `php artisan stx:security-check`. It lists what to fix now and what is worth fixing. Run it again after every change to the setup.
