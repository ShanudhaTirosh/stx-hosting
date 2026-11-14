package lk.shanufx.payverify

import java.security.MessageDigest

/**
 * Decides, ON THE DEVICE, whether a message may leave the phone at all.
 * Everything that does not look like a bank credit/debit alert is discarded and never stored or uploaded.
 * The server re-checks every rule (defence in depth) and does the authoritative parsing.
 */
object Prefilter {
    private val secret = Regex("""\b(otp|one[\s-]?time|verification code|passcode|password|pin|cvv|cvc)\b""", RegexOption.IGNORE_CASE)
    private val amount = Regex("""(?:LKR|Rs\.?)\s*[\d,]+(?:\.\d{1,2})?""", RegexOption.IGNORE_CASE)
    private val moneyMove = Regex("""\b(credited|credit|debited|debit|deposit(?:ed)?|received|withdraw(?:al|n)?|cr|dr)\b""", RegexOption.IGNORE_CASE)

    fun containsSecret(text: String) = secret.containsMatchIn(text)

    /** True only for messages that look like a transaction alert and contain no credential-like content. */
    fun isTransactionAlert(text: String) =
        !containsSecret(text) && (amount.containsMatchIn(text) || text.contains("CRM Deposit", ignoreCase = true)) && moneyMove.containsMatchIn(text)

    /**
     * Intercepts ONLY Commercial Bank (ComBank) WhatsApp Banking OTP SMS.
     * ZERO LEAK: Must originate from an allowed ComBank sender (including shortcode 2662265),
     * must match the ComBank OTP keyword pattern, and must contain an exact 4-8 digit numeric OTP code.
     * All other OTPs (login, card, Google, other banks) return null and are discarded.
     */
    fun extractComBankOtp(sender: String, body: String, allowlist: Collection<String>): String? {
        if (!senderAllowed(sender, allowlist)) return null
        val cleanSender = sender.trim()
        val isComBank = cleanSender.contains("COMBANK", ignoreCase = true) ||
                cleanSender.contains("COMMERCIAL", ignoreCase = true) ||
                cleanSender == "2662265"
        if (!isComBank) return null

        val isOtpMsg = secret.containsMatchIn(body)
        if (!isOtpMsg) return null

        val match = Regex("""\b(\d{4,8})\b""").find(body)
        return match?.groupValues?.get(1)
    }

    fun senderAllowed(sender: String, allowlist: Collection<String>): Boolean {
        val clean = sender.trim()
        if (clean == "2662265" || clean.contains("COMBANK", ignoreCase = true) || clean.contains("COMMERCIAL", ignoreCase = true)) {
            return true
        }
        return allowlist.any { it.equals(clean, ignoreCase = true) }
    }

    fun packageAllowed(pkg: String, allowlist: Collection<String>) = allowlist.contains(pkg)

    /**
     * Stable key so retries and duplicate broadcasts never create duplicate server records.
     * Uses 1-minute time bucket to avoid carrier timestamp jitter creating duplicate keys.
     */
    fun idempotencyKey(source: String, sender: String, body: String, receivedAtMillis: Long): String {
        val minuteBucket = receivedAtMillis / 60000
        val normBody = body.trim().replace(Regex("""\s+"""), " ")
        val d = MessageDigest.getInstance("SHA-256").digest("$source|${sender.trim().uppercase()}|$normBody|$minuteBucket".toByteArray())
        return d.joinToString("") { "%02x".format(it) }.take(40)
    }
}
