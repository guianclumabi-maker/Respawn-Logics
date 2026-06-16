<?php
require_once __DIR__ . '/bootstrap/app.php';

// If already logged in normally, redirect to dashboard
if (isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true)) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$error = '';
$showChangePasswordModal = false;
$activationToken = $_GET['activation_token'] ?? ($_POST['activation_token'] ?? null);
$activatingUserId = null;

if ($activationToken) {
    try {
        $stmtToken = $pdo->prepare("SELECT user_id FROM user_activation_tokens WHERE token = ? AND used_at IS NULL AND expires_at > NOW()");
        $stmtToken->execute([$activationToken]);
        $uid = $stmtToken->fetchColumn();
        if ($uid) {
            $showChangePasswordModal = true;
            $activatingUserId = $uid;
        } else {
            $error = 'Invalid or expired activation link.';
            $showChangePasswordModal = false;
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// 1. Process standard login submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Rate Limiting
    if (!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if (!isset($_SESSION['last_login_attempt'])) $_SESSION['last_login_attempt'] = time();
    
    if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_login_attempt']) < 60) {
        $error = 'Too many failed login attempts. Please wait 60 seconds.';
    } else {
        if (time() - $_SESSION['last_login_attempt'] >= 60) {
            $_SESSION['login_attempts'] = 0;
        }
        $_SESSION['last_login_attempt'] = time();

        if (empty($email) || empty($password)) {
            $error = 'Please fill in all fields.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                    session_regenerate_id(true); // Prevent Session Fixation
                    $_SESSION['login_attempts'] = 0; // Reset on success
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['user_name'] = $user['full_name'];
                    $_SESSION['tenant_id'] = $user['tenant_id'];
                    $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'light';
                    
                    header('Location: ' . url('/pages/dashboard.php'));
                    exit;
                } else {
                    $_SESSION['login_attempts']++;
                    $error = 'Invalid email or password.';
                }
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// 2. Process account activation submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'activate_account') {
    if ($showChangePasswordModal && $activatingUserId) {
        $newPass = $_POST['new_password'];
        $confirmPass = $_POST['confirm_password'];
        
        if (strlen($newPass) < 8) {
            $error = 'Password must be at least 8 characters long.';
        } else if ($newPass !== $confirmPass) {
            $error = 'Passwords do not match.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $newHash = password_hash($newPass, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE `users` SET `password_hash` = ? WHERE `id` = ?");
                $stmt->execute([$newHash, $activatingUserId]);
                
                $stmtTok = $pdo->prepare("UPDATE `user_activation_tokens` SET `used_at` = NOW() WHERE `token` = ?");
                $stmtTok->execute([$activationToken]);
                
                $stmtUser = $pdo->prepare("SELECT * FROM `users` WHERE `id` = ?");
                $stmtUser->execute([$activatingUserId]);
                $user = $stmtUser->fetch();
                
                $pdo->commit();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['full_name'];
                $_SESSION['tenant_id'] = $user['tenant_id'];
                $_SESSION['theme_preference'] = $user['theme_preference'] ?? 'light';
                
                header('Location: ' . url('/pages/dashboard.php'));
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $error = 'Failed to activate account: ' . $e->getMessage();
            }
        }
    } else {
        $error = 'Invalid or expired activation session.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Respawn Logic HRIS</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/assets/css/styles.css?v=' . time()) ?>">
    <style>
        /* Modals and layouts helpers */
        .hidden {
            display: none !important;
        }
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 11, 16, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-card {
            width: 100%;
            max-width: 440px;
            animation: modalFadeIn 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        @keyframes modalFadeIn {
            from { transform: scale(0.92); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        /* Custom styles for standalone layout */
        .onboarding-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            min-height: 100vh;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Global Nav -->
        <a href="index.php" style="position: fixed; top: 32px; left: 32px; color: #8b95a8; font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 8px; z-index: 100; transition: all 0.2s;" onmouseover="this.style.color='#00e07a'" onmouseout="this.style.color='#8b95a8'">
            <i class="fa-solid fa-arrow-left"></i> [ ABORT ]
        </a>

        <!-- Ambient Background Blobs -->
        <div class="ambient-blob blob-1"></div>
        <div class="ambient-blob blob-2"></div>
        <div class="ambient-blob blob-3"></div>

        <div class="onboarding-step" style="background: linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px); background-size: 40px 40px;">
            <div class="card onboarding-card login-card" style="background: #0f1422; border: 1px solid rgba(0, 224, 122, 0.2); box-shadow: 0 0 40px rgba(0, 224, 122, 0.1); border-radius: 4px; position: relative;">
                <div class="onboarding-header">
                    <div style="display: inline-block; padding: 4px 12px; margin-bottom: 16px; border-radius: 100px; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); color: #8b95a8; font-size: 12px; font-family: 'JetBrains Mono', monospace;">// SECURE_GATEWAY</div>
                    <?= renderLogo('centered') ?>
                    <h1 class="text-center" style="font-family: 'Space Grotesk', sans-serif; font-size: 32px; font-weight: 700; color: #fff; margin-bottom: 8px;">System Authentication<span style="color: #00e07a; animation: blink 1s step-end infinite;">_</span></h1>
                    <p class="text-center" style="font-family: 'Space Grotesk', sans-serif; color: #8b95a8; font-size: 14px;">Establish a secure connection to the corporate matrix.</p>
                </div>

                <?php if (!empty($error) && !$showChangePasswordModal): ?>
                    <div class="error-banner">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="onboarding-form">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="login-email" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #5e6a82; display: block; margin-bottom: 8px;">// Email_Address</label>
                        <div class="input-wrapper" style="position: relative;">
                            <i class="fa-solid fa-envelope input-icon" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #5e6a82;"></i>
                            <input type="email" id="login-email" name="email" required placeholder="user@domain.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : (isset($_GET['email']) ? htmlspecialchars($_GET['email']) : '') ?>" style="width: 100%; background: #0b0f1a; border: 1px solid rgba(255,255,255,0.1); color: #00e07a; font-family: 'JetBrains Mono', monospace; border-radius: 4px; padding: 12px 16px 12px 42px; outline: none; transition: border-color 0.2s;">
                        </div>
                    </div>
                    
                    <div class="form-group" style="margin-bottom: 28px;">
                        <label for="login-password" style="font-family: 'JetBrains Mono', monospace; font-size: 11px; text-transform: uppercase; letter-spacing: 0.1em; color: #5e6a82; display: block; margin-bottom: 8px;">// Security_Key</label>
                        <div class="input-wrapper" style="position: relative;">
                            <i class="fa-solid fa-lock input-icon" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #5e6a82;"></i>
                            <input type="password" id="login-password" name="password" required placeholder="••••••••" style="width: 100%; background: #0b0f1a; border: 1px solid rgba(255,255,255,0.1); color: #00e07a; font-family: 'JetBrains Mono', monospace; border-radius: 4px; padding: 12px 16px 12px 42px; outline: none; transition: border-color 0.2s;">
                        </div>
                    </div>
                    
                    <button type="submit" class="w-full" style="background: #00e07a; color: #000; font-family: 'JetBrains Mono', monospace; font-weight: 700; letter-spacing: 0.05em; border-radius: 4px; box-shadow: 0 0 20px rgba(0, 224, 122, 0.4); border: none; padding: 14px; text-transform: uppercase; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        [ AUTHENTICATE_ ] <i class="fa-solid fa-arrow-right-to-bracket"></i>
                    </button>
                    
                    <div style="margin-top: 16px;">
                        <button type="button" id="open-qr-btn" class="w-full" style="background: transparent; color: #c8d0e0; border: 1px solid rgba(255,255,255,0.1); font-family: 'JetBrains Mono', monospace; font-size: 13px; font-weight: 600; padding: 12px; border-radius: 4px; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa-solid fa-qrcode"></i> [ QR_OVERRIDE ]
                        </button>
                    </div>
                    

                </form>
            </div>
        </div>
    </div>

    <!-- QR Login Modal (Empty Popup Placeholder) -->
    <div id="qr-modal" class="modal-backdrop hidden">
        <div class="card modal-card" style="position: relative;">
            <button type="button" class="close-modal-btn" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px; transition: color 0.2s;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--text-secondary)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <div class="onboarding-header text-center" style="margin-bottom: 20px;">
                <i class="fa-solid fa-qrcode" style="font-size: 40px; color: var(--accent-green); margin-bottom: 12px; filter: drop-shadow(0 0 10px rgba(0, 224, 122, 0.4));"></i>
                <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 6px;">Login via QR</h2>
                <p style="font-size: 13px; color: var(--text-secondary);">Scan the QR code with your mobile application to log in securely.</p>
            </div>
            
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 20px;">
                <div style="position: relative; width: 180px; height: 180px; background: #ffffff; border-radius: var(--radius-md); padding: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 0 20px rgba(255, 255, 255, 0.1); margin-bottom: 16px;">
                    <svg width="160" height="160" viewBox="0 0 29 29" fill="none" xmlns="http://www.w3.org/2000/svg" style="image-rendering: pixelated;">
                        <rect x="0" y="0" width="7" height="7" fill="#0d0f15" />
                        <rect x="1" y="1" width="5" height="5" fill="#ffffff" />
                        <rect x="2" y="2" width="3" height="3" fill="#0d0f15" />
                        
                        <rect x="22" y="0" width="7" height="7" fill="#0d0f15" />
                        <rect x="23" y="1" width="5" height="5" fill="#ffffff" />
                        <rect x="24" y="2" width="3" height="3" fill="#0d0f15" />
                        
                        <rect x="0" y="22" width="7" height="7" fill="#0d0f15" />
                        <rect x="1" y="23" width="5" height="5" fill="#ffffff" />
                        <rect x="2" y="24" width="3" height="3" fill="#0d0f15" />
                        
                        <rect x="20" y="20" width="5" height="5" fill="#0d0f15" />
                        <rect x="21" y="21" width="3" height="3" fill="#ffffff" />
                        <rect x="22" y="22" width="1" height="1" fill="#0d0f15" />
                        
                        <rect x="9" y="1" width="1" height="1" fill="#0d0f15" />
                        <rect x="11" y="0" width="2" height="1" fill="#0d0f15" />
                        <rect x="14" y="1" width="1" height="2" fill="#0d0f15" />
                        <rect x="17" y="0" width="1" height="1" fill="#0d0f15" />
                        <rect x="19" y="1" width="2" height="1" fill="#0d0f15" />
                        
                        <rect x="8" y="3" width="1" height="3" fill="#0d0f15" />
                        <rect x="10" y="4" width="3" height="1" fill="#0d0f15" />
                        <rect x="14" y="3" width="2" height="1" fill="#0d0f15" />
                        <rect x="18" y="4" width="1" height="2" fill="#0d0f15" />
                        <rect x="20" y="3" width="1" height="1" fill="#0d0f15" />
                        
                        <rect x="9" y="8" width="2" height="1" fill="#0d0f15" />
                        <rect x="13" y="7" width="1" height="2" fill="#0d0f15" />
                        <rect x="16" y="8" width="3" height="1" fill="#0d0f15" />
                        <rect x="21" y="7" width="1" height="3" fill="#0d0f15" />
                        <rect x="25" y="8" width="2" height="1" fill="#0d0f15" />
                        
                        <rect x="1" y="9" width="3" height="1" fill="#0d0f15" />
                        <rect x="5" y="8" width="1" height="3" fill="#0d0f15" />
                        <rect x="7" y="10" width="2" height="2" fill="#0d0f15" />
                        <rect x="11" y="10" width="1" height="1" fill="#0d0f15" />
                        <rect x="14" y="11" width="3" height="1" fill="#0d0f15" />
                        <rect x="19" y="10" width="1" height="2" fill="#0d0f15" />
                        <rect x="23" y="11" width="2" height="1" fill="#0d0f15" />
                        
                        <rect x="0" y="14" width="2" height="1" fill="#0d0f15" />
                        <rect x="3" y="13" width="1" height="3" fill="#0d0f15" />
                        <rect x="6" y="14" width="2" height="1" fill="#0d0f15" />
                        <rect x="9" y="13" width="3" height="2" fill="#0d0f15" />
                        <rect x="13" y="14" width="1" height="1" fill="#0d0f15" />
                        <rect x="16" y="13" width="2" height="3" fill="#0d0f15" />
                        <rect x="20" y="14" width="1" height="1" fill="#0d0f15" />
                        <rect x="22" y="13" width="3" height="1" fill="#0d0f15" />
                        <rect x="27" y="14" width="1" height="2" fill="#0d0f15" />
                        
                        <rect x="1" y="18" width="2" height="2" fill="#0d0f15" />
                        <rect x="5" y="17" width="1" height="1" fill="#0d0f15" />
                        <rect x="7" y="19" width="3" height="1" fill="#0d0f15" />
                        <rect x="12" y="18" width="2" height="1" fill="#0d0f15" />
                        <rect x="15" y="17" width="1" height="3" fill="#0d0f15" />
                        <rect x="18" y="18" width="2" height="1" fill="#0d0f15" />
                        <rect x="26" y="18" width="2" height="2" fill="#0d0f15" />
                        
                        <rect x="9" y="23" width="1" height="3" fill="#0d0f15" />
                        <rect x="11" y="22" width="2" height="1" fill="#0d0f15" />
                        <rect x="14" y="24" width="3" height="1" fill="#0d0f15" />
                        <rect x="18" y="23" width="1" height="2" fill="#0d0f15" />
                        
                        <rect x="10" y="27" width="3" height="1" fill="#0d0f15" />
                        <rect x="15" y="26" width="2" height="2" fill="#0d0f15" />
                        <rect x="18" y="27" width="3" height="1" fill="#0d0f15" />
                    </svg>
                    <div style="position: absolute; width: 36px; height: 36px; background: #0a0b10; border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 2px solid #ffffff;">
                        <svg viewBox="0 0 100 80" width="20" height="16" xmlns="http://www.w3.org/2000/svg">
                            <path d="M 16,65 L 43,38 L 33,38 L 58,13 L 58,38 L 50,38 L 80,68 L 73,68 L 43,38 L 23,65 Z" fill="url(#logo-white-qr)" />
                            <path d="M 41,65 L 68,38 L 58,38 L 83,13 L 83,38 L 73,38 L 48,65 Z" fill="url(#logo-white-qr)" />
                            <defs>
                                <linearGradient id="logo-white-qr" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" stop-color="#8B5CF6" />
                                    <stop offset="100%" stop-color="#EC4899" />
                                </linearGradient>
                            </defs>
                        </svg>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; color: var(--accent-green); font-size: 12px; font-weight: 600;">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Awaiting authorization...</span>
                </div>
            </div>
            
            <button type="button" class="btn btn-secondary w-full close-modal-btn">Cancel</button>
        </div>
    </div>

    <!-- Create Ticket Modal Backdrop -->
    <div id="ticket-modal" class="modal-backdrop hidden">
        <div class="card modal-card" style="position: relative; max-width: 480px;">
            <button type="button" class="close-modal-btn" style="position: absolute; top: 20px; right: 20px; background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 18px; transition: color 0.2s;" onmouseover="this.style.color='#ffffff'" onmouseout="this.style.color='var(--text-secondary)'">
                <i class="fa-solid fa-xmark"></i>
            </button>
            
            <!-- Ticket Form Content -->
            <div id="ticket-form-container">
                <div class="onboarding-header text-center" style="margin-bottom: 20px;">
                    <i class="fa-solid fa-ticket" style="font-size: 40px; color: var(--accent-pink); margin-bottom: 12px; filter: drop-shadow(0 0 10px rgba(236, 72, 153, 0.4));"></i>
                    <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 6px;">Create Support Ticket</h2>
                    <p style="font-size: 13px; color: var(--text-secondary);">Submit a ticket to request a password reset or other assistance.</p>
                </div>
                
                <form id="ticket-form" class="onboarding-form">
                    <div class="form-group">
                        <label for="ticket-email">Your Account Email</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="ticket-email" placeholder="name@company.com" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticket-subject">Subject</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-heading input-icon"></i>
                            <input type="text" id="ticket-subject" value="Password Reset Request" placeholder="Ticket Subject" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="ticket-description">Describe your issue</label>
                        <textarea id="ticket-description" placeholder="Provide details (e.g. 'I forgot my password and need a reset link')" rows="4" style="width: 100%; padding: 14px; background: rgba(0, 0, 0, 0.2); border: 1px solid var(--border-color); border-radius: var(--radius-md); color: var(--text-primary); font-family: var(--font-body); font-size: 14px; transition: all 0.3s; resize: vertical;" onfocus="this.style.outline='none'; this.style.borderColor='var(--accent-purple)'; this.style.background='rgba(0,0,0,0.35)'; this.style.boxShadow='0 0 10px rgba(139, 92, 246, 0.15)'" required></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 12px; margin-top: 24px;">
                        <button type="button" class="btn btn-secondary w-full close-modal-btn">Cancel</button>
                        <button type="submit" id="ticket-submit-btn" class="btn btn-primary w-full">Submit Ticket</button>
                    </div>
                </form>
            </div>
            
            <!-- Success Confirmation Screen -->
            <div id="ticket-success-container" class="hidden">
                <div class="onboarding-header text-center" style="margin-bottom: 20px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); border: 2px solid var(--accent-green); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px auto; color: var(--accent-green); font-size: 28px; filter: drop-shadow(0 0 10px rgba(16, 185, 129, 0.3));">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 6px;">Ticket Submitted!</h2>
                    <p style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">Your support ticket has been successfully created. Our system administrator has been notified. You will receive updates at your registered email address.</p>
                </div>
                
                <div style="background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Ticket ID:</span>
                        <span style="color: #ffffff; font-family: var(--font-mono); font-weight: 600;" id="ticket-id-val">#TKT-82947</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 8px;">
                        <span style="color: var(--text-secondary);">Category:</span>
                        <span style="color: #ffffff; font-weight: 500;">Password Reset</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 12px;">
                        <span style="color: var(--text-secondary);">Status:</span>
                        <span style="color: var(--accent-yellow); font-weight: 600; display: flex; align-items: center; gap: 4px;">
                            <span style="width: 6px; height: 6px; border-radius: 50%; background: var(--accent-yellow); display: inline-block;"></span> Pending Review
                        </span>
                    </div>
                </div>
                
                <button type="button" class="btn btn-secondary w-full close-modal-btn">Close Window</button>
            </div>
        </div>
    </div>

    <!-- Forced Password Reset Modal Prompt -->
    <?php if ($showChangePasswordModal): ?>
        <div class="modal-backdrop">
            <div class="card modal-card" style="max-width: 440px;">
                <div class="onboarding-header">
                    <?= renderLogo('centered') ?>
                    <h2 class="text-center" style="font-family: var(--font-heading); font-size: 22px; font-weight: 700; color: #ffffff; margin-bottom: 8px;">Activate Your Account</h2>
                    <p class="text-center" style="font-size: 13px; color: var(--text-secondary); line-height: 1.5;">Welcome to Respawn Logic. Set your permanent password to activate your account and access your dashboard.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-banner">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="onboarding-form">
                    <input type="hidden" name="action" value="activate_account">
                    <input type="hidden" name="activation_token" value="<?= htmlspecialchars($activationToken) ?>">
                    
                    <div class="form-group">
                        <label for="new_password">Set New Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="new_password" name="new_password" required placeholder="Min. 8 characters" autofocus>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full" style="margin-top: 10px;">
                        <span>Activate Account & Enter</span>
                        <i class="fa-solid fa-circle-play"></i>
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Dialog Toggle Scripting -->
    <script>
        // Modal elements
        const qrModal = document.getElementById('qr-modal');
        const ticketModal = document.getElementById('ticket-modal');
        
        // Triggers
        const openQrBtn = document.getElementById('open-qr-btn');
        const openTicketBtn = document.getElementById('open-ticket-btn');
        
        // Closers
        const closeBtns = document.querySelectorAll('.close-modal-btn');
        
        // Form states
        const ticketFormContainer = document.getElementById('ticket-form-container');
        const ticketSuccessContainer = document.getElementById('ticket-success-container');
        const ticketForm = document.getElementById('ticket-form');
        const ticketIdVal = document.getElementById('ticket-id-val');
        const ticketSubmitBtn = document.getElementById('ticket-submit-btn');

        // Open QR popup
        if (openQrBtn) {
            openQrBtn.addEventListener('click', () => {
                qrModal.classList.remove('hidden');
            });
        }

        // Open Ticket popup
        if (openTicketBtn) {
            openTicketBtn.addEventListener('click', (e) => {
                e.preventDefault();
                ticketFormContainer.classList.remove('hidden');
                ticketSuccessContainer.classList.add('hidden');
                if (ticketForm) ticketForm.reset();
                ticketModal.classList.remove('hidden');
            });
        }

        // Close all modals
        closeBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                qrModal.classList.add('hidden');
                ticketModal.classList.add('hidden');
            });
        });

        // Close on clicking backdrop
        window.addEventListener('click', (e) => {
            if (e.target === qrModal) {
                qrModal.classList.add('hidden');
            }
            if (e.target === ticketModal) {
                ticketModal.classList.add('hidden');
            }
        });

        // Handle Support Ticket form submit (AJAX simulation)
        if (ticketForm) {
            ticketForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                // Set loading status on submit button
                const oldContent = ticketSubmitBtn.innerHTML;
                ticketSubmitBtn.disabled = true;
                ticketSubmitBtn.innerHTML = '<span>Submitting...</span><i class="fa-solid fa-spinner fa-spin"></i>';
                
                setTimeout(() => {
                    // Generate random ticket identifier
                    const mockId = '#TKT-' + Math.floor(10000 + Math.random() * 90000);
                    if (ticketIdVal) ticketIdVal.textContent = mockId;
                    
                    // Show confirmation view
                    ticketFormContainer.classList.add('hidden');
                    ticketSuccessContainer.classList.remove('hidden');
                    
                    // Re-enable button & restore label
                    ticketSubmitBtn.disabled = false;
                    ticketSubmitBtn.innerHTML = oldContent;
                }, 1000);
            });
        }
    </script>
</body>
</html>
