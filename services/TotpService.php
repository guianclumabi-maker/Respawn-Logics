<?php
/**
 * services/TotpService.php
 *
 * Self-contained TOTP (RFC 6238) implementation — no Composer required.
 * Compatible with Google Authenticator, Authy, and any RFC 6238 TOTP app.
 *
 * Security notes (OWASP A07 — Authentication Failures):
 *  - Uses HMAC-SHA1 as required by RFC 6238
 *  - Allows ±1 time window (30s each) to tolerate clock skew
 *  - Secrets are base32 encoded as per the TOTP standard
 *  - QR code URL is generated via Google Charts API (server-side, no JS dependency)
 */
class TotpService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const TIME_STEP    = 30;   // seconds per TOTP window (RFC 6238 default)
    private const CODE_DIGITS  = 6;    // digits in the OTP code
    private const WINDOW       = 1;    // allow ±1 window to handle clock skew

    // ── Secret Generation ────────────────────────────────────────────────────

    /**
     * Generates a cryptographically random base32 TOTP secret.
     * 20 bytes = 160 bits of entropy, encoded as 32 base32 characters.
     */
    public static function generateSecret(): string
    {
        // random_bytes() uses the OS CSPRNG — safe and unpredictable.
        $rawBytes = random_bytes(20);
        return self::base32Encode($rawBytes);
    }

    // ── Code Generation & Verification ──────────────────────────────────────

    /**
     * Generates the current TOTP code for a given secret.
     * Useful for testing without an authenticator app.
     */
    public static function generateCode(string $secret, int $timeStep = 0): string
    {
        $counter = (int) floor((time() + ($timeStep * self::TIME_STEP)) / self::TIME_STEP);
        return self::hotp($secret, $counter);
    }

    /**
     * Verifies a user-submitted 6-digit TOTP code against the stored secret.
     * Checks current window ± WINDOW to handle clock skew between server and device.
     *
     * @param string $secret  The base32 secret stored in totp_secrets table
     * @param string $code    The 6-digit code submitted by the user
     * @return bool           True if valid
     */
    public static function verify(string $secret, string $code): bool
    {
        // Reject if not exactly 6 digits — prevents brute-force with long inputs
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp = time();

        // Check current window and ±WINDOW adjacent windows
        for ($i = -self::WINDOW; $i <= self::WINDOW; $i++) {
            $counter = (int) floor($timestamp / self::TIME_STEP) + $i;
            $expected = self::hotp($secret, $counter);

            // hash_equals is constant-time — prevents timing side-channel attacks
            if (hash_equals($expected, $code)) {
                return true;
            }
        }

        return false;
    }

    // ── QR Code URL ──────────────────────────────────────────────────────────

    /**
     * Returns the otpauth:// URI for QR code generation.
     * Format: otpauth://totp/{label}?secret={secret}&issuer={issuer}&digits=6&period=30
     *
     * @param string $secret  The base32 secret
     * @param string $email   The user's email (used as account label)
     * @param string $issuer  Your app name (shown in authenticator apps)
     */
    public static function getOtpAuthUri(string $secret, string $email, string $issuer = 'Respawn Logics'): string
    {
        $label = rawurlencode($issuer . ':' . $email);
        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&digits=%d&period=%d',
            $label,
            $secret,
            rawurlencode($issuer),
            self::CODE_DIGITS,
            self::TIME_STEP
        );
    }

    /**
     * Returns a Google Charts QR code image URL for the otpauth URI.
     * This avoids requiring any PHP QR library.
     *
     * @param string $otpAuthUri  The otpauth:// URI
     * @param int    $size        QR image size in pixels (default 200)
     */
    public static function getQrCodeUrl(string $otpAuthUri, int $size = 200): string
    {
        return sprintf(
            'https://chart.googleapis.com/chart?chs=%dx%d&chld=M|0&cht=qr&chl=%s',
            $size,
            $size,
            rawurlencode($otpAuthUri)
        );
    }

    // ── HOTP Core (RFC 4226) ─────────────────────────────────────────────────

    /**
     * HOTP algorithm: HMAC-based One-Time Password (RFC 4226).
     * TOTP = HOTP with counter = floor(timestamp / TIME_STEP).
     */
    private static function hotp(string $secret, int $counter): string
    {
        // Pack counter as 64-bit big-endian unsigned integer
        $counterBytes = pack('N*', 0) . pack('N*', $counter);

        // Decode the base32 secret to raw bytes for HMAC
        $secretBytes = self::base32Decode($secret);

        // HMAC-SHA1 as required by RFC 4226
        $hmac = hash_hmac('sha1', $counterBytes, $secretBytes, true);

        // Dynamic truncation: use low-order 4 bits of last byte as offset
        $offset = ord($hmac[19]) & 0x0F;

        // Extract 4 bytes starting at offset, mask the sign bit
        $code = (
            ((ord($hmac[$offset])     & 0x7F) << 24) |
            ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
            ((ord($hmac[$offset + 2]) & 0xFF) <<  8) |
            ( ord($hmac[$offset + 3]) & 0xFF)
        );

        // Reduce to CODE_DIGITS digits, zero-padded
        return str_pad((string) ($code % (10 ** self::CODE_DIGITS)), self::CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    // ── Base32 Helpers ───────────────────────────────────────────────────────

    /**
     * Encodes raw bytes to base32 string (RFC 4648).
     */
    private static function base32Encode(string $data): string
    {
        $dataSize = strlen($data);
        $result   = '';
        $buffer   = 0;
        $bitsLeft = 0;

        for ($i = 0; $i < $dataSize; $i++) {
            $buffer = ($buffer << 8) | ord($data[$i]);
            $bitsLeft += 8;
            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $result .= self::BASE32_CHARS[($buffer >> $bitsLeft) & 0x1F];
            }
        }

        if ($bitsLeft > 0) {
            $result .= self::BASE32_CHARS[($buffer << (5 - $bitsLeft)) & 0x1F];
        }

        return $result;
    }

    /**
     * Decodes a base32 string back to raw bytes (RFC 4648).
     * Handles both upper and lower case input.
     */
    private static function base32Decode(string $data): string
    {
        $data     = strtoupper(rtrim($data, '='));
        $result   = '';
        $buffer   = 0;
        $bitsLeft = 0;

        for ($i = 0, $len = strlen($data); $i < $len; $i++) {
            $pos = strpos(self::BASE32_CHARS, $data[$i]);
            if ($pos === false) {
                continue; // skip invalid characters
            }
            $buffer   = ($buffer << 5) | $pos;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $result .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $result;
    }
}
