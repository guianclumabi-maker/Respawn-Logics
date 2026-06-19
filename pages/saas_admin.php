<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

// SaaS Control Center access check (Platform Admin and Internal Vendor Staff)
$user = getCurrentUser();
$allowed_roles = ['Platform_Admin', 'Support_Agent', 'Implementation_Specialist', 'Super_Admin'];
if (!hasRole($allowed_roles) && ($user === null || (!empty($user['tenant_id']) && $user['tenant_id'] != '1'))) {
    header("Location: dashboard.php");
    exit;
}

// Fetch all tenants and their stats
$stmt = $pdo->query("SELECT * FROM tenants ORDER BY created_at DESC");
$tenants = $stmt->fetchAll();

// Calculate aggregate stats
$totalTenants = count($tenants);
$totalTraffic = 0;
$totalApi = 0;
$activeCount = 0;

foreach ($tenants as $t) {
    $totalTraffic += (int)$t['foot_traffic_score'];
    $totalApi += (int)$t['ai_api_calls'];
    if ($t['status'] === 'Active') $activeCount++;
}

$current_page = 'saas_admin.php';
?>
<?php $page_title = 'SaaS Headquarters - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .hq-header {
            background: #0f1422;
            border-radius: 8px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.05);
            border: 1px solid rgba(0, 224, 122, 0.2);
            position: relative;
            overflow: hidden;
        }
        .hq-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(to right, #00e07a, #00b8ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        .hq-title::after {
            content: '_';
            color: #00e07a;
            animation: blink 1s step-end infinite;
            -webkit-text-fill-color: #00e07a;
        }
        @keyframes blink { 50% { opacity: 0; } }
        .hq-subtitle {
            color: #8b95a8;
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
            font-family: 'Space Grotesk', sans-serif;
        }
        .hq-badge {
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: var(--glass-bg);
            padding: 25px;
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(var(--glass-blur));
            -webkit-backdrop-filter: blur(var(--glass-blur));
            transition: all 0.2s;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 224, 122, 0.3);
            box-shadow: 0 10px 30px rgba(0, 224, 122, 0.05);
        }
        .stat-label {
            color: #5e6a82;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 8px;
        }
        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: white;
        }
        
        .tenant-table {
            width: 100%;
            border-collapse: collapse;
            background: #0f1422;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .tenant-table th {
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
        .tenant-table td {
            padding: 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #c8d0e0;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        .tenant-table tr:last-child td { border-bottom: none; }
        
        .badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .badge-active { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .badge-pastdue { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        
        .tier-Enterprise { color: #00e07a; font-weight: 700; font-family: 'JetBrains Mono', monospace; }
        .tier-Pro { color: #3b82f6; font-weight: 600; font-family: 'JetBrains Mono', monospace; }
        .tier-Starter { color: #00e07a; font-weight: 500; font-family: 'JetBrains Mono', monospace; }
        
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">

                </div>
            </header>
            
            <div class="content-wrapper">
                
                <div class="hq-header">
                    <div class="hq-badge">// GLOBAL_MONITOR</div>
                    <div class="hq-title">Respawn Logic Headquarters</div>
                    <div class="hq-subtitle">SaaS Global Monitoring & Analytics Dashboard</div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">// Total_Tenants</div>
                        <div class="stat-value"><?= $totalTenants ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">// Active_Subs</div>
                        <div class="stat-value"><?= $activeCount ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">// Total_Traffic</div>
                        <div class="stat-value"><?= number_format($totalTraffic) ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">// API_Calls</div>
                        <div class="stat-value"><?= number_format($totalApi) ?></div>
                    </div>
                </div>

                <h2 style="font-family: 'Space Grotesk', sans-serif; margin-bottom: 24px; color: #fff; font-size: 1.2rem; font-weight: 700;">Client Roster</h2>
                
                <table class="tenant-table">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Contact</th>
                            <th>Tier</th>
                            <th>Status</th>
                            <th>Foot Traffic (Last 30d)</th>
                            <th>AI Utilization</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td style="font-weight: 600; color: #fff;"><?= htmlspecialchars($t['company_name']) ?></td>
                            <td><?= htmlspecialchars($t['contact_email']) ?></td>
                            <td class="tier-<?= $t['subscription_tier'] ?>"><?= htmlspecialchars($t['subscription_tier']) ?></td>
                            <td>
                                <span class="badge <?= $t['status'] === 'Active' ? 'badge-active' : 'badge-pastdue' ?>">
                                    <?= htmlspecialchars($t['status']) ?>
                                </span>
                            </td>
                            <td><?= number_format($t['foot_traffic_score']) ?> sessions</td>
                            <td>
                                <a href="<?= url('/pages/impersonate.php?action=start&tenant_id=' . urlencode($t['id'])) ?>" style="display: inline-block; padding: 6px 12px; background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); text-decoration: none; border-radius: 4px; font-size: 11px; font-family: 'JetBrains Mono', monospace; font-weight: 600; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='#00e07a'; this.style.color='#111827';" onmouseout="this.style.background='rgba(0, 224, 122, 0.1)'; this.style.color='#00e07a';">
                                    <i class="fa-solid fa-mask"></i> IMPERSONATE
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
            </div>
        </div>
    </div>
</body>
</html>
