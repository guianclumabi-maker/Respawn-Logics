<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

/**
 * Generalizes ELR auto-population from the single AWOL config into a rule engine.
 * A tenant can have many rules, each pointing a "detector" (awol, tardiness, ...) at
 * one of their pipelines. Existing AWOL configs are carried over as an 'awol' rule.
 * Tenant-scoped and idempotent.
 */
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_auto_rules` (
        `id`                 BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id`          VARCHAR(50) NOT NULL,
        `rule_type`          VARCHAR(50) NOT NULL,          -- detector key: awol, tardiness, ...
        `name`               VARCHAR(255) NULL,
        `enabled`            TINYINT(1) DEFAULT 0,
        `params`             JSON NULL,                      -- detector params, e.g. {\"consecutive_days\":3}
        `target_pipeline_id` BIGINT NULL,
        `target_stage_id`    BIGINT NULL,
        `created_at`         DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at`         DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_ar_tenant` (`tenant_id`),
        INDEX `idx_ar_type` (`tenant_id`, `rule_type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // One-time carry-over: turn any existing elr_awol_config rows into an 'awol' rule
    // (only if that tenant doesn't already have an awol rule — safe to re-run).
    try {
        $rows = $pdo->query("SELECT * FROM `elr_awol_config`")->fetchAll(PDO::FETCH_ASSOC);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM `elr_auto_rules` WHERE `tenant_id` = ? AND `rule_type` = 'awol'");
        $ins = $pdo->prepare(
            "INSERT INTO `elr_auto_rules` (`tenant_id`, `rule_type`, `name`, `enabled`, `params`, `target_pipeline_id`, `target_stage_id`)
             VALUES (?, 'awol', 'AWOL detection', ?, ?, ?, ?)"
        );
        foreach ($rows as $r) {
            $chk->execute([$r['tenant_id']]);
            if ((int)$chk->fetchColumn() === 0) {
                $params = json_encode(['consecutive_days' => (int)($r['consecutive_days'] ?? 3)]);
                $ins->execute([
                    $r['tenant_id'],
                    (int)($r['enabled'] ?? 0),
                    $params,
                    !empty($r['target_pipeline_id']) ? (int)$r['target_pipeline_id'] : null,
                    !empty($r['target_stage_id']) ? (int)$r['target_stage_id'] : null,
                ]);
            }
        }
    } catch (PDOException $e) {
        // elr_awol_config may not exist yet on a brand-new DB — nothing to carry over.
    }

    echo "ELR auto-rules engine ready (elr_auto_rules).\n";
} catch (PDOException $e) {
    echo "Error (migrate_elr_auto_rules): " . $e->getMessage() . "\n";
}
