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

    /** Upload queued alerts. REPLACE ensures that new SMS arrivals during in-flight jobs are never dropped. */
    fun request(ctx: Context) {
        WorkManager.getInstance(ctx).enqueueUniqueWork(
            "sync",
            ExistingWorkPolicy.REPLACE,
            OneTimeWorkRequestBuilder<SyncWorker>()
                .setConstraints(online)
                .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 15, TimeUnit.SECONDS)
                .build()
        )
    }

    /** 15 minutes periodic heartbeat to report device health and battery optimization state. */
    fun schedulePeriodic(ctx: Context) {
        WorkManager.getInstance(ctx).enqueueUniquePeriodicWork(
            "heartbeat",
            ExistingPeriodicWorkPolicy.KEEP,
            PeriodicWorkRequestBuilder<HeartbeatWorker>(15, TimeUnit.MINUTES)
                .setConstraints(online)
                .build()
        )
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
                batch.forEach { store.markDone(it.key, results[it.key] ?: "synced", results[it.key] ?: "uploaded") }
                store.log("info", "Synced ${batch.size} alert(s) to server")
            }
            store.lastSyncMs = System.currentTimeMillis()
            store.lastError = ""
            if (store.queueDepth() > 0) Result.retry() else Result.success()
        } catch (e: ApiException) {
            store.lastError = if (e.code == 401) "Server rejected this device (revoked, or phone clock is wrong)" else e.message.orEmpty()
            store.log("error", store.lastError)
            // Do not spin battery infinitely on 401/403 permanent authorization failure
            if (e.code == 401 || e.code == 403) Result.failure() else Result.retry()
        } catch (e: IOException) {
            store.lastError = "Offline or server unreachable"
            store.log("warn", store.lastError)
            Result.retry()
        }
    }
}

class HeartbeatWorker(ctx: Context, p: WorkerParameters) : CoroutineWorker(ctx, p) {
    override suspend fun doWork(): Result {
        val c = applicationContext
        val store = App.store(c)
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
            Api.heartbeat(store, payload)
            runCatching { Api.fetchConfig(store) }
            store.prune()
            if (store.queueDepth() > 0) Sync.request(c)
            Result.success()
        } catch (e: IOException) {
            store.log("warn", "Heartbeat failed: ${e.message}")
            Result.retry()
        }
    }
}

object BuildConfigVersion { const val NAME = "0.2.0" }
