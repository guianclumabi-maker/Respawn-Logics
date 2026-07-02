<?php
/**
 * Two-Factor Authentication (TOTP) enrollment page.
 * Self-contained, server-rendered (same pattern as forgot-password.php).
 * Phase A: a LOGGED-IN user voluntarily enables 2FA here.
 * (Phase B enforcement will route non-enrolled users here when their tenant requires 2FA.)
 *
 * Does NOT touch onboarding/register — this is purely the auth/login side.
 */
require_once __DIR__ . '/bootstrap/app.php';
require_once __DIR__ . '/services/TotpService.php';

// Require a logged-in user (or a pending forced-setup user id set by the login flow later).
$userId = $_SESSION['user_id'] ?? ($_SESSION['2fa_setup_user_id'] ?? null);
if (empty($userId)) {
    header('Location: ' . url('/frontend/dist/index.html#/login'));
    exit;
}

$stmt = $pdo->prepare("SELECT `id`, `email`, `full_name`, `tenant_id`, `theme_preference`, `totp_enabled` FROM `users` WHERE `id` = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    header('Location: ' . url('/frontend/dist/index.html#/login'));
    exit;
}

$error = '';
$success = false;
$recoveryCode = '';

// Keep a pending secret in the session until the user confirms a valid code.
if (empty($_SESSION['2fa_setup_secret'])) {
    $_SESSION['2fa_setup_secret'] = TotpService::generateSecret();
}
$secret = $_SESSION['2fa_setup_secret'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_2fa') {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh and try again.';
    } else {
        $code = preg_replace('/\s+/', '', $_POST['code'] ?? '');
        if (TotpService::verify($secret, $code)) {
            try {
                // Generate a one-time recovery code (shown once, stored hashed).
                $recoveryCode = strtoupper(bin2hex(random_bytes(5))); // 10 hex chars
                $recoveryHash = password_hash($recoveryCode, PASSWORD_BCRYPT);

                $up = $pdo->prepare(
                    "INSERT INTO `totp_secrets` (`user_id`, `secret`, `totp_enabled`, `recovery_hash`)
                     VALUES (?, ?, 1, ?)
                     ON DUPLICATE KEY UPDATE `secret` = VALUES(`secret`), `totp_enabled` = 1, `recovery_hash` = VALUES(`recovery_hash`)"
                );
                $up->execute([$user['id'], $secret, $recoveryHash]);
                $pdo->prepare("UPDATE `users` SET `totp_enabled` = 1 WHERE `id` = ?")->execute([$user['id']]);

                // Consumed — clear the pending secret.
                unset($_SESSION['2fa_setup_secret']);

                // If this was a forced setup (user not yet fully logged in), establish the session now.
                if (empty($_SESSION['user_id'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']          = $user['id'];
                    $_SESSION['user_email']       = $user['email'];
                    $_SESSION['user_name']        = $user['full_name'];
                    $_SESSION['tenant_id']        = $user['tenant_id'];
                    $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'dark';
                    unset($_SESSION['2fa_setup_user_id']);
                }

                $success = true;
            } catch (\Throwable $e) {
                error_log('[setup_2fa] ' . $e->getMessage());
                $error = 'Could not enable 2FA right now. Please try again.';
            }
        } else {
            $error = 'That code was incorrect or expired. Check that your device clock is accurate, then try again.';
        }
    }
}

$otpUri  = TotpService::getOtpAuthUri($secret, $user['email'], 'Respawn Logics');
$qrUrl   = TotpService::getQrCodeUrl($otpUri, 200);
$csrf    = htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES);
$dashUrl = url('/frontend/dist/index.html?v=' . time() . '#/dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Up Two-Factor Authentication — Respawn Logics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', sans-serif; background: #070a12; color: #fff; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 16px; }
        .glow1 { position: fixed; top: -200px; left: -150px; width: 700px; height: 700px; border-radius: 50%; background: #00e07a; filter: blur(160px); opacity: .05; pointer-events: none; }
        .glow2 { position: fixed; bottom: -200px; right: -150px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: .06; pointer-events: none; }
        .wrap { position: relative; width: 100%; max-width: 440px; }
        .head { text-align: center; margin-bottom: 24px; }
        .logo { display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #00e07a, #00b8ff); box-shadow: 0 0 40px rgba(0,224,122,.4); margin-bottom: 14px; }
        .logo i { color: #000; font-size: 22px; }
        h1 { font-family: 'Space Grotesk', sans-serif; font-size: 22px; font-weight: 700; }
        .sub { color: #94a3b8; font-size: 14px; margin-top: 4px; }
        .card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 30px; box-shadow: 0 0 60px rgba(0,0,0,.5); }
        .step { color: #94a3b8; font-size: 13px; line-height: 1.6; margin-bottom: 16px; }
        .qr { background: #fff; padding: 12px; border-radius: 12px; width: 224px; margin: 0 auto 16px; display: block; }
        .key { font-family: 'JetBrains Mono', monospace; font-size: 13px; letter-spacing: 1px; color: #00e07a; background: rgba(0,224,122,.08); border: 1px solid rgba(0,224,122,.2); border-radius: 8px; padding: 10px; text-align: center; word-break: break-all; margin-bottom: 20px; }
        label { display: block; font-size: 11px; font-weight: 600; color: #94a3b8; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
        input[type=text] { width: 100%; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); border-radius: 12px; padding: 12px 16px; color: #fff; font-size: 18px; letter-spacing: 6px; text-align: center; font-family: 'JetBrains Mono', monospace; outline: none; }
        input[type=text]:focus { border-color: rgba(0,224,122,.5); box-shadow: 0 0 0 3px rgba(0,224,122,.12); }
        button { width: 100%; padding: 12px; border: none; border-radius: 12px; font-weight: 600; font-size: 14px; color: #000; cursor: pointer; margin-top: 18px; background: linear-gradient(135deg, #00e07a, #00b8ff); box-shadow: 0 0 30px rgba(0,224,122,.3); font-family: inherit; }
        .banner { padding: 12px 14px; border-radius: 10px; font-size: 13px; margin-bottom: 18px; display: flex; gap: 8px; align-items: flex-start; }
        .banner.error { background: rgba(239,68,68,.1); border: 1px solid rgba(239,68,68,.25); color: #f87171; }
        .banner.ok { background: rgba(0,224,122,.1); border: 1px solid rgba(0,224,122,.25); color: #34d399; }
        .success-icon { width: 64px; height: 64px; border-radius: 50%; background: rgba(0,224,122,.1); border: 1px solid rgba(0,224,122,.4); display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; }
        .success-icon i { color: #00e07a; font-size: 28px; }
        .recovery { font-family: 'JetBrains Mono', monospace; font-size: 20px; letter-spacing: 3px; color: #fff; background: rgba(255,255,255,.05); border: 1px dashed rgba(0,224,122,.4); border-radius: 10px; padding: 14px; text-align: center; margin: 14px 0; }
        .warn { color: #fbbf24; font-size: 12px; line-height: 1.5; }
        .foot { text-align: center; margin-top: 20px; }
        .foot a { color: #00e07a; font-size: 14px; text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="glow1"></div>
    <div class="glow2"></div>
    <div class="wrap">
        <div class="head">
            <div class="logo"><i class="fa-solid fa-shield-halved"></i></div>
            <h1>Two-Factor Authentication</h1>
            <p class="sub">Add an extra layer of security to your account</p>
        </div>
        <div class="card">
            <?php if ($success): ?>
                <div class="success-icon"><i class="fa-solid fa-circle-check"></i></div>
                <div class="banner ok"><span>Two-factor authentication is now enabled on your account.</span></div>
                <p class="step"><strong>Save your recovery code.</strong> If you lose access to your authenticator app, this one-time code lets you regain access. It will not be shown again.</p>
                <div class="recovery"><?= htmlspecialchars($recoveryCode) ?></div>
                <p class="warn"><i class="fa-solid fa-triangle-exclamation"></i> Store this somewhere safe now.</p>
                <div class="foot"><a href="<?= htmlspecialchars($dashUrl) ?>"><i class="fa-solid fa-arrow-right"></i>&nbsp; Continue to dashboard</a></div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="banner error"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars($error) ?></span></div>
                <?php endif; ?>
                <p class="step"><strong>1.</strong> Open an authenticator app (Google Authenticator, Authy, Microsoft Authenticator) and scan this QR code:</p>
                <img class="qr" src="<?= htmlspecialchars($qrUrl) ?>" alt="2FA QR code" width="200" height="200">
                <p class="step"><strong>Can't scan?</strong> Enter this key manually:</p>
                <div class="key"><?= htmlspecialchars($secret) ?></div>
                <p class="step"><strong>2.</strong> Enter the 6-digit code from your app to confirm:</p>
                <form method="POST" action="setup_2fa.php">
                    <input type="hidden" name="action" value="confirm_2fa">
                    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                    <label for="code">Verification code</label>
                    <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]*" placeholder="000000" required autofocus>
                    <button type="submit"><i class="fa-solid fa-lock"></i>&nbsp; Enable 2FA</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
