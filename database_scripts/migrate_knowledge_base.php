<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `hr_knowledge_base` (
        `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `content_body` TEXT NOT NULL,
        `category` VARCHAR(100) NOT NULL,
        `source_url` VARCHAR(255) NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FULLTEXT INDEX `ft_content` (`title`, `content_body`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    echo "Knowledge base table created successfully.\n";

    // Seed some initial company policies if table is empty
    $count = (int)$pdo->query("SELECT COUNT(*) FROM `hr_knowledge_base`")->fetchColumn();
    
    if ($count === 0) {
        $stmt = $pdo->prepare("INSERT INTO `hr_knowledge_base` (`title`, `content_body`, `category`) VALUES (?, ?, ?)");
        
        $policies = [
            [
                'Attendance & Tardiness Policy v3',
                'According to the Respawn Logics Employee Handbook (Section 4.2), the standard disciplinary progression for tardiness is: 1. First offense: Verbal Warning. 2. Second offense: Written Reprimand. 3. Third offense: Unpaid Suspension (1-3 days). 4. Fourth offense: Subject to termination. Always ensure that previous infractions are properly documented in the employee HR profile before escalating.',
                'Internal Policy'
            ],
            [
                'Dispute Resolution & Fairness Policy v4',
                'To handle an employee dispute fairly, please follow these steps: 1. Listen Objectively: Meet with each party separately. 2. Gather Evidence: Collect emails, messages, or statements. 3. Maintain Confidentiality: Only involve necessary personnel. Potential Risk: Low if resolved quickly, High if escalated to external bodies.',
                'Internal Policy'
            ],
            [
                'Incident Reporting Standard Operating Procedure',
                'Incident Report Draft Structure - Date of Incident, Time, Location, Parties Involved, Description of Event (objective summary), Immediate Action Taken, Witnesses.',
                'Procedure'
            ],
            [
                'AWOL (Absence Without Official Leave) Procedure v2',
                'An employee is considered AWOL if they fail to report to work for three (3) consecutive days without notifying their manager. A Notice to Explain (NTE) must be issued via registered mail to the employee\'s last known address.',
                'Internal Policy'
            ]
        ];

        foreach ($policies as $p) {
            $stmt->execute($p);
        }
        echo "Seeded initial company policies.\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
