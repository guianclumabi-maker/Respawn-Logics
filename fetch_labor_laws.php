<?php
/**
 * fetch_labor_laws.php
 * 
 * This script is designed to run automatically via Windows Task Scheduler.
 * It fetches official labor advisories (e.g., from DOLE) and stores them
 * directly into the hr_knowledge_base table for the AI Companion to read.
 */

require_once __DIR__ . '/bootstrap/app.php';

echo "Starting Automated Labor Law Fetcher...\n";

try {
    // In a production environment, this would hit an actual RSS feed or API, e.g.:
    // $feedUrl = 'https://www.dole.gov.ph/feed/';
    // $rss = simplexml_load_file($feedUrl);
    
    // For this prototype, we simulate an API payload of "new" advisories 
    // that might be published by the Department of Labor and Employment.
    $mockAdvisories = [
        [
            'title' => 'DOLE Labor Advisory No. 14: Guidelines on the Payment of 13th Month Pay',
            'content' => 'All rank-and-file employees in the private sector shall be entitled to 13th month pay regardless of their position, provided they have worked for at least one (1) month during the calendar year. It must be paid on or before December 24.',
            'category' => 'Official Labor Law',
            'source_url' => 'https://www.dole.gov.ph/news/labor-advisory-no-14/'
        ],
        [
            'title' => 'DOLE Labor Advisory No. 06: Payment of Wages for Regular Holidays',
            'content' => 'If the employee did not work, the employee shall be paid 100% of his/her wage for that day, subject to certain requirements under the implementing rules. If the employee worked, the employee shall be paid 200% of his/her wage for that day for the first eight (8) hours.',
            'category' => 'Official Labor Law',
            'source_url' => 'https://www.dole.gov.ph/news/payment-of-wages/'
        ]
    ];

    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM labor_references WHERE title = ?");
    $stmtInsert = $pdo->prepare("INSERT INTO labor_references (category, title, summary, source_type, source_url, status) VALUES (?, ?, ?, ?, ?, 'Pending')");

    $newCount = 0;

    foreach ($mockAdvisories as $advisory) {
        $stmtCheck->execute([$advisory['title']]);
        if ($stmtCheck->fetchColumn() == 0) {
            $stmtInsert->execute([
                $advisory['category'],
                $advisory['title'],
                $advisory['content'],
                'DOLE Advisory',
                $advisory['source_url']
            ]);
            $newCount++;
            echo "Inserted new pending law: " . $advisory['title'] . "\n";
        }
    }

    echo "Fetcher complete. Added $newCount new advisories to the Knowledge Base.\n";

} catch (Exception $e) {
    echo "Error fetching labor laws: " . $e->getMessage() . "\n";
}
