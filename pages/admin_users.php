<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('users.view')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$user = getCurrentUser();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'User & Role Management - Respawn Logic'; ?>
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

        .layout-grid {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 24px;
            align-items: start;
        }

        .roles-panel, .users-panel {
            background: rgba(22, 25, 34, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
        }

        .roles-panel {
            position: sticky;
            top: 20px;
            height: calc(100vh - 140px);
            overflow-y: auto;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .panel-title {
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-card {
            padding: 12px 16px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(26, 29, 39, 0.5);
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .role-card:hover {
            border-color: rgba(0, 224, 122, 0.5);
            background: rgba(0, 224, 122, 0.05);
        }

        .role-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
            margin-bottom: 4px;
        }

        .role-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
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

        .search-box input:focus {
            border-color: rgba(0, 224, 122, 0.5);
            box-shadow: 0 0 0 2px rgba(0, 224, 122, 0.1);
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

        .role-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 100px;
            font-size: 0.75rem;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-secondary);
        }

        .role-badge.admin {
            background: rgba(155, 109, 255, 0.1);
            border-color: rgba(155, 109, 255, 0.2);
            color: #f472b6;
        }

        .role-badge.hr {
            background: rgba(0, 224, 122, 0.1);
            border-color: rgba(0, 224, 122, 0.2);
            color: #c084fc;
        }

        .btn-edit-roles {
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

        .btn-edit-roles:hover {
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
            width: 480px;
            max-width: 90vw;
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
            padding: 24px;
            max-height: 60vh;
            overflow-y: auto;
        }

        .role-toggle-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: var(--radius-md);
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.02);
        }

        .role-toggle-info {
            flex: 1;
        }

        .role-toggle-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: white;
        }

        .role-toggle-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: .3s;
            border-radius: 20px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: var(--text-muted);
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--accent-green);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
            background-color: white;
        }

        input:disabled + .slider {
            opacity: 0.5;
            cursor: not-allowed;
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

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>


<body>
    <div class="global-glow-green"></div>
    <div class="global-glow-purple"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Users & Permissions</h1>
                    <p>Manage system access and assign global roles to users</p>
                </div>
                <button class="btn-primary" style="padding: 10px 16px;" onclick="openInviteModal()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16" style="margin-right: 6px; display: inline-block; vertical-align: middle;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    Invite User
                </button>
            </div>

            <div class="layout-grid">
                <!-- Roles Panel -->
                <div class="roles-panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                            Global Roles
                        </div>
                    </div>
                    
                    <div id="roles-container">
                        <div style="text-align:center; padding: 20px;"><div class="spinner"></div></div>
                    </div>
                </div>

                <!-- Users Panel -->
                <div class="users-panel">
                    <div class="panel-header">
                        <div class="panel-title">System Users</div>
                        <div class="search-box">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="16" height="16">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" id="user-search" placeholder="Search by name or email...">
                        </div>
                    </div>

                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Assigned Roles</th>
                                <th style="text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody id="users-container">
                            <tr>
                                <td colspan="3" style="text-align:center; padding: 40px;">
                                    <div class="spinner"></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit User Roles</div>
                <button class="modal-close" onclick="closeModal()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                    <div class="user-info">
                        <div class="user-avatar" id="modal-avatar"></div>
                        <div>
                            <div class="user-name" id="modal-name"></div>
                            <div class="user-email" id="modal-email"></div>
                        </div>
                    </div>
                </div>

                <div id="modal-roles-container">
                    <div style="text-align:center;"><div class="spinner"></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Invite User Modal -->
    <div class="modal-overlay" id="inviteModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Invite New User</div>
                <button class="modal-close" onclick="closeInviteModal()">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="20" height="20">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <form onsubmit="submitInvite(event)">
                <div class="modal-body">
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">Full Name</label>
                        <input type="text" id="invite-name" required style="width:100%; background:rgba(26,29,39,0.8); border:1px solid var(--border-color); color:white; padding:10px; border-radius:var(--radius-sm); outline:none;">
                    </div>
                    <div style="margin-bottom: 16px;">
                        <label style="display:block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px;">Email Address</label>
                        <input type="email" id="invite-email" required style="width:100%; background:rgba(26,29,39,0.8); border:1px solid var(--border-color); color:white; padding:10px; border-radius:var(--radius-sm); outline:none;">
                    </div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 10px;">
                        <p>The user will be created with a default password of <strong>password123</strong>.</p>
                        <p>After creating the user, they will appear in the table where you can assign them specific roles.</p>
                    </div>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" onclick="closeInviteModal()" style="background:transparent; border:1px solid var(--border-color); color:white; padding:8px 16px; border-radius:var(--radius-sm); cursor:pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding:8px 16px;" id="btn-submit-invite">Invite User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let globalRoles = [];
        let systemUsers = [];

        async function loadData() {
            try {
                const [rolesRes, usersRes] = await Promise.all([
                    fetch('<?= url('/api/index.php?route=iam&action=roles') ?>').then(r => r.json()),
                    fetch('<?= url('/api/index.php?route=iam&action=users') ?>').then(r => r.json())
                ]);

                if (rolesRes.success) globalRoles = rolesRes.data;
                if (usersRes.success) systemUsers = usersRes.data;

                renderRoles();
                renderUsers();
            } catch (err) {
                console.error("Failed to load IAM data", err);
            }
        }

        function renderRoles() {
            const container = document.getElementById('roles-container');
            container.innerHTML = globalRoles.map(r => `
                <div class="role-card">
                    <div class="role-name">${r.name}</div>
                    <div class="role-desc">${r.description || 'No description available.'}</div>
                </div>
            `).join('');
        }

        function renderUsers(search = '') {
            const container = document.getElementById('users-container');
            const filtered = systemUsers.filter(u => 
                u.full_name.toLowerCase().includes(search.toLowerCase()) || 
                u.email.toLowerCase().includes(search.toLowerCase())
            );

            if (filtered.length === 0) {
                container.innerHTML = `<tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding: 40px;">No users found.</td></tr>`;
                return;
            }

            container.innerHTML = filtered.map(u => {
                const initials = u.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                
                const roleBadges = u.roles.map(r => {
                    let c = 'role-badge';
                    if (r.name === 'Admin') c += ' admin';
                    if (r.name.includes('HR') || r.name.includes('Recruiter')) c += ' hr';
                    return `<span class="${c}">${r.name}</span>`;
                }).join('');

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
                        <td>
                            <div class="role-badges">
                                ${roleBadges || '<span style="color:var(--text-muted); font-size:0.75rem;">No roles</span>'}
                            </div>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn-edit-roles" onclick="openModal(${u.id})">Edit Roles</button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        document.getElementById('user-search').addEventListener('input', (e) => {
            renderUsers(e.target.value);
        });

        // Modal Logic
        let activeUserId = null;

        function openModal(userId) {
            activeUserId = userId;
            const user = systemUsers.find(u => u.id === userId);
            
            document.getElementById('modal-name').textContent = user.full_name;
            document.getElementById('modal-email').textContent = user.email;
            document.getElementById('modal-avatar').textContent = user.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();

            const userRoleIds = user.roles.map(r => r.id);

            const container = document.getElementById('modal-roles-container');
            container.innerHTML = globalRoles.map(r => {
                const isChecked = userRoleIds.includes(r.id);
                // System roles like Admin might be restricted from removing if it's the last admin, but we'll allow it for now.
                return `
                    <div class="role-toggle-item">
                        <div class="role-toggle-info">
                            <div class="role-toggle-name">${r.name}</div>
                            <div class="role-toggle-desc">${r.description || 'System role'}</div>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" ${isChecked ? 'checked' : ''} onchange="toggleRole(${r.id}, this.checked)">
                            <span class="slider"></span>
                        </label>
                    </div>
                `;
            }).join('');

            document.getElementById('editModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('editModal').classList.remove('active');
            activeUserId = null;
        }

        async function toggleRole(roleId, isAssigning) {
            if (!activeUserId) return;
            
            const action = isAssigning ? 'assign_role' : 'remove_role';
            
            try {
                const res = await fetch(`<?= url('/api/index.php?route=iam&action=${action}') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: activeUserId, role_id: roleId })
                });
                
                const data = await res.json();
                if (!data.success) {
                    alert('Error: ' + data.error);
                    // Revert UI on error could be implemented here
                } else {
                    // Update local state silently so when modal closes the main table updates
                    const user = systemUsers.find(u => u.id === activeUserId);
                    if (isAssigning) {
                        const role = globalRoles.find(r => r.id === roleId);
                        user.roles.push({ id: role.id, name: role.name });
                    } else {
                        user.roles = user.roles.filter(r => r.id !== roleId);
                    }
                    renderUsers(document.getElementById('user-search').value);
                }
            } catch (e) {
                alert('Network error');
            }
        }

        function openInviteModal() {
            document.getElementById('inviteModal').classList.add('active');
            document.getElementById('invite-name').value = '';
            document.getElementById('invite-email').value = '';
        }

        function closeInviteModal() {
            document.getElementById('inviteModal').classList.remove('active');
        }

        async function submitInvite(e) {
            e.preventDefault();
            const btn = document.getElementById('btn-submit-invite');
            const name = document.getElementById('invite-name').value;
            const email = document.getElementById('invite-email').value;

            btn.textContent = 'Inviting...';
            btn.disabled = true;

            try {
                const res = await fetch(`<?= url('/api/index.php?route=iam&action=invite_user') ?>`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ full_name: name, email: email })
                });
                const data = await res.json();

                if (data.success) {
                    closeInviteModal();
                    // Refresh data
                    loadData();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (err) {
                alert('Network error while inviting user.');
            }

            btn.textContent = 'Invite User';
            btn.disabled = false;
        }

        // Init
        loadData();
    </script>
</body>
</html>
