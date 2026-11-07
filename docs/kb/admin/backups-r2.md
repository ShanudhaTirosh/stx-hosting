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

## Limits

Only database backups have the customer page. App volume backups use the app's own Backups tab. Restore is in place only.
