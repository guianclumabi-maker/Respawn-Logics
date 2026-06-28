<?php
require_once __DIR__ . '/bootstrap/app.php';
require_once __DIR__ . '/backend/controllers/AuditController.php';

// Mock authentication for the test
$_SESSION['tenant_id'] = 1; // Assuming tenant 1 exists
$_SESSION['user_id'] = 1;   // Assuming user 1 is admin

// We need a fake getCurrentUser
function getMockUser() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM users LIMIT 1");
    return $stmt->fetch();
}

$controller = new AuditController($pdo);
// Hack to bypass permission check since we are directly calling the private methods via reflection or just overriding
// Wait, we can just use Reflection to call the private methods to see the return value!
ob_start();

$reflection = new ReflectionClass('AuditController');

// fetch_actions
$method1 = $reflection->getMethod('fetchActions');
$method1->setAccessible(true);
$method1->invoke($controller);
$actions_json = ob_get_clean();

ob_start();
// fetch_logs
$_GET['page'] = 1;
$_GET['limit'] = 5;
$method2 = $reflection->getMethod('fetchLogs');
$method2->setAccessible(true);
$method2->invoke($controller);
$logs_json = ob_get_clean();

echo "ACTIONS SHAPE:\n";
echo $actions_json . "\n\n";

echo "LOGS SHAPE:\n";
echo $logs_json . "\n\n";
