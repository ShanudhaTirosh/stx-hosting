package lk.shanufx.payverify

import android.content.Context
import android.os.PowerManager
import androidx.core.app.NotificationManagerCompat
import androidx.work.*
import org.json.JSONObject
import java.io.IOException
import java.util.concurrent.TimeUnit

object Sync {
    private val online = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

    /** Upload queued alerts soon. Unique + KEEP so a burst of alerts becomes one battery-friendly job. */
    fun request(ctx: Context) {
        WorkManager.getInstance(ctx).enqueueUniqueWork("sync", ExistingWorkPolicy.KEEP,
            OneTimeWorkRequestBuilder<SyncWorker>().setConstraints(online)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS).build())
    }

    /** 15 minutes is Android's minimum period; the server uses this heartbeat to notice a silent phone. */
    fun schedulePeriodic(ctx: Context) {
        WorkManager.getInstance(ctx).enqueueUniquePeriodicWork("heartbeat", ExistingPeriodicWorkPolicy.KEEP,
            PeriodicWorkRequestBuilder<HeartbeatWorker>(15, TimeUnit.MINUTES).setConstraints(online).build())
    }
}

class SyncWorker(ctx: Context, p: WorkerParameters) : CoroutineWorker(ctx, p) {
    override suspend fun doWork(): Result {
        val store = App.store(applicationContext)
        if (!store.enrolled) return Result.success()
        return try {
            repeat(10) {
                val batch = store.pending()
                if (batch.isEmpty()) return@repeat
                val results = Api.sendEvents(store, batch)
                batch.forEach { store.markDone(it.key, results[it.key] ?: "unknown", results[it.key] ?: "no answer") }
                store.log("info", "Synced ${batch.size} alert(s)")
            }
            store.lastSyncMs = System.currentTimeMillis(); store.lastError = ""
            if (store.queueDepth() > 0) Result.retry() else Result.success()
        } catch (e: ApiException) {
            store.lastError = if (e.code == 401) "Server rejected this device (revoked, or phone clock is wrong)" else e.message.orEmpty()
            store.log("error", store.lastError); Result.retry()
        } catch (e: IOException) {
            store.lastError = "Offline or server unreachable"; store.log("warn", store.lastError); Result.retry()
        }
    }
}

class HeartbeatWorker(ctx: Context, p: WorkerParameters) : CoroutineWorker(ctx, p) {
    override suspend fun doWork(): Result {
        val c = applicationContext; val store = App.store(c)
        if (!store.enrolled) return Result.success()
        val pm = c.getSystemService(Context.POWER_SERVICE) as PowerManager
        val payload = JSONObject()
            .put("app_version", BuildConfigVersion.NAME)
            .put("sms_permission", c.checkSelfPermission(android.Manifest.permission.RECEIVE_SMS) == android.content.pm.PackageManager.PERMISSION_GRANTED)
            .put("listener_enabled", NotificationManagerCompat.getEnabledListenerPackages(c).contains(c.packageName))
            .put("battery_optimised", !pm.isIgnoringBatteryOptimizations(c.packageName))
            .put("queue_depth", store.queueDepth())
            .put("last_error", store.lastError.ifEmpty { null })
        return try {
            Api.heartbeat(store, payload); runCatching { Api.fetchConfig(store) }; store.prune()
            if (store.queueDepth() > 0) Sync.request(c)
            Result.success()
        } catch (e: IOException) { store.log("warn", "Heartbeat failed: ${e.message}"); Result.retry() }
    }
}

object BuildConfigVersion { const val NAME = "0.1.0" }
