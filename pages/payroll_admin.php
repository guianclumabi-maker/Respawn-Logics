<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('payroll.manage')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$user = getCurrentUser();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Enterprise Payroll - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        body {
            background-color: #f8fafc !important; /* slate-100 for separation */
        }
        .main-content {
            background-color: #f8fafc !important; /* slate-100 for separation */
            position: relative;
            z-index: 0;
        }
        /* Global Background Glow Effects for Light Mode */
        .global-glow-green {
            position: fixed; top: -100px; left: -100px; width: 500px; height: 500px; border-radius: 50%; background: #00e07a; filter: blur(120px); opacity: 0.08; pointer-events: none; z-index: -1;
        }
        .global-glow-purple {
            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        
        .admin-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }
        
        .admin-title p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .tabs-header {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 24px;
            overflow-x: auto;
            white-space: nowrap;
        }

        .tab-btn {
            padding: 12px 24px;
            color: var(--text-muted);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }

        .tab-btn:hover {
            color: white;
        }

        .tab-btn.active {
            color: #00e07a;
            border-bottom-color: #00e07a;
        }

        .panel-container {
            background: rgba(22, 25, 34, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            vertical-align: middle;
            color: white;
            font-size: 0.875rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-Draft, .status-Info { background: rgba(0, 224, 122, 0.1); color: #c084fc; border: 1px solid rgba(0, 224, 122, 0.2); }
        .status-Approved, .status-Published { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }
        .status-Processed, .status-Ready { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .status-Locked, .status-Critical { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-Warning { background: rgba(245, 158, 11, 0.1); color: #fcd34d; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-Processing { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); animation: pulse 2s infinite; }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-control {
            width: 100%;
            background: rgba(26, 29, 39, 0.8);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            color: white;
        }

        .modal-overlay {
            position: fixed; top:0; left:0; right:0; bottom:0;
            background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; pointer-events: none; transition: opacity 0.2s; z-index: 100;
        }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-content {
            background: #161922; border: 1px solid var(--border-color);
            border-radius: var(--radius-lg); width: 800px; max-width: 90vw;
            max-height: 85vh; display: flex; flex-direction: column;
        }
        .modal-header {
            padding: 20px; border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; }

        .btn-action {
            background: transparent; border: 1px solid var(--border-color);
            color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; cursor: pointer;
        }
        .btn-action:hover { border-color: #00e07a; }

        .currency { color: #00e07a; font-weight: 600; }
        .deduction { color: #ef4444; font-weight: 600; }

        /* Dashboard specific */
        .dash-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px; }
        .dash-card { background: rgba(22, 25, 34, 0.7); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; }
        .dash-card-value { font-size: 1.5rem; font-weight: 700; color: white; margin-top: 10px; }
        .dash-card-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600; margin-top: 4px; }

        @media print {
            body * { visibility: hidden; }
            .print-area, .print-area * { visibility: visible; }
            .print-area { position: absolute; left: 0; top: 0; width: 100%; background: white !important; color: black !important; padding: 20px; border: none; }
            .print-area * { color: black !important; }
            .modal-content { border: none; width: 100%; }
            .modal-header, #details-actions { display: none !important; }
            .modal-overlay { position: static; background: white; }
        }
    </style>


<body>
    <div class="global-glow-green"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Enterprise Payroll Console</h1>
                    <p>Manage Schedules, Generate Payroll Runs, and Process Payments</p>
                </div>
            </div>

            <div class="tabs-header">
                <button class="tab-btn active" data-target="dashboard">Overview</button>
                <button class="tab-btn" data-target="runs">Payroll Queue</button>
                <button class="tab-btn" data-target="exceptions">Exceptions</button>
                <button class="tab-btn" data-target="payslips">Payslips</button>
                <button class="tab-btn" data-target="reports">Gov Reports</button>
                <button class="tab-btn" data-target="settings">Configuration</button>
            </div>

            <!-- Dashboard Panel -->
            <div id="panel-dashboard" class="panel-wrapper">
                <div class="dash-grid">
                    <div class="dash-card">
                        <div class="status-badge status-Ready" style="float:right;">Active</div>
                        <div class="dash-card-value" id="kpi-next-date">Loading...</div>
                        <div class="dash-card-label">Next Payroll Date</div>
                    </div>
                    <div class="dash-card">
                        <div class="status-badge status-Approved" style="float:right;">Forecast</div>
                        <div class="dash-card-value currency" id="kpi-est-cost">Loading...</div>
                        <div class="dash-card-label">Estimated Base Cost</div>
                    </div>
                    <div class="dash-card" style="border-color: rgba(239, 68, 68, 0.3); cursor: pointer;" onclick="document.querySelector('[data-target=\'exceptions\']').click()">
                        <div class="status-badge status-Critical" style="float:right;">Review</div>
                        <div class="dash-card-value" style="color: #ef4444;" id="kpi-exceptions">Loading...</div>
                        <div class="dash-card-label">Critical Exceptions</div>
                    </div>
                    <div class="dash-card">
                        <div class="status-badge status-Processed" style="float:right;">Ready</div>
                        <div class="dash-card-value" id="kpi-readiness">Loading...</div>
                        <div class="dash-card-label">Payroll Readiness</div>
                    </div>
                </div>

                <div class="panel-container">
                    <h3 style="color:white; font-size:1.1rem; margin-bottom: 20px;">Pre-Run Readiness Checklist</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <div style="display:flex; align-items:center; gap: 10px; padding: 12px; background: rgba(0, 224, 122, 0.05); border: 1px solid rgba(0, 224, 122, 0.1); border-radius: 8px;">
                            <span style="color: #00e07a;">✓</span> <span style="color:white; font-size: 0.875rem;">Attendance Imported</span>
                        </div>
                        <div style="display:flex; align-items:center; gap: 10px; padding: 12px; background: rgba(0, 224, 122, 0.05); border: 1px solid rgba(0, 224, 122, 0.1); border-radius: 8px;">
                            <span style="color: #00e07a;">✓</span> <span style="color:white; font-size: 0.875rem;">Leave Imported</span>
                        </div>
                        <div style="display:flex; align-items:center; gap: 10px; padding: 12px; background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.1); border-radius: 8px;">
                            <span style="color: #fcd34d;">○</span> <span style="color:white; font-size: 0.875rem;">Salary Rates Validation Pending</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Runs Panel -->
            <div id="panel-runs" class="panel-wrapper" style="display:none;">
                <div class="panel-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="color:white; font-size:1.1rem;">Payroll Runs</h3>
                        <button class="btn-primary" onclick="openNewRunModal()">Generate New Run</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Run ID</th>
                                <th>Schedule</th>
                                <th>Period</th>
                                <th>Pay Date</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="runs-list"></tbody>
                    </table>
                </div>
            </div>

            <!-- Exceptions Panel -->
            <div id="panel-exceptions" class="panel-wrapper" style="display:none;">
                <div class="panel-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="color:white; font-size:1.1rem;">Exceptions Center</h3>
                        <button class="btn-action">Export Log</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Severity</th>
                                <th>Type</th>
                                <th>Employee</th>
                                <th>Description</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="exceptions-list">
                            <tr><td colspan="5" style="text-align:center;">No active exceptions found.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Payslips Panel -->
            <div id="panel-payslips" class="panel-wrapper" style="display:none;">
                <div class="panel-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="color:white; font-size:1.1rem;">Generated Payslips</h3>
                        <button class="btn-primary" onclick="window.print()">Print Batch</button>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Net Pay</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="payslips-list"></tbody>
                    </table>
                </div>
            </div>

            <!-- Gov Reports Panel -->
            <div id="panel-reports" class="panel-wrapper" style="display:none;">
                <div class="dash-grid">
                    <div class="dash-card" style="text-align:center;">
                        <div class="dash-card-value">SSS</div>
                        <div class="dash-card-label">R-1A, R-3</div>
                    </div>
                    <div class="dash-card" style="text-align:center;">
                        <div class="dash-card-value">PhilHealth</div>
                        <div class="dash-card-label">Er2, RF-1</div>
                    </div>
                    <div class="dash-card" style="text-align:center;">
                        <div class="dash-card-value">Pag-IBIG</div>
                        <div class="dash-card-label">MCRF</div>
                    </div>
                    <div class="dash-card" style="text-align:center;">
                        <div class="dash-card-value">BIR</div>
                        <div class="dash-card-label">1601-C</div>
                    </div>
                </div>
                <div class="panel-container">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="color:white; font-size:1.1rem;">Recent Reports</h3>
                    </div>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Report Type</th>
                                <th>Coverage</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="reports-list">
                            <tr><td colspan="4" style="text-align:center;">No reports generated yet.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Settings Panel -->
            <div id="panel-settings" class="panel-wrapper" style="display:none;">
                <div class="panel-container" style="max-width: 600px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                        <h3 style="color:white; font-size:1.1rem;">Processing Configuration</h3>
                        <button class="btn-primary" onclick="alert('Settings saved')">Save</button>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Calculation Mode</label>
                        <select class="form-control">
                            <option>Manual Review (Draft Mode)</option>
                            <option>Fully Automatic (Direct to Processed)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Apply Statutory Deductions Automatically</label>
                        <select class="form-control">
                            <option>Yes</option>
                            <option>No</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label>Data Extraction</label>
                        <div style="color:white; font-size: 0.875rem; display:flex; flex-direction: column; gap:10px; margin-top: 10px;">
                            <label><input type="checkbox" checked> Import Attendance</label>
                            <label><input type="checkbox" checked> Import Leave</label>
                            <label><input type="checkbox" checked> Import Recurring Modifiers</label>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Modals -->
    <div class="modal-overlay" id="newRunModal">
        <div class="modal-content" style="width: 500px;">
            <div class="modal-header">
                <h3 style="color:white; font-size: 1.1rem; margin:0;">Generate Payroll Run</h3>
                <button class="btn-action" onclick="closeModals()" style="border:none;">X</button>
            </div>
            <div class="modal-body">
                <form id="form-new-run" onsubmit="generateRun(event)">
                    <div class="form-group" style="margin-bottom:16px;">
                        <label>Target Payroll Schedule</label>
                        <select class="form-control" id="run_schedule" required></select>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label>Period Start</label><input type="date" class="form-control" id="run_start" required></div>
                        <div class="form-group"><label>Period End</label><input type="date" class="form-control" id="run_end" required></div>
                    </div>
                    <div class="form-group" style="margin-bottom:24px;">
                        <label>Pay Date</label>
                        <input type="date" class="form-control" id="run_pay_date" required>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Generate Draft Run</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Run Details Modal -->
    <div class="modal-overlay" id="runDetailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color:white; font-size: 1.1rem; margin:0;" id="details-title">Run Details</h3>
                <button class="btn-action" onclick="closeModals()" style="border:none;">X</button>
            </div>
            <div class="modal-body">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div id="details-info" style="color:var(--text-muted); font-size:0.875rem; line-height:1.5;"></div>
                    <div id="details-actions"></div>
                </div>
                <h4 style="color:white; margin-bottom: 12px;">Employee Breakdown</h4>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Gross Pay</th>
                            <th>Taxes/Deds</th>
                            <th>Net Pay</th>
                        </tr>
                    </thead>
                    <tbody id="details-employees"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payslip View Modal -->
    <div class="modal-overlay print-area" id="payslipModal">
        <div class="modal-content" style="width: 800px; background: white; color: black; border-radius: 8px;">
            <div class="modal-header no-print">
                <h3 style="color:black; font-size: 1.1rem; margin:0;">Payslip Details</h3>
                <div>
                    <button class="btn-action" style="color:black; border-color: #ccc; margin-right: 10px;" onclick="window.print()">Print</button>
                    <button class="btn-action" style="color:black; border-color: #ccc;" onclick="closeModals()">X</button>
                </div>
            </div>
            <div class="modal-body" id="payslip-print-content" style="background: white; color: black;">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>

    <script>
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(amount);
        };

        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(t => {
            t.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                t.classList.add('active');
                document.querySelectorAll('.panel-wrapper').forEach(p => p.style.display = 'none');
                document.getElementById('panel-' + t.dataset.target).style.display = 'block';
            });
        });

        async function loadDashboard() {
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=dashboard_kpis') ?>`);
                const data = await res.json();
                if(data.success) {
                    const d = data.data;
                    document.getElementById('kpi-next-date').textContent = d.nextDate;
                    document.getElementById('kpi-est-cost').textContent = formatCurrency(d.estimatedCost);
                    document.getElementById('kpi-exceptions').textContent = d.criticalExceptions;
                    document.getElementById('kpi-readiness').textContent = d.readiness;
                }
            } catch(e){}
        }

        async function loadSchedules() {
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=schedules') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('run_schedule').innerHTML = data.data.map(s => `<option value="${s.id}">${s.name} (${s.frequency})</option>`).join('');
                }
            } catch(e){}
        }

        async function loadRuns() {
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=runs') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('runs-list').innerHTML = data.data.map(r => `
                        <tr>
                            <td>#${r.id}</td>
                            <td>${r.schedule_name || 'Manual'}</td>
                            <td>${r.payroll_period_start} to ${r.payroll_period_end}</td>
                            <td>${r.pay_date}</td>
                            <td><span class="status-badge status-${r.status}">${r.status}</span></td>
                            <td style="text-align:right;">
                                <button class="btn-action" onclick="viewRun(${r.id})">Details</button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        async function loadPayslips() {
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=payslips_admin') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('payslips-list').innerHTML = data.data.map(ps => `
                        <tr>
                            <td style="color:#00e07a; font-weight:bold;">PS-${ps.id}</td>
                            <td>${ps.empName}</td>
                            <td>${ps.period}</td>
                            <td class="currency">${formatCurrency(ps.net)}</td>
                            <td><span class="status-badge status-Published">${ps.status}</span></td>
                            <td style="text-align:right;">
                                <button class="btn-action" onclick="viewPayslip(${ps.id})">View/Print</button>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        async function viewPayslip(id) {
            document.getElementById('payslipModal').classList.add('active');
            document.getElementById('payslip-print-content').innerHTML = `<div>Loading...</div>`;
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=payslip_details&id=') ?>` + id);
                const data = await res.json();
                if(data.success) {
                    const ps = data.data;
                    document.getElementById('payslip-print-content').innerHTML = `
                        <div style="display:flex; justify-content:space-between; border-bottom: 2px solid #ccc; padding-bottom: 20px; margin-bottom: 20px;">
                            <div>
                                <h1 style="font-size: 2rem; margin:0;">PAYSLIP</h1>
                                <p style="color:#666; margin: 4px 0 0 0;">Period: ${ps.period}</p>
                                <p style="color:#666; margin: 0;">ID: PS-${ps.id}</p>
                            </div>
                            <div style="text-align:right;">
                                <h3 style="margin:0; font-size: 1.2rem;">Respawn Logic</h3>
                                <p style="color:#666; margin:0;">Enterprise HRIS</p>
                            </div>
                        </div>
                        <div style="background: #f9f9f9; padding: 15px; border-radius: 8px; display:flex; justify-content:space-between; margin-bottom: 20px;">
                            <div>
                                <strong style="display:block; margin-bottom: 4px;">${ps.empName}</strong>
                                <span style="color:#666;">ID: EMP-${ps.employee_id}</span>
                            </div>
                            <div style="text-align:right;">
                                <span style="color: #00e07a; font-weight:bold;">Published</span>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px;">
                            <div>
                                <h4 style="border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 10px; text-transform:uppercase;">Earnings</h4>
                                ${ps.earnings.map(e => `<div style="display:flex; justify-content:space-between; margin-bottom: 5px;"><span>${e.label}</span><strong>${formatCurrency(e.amount)}</strong></div>`).join('')}
                                <div style="display:flex; justify-content:space-between; margin-top: 15px; border-top: 1px solid #ccc; padding-top: 10px;">
                                    <strong>Gross</strong><strong>${formatCurrency(ps.gross)}</strong>
                                </div>
                            </div>
                            <div>
                                <h4 style="border-bottom: 1px solid #000; padding-bottom: 5px; margin-bottom: 10px; text-transform:uppercase;">Deductions</h4>
                                ${ps.deductions.map(d => `<div style="display:flex; justify-content:space-between; margin-bottom: 5px;"><span>${d.label}</span><strong style="color:red;">${formatCurrency(d.amount)}</strong></div>`).join('')}
                                <div style="display:flex; justify-content:space-between; margin-top: 15px; border-top: 1px solid #ccc; padding-top: 10px;">
                                    <strong style="color:red;">Total Deductions</strong><strong style="color:red;">- ${formatCurrency(ps.totalDeductions)}</strong>
                                </div>
                            </div>
                        </div>
                        <div style="background: #222; color: white; padding: 20px; border-radius: 8px; display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size: 1.2rem; font-weight:bold;">NET PAY</span>
                            <span style="font-size: 2rem; font-weight:bold; color: #34d399;">${formatCurrency(ps.netPay)}</span>
                        </div>
                    `;
                }
            } catch(e){}
        }

        function openNewRunModal() {
            document.getElementById('newRunModal').classList.add('active');
        }

        function closeModals() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        }

        async function generateRun(e) {
            e.preventDefault();
            const payload = {
                schedule_id: document.getElementById('run_schedule').value,
                start_date: document.getElementById('run_start').value,
                end_date: document.getElementById('run_end').value,
                pay_date: document.getElementById('run_pay_date').value
            };
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=generate_run') ?>`, {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if(data.success) {
                    if (data.warnings && data.warnings.length > 0) {
                        alert("Run generated with warnings:\n" + data.warnings.join("\n"));
                    } else {
                        alert("Run Generated Successfully.");
                    }
                    closeModals();
                    loadRuns();
                    loadDashboard();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch(e) {}
        }

        async function viewRun(id) {
            document.getElementById('runDetailsModal').classList.add('active');
            document.getElementById('details-employees').innerHTML = `<tr><td colspan="4">Loading...</td></tr>`;
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=run_details&id=') ?>` + id);
                const data = await res.json();
                if(data.success) {
                    const r = data.data.run;
                    document.getElementById('details-title').textContent = `Run #${r.id} - ${r.status}`;
                    document.getElementById('details-info').innerHTML = `
                        <strong>Period:</strong> ${r.payroll_period_start} to ${r.payroll_period_end}<br>
                        <strong>Pay Date:</strong> ${r.pay_date}
                    `;

                    let actionsHtml = '';
                    if (r.status === 'Draft') {
                        actionsHtml = `<button class="btn-primary" onclick="updateRunStatus(${r.id}, 'Approved')">Approve Run</button>`;
                    } else if (r.status === 'Approved') {
                        actionsHtml = `<button class="btn-primary" style="background:#00e07a;" onclick="updateRunStatus(${r.id}, 'Processed')">Process & Generate Payslips</button>`;
                    } else if (r.status === 'Processed') {
                        actionsHtml = `<button class="btn-primary" style="background:#ef4444;" onclick="updateRunStatus(${r.id}, 'Locked')">Lock Audit Ledger</button>`;
                    }
                    document.getElementById('details-actions').innerHTML = actionsHtml;

                    document.getElementById('details-employees').innerHTML = data.data.employees.map(emp => `
                        <tr>
                            <td>
                                <div style="font-weight:600;">${emp.full_name}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${emp.employee_number || 'N/A'} - ${emp.department || 'N/A'}</div>
                            </td>
                            <td class="currency">${formatCurrency(emp.gross_pay)}</td>
                            <td class="deduction">-${formatCurrency(emp.total_deductions)}</td>
                            <td style="font-weight:700; color:white;">${formatCurrency(emp.net_pay)}</td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        async function updateRunStatus(id, status) {
            if(!confirm(`Are you sure you want to change status to ${status}?`)) return;
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=update_run_status') ?>`, {
                    method: 'POST',
                    body: JSON.stringify({run_id: id, status: status})
                });
                const data = await res.json();
                if(data.success) {
                    viewRun(id);
                    loadRuns();
                    loadPayslips();
                } else {
                    alert(data.error);
                }
            } catch(e){}
        }

        loadDashboard();
        loadSchedules();
        loadRuns();
        loadPayslips();
    </script>
</body>
</html>
