package lk.shanufx.payverify

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper

data class EventRow(val id: Long, val ts: Long, val source: String, val sender: String, val state: String, val note: String)
data class LogRow(val ts: Long, val level: String, val msg: String)

data class MessageCardItem(
    val id: Long,
    val key: String,
    val source: String,
    val sender: String,
    val body: String,
    val receivedMs: Long,
    val state: String,
    val note: String,
    val amountMinor: Long?,
    val direction: String?,
    val balanceMinor: Long?,
    val accountTail: String?,
    val reference: String?,
    val branch: String?,
    val category: String,
    val receiptUrl: String?,
    val isRead: Boolean
)

/**
 * Local encrypted outbox, message repository, and sync log.
 * Stores bank messages with smart parsed metadata for the Device Message Viewer.
 */
class Store(ctx: Context) : SQLiteOpenHelper(ctx, "payverify.db", null, 2) {
    private val prefs = ctx.getSharedPreferences("pv", Context.MODE_PRIVATE)

    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL("""
            CREATE TABLE events(
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                k TEXT UNIQUE,
                source TEXT,
                sender TEXT,
                body TEXT,
                received_ms INTEGER,
                state TEXT,
                note TEXT DEFAULT '',
                amount_minor INTEGER,
                direction TEXT,
                balance_minor INTEGER,
                account_tail TEXT,
                reference TEXT,
                branch TEXT,
                category TEXT,
                receipt_url TEXT,
                is_read INTEGER DEFAULT 0
            )
        """.trimIndent())
        db.execSQL("CREATE TABLE logs(id INTEGER PRIMARY KEY AUTOINCREMENT, ts INTEGER, level TEXT, msg TEXT)")
        db.execSQL("CREATE INDEX idx_events_received ON events(received_ms DESC)")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {
        if (oldVersion < 2) {
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN amount_minor INTEGER") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN direction TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN balance_minor INTEGER") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN account_tail TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN reference TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN branch TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN category TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN receipt_url TEXT") }
            runCatching { db.execSQL("ALTER TABLE events ADD COLUMN is_read INTEGER DEFAULT 0") }
        }
    }

    // ---- Settings ----
    var baseUrl: String
        get() = prefs.getString("base", "") ?: ""
        set(v) = prefs.edit().putString("base", v.trimEnd('/')).apply()

    var deviceId: Long
        get() = prefs.getLong("dev", 0)
        set(v) = prefs.edit().putLong("dev", v).apply()

    var lastSyncMs: Long
        get() = prefs.getLong("last_sync", 0)
        set(v) = prefs.edit().putLong("last_sync", v).apply()

    var lastError: String
        get() = prefs.getString("last_err", "") ?: ""
        set(v) = prefs.edit().putString("last_err", v).apply()

    var senders: Set<String>
        get() = prefs.getStringSet("senders", setOf("COMBANK", "ComBank", "Commercial Bank", "2662265")) ?: emptySet()
        set(v) = prefs.edit().putStringSet("senders", v).apply()

    var packages: Set<String>
        get() = prefs.getStringSet("pkgs", setOf("com.combank.combankdigital", "combank.com.combankdigital")) ?: emptySet()
        set(v) = prefs.edit().putStringSet("pkgs", v).apply()

    var biometricLockEnabled: Boolean
        get() = prefs.getBoolean("bio_lock", false)
        set(v) = prefs.edit().putBoolean("bio_lock", v).apply()

    var adminToken: String
        get() = prefs.getString("admin_token", "") ?: ""
        set(v) = prefs.edit().putString("admin_token", v).apply()

    val enrolled get() = deviceId > 0 && baseUrl.isNotEmpty()

    fun resetEnrolment() {
        prefs.edit().clear().apply()
    }

    // ---- Outbox & Ingestion ----
    @Synchronized
    fun enqueueParsed(
        key: String,
        source: String,
        sender: String,
        body: String,
        receivedMs: Long,
        parsed: ParsedAlert
    ): Boolean {
        val cv = ContentValues().apply {
            put("k", key)
            put("source", source)
            put("sender", sender)
            put("body", body)
            put("received_ms", receivedMs)
            put("state", "pending")
            put("amount_minor", parsed.amountMinor)
            put("direction", parsed.direction)
            put("balance_minor", parsed.balanceMinor)
            put("account_tail", parsed.accountTail)
            put("reference", parsed.reference)
            put("branch", parsed.branch)
            put("category", parsed.category)
            put("receipt_url", parsed.receiptUrl)
            put("is_read", 0)
        }
        return writableDatabase.insertWithOnConflict("events", null, cv, SQLiteDatabase.CONFLICT_IGNORE) != -1L
    }

    data class Pending(val id: Long, val key: String, val source: String, val sender: String, val body: String, val receivedMs: Long)

    fun pending(limit: Int = 50): List<Pending> =
        readableDatabase.rawQuery("SELECT id,k,source,sender,body,received_ms FROM events WHERE state='pending' ORDER BY id LIMIT ?", arrayOf("$limit")).use { c ->
            buildList {
                while (c.moveToNext()) {
                    add(Pending(c.getLong(0), c.getString(1), c.getString(2), c.getString(3), c.getString(4), c.getLong(5)))
                }
            }
        }

    fun queueDepth(): Int =
        readableDatabase.rawQuery("SELECT COUNT(*) FROM events WHERE state='pending'", null).use {
            if (it.moveToFirst()) it.getInt(0) else 0
        }

    @Synchronized
    fun markDone(key: String, state: String, note: String) {
        // PRESERVES message body locally so the viewer can display full text & details!
        writableDatabase.execSQL("UPDATE events SET state=?, note=? WHERE k=?", arrayOf(state, note, key))
    }

    // ---- Messages Viewer Queries ----
    fun getMessages(
        query: String = "",
        directionFilter: String? = null,
        categoryFilter: String? = null,
        unreadOnly: Boolean = false,
        limit: Int = 100
    ): List<MessageCardItem> {
        val whereClauses = mutableListOf<String>()
        val args = mutableListOf<String>()

        if (query.isNotBlank()) {
            whereClauses.add("(body LIKE ? OR sender LIKE ? OR reference LIKE ? OR branch LIKE ?)")
            val pattern = "%${query.trim()}%"
            args.add(pattern); args.add(pattern); args.add(pattern); args.add(pattern)
        }
        if (!directionFilter.isNullOrBlank() && directionFilter != "all") {
            whereClauses.add("direction = ?")
            args.add(directionFilter.lowercase())
        }
        if (!categoryFilter.isNullOrBlank() && categoryFilter != "All") {
            whereClauses.add("category = ?")
            args.add(categoryFilter)
        }
        if (unreadOnly) {
            whereClauses.add("is_read = 0")
        }

        val where = if (whereClauses.isEmpty()) "" else "WHERE " + whereClauses.joinToString(" AND ")
        val sql = "SELECT id,k,source,sender,body,received_ms,state,note,amount_minor,direction,balance_minor,account_tail,reference,branch,category,receipt_url,is_read FROM events $where ORDER BY received_ms DESC LIMIT ?"
        args.add("$limit")

        return readableDatabase.rawQuery(sql, args.toTypedArray()).use { c ->
            buildList {
                while (c.moveToNext()) {
                    add(
                        MessageCardItem(
                            id = c.getLong(0),
                            key = c.getString(1),
                            source = c.getString(2),
                            sender = c.getString(3),
                            body = c.getString(4) ?: "",
                            receivedMs = c.getLong(5),
                            state = c.getString(6) ?: "pending",
                            note = c.getString(7) ?: "",
                            amountMinor = if (c.isNull(8)) null else c.getLong(8),
                            direction = c.getString(9),
                            balanceMinor = if (c.isNull(10)) null else c.getLong(10),
                            accountTail = c.getString(11),
                            reference = c.getString(12),
                            branch = c.getString(13),
                            category = c.getString(14) ?: "Bank Alert",
                            receiptUrl = c.getString(15),
                            isRead = c.getInt(16) == 1
                        )
                    )
                }
            }
        }
    }

    @Synchronized
    fun markAsRead(id: Long) {
        writableDatabase.execSQL("UPDATE events SET is_read=1 WHERE id=?", arrayOf(id))
    }

    @Synchronized
    fun markAllAsRead() {
        writableDatabase.execSQL("UPDATE events SET is_read=1 WHERE is_read=0")
    }

    fun unreadCount(): Int =
        readableDatabase.rawQuery("SELECT COUNT(*) FROM events WHERE is_read=0", null).use {
            if (it.moveToFirst()) it.getInt(0) else 0
        }

    fun history(limit: Int = 100): List<EventRow> =
        readableDatabase.rawQuery("SELECT id,received_ms,source,sender,state,note FROM events ORDER BY received_ms DESC LIMIT ?", arrayOf("$limit")).use { c ->
            buildList {
                while (c.moveToNext()) {
                    add(EventRow(c.getLong(0), c.getLong(1), c.getString(2), c.getString(3), c.getString(4), c.getString(5)))
                }
            }
        }

    // ---- Event Logs ----
    @Synchronized
    fun log(level: String, msg: String) {
        writableDatabase.execSQL("INSERT INTO logs(ts,level,msg) VALUES(?,?,?)", arrayOf(System.currentTimeMillis(), level, msg.take(300)))
        writableDatabase.execSQL("DELETE FROM logs WHERE id < (SELECT MAX(id) FROM logs) - 500")
    }

    fun logs(limit: Int = 100): List<LogRow> =
        readableDatabase.rawQuery("SELECT ts,level,msg FROM logs ORDER BY id DESC LIMIT ?", arrayOf("$limit")).use { c ->
            buildList {
                while (c.moveToNext()) {
                    add(LogRow(c.getLong(0), c.getString(1), c.getString(2)))
                }
            }
        }

    fun prune() {
        writableDatabase.execSQL("DELETE FROM events WHERE state!='pending' AND received_ms < ?", arrayOf(System.currentTimeMillis() - 60L * 86400_000))
    }
}
