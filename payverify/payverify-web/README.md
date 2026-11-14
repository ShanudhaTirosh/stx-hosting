# PayVerify Web Admin Dashboard (Next.js 16, React 19)

The **`payverify-web`** frontend is an enterprise web application built with **Next.js 16 (App Router)** and **React 19**, styled in a strict **monochrome glassmorphism** aesthetic (pitch black backgrounds, pure white typography, subtle borders, and translucent glass cards).

---

## Architectural & Security Design

1. **Backend-For-Frontend (BFF) Pattern**:
   The browser never communicates directly with the Python API. All requests route through `/bff/api/*`, where server-side route handlers attach authentication tokens from secure, `httpOnly`, `SameSite=Strict` cookies. Tokens are never exposed to browser JavaScript, preventing token exfiltration via XSS.

2. **Cross-Site Request Forgery (CSRF) Protection**:
   State-changing requests require the custom `x-pv-csrf` header and verified origin matching.

3. **Multi-Factor Authentication (MFA)**:
   All staff accounts require Time-Based One-Time Password (TOTP) MFA configured during first login via `/mfa-setup`.

---

## Dashboard Routes & Features

### 1. Manual Review Workbench (`/admin/claims/[id]`)
- **Interactive Proof Viewer**:
  - Translucent glass pane with native zoom in, zoom out, rotate, reset, and full-screen controls.
  - Supports high-resolution bank slip images (JPEG, PNG) and multi-page PDFs.
- **Side-by-Side Comparison**:
  - High-contrast visual diff comparing customer claimed amounts and references against multimodal AI extracted metadata.
  - Instant discrepancy banners and forensic fraud indicators (duplicate perceptual dHash warnings, EXIF editor traces).
- **Bank Candidates Table**:
  - Displays matching bank ledger credits that fit within the payment window.
- **One-Click Settlement Actions**:
  - Approve claim via deterministic bank credit allocation (`bank_match`).
  - Force manual override with required staff justification (`admin_override`).
  - Reject claim or mark as fraudulent.

### 2. WhatsApp Banking Sync Panel (`/admin/whatsapp-sync`)
- **8-Digit Pairing Code Linking**:
  - Generate an official 8-digit WhatsApp pairing code (`POST /pair/code`) by entering the merchant phone number. Link the bot without needing to scan a QR code with a camera.
- **QR Code Pairing**:
  - Multi-device QR code stream for standard camera scanning.
- **Bot Diagnostics**:
  - Live conversational state (`IDLE`, `AWAITING_OTP`, `FETCHING_STATEMENT`), last sync timestamp, and on-demand sync trigger.

### 3. Integrations Hub (`/admin/integrations`)
- **API Credentials & Keys**:
  - Securely manage external API keys and inspect webhook endpoints.
- **Live Health & Subsystem Diagnostics**:
  - Real-time diagnostic panel displaying database query latency (ms), storage writability & Fernet encryption, WhatsApp bot status, active Android SMS relay counts, and AI vision model readiness.
- **Multi-Language Code Snippets**:
  - Copy-paste integration snippets in cURL, JavaScript/TypeScript, Python, and PHP.

### 4. Statement Importer & Ledger (`/admin/bank`)
- Upload ComBank statement CSVs or Excel spreadsheets (`.xlsx`).
- Review import logs, matched transactions, and allocate unmatched credits.

### 5. Enrolled Mobile Relays (`/admin/devices`)
- Generate single-use enrollment codes for Android SMS interceptors.
- Monitor active Android relays, ECDSA P-256 public keys, and last heartbeat timestamps.

---

## Development & Production Build

### 1. Configuration
Copy the sample environment file:
```bash
cp .env.example .env.local
```

Configure `API_URL` (where Next.js reaches the backend API on the internal network):
```bash
API_URL=http://localhost:8000
```

### 2. Local Development
```bash
npm install
npm run dev
```
Open [http://localhost:3000](http://localhost:3000) in your browser.

### 3. Production Build
```bash
npm run build
npm start
```
**Verification**: All 14 static and dynamic routes compile cleanly with zero TypeScript errors.
