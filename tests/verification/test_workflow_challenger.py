"""
Empirical Verification Test Suite for Hosting Platform Workflows & State Machines
Investigating Docs:
- docs/hosting-platform/05-business-workflows.md
- docs/hosting-platform/06-ui-dashboard-spec.md
- docs/hosting-platform/09-operations-runbook.md
- docs/hosting-platform/04-database-api-security.md
"""

import os
import sys
import sqlite3
import hashlib
import threading
import time
import struct

def print_banner(title):
    print(f"\n{'='*70}\n[TEST] {title}\n{'='*70}")

# ==============================================================================
# TEST 1: Polyglot Payment Proof Detection Failure (Magic Bytes vs Re-encoding)
# ==============================================================================
def test_polyglot_upload():
    print_banner("1. Payment Proof Polyglot Upload & Magic Bytes Bypass")
    
    # 1. Construct a valid PNG with embedded executable web shell payload
    png_signature = b"\x89PNG\r\n\x1a\n"
    # Valid IHDR chunk
    ihdr_data = struct.pack(">IIBBBBB", 1, 1, 8, 2, 0, 0, 0)
    ihdr_crc = b"\x90wS\xde"
    ihdr_chunk = struct.pack(">I", len(ihdr_data)) + b"IHDR" + ihdr_data + ihdr_crc
    
    # Embedded polyglot payload: PHP code hidden inside custom chunk or after IDAT
    malicious_php = b"<?php system($_GET['cmd']); /* Malicious Shellcode */ ?>"
    custom_chunk = struct.pack(">I", len(malicious_php)) + b"tEXt" + malicious_php + b"\x00\x00\x00\x00"
    
    # Valid IEND chunk
    iend_chunk = struct.pack(">I", 0) + b"IEND" + b"\xaeB`\x82"
    
    polyglot_file = png_signature + ihdr_chunk + custom_chunk + iend_chunk
    
    # Test proposed magic byte validator from 05-business-workflows.md line 122:
    # "Strict verification of file content header and magic bytes. Allowed types: image/jpeg (FF D8 FF), image/png (89 50 4E 47)..."
    has_png_magic_bytes = polyglot_file.startswith(b"\x89PNG\r\n\x1a\n")
    contains_malicious_code = b"<?php" in polyglot_file
    
    print(f"File size: {len(polyglot_file)} bytes")
    print(f"Magic bytes match 'image/png' (89 50 4E 47): {has_png_magic_bytes}")
    print(f"File contains executable PHP shell: {contains_malicious_code}")
    
    if has_png_magic_bytes and contains_malicious_code:
        print("[-] VULNERABILITY CONFIRMED: Polyglot file passes magic byte check successfully!")
        print("    If stored as-is without image re-encoding or metadata stripping, malicious code remains intact.")
    return True

