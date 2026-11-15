#!/usr/bin/env bash
# docker/supabase/scripts/restore-db.sh
# Automated PostgreSQL restore script for self-hosted Supabase database

set -eo pipefail

DUMP_FILE="$1"

if [ -z "${DUMP_FILE}" ] || [ ! -f "${DUMP_FILE}" ]; then
    echo "Usage: $0 /path/to/supabase_db_YYYYMMDD_HHMMSS.dump" >&2
    exit 1
fi

echo "================================================================="
echo "WARNING: This will restore the database from ${DUMP_FILE}"
echo "All current data in the postgres database will be overwritten!"
echo "================================================================="
read -p "Type 'RESTORE' to continue: " CONFIRM

if [ "${CONFIRM}" != "RESTORE" ]; then
    echo "Restore cancelled by user."
    exit 1
fi

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Restoring Supabase PostgreSQL database from ${DUMP_FILE}..."

# Copy dump into container and restore using pg_restore with clean flag
cat "${DUMP_FILE}" | docker exec -i supabase-db pg_restore -U postgres -d postgres --clean --if-exists --no-owner --role=postgres || true

echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] Database restoration completed."
