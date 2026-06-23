<?php
require_once __DIR__ . '/bootstrap/app.php';

$modules = [
    'core-hr' => [
        'title' => 'Core HR & People',
        'tagline' => 'One source of truth for every person on your team. Enterprise-grade security out of the box.',
        'intro' => "When employee data lives in scattered spreadsheets, email threads, and someone's desktop folder, mistakes are inevitable and hours vanish into manual lookups. The cost of disorganized data isn't just time—it's compliance risks, security vulnerabilities, and shattered employee trust.

Core HR & People replaces all of it with a single, always-current system of record that your entire organization can actually trust. Every profile, document, role, and reporting line lives in one securely encrypted place. Built with strict role-based access control (RBAC), the right information is one search away, and your team can stop chasing it and start using it.",
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
        'why' => "Core HR is the foundation everything else builds on. Get your people data clean, secure, and centralized, and every other module—payroll, ATS, attendance—runs on data you can trust. Security isn't an afterthought here; it's the core primitive.",
        'cta' => "Give your team a single, secure home for everything people-related.",
        'problem' => "HR teams lose hours hunting for employee data scattered across spreadsheets, inboxes, and folders — and every stray copy is a chance for an error or a leak.",
        'steps' => [
            ['title' => 'Bring your people in', 'desc' => 'Add or import employees in minutes.'],
            ['title' => 'One secure source of truth', 'desc' => 'Profiles, documents, and org chart stay current in one place.'],
            ['title' => 'Everything else builds on it', 'desc' => 'Payroll, ATS, and Attendance all read from the same trusted data.']
        ],
        'connects_to' => [
            ['slug' => 'ats', 'label' => 'ATS Pipeline', 'blurb' => 'Hires flow straight into a clean employee record'],
            ['slug' => 'payroll', 'label' => 'Enterprise Payroll', 'blurb' => 'Accurate people data means correct pay'],
            ['slug' => 'attendance', 'label' => 'Attendance & Leaves', 'blurb' => 'Profiles power time tracking and leave']
        ],
        'faqs' => [
            ['q' => "Is my company's data isolated from others?", 'a' => "Yes — strict multi-tenant separation."],
            ['q' => "Who can see employee records?", 'a' => "Role-based access; people see only what they should."],
            ['q' => "Can I store documents securely?", 'a' => "Yes — contracts and IDs in a protected, access-controlled vault."]
        ]
    ],
    'ats' => [
        'title' => 'ATS Pipeline',
        'tagline' => 'Hire top talent faster—and never lose a great candidate to a messy process.',
        'intro' => "Great hiring dies in the cracks: résumés buried in inboxes, interviews that slip, candidates ghosted because no one knew whose turn it was. In a competitive market, top tier talent judges your company by your hiring process. If it's chaotic, they walk away.

ATS Pipeline gives your whole hiring team one shared, visual pipeline where every candidate moves cleanly from sourced to signed. From AI-assisted match scoring to one-click hire-to-employee onboarding, it removes the administrative busywork so your recruiters can spend their time on the only thing that matters—building relationships and closing the right people.",
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
        'why' => "A disorganized hiring process costs you the best candidates—they accept somewhere else while you deliberate. ATS Pipeline keeps every opportunity moving securely so good people don't fall through the cracks.",
        'cta' => "Build an elite hiring machine your whole team can run from one screen.",
        'problem' => "Great candidates slip away when résumés are buried in inboxes and no one knows whose turn it is to move.",
        'steps' => [
            ['title' => 'Open the role', 'desc' => 'Post a job and collect candidates in one pipeline.'],
            ['title' => 'Move them through stages', 'desc' => 'Drag candidates from Applied to Hired with match scoring and scorecards.'],
            ['title' => 'Hire to onboard in one click', 'desc' => 'A hire becomes an onboarded employee instantly.']
        ],
        'connects_to' => [
            ['slug' => 'core-hr', 'label' => 'Core HR & People', 'blurb' => 'Hires become full employee records automatically'],
            ['slug' => 'payroll', 'label' => 'Enterprise Payroll', 'blurb' => 'New hires are ready to be paid'],
            ['slug' => 'service-desk', 'label' => 'Service Desk', 'blurb' => 'Onboarding requests tracked end to end']
        ],
        'faqs' => [
            ['q' => "Can I store and manage résumés?", 'a' => "Yes — secure upload and quick access per candidate."],
            ['q' => "Can I schedule interviews?", 'a' => "Yes, with structured scorecards for comparable feedback."],
            ['q' => "What happens when I hire someone?", 'a' => "They're enrolled as an employee in Core HR in one click."]
        ]
    ],
    'payroll' => [
        'title' => 'Enterprise Payroll',
        'tagline' => 'Run Philippine payroll with absolute confidence. Designed for complex statutory reality.',
        'intro' => "Payroll is the one thing you cannot get wrong—people's livelihoods and your legal standing both ride on it. Manual payroll spreadsheets are slow, deeply error-prone, and introduce massive financial risk.

Enterprise Payroll is built around the strict realities of Philippine payroll, automating the complex statutory math so each run is accurate, consistent, and fully explainable. It's designed specifically for PH statutory compliance—SSS, PhilHealth, Pag-IBIG, and BIR withholding—with the rate logic kept in versioned tables so it adapts as the law changes. Whether you have 50 employees or 5,000, our engine scales to compute your payroll with pinpoint accuracy.",
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
        'why' => "Enterprise Payroll automates the hard parts so you pay people correctly and on time—and can show your work. Designed and built for the realities of Philippine compliance.",
        'cta' => "Spend less time on payroll, and more time confident it's right.",
        'problem' => "Manual payroll spreadsheets are slow and error-prone — and one miscalculation hits both your people and your legal standing.",
        'steps' => [
            ['title' => 'Configure once', 'desc' => 'Set pay schedules and components per company.'],
            ['title' => 'Run payroll', 'desc' => 'Statutory deductions are computed automatically for the period.'],
            ['title' => 'Pay and report', 'desc' => 'Generate payslips and remittance-ready figures.']
        ],
        'connects_to' => [
            ['slug' => 'core-hr', 'label' => 'Core HR & People', 'blurb' => 'Runs on your verified employee data'],
            ['slug' => 'attendance', 'label' => 'Attendance & Leaves', 'blurb' => 'Hours and leave feed straight into pay'],
            ['slug' => 'ats', 'label' => 'ATS Pipeline', 'blurb' => 'New hires become payees automatically']
        ],
        'faqs' => [
            ['q' => "Which contributions are handled?", 'a' => "Designed for SSS, PhilHealth, Pag-IBIG, and BIR withholding."],
            ['q' => "Does it handle 13th-month pay?", 'a' => "Yes, with de-minimis handling aligned to current thresholds."],
            ['q' => "What happens when rates change?", 'a' => "Rates live in versioned tables, so updates are data changes and past runs stay accurate."]
        ]
    ],
    'service-desk' => [
        'title' => 'Service Desk',
        'tagline' => 'Every IT and HR request: tracked, resolved, and measured with precision.',
        'intro' => "When requests come in by chat, email, and hallway conversations, things get dropped. Employees get frustrated, and no one can say what's actually happening. Shadow-IT and undocumented HR requests represent a major operational blind spot.

Service Desk turns every IT and HR request into a proper, auditable ticket with an owner, a status, and a strict deadline. Agents work from a single, prioritized queue. Employees always know exactly where their request stands. You finally get the rich data needed to see where your organization's time really goes—and exactly where to improve efficiency.",
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
        'why' => "Untracked requests erode employee trust and bury your operational teams in noise. Service Desk makes support measurable and highly accountable, so nothing slips through the cracks.",
        'cta' => "Give your team support that's organized, lightning-fast, and deeply accountable.",
        'problem' => "When IT and HR requests arrive by chat and hallway, they get dropped — and no one can prove what happened.",
        'steps' => [
            ['title' => 'Submit a ticket', 'desc' => 'Employees raise IT or HR requests in one place.'],
            ['title' => 'Route and prioritize', 'desc' => 'Tickets get an owner, a priority, and an SLA.'],
            ['title' => 'Resolve and measure', 'desc' => 'Agents work one queue; everyone sees status.']
        ],
        'connects_to' => [
            ['slug' => 'core-hr', 'label' => 'Core HR & People', 'blurb' => 'Requester identity and context built in'],
            ['slug' => 'employee-relations', 'label' => 'Employee Relations', 'blurb' => 'Escalate sensitive matters into a confidential case']
        ],
        'faqs' => [
            ['q' => "Can employees attach files?", 'a' => "Yes — screenshots and documents per ticket."],
            ['q' => "Can I set response targets?", 'a' => "Yes, with SLAs and priorities."],
            ['q' => "Who can see tickets?", 'a' => "Access is controlled by role."]
        ]
    ],
    'employee-relations' => [
        'title' => 'Employee Relations',
        'tagline' => 'Handle sensitive cases with rigorous structure, absolute discretion, and a clear record.',
        'intro' => "Workplace issues—grievances, disciplinary incidents, sensitive investigations—are exceptionally high-stakes. They are dangerously easy to mishandle when they live in someone's private notes or memory. Mishandled cases lead to catastrophic legal liabilities and a toxic culture.

Employee Relations gives you a confidential, highly structured environment to document and manage every case from initial report to final resolution. With military-grade access controls and a complete, tamper-proof audit trail, you protect everyone involved and maintain a completely defensible record. Handle the hardest situations the right way, every single time.",
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
        'why' => "Mishandled employee relations cases create massive legal, financial, and human risk. This module provides a disciplined, incredibly discreet process that fiercely protects your people and your organization's integrity.",
        'cta' => "Manage the hardest conversations with extreme care and a bulletproof paper trail.",
        'problem' => "Sensitive cases handled in private notes create legal risk and quietly erode trust.",
        'steps' => [
            ['title' => 'Log it confidentially', 'desc' => 'Open a case with restricted visibility.'],
            ['title' => 'Work the process', 'desc' => 'Move through a structured workflow with documented actions.'],
            ['title' => 'Resolve with a record', 'desc' => 'Close with a complete, defensible audit trail.']
        ],
        'connects_to' => [
            ['slug' => 'core-hr', 'label' => 'Core HR & People', 'blurb' => 'Full employee context for every case'],
            ['slug' => 'service-desk', 'label' => 'Service Desk', 'blurb' => 'Receive escalations from support']
        ],
        'faqs' => [
            ['q' => "Who can access cases?", 'a' => "Only authorized roles — strict confidentiality."],
            ['q' => "Is there an audit trail?", 'a' => "Yes — every action is recorded."],
            ['q' => "Does it support due process?", 'a' => "Built-in Philippine labor-law guidance to help you follow it."]
        ]
    ],
    'attendance' => [
        'title' => 'Attendance & Leaves',
        'tagline' => 'Know exactly who\'s in, who\'s out, and who\'s owed—without the spreadsheet gymnastics.',
        'intro' => "Tracking attendance and leave by hand is a tedious, universally despised chore that is highly prone to human error. Those errors don't just stay in attendance—they flow straight into payroll, causing underpayments, overpayments, and destroyed morale.

Attendance & Leaves automates the entire complex cycle: time-in/time-out, leave requests, managerial approvals, and accrual balances in one real-time, unified view. Employees request leave in a single click, managers approve without friction, and balances stay mathematically perfect. Everyone trusts the numbers, and HR never has to manually reconcile a timesheet again.",
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
        'why' => "Attendance errors quietly become payroll errors and severe employee frustration. Automate the tracking, accruals, and approvals once, and the numbers stay permanently right.",
        'cta' => "Replace the fragile attendance spreadsheet with a system your whole company can trust.",
        'problem' => "Tracking time and leave by hand is tedious — and the errors flow straight into payroll.",
        'steps' => [
            ['title' => 'Track time and requests', 'desc' => 'Employees clock time and request leave.'],
            ['title' => 'Approve in a click', 'desc' => 'Managers handle approvals from a clear queue.'],
            ['title' => 'Balances stay right', 'desc' => 'Credits update automatically and feed payroll.']
        ],
        'connects_to' => [
            ['slug' => 'payroll', 'label' => 'Enterprise Payroll', 'blurb' => 'Hours and leave flow directly into pay'],
            ['slug' => 'core-hr', 'label' => 'Core HR & People', 'blurb' => 'Built on employee profiles']
        ],
        'faqs' => [
            ['q' => "Are leave balances real-time?", 'a' => "Yes — they update automatically."],
            ['q' => "Can managers approve quickly?", 'a' => "Yes, from a dedicated approval queue."],
            ['q' => "Does it connect to payroll?", 'a' => "Yes — attendance and leave feed payroll directly."]
        ]
    ]
];

