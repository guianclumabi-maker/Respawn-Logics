<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$email = $user ? $user['email'] : ($_SESSION['user_email'] ?? '');
$is_manager = hasPermission('attendance.manage');

?>
<?php $page_title = 'Attendance - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .attendance-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }
        @media(min-width: 992px) {
            .attendance-container { grid-template-columns: 350px 1fr; }
        }
        .clock-panel {
            text-align: center;
            padding: 40px 20px;
        }
        .clock-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(0, 224, 122, 0.1);
            border: 2px solid rgba(0, 224, 122, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px auto;
            color: var(--accent-purple);
        }
        .clock-time {
            font-size: 2.5rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
            font-family: 'Space Grotesk';
        }
        .clock-date {
            color: var(--text-muted);
            margin-bottom: 30px;
        }
        .btn-clock {
            display: inline-block;
            padding: 12px 30px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
            width: 100%;
            font-size: 1rem;
        }
        .btn-clock-in {
            background: linear-gradient(135deg, var(--accent-purple), var(--accent-pink));
            color: white;
            box-shadow: 0 4px 15px rgba(0, 224, 122, 0.3);
        }
        .btn-clock-in:hover {
            box-shadow: 0 6px 20px rgba(0, 224, 122, 0.5);
            transform: translateY(-2px);
        }
        .btn-clock-out {
            background: rgba(255,255,255,0.05);
            color: white;
            border: 1px solid rgba(255,255,255,0.1);
        }
        .btn-clock-out:hover {
            background: rgba(255,255,255,0.1);
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .table th, .table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        .table th {
            color: var(--text-muted);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-ontime {
            background: rgba(0, 224, 122, 0.1);
            color: #34d399;
            border: 1px solid rgba(0, 224, 122, 0.2);
        }
        .status-late {
            background: rgba(239, 68, 68, 0.1);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
        }
        .tab-btn.active {
            color: var(--accent-purple);
            border-bottom-color: var(--accent-purple);
        }
    </style>


<body>
    <div class="app-layout">
        <!-- Sidebar -->
        <?php $current_page = 'attendance.php'; include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="content-wrapper">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div>
                        <h2 style="font-family: 'Space Grotesk'; margin-bottom: 5px;">Time & Attendance</h2>
                        <p style="color: var(--text-muted); font-size: 0.9rem;">Track your hours and manage your timesheets.</p>
                    </div>
                    <?php if ($is_manager): ?>
                    <div>
                        <button class="tab-btn active" onclick="switchTab('my-logs', this)">My Logs</button>
                        <button class="tab-btn" onclick="switchTab('approvals', this)">Manager Approvals</button>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- MY LOGS VIEW -->
                <div id="view-my-logs" class="attendance-container">
                    <div class="card-panel clock-panel">
                        <div class="clock-circle">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div class="clock-time" id="liveTime">00:00:00</div>
                        <div class="clock-date" id="liveDate">-- ---- ----</div>
                        
                        <div id="clockStatusText" style="margin-bottom: 20px; font-weight: 500;">Loading status...</div>
                        <div id="clockActionContainer"></div>
                    </div>

                    <div class="card-panel" style="padding: 0;">
                        <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                            <h3 style="font-size: 1.1rem;">Recent Timesheets</h3>
                        </div>
                        <div style="overflow-x: auto;">
                            <table class="table" id="timesheetTable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Clock In</th>
                                        <th>Clock Out</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Approval</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">Loading logs...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- MANAGER APPROVALS VIEW -->
                <?php if ($is_manager): ?>
                <div id="view-approvals" class="card-panel" style="display: none; padding: 0;">
                    <div style="padding: 20px; border-bottom: 1px solid var(--border-color);">
                        <h3 style="font-size: 1.1rem;">Pending Approvals</h3>
                        <p style="color: var(--text-muted); font-size: 0.85rem;">Review and approve employee timesheets.</p>
                    </div>
                    <div style="overflow-x: auto;">
                        <table class="table" id="approvalsTable">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Status</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" style="text-align: center; color: var(--text-muted);">Loading pending approvals...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

<script>
const API_URL = "<?= url('/api/index.php?route=attendance') ?>";

// Real-time clock
setInterval(() => {
    const now = new Date();
    document.getElementById('liveTime').textContent = now.toLocaleTimeString();
    document.getElementById('liveDate').textContent = now.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}, 1000);

async function fetchStatus() {
    try {
        const res = await fetch(`${API_URL}?action=status`);
        const json = await res.json();
        const container = document.getElementById('clockActionContainer');
        const text = document.getElementById('clockStatusText');
        
        if (json.data.state === 'in') {
            text.textContent = `Clocked in at ${new Date(json.data.log.time_in).toLocaleTimeString()}`;
            text.style.color = '#34d399';
            container.innerHTML = `<button class="btn-clock btn-clock-out" onclick="performClock('clock_out')">Clock Out</button>`;
        } else if (json.data.state === 'completed') {
            text.textContent = `Shift completed for today.`;
            text.style.color = 'var(--text-muted)';
            container.innerHTML = `<button class="btn-clock btn-clock-out" disabled style="opacity:0.5; cursor:not-allowed;">Shift Done</button>`;
        } else {
            text.textContent = `Ready to start your day?`;
            text.style.color = '#fff';
            container.innerHTML = `<button class="btn-clock btn-clock-in" onclick="performClock('clock_in')">Clock In Now</button>`;
        }
    } catch (e) {
        console.error(e);
    }
}

async function performClock(action) {
    try {
        const res = await fetch(`${API_URL}?action=${action}`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            fetchStatus();
            fetchTimesheets();
        } else {
            alert(json.error);
        }
    } catch (e) {
        console.error(e);
    }
}

async function fetchTimesheets() {
    try {
        const res = await fetch(`${API_URL}?action=timesheet`);
        const json = await res.json();
        const tbody = document.querySelector('#timesheetTable tbody');
        
        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-muted); padding: 30px;">No timesheets found.</td></tr>`;
            return;
        }
        
        tbody.innerHTML = json.data.map(log => {
            const timeIn = new Date(log.time_in).toLocaleTimeString();
            const timeOut = log.time_out ? new Date(log.time_out).toLocaleTimeString() : '<span style="color:var(--accent-yellow)">Active</span>';
            const statusBadge = log.status === 'Late' 
                ? `<span class="badge-status status-late">Late</span>` 
                : `<span class="badge-status status-ontime">On Time</span>`;
            const approval = parseInt(log.manager_approved) === 1 
                ? `<span style="color:#34d399">Approved</span>` 
                : `<span style="color:var(--text-muted)">Pending</span>`;
                
            return `
                <tr>
                    <td>${new Date(log.time_in).toLocaleDateString()}</td>
                    <td>${timeIn}</td>
                    <td>${timeOut}</td>
                    <td>${statusBadge}</td>
                    <td style="text-align: right;">${approval}</td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        console.error(e);
    }
}

<?php if ($is_manager): ?>
async function fetchApprovals() {
    try {
        const res = await fetch(`${API_URL}?action=pending_approvals`);
        const json = await res.json();
        const tbody = document.querySelector('#approvalsTable tbody');
        
        if (!json.data || json.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" style="text-align: center; color: var(--text-muted); padding: 40px;">All caught up! No pending timesheets.</td></tr>`;
            return;
        }
        
        tbody.innerHTML = json.data.map(log => {
            const timeIn = new Date(log.time_in).toLocaleTimeString();
            const timeOut = log.time_out ? new Date(log.time_out).toLocaleTimeString() : 'Active';
            const statusBadge = log.status === 'Late' 
                ? `<span class="badge-status status-late">Late</span>` 
                : `<span class="badge-status status-ontime">On Time</span>`;
                
            return `
                <tr id="approval-row-${log.id}">
                    <td>
                        <div style="font-weight: 500; color: #fff;">${log.full_name}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">${log.department || 'N/A'}</div>
                    </td>
                    <td>${new Date(log.time_in).toLocaleDateString()}</td>
                    <td>${timeIn}</td>
                    <td>${timeOut}</td>
                    <td>${statusBadge}</td>
                    <td style="text-align: right;">
                        <button onclick="approveTimesheet(${log.id})" style="padding: 6px 12px; background: rgba(0, 224, 122, 0.1); border: 1px solid rgba(0, 224, 122, 0.3); color: #c084fc; border-radius: var(--radius-sm); cursor: pointer; font-size: 0.8rem; transition: all 0.2s;">
                            Approve
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    } catch (e) {
        console.error(e);
    }
}

async function approveTimesheet(id) {
    try {
        const res = await fetch(`${API_URL}?action=approve_timesheet`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ record_id: id })
        });
        const json = await res.json();
        if (json.success) {
            document.getElementById(`approval-row-${id}`).remove();
            fetchTimesheets(); // Refresh my logs if I'm a manager viewing my own
        } else {
            alert(json.error);
        }
    } catch (e) {
        console.error(e);
    }
}

function switchTab(view, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.getElementById('view-my-logs').style.display = 'none';
    document.getElementById('view-approvals').style.display = 'none';
    
    document.getElementById(`view-${view}`).style.display = view === 'my-logs' ? 'grid' : 'block';
    
    if (view === 'approvals') fetchApprovals();
}
<?php endif; ?>

// Init
fetchStatus();
fetchTimesheets();

</script>
</body>
</html>
