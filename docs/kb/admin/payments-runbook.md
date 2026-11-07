---
title: Verifying payments
summary: Reviewing receipts, rejecting with a reason, and what approval does.
order: 2
---
## Daily routine

Open **Proof Reviews** (also announced by email and on Discord). Compare the receipt with the invoice: amount, currency, reference, date and the account it went to.

## Approve

Approval marks the invoice paid, records one transaction, and creates an **entitlement** for each item. That entitlement is what lets the customer deploy and sets their CPU and memory limits. Apps, bots, websites and databases are ready immediately. A VPS goes to the delivery queue (see *VPS delivery*). A proof can be decided only once.

## Reject

Always give a clear reason; the customer receives it in an email. The invoice returns to unpaid and they can upload again, up to five times.

## Watch for

- The same receipt used for another invoice is refused automatically.
- An amount that does not match, a cropped or edited image, or a receipt dated before the invoice.
- A second proof arriving while one is waiting is refused until the first is decided.

## Refunds and renewals

There is no refund or renewal flow in the platform. Refunds are handled by you outside it. When a customer's period ends, new deployments stop; they order the plan again. Running containers are not stopped automatically.
