<?php
/**
 * pages/setup_2fa.php
 *
 * Opt-in TOTP 2FA enrollment page.
 * Users arrive here from their profile/security settings.
 *
 * Flow:
 *  Step 1 — Generate a secret, show QR code. User scans with authenticator app.
 *  Step 2 — User submits a 6-digit code to prove setup worked.
 *  Step 3 — On valid code: secret saved, totp_enabled = 1 on users table.
 *
 * Security:
 *  - Requires active login (requireLogin())
 *  - CSRF on all POST forms
 *  - Code verified server-side via TotpService::verify() before enabling
 *  - Secret not persisted until the user proves they can generate a valid code
 */
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../services/TotpService.php';
requireLogin();

$user    = getCurrentUser();
$userId  = (int) $_SESSION['user_id'];
$email   = $user['email'];
$error   = '';
$success = '';

// Fetch existing 2FA status
$stmtCheck = $pdo->prepare("SELECT secret, totp_enabled FROM totp_secrets WHERE user_id = ?");
$stmtCheck->execute([$userId]);
$existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
$isEnabled = !empty($existing['totp_enabled']);

// ── Disable 2FA ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'disable_2fa') {
    csrf_verify();
    $pdo->prepare("UPDATE totp_secrets SET totp_enabled = 0 WHERE user_id = ?")
        ->execute([$userId]);
    $pdo->prepare("UPDATE users SET totp_enabled = 0 WHERE id = ?")
        ->execute([$userId]);
    $success = '2FA has been disabled on your account.';
    $isEnabled = false;
    $existing = null;
}

// ── Begin Setup: Generate secret ─────────────────────────────────────────────
// We store the pending secret in the session until the user verifies a code.
// This prevents saving an unverified secret to the DB.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'begin_setup') {
    csrf_verify();
    $_SESSION['totp_pending_secret'] = TotpService::generateSecret();
}

$pendingSecret = $_SESSION['totp_pending_secret'] ?? null;

// ── Verify and Activate ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'verify_setup') {
    csrf_verify();
    $totpCode = trim($_POST['totp_code'] ?? '');
    $secret   = $_SESSION['totp_pending_secret'] ?? null;

    if (!$secret) {
        $error = 'Setup session expired. Please start again.';
    } elseif (!TotpService::verify($secret, $totpCode)) {
        $error = 'Invalid code. Make sure your authenticator app is set up correctly and try again.';
    } else {
        // Code verified — persist the secret and enable 2FA
        $pdo->beginTransaction();
        try {
            // Upsert: insert or update existing row
            $pdo->prepare(
                "INSERT INTO totp_secrets (user_id, secret, totp_enabled) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE secret = VALUES(secret), totp_enabled = 1"
            )->execute([$userId, $secret]);

            // Set flag on users table for fast login check
            $pdo->prepare("UPDATE users SET totp_enabled = 1 WHERE id = ?")
                ->execute([$userId]);

            $pdo->commit();
            unset($_SESSION['totp_pending_secret']);
            $success  = '2FA has been successfully enabled on your account!';
            $isEnabled = true;

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = 'Failed to save 2FA settings. Please try again.';
            error_log('[2FA Setup] DB Error: ' . $e->getMessage());
        }
    }
}

