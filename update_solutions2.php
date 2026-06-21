<?php
$solutionsPath = __DIR__ . '/solutions.php';
$content = file_get_contents($solutionsPath);

// The new modules array
$phpLogic = <<<EOT
\$modules = [
    'core-hr' => [
        'title' => 'Core HR & People',
        'tagline' => 'One source of truth for every person on your team.',
        'intro' => "When employee data lives in scattered spreadsheets, email threads, and someone's desktop folder, mistakes are inevitable and hours vanish into manual lookups. Core HR & People replaces all of it with a single, always-current system of record that your entire organization can actually trust.\n\nEvery profile, document, role, and reporting line lives in one secure place — so the right information is one search away, and your team can stop chasing it and start using it.",
        'features' => [
            "Centralized employee directory: every profile, contact detail, and employment record in one place.",
            "Secure document vault: contracts, IDs, and records stored with tenant isolation and access control.",
            "Live org chart: see reporting lines and structure update automatically as your company changes.",
            "Role-based access: people see exactly what they should — nothing more, nothing less.",
            "Multi-tenant by design: complete data separation between organizations, built in from day one.",
            "Audit-ready: a clear trail of who changed what, when."
        ],
        'why' => "Core HR is the foundation everything else builds on. Get your people data clean, secure, and centralized, and every other module — payroll, ATS, attendance — runs on data you can trust.",
        'cta' => "Give your team a single, reliable home for everything people-related."
    ],
    'ats' => [
        'title' => 'ATS Pipeline',
        'tagline' => 'Hire top talent faster — and never lose a great candidate to a messy process.',
        'intro' => "Great hiring dies in the cracks: résumés buried in inboxes, interviews that slip, candidates ghosted because no one knew whose turn it was. ATS Pipeline gives your whole hiring team one shared, visual pipeline where every candidate moves cleanly from sourced to signed.\n\nFrom AI-assisted match scoring to one-click hire-to-employee onboarding, it removes the busywork so your recruiters can spend their time on the only thing that matters — finding and closing the right people.",
        'features' => [
            "Visual stage pipeline: drag candidates from Applied to Hired with full visibility for the whole team.",
            "Match scoring: surface the strongest candidates automatically against each role's requirements.",
            "Résumé management: secure upload, storage, and quick access to every candidate's documents.",
            "Interviews & scorecards: schedule interviews and capture structured, comparable feedback.",
            "Talent pools: keep promising candidates warm for future roles instead of starting from scratch.",
            "Hire-to-employee in one click: turn a hire into an onboarded employee record instantly."
        ],
        'why' => "A disorganized hiring process costs you the best candidates — they accept somewhere else while you deliberate. ATS Pipeline keeps every opportunity moving so good people don't fall through the cracks.",
        'cta' => "Build a hiring machine your whole team can run from one screen."
    ],
    'payroll' => [
        'title' => 'Enterprise Payroll',
        'tagline' => 'Run Philippine payroll with confidence.',
        'intro' => "Payroll is the one thing you cannot get wrong — people's livelihoods and your legal standing both ride on it. Enterprise Payroll is built around the realities of Philippine payroll, automating the complex statutory math so each run is accurate, consistent, and explainable.\n\nIt's designed for PH statutory compliance — SSS, PhilHealth, Pag-IBIG, and BIR withholding — with the rate logic kept in versioned tables so it adapts as the law changes, and configuration that flexes from a small team to a large enterprise.",
        'features' => [
            "Designed for PH statutory deductions: SSS, PhilHealth, Pag-IBIG, and BIR withholding tax.",
            "13th-month and de-minimis handling aligned to current thresholds.",
            "Versioned rate tables: statutory changes are data updates, so historical runs stay accurate.",
            "Employer-share tracking for accurate cost and remittance visibility.",
            "Per-company configuration: pay schedules and components that fit how your business actually runs.",
            "Clear payslips: transparent breakdowns employees can understand."
        ],
        'why' => "Manual payroll spreadsheets are slow, error-prone, and risky. Enterprise Payroll automates the hard parts so you pay people correctly and on time — and can show your work.",
        'cta' => "Spend less time on payroll, and more time confident it's right."
    ],
    'service-desk' => [
        'title' => 'Service Desk',
        'tagline' => 'Every IT and HR request: tracked, resolved, measured.',
        'intro' => "When requests come in by chat, email, and hallway, things get dropped and no one can say what's actually happening. Service Desk turns every IT and HR request into a proper ticket with an owner, a status, and a deadline.\n\nAgents work from a single queue, employees always know where their request stands, and you finally get the data to see where time really goes — and where to improve.",
        'features' => [
            "Unified ticketing: capture every IT and HR request in one place, nothing lost.",
            "SLAs & priorities: set response targets and never let urgent issues sit.",
            "Agent queues: a clear, shared workload so the right person picks up the right ticket.",
            "Attachments: screenshots and files attached securely to each ticket.",
            "Categories & routing: send requests to the right team automatically.",
            "Visibility: employees track their own tickets end to end."
        ],
        'why' => "Untracked requests erode trust and bury your team. Service Desk makes support measurable, so nothing slips and everyone knows what's next.",
        'cta' => "Give your team support that's organized, fast, and accountable."
    ],
    'employee-relations' => [
        'title' => 'Employee Relations',
        'tagline' => 'Handle sensitive cases with structure, discretion, and a clear record.',
        'intro' => "Workplace issues — grievances, incidents, investigations — are high-stakes and easy to mishandle when they live in someone's notes and memory. Employee Relations gives you a confidential, structured way to document and manage every case from report to resolution.\n\nWith strict access controls and a complete audit trail, you protect everyone involved and keep a defensible record — handled the right way, every time.",
        'features' => [
            "Case management: log, track, and resolve incidents and grievances in one secure place.",
            "Structured workflow: move cases through clear stages from report to closure.",
            "Confidentiality controls: only authorized people can see sensitive cases.",
            "Investigation tracking: keep notes, actions, and outcomes organized and documented.",
            "PH labor-law guidance: built-in references to help you follow due process.",
            "Audit trail: a defensible record of every action taken."
        ],
        'why' => "Mishandled employee relations cases create real legal and human risk. This gives you a disciplined, discreet process that protects your people and your organization.",
        'cta' => "Manage the hardest conversations with care and a clear paper trail."
    ],
    'attendance' => [
        'title' => 'Attendance & Leaves',
        'tagline' => 'Know who\'s in, who\'s out, and who\'s owed — without the spreadsheet gymnastics.',
        'intro' => "Tracking attendance and leave by hand is tedious and error-prone, and the mistakes flow straight into payroll. Attendance & Leaves automates the whole cycle — time in, time off, approvals, and balances — in one real-time view.\n\nEmployees request leave in a click, managers approve in a click, and balances stay accurate automatically, so everyone trusts the numbers.",
        'features' => [
            "Time tracking: accurate attendance records without manual tallying.",
            "Leave requests & approvals: a clean request-to-approval flow for employees and managers.",
            "Real-time balances: leave credits that update automatically and stay correct.",
            "Manager approvals: fast, clear approval queues for team leads.",
            "Schedules & shifts: organize who works when.",
            "Payroll-ready data: attendance and leave that flow cleanly into payroll."
        ],
        'why' => "Attendance errors quietly become payroll errors and employee frustration. Automate it once and the numbers just stay right.",
        'cta' => "Replace the attendance spreadsheet with something your whole team can trust."
    ]
];
EOT;

