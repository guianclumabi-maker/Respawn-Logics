<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * ELR Phase 5 — hearings + decision approvals.
 * (Document serve/acknowledge reuses served_at/acknowledged_at already on elr_generated_documents.)
 * Tenant-scoped, idempotent.
 */
try {
    // Hearings / conferences — the "opportunity to be heard" step in due process.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_hearings` (
        `id`           BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`    VARCHAR(50) NOT NULL,
        `case_card_id` BIGINT NOT NULL,
        `scheduled_at` DATETIME NULL,
        `location`     VARCHAR(255) NULL,
        `notes`        TEXT NULL,
        `outcome`      TEXT NULL,
        `status`       VARCHAR(30) DEFAULT 'Scheduled',   -- Scheduled / Held / Cancelled
        `created_by`   VARCHAR(100) NULL,
        `created_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_hearing_card` (`case_card_id`),
        INDEX `idx_hearing_tenant` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Decision approvals — sign-off gate before a decisive action (e.g. issuing a NOD).
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_approvals` (
        `id`            BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`     VARCHAR(50) NOT NULL,
        `case_card_id`  BIGINT NOT NULL,
        `stage_id`      BIGINT NULL,
        `subject`       VARCHAR(255) NOT NULL,
        `requested_by`  VARCHAR(100) NULL,
        `approver`      VARCHAR(100) NULL,
        `status`        VARCHAR(30) DEFAULT 'Pending',     -- Pending / Approved / Rejected
        `decision_note` TEXT NULL,
        `requested_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        `decided_at`    DATETIME NULL,
        INDEX `idx_appr_card` (`case_card_id`),
        INDEX `idx_appr_tenant` (`tenant_id`),
        INDEX `idx_appr_status` (`tenant_id`, `status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "ELR Phase 5 schema verified (elr_hearings, elr_approvals).\n";
} catch (PDOException $e) {
    echo "Error (migrate_elr_phase5): " . $e->getMessage() . "\n";
}
