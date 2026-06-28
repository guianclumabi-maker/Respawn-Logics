<?php
require_once __DIR__ . '/bootstrap/app.php';
require_once __DIR__ . '/backend/services/RoleSeederService.php';

// If already logged in normally, redirect to dashboard
if (isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true)) {
    header('Location: ' . url('/frontend/dist/index.html?v=' . time() . '#/dashboard'));
    exit;
}

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $companyName = trim($_POST['company_name'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($companyName) || empty($fullName) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        try {
            $pdo->beginTransaction();

            // 1. Check if email already exists globally
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->fetch()) {
                throw new Exception("An account with this email already exists.");
            }

            // 2. Create Tenant (Unique 6-digit numeric ID)
            $tenantId = '';
            do {
                $candidateId = (string)rand(100000, 999999);
                $stmtCheckTenant = $pdo->prepare("SELECT COUNT(*) FROM tenants WHERE id = ?");
                $stmtCheckTenant->execute([$candidateId]);
                if ($stmtCheckTenant->fetchColumn() == 0) {
                    $tenantId = $candidateId;
                }
            } while (empty($tenantId));

            $setupMode = $_POST['setup_mode'] ?? 'Solo';
            $validModes = ['Solo', 'Small', 'Mid', 'Enterprise'];
            if (!in_array($setupMode, $validModes)) $setupMode = 'Solo';

            $stmtTenant = $pdo->prepare("INSERT INTO tenants (id, company_name, contact_email, subscription_tier, status, setup_mode, permission_version) VALUES (?, ?, ?, 'Trial', 'Active', ?, 1)");
            $stmtTenant->execute([$tenantId, $companyName, $email, $setupMode]);

            // 3. Create Admin User
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            // Splitting name
            $nameParts = explode(' ', $fullName, 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

            $stmtUser = $pdo->prepare("
                INSERT INTO users (
                    tenant_id, first_name, last_name, full_name, email, work_email, 
                    password_hash, role, employment_status, tier
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Super_Admin', 'Active', 1.0)
            ");
            $stmtUser->execute([
                $tenantId, $firstName, $lastName, $fullName, $email, $email, $passwordHash
            ]);
            $userId = $pdo->lastInsertId();

            // Seed Roles using the new service
            RoleSeederService::seedTenantRoles($pdo, $tenantId, $setupMode, $userId);

            // 4. Create Default Payroll Schedule
            $scheduleName = "Standard Monthly";
            $scheduleConfig = json_encode([
                'type' => 'monthly',
                'day' => 25,
                'period_start' => '1st',
                'period_end' => 'end'
            ]);
            $stmtSchedule = $pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency, config_json, is_active) VALUES (?, ?, 'Monthly', ?, 1)");
            $stmtSchedule->execute([$tenantId, $scheduleName, $scheduleConfig]);

            $pdo->commit();

            // 5. Generate a one-time login token (bypasses session cookie race conditions on Railway)
            // The token is stored in the DB and React exchanges it for a real session via the API.
            $loginToken = bin2hex(random_bytes(32));
            $tokenExpiry = date('Y-m-d H:i:s', time() + 300); // 5 minutes

            // Ensure the table exists (self-healing — no separate migration needed)
            $pdo->exec("CREATE TABLE IF NOT EXISTS `user_activation_tokens` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `user_id`    INT UNSIGNED NOT NULL,
                `token`      VARCHAR(128) NOT NULL UNIQUE,
                `expires_at` DATETIME     NOT NULL,
                `used_at`    DATETIME     NULL DEFAULT NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_token` (`token`),
                INDEX `idx_user`  (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmtToken = $pdo->prepare(
                "INSERT INTO user_activation_tokens (user_id, token, expires_at, created_at) VALUES (?, ?, ?, NOW())"
            );
            $stmtToken->execute([$userId, $loginToken, $tokenExpiry]);

            $hashPath = ($setupMode === 'Solo') ? '/dashboard' : '/onboarding';
            $redirectUrl = url('/frontend/dist/index.html?v=' . time() . '#' . $hashPath . '?login_token=' . $loginToken);

            // Output the JS redirect — token is in the URL, no session cookie needed
            echo "<!DOCTYPE html><html><head><title>Redirecting...</title>";
            echo "<script>window.location.href = '" . addslashes($redirectUrl) . "';</script>";
            echo "</head><body>Redirecting to workspace...</body></html>";
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Company - Respawn Logic HRIS</title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= url('/assets/css/styles.css') ?>">
    <style>
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

        <div class="onboarding-step">
            <div class="card onboarding-card login-card">
                <div class="onboarding-header">
                    <?= renderLogo('centered') ?>
                    <h1 class="text-center">Register Your Company</h1>
                    <p class="text-center">Create a dedicated workspace for your organization in seconds.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="error-banner">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="onboarding-form">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-building input-icon"></i>
                            <input type="text" id="company_name" name="company_name" required placeholder="Acme Corp" value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Your Full Name</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-user input-icon"></i>
                            <input type="text" id="full_name" name="full_name" required placeholder="Jane Doe" value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Work Email</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-envelope input-icon"></i>
                            <input type="email" id="email" name="email" required placeholder="jane@acmecorp.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <i class="fa-solid fa-lock input-icon"></i>
                            <input type="password" id="password" name="password" required placeholder="Min. 8 characters">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="setup_mode">Company Setup Mode</label>
                        <div class="input-wrapper" style="position: relative;">
                            <i class="fa-solid fa-layer-group input-icon" style="position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; z-index: 2;"></i>
                            <?php $getMode = isset($_GET['setup_mode']) ? $_GET['setup_mode'] : ''; ?>
                            <select id="setup_mode" name="setup_mode" required class="form-control" style="width: 100%; padding: 12px 15px 12px 40px; border-radius: 6px; border: 1px solid var(--border); background: var(--bg-alt); color: var(--text); font-family: inherit; font-size: 14px; appearance: none; position: relative; z-index: 1;">
                                <option value="Solo" <?= $getMode === 'Solo' ? 'selected' : '' ?>>Single Player (Just me)</option>
                                <option value="Small" <?= $getMode === 'Small' ? 'selected' : '' ?>>Co-op Mode (1 - 100 employees)</option>
                                <option value="Mid" <?= $getMode === 'Mid' ? 'selected' : '' ?>>Multiplayer Guild (100 - 500 employees)</option>
                                <option value="Enterprise" <?= $getMode === 'Enterprise' ? 'selected' : '' ?>>MMO Server (500+ employees)</option>
                            </select>
                            <i class="fa-solid fa-chevron-down" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--text-secondary); pointer-events: none; z-index: 2; font-size: 12px;"></i>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-full" style="margin-top: 20px;">
                        <span>Create Workspace</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </button>
                    
                    <div style="margin-top: 20px; text-align: center;">
                        <span style="color: var(--text-secondary); font-size: 13px;">Already have an account? </span>
                        <a href="login.php" style="color: var(--accent-purple); font-size: 13px; text-decoration: none; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.color='#c084fc'; this.style.textDecoration='underline'" onmouseout="this.style.color='var(--accent-purple)'; this.style.textDecoration='none'">
                            Sign In
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
