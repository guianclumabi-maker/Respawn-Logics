<?php
require_once __DIR__ . '/bootstrap/app.php';

$loggedIn = isLoggedIn() && (!isset($_SESSION['must_change_password']) || $_SESSION['must_change_password'] !== true);
?>
<!DOCTYPE html>
<html lang="en" style="scroll-behavior: smooth;">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respawn Logics — Patch Notes</title>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=Archivo:wght@700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
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
            background-color: var(--bg);
            font-family: var(--sans);
            color: var(--text);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        .global-bg {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            z-index: 0;
            pointer-events: none;
            background-color: var(--bg);
            background-image:
                radial-gradient(ellipse 90% 55% at 50% -5%, rgba(255,255,255,0.015) 0%, transparent 65%),
                radial-gradient(ellipse 60% 50% at 85% 110%, rgba(255,255,255,0.01) 0%, transparent 65%);
        }

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
            color: var(--text);
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

        .nav-links a:hover { color: var(--text); }

        .content-wrap {
            position: relative;
            z-index: 10;
            max-width: 900px;
            margin: 120px auto 80px;
            padding: 0 5%;
        }

        .page-title {
            font-size: clamp(2.5rem, 5vw, 3.5rem);
            font-family: 'Archivo', sans-serif;
            font-weight: 900;
            color: #fff;
            margin-bottom: 10px;
            letter-spacing: -0.03em;
        }

        .page-subtitle {
            font-size: 1.15rem;
            color: var(--text-mid);
            margin-bottom: 60px;
        }

        .patch-card {
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border3);
            border-radius: 16px;
            padding: 40px;
            margin-bottom: 40px;
            transition: border-color 0.3s, background 0.3s;
        }

        .patch-card:hover {
            border-color: rgba(0,224,122,0.3);
            background: rgba(255,255,255,0.03);
        }

        .patch-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border3);
            padding-bottom: 24px;
        }

        .patch-version {
            font-family: var(--mono);
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--green);
        }

        .patch-date {
            font-family: var(--mono);
            font-size: 0.9rem;
            color: var(--text-dim);
        }

        .patch-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 24px;
        }

        .detail-section {
            margin-bottom: 24px;
        }

        .detail-label {
            font-family: var(--mono);
            font-size: 0.85rem;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            display: block;
        }

        .detail-text {
            color: var(--text);
            font-size: 1.05rem;
            line-height: 1.6;
        }

        .detail-text strong {
            color: #fff;
        }

        .tag-list {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .tag {
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border3);
            padding: 4px 12px;
            border-radius: 50px;
            font-family: var(--mono);
            font-size: 0.75rem;
            color: var(--text-mid);
        }
    </style>
