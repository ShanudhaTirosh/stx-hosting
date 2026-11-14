# PayVerify Android Client (Kotlin, Jetpack Compose)

The **`payverify-android`** application is a native Android background service designed to run on the merchant's dedicated business smartphone. It forwards incoming Commercial Bank SMS transaction alerts (and optionally bank app notifications) to the PayVerify server as supporting evidence and relays ephemeral ComBank OTPs to the WhatsApp Banking bot.

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

### 3. Strict Privacy by Design
- Requests strictly `android.permission.RECEIVE_SMS` (incoming messages only).
- Does **NOT** request `android.permission.READ_SMS`: the existing inbox, personal messages, and photos cannot be read.
- No accessibility services and no screen capture APIs.
- Strict sender allowlisting: only messages originating from `COMBANK` or verified bank headers are processed. Non-bank messages are dropped immediately on the device.

---

## User Interface & Features

- **Jetpack Compose UI**: Clean, high-contrast monochrome design using standard Android Material Vector Icons.
- **One-Click Enrollment**: Pair the device using a one-time enrollment code generated from the PayVerify Web Admin dashboard (`/admin/devices`).
- **Real-Time Connectivity Indicators**: Live connection status, public key fingerprint, last heartbeat time, and message relay counter.
- **Heartbeat & Battery Optimization**: Periodic background heartbeat worker (`WorkManager`) to ensure continuous relay availability.

---

## Setup & Deployment Instructions

### 1. Building the APK
1. Open `payverify-android` in **Android Studio** (Ladybug or newer).
2. Allow Gradle to sync dependencies.
3. Build the APK via **Build > Build Bundle(s) / APK(s) > Build APK(s)** or run directly on your phone:
   ```bash
   ./gradlew assembleRelease
   ```
4. Sideload the APK onto the business phone using `adb install` or direct USB transfer.

### 2. Enrollment & Device Pairing
1. Open the **PayVerify Web Admin Dashboard > Phones / Devices**.
2. Click **Create Enrollment Code** (generates an 8-character single-use code).
3. In the Android app, enter:
   - Server HTTPS URL (e.g. `https://pay.example.com`)
   - Enrollment Code
   - Friendly Device Name (e.g. `Samsung A15 - ComBank SIM`)
4. Tap **Connect & Enroll**.
5. Grant the **Receive SMS** permission when prompted.
6. Set the app's Battery usage setting to **Unrestricted** to prevent Android OS background suspension.
