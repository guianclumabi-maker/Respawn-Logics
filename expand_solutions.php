<?php
$solutionsPath = __DIR__ . '/solutions.php';
$content = file_get_contents($solutionsPath);

// The highly expanded new modules array
$phpLogic = <<<EOT
\$modules = [
    'core-hr' => [
        'title' => 'Core HR & People',
        'tagline' => 'One source of truth for every person on your team. Enterprise-grade security out of the box.',
        'intro' => "When employee data lives in scattered spreadsheets, email threads, and someone's desktop folder, mistakes are inevitable and hours vanish into manual lookups. The cost of disorganized data isn't just time—it's compliance risks, security vulnerabilities, and shattered employee trust.\n\nCore HR & People replaces all of it with a single, always-current system of record that your entire organization can actually trust. Every profile, document, role, and reporting line lives in one securely encrypted place. Built with strict role-based access control (RBAC), the right information is one search away, and your team can stop chasing it and start using it.",
        'features' => [
            "Centralized Employee Directory: Every profile, emergency contact detail, and employment record in one place, protected by 256-bit encryption.",
            "Secure Document Vault: Contracts, government IDs, and sensitive records stored with strict tenant isolation and granular access control.",
            "Live Dynamic Org Chart: See reporting lines, span of control, and structure update automatically in real-time as your company scales.",
            "Zero-Trust Role-Based Access: People see exactly what they should—nothing more, nothing less. Managers only see their downlines.",
            "Multi-Tenant by Design: Complete cryptographic data separation between organizations, built into the architecture from day one.",
            "Immutable Audit-Ready Logs: A clear, unalterable trail of who changed what, when, and from where. Ready for your next ISO or SOC audit."
        ],
        'trust_badges' => [
            "SOC2 Compliant Architecture",
            "AES-256 Data Encryption",
            "99.99% Guaranteed Uptime"
        ],
        'workflow' => [
            ['title' => 'Onboard', 'desc' => 'Employees enter their details securely via self-service portals.'],
            ['title' => 'Verify', 'desc' => 'HR reviews and approves documents with a single click.'],
            ['title' => 'Manage', 'desc' => 'Data flows seamlessly into Payroll, ATS, and Attendance modules.']
        ],
        'why' => "Core HR is the foundation everything else builds on. Get your people data clean, secure, and centralized, and every other module—payroll, ATS, attendance—runs on data you can trust. Security isn't an afterthought here; it's the core primitive.",
        'cta' => "Give your team a single, secure home for everything people-related."
    ],
    'ats' => [
        'title' => 'ATS Pipeline',
        'tagline' => 'Hire top talent faster—and never lose a great candidate to a messy process.',
        'intro' => "Great hiring dies in the cracks: résumés buried in inboxes, interviews that slip, candidates ghosted because no one knew whose turn it was. In a competitive market, top tier talent judges your company by your hiring process. If it's chaotic, they walk away.\n\nATS Pipeline gives your whole hiring team one shared, visual pipeline where every candidate moves cleanly from sourced to signed. From AI-assisted match scoring to one-click hire-to-employee onboarding, it removes the administrative busywork so your recruiters can spend their time on the only thing that matters—building relationships and closing the right people.",
        'features' => [
            "Visual Stage Pipeline: Drag candidates from Applied to Hired with full visibility for the whole team. Spot bottlenecks instantly.",
            "AI-Powered Match Scoring: Surface the strongest candidates automatically against each role's specific requirements, removing human bias.",
            "GDPR-Compliant Résumé Management: Secure upload, storage, and quick access to every candidate's documents with automated data retention policies.",
            "Structured Interviews & Scorecards: Schedule interviews directly and capture structured, comparable feedback to make objective hiring decisions.",
            "Dynamic Talent Pools: Keep promising silver-medalist candidates warm for future roles instead of starting your next search from scratch.",
            "Hire-to-Employee in One Click: Turn a signed offer into a fully onboarded employee record instantly. Zero double-data entry."
        ],
        'trust_badges' => [
            "GDPR & CCPA Compliant",
            "Unbiased AI Scoring Algorithms",
            "Secure PII Data Handling"
        ],
        'workflow' => [
            ['title' => 'Source & Score', 'desc' => 'AI parses resumes and ranks candidates against job requirements.'],
            ['title' => 'Collaborative Interview', 'desc' => 'Hiring managers submit standardized scorecards to prevent bias.'],
            ['title' => 'Offer & Onboard', 'desc' => 'Approved candidates are moved to HR seamlessly.']
        ],
        'why' => "A disorganized hiring process costs you the best candidates—they accept somewhere else while you deliberate. ATS Pipeline keeps every opportunity moving securely so good people don't fall through the cracks.",
        'cta' => "Build an elite hiring machine your whole team can run from one screen."
    ],
    'payroll' => [
        'title' => 'Enterprise Payroll',
        'tagline' => 'Run Philippine payroll with absolute confidence. Designed for complex statutory reality.',
        'intro' => "Payroll is the one thing you cannot get wrong—people's livelihoods and your legal standing both ride on it. Manual payroll spreadsheets are slow, deeply error-prone, and introduce massive financial risk.\n\nEnterprise Payroll is built around the strict realities of Philippine payroll, automating the complex statutory math so each run is accurate, consistent, and fully explainable. It's designed specifically for PH statutory compliance—SSS, PhilHealth, Pag-IBIG, and BIR withholding—with the rate logic kept in versioned tables so it adapts as the law changes. Whether you have 50 employees or 5,000, our engine scales to compute your payroll with pinpoint accuracy.",
        'features' => [
            "Built for PH Statutory Deductions: Computes SSS, PhilHealth, Pag-IBIG, and BIR withholding tax based on the latest government brackets.",
            "Advanced 13th-Month & De-Minimis: Handles non-taxable allowances, pro-rated 13th month, and annualized tax calculations effortlessly.",
            "Versioned Rate Tables: Statutory changes are handled as data updates, meaning historical payroll runs stay completely accurate and immutable.",
            "Employer-Share Tracking: Get crystal clear visibility into the employer-side costs for accurate financial forecasting and remittance.",
            "Highly Configurable Pay Schedules: Supports semi-monthly, monthly, or weekly runs with custom earning and deduction components tailored to your business.",
            "Transparent Digital Payslips: Clear, easy-to-read breakdowns that employees can access securely, reducing payroll dispute tickets."
        ],
        'trust_badges' => [
            "Designed for BIR & Statutory Compliance",
            "Immutable Historical Records",
            "Bank-Grade Security"
        ],
        'workflow' => [
            ['title' => 'Sync Attendance', 'desc' => 'Approved hours, OT, and leaves flow directly into the payroll engine.'],
            ['title' => 'Automated Calculation', 'desc' => 'Taxes, statutory deductions, and custom components are computed instantly.'],
            ['title' => 'Review & Disburse', 'desc' => 'Generate bank files, ledgers, and secure payslips with confidence.']
        ],
        'why' => "Enterprise Payroll automates the hard parts so you pay people correctly and on time—and can show your work. Designed and built for the realities of Philippine compliance.",
        'cta' => "Spend less time on payroll, and more time confident it's right."
    ],
    'service-desk' => [
        'title' => 'Service Desk',
        'tagline' => 'Every IT and HR request: tracked, resolved, and measured with precision.',
        'intro' => "When requests come in by chat, email, and hallway conversations, things get dropped. Employees get frustrated, and no one can say what's actually happening. Shadow-IT and undocumented HR requests represent a major operational blind spot.\n\nService Desk turns every IT and HR request into a proper, auditable ticket with an owner, a status, and a strict deadline. Agents work from a single, prioritized queue. Employees always know exactly where their request stands. You finally get the rich data needed to see where your organization's time really goes—and exactly where to improve efficiency.",
        'features' => [
            "Unified Omnichannel Ticketing: Capture every IT, HR, and Facilities request in one centralized place. Nothing is ever lost or forgotten.",
            "Strict SLAs & Escalation Matrix: Set response and resolution targets. Automatic escalations ensure urgent issues never sit idle.",
            "Intelligent Agent Queues: A clear, shared workload where AI routing ensures the right specialist picks up the right ticket immediately.",
            "Secure File Attachments: Screenshots, logs, and confidential files attached securely to each ticket with access restrictions.",
            "Automated Categorization: Send requests to the right team automatically based on smart tagging and department rules.",
            "Full Lifecycle Visibility: Employees can track their own tickets end-to-end, dramatically reducing 'status update' follow-up messages."
        ],
        'trust_badges' => [
            "ITIL Aligned Workflows",
            "Granular Data Access",
            "Real-Time SLA Tracking"
        ],
        'workflow' => [
            ['title' => 'Ticket Creation', 'desc' => 'Employees submit structured requests via a clean self-service portal.'],
            ['title' => 'Smart Triage', 'desc' => 'Requests are automatically routed and prioritized based on urgency and category.'],
            ['title' => 'Swift Resolution', 'desc' => 'Agents resolve issues with full context, updating the employee instantly.']
        ],
        'why' => "Untracked requests erode employee trust and bury your operational teams in noise. Service Desk makes support measurable and highly accountable, so nothing slips through the cracks.",
        'cta' => "Give your team support that's organized, lightning-fast, and deeply accountable."
    ],
    'employee-relations' => [
        'title' => 'Employee Relations',
        'tagline' => 'Handle sensitive cases with rigorous structure, absolute discretion, and a clear record.',
        'intro' => "Workplace issues—grievances, disciplinary incidents, sensitive investigations—are exceptionally high-stakes. They are dangerously easy to mishandle when they live in someone's private notes or memory. Mishandled cases lead to catastrophic legal liabilities and a toxic culture.\n\nEmployee Relations gives you a confidential, highly structured environment to document and manage every case from initial report to final resolution. With military-grade access controls and a complete, tamper-proof audit trail, you protect everyone involved and maintain a completely defensible record. Handle the hardest situations the right way, every single time.",
        'features' => [
            "Centralized Case Management: Log, track, and resolve sensitive incidents, complaints, and grievances in one ultra-secure vault.",
            "Structured Investigation Workflow: Move cases systematically through clear stages—from initial report, to fact-finding, to disciplinary action or closure.",
            "Absolute Confidentiality Controls: Strict Need-to-Know access. Only explicitly authorized personnel can see or acknowledge sensitive cases.",
            "Comprehensive Investigation Tracking: Keep interview notes, evidence attachments, and outcomes rigorously organized and interlinked.",
            "PH Labor-Law Best Practices: Built-in references and workflow gates to help you strictly follow administrative due process.",
            "Tamper-Proof Audit Trail: A cryptographically sound, defensible record of every view, edit, and action taken on a case."
        ],
        'trust_badges' => [
            "Strict Need-to-Know Access",
            "Tamper-Proof Audit Logs",
            "Supports Due Process"
        ],
        'workflow' => [
            ['title' => 'Secure Reporting', 'desc' => 'Incidents are logged securely with initial evidence and statements.'],
            ['title' => 'Confidential Investigation', 'desc' => 'Authorized HR personnel conduct fact-finding within an isolated workspace.'],
            ['title' => 'Defensible Resolution', 'desc' => 'Actions are taken with a complete, auditable paper trail backing every decision.']
        ],
        'why' => "Mishandled employee relations cases create massive legal, financial, and human risk. This module provides a disciplined, incredibly discreet process that fiercely protects your people and your organization's integrity.",
        'cta' => "Manage the hardest conversations with extreme care and a bulletproof paper trail."
    ],
    'attendance' => [
        'title' => 'Attendance & Leaves',
        'tagline' => 'Know exactly who\'s in, who\'s out, and who\'s owed—without the spreadsheet gymnastics.',
        'intro' => "Tracking attendance and leave by hand is a tedious, universally despised chore that is highly prone to human error. Those errors don't just stay in attendance—they flow straight into payroll, causing underpayments, overpayments, and destroyed morale.\n\nAttendance & Leaves automates the entire complex cycle: time-in/time-out, leave requests, managerial approvals, and accrual balances in one real-time, unified view. Employees request leave in a single click, managers approve without friction, and balances stay mathematically perfect. Everyone trusts the numbers, and HR never has to manually reconcile a timesheet again.",
        'features' => [
            "Frictionless Time Tracking: Highly accurate attendance records via biometric integration or secure web-clock, eliminating manual tallying and buddy-punching.",
            "Automated Leave Workflows: A clean, self-service request-to-approval pipeline for employees and multi-level approvals for managers.",
            "Real-Time Accrual Balances: Leave credits that update automatically based on company policy, ensuring no one takes time they haven't earned.",
            "Managerial Dashboard: Fast, clear approval queues for team leads to review schedules, overlapping leaves, and team availability.",
            "Dynamic Schedules & Shifts: Organize complex shift patterns, rotating rosters, and flexible working hours seamlessly.",
            "Payroll-Ready Data Sync: Approved attendance, overtime, and leave data flow seamlessly into the Enterprise Payroll engine without manual exports."
        ],
        'trust_badges' => [
            "Zero Data-Entry Errors",
            "Seamless Payroll Sync",
            "Automated Accrual Logic"
        ],
        'workflow' => [
            ['title' => 'Track Time & Request', 'desc' => 'Employees log hours and request time off via intuitive self-service.'],
            ['title' => 'Managerial Approval', 'desc' => 'Managers review requests against team schedules and approve instantly.'],
            ['title' => 'Sync to Payroll', 'desc' => 'Perfectly reconciled timesheets are pushed to payroll without human intervention.']
        ],
        'why' => "Attendance errors quietly become payroll errors and severe employee frustration. Automate the tracking, accruals, and approvals once, and the numbers stay permanently right.",
        'cta' => "Replace the fragile attendance spreadsheet with a system your whole company can trust."
    ]
];
EOT;

