package lk.shanufx.payverify

import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import okhttp3.Request
import okhttp3.RequestBody.Companion.toRequestBody
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.time.Instant
import java.util.concurrent.TimeUnit

class ApiException(val code: Int, msg: String) : IOException("HTTP $code: $msg")

object Api {
    private val json = "application/json".toMediaType()
    private val http = OkHttpClient.Builder().connectTimeout(15, TimeUnit.SECONDS).readTimeout(30, TimeUnit.SECONDS).build()
    // For production, pin your server certificate with OkHttp's CertificatePinner.

    private fun call(req: Request): String = http.newCall(req).execute().use { r ->
        val text = r.body?.string().orEmpty()
        if (!r.isSuccessful) throw ApiException(r.code, text.take(160))
        text
    }

    private fun signed(store: Store, method: String, path: String, body: String?): Request {
        val bytes = body?.toByteArray() ?: ByteArray(0)
        val ts = (System.currentTimeMillis() / 1000).toString()
        val url = store.baseUrl + path
        val b = Request.Builder().url(url)
            .header("X-Device-Id", store.deviceId.toString()).header("X-Timestamp", ts)
            .header("X-Signature", Signer.sign(method, path, ts, bytes))
        return if (method == "GET") b.get().build() else b.method(method, bytes.toRequestBody(json)).build()
    }

    fun enrol(baseUrl: String, code: String, name: String): Long {
        require(baseUrl.startsWith("https://")) { "Server address must start with https://" }
        Signer.ensureKey()
        val body = JSONObject().put("code", code.trim().uppercase()).put("name", name).put("public_key_der_b64", Signer.publicKeyB64())
        val r = call(Request.Builder().url(baseUrl.trimEnd('/') + "/api/v1/devices/enrol").post(body.toString().toRequestBody(json)).build())
        return JSONObject(r).getLong("device_id")
    }

    fun heartbeat(store: Store, payload: JSONObject) { call(signed(store, "POST", "/api/v1/devices/heartbeat", payload.toString())) }

    fun fetchConfig(store: Store) {
        val j = JSONObject(call(signed(store, "GET", "/api/v1/devices/config", null)))
        store.senders = j.getJSONArray("sender_allowlist").toList().toSet()
        store.packages = j.getJSONArray("notification_packages").toList().toSet()
    }

    /** Returns key -> server result string. */
    fun sendEvents(store: Store, items: List<Store.Pending>): Map<String, String> {
        val arr = JSONArray()
        items.forEach {
            arr.put(JSONObject().put("idempotency_key", it.key).put("source", it.source).put("sender", it.sender)
                .put("body", it.body).put("received_at", Instant.ofEpochMilli(it.receivedMs).toString()))
        }
        val res = JSONObject(call(signed(store, "POST", "/api/v1/devices/sms-events", JSONObject().put("events", arr).toString()))).getJSONArray("results")
        return (0 until res.length()).associate { val o = res.getJSONObject(it); o.getString("key") to o.getString("result") }
    }

    /** Zero-leak relay: sends ComBank OTP in-memory to server; never stored locally. */
    fun relayOtp(store: Store, otp: String, sender: String = "COMBANK"): Boolean {
        val payload = JSONObject().put("otp", otp.trim()).put("sender", sender.trim())
        val res = call(signed(store, "POST", "/api/v1/devices/otp-relay", payload.toString()))
        return JSONObject(res).optBoolean("relayed", false)
    }

    fun isOtpSessionActive(store: Store): Boolean {
        return try {
            val res = call(signed(store, "GET", "/api/v1/devices/otp-session-active", null))
            JSONObject(res).optBoolean("awaiting", false)
        } catch (_: Exception) {
            false
        }
    }

    data class DeviceClaim(
        val id: Long,
        val invoiceNo: String,
        val amountFormatted: String,
        val status: String,
        val reference: String?,
        val customerEmail: String?,
        val customerName: String?,
        val customerId: Long?
    )

    fun fetchClaims(store: Store): List<DeviceClaim> {
        val res = call(signed(store, "GET", "/api/v1/devices/claims", null))
        val arr = JSONArray(res)
        return (0 until arr.length()).map {
            val o = arr.getJSONObject(it)
            val cust = o.optJSONObject("customer")
            DeviceClaim(
                id = o.getLong("id"),
                invoiceNo = o.optString("invoice_no", "-"),
                amountFormatted = o.optString("amount_formatted", ""),
                status = o.optString("status", "pending"),
                reference = o.optString("reference", "").takeIf { r -> r.isNotEmpty() },
                customerEmail = cust?.optString("email"),
                customerName = cust?.optString("name"),
                customerId = cust?.optLong("id")
            )
        }
    }

    /** Manually verify a payment claim from the phone. */
    fun manuallyVerifyClaim(store: Store, claimId: Long, reason: String = "Verified by mobile app"): Boolean {
        val payload = JSONObject().put("reason", reason)
        val res = call(signed(store, "POST", "/api/v1/devices/claims/$claimId/verify", payload.toString()))
        return JSONObject(res).optString("status") == "verified"
    }

    private fun JSONArray.toList() = (0 until length()).map { getString(it) }
}