// Replace old modules array with new
$content = preg_replace('/\$modules = \[.*?\];/s', $phpLogic, $content, 1);

// We also need to change the <title> and <meta name="description"> block.
$titleMetaOriginal = <<<EOT
    <title>Respawn Logics — HR That Levels Up Your Team</title>
    <meta name="description" content="Respawn Logics is the enterprise HR platform built for companies that think differently. Payroll, ATS, performance, and more — all in one respawn point.">
EOT;

$titleMetaReplacement = <<<EOT
    <title><?= htmlspecialchars(\$module['title']) ?> - Respawn Logics</title>
    <meta name="description" content="<?= htmlspecialchars(substr(strip_tags(\$module['intro']), 0, 160)) ?>...">
EOT;
$content = str_replace($titleMetaOriginal, $titleMetaReplacement, $content);

// We need to inject the CSS for the feature cards
$featureGridCss = <<<EOT
    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }
    .feature-card {
        background: rgba(255, 255, 255, 0.02);
        border: 1px solid var(--border2);
        border-radius: 8px;
        padding: 24px;
        transition: transform 0.2s, border-color 0.2s;
    }
    .feature-card:hover {
        transform: translateY(-2px);
        border-color: rgba(0, 224, 122, 0.3);
    }
    .feature-card h3 {
        color: var(--green);
        font-size: 1.1rem;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .feature-card p {
        color: var(--text-mid);
        font-size: 0.95rem;
        line-height: 1.5;
        margin: 0;
    }
    .module-intro p {
        font-size: 1.125rem;
        color: var(--text-c);
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .closing-cta {
        text-align: center;
        padding: 60px 48px;
        background: linear-gradient(0deg, var(--bg) 0%, var(--bg3) 100%);
        border-top: 1px solid var(--border2);
    }
    .closing-cta h2 {
        font-size: 2rem;
        color: #fff;
        margin-bottom: 30px;
    }
EOT;

$content = str_replace('.cta-buttons {', $featureGridCss . "\n    .cta-buttons {", $content);

// Now we replace the <main> block
$mainPattern = '/<main>.*?<\/main>/s';
$mainReplacement = <<<EOT
<main>
    <section class="module-hero">
        <h1><?= htmlspecialchars(\$module['title']) ?></h1>
        <div class="tagline"><?= htmlspecialchars(\$module['tagline']) ?></div>
        
        <div class="cta-buttons">
            <a href="register.php" class="btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i data-lucide="building"></i> Create Workspace
            </a>
            <?php if (!\$loggedIn): ?>
                <a href="login.php" class="btn-ghost" style="font-size: 1.1rem; padding: 15px 30px;">
                    Sign in
                </a>
            <?php else: ?>
                <a href="pages/dashboard.php" class="btn-ghost" style="font-size: 1.1rem; padding: 15px 30px;">
                    Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </section>

    <div class="module-content">
        <div class="module-section module-intro">
            <?php 
            \$paragraphs = explode("\\n\\n", \$module['intro']);
            foreach (\$paragraphs as \$p) {
                echo "<p>" . htmlspecialchars(\$p) . "</p>";
            }
            ?>
        </div>

        <div class="module-section">
            <h2>Key Features</h2>
            <div class="features-grid">
                <?php foreach (\$module['features'] as \$feature): 
                    // Split feature into title and description based on the first colon
                    \$parts = explode(':', \$feature, 2);
                    \$featureTitle = \$parts[0];
                    \$featureDesc = isset(\$parts[1]) ? trim(\$parts[1]) : '';
                ?>
                <div class="feature-card">
                    <h3><i class="fa-solid fa-check"></i> <?= htmlspecialchars(\$featureTitle) ?></h3>
                    <?php if (\$featureDesc): ?>
                        <p><?= htmlspecialchars(\$featureDesc) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="module-section">
            <h2>Why it matters</h2>
            <p><?= htmlspecialchars(\$module['why']) ?></p>
        </div>
    </div>
    
    <div class="closing-cta">
        <h2><?= htmlspecialchars(\$module['cta']) ?></h2>
        <div class="cta-buttons" style="margin-top: 0;">
            <a href="register.php" class="btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i data-lucide="building"></i> Create Workspace
            </a>
            <?php if (!\$loggedIn): ?>
                <a href="login.php" class="btn-ghost" style="font-size: 1.1rem; padding: 15px 30px;">
                    Sign in
                </a>
            <?php else: ?>
                <a href="pages/dashboard.php" class="btn-ghost" style="font-size: 1.1rem; padding: 15px 30px;">
                    Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</main>
EOT;

$content = preg_replace($mainPattern, $mainReplacement, $content, 1);

file_put_contents($solutionsPath, $content);
echo "solutions.php successfully updated!\n";
