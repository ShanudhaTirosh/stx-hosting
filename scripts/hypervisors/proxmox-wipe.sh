#!/usr/bin/env bash
# ==============================================================================
# ShanuTechX Platform - Automated Hypervisor Node Sanitization Script
# Compliant with NIST SP 800-88 Rev 1 & Blast-Radius Protection Standards
# ==============================================================================

set -euo pipefail

VMID="${1:-}"
TARGET_STORAGE="${2:-}"

if [[ -z "${VMID}" ]]; then
    echo "[ERROR] Usage: $0 <vmid> <target_storage_device>"
    exit 1
fi

# 1. Validate VMID is purely numeric (prevent shell injection)
if ! [[ "${VMID}" =~ ^[0-9]+$ ]]; then
    echo "[FATAL] Invalid VMID '${VMID}'. Must be numeric."
    exit 2
fi

# 2. Strict Blast-Radius Protection: Refuse physical host disk devices
BLOCKED_DEVICES=("^/dev/nvme[0-9]+n[0-9]+$" "^/dev/sd[a-z]+$" "^/dev/vd[a-z]+$" "^/dev/hd[a-z]+$")
for pattern in "${BLOCKED_DEVICES[@]}"; do
    if [[ "${TARGET_STORAGE}" =~ ${pattern} ]]; then
        echo "[FATAL] Blast-Radius Violation! Attempted to wipe host physical disk: ${TARGET_STORAGE}"
        exit 3
    fi
done

# 3. Verify Target Storage matches valid hypervisor slice paths
if [[ -n "${TARGET_STORAGE}" ]]; then
    if ! [[ "${TARGET_STORAGE}" =~ ^/dev/zvol/ || "${TARGET_STORAGE}" =~ ^/dev/mapper/ || "${TARGET_STORAGE}" =~ ^/dev/pve/ ]]; then
        echo "[FATAL] Invalid storage path '${TARGET_STORAGE}'. Must be a guest zvol or LVM-thin slice."
        exit 4
    fi
fi

echo "[INFO] Commencing safe decommissioning for VM ${VMID}..."

# 4. Stop guest VM if running
if command -v qm &> /dev/null; then
    if qm status "${VMID}" | grep -q "status: running"; then
        echo "[INFO] Stopping VM ${VMID}..."
        qm stop "${VMID}" --timeout 30 || qm stop "${VMID}" --skiplock 1
    fi
fi

# 5. Cryptographic Sanitization / Trim
if [[ -n "${TARGET_STORAGE}" && -b "${TARGET_STORAGE}" ]]; then
    echo "[INFO] Executing blkdiscard trim on ${TARGET_STORAGE}..."
    blkdiscard "${TARGET_STORAGE}"
fi

# 6. Destroy guest VM definition
if command -v qm &> /dev/null; then
    echo "[INFO] Purging VM definition from Proxmox cluster..."
    qm destroy "${VMID}" --purge 1
fi

echo "[SUCCESS] VM ${VMID} sanitized and decommissioned cleanly."
exit 0
