---
title: Alerts and emails
summary: Discord channels per event, editable emails and mail delivery.
order: 6
---
## Discord channels

**Alert Channels** (owner) maps an event to one or more Discord webhooks: new order, new payment proof, proof approved or rejected, VPS delivery reminders, new support conversation, customer message, node or service issue, backup failed. Use **Test** after adding. The webhook URL is stored encrypted and shown masked. Repeats of the same alert are sent once, and one event type is capped at 30 per minute.

## Public status alerts & subscriptions

Visitors and customers can subscribe to infrastructure incident and maintenance alerts from the public status page (`/status`). Subscriptions use double opt-in email verification. When staff create an incident, post a progress update, or mark an incident resolved in the **Status Studio**, alert notifications are automatically dispatched to all active verified subscribers with one-click unsubscribe links.

## Emails

**Emails** (owner) lets you edit the wording of every customer email and the staff proof email, with a live preview and a test send. Use only the variables listed beside the editor. Reset returns the built-in text. Mail server settings stay in Settings, Email.

## If something is not arriving

Run `php artisan stx:security-check` and make sure a queue worker runs and listens to the `high` queue. Check the mail settings and the failed jobs list.
