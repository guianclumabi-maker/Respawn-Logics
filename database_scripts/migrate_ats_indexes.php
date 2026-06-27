<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

echo "Migrating ATS Indexes and fixing tenant_id default...\n";

// Tables and their indexes
$indexesToAdd = [
    'jobs'                   => ['idx_jobs_tenant' => ['tenant_id']],
    'candidate_profiles'     => ['idx_cp_tenant' => ['tenant_id']],
    'candidate_applications' => ['idx_ca_tenant_candidate_job' => ['tenant_id', 'candidate_id', 'job_id']],
    'interviews'             => ['idx_int_tenant_candidate_job' => ['tenant_id', 'candidate_id', 'job_id']],
    'scorecards'             => ['idx_sc_tenant_int' => ['tenant_id', 'interview_id']],
    'candidate_notes'        => ['idx_cn_tenant_candidate' => ['tenant_id', 'candidate_id']],
    'talent_pools'           => ['idx_tp_tenant' => ['tenant_id']],
    'pool_members'           => ['idx_pm_tenant_candidate' => ['tenant_id', 'candidate_id']],
    'approvals'              => ['idx_appr_tenant' => ['tenant_id']],
    'activities'             => ['idx_act_tenant_candidate_job' => ['tenant_id', 'candidate_id', 'job_id']]
];

foreach ($indexesToAdd as $table => $indexes) {
    // 1. Remove tenant_id default
    try {
        $pdo->exec("ALTER TABLE `$table` MODIFY `tenant_id` varchar(50) NOT NULL");
        echo "Fixed tenant_id default on $table\n";
    } catch (PDOException $e) {
        echo "Note: Could not alter tenant_id on $table: " . $e->getMessage() . "\n";
    }

    // 2. Add indexes idempotently
    foreach ($indexes as $indexName => $columns) {
        // Check if index exists
        $stmt = $pdo->prepare("SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $stmt->execute([$table, $indexName]);
        if ($stmt->fetchColumn() == 0) {
            $cols = implode(', ', array_map(function($c) { return "`$c`"; }, $columns));
            try {
                $pdo->exec("ALTER TABLE `$table` ADD INDEX `$indexName` ($cols)");
                echo "Added index $indexName on $table\n";
            } catch (PDOException $e) {
                echo "Failed to add index $indexName on $table: " . $e->getMessage() . "\n";
            }
        } else {
            echo "Index $indexName already exists on $table\n";
        }
    }
}

echo "ATS Indexes migration complete.\n";
