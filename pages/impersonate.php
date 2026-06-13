<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? '';

if ($action === 'start') {
    // 1. Verify current user is allowed to impersonate (exemption for Platform Admin / internal staff)
    $currentUser = getCurrentUser();
    $isStaff = false;
    if ($currentUser) {
        $allowed_roles = ['Platform_Admin', 'Support_Agent', 'Implementation_Specialist'];
        if (in_array($currentUser['role'], $allowed_roles) || $currentUser['tenant_id'] === null) {
            $isStaff = true;
        }
    }
    
    if (!$isStaff && !hasPermission('users.manage')) {
        die("Unauthorized. Missing users.manage permission.");
    }
    
    $tenant_id = $_GET['tenant_id'] ?? null;
    if (!$tenant_id) {
        die("Missing tenant_id");
    }
    
    // 2. Lookup the target tenant's primary Super Admin
    $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? AND role = 'Super_Admin' ORDER BY id ASC LIMIT 1");
    $stmt->execute([$tenant_id]);
    $targetUser = $stmt->fetch();
    
    if (!$targetUser) {
        // Fallback 1: Any admin or manager for this tenant
        $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? AND role IN ('Admin', 'Super_Admin', 'Manager') ORDER BY id ASC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $targetUser = $stmt->fetch();
    }
    
    if (!$targetUser) {
        // Fallback 2: Any user at all for this tenant
        $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $targetUser = $stmt->fetch();
    }
    
    if (!$targetUser) {
        // Fallback 3: Create a mock Super Admin user dynamically
        $tStmt = $pdo->prepare("SELECT * FROM tenants WHERE id = ?");
        $tStmt->execute([$tenant_id]);
        $tenantInfo = $tStmt->fetch();
        
        $email = $tenantInfo ? $tenantInfo['contact_email'] : "admin@{$tenant_id}.com";
        $name = $tenantInfo ? "Super Admin (" . $tenantInfo['company_name'] . ")" : "Super Admin";
        $dummyHash = password_hash('password123', PASSWORD_DEFAULT);
        
        $insertStmt = $pdo->prepare("INSERT INTO users (tenant_id, email, password_hash, full_name, role, department, immediate_supervisor, profile_image) VALUES (?, ?, ?, ?, 'Super_Admin', '', '', '')");
        $insertStmt->execute([$tenant_id, $email, $dummyHash, $name]);
        
        // Retrieve newly created user
        $stmt = $pdo->prepare("SELECT * FROM users WHERE tenant_id = ? AND role = 'Super_Admin' ORDER BY id ASC LIMIT 1");
        $stmt->execute([$tenant_id]);
        $targetUser = $stmt->fetch();
    }
    
    if (!$targetUser) {
        die("Unable to impersonate: Failed to resolve or create a target user for tenant ID " . htmlspecialchars($tenant_id));
    }
    
    // Log start event to audit_logs
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_email, action, details) VALUES (?, ?, ?, ?)");
    $logTenantId = $targetUser['tenant_id'] ?? 1;
    $logEmail = $currentUser['email'] ?? 'system';
    $logDetails = "Staff " . ($currentUser['full_name'] ?? $logEmail) . " started impersonating user ID " . $targetUser['id'] . " (" . $targetUser['full_name'] . ") of tenant ID " . $tenant_id;
    $stmtLog->execute([$logTenantId, $logEmail, 'Impersonation Start', $logDetails]);
    
    // 3. Backup current master session
    $_SESSION['original_user_id'] = $_SESSION['user_id'];
    $_SESSION['original_user_email'] = $_SESSION['user_email'];
    $_SESSION['original_user_name'] = $_SESSION['user_name'];
    $_SESSION['original_tenant_id'] = $_SESSION['tenant_id'] ?? null;
    $_SESSION['is_impersonating'] = true;
    
    // 4. Overwrite session with target user
    $_SESSION['user_id'] = $targetUser['id'];
    $_SESSION['user_email'] = $targetUser['email'];
    $_SESSION['user_name'] = $targetUser['full_name'];
    $_SESSION['tenant_id'] = $targetUser['tenant_id'];
    
    // Force permissions reload
    unset($_SESSION['permissions']);
    unset($_SESSION['permission_version']);
    
    header("Location: " . url('/pages/dashboard.php'));
    exit;
}

if ($action === 'stop') {
    // 1. Verify we are actually impersonating
    if (empty($_SESSION['is_impersonating'])) {
        header("Location: " . url('/pages/dashboard.php'));
        exit;
    }
    
    // Log stop event to audit_logs before clearing original session
    $stmtLog = $pdo->prepare("INSERT INTO audit_logs (tenant_id, user_email, action, details) VALUES (?, ?, ?, ?)");
    $logTenantId = $_SESSION['tenant_id'] ?? 1;
    $logEmail = $_SESSION['original_user_email'] ?? 'system';
    $logDetails = "Staff " . ($_SESSION['original_user_name'] ?? $logEmail) . " stopped impersonating";
    $stmtLog->execute([$logTenantId, $logEmail, 'Impersonation Stop', $logDetails]);
    
    // 2. Restore original session
    $_SESSION['user_id'] = $_SESSION['original_user_id'];
    $_SESSION['user_email'] = $_SESSION['original_user_email'];
    $_SESSION['user_name'] = $_SESSION['original_user_name'];
    $_SESSION['tenant_id'] = $_SESSION['original_tenant_id'];
    
    // 3. Clean up impersonation vars
    unset($_SESSION['original_user_id']);
    unset($_SESSION['original_user_email']);
    unset($_SESSION['original_user_name']);
    unset($_SESSION['original_tenant_id']);
    unset($_SESSION['is_impersonating']);
    
    // Force permissions reload for the master
    unset($_SESSION['permissions']);
    unset($_SESSION['permission_version']);
    
    header("Location: " . url('/pages/saas_admin.php'));
    exit;
}

die("Invalid action.");
