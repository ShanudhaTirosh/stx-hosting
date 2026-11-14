# PayVerify ComBank WhatsApp Banking Bot

The **`payverify-whatsapp-bot`** is an automated daemon built with TypeScript and [`@whiskeysockets/baileys`](https://github.com/WhiskeySockets/Baileys) that synchronizes bank statements with the **Commercial Bank of Ceylon (ComBank)** official WhatsApp Banking service (`+94 77 123 4567`).

---

## Connection Method Analysis & Safety Recommendation

### Why Baileys Multi-Device Client?
- **WhatsApp Cloud API / Official Business API**: The official Meta Business API is strictly designed for inbound customer communications and outbound template messaging to consumers. **It cannot initiate conversations or interact with other WhatsApp Business accounts** (such as ComBank's automated service `+94 77 123 4567`).
- **Baileys Companion Client**: Connects as an authorized linked companion device to your primary merchant business phone number (identical to WhatsApp Web or Desktop). This allows native conversation handling with ComBank's chatbot.

### Account Safety & Anti-Ban Protections:
1. **Statement Caching (10-Minute TTL)**: Eliminates redundant bank conversation sequences if repeated verification requests occur within 10 minutes.
2. **Natural Jitter Delays**: Injects human-like randomized delays (1.2s – 2.5s) between menu selections to prevent automated bot heuristics flags.
3. **Reasonable Polling Intervals**: Defaults to 60-minute periodic polling (`SYNC_INTERVAL_MINUTES=60`) plus on-demand triggers from checkout orders or admin actions.
4. **Auto-Reconnect with Backoff**: Gracefully handles network blips and WhatsApp socket disconnects (`428` connection closed, `515` restart required) without rapid reconnect hammering.

---

## Architectural Workflow & Buffered OTP Relay

![WhatsApp Bot & OTP Relay Sequence](../docs/diagrams/whatsapp-otp-relay.svg)

### Relay Workflow (Universal Text Rendering)

```text
1. WhatsApp Bot sends greeting "hi" to ComBank WhatsApp (+94 77 123 4567)
   │
2. Navigates menu: Language Selection -> Accept T&C -> Account History (2)
   │
3. Enters Merchant NIC / Passport number (e.g. 199512345678)
   │
4. ComBank dispatches SMS with 6-digit OTP to merchant phone
   │
5. Android Relay intercepts SMS in-memory and posts to /api/v1/devices/otp-relay
   │
6. API forwards OTP to bot's 60-second in-memory buffer (otpBuffer)
   │ (Eliminates race condition: OTP buffered even if SMS arrives before bank prompt)
   │
7. Bot sends 6-digit OTP into WhatsApp session and wipes memory buffer immediately
   │
8. ComBank returns statement history table (Date, CRM Branch, LKR Amount)
   │
9. Bot parses rows via regex (supporting DD/MM/YYYY, YYYY-MM-DD, Cr/Dr) and caches result
   │
10. Bot submits transactions to /api/v1/bank-sync/whatsapp-history and exits cleanly
```

---

## Multi-Language & Error Handling

- **Multi-Language Detection**: Robust regex triggers match ComBank menu prompts in English, Sinhala, and Tamil.
- **Bank Error Interception**: Automatically detects bank error and maintenance replies (`"technical difficulties"`, `"invalid"`, `"try again"`, `"temporarily unavailable"`). The bot immediately aborts the active session with an error payload rather than timing out and hanging.
- **Robust Statement Parser (`src/parser.ts`)**:
  - Handles both `DD/MM/YYYY` and `YYYY-MM-DD` date layouts without day/year inversion errors.
  - Recognizes `Cr` (credit) and `Dr` (debit) directional indicators.
  - Extracts STAN/reference codes and ATM/CRM branch names (`BATAPOLA-CRM1 BR`).

---

## Authorized Admin WhatsApp Chat Commands

Authorized administrators can interact directly with the bot via WhatsApp messaging (configured via `PV_ADMIN_WHATSAPP_NUMBERS` in `.env`):

| Command | Action |
|---|---|
| `last 3` | Triggers a bank statement sync and replies with the last 3 ComBank transactions formatted as cards (Date, Branch, Amount, Balance). |
| `status` | Replies with bot connection state (`CONNECTED`/`CONNECTING`), uptime, active session, and last successful statement fetch time. |
| `refresh` | Clears the 10-minute statement cache and forces a fresh bank fetch. |
| `help` | Lists available administrative chat commands and syntax. |

Unauthorized senders receive no response to prevent reconnaissance.

---

## Pairing Methods

### 1. 8-Digit Pairing Code (Camera-Free)
Link the bot directly to your merchant WhatsApp account without scanning a QR code with a phone camera:
1. Open **Web Admin Dashboard > WhatsApp Sync**.
2. Enter your ComBank registered WhatsApp mobile number (e.g. `94771234567`).
3. Click **Get Pairing Code**.
4. In WhatsApp on your phone: go to **Linked Devices > Link a Device > Link with phone number instead**, and enter the 8-digit code.

### 2. Multi-Device QR Code
Alternatively, open the WhatsApp Sync screen to view and scan the live multi-device QR code.

---

## Daemon API Endpoints (Port 3100)

All state-changing endpoints are protected by `PV_BOT_SECRET` via header `X-Bot-Secret` or `Authorization: Bearer <token>`.

### 1. `GET /status`
Returns connection status, active conversational state (`IDLE`, `AWAITING_OTP`, `FETCHING_STATEMENT`), last sync timestamp, and cached statement preview.

### 2. `GET /qr`
Returns the raw multi-device pairing QR code string and rendered base64 data URL.

### 3. `POST /pairing-code`
Requests an 8-digit pairing code for camera-free phone linking.
- **Request Body**: `{ "phoneNumber": "94771234567" }`
- **Response**: `{ "success": true, "code": "ABCD-1234", "phoneNumber": "94771234567" }`

### 4. `POST /sync` or `POST /sync-on-demand`
Triggers a statement fetch conversation.
- **Request Body (Optional)**: `{ "force": true }` to bypass the 10-minute statement cache.

### 5. `POST /otp`
Receives an ephemeral OTP code from the PayVerify API to inject into an active ComBank session or hold in the 60s queue.
- **Request Body**: `{ "otp": "492817" }`
- **Response**: `{ "success": true, "message": "OTP received and injected or buffered" }`

### 6. `POST /command`
Programmatically executes an admin command (`"last 3"`, `"status"`, `"refresh"`).

---

## Environment Variables (`.env`)

```bash
PORT=3100
WHATSAPP_AUTH_DIR=/data/auth
PV_COMBANK_WHATSAPP_NUMBER=94771234567
PV_COMBANK_NIC=199512345678
PV_API_URL=http://localhost:8000
PV_BOT_SECRET=your_shared_bot_secret
SYNC_INTERVAL_MINUTES=60
PV_ADMIN_WHATSAPP_NUMBERS=94771234567,94719876543
```

---

## Testing & Quality Assurance

```bash
# Install dependencies
npm install

# Run ComBank statement regex parser tests
npx tsx --test src/parser.test.ts

# Type check TypeScript code
npx tsc --noEmit

# Start daemon
npm start
```
**Verification Outcome**: All 3 test suites pass with 0 failures; zero TypeScript type errors.
