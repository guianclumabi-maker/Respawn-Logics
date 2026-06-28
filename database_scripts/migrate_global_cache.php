<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
    // Drop old table version if it exists to cleanly apply correct columns and fulltext indexes
    $pdo->exec("DROP TABLE IF EXISTS `global_intelligence_cache`;");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `global_intelligence_cache` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `tenant_id` VARCHAR(50) NOT NULL DEFAULT 'SYSTEM',
        `anonymized_prompt` TEXT NOT NULL,
        `ai_response` TEXT NOT NULL,
        `category` VARCHAR(100) DEFAULT 'General',
        `status` ENUM('Raw', 'Anonymized', 'Approved') DEFAULT 'Anonymized',
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        FULLTEXT INDEX `ft_global_cache` (`anonymized_prompt`, `ai_response`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Global Intelligence Cache table created successfully.\n";

    // Seed a couple of cross-tenant examples
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `global_intelligence_cache`")->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO `global_intelligence_cache` (`tenant_id`, `anonymized_prompt`, `ai_response`, `category`, `status`) VALUES (?, ?, ?, ?, ?)");
        
        $examples = [
            [
                'TENANT_A',
                'How do we calculate final pay for an employee who resigned and has outstanding loans?',
                '**AI Community Intelligence**\n\nBased on standard labor practices across our network: Final pay should include prorated 13th-month pay, unpaid earned wages, and encashment of unused leave credits. Company loans can be legally deducted from the final pay provided there is a signed agreement or promissory note authorizing the deduction. Always secure a signed quitclaim upon release.',
                'Payroll Dispute',
                'Approved'
            ],
            [
                'TENANT_B',
                'Is an employee entitled to separation pay if they are terminated for serious misconduct?',
                '**AI Community Intelligence**\n\nNo. Under the Labor Code, an employee dismissed for just causes (such as Serious Misconduct, Fraud, or Insubordination) is NOT entitled to separation pay. However, some companies grant financial assistance out of compassion, but this is strictly voluntary and not legally mandated.',
                'Termination',
                'Approved'
            ]
        ];

        foreach ($examples as $e) {
            $stmt->execute($e);
        }
        echo "Seeded initial Global Cache examples.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