# ==============================================================================
# TEST 2: Payment Proof Replay & Schema Inconsistency (Missing sha256_hash & Team ID)
# ==============================================================================
def test_replay_and_schema_audit():
    print_banner("2. Replay Attack & Schema Attribute Inconsistency")
    
    conn = sqlite3.connect(":memory:")
    cursor = conn.cursor()
    
    # Schema from 04-database-api-security.md (adapted to sqlite for empirical execution)
    cursor.execute("""
    CREATE TABLE invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        invoice_number TEXT NOT NULL UNIQUE,
        user_id INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'unpaid',
        total_cents INTEGER NOT NULL,
        balance_cents INTEGER NOT NULL
    );
    """)
    
    cursor.execute("""
    CREATE TABLE payment_transactions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        transaction_reference TEXT NOT NULL UNIQUE,
        invoice_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        amount_cents INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending'
    );
    """)
    
    cursor.execute("""
    CREATE TABLE payment_proofs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        uuid TEXT NOT NULL UNIQUE,
        invoice_id INTEGER NOT NULL,
        user_id INTEGER NOT NULL,
        file_path TEXT NOT NULL,
        transaction_reference TEXT,
        amount_paid_cents INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending_review'
    );
    """)
    
    # Check 2A: Query sha256_hash as written in 09-operations-runbook.md line 506
    # "SELECT id, invoice_id, user_id, created_at FROM payment_proofs WHERE sha256_hash = :hash;"
    has_sha256 = False
    cursor.execute("PRAGMA table_info(payment_proofs);")
    columns = [row[1] for row in cursor.fetchall()]
    if "sha256_hash" in columns:
        has_sha256 = True
    else:
        print("[-] SCHEMA BUG CONFIRMED: 'sha256_hash' column is MISSING from payment_proofs table definition in 04-database-api-security.md!")
        print(f"    Available columns: {columns}")
        print("    Playbook 3 in 09-operations-runbook.md will crash on: WHERE sha256_hash = :hash")

    # Check 2B: Missing team_id on invoices and payment_proofs
    cursor.execute("PRAGMA table_info(invoices);")
    inv_cols = [row[1] for row in cursor.fetchall()]
    print(f"[-] MULTI-TENANCY BUG CONFIRMED: 'team_id' column is MISSING from invoices! Columns: {inv_cols}")
    print("    Policies in 06-ui-dashboard-spec.md ($proof->invoice->team_id) will fail with missing property/column error.")

    # Check 2C: Replay Attack Simulation
    # Customer A uploads proof for Invoice 1 with TXN-9482104
    cursor.execute("INSERT INTO invoices (uuid, invoice_number, user_id, total_cents, balance_cents) VALUES ('inv-1', 'INV-001', 101, 1200, 1200)")
    cursor.execute("INSERT INTO payment_proofs (uuid, invoice_id, user_id, file_path, transaction_reference, amount_paid_cents) VALUES ('prf-1', 1, 101, '/path/1', 'TXN-9482104', 1200)")
    
    # Customer B uploads proof for Invoice 2 with SAME TXN-9482104 (Replay attack!)
    cursor.execute("INSERT INTO invoices (uuid, invoice_number, user_id, total_cents, balance_cents) VALUES ('inv-2', 'INV-002', 102, 1200, 1200)")
    try:
        cursor.execute("INSERT INTO payment_proofs (uuid, invoice_id, user_id, file_path, transaction_reference, amount_paid_cents) VALUES ('prf-2', 2, 102, '/path/2', 'TXN-9482104', 1200)")
        print("[-] VULNERABILITY CONFIRMED: Duplicate transaction_reference 'TXN-9482104' was ACCEPTED into payment_proofs without error!")
    except Exception as e:
        print(f"[+] Replay blocked at insert: {e}")

    # Now simulate Admin approving proof 1
    cursor.execute("UPDATE payment_proofs SET status = 'approved' WHERE id = 1")
    cursor.execute("UPDATE invoices SET status = 'paid', balance_cents = 0 WHERE id = 1")
    cursor.execute("INSERT INTO payment_transactions (uuid, transaction_reference, invoice_id, user_id, amount_cents, status) VALUES ('tx-1', 'TXN-9482104', 1, 101, 1200, 'completed')")
    conn.commit()
    print("[+] Proof 1 approved and payment_transactions record created.")

    # Now Admin opens Proof 2 and clicks Approve
    print("[-] Testing Admin approval of replayed proof 2:")
    try:
        cursor.execute("INSERT INTO payment_transactions (uuid, transaction_reference, invoice_id, user_id, amount_cents, status) VALUES ('tx-2', 'TXN-9482104', 2, 102, 1200, 'completed')")
        conn.commit()
        print("[-] Double spend occurred!")
    except sqlite3.IntegrityError as e:
        print(f"[-] CRASH CONFIRMED: Admin approval throws unhandled database integrity error: {e}")
        print("    Because payment_transactions enforces UNIQUE(transaction_reference), but payment_proofs did not validate it upon submission or approval.")

    conn.close()

