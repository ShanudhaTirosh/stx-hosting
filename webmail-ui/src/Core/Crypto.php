<?php
declare(strict_types=1);

namespace Webmail\Core;

/** Authenticated encryption (XSalsa20-Poly1305) for secrets stored in the server-side session. */
final class Crypto
{
    private string $key;

    public function __construct(string $appKeyBase64)
    {
        $key = base64_decode($appKeyBase64, true);
        if ($key === false || strlen($key) < SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY must be base64 of at least 32 random bytes.');
        }
        // Derive a fixed-size key so longer keys are accepted.
        $this->key = sodium_crypto_generichash($key, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(string $plain): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, $this->key));
    }

    public function decrypt(string $blob): ?string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        return $plain === false ? null : $plain;
    }
}
