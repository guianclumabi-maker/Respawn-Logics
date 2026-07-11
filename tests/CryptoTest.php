<?php
/**
 * Unit tests for App\Utils\Crypto
 *
 * Run: php vendor/bin/phpunit tests/CryptoTest.php
 * Or:  php phpunit.phar tests/CryptoTest.php
 *
 * Environment: set APP_ENCRYPTION_KEY and APP_BLIND_INDEX_KEY before running,
 * or the test will set temporary test values via putenv().
 */

use PHPUnit\Framework\TestCase;
use App\Utils\Crypto;

require_once __DIR__ . '/../backend/utils/Crypto.php';

class CryptoTest extends TestCase
{
    // ── Setup ──────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        // Set deterministic test keys (32 random bytes base64-encoded, generated once)
        if (!getenv('APP_ENCRYPTION_KEY')) {
            putenv('APP_ENCRYPTION_KEY=' . base64_encode(str_repeat('K', 32)));
        }
        if (!getenv('APP_BLIND_INDEX_KEY')) {
            putenv('APP_BLIND_INDEX_KEY=' . base64_encode(str_repeat('B', 32)));
        }
    }

    // ── isEncrypted() ──────────────────────────────────────────────────────────

    public function testIsEncryptedReturnsTrueForEncPrefix(): void
    {
        $this->assertTrue(Crypto::isEncrypted('enc:v1:AAAA'));
    }

    public function testIsEncryptedReturnsFalseForPlaintext(): void
    {
        $this->assertFalse(Crypto::isEncrypted('123-456-7890'));
    }

    public function testIsEncryptedReturnsFalseForNull(): void
    {
        $this->assertFalse(Crypto::isEncrypted(null));
    }

    public function testIsEncryptedReturnsFalseForEmpty(): void
    {
        $this->assertFalse(Crypto::isEncrypted(''));
    }

    // ── Null / empty pass-through ──────────────────────────────────────────────

    public function testEncryptNullReturnsNull(): void
    {
        $this->assertNull(Crypto::encrypt(null));
    }

    public function testEncryptEmptyReturnsEmpty(): void
    {
        $this->assertSame('', Crypto::encrypt(''));
    }

    public function testDecryptNullReturnsNull(): void
    {
        $this->assertNull(Crypto::decrypt(null));
    }

    public function testDecryptEmptyReturnsEmpty(): void
    {
        $this->assertSame('', Crypto::decrypt(''));
    }

    // ── Legacy plaintext pass-through (migration safety) ──────────────────────

    public function testDecryptLegacyPlaintextPassThrough(): void
    {
        $legacy = '112-345-678'; // old SSS number stored before encryption
        $this->assertSame($legacy, Crypto::decrypt($legacy));
    }

    // ── Round-trip ─────────────────────────────────────────────────────────────

    public function testEncryptDecryptRoundTrip(): void
    {
        $plaintext = '123-456-7890-000'; // sample TIN
        $ct = Crypto::encrypt($plaintext);
        $this->assertSame($plaintext, Crypto::decrypt($ct));
    }

    public function testRoundTripWithSpecialChars(): void
    {
        $plaintext = "Niño O'Brien — PH-ÑÑÑ";
        $this->assertSame($plaintext, Crypto::decrypt(Crypto::encrypt($plaintext)));
    }

    public function testRoundTripWithBinaryishString(): void
    {
        $plaintext = str_repeat("\x00\xFF", 20);
        $this->assertSame($plaintext, Crypto::decrypt(Crypto::encrypt($plaintext)));
    }

    // ── Non-determinism (GCM uses random nonce per call) ──────────────────────

    public function testEncryptIsNonDeterministic(): void
    {
        $plaintext = '112-345-678';
        $ct1 = Crypto::encrypt($plaintext);
        $ct2 = Crypto::encrypt($plaintext);
        $this->assertNotSame($ct1, $ct2, 'Two encryptions of the same value must produce different ciphertexts.');
    }

    // ── Idempotent encrypt (never double-encrypt) ──────────────────────────────

    public function testEncryptIsIdempotentOnAlreadyEncrypted(): void
    {
        $ct = Crypto::encrypt('some TIN');
        $this->assertSame($ct, Crypto::encrypt($ct));
    }

    // ── Tamper detection (GCM authentication tag) ─────────────────────────────

    public function testTamperedCiphertextThrows(): void
    {
        $ct  = Crypto::encrypt('1234567890');
        $raw = base64_decode(substr($ct, strlen('enc:v1:')), true);
        // Flip a bit in the ciphertext body (after the 12-byte nonce)
        $raw[15] = chr(ord($raw[15]) ^ 0xFF);
        $tampered = 'enc:v1:' . base64_encode($raw);
        $this->expectException(\RuntimeException::class);
        Crypto::decrypt($tampered);
    }

    public function testTruncatedCiphertextThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        Crypto::decrypt('enc:v1:dG9vc2hvcnQ='); // "tooshort" in base64 — < 29 bytes raw
    }

    // ── blindIndex() ──────────────────────────────────────────────────────────

    public function testBlindIndexIsDeterministic(): void
    {
        $h1 = Crypto::blindIndex('123-456-7890');
        $h2 = Crypto::blindIndex('123-456-7890');
        $this->assertSame($h1, $h2);
    }

    public function testBlindIndexIsCaseAndSpaceNormalized(): void
    {
        $h1 = Crypto::blindIndex('  ABC  ');
        $h2 = Crypto::blindIndex('abc');
        $this->assertSame($h1, $h2);
    }

    public function testBlindIndexDiffersForDifferentValues(): void
    {
        $this->assertNotSame(
            Crypto::blindIndex('123-456-7890'),
            Crypto::blindIndex('000-000-0000')
        );
    }

    public function testBlindIndexReturnsNullForNull(): void
    {
        $this->assertNull(Crypto::blindIndex(null));
    }

    public function testBlindIndexReturnsNullForEmpty(): void
    {
        $this->assertNull(Crypto::blindIndex(''));
    }

    // ── File / byte helpers ────────────────────────────────────────────────────

    public function testEncryptDecryptBytesRoundTrip(): void
    {
        $bytes = random_bytes(512);
        $blob  = Crypto::encryptBytes($bytes);
        $this->assertTrue(Crypto::isEncrypted($blob));
        $this->assertSame($bytes, Crypto::decryptBytes($blob));
    }

    public function testDecryptBytesLegacyPassThrough(): void
    {
        $legacy = 'raw file content without enc prefix';
        $this->assertSame($legacy, Crypto::decryptBytes($legacy));
    }

    public function testEncryptBytesIsNonDeterministic(): void
    {
        $bytes = str_repeat('x', 100);
        $this->assertNotSame(Crypto::encryptBytes($bytes), Crypto::encryptBytes($bytes));
    }

    // ── Missing key throws loudly ──────────────────────────────────────────────

    public function testMissingMasterKeyThrows(): void
    {
        $orig = getenv('APP_ENCRYPTION_KEY');
        putenv('APP_ENCRYPTION_KEY=');
        try {
            $this->expectException(\RuntimeException::class);
            Crypto::encrypt('anything');
        } finally {
            putenv("APP_ENCRYPTION_KEY=$orig");
        }
    }
}