// Prepare QR code if we have a pending secret
$qrUrl = null;
if ($pendingSecret) {
    $otpUri = TotpService::getOtpAuthUri($pendingSecret, $email);
    $qrUrl  = TotpService::getQrCodeUrl($otpUri, 220);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Settings – Two-Factor Authentication | Respawn Logics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/assets/css/styles.css') ?>">
    <style>
        .setup-card {
            max-width: 520px;
            margin: 60px auto;
            background: #0f1422;
            border: 1px solid rgba(0, 224, 122, 0.15);
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.06);
        }
        .qr-box {
            background: #fff;
            border-radius: 12px;
            padding: 12px;
            display: inline-block;
            line-height: 0;
            box-shadow: 0 0 20px rgba(255,255,255,0.1);
        }
        .badge-enabled  { background: rgba(0,224,122,0.1); color: #00e07a; border: 1px solid rgba(0,224,122,0.3); padding: 4px 12px; border-radius: 100px; font-size: 12px; font-family: monospace; }
        .badge-disabled { background: rgba(255,80,80,0.1); color: #ff5050; border: 1px solid rgba(255,80,80,0.3); padding: 4px 12px; border-radius: 100px; font-size: 12px; font-family: monospace; }
        .step-num { width: 28px; height: 28px; border-radius: 50%; background: rgba(0,224,122,0.1); border: 1px solid rgba(0,224,122,0.3); color: #00e07a; font-family: monospace; font-size: 13px; display: inline-flex; align-items: center; justify-content: center; margin-right: 10px; flex-shrink: 0; }
        .mono-code { font-family: 'JetBrains Mono', monospace; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding: 8px 14px; font-size: 13px; color: #00e07a; word-break: break-all; letter-spacing: 0.1em; }
    </style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="main-content" style="padding: 20px;">

<div class="setup-card">

    <!-- Header -->
    <div style="display:flex; align-items:center; gap: 14px; margin-bottom: 32px;">
        <div style="width:48px;height:48px;border-radius:12px;background:rgba(0,224,122,0.1);border:1px solid rgba(0,224,122,0.2);display:flex;align-items:center;justify-content:center;font-size:22px;color:#00e07a;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <h1 style="font-family:'Space Grotesk',sans-serif;font-size:20px;font-weight:700;color:#fff;margin:0;">Two-Factor Authentication</h1>
            <p style="font-family:monospace;font-size:12px;color:#5e6a82;margin:0;">// TOTP · RFC 6238 · Google Authenticator compatible</p>
        </div>
        <div style="margin-left:auto;">
            <span class="<?= $isEnabled ? 'badge-enabled' : 'badge-disabled' ?>">
                <?= $isEnabled ? '// ACTIVE' : '// INACTIVE' ?>
            </span>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($error): ?>
    <div style="background:rgba(255,80,80,0.08);border:1px solid rgba(255,80,80,0.2);border-radius:8px;padding:14px 16px;margin-bottom:24px;color:#ff8080;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div style="background:rgba(0,224,122,0.08);border:1px solid rgba(0,224,122,0.2);border-radius:8px;padding:14px 16px;margin-bottom:24px;color:#00e07a;font-size:13px;display:flex;align-items:center;gap:10px;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($isEnabled): ?>
    <!-- ── 2FA is ENABLED ─────────────────────────────────────────────────── -->
    <div style="text-align:center;padding:24px 0;">
        <i class="fa-solid fa-shield-check" style="font-size:48px;color:#00e07a;margin-bottom:16px;display:block;filter:drop-shadow(0 0 12px rgba(0,224,122,0.4));"></i>
        <p style="color:#c8d0e0;font-size:14px;margin-bottom:24px;">Your account is protected with TOTP two-factor authentication.<br>Every login requires a code from your authenticator app.</p>
    </div>
    <form method="POST" action="setup_2fa.php" onsubmit="return confirm('Are you sure you want to disable 2FA? This will make your account less secure.');">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="disable_2fa">
        <button type="submit" style="width:100%;background:rgba(255,80,80,0.1);color:#ff5050;border:1px solid rgba(255,80,80,0.2);font-family:'JetBrains Mono',monospace;font-size:13px;padding:12px;border-radius:8px;cursor:pointer;transition:all 0.2s;">
            <i class="fa-solid fa-shield-xmark"></i> Disable Two-Factor Authentication
        </button>
    </form>

    <?php elseif ($pendingSecret && !$success): ?>
    <!-- ── Step 2: Verify code ────────────────────────────────────────────── -->
    <div style="margin-bottom:28px;">
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;">
            <span class="step-num">1</span>
            <p style="color:#c8d0e0;font-size:13px;margin:0;">Install <strong style="color:#fff">Google Authenticator</strong>, <strong style="color:#fff">Authy</strong>, or any TOTP app on your phone.</p>
        </div>
        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:20px;">
            <span class="step-num">2</span>
            <p style="color:#c8d0e0;font-size:13px;margin:0;">Scan the QR code below, or enter the secret key manually.</p>
        </div>
        <div style="text-align:center;margin-bottom:16px;">
            <div class="qr-box">
                <img src="<?= htmlspecialchars($qrUrl) ?>" width="220" height="220" alt="TOTP QR Code" style="display:block;">
            </div>
        </div>
        <p style="font-size:12px;color:#5e6a82;text-align:center;margin-bottom:8px;">Or enter this secret key manually:</p>
        <div class="mono-code" style="text-align:center;margin-bottom:24px;"><?= htmlspecialchars($pendingSecret) ?></div>

        <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;">
            <span class="step-num">3</span>
            <p style="color:#c8d0e0;font-size:13px;margin:0;">Enter the 6-digit code shown in your app to verify setup.</p>
        </div>
    </div>

    <form method="POST" action="setup_2fa.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="verify_setup">
        <div style="margin-bottom:20px;">
            <label style="font-family:monospace;font-size:11px;color:#5e6a82;text-transform:uppercase;letter-spacing:0.1em;display:block;margin-bottom:8px;">// Verification_Code</label>
            <input type="text" name="totp_code" maxlength="6" pattern="[0-9]{6}" inputmode="numeric" autocomplete="one-time-code" required placeholder="000000" autofocus
                style="width:100%;background:#0b0f1a;border:1px solid rgba(255,255,255,0.1);color:#00e07a;font-family:'JetBrains Mono',monospace;font-size:28px;letter-spacing:0.5em;text-align:center;border-radius:8px;padding:16px;outline:none;box-sizing:border-box;">
        </div>
        <button type="submit" style="width:100%;background:#00e07a;color:#000;font-family:'JetBrains Mono',monospace;font-weight:700;padding:14px;border-radius:8px;border:none;cursor:pointer;font-size:14px;letter-spacing:0.05em;">
            [ VERIFY &amp; ACTIVATE 2FA ]
        </button>
    </form>
    <form method="POST" action="setup_2fa.php" style="margin-top:10px;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="begin_setup">
        <button type="submit" style="width:100%;background:transparent;color:#5e6a82;border:none;font-family:monospace;font-size:12px;padding:8px;cursor:pointer;">↺ Generate a new QR code</button>
    </form>

    <?php else: ?>
    <!-- ── Step 1: Start Setup ─────────────────────────────────────────────── -->
    <p style="color:#8b95a8;font-size:14px;line-height:1.6;margin-bottom:28px;">
        Add an extra layer of security. After enabling 2FA, you'll need your authenticator app every time you log in — even if someone steals your password.
    </p>
    <div style="background:rgba(0,224,122,0.04);border:1px solid rgba(0,224,122,0.1);border-radius:8px;padding:16px;margin-bottom:28px;">
        <p style="color:#c8d0e0;font-size:13px;margin:0;"><i class="fa-solid fa-circle-info" style="color:#00e07a;margin-right:8px;"></i>Compatible with <strong>Google Authenticator</strong>, <strong>Authy</strong>, <strong>Microsoft Authenticator</strong>, and all RFC 6238 TOTP apps.</p>
    </div>
    <form method="POST" action="setup_2fa.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="begin_setup">
        <button type="submit" style="width:100%;background:#00e07a;color:#000;font-family:'JetBrains Mono',monospace;font-weight:700;padding:14px;border-radius:8px;border:none;cursor:pointer;font-size:14px;letter-spacing:0.05em;">
            <i class="fa-solid fa-shield-plus"></i> [ ENABLE 2FA ]
        </button>
    </form>
    <?php endif; ?>

    <div style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.05);text-align:center;">
        <a href="<?= url('/pages/dashboard.php') ?>" style="font-family:monospace;font-size:12px;color:#5e6a82;text-decoration:none;">← Back to Dashboard</a>
    </div>

</div>
</div>
</body>
</html>
