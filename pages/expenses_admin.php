<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$tenantId = $user['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';

function isManagerOrHR_Admin($userId, $tenantId) {
    global $pdo;
    if (hasPermission('expenses.manage')) return true;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `manager_id` = ? AND `tenant_id` = ?");
    $stmt->execute([$userId, $tenantId]);
    return $stmt->fetchColumn() > 0;
}

if (!isManagerOrHR_Admin($user['id'], $tenantId)) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Expense & Claims Management - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .admin-title h1 { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 4px; }
        .admin-title p { font-size: 0.875rem; color: var(--text-muted); }
        .tabs-header { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; }
        .tab-btn { padding: 12px 24px; color: var(--text-muted); background: transparent; border: none; border-bottom: 2px solid transparent; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .tab-btn:hover { color: white; }
        .tab-btn.active { color: #00e07a; border-bottom-color: #00e07a; }
        .panel-container { background: rgba(22, 25, 34, 0.7); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        .data-table td { padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: middle; color: white; font-size: 0.875rem; }
        
        .btn-action { background: transparent; border: 1px solid var(--border-color); color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; transition: 0.2s;}
        .btn-action:hover { border-color: #00e07a; }
        .btn-approve { background: rgba(0, 224, 122, 0.1); border-color: #00e07a; color: #00e07a; }
        .btn-approve:hover { background: #00e07a; color: white; }
        .btn-reject { background: rgba(239, 68, 68, 0.1); border-color: #ef4444; color: #ef4444; }
        .btn-reject:hover { background: #ef4444; color: white; }
    </style>


<body>
    <div class="global-glow-green"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Expense & Claims Console</h1>
                    <p>Approve employee reimbursements and queue for payroll.</p>
                </div>
            </div>

            <div class="tabs-header">
                <button class="tab-btn active" data-target="manager">Manager Approvals (My Team)</button>
                <?php if (hasPermission('expenses.manage')): ?>
                <button class="tab-btn" data-target="finance">Finance Approvals (All Teams)</button>
                <?php endif; ?>
            </div>

            <!-- Manager Panel -->
            <div id="panel-manager" class="panel-container">
                <h3 style="color:white; font-size:1.1rem; margin-bottom: 20px;">Pending Manager Approval</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date / Category</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="manager-list"></tbody>
                </table>
            </div>

            <?php if (hasPermission('expenses.manage')): ?>
            <!-- Finance Panel -->
            <div id="panel-finance" class="panel-container" style="display:none;">
                <h3 style="color:white; font-size:1.1rem; margin-bottom: 20px;">Pending Finance Approval</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Date / Category</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="finance-list"></tbody>
                </table>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(t => {
            t.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                t.classList.add('active');
                document.querySelectorAll('.panel-container').forEach(p => p.style.display = 'none');
                document.getElementById('panel-' + t.dataset.target).style.display = 'block';
                if(t.dataset.target === 'finance') loadFinance();
                else loadManager();
            });
        });

        async function loadManager() {
            try {
                const res = await fetch(`<?= url('/api/index.php?route=expenses&action=manager_pending') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('manager-list');
                    if(data.data.length === 0) { c.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:20px;">No pending team claims.</td></tr>'; return; }
                    c.innerHTML = data.data.map(e => `
                        <tr>
                            <td><strong>${e.employee_name}</strong></td>
                            <td>
                                <div>${e.expense_date}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${e.category_name}</div>
                            </td>
                            <td style="font-weight:700; color:#00e07a;">$${parseFloat(e.amount).toLocaleString()}</td>
                            <td>${e.receipt_path ? `<a href="<?= url('/') ?>${e.receipt_path}" target="_blank" style="color:#00e07a;">View File</a>` : 'None'}</td>
                            <td style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
                                <button class="btn-action btn-approve" onclick="approveClaim(${e.id}, 'Approve')">Approve</button>
                                <button class="btn-action btn-reject" onclick="approveClaim(${e.id}, 'Reject')">Reject</button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        <?php if (hasPermission('expenses.manage')): ?>
        async function loadFinance() {
            try {
                const res = await fetch(`<?= url('/api/index.php?route=expenses&action=finance_pending') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('finance-list');
                    if(data.data.length === 0) { c.innerHTML = '<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding:20px;">No pending finance approvals.</td></tr>'; return; }
                    c.innerHTML = data.data.map(e => `
                        <tr>
                            <td><strong>${e.employee_name}</strong></td>
                            <td>
                                <div>${e.expense_date}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${e.category_name}</div>
                            </td>
                            <td style="font-weight:700; color:#00e07a;">$${parseFloat(e.amount).toLocaleString()}</td>
                            <td>${e.receipt_path ? `<a href="<?= url('/') ?>${e.receipt_path}" target="_blank" style="color:#00e07a;">View File</a>` : 'None'}</td>
                            <td style="text-align:right; display:flex; gap:8px; justify-content:flex-end;">
                                <button class="btn-action btn-approve" onclick="approveClaim(${e.id}, 'Approve')">Clear for Payout</button>
                                <button class="btn-action btn-reject" onclick="approveClaim(${e.id}, 'Reject')">Reject</button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }
        <?php endif; ?>

        async function approveClaim(id, decision) {
            let comments = prompt(`Enter optional comments for ${decision}:`);
            if (comments === null) return; // Cancelled
            
            try {
                const res = await fetch(`<?= url('/api/index.php?route=expenses&action=approve_claim') ?>`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({claim_id: id, decision: decision, comments: comments})
                });
                const data = await res.json();
                if(data.success) {
                    loadManager();
                    if(typeof loadFinance === 'function') loadFinance();
                } else {
                    alert(data.error);
                }
            } catch(e){}
        }

        loadManager();
    </script>
</body>
</html>
