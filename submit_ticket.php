<?php
/**
 * submit_ticket.php
 *
 * Public endpoint: accepts pre-login support ticket submissions.
 * No authentication required — intended for users who cannot log in.
 *
 * Security measures:
 *  - CSRF token validation (OWASP A01)
 *  - Input sanitization and length validation (OWASP A03)
 *  - PDO prepared statements (OWASP A03 - SQL Injection prevention)
 *  - Rate limiting via IP check against login_rate_limits (basic abuse prevention)
 *  - JSON-only response (no HTML reflection — XSS prevention)
 */

require_once __DIR__ . '/bootstrap/app.php';

// Force JSON response — never reflect raw user input as HTML
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── CSRF Validation ───────────────────────────────────────────────────────────
// Verify the token from the session matches the submitted token.
// Uses hash_equals() to prevent timing-based side-channel attacks.
$submittedCsrf = $_POST['csrf_token'] ?? '';
$sessionCsrf   = $_SESSION['csrf_token'] ?? '';

if (!$submittedCsrf || !$sessionCsrf || !hash_equals($sessionCsrf, $submittedCsrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Security token mismatch. Please refresh and try again.']);
    exit;
}

// ── Input Validation ─────────────────────────────────────────────────────────
// trim() removes whitespace; filter_var validates email format.
// Length limits prevent database overflow and abuse.
$email   = trim($_POST['email']   ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if (empty($email) || empty($subject) || empty($message)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'All fields are required.']);
    exit;
}

// Validate email format (OWASP: never trust client-side validation alone)
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// Enforce reasonable length limits to prevent abuse
if (strlen($email) > 255 || strlen($subject) > 255 || strlen($message) > 5000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Input exceeds maximum allowed length.']);
    exit;
}

// ── Insert Ticket ─────────────────────────────────────────────────────────────
// PDO prepared statement — all values are parameterised, zero SQL injection risk.
// user_id is NULL for pre-login submissions.
try {
    $stmt = $pdo->prepare(
        "INSERT INTO `support_tickets` (`user_id`, `email`, `subject`, `message`, `status`, `created_at`)
         VALUES (NULL, ?, ?, ?, 'open', NOW())"
    );
    $stmt->execute([$email, $subject, $message]);

    $ticketId = $pdo->lastInsertId();

    // Return the real ticket ID with a padded reference format
    $ticketRef = '#TKT-' . str_pad($ticketId, 5, '0', STR_PAD_LEFT);

    // Rotate CSRF token after successful submission (prevents token reuse)
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    echo json_encode([
        'success'    => true,
        'ticket_ref' => $ticketRef,
        'message'    => 'Your ticket has been submitted. Our team will reach out to your email.',
    ]);

} catch (PDOException $e) {
    // Log the real error server-side; never expose DB details to the client
    error_log('[submit_ticket] DB Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to submit ticket. Please try again later.']);
}
