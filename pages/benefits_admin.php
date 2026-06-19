<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('benefits.manage')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Benefits Administration - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    .global-glow-purple {
            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;
        }

        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .admin-title h1 { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 4px; }
        .admin-title p { font-size: 0.875rem; color: var(--text-muted); }
        
        .tab-nav {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 12px;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 0.875rem;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        .tab-btn.active {
            color: white;
            background: rgba(255,255,255,0.05);
        }
        
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th, .data-table td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); font-size: 0.875rem; }
        .data-table th { color: var(--text-muted); font-weight: 600; }
        .data-table td { color: #fff; }
    </style>


<body>
    <div class="ambient-glow glow-emerald"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="admin-header">
                <div class="admin-title">
                    <h1>Benefits Administration</h1>
                    <p>Manage company-sponsored HMO plans, De Minimis allowances, and track employee enrollments.</p>
                </div>
            </div>

            <div class="tab-nav">
                <button class="tab-btn active" id="btnPlans">Benefit Plans</button>
                <button class="tab-btn" id="btnEnrollments">Employee Enrollments</button>
            </div>

            <!-- Benefit Plans -->
            <div id="panePlans" class="tab-pane active">
                <div style="background: rgba(22, 25, 34, 0.7); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
                        <h3 style="color:white; margin:0; font-size:1.1rem;">Active Benefit Plans</h3>
                        <button class="btn-primary" onclick="document.getElementById('planModal').classList.add('active')" style="font-size:0.75rem; padding:6px 12px;">+ New Plan</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Provider</th>
                                <th>Type</th>
                                <th>Company Cost (Principal)</th>
                                <th>Employee Cost (Dependent)</th>
                                <th>Enrolled Emps</th>
                            </tr>
                        </thead>
                        <tbody id="plans-tbody">
                            <tr><td colspan="6" style="text-align:center;"><div class="spinner"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Employee Enrollments -->
            <div id="paneEnrollments" class="tab-pane">
                <div style="background: rgba(22, 25, 34, 0.7); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 24px;">
                    <h3 style="color:white; margin-bottom:16px; font-size:1.1rem;">Company-wide Enrollments</h3>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Plan Name</th>
                                <th>Type</th>
                                <th>Dependents Enrolled</th>
                                <th>Payroll Deduction</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="enrollments-tbody">
                            <tr><td colspan="6" style="text-align:center;"><div class="spinner"></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </main>
    </div>

    <!-- Create Plan Modal -->
    <div class="modal-overlay" id="planModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:100;">
        <div class="modal-content" style="background:#161922; border:1px solid var(--border-color); border-radius:var(--radius-lg); width:450px; max-width:90vw;">
            <div style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color:white; font-size:1.1rem; margin:0;">Create Benefit Plan</h3>
                <button onclick="document.getElementById('planModal').classList.remove('active')" style="background:transparent; border:none; color:white; cursor:pointer;">X</button>
            </div>
            <div style="padding:20px;">
                <form id="form-plan" onsubmit="submitPlan(event)">
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Plan Name</label>
                        <input type="text" name="name" class="form-input" required placeholder="e.g. Maxicare Platinum">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Provider</label>
                        <input type="text" name="provider" class="form-input" placeholder="e.g. Maxicare">
                    </div>
                    <div style="margin-bottom:12px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Type</label>
                        <select name="type" class="form-input">
                            <option value="HMO">HMO</option>
                            <option value="De Minimis">De Minimis Allowance</option>
                            <option value="Perk">Perk</option>
                        </select>
                    </div>
                    <div style="display:flex; gap:12px; margin-bottom:12px;">
                        <div style="flex:1;">
                            <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Company Cost</label>
                            <input type="number" step="0.01" name="company_cost" class="form-input" value="0.00">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Employee Cost (per Dep)</label>
                            <input type="number" step="0.01" name="employee_cost" class="form-input" value="0.00">
                        </div>
                    </div>
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Description</label>
                        <textarea name="description" class="form-input" rows="2"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Create Plan</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('btnPlans').addEventListener('click', () => {
            document.getElementById('btnPlans').classList.add('active');
            document.getElementById('btnEnrollments').classList.remove('active');
            document.getElementById('panePlans').classList.add('active');
            document.getElementById('paneEnrollments').classList.remove('active');
            loadPlans();
        });

        document.getElementById('btnEnrollments').addEventListener('click', () => {
            document.getElementById('btnEnrollments').classList.add('active');
            document.getElementById('btnPlans').classList.remove('active');
            document.getElementById('paneEnrollments').classList.add('active');
            document.getElementById('panePlans').classList.remove('active');
            loadEnrollments();
        });

        async function loadPlans() {
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=hr_plans') ?>`);
                const data = await res.json();
                const tbody = document.getElementById('plans-tbody');
                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(p => `
                        <tr>
                            <td><strong>${p.name}</strong><br><small style="color:var(--text-muted);">${p.description}</small></td>
                            <td>${p.provider}</td>
                            <td><span style="background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px; font-size:0.7rem;">${p.type}</span></td>
                            <td>$${parseFloat(p.company_cost).toLocaleString()}</td>
                            <td>$${parseFloat(p.employee_cost).toLocaleString()}</td>
                            <td>${p.enrolled_count} <svg style="width:12px;display:inline;vertical-align:middle;margin-left:4px;color:var(--text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg></td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No benefit plans found.</td></tr>`;
                }
            } catch(e){}
        }

        async function loadEnrollments() {
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=hr_enrollments') ?>`);
                const data = await res.json();
                const tbody = document.getElementById('enrollments-tbody');
                if (data.success && data.data.length > 0) {
                    tbody.innerHTML = data.data.map(e => `
                        <tr>
                            <td><strong>${e.full_name}</strong><br><small style="color:var(--text-muted);">${e.employee_number}</small></td>
                            <td>${e.plan_name}</td>
                            <td>${e.type}</td>
                            <td>${e.dependent_count}</td>
                            <td style="color:#ef4444;">$${(parseFloat(e.employee_cost) * parseFloat(e.dependent_count)).toLocaleString()}</td>
                            <td style="color:#00e07a;">${e.status}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--text-muted);">No enrollments found.</td></tr>`;
                }
            } catch(e){}
        }

        window.submitPlan = async function(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-plan'));
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=hr_create_plan') ?>`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('planModal').classList.remove('active');
                    loadPlans();
                } else { alert(data.error); }
            } catch(e){}
        };

        // Init
        document.addEventListener('DOMContentLoaded', loadPlans);
    </script>
</body>
</html>
