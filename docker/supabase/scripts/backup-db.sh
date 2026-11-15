#!/usr/bin/env bash
# docker/supabase/scripts/backup-db.sh
# Automated PostgreSQL backup script for self-hosted Supabase database

set -eo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/supabase}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/supabase_db_${TIMESTAMP}.dump"
RETENTION_DAYS="${RETENTION_DAYS:-7}"

mkdir -p "${BACKUP_DIR}"

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Starting Supabase PostgreSQL backup..."

# Execute pg_dump using custom compressed format (-Fc)
docker exec supabase-db pg_dump -U postgres -d postgres -Fc > "${BACKUP_FILE}"

if [ -s "${BACKUP_FILE}" ]; then
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Backup successful: ${BACKUP_FILE} ($(du -h "${BACKUP_FILE}" | cut -f1))"
else
    echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Backup failed or produced empty file!" >&2
    exit 1
fi

# Retention policy: remove backups older than RETENTION_DAYS
echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Pruning backups older than ${RETENTION_DAYS} days..."
find "${BACKUP_DIR}" -name "supabase_db_*.dump" -type f -mtime +"${RETENTION_DAYS}" -delete

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Backup and rotation completed successfully."
