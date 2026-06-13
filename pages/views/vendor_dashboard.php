<?php
// Secure context: only accessible via dashboard.php router
if (!defined('ABSPATH') && empty($user)) {
    $user = getCurrentUser();
    if (!$user) { header("Location: ../login.php"); exit; }
}

$current_page = 'dashboard.php';

// AJAX Stats Handler
if (isset($_GET['action']) && $_GET['action'] === 'get_vendor_stats') {
    header('Content-Type: application/json');
    try {
        $tenantCount = $pdo->query("SELECT COUNT(*) FROM `tenants` WHERE `status` = 'active' AND `id` != '1'")->fetchColumn();
        $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
        $openTickets = $pdo->query("SELECT COUNT(*) FROM `platform_tickets` WHERE `status` NOT IN ('Resolved', 'Closed')")->fetchColumn();
        $breachedTickets = $pdo->query("SELECT COUNT(*) FROM `platform_tickets` WHERE `status` NOT IN ('Resolved', 'Closed') AND TIMESTAMPDIFF(HOUR, `created_at`, NOW()) >= 48")->fetchColumn();
        
        // Sum tier values dynamically
        $mrrStmt = $pdo->query("SELECT subscription_tier FROM tenants WHERE status = 'Active' AND id != '1'");
        $activeTiers = $mrrStmt->fetchAll(PDO::FETCH_COLUMN);
        $calculatedMRR = 0;
        foreach ($activeTiers as $tier) {
            switch ($tier) {
                case 'Enterprise': $calculatedMRR += 999; break;
                case 'Pro': $calculatedMRR += 499; break;
                case 'Starter': $calculatedMRR += 99; break;
                case 'Trial': default: $calculatedMRR += 0; break;
            }
        }
        if ($calculatedMRR === 0) {
            $calculatedMRR = $tenantCount * 499;
        }

        $recentTenants = $pdo->query("SELECT * FROM `tenants` WHERE `id` != '1' ORDER BY `id` DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        $recentData = [];
        foreach ($recentTenants as $t) {
            $recentData[] = [
                'id' => $t['id'],
                'company_name' => $t['company_name'],
                'status' => $t['status'],
                'created_at' => date('M j, Y', strtotime($t['created_at'])),
                'impersonate_url' => url('/pages/impersonate.php?action=start&tenant_id=' . urlencode($t['id']))
            ];
        }

        echo json_encode([
            'success' => true,
            'tenantCount' => (int)$tenantCount,
            'userCount' => (int)$userCount,
            'openTickets' => (int)$openTickets,
            'breachedTickets' => (int)$breachedTickets,
            'mockMRR' => (int)$calculatedMRR,
            'recentTenants' => $recentData
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Vendor Metrics Queries
try {
    // Total Tenants
    $tenantCount = $pdo->query("SELECT COUNT(*) FROM `tenants` WHERE `status` = 'active' AND `id` != '1'")->fetchColumn();
    
    // Total Users globally
    $userCount = $pdo->query("SELECT COUNT(*) FROM `users`")->fetchColumn();
    
    // Support SLA Metrics
    $openTickets = $pdo->query("SELECT COUNT(*) FROM `platform_tickets` WHERE `status` NOT IN ('Resolved', 'Closed')")->fetchColumn();
    $breachedTickets = $pdo->query("SELECT COUNT(*) FROM `platform_tickets` WHERE `status` NOT IN ('Resolved', 'Closed') AND TIMESTAMPDIFF(HOUR, `created_at`, NOW()) >= 48")->fetchColumn();
    
    // Dynamic MRR based on actual tiers
    $mrrStmt = $pdo->query("SELECT subscription_tier FROM tenants WHERE status = 'Active' AND id != '1'");
    $activeTiers = $mrrStmt->fetchAll(PDO::FETCH_COLUMN);
    $mockMRR = 0;
    foreach ($activeTiers as $tier) {
        switch ($tier) {
            case 'Enterprise': $mockMRR += 999; break;
            case 'Pro': $mockMRR += 499; break;
            case 'Starter': $mockMRR += 99; break;
            case 'Trial': default: $mockMRR += 0; break;
        }
    }
    if ($mockMRR === 0) {
        $mockMRR = $tenantCount * 499;
    }

    // Recent Onboardings
    $recentTenants = $pdo->query("SELECT * FROM `tenants` WHERE `id` != '1' ORDER BY `id` DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    // Fallbacks
    $tenantCount = 0; $userCount = 0; $openTickets = 0; $breachedTickets = 0; $mockMRR = 0;
    $recentTenants = [];
}
?>
<?php $page_title = 'SaaS Control Center - Vendor Dashboard'; ?>
<?php include __DIR__ . '/../../includes/head.php'; ?>

    <style>
        /* Force Dark Theme for SaaS Control Center */
        body {
            background-color: #0b0f1a;
            color: #c8d0e0;
            background-image: linear-gradient(to right, rgba(255,255,255,0.02) 1px, transparent 1px), linear-gradient(to bottom, rgba(255,255,255,0.02) 1px, transparent 1px);
            background-size: 40px 40px;
        }
        .main-content {
            background-color: transparent;
        }
        .topbar {
            background: rgba(11, 15, 26, 0.8);
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }
        .page-title {
            color: #ffffff;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
        }
        .god-mode-header {
            background: #0f1422;
            border-radius: 8px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.05);
            border: 1px solid rgba(0, 224, 122, 0.2);
            position: relative;
            overflow: hidden;
        }
        .god-mode-header::before {
            content: '';
            position: absolute;
            top: -50%; left: -50%; width: 200%; height: 200%;
            background: radial-gradient(circle, rgba(0, 224, 122,0.05) 0%, transparent 60%);
            animation: pulseBg 10s infinite alternate;
        }
        @keyframes pulseBg {
            0% { transform: scale(1); }
            100% { transform: scale(1.2); }
        }
        .god-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(to right, #00e07a, #00b8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .god-title::after {
            content: '_';
            color: #00e07a;
            animation: blink 1s step-end infinite;
            -webkit-text-fill-color: #00e07a;
        }
        @keyframes blink { 50% { opacity: 0; } }
        .god-subtitle {
            color: #8b95a8;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
            font-family: 'Space Grotesk', sans-serif;
        }
        .god-badge {
            display: inline-block;
            padding: 4px 12px;
            margin-bottom: 16px;
            border-radius: 100px;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.05);
            color: #8b95a8;
            font-size: 12px;
            font-family: 'JetBrains Mono', monospace;
            position: relative;
            z-index: 1;
        }
        .vendor-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 30px;
        }
        .vendor-card {
            background: #0f1422;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 25px;
            backdrop-filter: blur(10px);
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .vendor-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 224, 122, 0.3);
            box-shadow: 0 10px 30px rgba(0, 224, 122, 0.05);
        }
        .v-card-icon {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #00e07a;
        }
        .v-card-label {
            color: #5e6a82;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }
        .v-card-value {
            font-size: 2.2rem;
            font-weight: 700;
            font-family: 'Space Grotesk', sans-serif;
            color: white;
        }
        
        .recent-table-wrapper {
            background: #0f1422;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            padding: 25px;
            backdrop-filter: blur(10px);
        }
        .recent-table-wrapper h3 {
            color: #fff;
            margin-bottom: 24px;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 1.2rem;
            font-weight: 700;
        }
        .r-table {
            width: 100%;
            border-collapse: collapse;
        }
        .r-table th {
            text-align: left;
            padding: 12px 15px;
            color: #5e6a82;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .r-table td {
            padding: 15px;
            color: #c8d0e0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-family: 'Space Grotesk', sans-serif;
            font-size: 14px;
        }
        .r-table tr:last-child td { border-bottom: none; }
        .r-badge { padding: 4px 10px; border-radius: 4px; font-size: 11px; font-weight: 600; font-family: 'JetBrains Mono', monospace; text-transform: uppercase; letter-spacing: 0.05em; }
        .r-badge-active { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .r-badge-past-due { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .tenant-id-badge {
            transition: all 0.2s ease-in-out;
        }
        .tenant-id-badge:hover {
            background: rgba(0, 224, 122, 0.25) !important;
            box-shadow: 0 0 10px rgba(0, 224, 122, 0.2);
            border-color: rgba(0, 224, 122, 0.4) !important;
        }
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Vendor Command Center</h1>
                </div>
            </header>
            
            <div class="content-wrapper">
                <div class="god-mode-header">
                    <div class="god-badge">// GOD_MODE_ENABLED</div>
                    <div class="god-title">SaaS Control Center</div>
                    <div class="god-subtitle">You are viewing the entire Respawn Logics universe. Data spans across all sandboxes.</div>
                </div>

                <div class="vendor-grid">
                    <div class="vendor-card">
                        <div class="v-card-icon"><i class="fa-solid fa-server"></i></div>
                        <div class="v-card-label">// Active_Sandboxes</div>
                        <div class="v-card-value" id="active-sandboxes-val"><?= number_format($tenantCount) ?></div>
                    </div>
                    <div class="vendor-card">
                        <div class="v-card-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="v-card-label">// Global_Users</div>
                        <div class="v-card-value" id="global-users-val"><?= number_format($userCount) ?></div>
                    </div>
                    <div class="vendor-card">
                        <div class="v-card-icon"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                        <div class="v-card-label">// Platform_MRR</div>
                        <div class="v-card-value" id="platform-mrr-val">$<?= number_format($mockMRR) ?></div>
                    </div>
                    <div class="vendor-card" id="action-needed-card" style="border-color: <?= $breachedTickets > 0 ? 'rgba(239,68,68,0.5)' : 'rgba(255,255,255,0.05)' ?>;">
                        <div class="v-card-icon" id="action-needed-icon" style="color: <?= $breachedTickets > 0 ? '#ef4444' : '#00e07a' ?>;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div class="v-card-label">// Action_Needed</div>
                        <div class="v-card-value" id="action-needed-val">
                            <?= $openTickets ?> <span style="font-size:14px; color:#5e6a82; font-weight:500;">(<?= $breachedTickets ?> Breached)</span>
                        </div>
                    </div>
                </div>

                <div class="recent-table-wrapper">
                    <h3>Recently Onboarded Companies</h3>
                    <table class="r-table">
                        <thead>
                            <tr>
                                <th>Tenant ID</th>
                                <th>Company Name</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody id="recent-tenants-tbody">
                            <?php if (empty($recentTenants)): ?>
                                <tr><td colspan="4" style="text-align:center;">No companies found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentTenants as $t): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= url('/pages/impersonate.php?action=start&tenant_id=' . urlencode($t['id'])) ?>" style="text-decoration: none;">
                                                <code class="tenant-id-badge" style="background: rgba(0, 224, 122, 0.1); padding: 4px 8px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); cursor: pointer; transition: all 0.2s; display: inline-block;">
                                                    TENANT-<?= htmlspecialchars($t['id']) ?>
                                                </code>
                                            </a>
                                        </td>
                                        <td style="font-weight:600; color:white;"><?= htmlspecialchars($t['company_name']) ?></td>
                                        <td><span class="r-badge <?= strtolower($t['status']) === 'active' ? 'r-badge-active' : 'r-badge-past-due' ?>"><?= htmlspecialchars($t['status']) ?></span></td>
                                        <td><?= date('M j, Y', strtotime($t['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const activeSandboxesVal = document.getElementById('active-sandboxes-val');
        const globalUsersVal = document.getElementById('global-users-val');
        const platformMrrVal = document.getElementById('platform-mrr-val');
        const actionNeededVal = document.getElementById('action-needed-val');
        const actionNeededCard = document.getElementById('action-needed-card');
        const actionNeededIcon = document.getElementById('action-needed-icon');
        const recentTenantsTbody = document.getElementById('recent-tenants-tbody');

        function flashUpdate(element) {
            element.style.transition = 'none';
            element.style.color = '#00e07a';
            element.style.textShadow = '0 0 15px rgba(0, 224, 122, 0.8)';
            setTimeout(() => {
                element.style.transition = 'all 1s ease';
                element.style.color = '';
                element.style.textShadow = '';
            }, 100);
        }

        function updateMetrics() {
            fetch('dashboard.php?action=get_vendor_stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Check and update Active Sandboxes
                        const currentSandboxes = activeSandboxesVal.textContent.trim();
                        const newSandboxes = String(data.tenantCount);
                        if (currentSandboxes !== newSandboxes) {
                            activeSandboxesVal.textContent = newSandboxes;
                            flashUpdate(activeSandboxesVal);
                        }

                        // Check and update Global Users
                        const currentUsers = globalUsersVal.textContent.replace(/,/g, '').trim();
                        const newUsers = String(data.userCount);
                        if (currentUsers !== newUsers) {
                            globalUsersVal.textContent = Number(newUsers).toLocaleString();
                            flashUpdate(globalUsersVal);
                        }

                        // Check and update Platform MRR
                        const currentMrr = platformMrrVal.textContent.replace(/[$,]/g, '').trim();
                        const newMrr = String(data.mockMRR);
                        if (currentMrr !== newMrr) {
                            platformMrrVal.textContent = '$' + Number(newMrr).toLocaleString();
                            flashUpdate(platformMrrVal);
                        }

                        // Check and update Action Needed / Support SLA
                        const newActionNeededHtml = `${data.openTickets} <span style="font-size:14px; color:#5e6a82; font-weight:500;">(${data.breachedTickets} Breached)</span>`;
                        const tempDiv = document.createElement('div');
                        tempDiv.innerHTML = newActionNeededHtml;
                        if (actionNeededVal.innerHTML.trim() !== tempDiv.innerHTML.trim()) {
                            actionNeededVal.innerHTML = newActionNeededHtml;
                            flashUpdate(actionNeededVal);
                            
                            // Update border and icon colors dynamically
                            if (data.breachedTickets > 0) {
                                actionNeededCard.style.borderColor = 'rgba(239,68,68,0.5)';
                                actionNeededIcon.style.color = '#ef4444';
                            } else {
                                actionNeededCard.style.borderColor = 'rgba(255,255,255,0.05)';
                                actionNeededIcon.style.color = '#00e07a';
                            }
                        }

                        // Update Recently Onboarded Companies Table
                        let newTableHtml = '';
                        if (data.recentTenants.length === 0) {
                            newTableHtml = '<tr><td colspan="4" style="text-align:center;">No companies found.</td></tr>';
                        } else {
                            data.recentTenants.forEach(t => {
                                const statusClass = t.status.toLowerCase() === 'active' ? 'r-badge-active' : 'r-badge-past-due';
                                newTableHtml += `
                                    <tr>
                                        <td>
                                            <a href="${t.impersonate_url}" style="text-decoration: none;">
                                                <code class="tenant-id-badge" style="background: rgba(0, 224, 122, 0.1); padding: 4px 8px; border-radius: 4px; font-family: 'JetBrains Mono', monospace; font-size: 11px; color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); cursor: pointer; transition: all 0.2s; display: inline-block;">
                                                    TENANT-${escapeHtml(t.id)}
                                                </code>
                                            </a>
                                        </td>
                                        <td style="font-weight:600; color:white;">${escapeHtml(t.company_name)}</td>
                                        <td><span class="r-badge ${statusClass}">${escapeHtml(t.status)}</span></td>
                                        <td>${escapeHtml(t.created_at)}</td>
                                    </tr>
                                `;
                            });
                        }

                        if (recentTenantsTbody.innerHTML.trim() !== newTableHtml.trim()) {
                            recentTenantsTbody.innerHTML = newTableHtml;
                        }
                    }
                })
                .catch(err => console.error('Failed to fetch stats:', err));
        }

        function escapeHtml(str) {
            return str
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Poll every 10 seconds for live data updates
        setInterval(updateMetrics, 10000);
    });
    </script>
</body>
</html>
