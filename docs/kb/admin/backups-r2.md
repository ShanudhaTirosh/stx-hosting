---
title: Backups on R2
summary: Setting up the bucket, choosing it, quotas and restoring for a customer.
order: 5
---
## Set up

1. In Cloudflare create an R2 bucket and an API token limited to it.
2. In the platform team open **Storages** and add it: endpoint `https://<account>.r2.cloudflarestorage.com`, region `auto`, bucket, key and secret.
3. Open **Backups** (staff), choose the storage and press **Use this storage**. It is tested; a failing connection is reported.

Customers never see the bucket or its keys. Each team's files sit under their own folder.

## Quotas

Each team gets `BACKUP_DEFAULT_QUOTA_GB`. The owner can set another value per team on the same page. When a team is full, runs are skipped and recorded, and you are alerted.

## Restoring for a customer

On **Backups** pick the backup and press **Restore**. A safety backup is taken first; if it fails nothing changes. A restore replaces the current data.

## Alerts

Backup failures, skipped runs and failed restores go to the **Backup failed** Discord channel.

## Volume backups & multi-target restore

Customers can manage both **Database Backups** and **Application Volume Backups** directly from their client portal (`/portal/backups`).

- **Volume backups**: Persistent app and service volumes can be scheduled (daily or weekly) or backed up on-demand to platform R2 cloud storage.
- **Cross-database restore**: When restoring a database backup, customers can choose either an in-place restore or restore directly into an alternate staging/target database of the same engine. A safety backup of the target database is taken first.
