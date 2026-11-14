package lk.shanufx.payverify

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

data class EventRow(val id: Long, val ts: Long, val source: String, val sender: String, val state: String, val note: String)
data class LogRow(val ts: Long, val level: String, val msg: String)

/** Local outbox + sync log. Message bodies are kept only until the server acknowledges them. */
class Store(ctx: Context) : SQLiteOpenHelper(ctx, "payverify.db", null, 1) {
    private val prefs = ctx.getSharedPreferences("pv", Context.MODE_PRIVATE)

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL("CREATE TABLE events(id INTEGER PRIMARY KEY, k TEXT UNIQUE, source TEXT, sender TEXT, body TEXT, received_ms INTEGER, state TEXT, note TEXT DEFAULT '')")
        db.execSQL("CREATE TABLE logs(id INTEGER PRIMARY KEY, ts INTEGER, level TEXT, msg TEXT)")
    }
    override fun onUpgrade(db: SQLiteDatabase, o: Int, n: Int) {}

    // ---- settings (no secrets: the private key lives in the Keystore) ----
    var baseUrl: String get() = prefs.getString("base", "") ?: ""; set(v) = prefs.edit().putString("base", v.trimEnd('/')).apply()
    var deviceId: Long get() = prefs.getLong("dev", 0); set(v) = prefs.edit().putLong("dev", v).apply()
    var lastSyncMs: Long get() = prefs.getLong("last_sync", 0); set(v) = prefs.edit().putLong("last_sync", v).apply()
    var lastError: String get() = prefs.getString("last_err", "") ?: ""; set(v) = prefs.edit().putString("last_err", v).apply()
    var senders: Set<String> get() = prefs.getStringSet("senders", setOf("COMBANK", "ComBank")) ?: emptySet(); set(v) = prefs.edit().putStringSet("senders", v).apply()
    var packages: Set<String> get() = prefs.getStringSet("pkgs", setOf("com.combank.combankdigital")) ?: emptySet(); set(v) = prefs.edit().putStringSet("pkgs", v).apply()
    val enrolled get() = deviceId > 0 && baseUrl.isNotEmpty()

    fun resetEnrolment() { prefs.edit().clear().apply() }

    // ---- outbox ----
    @Synchronized fun enqueue(key: String, source: String, sender: String, body: String, receivedMs: Long): Boolean {
        val cv = ContentValues().apply { put("k", key); put("source", source); put("sender", sender); put("body", body); put("received_ms", receivedMs); put("state", "pending") }
        return writableDatabase.insertWithOnConflict("events", null, cv, SQLiteDatabase.CONFLICT_IGNORE) != -1L
    }
    data class Pending(val id: Long, val key: String, val source: String, val sender: String, val body: String, val receivedMs: Long)
    fun pending(limit: Int = 50): List<Pending> = readableDatabase.rawQuery("SELECT id,k,source,sender,body,received_ms FROM events WHERE state='pending' ORDER BY id LIMIT ?", arrayOf("$limit")).use { c ->
        buildList { while (c.moveToNext()) add(Pending(c.getLong(0), c.getString(1), c.getString(2), c.getString(3), c.getString(4), c.getLong(5))) } }
    fun queueDepth(): Int = readableDatabase.rawQuery("SELECT COUNT(*) FROM events WHERE state='pending'", null).use { it.moveToFirst(); it.getInt(0) }
    @Synchronized fun markDone(key: String, state: String, note: String) {
        // body is wiped once the server has answered; only metadata stays for the history screen
        writableDatabase.execSQL("UPDATE events SET state=?, note=?, body='' WHERE k=?", arrayOf(state, note, key))
    }
    fun history(limit: Int = 100): List<EventRow> = readableDatabase.rawQuery("SELECT id,received_ms,source,sender,state,note FROM events ORDER BY id DESC LIMIT ?", arrayOf("$limit")).use { c ->
        buildList { while (c.moveToNext()) add(EventRow(c.getLong(0), c.getLong(1), c.getString(2), c.getString(3), c.getString(4), c.getString(5))) } }

    // ---- logs ----
    @Synchronized fun log(level: String, msg: String) {
        writableDatabase.execSQL("INSERT INTO logs(ts,level,msg) VALUES(?,?,?)", arrayOf(System.currentTimeMillis(), level, msg.take(300)))
        writableDatabase.execSQL("DELETE FROM logs WHERE id < (SELECT MAX(id) FROM logs) - 500")
    }
    fun logs(limit: Int = 100): List<LogRow> = readableDatabase.rawQuery("SELECT ts,level,msg FROM logs ORDER BY id DESC LIMIT ?", arrayOf("$limit")).use { c ->
        buildList { while (c.moveToNext()) add(LogRow(c.getLong(0), c.getString(1), c.getString(2))) } }
    fun prune() { writableDatabase.execSQL("DELETE FROM events WHERE state!='pending' AND received_ms < ?", arrayOf(System.currentTimeMillis() - 30L * 86400_000)) }
}
