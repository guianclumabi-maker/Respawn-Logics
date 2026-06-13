<?php
require_once __DIR__ . '/bootstrap/app.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['roles'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid or missing roles mapping payload.']);
    exit;
}

// 1. Session & CSRF Verification
if (empty($_SESSION['onboarding_active'])) {
    http_response_code(403);
    echo json_encode(['error' => 'No active onboarding session.']);
    exit;
}

$clientCsrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $clientCsrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token.']);
    exit;
}

// 2. Dynamic Tenancy Binding
$tenantId = $_SESSION['tenant_id'] ?? 1;

// 3. Strict Input Whitelisting
$allowedRoles = ['admin', 'hr', 'hr manager', 'recruiter', 'employee', 'manager', 'supervisor'];

try {
    $pdo->beginTransaction();

    $stmtUserUpdate = $pdo->prepare("UPDATE users SET role = ? WHERE employee_id = ? AND tenant_id = ?");
    $stmtFindUser = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND tenant_id = ?");
    $stmtFindRole = $pdo->prepare("SELECT id FROM roles WHERE LOWER(name) = ?");
    $stmtClearRoles = $pdo->prepare("DELETE FROM user_roles WHERE user_id = ?");
    $stmtInsertRole = $pdo->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");

    foreach ($input['roles'] as $employeeId => $newRole) {
        // Validate strictly against whitelist
        $cleanRole = strtolower(trim($newRole));
        if (!in_array($cleanRole, $allowedRoles)) {
            throw new Exception("Invalid role requested: " . htmlspecialchars($cleanRole));
        }

        // 1. Update legacy string role
        $stmtUserUpdate->execute([$cleanRole, $employeeId, $tenantId]);

        // 2. Resolve IDs for linking table
        $stmtFindUser->execute([$employeeId, $tenantId]);
        $userId = $stmtFindUser->fetchColumn();

        if ($userId) {
            $stmtFindRole->execute([$cleanRole]);
            $roleId = $stmtFindRole->fetchColumn();

            if ($roleId) {
                $stmtClearRoles->execute([$userId]);
                $stmtInsertRole->execute([$userId, $roleId]);

                // Always ensure they have baseline Employee permissions too
                $stmtFindRole->execute(['employee']);
                $empRoleId = $stmtFindRole->fetchColumn();
                if ($empRoleId && $empRoleId != $roleId) {
                    $stmtInsertRole->execute([$userId, $empRoleId]);
                }
            }
        }
    }

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database update error: ' . $e->getMessage()]);
}
