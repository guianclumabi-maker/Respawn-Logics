<?php
require_once __DIR__ . '/bootstrap/app.php';

$message = '';
$error = '';

// Idempotent, self-healing table (kept separate from onboarding's user_activation_tokens)
$pdo->exec("CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `token`      VARCHAR(128) NOT NULL UNIQUE,
    `expires_at` DATETIME     NOT NULL,
    `used_at`    DATETIME     NULL DEFAULT NULL,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_prt_token` (`token`),
    INDEX `idx_prt_user`  (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'request_reset') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Invalidate any previous unused tokens for this user
                    $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                        ->execute([$user['id']]);

                    $token = bin2hex(random_bytes(32));
                    $pdo->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                        ->execute([$user['id'], $token]);

                    $resetLink = url('/reset-password.php?token=' . $token);
                    $safeName = htmlspecialchars($user['full_name'] ?? '', ENT_QUOTES);
                    $html = "<p>Hi {$safeName},</p>"
                          . "<p>We received a request to reset your Respawn Logics password. "
                          . "Click the button below to choose a new one. This link expires in 1 hour.</p>"
                          . "<p><a href=\"{$resetLink}\" style=\"display:inline-block;padding:12px 20px;background:#00e07a;color:#000;text-decoration:none;border-radius:6px;font-weight:bold\">Reset my password</a></p>"
                          . "<p>Or paste this link into your browser:<br><span style=\"color:#555\">{$resetLink}</span></p>"
                          . "<p>If you didn't request this, you can safely ignore this email — your password won't change.</p>";
                    try {
                        require_once __DIR__ . '/backend/services/Mailer.php';
                        Mailer::send($user['email'], $user['full_name'], 'Reset your Respawn Logics password', $html);
                    } catch (\Throwable $mailEx) {
                        // Do not reveal mail failures to the user; log for ops.
                        error_log('[forgot-password] Mail send failed: ' . $mailEx->getMessage());
                    }
                }

                // Always the same response — never reveal whether an account exists.
                $message = 'If an account exists for that email, a password reset link has been sent. Please check your inbox (and spam folder).';
            } catch (\Throwable $e) {
                error_log('[forgot-password] ' . $e->getMessage());
                $message = 'If an account exists for that email, a password reset link has been sent. Please check your inbox (and spam folder).';
            }
        }
    }
}

$loginUrl = url('/frontend/dist/index.html#/login');
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password — Respawn Logics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #070a12; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .glow1 { position: fixed; top: -200px; left: -150px; width: 700px; height: 700px; border-radius: 50%; background: #00e07a; filter: blur(160px); opacity: .05; pointer-events: none; }
        .glow2 { position: fixed; bottom: -200px; right: -150px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: .06; pointer-events: none; }
        .wrap { position: relative; width: 100%; max-width: 420px; }
        .head { text-align: center; margin-bottom: 28px; }
        .logo { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #00e07a, #00b8ff); box-shadow: 0 0 40px rgba(0,224,122,.4); margin-bottom: 14px; }
        .logo i { color: #000; font-size: 22px; }
        h1 { font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; }
        .sub { color: #94a3b8; font-size: 14px; margin-top: 4px; }
        .card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 32px; box-shadow: 0 0 60px rgba(0,0,0,.5); }
        label { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        input[type=email] { width: 100%; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none; transition: all .2s; }
        input[type=email]:focus { border-color: rgba(0,224,122,.5); box-shadow: 0 0 0 3px rgba(0,224,122,.12); }
        button { width: 100%; padding: 12px; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; color: #000; cursor: pointer; margin-top: 20px; background: linear-gradient(135deg, #00e07a, #00b8ff); box-shadow: 0 0 30px rgba(0,224,122,.3); font-family: inherit; }
        .banner { padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; display: flex; gap: 8px; align-items: flex-start; }
        .banner.error { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #f87171; }
        .banner.ok { background: rgba(0,224,122,.1); border: 1px solid rgba(0,224,122,.25); color: #34d399; }
        .desc { color: #94a3b8; font-size: 13px; line-height: 1.6; margin-bottom: 22px; }
        .foot { text-align: center; margin-top: 22px; }
        .foot a { color: #94a3b8; font-size: 13px; text-decoration: none; }
        .foot a:hover { color: #00e07a; }
    </style>
</head>
<body>
    <div class="glow1"></div>
    <div class="glow2"></div>
    <div class="wrap">
        <div class="head">
            <div class="logo"><i class="fa-solid fa-gamepad"></i></div>
            <h1>Respawn Logics</h1>
            <p class="sub">Reset your password</p>
        </div>
        <div class="card">
            <?php if (!empty($message)): ?>
                <div class="banner ok"><i class="fa-solid fa-circle-check"></i><span><?= htmlspecialchars($message) ?></span></div>
                <div class="foot"><a href="<?= htmlspecialchars($loginUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Back to sign in</a></div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="banner error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>
                <p class="desc">Enter the email associated with your account and we'll send you a link to reset your password.</p>
                <form method="POST" action="forgot-password.php">
                    <input type="hidden" name="action" value="request_reset">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" required placeholder="you@company.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email'], ENT_QUOTES) : '' ?>">
                    <button type="submit"><i class="fa-solid fa-paper-plane"></i>&nbsp; Send reset link</button>
                </form>
                <div class="foot"><a href="<?= htmlspecialchars($loginUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Back to sign in</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
