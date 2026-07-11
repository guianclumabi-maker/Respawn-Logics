<?php
namespace App\Utils;

/**
 * AES-256-GCM authenticated encryption helper.
 *
 * Ciphertext format: enc:v1: + base64( nonce[12] | ciphertext | tag[16] )
 * The enc:v1: prefix flags encrypted values and versions the scheme for future rotation.
 *
 * Required environment variables (Railway → Variables, never committed):
 *   APP_ENCRYPTION_KEY   — base64-encoded 32 random bytes  (master key)
 *   APP_BLIND_INDEX_KEY  — base64-encoded 32 random bytes  (HMAC key for searchable indexes)
 *
 * Generate keys (run once locally, then set in Railway):
 *   php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"  # APP_ENCRYPTION_KEY
 *   php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"  # APP_BLIND_INDEX_KEY
 *
 * RULES — do not violate:
 *  - Only encrypt store-and-display identifiers (TIN, SSS, etc.) and documents.
 *  - Never encrypt computed fields (base_salary, statutory amounts, is_mwe).
 *  - Never log decrypted values or key material.
 *  - Widen target columns to VARCHAR(255)/TEXT BEFORE encrypting.
 *  - decrypt() tolerates legacy plaintext (no prefix) — the app never breaks mid-migration.
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private const NONCE  = 12; // bytes
    private const TAG    = 16; // bytes
    private const PREFIX = 'enc:v1:';

    // ── Key loading ────────────────────────────────────────────────────────────

    private static function masterKey(): string
    {
        $b64 = getenv('APP_ENCRYPTION_KEY') ?: ($_ENV['APP_ENCRYPTION_KEY'] ?? '');
        if ($b64 === '') {
            throw new \RuntimeException(
                'APP_ENCRYPTION_KEY is not set. Set it in Railway Variables (never in code). ' .
                'Generate: php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"'
            );
        }
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('APP_ENCRYPTION_KEY must be exactly 32 bytes, base64-encoded.');
        }
        return $key;
    }

    private static function blindKey(): string
    {
        $b64 = getenv('APP_BLIND_INDEX_KEY') ?: ($_ENV['APP_BLIND_INDEX_KEY'] ?? '');
        if ($b64 === '') {
            throw new \RuntimeException(
                'APP_BLIND_INDEX_KEY is not set. Set it in Railway Variables (never in code). ' .
                'Generate: php -r "echo base64_encode(random_bytes(32)),PHP_EOL;"'
            );
        }
        $key = base64_decode($b64, true);
        return $key !== false ? $key : $b64; // fall back to raw string if not valid base64
    }

    // ── Public helpers ─────────────────────────────────────────────────────────

    /**
     * Returns true if $value looks like a ciphertext produced by this class.
     * Use this to tolerate legacy plaintext during a rolling migration.
     */
    public static function isEncrypted(?string $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    /**
     * Encrypt a string. Null and empty string pass through unchanged.
     * Already-encrypted values are returned as-is (never double-encrypted).
     */
    public static function encrypt(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return $plaintext;
        }
        if (self::isEncrypted($plaintext)) {
            return $plaintext; // idempotent
        }

        $nonce = random_bytes(self::NONCE);
        $tag   = '';
        $ct    = openssl_encrypt(
            $plaintext, self::CIPHER, self::masterKey(),
            OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG
        );
        if ($ct === false) {
            throw new \RuntimeException('Encryption failed (openssl_encrypt returned false).');
        }
        return self::PREFIX . base64_encode($nonce . $ct . $tag);
    }

    /**
     * Decrypt a ciphertext produced by encrypt().
     * Legacy plaintext (no enc:v1: prefix) is returned as-is — the app
     * survives a half-migrated database without breaking.
     */
    public static function decrypt(?string $blob): ?string
    {
        if ($blob === null || $blob === '' || !self::isEncrypted($blob)) {
            return $blob; // pass-through: null, empty, or legacy plaintext
        }

        $raw = base64_decode(substr($blob, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < self::NONCE + self::TAG + 1) {
            throw new \RuntimeException('Malformed ciphertext: failed to base64-decode or too short.');
        }

        $nonce = substr($raw, 0, self::NONCE);
        $tag   = substr($raw, -self::TAG);
        $ct    = substr($raw, self::NONCE, -self::TAG);

        $pt = openssl_decrypt($ct, self::CIPHER, self::masterKey(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($pt === false) {
            throw new \RuntimeException('Decryption failed: ciphertext may be tampered or the key is wrong.');
        }
        return $pt;
    }

    /**
     * Deterministic blind index for equality-search without exposing plaintext.
     * Use this to find rows by TIN/account number: store blindIndex($v) in a *_bidx column,
     * then query WHERE tin_bidx = Crypto::blindIndex($search).
     *
     * Never use encrypt() for searching — AES-GCM is non-deterministic by design.
     */
    public static function blindIndex(?string $plaintext): ?string
    {
        if ($plaintext === null || $plaintext === '') {
            return null;
        }
        return hash_hmac('sha256', mb_strtolower(trim($plaintext)), self::blindKey());
    }

    // ── File / binary helpers ──────────────────────────────────────────────────

    /**
     * Encrypt raw file bytes (e.g., a 201-file PDF held in memory).
     * Returns enc:v1: prefixed base64 string suitable for file_put_contents().
     */
    public static function encryptBytes(string $bytes): string
    {
        return self::PREFIX . base64_encode(self::rawEnc($bytes));
    }

    /**
     * Decrypt bytes encrypted by encryptBytes().
     * Legacy unencrypted content (no prefix) is returned as-is.
     */
    public static function decryptBytes(string $blob): string
    {
        if (!self::isEncrypted($blob)) {
            return $blob; // legacy plaintext file — pass through
        }
        $raw = base64_decode(substr($blob, strlen(self::PREFIX)), true);
        if ($raw === false) {
            throw new \RuntimeException('Malformed encrypted file: base64 decode failed.');
        }
        return self::rawDec($raw);
    }

    // ── Internal ───────────────────────────────────────────────────────────────

    private static function rawEnc(string $bytes): string
    {
        $nonce = random_bytes(self::NONCE);
        $tag   = '';
        $ct    = openssl_encrypt($bytes, self::CIPHER, self::masterKey(), OPENSSL_RAW_DATA, $nonce, $tag, '', self::TAG);
        if ($ct === false) {
            throw new \RuntimeException('File encryption failed.');
        }
        return $nonce . $ct . $tag;
    }

    private static function rawDec(string $raw): string
    {
        if (strlen($raw) < self::NONCE + self::TAG + 1) {
            throw new \RuntimeException('Malformed encrypted file: too short.');
        }
        $nonce = substr($raw, 0, self::NONCE);
        $tag   = substr($raw, -self::TAG);
        $ct    = substr($raw, self::NONCE, -self::TAG);
        $pt    = openssl_decrypt($ct, self::CIPHER, self::masterKey(), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($pt === false) {
            throw new \RuntimeException('File decryption failed: tampered or wrong key.');
        }
        return $pt;
    }
}
