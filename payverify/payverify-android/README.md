# PayVerify Android Client (Kotlin, Jetpack Compose)

The **`payverify-android`** application is a native Android application built with Kotlin and Jetpack Compose. It functions in two modes:
1. **Device Messages Viewer**: A reliable, secure bank transaction alert viewer for dedicated relay smartphones, featuring smart transaction card parsing, full-text search, filters, and offline message queueing.
2. **Admin Console**: A mobile management console for reviewing pending payment claims, performing one-tap manual approvals, checking bot health, and configuring custom monitored shortcodes.

---

## Architectural & Security Invariants

### 1. Zero-Leak OTP Guarantee
- Commercial Bank SMS OTP codes (received during WhatsApp statement requests) are **never written to SQLite, never saved to flash memory, and never logged**.
- The OTP is dispatched in-memory over TLS directly to the server's ephemeral relay buffer (`POST /api/v1/devices/otp-relay`), and the local string is immediately wiped.

### 2. Hardware-Backed Cryptographic Authentication (ECDSA P-256)
- Upon device enrollment, a cryptographic key pair is generated inside the **Android Keystore (hardware-backed TEE / StrongBox)**.
- The private key never leaves the secure element.
- Every HTTP request dispatched to the PayVerify API is signed using **ECDSA with SHA-256** over `(method, path, timestamp, body_sha256)`.
- The PayVerify server verifies the signature against the enrolled public key, enforcing a strict 5-minute clock skew tolerance to prevent replay attacks.

### 3. Biometric & Device Security
- **Biometric / PIN App Lock**: Integrated `androidx.biometric:biometric` prompt shields banking data on device launch or resume.
- **Local Message History Retention**: Message bodies are retained locally in SQLite schema v2 for the on-device transaction viewer without being wiped on upload, while maintaining the zero-leak guarantee for OTPs.

### 4. Strict Privacy & Sender Allowlisting
- Requests strictly `android.permission.RECEIVE_SMS` (incoming messages only), `android.permission.USE_BIOMETRIC`, and `android.permission.POST_NOTIFICATIONS`.
- Does **NOT** request `android.permission.READ_SMS`: the existing inbox, personal messages, and photos cannot be read.
- Strict sender allowlisting: processes messages originating from `COMBANK` or `2662265` (the keypad numeric equivalent of `C-O-M-B-A-N-K` used by Windows Phone Link and certain carriers), as well as admin-configured custom numbers. Non-bank messages are dropped immediately on the device.

---

## Dual-Mode Architecture & Features

### Mode 1: Device Messages Viewer
- **Smart Bank Parser (`BankParser.kt`)**: Automatically extracts:
  - Amount in cents and formatted LKR (e.g. `Rs. 4,500.00`).
  - Transaction Direction: Credit (`Cr`) vs Debit (`Dr`).
  - Available Account Balance.
  - Account Number tail (e.g. `*7213`).
  - Reference / STAN (e.g. `032699400003`).
  - CRM / ATM Branch name (e.g. `BATAPOLA-CRM1 BR`).
  - Category tag (`Salary`, `Transfer`, `Deposit`, `Card Payment`, `Withdrawal`, `Bill Payment`).
  - ComBank Digital Receipt URL (`https://vas.combank.net/rec/...`).
- **Interactive Filtering & Search**:
  - Filter by Bank sender, direction, category, date, or amount range.
  - Full-text search across reference codes, branch names, and message text.
  - Unread vs read indicator pills and pull-to-refresh synchronization.
- **Message Detail Bottom Sheet**:
  - Tap any card to open a full bottom sheet showing extracted metadata chips, digital receipt link buttons, and raw SMS text.

### Mode 2: Mobile Admin Console
- **Quick Operations**:
  - Review queue showing pending payment claims needing staff verification.
  - One-tap manual verification action with reason justification.
  - Real-time bot status indicator (Connected / Idle / Syncing).
  - Custom monitored senders configuration (add/remove shortcodes like `2662265`).

---

## Setup & Deployment Instructions

### 1. Building the APK
1. Open `payverify-android` in **Android Studio** (Ladybug or newer).
2. Ensure JDK 21+ and Android SDK 34+ are configured.
3. Build the APK or compile Kotlin sources:
   ```bash
   ./gradlew assembleDebug
   ```
   Or verify Kotlin compilation:
   ```bash
   ./gradlew compileDebugKotlin
   ```
4. Sideload the APK onto the business phone using `adb install` or direct transfer.

### 2. Enrollment & Device Pairing
1. Open the **PayVerify Web Admin Dashboard > Phones / Devices**.
2. Click **Create Enrollment Code** (generates an 8-character single-use code).
3. In the Android app, enter:
   - Server HTTPS URL (e.g. `https://pay.example.com`)
   - Enrollment Code
   - Friendly Device Name (e.g. `Samsung A15 - ComBank SIM`)
4. Tap **Connect & Enroll**.
5. Grant the **Receive SMS** and **Notification** permissions when prompted.
6. Set the app's Battery usage setting to **Unrestricted** to prevent Android OS background suspension.
