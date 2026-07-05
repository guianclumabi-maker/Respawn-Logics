<?php
/**
 * AWOL daily scan — intended to be run by a scheduler (cron / Railway cron / external hit).
 * Iterates every tenant that has AWOL auto-detection enabled and runs the scan, which
 * auto-adds absent employees into the configured pipeline (firing the entry-stage document).
 *
 * Usage (CLI):   php scripts/awol_scan.php
 * It is CLI-only unless ALLOW_SCAN=1 is set (so it can't be triggered anonymously over HTTP).
 */
if (php_sapi_name() !== 'cli' && getenv('ALLOW_SCAN') !== '1') {
    http_response_code(403);
    die("This script is CLI-only. Set ALLOW_SCAN=1 to override.\n");
}

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../backend/controllers/ELRPipelineController.php';
global $pdo;

try {
    // Discover tenants from the generalized auto-rules engine (AWOL, tardiness, etc.),
    // NOT the legacy elr_awol_config — rules created via the Automation UI live here.
    $tenants = $pdo->query(
        "SELECT DISTINCT `tenant_id` FROM `elr_auto_rules` WHERE `enabled` = 1 AND `target_pipeline_id` IS NOT NULL"
    )->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    die("Auto-rule scan aborted: " . $e->getMessage() . "\n");
}

echo "Auto-rule scan starting for " . count($tenants) . " tenant(s).\n";
foreach ($tenants as $tid) {
    // Set the tenant context the controller reads from, then run the scan for that tenant.
    $_SESSION['tenant_id'] = $tid;
    try {
        $ctrl = new ELRPipelineController($pdo);
        $res  = $ctrl->scanCurrentTenant();
        echo "[$tid] " . json_encode($res) . "\n";
    } catch (Throwable $e) {
        echo "[$tid] ERROR: " . $e->getMessage() . "\n";
    }
}
echo "AWOL scan finished.\n";
