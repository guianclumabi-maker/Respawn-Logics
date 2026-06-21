<?php
$indexPath = __DIR__ . '/index.php';
$solutionsPath = __DIR__ . '/solutions.php';

$lines = file($indexPath);
$navEndIdx = -1;
$footerStartIdx = -1;

foreach ($lines as $idx => $line) {
    if (strpos($line, '</nav>') !== false && $navEndIdx === -1) {
        $navEndIdx = $idx;
    }
    if (strpos($line, '<footer') !== false && $footerStartIdx === -1) {
        $footerStartIdx = $idx;
    }
}

if ($navEndIdx === -1 || $footerStartIdx === -1) {
    die("Could not find boundaries in index.php\n");
}

$headerContent = implode("", array_slice($lines, 0, $navEndIdx + 1));
$footerContent = implode("", array_slice($lines, $footerStartIdx));

// Replace the top of headerContent to insert our PHP logic
$phpLogic = <<<EOT
<?php
require_once __DIR__ . '/bootstrap/app.php';

\$modules = [
    'core-hr' => [
        'title' => 'Core HR & People',
        'tagline' => 'Your single source of truth for everything HR.',
        'overview' => 'Centralized employee directory & profiles, documents, org chart, and multi-tenant capabilities, giving you total visibility into your workforce.',
        'features' => [
            'Centralized employee directory & profiles',
            'Secure document storage',
            'Dynamic org charts',
            'Multi-tenant architecture'
        ],
        'benefits' => 'Built for HR leaders and operations teams who need an organized, accessible, and secure foundation for managing personnel data.'
    ],
    'ats' => [
        'title' => 'ATS Pipeline',
        'tagline' => 'Hire top talent faster and smarter.',
        'overview' => 'Manage your entire recruiting pipeline by stage, from sourcing to hiring, with advanced match scoring and seamless one-click hire-to-employee onboarding.',
        'features' => [
            'Custom recruiting pipelines',
            'AI-assisted match scoring',
            'Résumé management and parsing',
            'Interview scheduling & scorecards',
            'Talent pools for future hiring',
            'One-click hire-to-employee workflow'
        ],
        'benefits' => 'Designed for recruiters and hiring managers who want a streamlined process to evaluate, track, and close top candidates efficiently.'
    ],
    'payroll' => [
        'title' => 'Enterprise Payroll',
        'tagline' => 'Accurate, efficient, and compliant payroll processing.',
        'overview' => 'Designed for PH statutory compliance, managing complex deductions and specialized payroll configurations per company with ease.',
        'features' => [
            'Designed for Philippine statutory deductions (SSS, PhilHealth, Pag-IBIG, BIR withholding)',
            '13th-month pay calculations',
            'De-minimis benefits handling',
            'Per-company configuration capabilities',
            'Detailed digital payslips'
        ],
        'benefits' => 'Ideal for payroll officers and finance teams needing a reliable system tailored to local requirements (pending final CPA validation).'
    ],
    'service-desk' => [
        'title' => 'Service Desk',
        'tagline' => 'Centralize HR and IT support requests.',
        'overview' => 'Empower your employees with an intuitive portal for IT and HR ticketing, complete with SLA tracking, attachments, and dedicated agent queues.',
        'features' => [
            'Unified IT/HR ticketing system',
            'Service Level Agreement (SLA) tracking',
            'File attachments and documentation',
            'Dedicated agent queues for rapid resolution'
        ],
        'benefits' => 'Perfect for support teams and administrators aiming to deliver prompt, organized internal services to all employees.'
    ],
    'employee-relations' => [
        'title' => 'Employee Relations',
        'tagline' => 'Handle cases with compliance and care.',
        'overview' => 'A structured case management system for incidents and investigations, featuring integrated PH labor-law guidance to ensure fair resolutions.',
        'features' => [
            'Incident and investigation case management',
            'Integrated PH labor-law guidance',
            'Secure and confidential documentation',
            'Resolution tracking'
        ],
        'benefits' => 'Essential for HR professionals handling sensitive workplace issues securely and compliantly.'
    ],
    'attendance' => [
        'title' => 'Attendance & Leaves',
        'tagline' => 'Simplify time tracking and time-off requests.',
        'overview' => 'A comprehensive module to monitor attendance, track leave requests, manage approvals, and maintain accurate leave balances in real time.',
        'features' => [
            'Real-time time tracking',
            'Automated leave requests and multi-level approvals',
            'Up-to-date leave balances',
            'Absence reporting and trends'
        ],
        'benefits' => 'Built for managers and employees to ensure clear, transparent, and hassle-free scheduling and time-off management.'
    ]
];

\$slug = \$_GET['module'] ?? '';
if (!isset(\$modules[\$slug])) {
    header("Location: index.php");
    exit;
}

\$module = \$modules[\$slug];
\$loggedIn = isLoggedIn() && (!isset(\$_SESSION['must_change_password']) || \$_SESSION['must_change_password'] !== true);
?>
EOT;

// Replace the original top php block
$headerContent = preg_replace('/<\?php.*?\?>/s', $phpLogic, $headerContent, 1);

// We need to add some specific styling for the solutions page content
$extraStyles = <<<EOT
<style>
    .module-hero {
        padding: 140px 48px 80px;
        text-align: center;
        background: linear-gradient(180deg, var(--bg) 0%, var(--bg2) 100%);
    }
    .module-hero h1 {
        font-size: 3.5rem;
        color: #fff;
        margin-bottom: 20px;
        letter-spacing: -0.03em;
    }
    .module-hero .tagline {
        font-size: 1.5rem;
        color: var(--green);
        margin-bottom: 40px;
    }
    .module-content {
        max-width: 900px;
        margin: 0 auto;
        padding: 0 48px 100px;
    }
    .module-section {
        background: var(--bg3);
        border: 1px solid var(--border2);
        border-radius: 12px;
        padding: 40px;
        margin-bottom: 40px;
    }
    .module-section h2 {
        color: #fff;
        margin-bottom: 20px;
        font-size: 1.75rem;
    }
    .module-section p {
        font-size: 1.125rem;
        color: var(--text-c);
        margin-bottom: 20px;
        line-height: 1.6;
    }
    .feature-list {
        list-style: none;
        padding: 0;
    }
    .feature-list li {
        position: relative;
        padding-left: 30px;
        margin-bottom: 15px;
        font-size: 1.1rem;
        color: var(--text);
    }
    .feature-list li::before {
        content: '\\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: var(--green);
    }
    .cta-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-top: 40px;
    }
</style>
EOT;

$headerContent = str_replace('</head>', $extraStyles . "\n</head>", $headerContent);

$bodyContent = <<<EOT

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
        <div class="module-section">
            <h2>Overview</h2>
            <p><?= htmlspecialchars(\$module['overview']) ?></p>
        </div>

        <div class="module-section">
            <h2>Key Features</h2>
            <ul class="feature-list">
                <?php foreach (\$module['features'] as \$feature): ?>
                    <li><?= htmlspecialchars(\$feature) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="module-section">
            <h2>Who It's For</h2>
            <p><?= htmlspecialchars(\$module['benefits']) ?></p>
        </div>
    </div>
</main>

EOT;

$finalContent = $headerContent . $bodyContent . $footerContent;
file_put_contents($solutionsPath, $finalContent);

echo "Successfully generated solutions.php\n";
