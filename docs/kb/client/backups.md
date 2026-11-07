---
title: Database backups
summary: Turn backups on, keep the last few, download and restore safely.
order: 8
---
Backups cover PostgreSQL, MySQL, MariaDB, MongoDB and ClickHouse databases. They are stored for you in our cloud storage, separately from your server.

## Turn them on

Open **Backups**, choose **every day at 02:00** or **every Sunday at 03:00**, choose how many to keep (3, 7, 14 or 30), and press **Turn on**. **Back up now** takes one straight away (once every few minutes at most, and the database must be running).

## Space

Each team has a backup allowance, shown at the top of the page. When it is full, new backups are skipped until you free space or ask us for more.

## Download

Press **Download** next to a finished backup. The link works for ten minutes.

## Restore

1. Press **Restore** next to the backup, type `RESTORE`, and confirm.
2. We first take a **safety backup** of the data as it is now. If that fails, nothing is changed.
3. Then the restore starts and **replaces the current data** with the backup. The database may be unavailable for a few minutes.
4. If the result is not what you wanted, restore the safety backup taken just before.

The database must be running for a restore. Backups of app volumes are not on this page.
