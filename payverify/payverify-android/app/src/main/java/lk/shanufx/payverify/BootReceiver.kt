package lk.shanufx.payverify

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent

/**
 * Ensures PayVerify background workers restart immediately when the Android device reboots.
 */
class BootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_BOOT_COMPLETED || intent.action == "android.intent.action.QUICKBOOT_POWERON") {
            val store = App.store(context)
            if (store.enrolled) {
                store.log("info", "Device reboot detected: scheduling periodic heartbeat and pending sync")
                Sync.schedulePeriodic(context)
                if (store.queueDepth() > 0) {
                    Sync.request(context)
                }
            }
        }
    }
}

