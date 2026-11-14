package lk.shanufx.payverify

import android.app.Notification
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification

/** Incoming SMS. Only allow-listed senders whose text looks like a transaction alert are kept. */
class SmsReceiver : BroadcastReceiver() {
    override fun onReceive(ctx: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return
        val store = App.store(ctx)
        if (!store.enrolled) return
        val parts = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return
        parts.groupBy { it.originatingAddress.orEmpty() }.forEach { (sender, msgs) ->
            val body = msgs.joinToString("") { it.messageBody.orEmpty() }
            val at = msgs.first().timestampMillis
            if (!Prefilter.senderAllowed(sender, store.senders)) return@forEach          // not the bank: ignore, never stored

            // ZERO-LEAK COMBANK WHATSAPP OTP RELAY:
            val combankOtp = Prefilter.extractComBankOtp(sender, body, store.senders)
            if (combankOtp != null) {
                // Relayed in-memory directly to PayVerify via encrypted ECDSA signed API.
                // NEVER stored in SQLite or device logs (redacted).
                store.log("info", "ComBank OTP intercepted; relaying securely")
                Thread {
                    try {
                        Api.relayOtp(store, combankOtp, sender)
                    } catch (e: Exception) {
                        store.log("warn", "Failed to relay ComBank OTP: ${e.message}")
                    }
                }.start()
                return@forEach
            }

            if (!Prefilter.isTransactionAlert(body)) return@forEach                      // OTPs, promos, chat: ignore
            val key = Prefilter.idempotencyKey("sms", sender, body, at)
            if (store.enqueue(key, "sms", sender, body, at)) { store.log("info", "SMS alert queued"); Sync.request(ctx) }
        }
    }
}

/** Optional. Reads ONLY notifications from allow-listed bank app packages; everything else is ignored. */
class BankNotificationListener : NotificationListenerService() {
    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val store = App.store(applicationContext)
        if (!store.enrolled || !Prefilter.packageAllowed(sbn.packageName, store.packages)) return
        val ex = sbn.notification.extras
        val text = listOfNotNull(ex.getCharSequence(Notification.EXTRA_TITLE), ex.getCharSequence(Notification.EXTRA_BIG_TEXT)
            ?: ex.getCharSequence(Notification.EXTRA_TEXT)).joinToString(" ")
        if (!Prefilter.isTransactionAlert(text)) return
        val key = Prefilter.idempotencyKey("notification", sbn.packageName, text, sbn.postTime)
        if (store.enqueue(key, "notification", sbn.packageName, text, sbn.postTime)) { store.log("info", "Bank notification queued"); Sync.request(applicationContext) }
    }
}
