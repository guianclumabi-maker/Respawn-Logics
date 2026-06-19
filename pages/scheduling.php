<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
if (!hasPermission('shifts.manage')) {
    header('Location: dashboard.php');
    exit;
}

$current_page = 'scheduling.php';
?>
<?php $page_title = 'Shift Scheduler - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .page-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color);
        }
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.5rem; color: var(--text-primary); margin: 0 0 4px 0; }
        .title-block p { color: var(--text-muted); margin: 0; font-size: 0.9rem; }
        
        .controls-bar {
            display: flex; justify-content: space-between; align-items: center;
            background: var(--bg-secondary); padding: 16px; border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); margin-bottom: 24px;
        }
        
        .week-selector { display: flex; align-items: center; gap: 16px; }
        .week-nav-btn {
            background: transparent; border: 1px solid var(--border-color);
            color: var(--text-primary); width: 36px; height: 36px; border-radius: 50%;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s;
        }
        .week-nav-btn:hover { background: rgba(255,255,255,0.1); }
        .week-label { font-weight: 600; font-family: 'Space Grotesk'; font-size: 1.1rem; min-width: 250px; text-align: center; }
        
        .roster-container {
            background: var(--bg-secondary); border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); overflow-x: auto;
        }
        .roster-table { width: 100%; border-collapse: collapse; min-width: 1000px; }
        .roster-table th {
            padding: 16px; text-align: left; background: rgba(0,0,0,0.2);
            color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;
            border-bottom: 1px solid var(--border-color); border-right: 1px solid var(--border-color);
        }
        .roster-table th.emp-col { width: 250px; position: sticky; left: 0; background: var(--bg-secondary); z-index: 2; }
        .roster-table td {
            padding: 12px; border-bottom: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color); vertical-align: top;
            background: var(--bg-primary);
        }
        .roster-table td.emp-col {
            position: sticky; left: 0; background: var(--bg-secondary); z-index: 1;
        }
        
        .emp-card { display: flex; align-items: center; gap: 12px; }
        .emp-avatar {
            width: 36px; height: 36px; border-radius: 50%; background: var(--bg-primary);
            display: flex; align-items: center; justify-content: center; font-weight: bold; overflow: hidden;
        }
        .emp-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .emp-name { font-weight: 600; color: var(--text-primary); font-size: 0.9rem; margin-bottom: 2px;}
        .emp-dept { font-size: 0.75rem; color: var(--text-muted); }
        
        .shift-select {
            width: 100%; padding: 8px; border-radius: 6px;
            background: var(--bg-secondary); color: var(--text-primary);
            border: 1px solid var(--border-color); font-size: 0.8rem;
            cursor: pointer; appearance: none;
        }
        .shift-select:focus { border-color: var(--accent-blue); outline: none; }
        .shift-select.assigned { background: rgba(59, 130, 246, 0.1); border-color: #3b82f6; color: #60a5fa; font-weight: 600; }
        
        /* Modal for creating shifts */
        .modal {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center;
        }
        .modal.show { display: flex; }
        .modal-content {
            background: var(--bg-secondary); width: 450px; border-radius: var(--radius-lg);
            border: 1px solid var(--border-color); box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5);
            overflow: hidden;
        }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; color: white; font-family: 'Space Grotesk'; }
        .modal-body { padding: 20px; }
        .modal-footer { padding: 20px; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 12px; }
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="title-block">
                        <h1>Shift Scheduler</h1>
                        <p>Manage your weekly team roster and automate shift notifications.</p>
                    </div>
                    <div style="display: flex; gap: 12px;">
                        <button class="btn btn-secondary" onclick="openShiftModal()"><i data-lucide="settings"></i> Manage Shift Types</button>
                        <button class="btn btn-primary" onclick="publishSchedule()"><i data-lucide="send"></i> Publish Schedule</button>
                    </div>
                </div>

                <div class="controls-bar">
                    <div class="week-selector">
                        <button class="week-nav-btn" onclick="prevWeek()"><i data-lucide="chevron-left"></i></button>
                        <div class="week-label" id="weekLabel">Loading Week...</div>
                        <button class="week-nav-btn" onclick="nextWeek()"><i data-lucide="chevron-right"></i></button>
                    </div>
                    <div style="color: var(--text-muted); font-size: 0.85rem;">
                        <i data-lucide="info"></i> Changes made here must be published to notify employees.
                    </div>
                </div>

                <div class="roster-container">
                    <table class="roster-table">
                        <thead>
                            <tr id="rosterHeader">
                                <th class="emp-col">Employee</th>
                                <!-- Days will be injected here -->
                            </tr>
                        </thead>
                        <tbody id="rosterBody">
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">Loading Roster...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Shift Type Modal -->
    <div class="modal" id="shiftModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Manage Shift Types</h3>
                <button class="btn btn-secondary" style="padding: 4px 8px;" onclick="closeShiftModal()"><i data-lucide="x"></i></button>
            </div>
            <div class="modal-body">
                <div id="existingShifts" style="margin-bottom: 24px; max-height: 150px; overflow-y: auto;"></div>
                
                <hr style="border-color: var(--border-color); margin-bottom: 16px;">
                <h4 style="color: white; margin-bottom: 12px; font-size: 0.95rem;">Create New Shift</h4>
                <div class="form-group" style="margin-bottom: 12px;">
                    <label class="form-label">Shift Name (e.g. Morning Shift)</label>
                    <input type="text" id="newShiftName" class="form-input">
                </div>
                <div style="display: flex; gap: 12px; margin-bottom: 12px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Start Time</label>
                        <input type="time" id="newShiftStart" class="form-input">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">End Time</label>
                        <input type="time" id="newShiftEnd" class="form-input">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeShiftModal()">Cancel</button>
                <button class="btn btn-primary" onclick="createShift()">Save Shift Type</button>
            </div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        // Adjust to Monday of current week
        const day = currentDate.getDay();
        const diff = currentDate.getDate() - day + (day == 0 ? -6 : 1);
        currentDate = new Date(currentDate.setDate(diff));
        
        let shiftTypes = [];
        let rosterChanges = {}; // { userId_dateStr: shiftId }

        function getDatesForWeek(startDate) {
            let dates = [];
            for (let i = 0; i < 7; i++) {
                let d = new Date(startDate);
                d.setDate(startDate.getDate() + i);
                dates.push(d);
            }
            return dates;
        }

        function formatDateYMD(date) {
            return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
        }

        function formatDisplayDate(date) {
            const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${days[date.getDay()]} ${months[date.getMonth()]} ${date.getDate()}`;
        }

        async function loadShiftTypes() {
            try {
                const res = await fetch(`<?= url('/api/index.php?route=shifts&action=fetch_shift_types') ?>`);
                const data = await res.json();
                if (data.success) {
                    shiftTypes = data.data;
                    renderShiftTypesList();
                }
            } catch (e) {
                console.error(e);
            }
        }

        async function loadRoster() {
            const dates = getDatesForWeek(currentDate);
            const startDateStr = formatDateYMD(dates[0]);
            const endDateStr = formatDateYMD(dates[6]);
            
            document.getElementById('weekLabel').innerText = `${formatDisplayDate(dates[0])}  -  ${formatDisplayDate(dates[6])}`;
            
            // Build Headers
            let headerHtml = `<th class="emp-col">Employee</th>`;
            dates.forEach(d => {
                const isToday = formatDateYMD(d) === formatDateYMD(new Date()) ? 'color: #3b82f6;' : '';
                headerHtml += `<th style="${isToday}">${formatDisplayDate(d)}</th>`;
            });
            document.getElementById('rosterHeader').innerHTML = headerHtml;

            // Fetch Data
            try {
                const res = await fetch(`<?= url('/api/index.php?route=shifts&action=fetch_roster') ?>?start_date=${startDateStr}&end_date=${endDateStr}`);
                const data = await res.json();
                
                if (data.success) {
                    renderRoster(data.data, dates);
                } else {
                    document.getElementById('rosterBody').innerHTML = `<tr><td colspan="8">Failed to load roster.</td></tr>`;
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderRoster(employees, dates) {
            const tbody = document.getElementById('rosterBody');
            if (employees.length === 0) {
                tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:30px;">No employees found.</td></tr>`;
                return;
            }

            let html = '';
            
            // Generate <option> tags for shift select
            let shiftOptionsHtml = `<option value="0">Off / Unassigned</option>`;
            shiftTypes.forEach(s => {
                shiftOptionsHtml += `<option value="${s.id}">${s.name}</option>`;
            });

            employees.forEach(emp => {
                const initials = emp.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                const avatar = emp.profile_image ? `<img src="<?= url('/uploads/') ?>${emp.profile_image}">` : initials;
                
                html += `<tr>`;
                html += `
                    <td class="emp-col">
                        <div class="emp-card">
                            <div class="emp-avatar">${avatar}</div>
                            <div>
                                <div class="emp-name">${emp.full_name}</div>
                                <div class="emp-dept">${emp.job_title} &bull; ${emp.department}</div>
                            </div>
                        </div>
                    </td>
                `;

                dates.forEach(d => {
                    const dateStr = formatDateYMD(d);
                    const shiftData = emp.shifts ? emp.shifts[dateStr] : null;
                    const assignedShiftId = shiftData ? shiftData.shift_id : 0;
                    
                    const isChanged = rosterChanges[`${emp.user_id}_${dateStr}`] !== undefined;
                    const currentVal = isChanged ? rosterChanges[`${emp.user_id}_${dateStr}`] : assignedShiftId;
                    
                    const activeClass = currentVal > 0 ? 'assigned' : '';

                    html += `
                        <td>
                            <select class="shift-select ${activeClass}" 
                                    onchange="recordChange(${emp.user_id}, '${dateStr}', this.value, this)"
                                    data-original="${assignedShiftId}">
                    `;
                    
                    html += `<option value="0">Off</option>`;
                    shiftTypes.forEach(s => {
                        const selected = (s.id == currentVal) ? 'selected' : '';
                        html += `<option value="${s.id}" ${selected}>${s.name}</option>`;
                    });
                    
                    html += `</select></td>`;
                });

                html += `</tr>`;
            });
            tbody.innerHTML = html;
        }

        function recordChange(userId, dateStr, shiftId, selectEl) {
            rosterChanges[`${userId}_${dateStr}`] = parseInt(shiftId);
            
            if (shiftId > 0) {
                selectEl.classList.add('assigned');
            } else {
                selectEl.classList.remove('assigned');
            }
        }

        async function publishSchedule() {
            const changes = Object.keys(rosterChanges).map(key => {
                const parts = key.split('_');
                return {
                    user_id: parts[0],
                    date: parts[1],
                    shift_id: rosterChanges[key]
                };
            });

            if (changes.length === 0) {
                alert('No changes to publish.');
                return;
            }

            try {
                const res = await fetch(`<?= url('/api/index.php?route=shifts&action=publish_roster') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ },
                    body: JSON.stringify({ assignments: changes })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Schedule published! Notifications have been sent to updated employees.');
                    rosterChanges = {}; // clear changes
                    loadRoster(); // reload
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                console.error(e);
                alert('Failed to publish schedule.');
            }
        }

        // Navigation
        function prevWeek() {
            currentDate.setDate(currentDate.getDate() - 7);
            rosterChanges = {};
            loadRoster();
        }
        function nextWeek() {
            currentDate.setDate(currentDate.getDate() + 7);
            rosterChanges = {};
            loadRoster();
        }

        // Modal Logic
        function openShiftModal() {
            document.getElementById('shiftModal').classList.add('show');
            renderShiftTypesList();
        }
        function closeShiftModal() {
            document.getElementById('shiftModal').classList.remove('show');
        }
        function renderShiftTypesList() {
            const container = document.getElementById('existingShifts');
            if (shiftTypes.length === 0) {
                container.innerHTML = '<div style="color:var(--text-muted); font-size:0.85rem;">No shifts defined yet.</div>';
                return;
            }
            let html = '<ul style="list-style:none; padding:0; margin:0;">';
            shiftTypes.forEach(s => {
                html += `<li style="padding: 8px; border: 1px solid var(--border-color); border-radius: 6px; margin-bottom: 8px; font-size: 0.85rem; color: #d1d5db; display: flex; justify-content: space-between;">
                    <strong>${s.name}</strong> <span>${s.start_time.substring(0,5)} - ${s.end_time.substring(0,5)}</span>
                </li>`;
            });
            html += '</ul>';
            container.innerHTML = html;
        }

        async function createShift() {
            const name = document.getElementById('newShiftName').value;
            const start = document.getElementById('newShiftStart').value;
            const end = document.getElementById('newShiftEnd').value;

            if (!name || !start || !end) return alert('Fill all fields');

            try {
                const res = await fetch(`<?= url('/api/index.php?route=shifts&action=create_shift_type') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__ },
                    body: JSON.stringify({ name: name, start_time: start, end_time: end })
                });
                const data = await res.json();
                if (data.success) {
                    document.getElementById('newShiftName').value = '';
                    document.getElementById('newShiftStart').value = '';
                    document.getElementById('newShiftEnd').value = '';
                    await loadShiftTypes();
                    loadRoster(); // Reload to show new option in dropdowns
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                console.error(e);
            }
        }

        // Boot
        document.addEventListener('DOMContentLoaded', async () => {
            await loadShiftTypes();
            loadRoster();
        });
    </script>
</body>
</html>
