<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    // Drop the old generic knowledge base
    $pdo->exec("DROP TABLE IF EXISTS `hr_knowledge_base`;");

    // 1. labor_references (For DOLE, Statutory Compliance)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `labor_references` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `country_code` VARCHAR(5) DEFAULT 'PH',
        `category` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `summary` TEXT NOT NULL,
        `source_type` VARCHAR(100) NOT NULL,
        `official_url` VARCHAR(255) NULL,
        `effective_date` DATE NULL,
        `reviewed_by` VARCHAR(100) NULL,
        `status` ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT INDEX `ft_compliance` (`title`, `summary`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. elr_precedents (For Supreme Court Jurisprudence / Internal Discipline)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `elr_precedents` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `case_type` VARCHAR(100) NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `summary` TEXT NOT NULL,
        `key_principles` TEXT NOT NULL,
        `source_reference` VARCHAR(255) NOT NULL,
        `risk_level` ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium',
        `recommended_process` TEXT NOT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT INDEX `ft_precedents` (`case_type`, `title`, `summary`, `key_principles`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "New database schemas (labor_references, elr_precedents) created successfully.\n";

    // --- SEED AUTHENTIC LEGAL DATA ---
    
    // Seed DOLE Advisories (labor_references)
    $stmtDole = $pdo->prepare("INSERT INTO `labor_references` (`category`, `title`, `summary`, `source_type`, `status`) VALUES (?, ?, ?, ?, 'Approved')");
    $doleData = [
        [
            'Holiday Pay',
            'DOLE Labor Advisory No. 06-24',
            'Payment of wages for the regular holiday on May 1, 2024 (Labor Day). If the employee did not work, the employee shall be paid 100% of his/her wage for that day. If the employee worked, they shall be paid 200% of their wage for the first eight (8) hours.',
            'DOLE Advisory'
        ],
        [
            '13th Month Pay',
            'DOLE Labor Advisory No. 13-24',
            'Guidelines on the payment of the 13th-month pay. All rank-and-file employees in the private sector shall be entitled to 13th month pay regardless of their position, provided they have worked for at least one (1) month during the calendar year. Must be paid on or before Dec 24.',
            'DOLE Advisory'
        ]
    ];
    foreach ($doleData as $d) {
        $stmtDole->execute($d);
    }
    echo "Seeded authentic DOLE Labor Advisories.\n";

    // Seed Supreme Court Jurisprudence (elr_precedents)
    $stmtSc = $pdo->prepare("INSERT INTO `elr_precedents` (`case_type`, `title`, `summary`, `key_principles`, `source_reference`, `risk_level`, `recommended_process`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $scData = [
        [
            'AWOL / Abandonment',
            'AWOL vs. Abandonment of Work (G.R. No. 231859 & 218384)',
            'The Supreme Court has consistently established that Absence Without Official Leave (AWOL) is NOT synonymous with abandonment of work. Mere absence or failure to report for work, even if prolonged, does not automatically amount to abandonment.',
            'Two-Element Test for Abandonment: 1) Failure to report for work without justifiable reason. 2) A clear intention to sever the employer-employee relationship (intent is determinative). Burden of proof lies squarely on the employer.',
            'Supreme Court Decision (G.R. No. 231859, Feb 19 2020)',
            'High',
            'Do not immediately terminate for Abandonment based solely on AWOL. Issue a Return to Work Order (RTWO) via registered mail. If the employee fails to return, issue a Notice to Explain (NTE) to establish the clear intent to sever employment.'
        ]
    ];
    foreach ($scData as $s) {
        $stmtSc->execute($s);
    }
    echo "Seeded authentic Supreme Court Jurisprudence.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