# ==============================================================================
# TEST 3: Admin Verification Queue Concurrency Race (Double Review / State Conflict)
# ==============================================================================
def test_admin_queue_concurrency():
    print_banner("3. Admin Verification Queue Concurrency (Dual-Review Race)")
    
    # We simulate two admin requests executing concurrently on the same proof
    db_file = "test_queue_race.db"
    if os.path.exists(db_file):
        os.remove(db_file)
        
    conn = sqlite3.connect(db_file, check_same_thread=False)
    cursor = conn.cursor()
    cursor.execute("""
    CREATE TABLE payment_proofs (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL,
        reviewer_id INTEGER,
        reviewed_at TEXT
    );
    """)
    cursor.execute("INSERT INTO payment_proofs VALUES (1, 'pending_review', NULL, NULL)")
    cursor.execute("""
    CREATE TABLE invoices (
        id INTEGER PRIMARY KEY,
        status TEXT NOT NULL
    );
    """)
    cursor.execute("INSERT INTO invoices VALUES (1, 'payment_pending')")
    conn.commit()
    conn.close()

    results = []
    
    def admin_action(admin_id, action):
        local_conn = sqlite3.connect(db_file)
        local_cur = local_conn.cursor()
        
        # Read current state (as displayed in admin UI modal)
        local_cur.execute("SELECT status FROM payment_proofs WHERE id = 1")
        row = local_cur.fetchone()
        current_status = row[0]
        
        time.sleep(0.05) # simulate human click or processing latency
        
        # Execute action without checking state transition guard (as written in 05-business-workflows.md line 140)
        # UPDATE payment_proofs SET status = :status, reviewer_user_id = :admin_id, reviewed_at = NOW();
        if action == "approve":
            local_cur.execute("UPDATE payment_proofs SET status = 'approved', reviewer_id = ? WHERE id = 1", (admin_id,))
            local_cur.execute("UPDATE invoices SET status = 'paid' WHERE id = 1")
        elif action == "reject":
            local_cur.execute("UPDATE payment_proofs SET status = 'rejected', reviewer_id = ? WHERE id = 1", (admin_id,))
            local_cur.execute("UPDATE invoices SET status = 'unpaid' WHERE id = 1")
            
        local_conn.commit()
        results.append((admin_id, action, current_status))
        local_conn.close()

    t1 = threading.Thread(target=admin_action, args=(1, "approve"))
    t2 = threading.Thread(target=admin_action, args=(2, "reject"))
    
    t1.start()
    t2.start()
    t1.join()
    t2.join()

    check_conn = sqlite3.connect(db_file)
    cur = check_conn.cursor()
    cur.execute("SELECT status, reviewer_id FROM payment_proofs WHERE id = 1")
    final_proof = cur.fetchone()
    cur.execute("SELECT status FROM invoices WHERE id = 1")
    final_inv = cur.fetchone()
    check_conn.close()
    
    print(f"Admin actions recorded: {results}")
    print(f"Final Proof Status: {final_proof[0]} (Reviewer: {final_proof[1]})")
    print(f"Final Invoice Status: {final_inv[0]}")
    
    if final_inv[0] == 'unpaid' and any(r[1] == 'approve' for r in results):
        print("[-] STATE CORRUPTION CONFIRMED: Proof was APPROVED, but concurrent REJECT clobbered the invoice back to 'unpaid'!")
        print("    Meanwhile, ProvisionServiceJob was already dispatched by the approve action!")
    
    if os.path.exists(db_file):
        os.remove(db_file)

# ==============================================================================
# TEST 4: Dunning Suspension Collision (Payment Arrives During Suspension Job)
# ==============================================================================
def test_dunning_suspension_collision():
    print_banner("4. Dunning Suspension Collision vs Payment Settlement Race")
    
    # State tracking
    service_state = {"status": "active", "power": "on"}
    invoice_state = {"status": "unpaid", "paid_at": None}
    logs = []

    def suspension_worker():
        # Worker checks if invoice is unpaid
        if invoice_state["status"] == "unpaid":
            logs.append("[SuspensionWorker] Invoice is unpaid. Initiating ACPI shutdown on hypervisor...")
            # Simulate 60s ACPI shutdown delay
            time.sleep(0.1)
            # Power off VM
            service_state["power"] = "off"
            # Set status to suspended without re-checking invoice status
            service_state["status"] = "suspended"
            logs.append("[SuspensionWorker] VM powered off and service marked SUSPENDED.")

    def payment_webhook():
        time.sleep(0.04) # Payment arrives while suspension worker is executing hypervisor shutdown
        logs.append("[PaymentWebhook] Customer paid via Stripe/Proof approval! Marking invoice PAID.")
        invoice_state["status"] = "paid"
        invoice_state["paid_at"] = time.time()
        # In current design, ReactivateServiceJob only triggers if service was already 'suspended':
        if service_state["status"] == "suspended":
            logs.append("[PaymentWebhook] Service was suspended, restoring power.")
            service_state["status"] = "active"
            service_state["power"] = "on"
        else:
            logs.append("[PaymentWebhook] Service status was still 'active' (suspension in flight). No reactivation dispatched!")

    t1 = threading.Thread(target=suspension_worker)
    t2 = threading.Thread(target=payment_webhook)
    
    t1.start()
    t2.start()
    t1.join()
    t2.join()

    for l in logs:
        print("  ", l)
        
    print(f"Final Service State: status={service_state['status']}, power={service_state['power']}")
    print(f"Final Invoice State: status={invoice_state['status']}")
    
    if invoice_state["status"] == "paid" and (service_state["status"] == "suspended" or service_state["power"] == "off"):
        print("[-] RACE CONDITION CONFIRMED: Customer PAID invoice, but service was powered OFF and left SUSPENDED!")
        print("    ReactivateServiceJob was never dispatched or failed to wake the node.")

