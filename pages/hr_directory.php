<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('employees.view')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$user = getCurrentUser();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Employee Master Directory - Core HR'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
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

        .directory-panel {
            background: rgba(22, 25, 34, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box input {
            width: 100%;
            background: rgba(26, 29, 39, 0.8);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 8px 12px 8px 36px;
            color: white;
            font-size: 0.875rem;
            outline: none;
            transition: all 0.2s;
        }

        .search-box svg {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            text-align: left;
            padding: 12px 16px;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--border-color);
        }

        .users-table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.02);
            vertical-align: middle;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: rgba(0, 224, 122, 0.15);
            border: 1px solid rgba(0, 224, 122, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            font-weight: 700;
            color: #c084fc;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin-bottom: 2px;
        }

        .user-email {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-Active { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .status-Terminated { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .status-LOA { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-Probation { background: rgba(0, 224, 122, 0.1); color: #c084fc; border: 1px solid rgba(0, 224, 122, 0.2); }

        .btn-view-master {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            padding: 6px 12px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-view-master:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(255, 255, 255, 0.2);
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s ease;
        }

        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }

        .modal-content {
            background: #161922;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            width: 800px;
            max-width: 90vw;
            height: 80vh;
            display: flex;
            flex-direction: column;
            transform: scale(0.95);
            transition: transform 0.2s ease;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
        }

        .modal-overlay.active .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: white;
        }

        .modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }

        .modal-close:hover {
            color: white;
        }

        .modal-body {
            display: flex;
            flex: 1;
            overflow: hidden;
        }

        .modal-sidebar {
            width: 200px;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            padding: 20px 0;
            background: rgba(0,0,0,0.2);
        }

        .modal-tab {
            padding: 10px 20px;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 0.875rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .modal-tab:hover {
            color: white;
            background: rgba(255,255,255,0.02);
        }

        .modal-tab.active {
            color: #c084fc;
            background: rgba(0, 224, 122, 0.05);
            border-left-color: #00e07a;
            font-weight: 600;
        }

        .modal-main {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .form-input {
            width: 100%;
            background: rgba(26, 29, 39, 0.8);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 12px;
            color: white;
            font-size: 0.875rem;
            outline: none;
        }

        .form-input:focus {
            border-color: rgba(0, 224, 122, 0.5);
        }

        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239ca3af'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
            padding-right: 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .btn-save {
            background: linear-gradient(135deg, #00e07a 0%, #d946ef 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            margin-top: 10px;
        }

        .history-item {
            border-left: 2px solid rgba(0, 224, 122, 0.5);
            padding-left: 20px;
            margin-bottom: 24px;
            position: relative;
        }
        .history-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: #00e07a;
        }

        .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.1);
            border-radius: 50%;
            border-top-color: #00e07a;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }

    </style>


<body>
    <div class="ambient-glow glow-green"></div>
    <div class="ambient-glow glow-cyan"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Employee Directory</h1>
                    <p>Core HR System of Record & Employment Master Files</p>
                </div>
            </div>

            <div class="directory-panel">
                <div class="panel-header">
                    <div class="search-box">
                        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" id="user-search" placeholder="Search employees by name, email, or dept...">
                    </div>
                </div>

                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Department</th>
                            <th>Job Title</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="users-container">
                        <tr><td colspan="5" style="text-align:center; padding: 40px;"><div class="spinner"></div></td></tr>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Master Record Modal -->
    <div class="modal-overlay" id="masterModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title" id="modal-title-name">Master Record: ...</div>
                <button class="modal-close" onclick="closeModal()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div class="modal-sidebar">
                    <div class="modal-tab active" data-tab="profile">General Profile</div>
                    <div class="modal-tab" data-tab="employment">Job & Compensation</div>
                    <div class="modal-tab" data-tab="history">Career Timeline</div>
                    <div class="modal-tab" data-tab="documents">Documents</div>
                </div>
                <div class="modal-main">
                    <div id="loader-panel" style="text-align:center; margin-top:50px; display:none;"><div class="spinner"></div></div>
                    
                    <form id="master-form" onsubmit="saveMasterRecord(event)">
                        <!-- Profile Tab -->
                        <div class="tab-content" id="tab-profile">
                            <h3 style="color:white; margin-bottom: 20px;">General Details</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Full Name</label>
                                    <input type="text" class="form-input" id="f_full_name" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Employee Number</label>
                                    <input type="text" class="form-input" id="f_emp_number">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Work Email</label>
                                    <input type="email" class="form-input" id="f_work_email">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Employment Status</label>
                                    <select class="form-input" id="f_status">
                                        <option value="Active">Active</option>
                                        <option value="Probation">Probation</option>
                                        <option value="LOA">Leave of Absence</option>
                                        <option value="Terminated">Terminated</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Work Location</label>
                                    <input type="text" class="form-input" id="f_location">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Hire Date</label>
                                    <input type="date" class="form-input" id="f_hire_date">
                                </div>
                            </div>
                        </div>

                        <!-- Job & Compensation Tab -->
                        <div class="tab-content" id="tab-employment" style="display:none;">
                            <h3 style="color:white; margin-bottom: 10px;">Job & Compensation</h3>
                            <div style="font-size: 0.8rem; color: #f59e0b; margin-bottom: 20px;">Note: Changing Title, Dept, or Salary will automatically write to the employee's Career Timeline.</div>
                            
                            <div class="form-group">
                                <label class="form-label">Change Reason (For History Log)</label>
                                <select class="form-input" id="f_change_type" style="border-color: #00e07a;">
                                    <option value="Profile Update">Administrative Profile Update (No log)</option>
                                    <option value="Promotion">Promotion</option>
                                    <option value="Salary Review">Salary Review / Raise</option>
                                    <option value="Transfer">Department Transfer</option>
                                    <option value="Title Change">Title Change</option>
                                </select>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Job Title</label>
                                    <input type="text" class="form-input" id="f_job_title">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Department</label>
                                    <input type="text" class="form-input" id="f_department">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label">Base Salary (Annual)</label>
                                    <input type="number" step="0.01" class="form-input" id="f_base_salary">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Manager ID</label>
                                    <input type="text" class="form-input" id="f_manager_id">
                                </div>
                            </div>
                            <div class="form-group" id="f_notes_group" style="display:none;">
                                <label class="form-label">Change Notes / Justification</label>
                                <textarea class="form-input" id="f_notes" rows="3"></textarea>
                            </div>
                        </div>

                        <!-- Save button floats for the form tabs -->
                        <div id="form-actions" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 20px;">
                            <button type="submit" class="btn-save">Save Master Record</button>
                        </div>
                    </form>

                    <!-- History Tab -->
                    <div class="tab-content" id="tab-history" style="display:none;">
                        <h3 style="color:white; margin-bottom: 20px;">Career Timeline (Immutable Ledger)</h3>
                        <div id="history-container"></div>
                    </div>

                    <!-- Documents Tab -->
                    <div class="tab-content" id="tab-documents" style="display:none;">
                        <h3 style="color:white; margin-bottom: 20px;">Employee Digital Vault</h3>
                        
                        <div style="background: rgba(0,0,0,0.2); padding: 16px; border-radius: var(--radius-md); margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.05);">
                            <form id="doc-form" onsubmit="uploadDocument(event)">
                                <div class="form-row" style="align-items: flex-end;">
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">Document Type</label>
                                        <select class="form-input" id="d_type">
                                            <option value="Contract">Signed Contract</option>
                                            <option value="ID">Government ID</option>
                                            <option value="Tax Form">Tax Form</option>
                                            <option value="Certification">Certification</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="margin-bottom: 0;">
                                        <label class="form-label">File</label>
                                        <input type="file" class="form-input" id="d_file" required>
                                    </div>
                                    <button type="submit" class="btn-primary" style="padding: 10px 16px; height: 40px;">Upload</button>
                                </div>
                            </form>
                        </div>

                        <div id="docs-container"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        let allUsers = [];
        let activeUserId = null;

        async function loadDirectory() {
            try {
                // We'll borrow the IAM api for the raw user list, or core_hr if we had an endpoint. 
                // iam_api.php?action=users returns all basic info.
                const res = await fetch('<?= url('/iam_api.php?action=users') ?>');
                const data = await res.json();
                if (data.success) {
                    allUsers = data.data;
                    renderDirectory();
                }
            } catch (e) { console.error('Failed to load directory'); }
        }

        function renderDirectory(search = '') {
            const container = document.getElementById('users-container');
            const filtered = allUsers.filter(u => 
                u.full_name.toLowerCase().includes(search.toLowerCase()) || 
                u.email.toLowerCase().includes(search.toLowerCase()) ||
                (u.department && u.department.toLowerCase().includes(search.toLowerCase()))
            );

            if (filtered.length === 0) {
                container.innerHTML = `<tr><td colspan="5" style="text-align:center; color:var(--text-muted); padding: 40px;">No employees found.</td></tr>`;
                return;
            }

            container.innerHTML = filtered.map(u => {
                const initials = u.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                // Default status for display if not set properly in IAM output
                const status = u.employment_status || 'Active';
                
                return `
                    <tr>
                        <td>
                            <div class="user-info">
                                <div class="user-avatar">${initials}</div>
                                <div>
                                    <div class="user-name">${u.full_name}</div>
                                    <div class="user-email">${u.email}</div>
                                </div>
                            </div>
                        </td>
                        <td><span class="status-badge status-${status}">${status}</span></td>
                        <td>${u.department || '-'}</td>
                        <td>${u.job_title || '-'}</td>
                        <td style="text-align: right;">
                            <button class="btn-view-master" onclick="openMasterRecord(${u.id})">Open Record</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        document.getElementById('user-search').addEventListener('input', e => renderDirectory(e.target.value));

        // TABS LOGIC
        const tabs = document.querySelectorAll('.modal-tab');
        const contents = document.querySelectorAll('.tab-content');
        tabs.forEach(t => {
            t.addEventListener('click', () => {
                tabs.forEach(tab => tab.classList.remove('active'));
                contents.forEach(c => c.style.display = 'none');
                
                t.classList.add('active');
                document.getElementById('tab-' + t.dataset.tab).style.display = 'block';

                if (t.dataset.tab === 'history' || t.dataset.tab === 'documents') {
                    document.getElementById('form-actions').style.display = 'none';
                } else {
                    document.getElementById('form-actions').style.display = 'block';
                }
            });
        });

        document.getElementById('f_change_type').addEventListener('change', e => {
            document.getElementById('f_notes_group').style.display = (e.target.value !== 'Profile Update') ? 'block' : 'none';
        });

        // MODAL LOGIC
        async function openMasterRecord(userId) {
            activeUserId = userId;
            document.getElementById('masterModal').classList.add('active');
            
            // Show loader
            document.getElementById('loader-panel').style.display = 'block';
            document.getElementById('master-form').style.display = 'none';
            document.getElementById('tab-history').style.display = 'none';
            document.getElementById('tab-documents').style.display = 'none';

            // Switch to profile tab safely
            tabs.forEach(tab => tab.classList.remove('active'));
            document.querySelector('[data-tab="profile"]').classList.add('active');

            try {
                const res = await fetch(`<?= url('/core_hr_api.php?action=master_record&user_id=') ?>${userId}`);
                const data = await res.json();
                
                if (data.success) {
                    const p = data.data.profile;
                    document.getElementById('modal-title-name').textContent = `Master Record: ${p.full_name}`;
                    
                    // Fill profile
                    document.getElementById('f_full_name').value = p.full_name || '';
                    document.getElementById('f_emp_number').value = p.employee_number || '';
                    document.getElementById('f_work_email').value = p.work_email || p.email || '';
                    document.getElementById('f_status').value = p.employment_status || 'Active';
                    document.getElementById('f_location').value = p.work_location || '';
                    document.getElementById('f_hire_date').value = p.hire_date || '';

                    // Fill job
                    document.getElementById('f_job_title').value = p.job_title || '';
                    document.getElementById('f_department').value = p.department || '';
                    document.getElementById('f_base_salary').value = p.base_salary || '';
                    document.getElementById('f_manager_id').value = p.manager_id || '';
                    
                    document.getElementById('f_change_type').value = 'Profile Update';
                    document.getElementById('f_notes').value = '';
                    document.getElementById('f_notes_group').style.display = 'none';

                    // Fill History
                    const hc = document.getElementById('history-container');
                    if (data.data.history.length === 0) {
                        hc.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding: 20px;">No history records found.</div>`;
                    } else {
                        hc.innerHTML = data.data.history.map(h => `
                            <div class="history-item">
                                <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">${h.effective_date} (Recorded by: ${h.recorded_by})</div>
                                <div style="font-weight: 600; color: white; margin-bottom: 4px;">${h.change_type}</div>
                                <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                    Title: ${h.job_title || '-'} | Dept: ${h.department || '-'}
                                    ${h.base_salary ? `| Salary: $${parseFloat(h.base_salary).toLocaleString()}` : ''}
                                </div>
                                ${h.notes ? `<div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px; font-style: italic;">"${h.notes}"</div>` : ''}
                            </div>
                        `).join('');
                    }

                    // Fill Docs
                    const dc = document.getElementById('docs-container');
                    if (data.data.documents.length === 0) {
                        dc.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding: 20px;">No documents in vault.</div>`;
                    } else {
                        dc.innerHTML = data.data.documents.map(d => `
                            <div style="display:flex; justify-content:space-between; padding: 12px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); margin-bottom: 8px; border-radius: 4px;">
                                <div>
                                    <div style="color:white; font-size:0.875rem; font-weight: 600;">${d.document_type}: ${d.file_name}</div>
                                    <div style="color:var(--text-muted); font-size:0.75rem;">Uploaded by ${d.uploaded_by} on ${d.uploaded_at}</div>
                                </div>
                                <a href="<?= url('/') ?>${d.file_path}" target="_blank" style="color: #c084fc; font-size:0.875rem; text-decoration:none;">View</a>
                            </div>
                        `).join('');
                    }

                }
            } catch (e) {
                console.error(e);
            }

            document.getElementById('loader-panel').style.display = 'none';
            document.getElementById('master-form').style.display = 'block';
            document.getElementById('form-actions').style.display = 'block';
            document.getElementById('tab-profile').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('masterModal').classList.remove('active');
            activeUserId = null;
        }

        async function saveMasterRecord(e) {
            e.preventDefault();
            if (!activeUserId) return;

            const payload = {
                user_id: activeUserId,
                full_name: document.getElementById('f_full_name').value,
                employee_number: document.getElementById('f_emp_number').value,
                work_email: document.getElementById('f_work_email').value,
                employment_status: document.getElementById('f_status').value,
                work_location: document.getElementById('f_location').value,
                hire_date: document.getElementById('f_hire_date').value,
                
                change_type: document.getElementById('f_change_type').value,
                job_title: document.getElementById('f_job_title').value,
                department: document.getElementById('f_department').value,
                base_salary: document.getElementById('f_base_salary').value,
                manager_id: document.getElementById('f_manager_id').value,
                notes: document.getElementById('f_notes').value
            };

            try {
                const res = await fetch(`<?= url('/core_hr_api.php?action=update_master_record') ?>`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json'},
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    alert('Master Record saved successfully.');
                    // Reload directory and current modal to show history
                    loadDirectory();
                    openMasterRecord(activeUserId);
                } else {
                    alert('Error: ' + data.error);
                }
            } catch(e) { alert('Network Error'); }
        }

        async function uploadDocument(e) {
            e.preventDefault();
            if (!activeUserId) return;

            const fd = new FormData();
            fd.append('user_id', activeUserId);
            fd.append('document_type', document.getElementById('d_type').value);
            fd.append('document', document.getElementById('d_file').files[0]);

            try {
                const res = await fetch(`<?= url('/core_hr_api.php?action=upload_document') ?>`, {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if(data.success) {
                    alert('Document uploaded successfully.');
                    document.getElementById('d_file').value = '';
                    openMasterRecord(activeUserId); // reload history/docs
                } else {
                    alert('Error: ' + data.error);
                }
            } catch(e) { alert('Network Error'); }
        }

        // Init
        loadDirectory();
    </script>
</body>
</html>
