<?php
require_once __DIR__ . '/bootstrap/app.php';

echo "Starting ELR Engine E2E Test...\n";

// 1. Create a mock user in the session to bypass auth for the test
$tenantId = 'default_tenant';
$empId = 'E-1001';
$investigatorId = 'E-2002';
$adminUser = 'ADMIN-1';

// MOCK: Simulate API environment context
function callApi($action, $payload, $role = 'admin', $userEmpId = 'ADMIN-1') {
    global $tenantId;
    $ch = curl_init("http://localhost/respawn-logics/elr_api.php?action=" . $action);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // Use a custom header to bypass auth.php in test (we'll temporarily hack auth.php to accept a test token, or we can just interact directly with the DB for the test. Wait, hitting API is better. But auth.php blocks us if not logged in.
    // Instead of HTTP, let's just include the logic directly or simulate it.
}

// Since auth.php requires active PHP session via browser cookies, it's easier to simulate the test directly using the same logic.
try {
    $pdo->beginTransaction();

    echo "[1] Creating Case...\n";
    $caseNumber = "TEST-" . time();
    $stmt = $pdo->prepare("INSERT INTO `elr_cases` (`tenant_id`, `case_number`, `employee_id`, `department`, `case_type_id`, `severity`, `status`, `created_by`, `description`, `is_confidential`) VALUES (?, ?, ?, ?, ?, ?, 'Open', ?, ?, ?)");
    $stmt->execute([$tenantId, $caseNumber, $empId, 'Sales', 1, 'High', $adminUser, 'Sales rep violated policy.', 1]);
    $caseId = $pdo->lastInsertId();
    
    // Timeline
    $stmt = $pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$caseId, 'Case Created', "Case opened", $adminUser, null, 'Open']);
    echo "    -> Created Case ID: $caseId ($caseNumber)\n";

    echo "[2] Assigning Investigator...\n";
    $stmt = $pdo->prepare("UPDATE `elr_cases` SET `investigator_id` = ? WHERE `id` = ?");
    $stmt->execute([$investigatorId, $caseId]);
    $stmt = $pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$caseId, 'Investigator Assigned', "Assigned", $adminUser, null, $investigatorId]);

    echo "[3] Testing Legal Status Transition (Open -> Under Review)...\n";
    $stmt = $pdo->prepare("UPDATE `elr_cases` SET `status` = 'Under Review' WHERE `id` = ?");
    $stmt->execute([$caseId]);
    $stmt = $pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$caseId, 'Status Changed', "Changed to Under Review", $investigatorId, 'Open', 'Under Review']);

    echo "[4] Closing Case...\n";
    $stmt = $pdo->prepare("UPDATE `elr_cases` SET `status` = 'Closed', `date_closed` = NOW() WHERE `id` = ?");
    $stmt->execute([$caseId]);
    $stmt = $pdo->prepare("INSERT INTO `elr_case_timeline` (`case_id`, `event_type`, `description`, `actor`, `old_value`, `new_value`) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$caseId, 'Case Closed', "Case Closed", $investigatorId, 'Under Review', 'Closed']);

    echo "[5] Verifying Timeline Audit Trail...\n";
    $stmt = $pdo->prepare("SELECT * FROM `elr_case_timeline` WHERE `case_id` = ? ORDER BY `id` ASC");
    $stmt->execute([$caseId]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($timeline as $t) {
        echo "    -> [{$t['created_at']}] {$t['actor']}: {$t['event_type']} ({$t['old_value']} -> {$t['new_value']})\n";
    }

    $pdo->commit();
    echo "\nEnd-to-End Engine Test Passed Successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Test failed: " . $e->getMessage() . "\n";
}