// Replace old modules array with new
$content = preg_replace('/\$modules = \[.*?\];/s', $phpLogic, $content, 1);

// Add the CSS for the new trust badges and workflow sections
$newCss = <<<EOT
    .trust-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        margin-top: 30px;
        margin-bottom: 40px;
    }
    .trust-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(0, 224, 122, 0.05);
        border: 1px solid rgba(0, 224, 122, 0.2);
        color: var(--green);
        padding: 8px 16px;
        border-radius: 50px;
        font-size: 0.85rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        box-shadow: 0 0 10px rgba(0, 224, 122, 0.05);
    }
    .trust-badge i {
        font-size: 1rem;
    }
    .workflow-section {
        margin-top: 60px;
        margin-bottom: 40px;
    }
    .workflow-section h2 {
        font-size: 1.8rem;
        color: #fff;
        margin-bottom: 30px;
        border-bottom: 1px solid var(--border2);
        padding-bottom: 15px;
    }
    .workflow-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 30px;
    }
    .workflow-step {
        background: rgba(255, 255, 255, 0.01);
        border: 1px solid var(--border2);
        border-radius: 8px;
        padding: 25px;
        position: relative;
    }
    .workflow-step::before {
        content: counter(step-counter);
        counter-increment: step-counter;
        position: absolute;
        top: -15px;
        left: 20px;
        background: var(--bg2);
        border: 1px solid var(--green);
        color: var(--green);
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        font-weight: bold;
        font-family: 'JetBrains Mono', monospace;
    }
    .workflow-grid {
        counter-reset: step-counter;
    }
    .workflow-step h4 {
        color: #fff;
        margin-top: 10px;
        margin-bottom: 10px;
        font-size: 1.1rem;
    }
    .workflow-step p {
        color: var(--text-mid);
        font-size: 0.9rem;
        line-height: 1.5;
        margin: 0;
    }
