<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * ELR Admin Console — "ATS-for-cases" pipeline module.
 *
 * Mirrors the ATS: document templates are the "jobs", pipelines/stages are the hiring
 * stages, and case cards are the "candidates" (employees moving through due-process phases).
 * Moving a card into a stage auto-generates that stage's document from a rich-text template.
 * All tables are tenant-scoped and idempotent (safe to re-run on every deploy).
 */
try {
    // 1. Document templates (the "Jobs" equivalent) — rich text with {{merge_fields}}.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_document_templates` (
        `id`            BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`     VARCHAR(50)  NOT NULL,
        `name`          VARCHAR(255) NOT NULL,
        `doc_type`      VARCHAR(50)  NOT NULL,               -- e.g. RTWN, NTE, NOD, or a client-defined tag
        `description`   TEXT NULL,
        `body`          MEDIUMTEXT   NOT NULL,               -- rich text/HTML with {{placeholders}}
        `merge_fields`  JSON NULL,                            -- distinct {{fields}} detected in body
        `is_active`     TINYINT(1) DEFAULT 1,
        `created_by`    VARCHAR(100) NULL,
        `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_tpl_tenant` (`tenant_id`),
        INDEX `idx_tpl_type` (`tenant_id`, `doc_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Pipelines (a case workflow, e.g. 'AWOL / Disciplinary').
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_pipelines` (
        `id`          BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`   VARCHAR(50)  NOT NULL,
        `name`        VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `is_active`   TINYINT(1) DEFAULT 1,
        `created_by`  VARCHAR(100) NULL,
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_pipe_tenant` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Pipeline stages (the kanban columns). Each stage may auto-fire a document template.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_pipeline_stages` (
        `id`          BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`   VARCHAR(50)  NOT NULL,
        `pipeline_id` BIGINT       NOT NULL,
        `name`        VARCHAR(255) NOT NULL,                 -- e.g. AWOL Pool, RTWN, NTE, Hearing, NOD, Resolved
        `stage_order` INT          NOT NULL DEFAULT 0,
        `template_id` BIGINT NULL,                            -- doc generated when a card ENTERS this stage
        `sla_days`    INT NULL,
        `is_terminal` TINYINT(1) DEFAULT 0,                   -- e.g. Resolved / Closed
        `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_stage_pipe` (`pipeline_id`),
        INDEX `idx_stage_tenant` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 4. Case cards (the 'candidates') — an employee moving through a pipeline.
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_case_cards` (
        `id`               BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`        VARCHAR(50)  NOT NULL,
        `pipeline_id`      BIGINT       NOT NULL,
        `employee_id`      VARCHAR(100) NOT NULL,            -- the subject employee
        `current_stage_id` BIGINT NULL,
        `status`           VARCHAR(50) DEFAULT 'Active',      -- Active / Resolved / Closed
        `entered_via`      VARCHAR(20) DEFAULT 'manual',      -- manual / auto (AWOL scan)
        `notes`            TEXT NULL,
        `created_by`       VARCHAR(100) NULL,
        `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_card_pipe` (`tenant_id`, `pipeline_id`),
        INDEX `idx_card_emp` (`tenant_id`, `employee_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 5. Generated documents — the compliance evidence (every merged notice ever produced).
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_generated_documents` (
        `id`             BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`      VARCHAR(50)  NOT NULL,
        `case_card_id`   BIGINT       NOT NULL,
        `template_id`    BIGINT NULL,
        `stage_id`       BIGINT NULL,
        `doc_type`       VARCHAR(50) NULL,
        `title`          VARCHAR(255) NULL,
        `content`        MEDIUMTEXT   NOT NULL,              -- rendered/merged document
        `generated_by`   VARCHAR(100) NULL,
        `generated_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
        `served_at`      DATETIME NULL,
        `acknowledged_at` DATETIME NULL,
        INDEX `idx_gendoc_card` (`case_card_id`),
        INDEX `idx_gendoc_tenant` (`tenant_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 6. Stage transitions — the due-process audit trail (twin-notice evidence).
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_stage_transitions` (
        `id`             BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`      VARCHAR(50)  NOT NULL,
        `case_card_id`   BIGINT       NOT NULL,
        `from_stage_id`  BIGINT NULL,
        `to_stage_id`    BIGINT NULL,
        `actor`          VARCHAR(100) NULL,
        `note`           TEXT NULL,
        `transitioned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX `idx_trans_card` (`case_card_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 7. AWOL auto-detection config (per tenant).
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_awol_config` (
        `id`                 BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`          VARCHAR(50) NOT NULL UNIQUE,
        `enabled`            TINYINT(1) DEFAULT 0,
        `consecutive_days`   INT DEFAULT 3,
        `target_pipeline_id` BIGINT NULL,
        `target_stage_id`    BIGINT NULL,
        `updated_at`         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "ELR pipeline module schema verified (7 tables).\n";
} catch (PDOException $e) {
    echo "Error (migrate_elr_pipeline): " . $e->getMessage() . "\n";
}
