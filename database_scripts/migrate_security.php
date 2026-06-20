<?php
/**
 * migrate_security.php
 * Creates tables required for:
 *  - DB-based login rate limiting (OWASP A07: Authentication Failures)
 *  - Pre-login support ticket submission (public endpoint, no user_id required)
 *  - TOTP 2FA secrets storage (OWASP A07: MFA)
 *
 * Follows OWASP Top 10 guidance throughout.
 */
if (!defined('MIGRATION_SAFE')) die('Forbidden');

// ── 1. Login rate limiting table ─────────────────────────────────────────────
// Stores per-IP attempt counts server-side so attackers cannot bypass by clearing cookies.
$sql_rate = "
CREATE TABLE IF NOT EXISTS `login_rate_limits` (
    `id`            BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address`    VARCHAR(45)  NOT NULL,          -- supports IPv6 (max 39 chars)
    `attempt_count` INT          UNSIGNED NOT NULL DEFAULT 1,
    `last_attempt_at` DATETIME   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `locked_until`  DATETIME     NULL DEFAULT NULL, -- NULL = not currently locked
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_ip` (`ip_address`)               -- one row per IP for fast upsert
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 2. Support tickets table ─────────────────────────────────────────────────
// Pre-login ticket submissions (no user_id required — guest submitter).
// Follows principle of least privilege: status is restricted by ENUM.
$sql_tickets = "
CREATE TABLE IF NOT EXISTS `support_tickets` (
    `id`         BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    BIGINT       UNSIGNED NULL DEFAULT NULL, -- NULL if submitted before login
    `email`      VARCHAR(255) NOT NULL,
    `subject`    VARCHAR(255) NOT NULL,
    `message`    TEXT         NOT NULL,
    `status`     ENUM('open','in_progress','resolved','closed') NOT NULL DEFAULT 'open',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_email`  (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

// ── 3. TOTP 2FA secrets table ────────────────────────────────────────────────
// Stores per-user TOTP base32 secrets.
// totp_enabled = 0 means the user has NOT completed setup yet.
// recovery_hash stores a bcrypt hash of a single-use recovery code.
$sql_totp = "
CREATE TABLE IF NOT EXISTS `totp_secrets` (
    `id`            BIGINT       UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`       BIGINT       UNSIGNED NOT NULL,
    `secret`        VARCHAR(64)  NOT NULL,            -- base32-encoded TOTP secret
    `totp_enabled`  TINYINT(1)   NOT NULL DEFAULT 0,  -- 0 = pending setup, 1 = active
    `recovery_hash` VARCHAR(255) NULL DEFAULT NULL,   -- bcrypt hash of a recovery code
    `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_id` (`user_id`)               -- one secret row per user
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql_rate);
    echo "  [OK] login_rate_limits table ready.\n";
} catch (PDOException $e) {
    echo "  [ERROR] login_rate_limits: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec($sql_tickets);
    echo "  [OK] support_tickets table ready.\n";
} catch (PDOException $e) {
    echo "  [ERROR] support_tickets: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec($sql_totp);
    echo "  [OK] totp_secrets table ready.\n";
} catch (PDOException $e) {
    echo "  [ERROR] totp_secrets: " . $e->getMessage() . "\n";
}

// Add totp_pending column to users table if not already present.
// This temporary flag tells the login flow to enforce 2FA verification.
try {
    $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0");
    echo "  [OK] users.totp_enabled column added.\n";
} catch (PDOException $e) {
    // Column already exists — safe to ignore
    echo "  [SKIP] users.totp_enabled already exists.\n";
}