EOT;

// Inject CSS
$content = str_replace('.feature-card h3 {', $newCss . "\n    .feature-card h3 {", $content);

// Update HTML rendering to include trust_badges and workflow
$htmlReplacement = <<<EOT
        <div class="module-section module-intro">
            <?php 
            \$paragraphs = explode("\\n\\n", \$module['intro']);
            foreach (\$paragraphs as \$p) {
                echo "<p>" . htmlspecialchars(\$p) . "</p>";
            }
            ?>
            
            <?php if (isset(\$module['trust_badges'])): ?>
            <div class="trust-badges">
                <?php foreach (\$module['trust_badges'] as \$badge): ?>
                    <div class="trust-badge">
                        <i class="fa-solid fa-shield-check"></i>
                        <?= htmlspecialchars(\$badge) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="module-section">
            <h2 style="color: #fff; border-bottom: 1px solid var(--border2); padding-bottom: 15px; margin-bottom: 25px;">Enterprise Capabilities</h2>
            <div class="features-grid">
                <?php foreach (\$module['features'] as \$feature): ?>
                    <?php 
                    \$parts = explode(":", \$feature, 2);
                    \$title = trim(\$parts[0]);
                    \$desc = isset(\$parts[1]) ? trim(\$parts[1]) : "";
                    ?>
                    <div class="feature-card">
                        <h3><i class="fa-solid fa-check"></i> <?= htmlspecialchars(\$title) ?></h3>
                        <?php if (\$desc): ?>
                            <p><?= htmlspecialchars(\$desc) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <?php if (isset(\$module['workflow'])): ?>
        <div class="module-section workflow-section">
            <h2>The Workflow</h2>
            <div class="workflow-grid">
                <?php foreach (\$module['workflow'] as \$step): ?>
                    <div class="workflow-step">
                        <h4><?= htmlspecialchars(\$step['title']) ?></h4>
                        <p><?= htmlspecialchars(\$step['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="module-section why-card" style="margin-top: 40px; background: rgba(0,224,122,0.05); border: 1px solid rgba(0,224,122,0.2); padding: 30px; border-radius: 8px;">
            <h3 style="color: #fff; margin-bottom: 15px; font-size: 1.3rem;">Why Trust Us?</h3>
EOT;

$content = preg_replace('/<div class="module-section module-intro">.*?<div class="module-section why-card"/s', $htmlReplacement . ' <div class="module-section why-card"', $content);
$content = str_replace('<h3 style="color: #fff; margin-bottom: 15px; font-size: 1.3rem;">Why it matters</h3>', '', $content); // Clean up old why title if it exists

file_put_contents($solutionsPath, $content);
echo "Successfully expanded solutions.php\n";
