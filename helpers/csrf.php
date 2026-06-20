<?php
/**
 * helpers/csrf.php
 *
 * Reusable CSRF protection helpers.
 * OWASP A01: Broken Access Control / OWASP CSRF guidance.
 *
 * Usage:
 *   In any form:     <?= csrf_field() ?>
 *   To validate:     csrf_verify();   // throws or redirects on failure
 */

/**
 * Returns the current session CSRF token, generating one if needed.
 * The token is a 32-byte cryptographically random hex string (256-bit entropy).
 */
function csrf_token(): string
{
    // Session must already be started by bootstrap/app.php before this runs.
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes() uses the OS CSPRNG — safe against prediction.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Returns an HTML hidden input with the current CSRF token.
 * Embed this inside every <form> that uses POST.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Validates the submitted CSRF token against the session value.
 * Uses hash_equals() to prevent timing-based side-channel attacks.
 *
 * On failure: sends a 403 and exits. Never silently continues.
 */
function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    // hash_equals is constant-time: prevents timing attacks on token comparison.
    if (!$submitted || !$expected || !hash_equals($expected, $submitted)) {
        // Log the violation for audit trail
        error_log('[CSRF VIOLATION] IP=' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
            . ' URI=' . ($_SERVER['REQUEST_URI'] ?? 'unknown'));

        http_response_code(403);
        // Return JSON if it looks like an AJAX request, otherwise plain text
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'CSRF token mismatch.']);
        } else {
            echo 'Security error: invalid CSRF token. Please go back and try again.';
        }
        exit;
    }

    // Rotate token after each successful verification to prevent token-reuse attacks.
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