$slug = $_GET['module'] ?? '';
if (!isset($modules[$slug])) {
    header("Location: index.php");
    exit;
}

$module = $modules[$slug];
$loggedIn = isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true);
?>
<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($module['title']) ?> - Respawn Logics</title>
    <meta name="description" content="<?= htmlspecialchars(substr(strip_tags($module['intro']), 0, 160)) ?>...">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <script src="https://unpkg.com/lucide@latest" defer></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            lucide.createIcons();
        });
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= url('/assets/favicon.svg') ?>">
    <style>
        :root {
            --green:     #00e07a;
            --green-dim: #00b862;
            --amber:     #f5a623;
            --blue:      #4f8ef7;
            --purple:    #9b6dff;
            --red:       #ff4d6a;
            --teal:      #00c9b1;

            /* Background: deep navy-slate, not pure black */
            --bg:        #0b0f1a;
            --bg2:       #0f1422;
            --bg3:       #141929;
            --bg4:       #1a2035;

            --border:    rgba(0, 224, 122, 0.1);
            --border2:   rgba(255, 255, 255, 0.07);
            --border3:   rgba(255, 255, 255, 0.04);

            --text:      #c8d0e0;
            --text-dim:  #5e6a82;
            --text-mid:  #8b95a8;

            --mono:      'JetBrains Mono', monospace;
            --sans:      'Space Grotesk', sans-serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }

        body {
            background-color: transparent;
            font-family: var(--sans);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        /* Subtle noise texture */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            opacity: 0.018;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            background-size: 128px 128px;
            pointer-events: none;
            z-index: 9998;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 48px;
            height: 62px;
            background: rgba(11, 15, 26, 0.88);
            backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border2);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            font-family: var(--mono);
            font-size: 0.9375rem;
            font-weight: 700;
            color: #fff;
        }

        .logo-mark {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--green), #00b8ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            box-shadow: 0 8px 20px rgba(0, 224, 122, 0.25);
            flex-shrink: 0;
        }

        .version-pill {
            font-family: var(--mono);
            font-size: 0.5625rem;
            font-weight: 700;
            color: var(--green);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 36px;
        }

        .nav-links a {
            font-size: 0.875rem;
            color: var(--text-dim);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover { color: #fff; }

        .nav-cta {
            font-family: var(--mono);
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #000 !important;
            background: var(--green);
            padding: 9px 20px;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-cta:hover {
            background: #fff;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,224,122,0.3);
        }

        /* ─── HERO ─── */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 140px 24px 80px;
            position: relative;
            overflow: hidden;
        }

        /* Layered background — navy base with dynamic drifting grid */
        .global-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            pointer-events: none;
            background-color: var(--bg);
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,184,255,0.07) 0%, transparent 70%),
                radial-gradient(ellipse 70% 60% at 80% 100%, rgba(155,109,255,0.06) 0%, transparent 70%),
                radial-gradient(ellipse 50% 50% at 20% 50%, rgba(0,224,122,0.05) 0%, transparent 60%);
        }



        .app-wrapper {
            position: relative;
            z-index: 1;
            overflow-x: hidden;
        }



        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 0 15px rgba(0, 224, 122, 0.02); border-color: rgba(0, 224, 122, 0.05); }
            50% { box-shadow: 0 0 35px rgba(0, 224, 122, 0.15); border-color: rgba(0, 224, 122, 0.3); }
        }

        .hero-badge {
            position: relative;
            z-index: 2;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: var(--mono);
            font-size: 0.6875rem;
            font-weight: 600;
            letter-spacing: 0.1em;
            color: var(--green);
            border: 1px solid rgba(0,224,122,0.22);
            background: rgba(0,224,122,0.05);
            padding: 6px 16px;
            border-radius: 4px;
            margin-bottom: 36px;
        }

        .ping {
            width: 7px; height: 7px;
            border-radius: 50%;
            background: var(--green);
            animation: ping 2s ease infinite;
        }

        @keyframes ping {
            0%, 100% { opacity: 1; box-shadow: 0 0 0 0 rgba(0,224,122,0.5); }
            50% { opacity: 0.6; box-shadow: 0 0 0 4px rgba(0,224,122,0); }
        }

        .hero-h1 {
            position: relative;
            z-index: 2;
            font-size: clamp(3rem, 7.5vw, 5.75rem);
            font-weight: 700;
            line-height: 1.0;
            letter-spacing: -0.04em;
            color: #fff;
            max-width: 880px;
            margin-bottom: 28px;
        }

        .hero-h1 .accent {
            background: linear-gradient(90deg, var(--green), #00c9ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
        }

        .hero-h1 .accent::after {
            content: '_';
            -webkit-text-fill-color: var(--green);
            animation: blink 1.1s step-start infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0; }
        }

        .hero-sub {
            position: relative;
            z-index: 2;
            font-size: 1.1rem;
            color: var(--text-mid);
            max-width: 560px;
            line-height: 1.75;
            margin-bottom: 48px;
            font-weight: 400;
        }

        .hero-actions {
            position: relative;
            z-index: 2;
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 80px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green);
            color: #000;
            font-family: var(--mono);
            font-weight: 700;
            font-size: 0.9rem;
            padding: 13px 28px;
            border-radius: 6px;
            text-decoration: none;
            letter-spacing: 0.03em;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #fff;
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,224,122,0.2);
        }

        .btn-ghost {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: transparent;
            color: var(--text-mid);
            font-family: var(--mono);
            font-size: 0.875rem;
            font-weight: 500;
            padding: 13px 24px;
            border-radius: 6px;
            border: 1px solid var(--border2);
            text-decoration: none;
            transition: all 0.2s;
        }

        .btn-ghost:hover {
            border-color: rgba(255,255,255,0.18);
            color: #fff;
        }

        /* HUD Stats */
        .hud {
            position: relative;
            z-index: 2;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            max-width: 860px;
            width: 100%;
            border: 1px solid var(--border2);
            border-radius: 10px;
            overflow: hidden;
            background: rgba(15,20,34,0.8);
            backdrop-filter: blur(10px);
        }

        .hud-item {
            padding: 22px 28px;
            border-right: 1px solid var(--border2);
            text-align: left;
        }

        .hud-item:last-child { border-right: none; }

        .hud-key {
            font-family: var(--mono);
            font-size: 0.625rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 8px;
        }

        .hud-val {
            font-family: var(--mono);
            font-size: 1.75rem;
            font-weight: 700;
            line-height: 1;
            color: var(--green);
        }

        .hud-val.amber { color: var(--amber); }
        .hud-val.blue  { color: var(--blue);  }

        .hud-desc {
            font-size: 0.75rem;
            color: var(--text-dim);
            margin-top: 5px;
        }

        /* ─── MODULES SECTION ─── */
        .section {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 1180px;
            margin: 0 auto;
            padding: 110px 24px;
        }

        .eyebrow {
            font-family: var(--mono);
            font-size: 0.6875rem;
            color: var(--green);
            letter-spacing: 0.14em;
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 14px;
        }

        .section-h {
            font-size: clamp(1.875rem, 4vw, 2.875rem);
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.03em;
            line-height: 1.12;
            margin-bottom: 14px;
        }

        .section-p {
            font-size: 1rem;
            color: var(--text-mid);
            max-width: 500px;
            line-height: 1.7;
            margin-bottom: 56px;
        }

        /* CATEGORY TABS */
        .module-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            flex-wrap: wrap;
        }

        .tab-btn {
            font-family: var(--mono);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            padding: 7px 16px;
            border-radius: 5px;
            border: 1px solid var(--border2);
            background: transparent;
            color: var(--text-dim);
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover, .tab-btn.active {
            background: var(--bg4);
            border-color: rgba(255,255,255,0.15);
            color: #fff;
        }

        /* MODULE CARDS GRID */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }

        .mod-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 10px;
            padding: 26px 28px;
            position: relative;
            overflow: hidden;
            animation: pulse-glow 4s infinite alternate;
            transition: border-color 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .mod-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 2px;
            opacity: 0;
            transition: opacity 0.2s;
        }

        .mod-card.c-green::before  { background: var(--green); }
        .mod-card.c-blue::before   { background: var(--blue); }
        .mod-card.c-purple::before { background: var(--purple); }
        .mod-card.c-amber::before  { background: var(--amber); }
        .mod-card.c-teal::before   { background: var(--teal); }
        .mod-card.c-red::before    { background: var(--red); }

        .mod-card:hover {
            border-color: rgba(255,255,255,0.12);
            transform: translateY(-3px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.3);
        }

        .mod-card:hover::before { opacity: 1; }

        .mod-card.wide { grid-column: span 2; }

        .mod-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            margin-bottom: 18px;
        }

        .mod-icon.green  { background: rgba(0,224,122,0.1);  color: var(--green); }
        .mod-icon.blue   { background: rgba(79,142,247,0.1); color: var(--blue); }
        .mod-icon.purple { background: rgba(155,109,255,0.1);color: var(--purple); }
        .mod-icon.amber  { background: rgba(245,166,35,0.1); color: var(--amber); }
        .mod-icon.teal   { background: rgba(0,201,177,0.1);  color: var(--teal); }
        .mod-icon.red    { background: rgba(255,77,106,0.1); color: var(--red); }

        .mod-tag {
            display: inline-block;
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            padding: 2px 8px;
            border-radius: 3px;
            margin-bottom: 10px;
        }

        .mod-tag.green  { color: var(--green);  background: rgba(0,224,122,0.08);  border: 1px solid rgba(0,224,122,0.15); }
        .mod-tag.blue   { color: var(--blue);   background: rgba(79,142,247,0.08); border: 1px solid rgba(79,142,247,0.15); }
        .mod-tag.purple { color: var(--purple); background: rgba(155,109,255,0.08);border: 1px solid rgba(155,109,255,0.15); }
        .mod-tag.amber  { color: var(--amber);  background: rgba(245,166,35,0.08); border: 1px solid rgba(245,166,35,0.15); }
        .mod-tag.teal   { color: var(--teal);   background: rgba(0,201,177,0.08);  border: 1px solid rgba(0,201,177,0.15); }
        .mod-tag.red    { color: var(--red);     background: rgba(255,77,106,0.08); border: 1px solid rgba(255,77,106,0.15); }

        .mod-title {
            font-size: 1.0625rem;
            font-weight: 600;
            color: #fff;
            letter-spacing: -0.02em;
            margin-bottom: 8px;
        }

        .mod-desc {
            font-size: 0.875rem;
            color: var(--text-mid);
            line-height: 1.65;
        }

        .mod-badge {
            position: absolute;
            bottom: 18px;
            right: 20px;
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: var(--text-dim);
            opacity: 0.4;
        }

        /* XP bar */
        .xp {
            margin-top: 18px;
        }

        .xp-head {
            display: flex;
            justify-content: space-between;
            font-family: var(--mono);
            font-size: 0.625rem;
            color: var(--text-dim);
            margin-bottom: 5px;
        }

        .xp-track {
            height: 3px;
            background: rgba(255,255,255,0.05);
            border-radius: 2px;
        }

        .xp-fill {
            height: 100%;
            border-radius: 2px;
        }

        /* ─── DIVIDER ─── */
        .divider {
            border: none;
            margin: 80px 0;
        }

        /* ─── PHILOSOPHY SECTION ─── */
        .story-section {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 100px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }

        .story-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 110px 24px;
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 80px;
            align-items: center;
        }

        .terminal {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            overflow: hidden;
            font-family: var(--mono);
        }

        .term-bar {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            background: var(--bg3);
        }

        .t-dot { width: 10px; height: 10px; border-radius: 50%; }
        .t-dot.r { background: #ff5f57; }
        .t-dot.y { background: #febc2e; }
        .t-dot.g { background: #28c840; }

        .term-file {
            font-size: 0.7rem;
            color: var(--text-dim);
            margin-left: 6px;
        }

        .term-body {
            padding: 22px 20px;
            font-size: 0.8rem;
            line-height: 2.1;
        }

        .t-row { display: flex; gap: 10px; }
        .t-p { color: var(--green); flex-shrink: 0; }
        .t-c { color: #fff; }
        .t-o { color: var(--text-dim); padding-left: 18px; font-size: 0.75rem; }
        .t-cm { color: #354050; }
        .t-v { color: var(--amber); }
        .t-gap { height: 0.4rem; }
        .t-cursor { display: inline-block; width: 8px; height: 13px; background: var(--green); animation: blink 1.1s step-start infinite; vertical-align: middle; }

        /* 🎮 BETA CTA 🎮 */
        .beta-wrap {
            display: flex;
            flex-direction: column;
            justify-content: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 110px 24px 60px;
        }

        .beta-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 14px;
            padding: 64px 60px;
            display: grid;
            grid-template-columns: 1fr 240px;
            gap: 60px;
            align-items: center;
            position: relative;
            overflow: hidden;
            animation: pulse-glow 5s infinite alternate;
        }

        .beta-card::before {
            content: '';
            position: absolute;
            top: -80px; right: -80px;
            width: 280px; height: 280px;
            background: radial-gradient(circle, rgba(0,224,122,0.07) 0%, transparent 65%);
            pointer-events: none;
        }

        .beta-card::after {
            content: '';
            position: absolute;
            bottom: -60px; left: 30%;
            width: 200px; height: 200px;
            background: radial-gradient(circle, rgba(79,142,247,0.05) 0%, transparent 65%);
            pointer-events: none;
        }

        .beta-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            font-family: var(--mono);
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--amber);
            background: rgba(245,166,35,0.07);
            border: 1px solid rgba(245,166,35,0.2);
            padding: 4px 12px;
            border-radius: 3px;
            margin-bottom: 22px;
        }

        .beta-h {
            font-size: 2.25rem;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.03em;
            line-height: 1.15;
            margin-bottom: 16px;
        }

        .beta-p {
            font-size: 0.9375rem;
            color: var(--text-mid);
            line-height: 1.75;
            max-width: 480px;
            margin-bottom: 28px;
        }

        .perks {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px 24px;
        }

        .perk {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 0.875rem;
            color: var(--text);
        }

        .perk i {
            color: var(--green);
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .price-panel {
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .price-num {
            font-family: var(--mono);
            font-size: 4rem;
            font-weight: 700;
            color: var(--green);
            line-height: 1;
        }

        .price-tag {
            font-family: var(--mono);
            font-size: 0.6875rem;
            color: var(--text-dim);
            letter-spacing: 0.08em;
            margin-top: 6px;
            margin-bottom: 28px;
        }

        /* 🎮 FOOTER 🎮 */
        footer {
            border-top: 1px solid var(--border2);
            padding: 60px 0 30px;
        }

        .footer-inner {
            max-width: 1600px;
            width: 95%;
            margin: 0 auto;
            padding: 0 24px;
            display: flex;
            flex-direction: column;
            gap: 60px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 40px;
        }

        .footer-col h4 {
            font-family: var(--sans);
            font-size: 0.9rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 20px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-col a {
            font-family: var(--sans);
            font-size: 0.85rem;
            color: var(--text-dim);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-col a:hover {
            color: #fff;
        }

        .footer-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 30px;
            border-top: 1px solid var(--border2);
        }

        .footer-copy {
            font-family: var(--mono);
            font-size: 0.8rem;
            color: var(--text-dim);
        }

        .footer-socials {
            display: flex;
            gap: 20px;
        }

        .footer-socials a {
            color: var(--text-dim);
            text-decoration: none;
            font-size: 1.1rem;
            transition: color 0.2s;
        }

        .footer-socials a:hover { color: #fff; }

        /* ─── RESPONSIVE ─── */
        @media (max-width: 960px) {
            nav { padding: 0 20px; }
            .nav-links a:not(.nav-cta) { display: none; }
            .hero h1 { font-size: 3.5rem; }
            .terminal-window { min-height: 400px; }
            .module-grid { grid-template-columns: repeat(2, 1fr); }
            .mod-card.wide { grid-column: span 2; }
            .story-inner { grid-template-columns: 1fr; gap: 40px; }
            .beta-card { grid-template-columns: 1fr; padding: 36px 28px; }
            .perks { grid-template-columns: 1fr; }
            .price-panel { text-align: left; }
            .footer-bottom { flex-direction: column; gap: 16px; text-align: center; }
        }

        @media (max-width: 600px) {
            .module-grid { grid-template-columns: 1fr; }
            .mod-card.wide { grid-column: span 1; }
            .hero-h1 { font-size: 2.75rem; }
            .hud { grid-template-columns: 1fr 1fr; }
        }
        /* ─── NEW STORY SECTIONS ─── */
        .story-section {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 100px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .story-container {
            max-width: 1600px;
            width: 95%;
            margin: 0 auto;
            position: relative;
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 24px;
            padding: 120px 80px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4);
            animation: pulse-glow 6s infinite alternate;
        }
        @media (max-width: 768px) {
            .story-container { padding: 40px 20px; }
        }
        .story-section h2 {
            font-size: clamp(2rem, 5vw, 3.25rem);
            color: #fff;
            margin-bottom: 24px;
            line-height: 1.15;
            letter-spacing: -0.02em;
        }
        .story-section p.sub {
            font-size: 1.2rem;
            color: var(--text-mid);
            max-width: 800px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }

        /* Journey Flow */
        .journey-flow {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 40px;
            position: relative;
        }
        .journey-node {
            background: rgba(0,224,122,0.05);
            border: 1px solid rgba(0,224,122,0.2);
            padding: 14px 28px;
            border-radius: 50px;
            color: var(--green);
            font-family: var(--mono);
            font-weight: 600;
            font-size: 0.9375rem;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(0,224,122,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .journey-node:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 24px rgba(0,224,122,0.15);
            border-color: rgba(0,224,122,0.4);
        }
        .journey-node i {
            font-size: 1.1em;
            opacity: 0.8;
        }
        .journey-arrow {
            color: var(--text-dim);
            font-size: 1rem;
            opacity: 0.5;
        }

        /* Problems Grid */
        .problems-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 56px;
        }
        @media (max-width: 1024px) {
            .problems-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .problems-grid { grid-template-columns: 1fr; }
        }
        .problem-card {
            background: rgba(255,77,106,0.05);
            border: 1px solid rgba(255,77,106,0.15);
            border-radius: 10px;
            padding: 24px;
            text-align: left;
            color: var(--red);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .problem-card i { font-size: 1.25rem; }
        .solution-block {
            background: linear-gradient(135deg, rgba(0,224,122,0.1), rgba(0,184,255,0.05));
            border: 1px solid rgba(0,224,122,0.3);
            border-radius: 12px;
            padding: 32px;
            font-size: 1.25rem;
            color: #fff;
            font-weight: 600;
            max-width: 800px;
            margin: 0 auto;
        }

        /* 4 Pillars */
        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }
        @media (max-width: 1024px) {
            .pillars-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        @media (max-width: 768px) {
            .pillars-grid {
                grid-template-columns: 1fr;
            }
        }
        .pillar-card {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid var(--border2);
            border-radius: 12px;
            padding: 32px 24px;
            text-align: left;
            animation: pulse-glow 4.5s infinite alternate;
        }
        .pillar-card h3 {
            font-size: 1.25rem;
            color: #fff;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pillar-card h3 i { color: var(--green); }
        .pillar-card ul {
            list-style: none;
            color: var(--text-mid);
            font-size: 0.9375rem;
        }
        .pillar-card ul li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .pillar-card ul li::before {
            content: '\f054';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            font-size: 0.625rem;
            color: var(--green);
        }

        /* Why Teams Choose */
        .why-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }
        @media (max-width: 768px) { .why-grid { grid-template-columns: 1fr; } }
        .why-card {
            background: transparent;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            padding: 40px 32px;
            text-align: left;
            transition: transform 0.3s, border-color 0.3s;
            animation: pulse-glow 5.5s infinite alternate;
        }
        .why-card:hover { transform: translateY(-3px); border-color: rgba(0,224,122,0.3); }
        .why-card i {
            font-size: 2rem;
            color: var(--green);
            margin-bottom: 20px;
        }
        .why-card h3 {
            font-size: 1.375rem;
            color: #fff;
            margin-bottom: 12px;
        }
        .why-card p {
            color: var(--text-mid);
            line-height: 1.6;
        }

        /* Split Section */
        .split-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
            text-align: left;
        }
        @media (max-width: 900px) { .split-section { grid-template-columns: 1fr; text-align: center; } }
        .split-image img {
            width: 100%;
            border-radius: 12px;
            border: 1px solid var(--border2);
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }

        /* Mock Window Screenshot */
        .window-frame {
            border: 1px solid var(--border2);
            border-radius: 6px;
            overflow: hidden;
            background: #060913;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
        }
        .window-header {
            background: #111524;
            border-bottom: 1px solid var(--border3);
            height: 36px;
            display: flex;
            align-items: center;
            padding: 0 16px;
            position: relative;
        }
        .window-dots {
            display: flex;
            gap: 6px;
        }
        .window-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
        }
        .window-dot.close { background: #ff4d6a; }
        .window-dot.minimize { background: #f5a623; }
        .window-dot.maximize { background: #00e07a; }
        .window-url {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            font-family: var(--mono);
            font-size: 0.7rem;
            color: var(--text-dim);
            background: #0b0f1a;
            border: 1px solid var(--border3);
            padding: 2px 30px;
            border-radius: 4px;
            text-align: center;
            white-space: nowrap;
            max-width: 60%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .window-body {
            position: relative;
            background: #0b0f1a;
            display: block;
        }
        .window-screenshot {
            display: block;
            width: 100%;
            height: auto;
            max-height: 480px;
            object-fit: cover;
            object-position: top center;
            border: none;
            transition: opacity 0.3s;
        }

        /* Gaming CTA */
        .gaming-cta {
            padding: 60px 24px;
            width: 100%;
            text-align: center;
            position: relative;
        }
        .gaming-cta .story-container {
            background: linear-gradient(135deg, rgba(0, 224, 122, 0.05), rgba(0, 224, 122, 0.01));
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-color: rgba(0, 224, 122, 0.2);
        }
        .gaming-flow {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
            margin-top: 40px;
        }
        .gaming-step {
            font-family: var(--mono);
            font-size: 1.125rem;
            font-weight: 700;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gaming-step i { color: var(--green); }
        .gaming-divider { color: var(--text-dim); }
    </style>
<style>
    .module-hero {
        padding: 140px 48px 80px;
        text-align: center;
        background: linear-gradient(180deg, var(--bg) 0%, var(--bg2) 100%);
    }
    .module-hero h1 {
        font-size: 3.5rem;
        color: transparent;
        background: linear-gradient(135deg, #fff 0%, #00e07a 100%);
        -webkit-background-clip: text;
        background-clip: text;
        margin-bottom: 20px;
        letter-spacing: -0.03em;
        animation: hero-glow 3s ease-in-out infinite alternate;
    }
    @keyframes hero-glow {
        from { filter: drop-shadow(0 0 10px rgba(0,224,122,0.1)); }
        to { filter: drop-shadow(0 0 20px rgba(0,224,122,0.3)); }
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
        content: '\f00c';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        position: absolute;
        left: 0;
        top: 2px;
        color: var(--green);
    }
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
    .cta-buttons {
        display: flex;
        gap: 20px;
        justify-content: center;
        margin-top: 40px;
    }
</style>
</head>
<body>
    <div class="global-bg"></div>

<div class="app-wrapper">
<!-- NAV -->
<nav>
    <?= renderLogo('navbar') ?>
    <div class="nav-links">
        <a href="index.php#overview">Platform</a>
        <a href="deep_dive.php">Deep Dive</a>
        <a href="design.php">Design</a>
        <a href="index.php#why">Why Us</a>
        <a href="index.php#story">The Story</a>
        <a href="index.php#beta">Beta</a>
        <div style="width: 1px; height: 24px; background: var(--border2); margin: 0 10px;"></div>
        <a href="index.php" class="btn-ghost" style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; padding: 6px 12px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Home
        </a>
        <?php if ($loggedIn): ?>
            <a href="<?= url('/pages/dashboard.php') ?>" class="nav-cta">[ RESUME ]</a>
        <?php else: ?>
            <a href="<?= url('/login.php') ?>" class="nav-cta">[ LOGIN ]</a>
        <?php endif; ?>
    </div>
</nav>

<main>
    <section class="module-hero">
        <h1><?= htmlspecialchars($module['title']) ?></h1>
        <div class="tagline"><?= htmlspecialchars($module['tagline']) ?></div>
        
        <div class="cta-buttons">
            <a href="register.php" class="btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i data-lucide="building"></i> Create Workspace
            </a>
            <?php if (!$loggedIn): ?>
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
            $paragraphs = explode("\n\n", $module['intro']);
            foreach ($paragraphs as $p) {
                echo "<p>" . htmlspecialchars($p) . "</p>";
            }
            ?>
            
            <?php if (isset($module['trust_badges'])): ?>
            <div class="trust-badges">
                <?php foreach ($module['trust_badges'] as $badge): ?>
                    <div class="trust-badge">
                        <i class="fa-solid fa-shield"></i>
                        <?= htmlspecialchars($badge) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- NEW: The Problem band -->
        <div class="module-section problem-band" style="background: rgba(255,77,106,0.05); border: 1px solid rgba(255,77,106,0.15); border-radius: 8px; padding: 30px; margin-bottom: 40px;">
            <h3 style="color: var(--red); margin-bottom: 15px; font-size: 1.3rem;"><i class="fa-solid fa-triangle-exclamation"></i> The Problem</h3>
            <p style="color: var(--text-mid); font-size: 1.1rem; line-height: 1.6;"><?= htmlspecialchars($module['problem']) ?></p>
        </div>

        <!-- NEW: How it works (steps) -->
        <div class="module-section workflow-section">
            <h2>How it works</h2>
            <div class="workflow-grid">
                <?php foreach ($module['steps'] as $step): ?>
                    <div class="workflow-step">
                        <h4><?= htmlspecialchars($step['title']) ?></h4>
                        <p><?= htmlspecialchars($step['desc']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="module-section">
            <h2 style="color: #fff; border-bottom: 1px solid var(--border2); padding-bottom: 15px; margin-bottom: 25px;">Enterprise Capabilities</h2>
            <div class="features-grid">
                <?php foreach ($module['features'] as $feature): 
                    // Split feature into title and description based on the first colon
                    $parts = explode(':', $feature, 2);
                    $featureTitle = $parts[0];
                    $featureDesc = isset($parts[1]) ? trim($parts[1]) : '';
                ?>
                <div class="feature-card">
                    <h3><i class="fa-solid fa-check"></i> <?= htmlspecialchars($featureTitle) ?></h3>
                    <?php if ($featureDesc): ?>
                        <p><?= htmlspecialchars($featureDesc) ?></p>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- NEW: Part of one connected platform -->
        <div class="module-section connected-platform" style="margin-top: 60px;">
            <h2 style="color: #fff; margin-bottom: 30px; border-bottom: 1px solid var(--border2); padding-bottom: 15px;">Part of one connected platform</h2>
            <div class="features-grid">
                <?php foreach ($module['connects_to'] as $conn): ?>
                    <a href="solutions.php?module=<?= urlencode($conn['slug']) ?>" class="feature-card" style="text-decoration: none; display: block;">
                        <h3><i class="fa-solid fa-link" style="color: var(--blue);"></i> <?= htmlspecialchars($conn['label']) ?></h3>
                        <p style="color: var(--text-dim);"><?= htmlspecialchars($conn['blurb']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- NEW: Shared trust band -->
        <div class="module-section trust-band" style="margin-top: 40px; background: rgba(0,224,122,0.05); border: 1px solid rgba(0,224,122,0.2); padding: 40px; border-radius: 8px; text-align: center;">
            <h3 style="color: #fff; margin-bottom: 15px; font-size: 1.5rem;"><i class="fa-solid fa-shield-halved" style="color: var(--green);"></i> Built for the Philippines. Secure by default.</h3>
            <p style="color: var(--text-mid); font-size: 1.1rem; line-height: 1.6; max-width: 700px; margin: 0 auto;">Every module runs on a multi-tenant architecture with strict data isolation between companies, role-based access control, and privacy-minded data handling — and it's built around Philippine workplace realities, not retrofitted from abroad.</p>
        </div>

        <!-- NEW: FAQ -->
        <div class="module-section faq-section" style="margin-top: 60px;">
            <h2 style="color: #fff; margin-bottom: 30px; border-bottom: 1px solid var(--border2); padding-bottom: 15px;">Frequently Asked Questions</h2>
            <div class="faq-grid" style="display: grid; gap: 20px;">
                <?php foreach ($module['faqs'] as $faq): ?>
                    <div class="faq-card" style="background: rgba(255,255,255,0.02); border: 1px solid var(--border2); padding: 25px; border-radius: 8px;">
                        <h4 style="color: #fff; font-size: 1.1rem; margin-bottom: 10px;"><i class="fa-solid fa-circle-question" style="color: var(--blue);"></i> <?= htmlspecialchars($faq['q']) ?></h4>
                        <p style="color: var(--text-mid); line-height: 1.6; margin: 0;"><?= htmlspecialchars($faq['a']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="module-section why-card" style="margin-top: 40px; background: rgba(0,224,122,0.05); border: 1px solid rgba(0,224,122,0.2); padding: 30px; border-radius: 8px;">
            <h3 style="color: #fff; margin-bottom: 15px; font-size: 1.3rem;">Why Trust Us?</h3>
            <p style="color: var(--text-mid); font-size: 1rem; line-height: 1.6;"><?= htmlspecialchars($module['why']) ?></p>
        </div>
    </div>
    
    <div class="closing-cta">
        <h2><?= htmlspecialchars($module['cta']) ?></h2>
        <div class="cta-buttons" style="margin-top: 0;">
            <a href="register.php" class="btn-primary" style="font-size: 1.1rem; padding: 15px 30px;">
                <i data-lucide="building"></i> Create Workspace
            </a>
            <?php if (!$loggedIn): ?>
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
<footer>
    <div class="footer-inner">
        <div class="footer-grid">
            <div class="footer-col">
                <h4>Platform Modules</h4>
                <ul>
                    <li><a href="<?= url('/solutions.php?module=core-hr') ?>">Core HR & People</a></li>
                    <li><a href="<?= url('/solutions.php?module=ats') ?>">ATS Pipeline</a></li>
                    <li><a href="<?= url('/solutions.php?module=payroll') ?>">Enterprise Payroll</a></li>
                    <li><a href="<?= url('/solutions.php?module=service-desk') ?>">Service Desk</a></li>
                    <li><a href="<?= url('/solutions.php?module=employee-relations') ?>">Employee Relations</a></li>
                    <li><a href="<?= url('/solutions.php?module=attendance') ?>">Attendance & Leaves</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Portals</h4>
                <ul>
                    <li><a href="<?= url('/login.php') ?>">Employee Login</a></li>
                    <li><a href="<?= url('/register.php') ?>">Create Workspace</a></li>
                    <li><a href="<?= url('/frontend/dist/index.html#/onboarding') ?>">Enterprise Onboarding</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Resources</h4>
                <ul>
                    <li><a href="<?= url('/submit_ticket.php') ?>">Help Center</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="#story">Our Story</a></li>
                    <li><a href="design.php">Design Philosophy</a></li>
                    <li><a href="#beta">Pricing</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Legal</h4>
                <ul>
                    <li><a href="<?= url('/privacy.php') ?>">Privacy Policy</a></li>
                    <li><a href="<?= url('/terms.php') ?>">Terms of Service</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div class="footer-copy">
                © <?= date('Y') ?> Respawn Logics Inc. &nbsp;·&nbsp; Built in the Philippines <img src="https://flagcdn.com/ph.svg" width="16" alt="PH" style="vertical-align: middle; margin-left: 2px; margin-top: -2px; border-radius: 2px;">
            </div>
            <div class="footer-socials">
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect width="4" height="12" x="2" y="9"/><circle cx="4" cy="4" r="2"/></svg></a>
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l11.733 16h4.267l-11.733 -16z"/><path d="M4 20l6.768 -6.768m2.46 -2.46l6.772 -6.772"/></svg></a>
                <a href="#"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.2c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4"/><path d="M9 18c-4.51 2-5-2-7-2"/></svg></a>
            </div>
        </div>
    </div>
</footer>

<script>
(function() {
    const tabs = document.querySelectorAll('#module-tabs .tab-btn');
    const cards = document.querySelectorAll('.mod-card');
    tabs.forEach(btn => {
        btn.addEventListener('click', function() {
            tabs.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const filter = this.dataset.filter;
            cards.forEach(card => {
                const match = filter === 'all' || card.dataset.filter === filter;
                card.style.display = match ? '' : 'none';
                // Handle wide class visibility in grid
                if (match && card.classList.contains('wide') && filter !== 'all') {
                    card.style.gridColumn = 'span 1';
                } else if (match && card.classList.contains('wide')) {
                    card.style.gridColumn = 'span 2';
                }
            });
        });
    });
})();
</script>
</div>
</body>
</html>
