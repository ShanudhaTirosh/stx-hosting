package lk.shanufx.payverify

import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.MessageDigest
import java.security.PrivateKey
import java.security.Signature
import java.security.spec.ECGenParameterSpec

/** ECDSA P-256 key pair generated INSIDE the Android Keystore; the private key never leaves the device. */
object Signer {
    private const val ALIAS = "payverify_device_key"
    private fun ks() = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }

    fun ensureKey() {
        if (ks().containsAlias(ALIAS)) return
        val spec = KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_SIGN)
            .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
            .setDigests(KeyProperties.DIGEST_SHA256).build()
        KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, "AndroidKeyStore").apply { initialize(spec) }.generateKeyPair()
    }

    fun publicKeyB64(): String {
        ensureKey()
        return Base64.encodeToString(ks().getCertificate(ALIAS).publicKey.encoded, Base64.NO_WRAP)  // X.509 SubjectPublicKeyInfo
    }

    fun forget() { runCatching { ks().deleteEntry(ALIAS) } }

    /** Signs "METHOD\nPATH\nTIMESTAMP\nSHA256hex(body)" exactly as the server verifies it. DER-encoded signature. */
    fun sign(method: String, path: String, ts: String, body: ByteArray): String {
        val bodyHash = MessageDigest.getInstance("SHA-256").digest(body).joinToString("") { "%02x".format(it) }
        val key = ks().getKey(ALIAS, null) as PrivateKey
        val sig = Signature.getInstance("SHA256withECDSA").apply {
            initSign(key); update("$method\n$path\n$ts\n$bodyHash".toByteArray())
        }.sign()
        return Base64.encodeToString(sig, Base64.NO_WRAP)
    }
}
