<?php
if (!defined('MIGRATION_SAFE')) die('Forbidden');
require_once __DIR__ . '/../bootstrap/app.php';

try {
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

    // Guarded column additions for labor_references
    $laborRefCols = [
        'country_code'   => "VARCHAR(5) DEFAULT 'PH'",
        'category'       => "VARCHAR(100) NOT NULL",
        'title'          => "VARCHAR(255) NOT NULL",
        'summary'        => "TEXT NOT NULL",
        'source_type'    => "VARCHAR(100) NOT NULL",
        'official_url'   => "VARCHAR(255) NULL",
        'effective_date' => "DATE NULL",
        'reviewed_by'    => "VARCHAR(100) NULL",
        'status'         => "ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending'",
        'created_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        'updated_at'     => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    foreach ($laborRefCols as $col => $defn) {
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'labor_references' AND column_name = '$col'")->fetchColumn();
        if ((int)$exists === 0) {
            $pdo->exec("ALTER TABLE `labor_references` ADD COLUMN `$col` $defn");
        }
    }
    // Ensure FT index exists
    $ftCompliance = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'labor_references' AND index_name = 'ft_compliance'")->fetchColumn();
    if ((int)$ftCompliance === 0) {
        try {
            $pdo->exec("ALTER TABLE `labor_references` ADD FULLTEXT INDEX `ft_compliance` (`title`, `summary`)");
        } catch (PDOException $e) {}
    }

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

    // Guarded column additions for elr_precedents
    $elrPrecedentCols = [
        'case_type'           => "VARCHAR(100) NOT NULL",
        'title'               => "VARCHAR(255) NOT NULL",
        'summary'             => "TEXT NOT NULL",
        'key_principles'      => "TEXT NOT NULL",
        'source_reference'    => "VARCHAR(255) NOT NULL",
        'risk_level'          => "ENUM('Low', 'Medium', 'High', 'Critical') DEFAULT 'Medium'",
        'recommended_process' => "TEXT NOT NULL",
        'created_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        'updated_at'          => "DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    foreach ($elrPrecedentCols as $col => $defn) {
        $exists = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE table_schema = DATABASE() AND table_name = 'elr_precedents' AND column_name = '$col'")->fetchColumn();
        if ((int)$exists === 0) {
            $pdo->exec("ALTER TABLE `elr_precedents` ADD COLUMN `$col` $defn");
        }
    }
    // Ensure FT index exists
    $ftPrecedents = $pdo->query("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE table_schema = DATABASE() AND table_name = 'elr_precedents' AND index_name = 'ft_precedents'")->fetchColumn();
    if ((int)$ftPrecedents === 0) {
        try {
            $pdo->exec("ALTER TABLE `elr_precedents` ADD FULLTEXT INDEX `ft_precedents` (`case_type`, `title`, `summary`, `key_principles`)");
        } catch (PDOException $e) {}
    }

    echo "New database schemas (labor_references, elr_precedents) verified successfully.\n";

    // --- SEED AUTHENTIC LEGAL DATA ---
    
    // Seed DOLE Advisories (labor_references)
    // Use INSERT IGNORE by modifying the statement, but since there's no unique key on title, we will check if exists
    $stmtCheckDole = $pdo->prepare("SELECT COUNT(*) FROM `labor_references` WHERE `title` = ?");
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
        ],
        [
            'Final Pay',
            'DOLE Labor Advisory No. 06-20 (Final Pay & COE)',
            'Final pay (last salary, pro-rated 13th month pay, cash conversion of unused Service Incentive Leave, and other amounts due) must be released within thirty (30) days from the date of separation, unless a more favorable company policy or CBA applies. A Certificate of Employment (COE) must be issued within three (3) days from the employee request.',
            'DOLE Advisory'
        ],
        [
            '13th Month Pay',
            'Presidential Decree No. 851 (13th Month Pay Law)',
            'All rank-and-file employees who have worked at least one (1) month during the calendar year are entitled to 13th month pay of at least one-twelfth (1/12) of the basic salary earned within the year, payable on or before December 24. Managerial employees are excluded.',
            'Statute (PD 851)'
        ],
        [
            'Service Incentive Leave',
            'Service Incentive Leave — Labor Code Art. 95',
            'Every employee who has rendered at least one (1) year of service is entitled to five (5) days of paid Service Incentive Leave (SIL) per year, convertible to cash if unused at year-end. Establishments with fewer than 10 employees, and those already granting at least 5 days leave, are exempt.',
            'Labor Code'
        ],
        [
            'Night Shift Differential',
            'Night Shift Differential — Labor Code Art. 86',
            'Every employee is entitled to a night shift differential of not less than ten percent (10%) of the regular wage for each hour of work performed between 10:00 PM and 6:00 AM.',
            'Labor Code'
        ],
        [
            'Overtime Pay',
            'Overtime Pay — Labor Code Art. 87',
            'Work beyond eight (8) hours per day is paid an additional twenty-five percent (25%) of the hourly rate on an ordinary working day, and an additional thirty percent (30%) of the hourly rate when performed on a rest day, special day, or regular holiday.',
            'Labor Code'
        ],
        [
            'Maternity Leave',
            '105-Day Expanded Maternity Leave — RA 11210',
            'Covered female workers are entitled to 105 days of paid maternity leave for live childbirth (regardless of civil status or legitimacy of the child), with an option to extend 30 days without pay, plus an additional 15 days if the mother qualifies as a solo parent. Up to 7 days may be transferred to the child father or an alternate caregiver. Sixty (60) days apply for miscarriage or emergency termination of pregnancy.',
            'Statute (RA 11210)'
        ],
        [
            'Paternity Leave',
            'Paternity Leave — RA 8187',
            'Married male employees are entitled to seven (7) days of paid paternity leave for the first four (4) deliveries of the legitimate spouse with whom he is cohabiting, availed during or reasonably after the delivery or miscarriage.',
            'Statute (RA 8187)'
        ],
        [
            'Solo Parent Leave',
            'Expanded Solo Parents Welfare Act — RA 11861 (amending RA 8972)',
            'Qualified solo parents who have rendered at least six (6) months of service are entitled to seven (7) working days of paid parental leave per year, in addition to other leave benefits, plus flexible work arrangements and protection from work discrimination.',
            'Statute (RA 11861)'
        ],
        [
            'VAWC Leave',
            'VAWC Leave — RA 9262',
            'A female employee who is a victim of Violence Against Women and their Children (VAWC) is entitled to up to ten (10) days of paid leave, extendible when necessary, to attend to medical and legal concerns, supported by an appropriate certification.',
            'Statute (RA 9262)'
        ],
        [
            'Service Charges',
            'Service Charge Distribution — RA 11360',
            'All service charges collected by covered establishments (e.g., hotels and restaurants) must be distributed one hundred percent (100%) to covered rank-and-file employees, at least once every two (2) weeks or twice a month.',
            'Statute (RA 11360)'
        ],
        [
            'Telecommuting',
            'Telecommuting Act — RA 11165',
            'Private-sector employers may offer a voluntary telecommuting (work-from-home) program. Telecommuting employees must receive treatment comparable to on-site employees regarding rate of pay, rest days, leave, benefits, and protection against unfair working conditions.',
            'Statute (RA 11165)'
        ],
        [
            'Anti-Harassment',
            'Safe Spaces Act (Bawal Bastos) — RA 11313',
            'Employers must prevent, deter, and penalize gender-based sexual harassment in the workplace, including adopting a clear policy, forming a Committee on Decorum and Investigation (CODI), and disseminating the law. Covers physical, verbal, and online conduct.',
            'Statute (RA 11313)'
        ],
        [
            'Probationary Employment',
            'Probationary Employment — Labor Code Art. 296',
            'Probationary employment must not exceed six (6) months from the start of work (unless under an apprenticeship agreement). The reasonable standards for regularization must be made known to the employee at the time of engagement; otherwise the employee is deemed regular. A probationary employee may be dismissed for just cause or for failure to meet the communicated standards.',
            'Labor Code'
        ],
        [
            'Retirement Pay',
            'Retirement Pay Law — RA 7641',
            'Absent a company retirement plan, an employee who retires at age 60 (optional) up to 65 (compulsory) with at least five (5) years of service is entitled to retirement pay of at least one-half (1/2) month salary per year of service, where one-half month equals about 22.5 days (15 days pay + 1/12 of the 13th month + cash equivalent of 5 days SIL). A fraction of at least 6 months counts as one whole year.',
            'Statute (RA 7641)'
        ],
        [
            'Separation Pay',
            'Separation Pay for Authorized Causes — Labor Code Art. 298-299',
            'Separation pay for authorized-cause terminations: (a) installation of labor-saving devices or redundancy — at least one (1) month pay per year of service; (b) retrenchment to prevent losses or closure not due to serious losses — at least one-half (1/2) month pay per year of service; (c) disease — at least one-half (1/2) month pay per year of service. A fraction of at least 6 months counts as one year. Written notice to the employee and DOLE at least 30 days prior is required.',
            'Labor Code'
        ]
    ];
    foreach ($doleData as $d) {
        $stmtCheckDole->execute([$d[1]]);
        if ($stmtCheckDole->fetchColumn() == 0) {
            $stmtDole->execute($d);
        }
    }
    echo "Seeded authentic DOLE Labor Advisories.\n";

    // Seed Supreme Court Jurisprudence (elr_precedents)
    $stmtCheckSc = $pdo->prepare("SELECT COUNT(*) FROM `elr_precedents` WHERE `title` = ?");
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
        ],
        [
            'Due Process / Just Cause',
            'Agabon v. NLRC (G.R. No. 158693)',
            'Where an employee is dismissed for a JUST cause but the employer fails to observe procedural due process (the twin-notice requirement), the dismissal is UPHELD as valid, but the employer is liable to pay nominal damages for violating the right to statutory due process.',
            'A valid substantive ground does not excuse procedural lapses. The dismissal stands, but the employer is penalized with nominal damages (set at P30,000 in this case).',
            'Supreme Court, G.R. No. 158693 (Nov 17, 2004)',
            'High',
            'Even with a clear just cause, always complete the twin-notice process: Notice to Explain, an opportunity to be heard, then a Notice of Decision. If procedure was missed, expect nominal damages (about P30,000) even if the dismissal itself is upheld.'
        ],
        [
            'Due Process / Authorized Cause',
            'Jaka Food Processing Corp. v. Pacot (G.R. No. 151378)',
            'Where an employee is dismissed for an AUTHORIZED cause (e.g., retrenchment or redundancy) but the employer fails to serve the required 30-day written notices to the employee and to DOLE, the dismissal is valid but the employer is liable for nominal damages set HIGHER than in just-cause cases, because the termination was initiated by the employer management prerogative.',
            'Authorized-cause procedural violations are sanctioned more heavily than just-cause ones. Nominal damages of P50,000 were imposed.',
            'Supreme Court, G.R. No. 151378 (Mar 28, 2005)',
            'High',
            'For authorized-cause terminations (redundancy, retrenchment, closure), serve written notice to BOTH the affected employees and DOLE at least 30 days before effectivity, and pay the correct separation pay. Missing the 30-day notice can cost about P50,000 nominal damages per employee.'
        ],
        [
            'Due Process / Twin-Notice Rule',
            'King of Kings Transport, Inc. v. Mamac (G.R. No. 166208)',
            'The Supreme Court detailed the twin-notice rule. A VERBAL notice of charges does not satisfy due process. The first notice must be WRITTEN, specifying the particular acts or omissions and giving the employee a reasonable period (at least 5 calendar days) to explain, followed by a hearing or conference, and finally a written notice of decision.',
            'The first notice must be written and specific (with a detailed narration of facts); a mere verbal appraisal is insufficient. The employee must be given a genuine opportunity to be heard before the decision notice is issued.',
            'Supreme Court, G.R. No. 166208 (Jun 29, 2007)',
            'High',
            'Issue the first Notice to Explain in writing with specific facts and at least 5 calendar days to respond. Hold a hearing or conference. Then issue a written Notice of Decision. Never rely on verbal warnings for termination due process.'
        ],
        [
            'Constructive Dismissal',
            'Constructive Dismissal Doctrine',
            'Constructive dismissal exists when continued employment is rendered impossible, unreasonable, or unlikely, such as through demotion in rank, diminution of pay or benefits, or acts of clear discrimination, insensibility, or disdain that force the employee to resign. It is treated as illegal dismissal.',
            'The test is whether a reasonable person in the employee position would have felt compelled to give up employment. The burden is on the employer to prove the action was a valid, good-faith exercise of management prerogative.',
            'Settled Philippine jurisprudence (constructive dismissal doctrine)',
            'High',
            'Before any demotion, transfer, or reduction of pay or benefits, ensure a legitimate business reason and good faith, and document the justification. Avoid conduct that could be seen as forcing a resignation.'
        ],
        [
            'Just Cause / Loss of Trust',
            'Loss of Trust and Confidence (Labor Code Art. 297[c])',
            'Loss of trust and confidence is a valid just cause for dismissal, but only for employees holding positions of trust (managerial employees, or fiduciary rank-and-file who handle money or property). The loss of trust must rest on a WILLFUL breach founded on clearly established facts, not on arbitrary or simulated grounds.',
            'Two requisites: (1) the employee holds a position of trust and confidence; and (2) a willful act founded on clearly established facts justifies the loss of trust. It cannot be a catch-all for arbitrary dismissals.',
            'Labor Code Art. 297(c); settled jurisprudence',
            'Medium',
            'Reserve this ground for employees in positions of trust. Document the specific willful act and evidence, and still observe the twin-notice due process. Do not use loss of trust as a pretext.'
        ],
        [
            'Floating Status / Suspension of Operations',
            'Floating Status / Off-Detail (Labor Code Art. 301)',
            'A bona fide suspension of operations or the floating status / off-detail of an employee (common in security and agency work) is allowed for a period NOT exceeding six (6) months. Beyond six months without recall or reassignment, the employee is deemed constructively (illegally) dismissed.',
            'Off-detail within six (6) months and done in good faith due to lack of available posts is not dismissal. Exceeding six months converts it into constructive dismissal.',
            'Labor Code Art. 301 (bona fide suspension of operations); settled jurisprudence',
            'Medium',
            'Track floating / off-detail periods carefully and reassign or recall within six (6) months. If no post is available near the deadline, resolve the status properly (e.g., authorized-cause separation with pay) rather than leaving the employee floating indefinitely.'
        ]
    ];
    foreach ($scData as $s) {
        $stmtCheckSc->execute([$s[1]]);
        if ($stmtCheckSc->fetchColumn() == 0) {
            $stmtSc->execute($s);
        }
    }
    echo "Seeded authentic Supreme Court Jurisprudence.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
