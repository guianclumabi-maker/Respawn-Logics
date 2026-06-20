<?php
require_once __DIR__ . '/bootstrap/app.php';

$loggedIn = isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true);
?>
<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modules Deep Dive — Respawn Logics</title>
    <meta name="description" content="An in-depth explore of the core modules driving Respawn Logics, showcasing real-time interface views.">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green:     #00e07a;
            --green-dim: #00b862;
            --amber:     #f5a623;
            --blue:      #4f8ef7;
            --purple:    #9b6dff;
            --red:       #ff4d6a;
            --teal:      #00c9b1;

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
        body {
            background: var(--bg);
            font-family: var(--sans);
            color: var(--text);
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ─── NAV ─── */
        nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            height: 70px;
            background: rgba(11, 15, 26, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 40px;
            z-index: 9999;
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
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--green), #00b8ff);
            border-radius: 7px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #000;
            font-size: 0.875rem;
        }

        .version-pill {
            font-family: var(--mono);
            font-size: 0.625rem;
            font-weight: 700;
            letter-spacing: 0.1em;
            color: var(--green);
            background: rgba(0,224,122,0.07);
            border: 1px solid rgba(0,224,122,0.18);
            padding: 2px 7px;
            border-radius: 3px;
        }
        .nav-links { display: flex; align-items: center; gap: 24px; }
        .nav-links a {
            color: var(--text-mid);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover, .nav-links a.active { color: var(--green); }
        .nav-cta {
            font-family: var(--mono);
            color: var(--green) !important;
            border: 1px solid var(--border);
            padding: 6px 14px;
            border-radius: 4px;
            background: rgba(0, 224, 122, 0.03);
            font-weight: 600;
            transition: all 0.2s;
        }
        .nav-cta:hover {
            background: rgba(0, 224, 122, 0.1);
            box-shadow: 0 0 12px rgba(0,224,122,0.2);
        }

        /* ─── HERO ─── */
        .deep-dive-hero {
            padding: 140px 40px 60px;
            max-width: 1180px;
            margin: 0 auto;
            position: relative;
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(0,224,122,0.04) 0%, transparent 60%);
            z-index: -1;
        }
        .eyebrow {
            font-family: var(--mono);
            color: var(--green);
            font-size: 0.85rem;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }
        .hero-title {
            font-size: 3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }
        .hero-sub {
            font-size: 1.1rem;
            color: var(--text-mid);
            max-width: 700px;
        }

        /* ─── LAYOUT CONTAINER ─── */
        .dive-container {
            max-width: 1180px;
            margin: 0 auto 100px;
            padding: 0 40px;
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 48px;
            align-items: start;
        }

        /* ─── SIDEBAR FILTER ─── */
        .dive-sidebar {
            position: sticky;
            top: 110px;
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 8px;
            padding: 20px;
        }
        .sidebar-title {
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 16px;
        }
        .sidebar-links {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-item {
            font-family: var(--mono);
            font-size: 0.85rem;
            color: var(--text-mid);
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            border-left: 2px solid transparent;
            transition: all 0.2s;
            cursor: pointer;
        }
        .sidebar-item:hover {
            color: #fff;
            background: rgba(255,255,255,0.02);
        }
        .sidebar-item.active {
            color: var(--green);
            background: rgba(0, 224, 122, 0.05);
            border-left-color: var(--green);
        }

        /* ─── CONTENT PANELS ─── */
        .dive-content {
            display: flex;
            flex-direction: column;
            gap: 80px;
        }

        .module-section {
            scroll-margin-top: 110px;
        }

        /* ─── MODULE DETAILS CARD ─── */
        .dive-card {
            background: var(--bg2);
            border: 1px solid var(--border2);
            border-radius: 8px;
            overflow: hidden;
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        .dive-card:hover {
            border-color: rgba(0, 224, 122, 0.2);
            box-shadow: 0 0 25px rgba(0, 224, 122, 0.05);
        }

        .card-header {
            padding: 32px 32px 20px;
            border-bottom: 1px solid var(--border3);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        .mod-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .mod-badge {
            font-family: var(--mono);
            font-size: 0.75rem;
            color: #fff;
            background: var(--bg3);
            border: 1px solid var(--border2);
            padding: 2px 8px;
            border-radius: 3px;
        }
        .mod-tag {
            font-family: var(--mono);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 3px;
        }
        .mod-tag.green { color: var(--green); background: rgba(0, 224, 122, 0.1); border: 1px solid rgba(0, 224, 122, 0.2); }
        .mod-tag.blue { color: var(--blue); background: rgba(79, 142, 247, 0.1); border: 1px solid rgba(79, 142, 247, 0.2); }
        .mod-tag.teal { color: var(--teal); background: rgba(0, 201, 177, 0.1); border: 1px solid rgba(0, 201, 177, 0.2); }
        .mod-tag.red { color: var(--red); background: rgba(255, 77, 106, 0.1); border: 1px solid rgba(255, 77, 106, 0.2); }
        .mod-tag.purple { color: var(--purple); background: rgba(155, 109, 255, 0.1); border: 1px solid rgba(155, 109, 255, 0.2); }
        .mod-tag.amber { color: var(--amber); background: rgba(245, 166, 35, 0.1); border: 1px solid rgba(245, 166, 35, 0.2); }
        
        .mod-tag.portal-admin { color: #fff; background: rgba(255, 77, 106, 0.15); border: 1px solid var(--red); font-size: 0.65rem; }
        .mod-tag.portal-employee { color: #fff; background: rgba(0, 201, 177, 0.15); border: 1px solid var(--teal); font-size: 0.65rem; }
        .mod-tag.portal-both { color: #fff; background: rgba(155, 109, 255, 0.15); border: 1px solid var(--purple); font-size: 0.65rem; }

        .card-title {
            font-size: 1.6rem;
            font-weight: 600;
            color: #fff;
        }

        .card-body {
            padding: 32px;
        }

        .mod-summary {
            color: var(--text);
            font-size: 0.95rem;
            margin-bottom: 24px;
            line-height: 1.7;
        }

        /* ─── FEATURE LIST ─── */
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .feature-item {
            display: flex;
            gap: 12px;
            align-items: flex-start;
        }
        .feature-item i {
            color: var(--green);
            margin-top: 4px;
            font-size: 0.9rem;
        }
        .feature-title {
            font-weight: 600;
            color: #fff;
            font-size: 0.9rem;
            margin-bottom: 4px;
        }
        .feature-desc {
            font-size: 0.85rem;
            color: var(--text-mid);
        }

        /* ─── MOCK WINDOW SCREENSHOT ─── */
        .window-frame {
            border: 1px solid var(--border2);
            border-radius: 6px;
            overflow: hidden;
            background: #060913;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
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

        /* ─── FOOTER ─── */
        footer {
            border-top: 1px solid var(--border2);
            background: #070a12;
            padding: 40px 0;
            margin-top: 100px;
        }
        .footer-inner {
            max-width: 1180px;
            margin: 0 auto;
            padding: 0 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .footer-copy {
            font-size: 0.85rem;
            color: var(--text-dim);
        }
        .footer-socials {
            display: flex;
            gap: 16px;
        }
        .footer-socials a {
            color: var(--text-dim);
            font-size: 1.1rem;
            transition: color 0.2s;
        }
        .footer-socials a:hover { color: #fff; }

        @media (max-width: 960px) {
            .dive-container {
                grid-template-columns: 1fr;
            }
            .dive-sidebar {
                display: none;
            }
            .hero-title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <?= renderLogo('navbar') ?>
    <div class="nav-links">
        <a href="index.php#modules">Modules</a>
        <a href="deep_dive.php" class="active">Deep Dive</a>
        <a href="index.php#story">The Story</a>
        <a href="index.php#beta">Beta</a>
        <?php if ($loggedIn): ?>
            <a href="<?= url('/pages/dashboard.php') ?>" class="nav-cta">[ RESUME ]</a>
        <?php else: ?>
            <a href="login.php" class="nav-cta">[ LOGIN ]</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<div class="deep-dive-hero">
    <div class="hero-bg"></div>
    <div class="eyebrow">// DIAGNOSTIC CORE SHOWCASE</div>
    <h1 class="hero-title">Core System Deep Dive</h1>
    <p class="hero-sub">Explore the visual dashboards, data pipelines, and administrative control centers running inside Respawn Logics.</p>
</div>

<!-- DIVE CONTAINER -->
<div class="dive-container">
    
    <!-- STICKY SIDEBAR -->
    <div class="dive-sidebar">
        <div class="sidebar-title">System Sections</div>
        <div class="sidebar-links">
            <a href="#onboarding" class="sidebar-item active">Onboarding Engine</a>
            <a href="#core-hr" class="sidebar-item">Core HR & Org Chart</a>
            <a href="#dashboard" class="sidebar-item">Client Dashboard</a>
            <a href="#elr" class="sidebar-item">Employee Relations</a>
            <a href="#payroll" class="sidebar-item">Payroll Engine</a>
            <a href="#ats" class="sidebar-item">Applicant Tracking (ATS)</a>
            <a href="#performance" class="sidebar-item">Performance Mgt</a>
            <a href="#esm" class="sidebar-item">Platform Support (ESM)</a>
        </div>
    </div>

    <!-- MAIN PANELS -->
    <div class="dive-content">

        <!-- 1. ONBOARDING ENGINE -->
        <section id="onboarding" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 01</span>
                            <span class="mod-tag green">SPAWN POINT</span>
                            <span class="mod-tag portal-admin">ADMIN VERSION</span>
                        </div>
                        <h2 class="card-title">Universal Onboarding Engine</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        The onboarding process sets the tone for your team. Our engine eliminates the friction of manual entry by parsing raw company CSV spreadsheets, mapping fields dynamically, and setting up complex nested hierarchies instantly.
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Dynamic CSV Parser</div>
                                <div class="feature-desc">Upload raw data and map custom columns to employee record properties.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Automatic De-duplication</div>
                                <div class="feature-desc">Matches emails, names, and IDs globally before commits to prevent collision.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Instant Active Tokens</div>
                                <div class="feature-desc">Generates secure, one-time setup activation links dispatched to all new recruits.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Browser Screenshot -->
                    <div class="window-frame">
                        <div class="window-header">
                            <div class="window-dots">
                                <div class="window-dot close"></div>
                                <div class="window-dot minimize"></div>
                                <div class="window-dot maximize"></div>
                            </div>
                            <div class="window-url">http://localhost/respawn-logics/pages/onboarding_admin.php</div>
                        </div>
                        <div class="window-body">
                            <img src="assets/images/onboarding.png" alt="Onboarding Admin Dashboard" class="window-screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. CORE HR & ORG CHART -->
        <section id="core-hr" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 02</span>
                            <span class="mod-tag blue">PARTY ROSTER</span>
                            <span class="mod-tag portal-both">BOTH / ADMIN HEAVY</span>
                        </div>
                        <h2 class="card-title">Core HR & Roster Directory</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        Manage employee profiles, historical logs, salary grades, and supervisors within a secure roster directory. Features a visual, responsive organization chart showing direct reports.
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Interactive Org Tree</div>
                                <div class="feature-desc">Visual directory tree tracing supervisors, manager links, and operational chains.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Granular Profile Sheets</div>
                                <div class="feature-desc">Personal records, bank details, job titles, and compensation levels.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">History Logs</div>
                                <div class="feature-desc">Automatic tracking of reassignments, salary adjustments, and status changes.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Browser Screenshot -->
                    <div class="window-frame">
                        <div class="window-header">
                            <div class="window-dots">
                                <div class="window-dot close"></div>
                                <div class="window-dot minimize"></div>
                                <div class="window-dot maximize"></div>
                            </div>
                            <div class="window-url">http://localhost/respawn-logics/pages/org-chart.php</div>
                        </div>
                        <div class="window-body">
                            <img src="assets/images/org_chart.png" alt="Organization Chart & Directory" class="window-screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. CLIENT DASHBOARD -->
        <section id="dashboard" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 00</span>
                            <span class="mod-tag teal">CONTROL CORE</span>
                            <span class="mod-tag portal-employee">EMPLOYEE FACING PORTAL</span>
                        </div>
                        <h2 class="card-title">Interactive Client Dashboard</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        The central control dashboard for tenants. Summarizes pending requests, action items, system announcements, and quick utility links, styled inside a premium terminal console.
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Actionable HUD Metrics</div>
                                <div class="feature-desc">Real-time indicators showing pending approvals, scheduling shifts, and open tasks.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Broadcast Feed</div>
                                <div class="feature-desc">LinkedIn-style announcement cards keeping the entire organization aligned.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Quick Action Links</div>
                                <div class="feature-desc">Direct shortcuts to submit expenses, log attendance, or check leave balances.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Browser Screenshot -->
                    <div class="window-frame">
                        <div class="window-header">
                            <div class="window-dots">
                                <div class="window-dot close"></div>
                                <div class="window-dot minimize"></div>
                                <div class="window-dot maximize"></div>
                            </div>
                            <div class="window-url">http://localhost/respawn-logics/pages/dashboard.php</div>
                        </div>
                        <div class="window-body">
                            <img src="assets/images/dashboard.png" alt="Client Dashboard" class="window-screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. EMPLOYEE RELATIONS (ELR) -->
        <section id="elr" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 11</span>
                            <span class="mod-tag red">TRIBUNAL</span>
                            <span class="mod-tag portal-admin">ADMIN VERSION</span>
                        </div>
                        <h2 class="card-title">Employee Relations Console (ELR)</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        Handles sensitive escalations, performance improvement plans (PIPs), disciplinary disputes, and investigations inside strict, role-based access-controlled case folders.
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solidfa-circle-check"></i>
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Permission-Scoped Isolation</div>
                                <div class="feature-desc">Strictly isolated from general supervisors; cases are only accessible to designated HR investigators.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Severity Classification</div>
                                <div class="feature-desc">Calibrates case impact (Low, Medium, High, Critical) with automatic SLA notifications.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Case Timelines</div>
                                <div class="feature-desc">Detailed activity feed tracking updates, resolutions, and uploaded documentation.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Browser Screenshot -->
                    <div class="window-frame">
                        <div class="window-header">
                            <div class="window-dots">
                                <div class="window-dot close"></div>
                                <div class="window-dot minimize"></div>
                                <div class="window-dot maximize"></div>
                            </div>
                            <div class="window-url">http://localhost/respawn-logics/pages/elr_admin.php</div>
                        </div>
                        <div class="window-body">
                            <img src="assets/images/elr.png" alt="Employee Relations Console" class="window-screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. PAYROLL ENGINE -->
        <section id="payroll" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 04</span>
                            <span class="mod-tag amber">GOLD RECOVERY</span>
                            <span class="mod-tag portal-admin">ADMIN VERSION</span>
                        </div>
                        <h2 class="card-title">Payroll Engine & Calculations</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        Maintains clean schedules, auto-computes tax deductions, manages standard allowances, and renders digital pay slips directly to employee portals.
                    </p>
                    
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Allowance Configuration</div>
                                <div class="feature-desc">Manage bonuses, overtime allowances, health packages, and custom deductibles.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Auto Payslip Generation</div>
                                <div class="feature-desc">Instant computation results rendered as individual payslips in the employee portal.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Bank Compliance</div>
                                <div class="feature-desc">Generates batch bank export files directly supporting standard regional disbursements.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Mock Browser Screenshot -->
                    <div class="window-frame">
                        <div class="window-header">
                            <div class="window-dots">
                                <div class="window-dot close"></div>
                                <div class="window-dot minimize"></div>
                                <div class="window-dot maximize"></div>
                            </div>
                            <div class="window-url">http://localhost/respawn-logics/payroll-frontend/dist/index.html</div>
                        </div>
                        <div class="window-body">
                            <img src="assets/images/payroll.png" alt="Payroll System Interface" class="window-screenshot">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 6. ATS -->
        <section id="ats" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 06</span>
                            <span class="mod-tag purple">RECRUITMENT</span>
                            <span class="mod-tag portal-admin">ADMIN VERSION</span>
                        </div>
                        <h2 class="card-title">Applicant Tracking System (ATS)</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        End-to-end recruitment pipeline. Manage job postings, track candidates through custom hiring stages, evaluate with scorecards, and collaborate directly with hiring managers.
                    </p>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Kanban Pipeline</div>
                                <div class="feature-desc">Drag and drop candidates across stages visually.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Structured Scorecards</div>
                                <div class="feature-desc">Ensure objective hiring with customizable interview rubrics.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 7. PERFORMANCE -->
        <section id="performance" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 07</span>
                            <span class="mod-tag blue">LEVEL UP</span>
                            <span class="mod-tag portal-both">BOTH (MGR & EMPLOYEES)</span>
                        </div>
                        <h2 class="card-title">Performance Management</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        Conduct periodic 360-degree performance reviews, track OKRs (Objectives and Key Results), and align employee goals with company-wide strategic initiatives.
                    </p>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Custom Review Cycles</div>
                                <div class="feature-desc">Set up annual, bi-annual, or quarterly review periods automatically.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Goal Tracking</div>
                                <div class="feature-desc">Employees set their KPIs, managers approve and score them.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 8. ESM -->
        <section id="esm" class="module-section">
            <div class="dive-card">
                <div class="card-header">
                    <div>
                        <div class="mod-meta">
                            <span class="mod-badge">MODULE 10</span>
                            <span class="mod-tag teal">SUPPORT DECK</span>
                            <span class="mod-tag portal-employee">EMPLOYEE FACING PORTAL</span>
                        </div>
                        <h2 class="card-title">Platform Support (ESM)</h2>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mod-summary">
                        An internal IT and HR helpdesk for employees to submit tickets, request equipment, and report system issues. Includes an SLA-driven ticketing system for support agents.
                    </p>
                    <div class="feature-grid">
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">Self-Service Portal</div>
                                <div class="feature-desc">Employees can file tickets easily from their dashboard.</div>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div>
                                <div class="feature-title">SLA Timers</div>
                                <div class="feature-desc">Priority-based countdowns to ensure prompt resolution by agents.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>

</div>

<!-- FOOTER -->
<footer>
    <div class="footer-inner">
        <div class="footer-copy">
            © <?= date('Y') ?> Respawn Logics Inc. &nbsp;·&nbsp; Built in the Philippines 🇵🇭
        </div>
        <div class="footer-socials">
            <a href="#"><i class="fa-brands fa-linkedin"></i></a>
            <a href="#"><i class="fa-brands fa-twitter"></i></a>
            <a href="#"><i class="fa-brands fa-github"></i></a>
        </div>
    </div>
</footer>

<script>
    // Highlight sidebar items dynamically as we scroll
    document.addEventListener('DOMContentLoaded', () => {
        const sections = document.querySelectorAll('.module-section');
        const navItems = document.querySelectorAll('.sidebar-item');

        window.addEventListener('scroll', () => {
            let currentSectionId = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop - 150;
                if (window.scrollY >= sectionTop) {
                    currentSectionId = section.getAttribute('id');
                }
            });

            if (currentSectionId) {
                navItems.forEach(item => {
                    item.classList.remove('active');
                    if (item.getAttribute('href') === '#' + currentSectionId) {
                        item.classList.add('active');
                    }
                });
            }
        });
        
        // Add click behavior
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = item.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 120,
                        behavior: 'smooth'
                    });
                }
            });
        });
    });
</script>

</body>
</html>
