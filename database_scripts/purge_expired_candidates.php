<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../backend/utils/Storage.php';

echo "Starting Candidate Purge Script...\n";

$isDryRun = in_array('--dry-run', $argv);
if ($isDryRun) {
    echo "========================================================\n";
    echo " DRY RUN MODE ENABLED. NO DATA WILL BE ALTERED OR DELETED.\n";
    echo "========================================================\n";
} else {
    echo "========================================================\n";
    echo " DESTRUCTIVE MODE. ANONYMIZING CANDIDATES AND FILES...\n";
    echo "========================================================\n";
}

$retentionMonths = getenv('CANDIDATE_DATA_RETENTION_MONTHS') ?: 24;
$cutoff = date('Y-m-d H:i:s', strtotime("-$retentionMonths months"));

echo "Retention period: $retentionMonths months.\n";
echo "Cutoff date for inactivity: $cutoff\n\n";

try {
    // Query 1: Candidates with applications, all of which are Rejected or Withdrawn.
    // We clock their last activity from the latest stage_entered_at.
    $stmt1 = $pdo->prepare("
        SELECT 
            p.`id`, 
            p.`tenant_id`, 
            p.`name`, 
            p.`resume_filename`, 
            MAX(COALESCE(a.`rejected_at`, a.`stage_entered_at`)) as last_activity
        FROM `candidate_profiles` p
        JOIN `candidate_applications` a ON p.`id` = a.`candidate_id`
        WHERE p.`is_anonymized` = 0
        GROUP BY p.`id`, p.`tenant_id`, p.`name`, p.`resume_filename`
        HAVING 
            SUM(CASE WHEN a.`stage` NOT IN ('Rejected', 'Withdrawn') THEN 1 ELSE 0 END) = 0
            AND MAX(COALESCE(a.`rejected_at`, a.`stage_entered_at`)) < ?
    ");
    $stmt1->execute([$cutoff]);
    $expiredWithApps = $stmt1->fetchAll();

    // Query 2: Candidates with no applications at all (truly abandoned).
    // Clock their activity from created_at.
    $stmt2 = $pdo->prepare("
        SELECT 
            p.`id`, 
            p.`tenant_id`, 
            p.`name`, 
            p.`resume_filename`, 
            p.`created_at` as last_activity
        FROM `candidate_profiles` p
        LEFT JOIN `candidate_applications` a ON p.`id` = a.`candidate_id`
        WHERE p.`is_anonymized` = 0 AND a.`id` IS NULL AND p.`created_at` < ?
    ");
    $stmt2->execute([$cutoff]);
    $expiredNoApps = $stmt2->fetchAll();

    $allExpired = array_merge($expiredWithApps, $expiredNoApps);

    if (empty($allExpired)) {
        echo "No candidates eligible for purge found.\n";
        exit(0);
    }

    echo "Found " . count($allExpired) . " candidates eligible for purge.\n";

    $anonymizeStmt = $pdo->prepare("UPDATE `candidate_profiles` SET `name` = 'Anonymized', `email` = '', `phone` = '', `location` = '', `skills` = '', `resume_text` = '', `resume_filename` = NULL, `is_anonymized` = 1 WHERE `id` = ? AND `tenant_id` = ?");
    $auditStmt = $pdo->prepare("INSERT INTO `audit_logs` (`tenant_id`, `user_id`, `action`, `details`, `target_type`, `target_id`) VALUES (?, NULL, 'candidate_auto_purged', 'Candidate automatically anonymized per retention policy', 'candidate', ?)");

    $processed = 0;
    foreach ($allExpired as $cand) {
        echo " - [Tenant {$cand['tenant_id']}] Candidate ID {$cand['id']} ('{$cand['name']}'). Last activity: {$cand['last_activity']}\n";
        
        if (!$isDryRun) {
            // Delete resume file if exists
            if (!empty($cand['resume_filename'])) {
                $path = \App\Utils\Storage::resolveStorageBase(true) . '/' . basename($cand['resume_filename']);
                if (file_exists($path)) {
                    @unlink($path);
                    echo "   > Deleted resume file.\n";
                }
            }

            // Anonymize DB row
            $anonymizeStmt->execute([$cand['id'], $cand['tenant_id']]);
            $auditStmt->execute([$cand['tenant_id'], $cand['id']]);
            echo "   > Anonymized DB record and logged audit.\n";
        }
        $processed++;
    }

    echo "\nSummary:\n";
    if ($isDryRun) {
        echo "Dry run complete. $processed candidates WOULD have been purged.\n";
    } else {
        echo "Purge complete. $processed candidates were anonymized.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