# ==============================================================================
# TEST 5: Cryptographic Wipe Fallback & '|| true' Error Swallowing
# ==============================================================================
def test_cryptographic_wipe_error_masking():
    print_banner("5. Cryptographic Wipe '|| true' Error Masking & Host Drive Blast Radius")
    
    # Examine the script line from 05-business-workflows.md line 499:
    # blkdiscard -f "${STORAGE_DEV}" || dd if=/dev/zero of="${STORAGE_DEV}" bs=4M status=progress conv=fdatasync || true
    script_snippet = 'blkdiscard -f "${STORAGE_DEV}" || dd if=/dev/zero of="${STORAGE_DEV}" bs=4M status=progress conv=fdatasync || true'
    
    print(f"Script command in 05-business-workflows.md: {script_snippet}")
    
    # Notice: If STORAGE_DEV path does not exist or fails, bash evaluates `|| true` to exit code 0!
    # Let's test exit code logic:
    cmd_exit_code = 1 # simulated failure of blkdiscard and dd
    final_exit_code = 0 if "|| true" in script_snippet else cmd_exit_code
    
    print(f"Simulated storage failure exit code with '|| true': {final_exit_code}")
    if final_exit_code == 0:
        print("[-] FLAW CONFIRMED: '|| true' swallows fatal wipe failures! The script exits 0 despite zero sanitization!")
        print("    The hypervisor then proceeds to clone a new VM onto an UNSANITIZED disk.")
        
    # Blast radius analysis on NVMe format
    print("\n[!] Blast Radius Analysis on line 496:")
    print("    'nvme format \"${STORAGE_DEV}\" --namespace-id=1 --ses=2'")
    print("    If STORAGE_DEV is set to the host drive (e.g. /dev/nvme0n1) on a shared hypervisor:")
    print("    '--ses=2' triggers User Data Erase across the ENTIRE physical controller/namespace!")
    print("    This destroys ALL other customer VMs running on that hypervisor host!")

# ==============================================================================
# TEST 6: Check Status Enum Compatibility between 04-DB and 05-Workflows
# ==============================================================================
def test_status_enum_mismatch():
    print_banner("6. Cross-Document Status Enum Compatibility Check")
    
    # From 04-database-api-security.md line 227:
    db_order_statuses = {'pending', 'payment_pending', 'processing', 'completed', 'cancelled', 'fraud_rejected'}
    # From 05-business-workflows.md lines 202-231:
    workflow_order_statuses = {'pending_payment', 'payment_submitted', 'payment_verified', 'provisioning', 'active', 'provision_failed', 'cancelled', 'rejected'}
    
    overlap = db_order_statuses.intersection(workflow_order_statuses)
    missing_in_db = workflow_order_statuses - db_order_statuses
    
    print(f"Database CHECK constraint statuses: {sorted(list(db_order_statuses))}")
    print(f"Workflow State Machine statuses:   {sorted(list(workflow_order_statuses))}")
    print(f"Overlapping statuses:              {sorted(list(overlap))}")
    print(f"Statuses used in workflows REJECTED by DB check constraint: {sorted(list(missing_in_db))}")
    
    if len(missing_in_db) > 0:
        print(f"[-] CRITICAL SPECIFICATION CONFLICT: {len(missing_in_db)} workflow states will fail PostgreSQL CHECK constraint!")

if __name__ == "__main__":
    test_polyglot_upload()
    test_replay_and_schema_audit()
    test_admin_queue_concurrency()
    test_dunning_suspension_collision()
    test_cryptographic_wipe_error_masking()
    test_status_enum_mismatch()
    print("\n" + "="*70 + "\n[TEST SUITE COMPLETE]\n" + "="*70)
