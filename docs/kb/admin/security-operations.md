---
title: Security and access
summary: Roles, two-factor, the audit log, protections and what to do in an incident.
order: 7
---
## Roles

| | Owner | Admin | Customer |
|---|---|---|---|
| Add a node | Yes | Yes | No |
| Remove a node | Yes | No | No |
| Verify payments | Yes | Yes | No |
| Products | Yes | Yes | No |
| Prices and payment methods | Yes | No | No |
| Node terminal, restart | Yes | No | No |
| Alert channels, emails, backup storage | Yes | No | No |
| Support inbox, backups for customers | Yes | Yes | No |

## Two-factor

Staff powers need two-factor sign-in. A staff member without it is treated as a customer until they turn it on, so a stolen password alone gets an attacker nothing. The platform team also requires it.

## Audit log

**Audit Log** shows who did what in payments, deliveries, prices, backups, emails and restores. Entries cannot be edited or deleted. Changes to apps, servers and databases are in the team audit log.

## Built-in protections

Security headers on every page, a strict content policy on the storefront (no scripts allowed), rate limits on sign-in, orders, receipts, chat, downloads and the terminal, content-checked uploads stored privately, encrypted passwords and webhooks, and a weekly dependency audit in CI.

## If an account may be compromised

1. Ask the person to change their password and turn on two-factor; for staff, remove their staff role first.
2. Check **Audit Log** for what that account did.
3. For a VPS, change the root password and remove the panel key line if needed.
4. Rotate any webhook or storage keys that person could see.

## Keys

Do not change `APP_KEY` casually: encrypted values (delivered passwords, webhooks) cannot be read afterwards.
