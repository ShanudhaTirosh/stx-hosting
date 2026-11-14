# PayVerify Production Go-Live Checklist

This checklist must be executed and verified before enabling automated financial settlement in production.

---

## 1. Server & Cryptographic Secrets Setup

- [ ] **Dedicated Public Server**: Ubuntu 22.04 LTS / Debian 12 with a public static IP, Docker 24+, and Docker Compose v2.
- [ ] **DNS & Reverse Proxy**:
  - Configure `PUBLIC_HOST` (e.g. `pay.example.com`) pointing to the server IP.
  - Configure `PHONE_API_HOST` (e.g. `phones.pay.example.com`) for Android device traffic.
  - Ports 80 and 443 open; all other inbound ports blocked by firewall (UFW).
- [ ] **Secrets Generation & Offline Backup**:
  ```bash
  # 1. 32-byte JWT Secret
  openssl rand -hex 32
  # 2. Fernet Symmetric Encryption Key (KEEP BACKED UP OFFLINE)
  python3 -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
  # 3. External API Secret
  openssl rand -hex 24
  ```
  > [!IMPORTANT]
  > Store `PV_DATA_KEY` and `PV_JWT_SECRET` in a secure password vault outside the server. If `PV_DATA_KEY` is lost, encrypted proof slips and electronic deposit advice PDFs cannot be decrypted.

---

## 2. Bank Integration & Ingestion Vectors

### Vector 1: Commercial Bank WhatsApp Banking Bot
- [ ] Ensure the business phone number with Commercial Bank WhatsApp Banking registered is accessible.
- [ ] In `deploy/.env`, configure `PV_COMBANK_NIC` (merchant national identity card number), `PV_COMBANK_WHATSAPP_NUMBER` (`94771234567`), and `PV_ADMIN_WHATSAPP_NUMBERS` (comma-separated admin numbers authorized to chat with bot).
- [ ] Start the stack: `docker compose -f deploy/docker-compose.prod.yml up -d`.
- [ ] In the Web Admin dashboard (`/admin/whatsapp-sync`), link the bot:
  - Enter the phone number and click **Get Pairing Code**, then enter the 8-digit code in WhatsApp on your phone under **Linked Devices > Link a Device > Link with phone number instead**.
  - Or scan the live multi-device QR code.
- [ ] Verify connection state transitions to `CONNECTED` and trigger a test statement sync.
- [ ] Note: The bot utilizes a 10-minute statement cache and a 60-second in-memory buffered OTP queue to eliminate race conditions between phone SMS delivery and ComBank prompts.

### Vector 2: Android SMS & OTP Interceptor
- [ ] Install the compiled `payverify-android.apk` onto the smartphone with the registered ComBank SIM card.
- [ ] In the Web Admin dashboard (`/admin/devices`), click **Create Enrollment Code**.
- [ ] In the Android app, enter the server URL and enrollment code, then tap **Connect & Enroll**.
- [ ] Confirm the device generates an ECDSA P-256 key pair, transmits a signed heartbeat, and shows status `ACTIVE`.
- [ ] Confirm sender allowlists accept both `COMBANK` and `2662265` (Windows Phone Link numeric shortcode).
- [ ] Enable Biometric / PIN app lock in the Android settings to protect local financial alert history.
- [ ] Set the Android battery optimization to **Unrestricted** and lock the app in the recent apps tray.

### Vector 3: Multimodal Visual AI Extraction
- [ ] Create a free API key at [Google AI Studio](https://aistudio.google.com/).
- [ ] Set `PV_EXTRACTION_ENABLED=true`, `PV_AI_PROVIDER=gemini`, `PV_GEMINI_API_KEY=AIzaSy...`, and `PV_GEMINI_MODEL=gemini-1.5-flash` in `deploy/.env`.
- [ ] If running on an offline VPS without external internet, install Ollama with `llama3.2-vision` and set `PV_AI_PROVIDER=ollama`.

### Vector 4: Bank Statement CSV / XLSX Importer
- [ ] Export a real ComBank CSV statement from online banking.
- [ ] Upload to `/admin/bank` and verify column recognition. If custom column names are present, configure `PV_STATEMENT_PROFILE` in `.env`.

### Vector 5: Merchant Account Settings & Credentials
- [ ] In the Web Admin dashboard, navigate to **Settings > My Own Details** (`/admin/settings`).
- [ ] Enter full name, bank name (`Commercial Bank of Ceylon PLC`), branch name, bank account number, national identity card (NIC) / passport, and monitored senders (`COMBANK, 2662265`).
- [ ] Save changes and verify account number and NIC are encrypted at rest with Fernet.
- [ ] Test the **Reveal Details** modal by entering your staff password; verify values unmask for 30 seconds and generate an audit log entry in `AuditLog`.

---

## 3. Financial Settlement Dry Run (Verification Testing)

- [ ] **Test Case 1: CEFTS Bank Transfer with Payment Code**
  - Create a test order via `POST /api/v1/external/orders`. Note the generated `payment_code` (e.g. `8F3A2B1C`).
  - Transfer a small test amount (e.g. Rs. 100.00) including the code in remarks.
  - Upload receipt slip. Trigger bank sync or import statement.
  - Verify claim automatically transitions to `verified` with `basis="bank_match"`.
- [ ] **Test Case 2: ATM / CRM Cash Machine Deposit**
  - Deposit cash at a ComBank CRM machine.
  - Verify incoming SMS contains a `https://vas.combank.net/rec/<token>` link.
  - Confirm PayVerify downloads the digital advice PDF and parses the STAN.
- [ ] **Test Case 3: Duplicate Slip Prevention**
  - Attempt to upload the identical receipt slip on a second order.
  - Confirm the API rejects with `409 Conflict (DUPLICATE_RECEIPT_DETECTED)`.
- [ ] **Test Case 4: Amount Mismatch Handling**
  - Upload a slip with an amount different from the order.
  - Confirm the claim transitions to `manual_review` with discrepancy alerts displayed in the workbench.
- [ ] **Test Case 5: WhatsApp Bot Admin Command (`last 3`)**
  - From a phone configured in `PV_ADMIN_WHATSAPP_NUMBERS`, send `last 3` to the bot.
  - Verify the bot replies with formatted transaction cards showing date, branch, amount, and balance.
- [ ] **Test Case 6: Shortcode `2662265` & Windows Phone Link Ingestion**
  - Verify incoming SMS originating from `2662265` is successfully accepted by the Android prefilter, stored locally, and dispatched to `/api/v1/devices/otp-relay`.

---

## 4. Backups, Retention & Legal Compliance

- [ ] **Automated Backups**: Configure nightly encrypted backups of PostgreSQL volume (`pgdata`) and storage volume (`proofs`). Test restoring to a staging environment.
- [ ] **PDPA Data Retention**: Schedule daily data retention execution:
  ```bash
  docker compose run --rm api python -m app.retention --apply
  ```
- [ ] **Legal Review**: Ensure terms of service and customer upload notices comply with Sri Lanka's Personal Data Protection Act (PDPA).
