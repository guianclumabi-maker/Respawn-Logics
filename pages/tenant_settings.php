<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('settings.manage')) {
    header('Location: dashboard.php');
    exit;
}

$current_page = 'tenant_settings.php';
$user = getCurrentUser();
$tenantId = $user['tenant_id'];

// Fetch all modules
$modules = [
    'attendance' => 'Attendance Tracking',
    'shifts' => 'Shift Scheduler',
    'leave' => 'Leave Requests',
    'onboarding' => 'Onboarding',
    'assets' => 'Asset Management',
    'intelligence' => 'Predictive AI',
    'ats' => 'Recruitment / ATS',
    'elr' => 'Employee Relations',
    'esm' => 'IT/HR Service Desk',
    'analytics' => 'Workforce Analytics',
    'payroll' => 'Payroll Engine',
    'performance' => 'Performance',
    'expenses' => 'Expenses & Claims',
    'benefits' => 'Benefits & HMO',
    'surveys' => 'Engagement Surveys',
    'announcements' => 'Company Feed'
];

$stmt = $pdo->prepare("SELECT module_key, is_enabled FROM tenant_modules WHERE tenant_id = ?");
$stmt->execute([$tenantId]);
$enabledModules = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $enabledModules[$row['module_key']] = (bool)$row['is_enabled'];
}

?>
<?php $page_title = 'Tenant Settings - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    .global-glow-purple {
            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;
        }

        .settings-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .module-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }
        .module-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }
        .module-info h4 { margin: 0 0 4px 0; font-size: 1rem; color: var(--text-primary); }
        .module-info p { margin: 0; font-size: 0.875rem; color: var(--text-secondary); }
        
        .switch { position: relative; display: inline-block; width: 44px; height: 24px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255, 255, 255, 0.1); transition: .4s; border-radius: 24px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--brand-primary); }
        input:focus + .slider { box-shadow: 0 0 1px var(--brand-primary); }
        input:checked + .slider:before { transform: translateX(20px); }
    </style>


<body>
    <?php include __DIR__ . '/../includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title">Tenant Settings</h1>
                <p class="page-subtitle">Manage modules and features for your organization.</p>
            </div>
            <div>
                <button class="btn btn-primary" onclick="saveModules()">Save Changes</button>
            </div>
        </div>

        <div class="settings-card">
            <h3>Module Manager</h3>
            <p style="color: var(--text-secondary);">Toggle entire modules on or off for your organization. Disabling a module removes it from the sidebar and revokes access.</p>
            
            <div class="module-list" id="moduleContainer">
                <?php foreach ($modules as $key => $name): ?>
                    <?php 
                        $isChecked = isset($enabledModules[$key]) ? $enabledModules[$key] : false;
                    ?>
                    <div class="module-item">
                        <div class="module-info">
                            <h4><?= htmlspecialchars($name) ?></h4>
                            <p>Key: <code><?= $key ?></code></p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" class="module-toggle" data-key="<?= $key ?>" <?= $isChecked ? 'checked' : '' ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        function saveModules() {
            const toggles = document.querySelectorAll('.module-toggle');
            const data = {
                action: 'save_tenant_modules',
                modules: {}
            };
            
            toggles.forEach(t => {
                data.modules[t.dataset.key] = t.checked;
            });

            fetch('<?= url('/api/index.php?route=core_hr') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...data, csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
            })
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    alert('Modules updated successfully. Changes will fully apply on next page reload.');
                    window.location.reload();
                } else {
                    alert('Error: ' + (res.error || 'Unknown error'));
                }
            })
            .catch(err => alert('Network error.'));
        }
    </script>
</body>
</html>