</head>
<body>
    <div class="global-bg"></div>

    <nav>
        <a href="/" class="nav-logo">
            <div class="logo-mark"><i class="fa-solid fa-gamepad"></i></div>
            Respawn Logics
        </a>
        <div class="nav-links">
            <a href="/index.php#features">Features</a>
            <a href="/index.php#changelog">Changelog</a>
            <?php if ($loggedIn): ?>
                <a href="<?= url('/backend/dashboard.php') ?>" class="nav-cta">Go to Dashboard</a>
            <?php else: ?>
                <a href="<?= url('/login.php') ?>" class="nav-cta">Log In</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="content-wrap">
        <h1 class="page-title">Patch Notes</h1>
        <p class="page-subtitle">Detailed engineering logs for major updates to the Respawn engine.</p>

        <!-- v2.7.1 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.7.1</span>
                <span class="patch-date">Jul 19, 2026</span>
            </div>
            <h2 class="patch-title">Global CSRF Fortification & Integrity Guards</h2>
            
            <div class="detail-section">
                <span class="detail-label">Reason</span>
                <p class="detail-text">After deploying the new authentication matrix, several API endpoints performing state mutations (POST, PUT, DELETE) were returning 403 Forbidden errors due to missing CSRF tokens. Additionally, edge cases in the ATS and Payroll modules allowed inconsistent states (e.g., dragging candidates to "Hired" without an employee record, and failed payroll tenant configurations due to schema mismatches).</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Implementation</span>
                <p class="detail-text">We performed a site-wide automated refactor to replace all raw <code>fetch()</code> mutation calls with a centralized <code>apiFetch</code> wrapper that inherently handles <code>X-CSRF-Token</code> injection and token refreshing. We also patched the <code>migrate_payroll_config.php</code> schema script to correct a foreign key type and collation mismatch that blocked new tenants from generating payroll settings. Finally, we introduced a strict transition guard in the ATS <code>CandidatesController</code> that rejects state changes to "Hired" unless a valid employee record has already been provisioned.</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">All 403 CSRF errors have been eliminated platform-wide. Database referential integrity for the payroll module is restored, and ATS transitions are fully consistent with the overarching employee lifecycle.</p>
            </div>

            <div class="tag-list">
                <span class="tag">Security</span>
                <span class="tag">Bugfix</span>
                <span class="tag">Database</span>
            </div>
        </div>

        <!-- v2.7.0 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.7.0</span>
                <span class="patch-date">Jul 12, 2026</span>
            </div>
            <h2 class="patch-title">Tenant IDOR Verification Sweep</h2>
            
            <div class="detail-section">
                <span class="detail-label">Reason</span>
                <p class="detail-text">As a multi-tenant SaaS application handling highly sensitive HR and payroll data, any reliance on naked direct object references (e.g., <code>WHERE id = ?</code>) poses a critical security risk. We needed absolute certainty that data cannot bleed across organizational boundaries, even if an attacker attempts to enumerate resource IDs.</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Implementation</span>
                <p class="detail-text">We introduced a global <code>assertOwned()</code> security helper to standardise tenant checks. We then ran a sweeping static analysis script across the entire controller layer to identify vulnerable endpoints. We explicitly patched <code>CandidatesController</code>, <code>OnboardingController</code>, and <code>PayrollController</code>. Furthermore, we deployed <code>migrate_tenant_constraints.php</code>, a dynamic schema mutation script that analyzes the database and enforces <code>tenant_id IS NOT NULL</code> constraints and composite indexes across all tenant-scoped tables.</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Methodologies Used</span>
                <p class="detail-text"><strong>Defense in Depth & Zero Trust Architecture.</strong> We did not rely solely on application logic; we layered the protection down to the database schema. The <code>assertOwned()</code> helper was specifically designed to emit <code>404 Not Found</code> rather than <code>403 Forbidden</code> to prevent blind enumeration attacks.</p>
            </div>

            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">The platform is now mathematically immune to cross-tenant IDOR attacks. The schema-level composite indexes also drastically improved the query planner's efficiency on high-volume tables.</p>
            </div>

            <div class="tag-list">
                <span class="tag">Security</span>
                <span class="tag">Database</span>
                <span class="tag">Zero Trust</span>
            </div>
        </div>

        <!-- v2.6.0 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.6.0</span>
                <span class="patch-date">Jul 11, 2026</span>
            </div>
            <h2 class="patch-title">Automated CI/CD Pipeline & Regression Guards</h2>
            
            <div class="detail-section">
                <span class="detail-label">Reason</span>
                <p class="detail-text">Manual code reviews were becoming a bottleneck, and regressions were occasionally slipping into the <code>main</code> branch. The repository required branch protection rules, but there was no automated workflow to validate Pull Requests before merging.</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Implementation</span>
                <p class="detail-text">We engineered a GitHub Actions workflow (<code>.github/workflows/ci.yml</code>) that spins up an ephemeral MySQL 8.0 container on every push and PR. The pipeline runs two parallel jobs: <code>backend-tests</code> (which runs the entire PHPUnit integration test suite against the test database) and <code>typecheck-and-lint</code> (which performs deep static analysis using TypeScript and Vitest on the frontend). We also synchronized the raw job keys with GitHub's branch protection policies to strictly gate deployments.</p>
            </div>
            
            <div class="detail-section">
                <span class="detail-label">Methodologies Used</span>
                <p class="detail-text"><strong>Shift-Left Security & Continuous Integration.</strong> By automatically validating code at the PR level, we prevent broken syntax, typing errors, and logical regressions from ever reaching production environments. We optimized execution by utilizing composer and npm dependency caching.</p>
            </div>

            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">Pull requests are now strictly gated by the pipeline. If a developer breaks existing functionality or violates types, the branch cannot be merged. Developer confidence and deployment velocity have significantly increased.</p>
            </div>

            <div class="tag-list">
                <span class="tag">DevOps</span>
                <span class="tag">Testing</span>
                <span class="tag">CI/CD</span>
            </div>
        </div>

        <!-- v2.5.0 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.5.0</span>
                <span class="patch-date">Jul 10, 2026</span>
            </div>
            <h2 class="patch-title">Fail-Loud Storage Security</h2>
            
            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">Enforced strict security bounds. File uploads fail instantly if configured storage paths fall inside the public web root directory, preventing Remote Code Execution (RCE) via malicious file uploads.</p>
            </div>
        </div>

        <!-- v2.4.0 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.4.0</span>
                <span class="patch-date">Jun 30, 2026</span>
            </div>
            <h2 class="patch-title">Versioned PH Tax Tables</h2>
            
            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">Integrated dynamically versioned tax calculation schemas for BIR, SSS, and PhilHealth computations, retaining historical runs accuracy so that retroactive payroll viewing does not break when tax brackets change.</p>
            </div>
        </div>

        <!-- v2.3.0 -->
        <div class="patch-card">
            <div class="patch-header">
                <span class="patch-version">v2.3.0</span>
                <span class="patch-date">Jun 12, 2026</span>
            </div>
            <h2 class="patch-title">Employee Relations (ELR) Pipeline</h2>
            
            <div class="detail-section">
                <span class="detail-label">Outcome</span>
                <p class="detail-text">Launched unified Employee Relations queues with automated case logging, SLA status checks, and encrypted attachments.</p>
            </div>
        </div>

    </div>
</body>
</html>
