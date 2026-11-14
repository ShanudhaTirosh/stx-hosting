package lk.shanufx.payverify

import android.app.Application
import android.content.Context

class App : Application() {
    override fun onCreate() {
        super.onCreate()
        instance = this
        if (store(this).enrolled) Sync.schedulePeriodic(this)
    }
    companion object {
        private lateinit var instance: App
        private val lock = Any()
        private var s: Store? = null
        fun store(ctx: Context): Store = synchronized(lock) { s ?: Store(ctx.applicationContext).also { s = it } }
    }
}
