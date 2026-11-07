---
title: How it fits together
summary: The pieces, the flows and where to extend.
order: 8
---
## The shape

The platform is the Coolify engine (teams, servers, apps, databases, proxy, backups) with a commerce layer on top. Customers are tenant teams on shared nodes; staff are members of the root team.

## Order to service

1. **Cart and checkout** create an order and invoice (`OrderService`); prices always come from the catalog.
2. **Receipt** (`ProofStorageService`) is checked and stored privately.
3. **Review** (`PaymentReviewService`) approves or rejects; approval records an entitlement (plan type, expiry, CPU and memory).
4. **Gate** (`DeploymentGate`) lets a team create and deploy only with an active entitlement and inside limits. It sits in the deployment queue and on resource creation.
5. **VPS** orders go to the provisioning queue and are delivered by hand (`VpsFulfillmentService`).

## Where code lives

| Area | Location |
|---|---|
| Permissions | `config/stx-permissions.php`, `RbacService` |
| Commerce | `app/Services/Commerce`, `app/Models/Commerce` |
| VPS panel | `app/Services/Vps`, `app/Livewire/Portal/VpsPanel.php` |
| Backups | `app/Services/Backups` |
| Chat | `app/Services/Chat`, `app/Livewire/Support` |
| Email templates and alerts | `app/Services/Notifications` |
| Storefront | `app/Http/Controllers/Storefront`, `resources/views/storefront` |
| Help center | `docs/kb`, `app/Services/Docs` |

## Queues and schedule

Customer emails and alerts use the `high` queue. Scheduled: `vps:check-delivery-sla` hourly, `vps:collect-stats` every minute.

## Extending

- **New email:** add it to `EmailTemplates::catalog()`; it becomes editable at once.
- **New alert event:** add a constant and label in `StaffEvents` and call `StaffEvents::notify(...)`.
- **New permission:** add it to `config/stx-permissions.php` and check `RbacService::can()`.
- **New product category:** add it to `config/storefront.php` and seed products with that category.

## API

Customer-facing endpoints are described in `docs/openapi-commerce.yaml`. A test checks the file matches the real routes.

## Not built

Renewal invoices and automatic suspension, status alert subscriptions, app volume backups for customers, restore to a new database, and a template manifest system.
