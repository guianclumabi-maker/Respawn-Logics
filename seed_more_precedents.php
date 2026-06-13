<?php
require_once __DIR__ . '/bootstrap/app.php';

try {
    $stmtSc = $pdo->prepare("INSERT INTO `elr_precedents` (`case_type`, `title`, `summary`, `key_principles`, `source_reference`, `risk_level`, `recommended_process`) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    $scData = [
        [
            'Insubordination',
            'Insubordination and Willful Disobedience (G.R. No. 149074 & 163431)',
            'Insubordination and willful disobedience are treated as forms of serious misconduct and serve as just causes for termination. The refusal to obey must be intentional and characterized by a wrongful and perverse attitude.',
            'Two Requisites: 1) The conduct must be willful or intentional. 2) The order violated must be reasonable, lawful, made known to the employee, and connected to their duties.',
            'Supreme Court Decision (G.R. No. 149074 & 163431)',
            'High',
            'Ensure the order given was lawful and documented. Issue a Notice to Explain (NTE) detailing the specific lawful order that was disobeyed. Evaluate the explanation to confirm intent before termination.'
        ],
        [
            'Sexual Harassment',
            'Constructive Dismissal via Sexual Harassment (G.R. No. 217101)',
            'Employers have a duty to provide a safe work environment and to act promptly and sensitively on complaints of sexual harassment. Failure to do so renders the employer solidarily liable for constructive dismissal.',
            'At the core of sexual harassment is the abuse of power by a superior over a subordinate. Employers MUST create a Committee on Decorum and Investigation (CODI) under R.A. No. 7877.',
            'Supreme Court Decision (G.R. No. 217101 & 268399)',
            'Critical',
            'Immediately isolate the parties involved to prevent further hostile environment. Convene the Committee on Decorum and Investigation (CODI). If proven, the perpetrator is subject to termination for Serious Misconduct.'
        ],
        [
            'Theft / Loss of Trust',
            'Theft as Willful Breach of Trust (G.R. No. 226089 & 200571)',
            'Theft committed by an employee is a valid ground for dismissal based on serious misconduct and willful breach of trust. The amount or value of the property stolen is immaterial.',
            'Criminal conviction is NOT required for dismissal; substantial evidence is enough. For managerial employees, a mere basis for believing they breached trust is sufficient. For rank-and-file, the breach must be intentionally and purposely done.',
            'Supreme Court Decision (G.R. No. 226089)',
            'Critical',
            'Conduct a thorough internal investigation and gather substantial evidence (CCTV, witness statements). Issue an NTE for Loss of Trust and Confidence. Proceed with administrative hearing. Criminal filing is optional but administrative dismissal can proceed independently.'
        ]
    ];

    foreach ($scData as $s) {
        $stmtSc->execute($s);
    }
    
    echo "Successfully seeded additional Supreme Court Jurisprudence (Insubordination, Sexual Harassment, Theft).\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
