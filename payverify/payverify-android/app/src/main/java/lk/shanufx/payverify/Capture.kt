package lk.shanufx.payverify

import android.app.Notification
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification

/**
 * Incoming SMS interceptor.
 * Reliably captures, orders multi-part SMS, extracts OTPs in-memory, and smartly parses bank cards.
 */
class SmsReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return
        val store = App.store(ctx)
        if (!store.enrolled) return
        val parts = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return

        parts.groupBy { it.originatingAddress.orEmpty() }.forEach { (sender, msgs) ->
            // Sort multi-part SMS parts to guarantee proper message assembly
            val sorted = msgs.sortedBy { it.timestampMillis }
            val body = sorted.joinToString("") { it.messageBody.orEmpty() }
            val at = sorted.firstOrNull()?.timestampMillis ?: System.currentTimeMillis()

            if (!Prefilter.senderAllowed(sender, store.senders)) return@forEach

            // ZERO-LEAK COMBANK WHATSAPP OTP RELAY (supports COMBANK, Commercial Bank, and 2662265):
            val combankOtp = Prefilter.extractComBankOtp(sender, body, store.senders)
            if (combankOtp != null) {
                store.log("info", "ComBank WhatsApp OTP intercepted; relaying securely in-memory")
                Thread {
                    for (attempt in 1..3) {
                        try {
                            val ok = Api.relayOtp(store, combankOtp, sender)
                            if (ok) {
                                store.log("info", "ComBank OTP relayed successfully to bot")
                                break
                            }
                        } catch (e: Exception) {
                            store.log("warn", "Attempt $attempt to relay OTP failed: ${e.message}")
                            try { Thread.sleep(800) } catch (_: Exception) {}
                        }
                    }
                }.start()
                return@forEach
            }

            if (!Prefilter.isTransactionAlert(body)) return@forEach

            val parsed = BankParser.parse(sender, body)
            val key = Prefilter.idempotencyKey("sms", sender, body, at)
            if (store.enqueueParsed(key, "sms", sender, body, at, parsed)) {
                store.log("info", "Bank SMS alert queued: ${parsed.category} ${parsed.formattedAmount}")
                Sync.request(ctx)
            }
        }
    }
}

/** Reads notifications from allow-listed bank app packages; parses alerts into smart cards. */
class BankNotificationListener : NotificationListenerService() {
    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val store = App.store(applicationContext)
        if (!store.enrolled || !Prefilter.packageAllowed(sbn.packageName, store.packages)) return
        val ex = sbn.notification.extras
        val text = listOfNotNull(
            ex.getCharSequence(Notification.EXTRA_TITLE),
            ex.getCharSequence(Notification.EXTRA_BIG_TEXT) ?: ex.getCharSequence(Notification.EXTRA_TEXT)
        ).joinToString(" ")

        if (!Prefilter.isTransactionAlert(text)) return
        val parsed = BankParser.parse(sbn.packageName, text)
        val key = Prefilter.idempotencyKey("notification", sbn.packageName, text, sbn.postTime)
        if (store.enqueueParsed(key, "notification", sbn.packageName, text, sbn.postTime, parsed)) {
            store.log("info", "Bank notification queued: ${parsed.category} ${parsed.formattedAmount}")
            Sync.request(applicationContext)
        }
    }
}
