<?php
require_once __DIR__ . '/../bootstrap/app.php';
global $pdo;

// 1. Create Tables
echo "Creating tables...\n";

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `elr_precedents` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT 'tenant_respawn_01',
        `case_title` varchar(255) NOT NULL,
        `gr_number` varchar(100) DEFAULT NULL,
        `decision_date` date DEFAULT NULL,
        `description` text,
        `ruling` text,
        `tags` varchar(255) DEFAULT NULL,
        PRIMARY KEY (`id`),
        FULLTEXT KEY `ft_idx_description` (`description`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `labor_references` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT 'tenant_respawn_01',
        `category` varchar(100) NOT NULL,
        `reference_code` varchar(100) NOT NULL,
        `content` text NOT NULL,
        PRIMARY KEY (`id`),
        FULLTEXT KEY `ft_idx_content` (`content`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS `global_intelligence_cache` (
        `id` int NOT NULL AUTO_INCREMENT,
        `tenant_id` varchar(50) DEFAULT 'tenant_respawn_01',
        `anonymized_prompt` text,
        `ai_response` text,
        `status` enum('Anonymized','Approved','Rejected') DEFAULT 'Anonymized',
        `confidence_score` decimal(5,2) DEFAULT '0.00',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        FULLTEXT KEY `ft_idx_prompt_response` (`anonymized_prompt`,`ai_response`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "Tables created successfully.\n";

// 2. Seed Data
echo "Seeding data...\n";

// Clear existing to avoid duplicates if run multiple times
$pdo->exec("TRUNCATE TABLE elr_precedents");
$pdo->exec("TRUNCATE TABLE labor_references");

$precedents = [
    [
        'case_title' => 'Philippine Long Distance Telephone Company vs. NLRC',
        'gr_number' => 'G.R. No. 80609',
        'decision_date' => '1988-08-23',
        'description' => 'Employee termination due to gross and habitual neglect of duties and stealing company property.',
        'ruling' => 'Separation pay shall be allowed as a measure of social justice only in those instances where the employee is validly dismissed for causes other than serious misconduct or those reflecting on his moral character.',
        'tags' => 'termination, separation pay, serious misconduct'
    ],
    [
        'case_title' => 'King of Kings Transport vs. Mamac',
        'gr_number' => 'G.R. No. 166208',
        'decision_date' => '2007-06-29',
        'description' => 'A case concerning the proper observance of the Twin-Notice Rule for termination under DOLE standards.',
        'ruling' => 'The Supreme Court laid down the standards for due process: (1) First written notice specifying grounds for termination; (2) An ample opportunity to be heard (hearing/conference); (3) Second written notice indicating that all circumstances involving the charge have been considered.',
        'tags' => 'due process, twin-notice rule, termination'
    ],
    [
        'case_title' => 'Agabon vs. NLRC',
        'gr_number' => 'G.R. No. 158693',
        'decision_date' => '2004-11-17',
        'description' => 'Termination of employees due to Abandonment of Work (AWOL) without proper observance of procedural due process.',
        'ruling' => 'When the dismissal is for a just cause but procedural due process was not observed, the dismissal is valid, but the employer must pay nominal damages (PhP 30,000) for violation of statutory due process.',
        'tags' => 'awol, abandonment, nominal damages, due process'
    ],
    [
        'case_title' => 'Sugue vs. Triumph International',
        'gr_number' => 'G.R. No. 164804',
        'decision_date' => '2009-01-30',
        'description' => 'Loss of trust and confidence of managerial employees.',
        'ruling' => 'For loss of trust and confidence to be a valid ground for dismissal, the employee must be holding a position of trust and confidence, and there must be an act that would justify the loss of trust and confidence. The act must be willful.',
        'tags' => 'loss of trust, termination, managerial'
    ]
];

$stmt = $pdo->prepare("INSERT INTO elr_precedents (case_title, gr_number, decision_date, description, ruling, tags) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($precedents as $p) {
    $stmt->execute([$p['case_title'], $p['gr_number'], $p['decision_date'], $p['description'], $p['ruling'], $p['tags']]);
}

$labor_references = [
    [
        'category' => 'DOLE Guidelines',
        'reference_code' => 'Article 297 (formerly 282) Labor Code',
        'content' => 'Termination by Employer. An employer may terminate an employment for any of the following causes: (a) Serious misconduct or willful disobedience; (b) Gross and habitual neglect by the employee of his duties; (c) Fraud or willful breach of trust; (d) Commission of a crime or offense by the employee against the person of his employer or any immediate member of his family or his duly authorized representatives; and (e) Other causes analogous to the foregoing.'
    ],
    [
        'category' => 'DOLE Guidelines',
        'reference_code' => 'DOLE D.O. 147-15 (Twin Notice Rule)',
        'content' => 'Statutory Due Process requires the Twin-Notice Rule: (1) Notice to Explain (NTE) containing a detailed narration of the facts and circumstances serving as basis for the charge, directing the employee to submit a written explanation within at least 5 calendar days. (2) Notice of Decision stating the circumstances that were considered and the grounds to justify the termination.'
    ],
    [
        'category' => 'Company Policy',
        'reference_code' => 'Attendance Policy 1.0 (AWOL)',
        'content' => 'Absence Without Official Leave (AWOL): Any employee who is absent for three (3) consecutive working days without notifying their immediate supervisor and without an approved leave of absence shall be considered AWOL and may be subject to disciplinary action up to termination of employment, constituting gross and habitual neglect of duty or abandonment.'
    ],
    [
        'category' => 'Company Policy',
        'reference_code' => 'Code of Conduct (Insubordination)',
        'content' => 'Insubordination is the willful or intentional disregard of the lawful and reasonable instructions of the employer. First offense: Written Warning. Second offense: 3-5 days suspension. Third offense: Termination.'
    ]
];

$stmt = $pdo->prepare("INSERT INTO labor_references (category, reference_code, content) VALUES (?, ?, ?)");
foreach ($labor_references as $r) {
    $stmt->execute([$r['category'], $r['reference_code'], $r['content']]);
}

echo "Database successfully upgraded and seeded with Philippine Labor Law precedents!\n";
