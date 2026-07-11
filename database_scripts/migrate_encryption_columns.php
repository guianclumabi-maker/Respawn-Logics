<?php
/**
 * Phase 3 — Widen target columns + add blind-index columns.
 *
 * Targets (confirmed from BenefitsController + CoreHRController discovery):
 *   Table: employee_statutory
 *     sss_number         VARCHAR(30)  → VARCHAR(255)
 *     philhealth_number  VARCHAR(30)  → VARCHAR(255)
 *     pagibig_number     VARCHAR(30)  → VARCHAR(255)
 *     tin_number         VARCHAR(25)  → VARCHAR(255)
 *     tin_bidx           CHAR(64)     (new — for equality search)
 *
 *   Table: employee_documents
 *     file_path          VARCHAR(255) → TEXT   (encrypted bytes stored inline,
 *                                               or path to .enc file — same column)
 *     is_encrypted       TINYINT(1)   (new — flag to distinguish encrypted vs legacy)
 *
 * DO NOT touch: base_salary, is_mwe, any statutory rate/amount column.
 *
 * Safe to re-run: all ALTER TABLE calls are guarded by SHOW COLUMNS checks.
 */
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    echo "Starting AES-256-GCM encryption column migration...\n";

    // ── employee_statutory ────────────────────────────────────────────────────

    $statutoryWidenings = [
        'sss_number'        => "VARCHAR(255) DEFAULT NULL",
        'philhealth_number' => "VARCHAR(255) DEFAULT NULL",
        'pagibig_number'    => "VARCHAR(255) DEFAULT NULL",
        'tin_number'        => "VARCHAR(255) DEFAULT NULL",
    ];

    foreach ($statutoryWidenings as $col => $def) {
        $check = $pdo->query("SHOW COLUMNS FROM `employee_statutory` LIKE '$col'");
        if ($check && $check->rowCount() > 0) {
            $pdo->exec("ALTER TABLE `employee_statutory` MODIFY COLUMN `$col` $def");
            echo "- Widened employee_statutory.$col to VARCHAR(255).\n";
        } else {
            echo "- Skipped employee_statutory.$col (column not found — may not exist yet).\n";
        }
    }

    // Blind index for TIN (used for equality lookups, e.g. duplicate TIN check)
    $tinBidxCheck = $pdo->query("SHOW COLUMNS FROM `employee_statutory` LIKE 'tin_bidx'");
    if ($tinBidxCheck && $tinBidxCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `employee_statutory` ADD COLUMN `tin_bidx` CHAR(64) DEFAULT NULL AFTER `tin_number`");
        $pdo->exec("ALTER TABLE `employee_statutory` ADD INDEX `idx_tin_bidx` (`tin_bidx`)");
        echo "- Added tin_bidx CHAR(64) + index to employee_statutory.\n";
    } else {
        echo "- tin_bidx already exists on employee_statutory.\n";
    }

    // ── employee_documents ────────────────────────────────────────────────────

    $filePathCheck = $pdo->query("SHOW COLUMNS FROM `employee_documents` LIKE 'file_path'");
    if ($filePathCheck && $filePathCheck->rowCount() > 0) {
        // Widen to TEXT to hold enc:v1: prefix + large base64 blobs
        $pdo->exec("ALTER TABLE `employee_documents` MODIFY COLUMN `file_path` TEXT NOT NULL");
        echo "- Widened employee_documents.file_path to TEXT.\n";
    } else {
        echo "- Skipped employee_documents.file_path (column not found).\n";
    }

    $isEncCheck = $pdo->query("SHOW COLUMNS FROM `employee_documents` LIKE 'is_encrypted'");
    if ($isEncCheck && $isEncCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `employee_documents` ADD COLUMN `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `file_path`");
        echo "- Added is_encrypted flag to employee_documents.\n";
    } else {
        echo "- is_encrypted already exists on employee_documents.\n";
    }

    echo "Encryption column migration completed successfully!\n";

} catch (\Throwable $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    throw $e;
}
