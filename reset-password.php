<?php
require_once __DIR__ . '/bootstrap/app.php';

// Self-healing table (in case this instance never served forgot-password.php)
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

$token = $_POST['token'] ?? ($_GET['token'] ?? '');
$error = '';
$success = false;
$validToken = false;
$userId = null;

// Validate the token (exists, unused, not expired) using MySQL's clock
if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT user_id, used_at FROM password_reset_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && $row['used_at'] === null) {
        $validToken = true;
        $userId = (int)$row['user_id'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_password') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } elseif (!$validToken) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $pdo->prepare("UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?")
                    ->execute([$hash, $userId]);
                // Single-use: consume this token and any other outstanding ones for the user
                $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                    ->execute([$userId]);
                $pdo->commit();
                $success = true;
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('[reset-password] ' . $e->getMessage());
                $error = 'Something went wrong while resetting your password. Please try again.';
            }
        }
    }
}

$loginUrl = url('/frontend/dist/index.html#/login');
$forgotUrl = url('/forgot-password.php');
$csrf = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
$safeToken = htmlspecialchars($token, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — Respawn Logics</title>
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
        label { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; margin-top: 16px; }
        label:first-of-type { margin-top: 0; }
        input[type=password] { width: 100%; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 12px 16px; color: #fff; font-size: 14px; outline: none; transition: all .2s; }
        input[type=password]:focus { border-color: rgba(0,224,122,.5); box-shadow: 0 0 0 3px rgba(0,224,122,.12); }
        button { width: 100%; padding: 12px; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; color: #000; cursor: pointer; margin-top: 22px; background: linear-gradient(135deg, #00e07a, #00b8ff); box-shadow: 0 0 30px rgba(0,224,122,.3); font-family: inherit; }
        .banner { padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; display: flex; gap: 8px; align-items: flex-start; }
        .banner.error { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #f87171; }
        .banner.ok { background: rgba(0,224,122,.1); border: 1px solid rgba(0,224,122,.25); color: #34d399; }
        .desc { color: #94a3b8; font-size: 13px; line-height: 1.6; margin-bottom: 6px; }
        .foot { text-align: center; margin-top: 22px; }
        .foot a { color: #94a3b8; font-size: 13px; text-decoration: none; }
        .foot a:hover { color: #00e07a; }
        .success-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(0,224,122,.1); border: 1px solid rgba(0,224,122,.4); display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
        .success-icon i { color: #00e07a; font-size: 28px; }
    </style>
</head>
<body>
    <div class="glow1"></div>
    <div class="glow2"></div>
    <div class="wrap">
        <div class="head">
            <div class="logo"><i class="fa-solid fa-gamepad"></i></div>
            <h1>Respawn Logics</h1>
            <p class="sub">Set a new password</p>
        </div>
        <div class="card">
            <?php if ($success): ?>
                <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="banner ok"><span>Your password has been reset. You can now sign in with your new password.</span></div>
                <div class="foot"><a href="<?= htmlspecialchars($loginUrl) ?>"><i class="fa-solid fa-right-to-bracket"></i>&nbsp; Go to sign in</a></div>
            <?php elseif (!$validToken): ?>
                <div class="banner error"><i class="fa-solid fa-triangle-exclamation"></i><span>This reset link is invalid or has expired.</span></div>
                <div class="foot"><a href="<?= htmlspecialchars($forgotUrl) ?>"><i class="fa-solid fa-rotate-right"></i>&nbsp; Request a new link</a></div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="banner error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>
                <p class="desc">Choose a new password for your account. Minimum 8 characters.</p>
                <form method="POST" action="reset-password.php">
                    <input type="hidden" name="action" value="set_password">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <input type="hidden" name="token" value="<?= $safeToken ?>">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="••••••••">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="••••••••">
                    <button type="submit"><i class="fa-solid fa-lock"></i>&nbsp; Reset password</button>
                </form>
                <div class="foot"><a href="<?= htmlspecialchars($loginUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Back to sign in</a></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
