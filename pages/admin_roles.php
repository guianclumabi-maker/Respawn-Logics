<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

if (!hasPermission('users.manage')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$user = getCurrentUser();
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Roles & Permissions - Respawn Logic'; ?>
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
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .admin-title p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .layout-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: start;
        }

        .panel {
            background: rgba(22, 25, 34, 0.7);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 24px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .role-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .role-item {
            padding: 14px 16px;
            border-radius: var(--radius-md);
            border: 1px solid rgba(255, 255, 255, 0.05);
            background: rgba(26, 29, 39, 0.5);
            cursor: pointer;
            transition: all 0.2s ease;
            position: relative;
            overflow: hidden;
        }

        .role-item:hover {
            border-color: rgba(0, 224, 122, 0.5);
            background: rgba(0, 224, 122, 0.05);
        }

        .role-item.active {
            border-color: #00e07a;
            background: rgba(0, 224, 122, 0.1);
        }

        .role-item.active::before {
            content: '';
            position: absolute;
            left: 0; top: 0; bottom: 0;
            width: 3px;
            background: #00e07a;
        }

        .role-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 4px;
        }

        .role-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .sys-badge {
            font-size: 0.65rem;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(255,255,255,0.1);
            color: var(--text-secondary);
            margin-left: 8px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .matrix-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
            text-align: center;
            border: 1px dashed rgba(255,255,255,0.1);
            border-radius: var(--radius-lg);
            background: rgba(0,0,0,0.2);
            height: 100%;
        }

        .matrix-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            background: rgba(0, 224, 122, 0.1);
            color: #c084fc;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }

        .matrix-content {
            display: none;
            flex-direction: column;
            height: 100%;
        }
        
        .perm-group {
            margin-bottom: 24px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .perm-group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }

        .perm-group-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #f0f4ff;
        }

        .perm-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 12px;
            padding: 16px;
        }

        .perm-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .perm-item input[type="checkbox"] {
            margin-top: 3px;
            accent-color: #00e07a;
        }

        .perm-item-label {
            font-size: 0.875rem;
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 2px;
        }

        .perm-item-desc {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        #permissions-container {
            max-height: calc(100vh - 280px);
        }
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
                    <h1>Roles & Permissions</h1>
                    <p>Configure what each role is allowed to see and do across the platform</p>
                </div>
            </div>

            <div class="layout-grid">
                <!-- Roles Panel -->
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">
                            Global Roles
                        </div>
                        <div style="display: flex; gap: 8px;">
                            <button class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem;" onclick="openTemplateModal()">+ From Template</button>
                            <button class="btn-primary" style="padding: 6px 12px; font-size: 0.75rem;">+ New Role</button>
                        </div>
                    </div>
                    
                    <div class="role-list" id="roles-container">
                        <!-- Loaded via JS -->
                    </div>
                </div>

                <!-- Permission Matrix Panel -->
                <div class="panel" style="display:flex; flex-direction: column;">
                    <div class="panel-header">
                        <div class="panel-title" id="matrix-header-title">
                            Permission Matrix
                        </div>
                    </div>

                    <div style="flex:1;">
                        <div class="matrix-placeholder" id="matrix-placeholder">
                            <div class="matrix-icon">
                                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" width="32" height="32">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                            </div>
                            <div class="matrix-title">Select a Role</div>
                            <div class="matrix-desc">
                                Choose a role from the sidebar to view and modify its permissions.
                            </div>
                        </div>

                        <div class="matrix-content" id="matrix-content">
                            <div id="permissions-container" style="flex:1; overflow-y:auto; padding-right:8px;">
                                <!-- Groups loaded via JS -->
                            </div>
                            <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: flex-end; gap: 12px;">
                                <button class="btn-primary" id="save-perms-btn" onclick="savePermissions()" style="padding: 8px 16px;">Save Permissions</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    </div>

    <!-- Template Modal -->
    <div id="templateModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; backdrop-filter:blur(4px); align-items:center; justify-content:center;">
        <div style="background:#1e2430; border:1px solid rgba(255,255,255,0.1); border-radius:12px; width:500px; max-width:90%; padding:24px; box-shadow:0 10px 25px rgba(0,0,0,0.5);">
            <h3 style="margin-top:0; color:#f0f4ff; font-size:1.25rem;">Create Role from Template</h3>
            <p style="color:#8899b4; font-size:0.875rem; margin-bottom:16px;">Select a preset to instantly deploy a fully-configured role for your organization.</p>
            
            <div style="margin-bottom: 16px;">
                <label style="display:block; color:#8899b4; font-size:0.75rem; font-weight:600; margin-bottom:4px; text-transform:uppercase;">New Role Name</label>
                <input type="text" id="templateRoleName" placeholder="e.g. Sales Manager" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#f0f4ff; border-radius:8px; padding:10px 12px; font-family:inherit; outline:none;">
            </div>

            <div id="templatesList" style="display:flex; flex-direction:column; gap:8px; max-height:250px; overflow-y:auto; margin-bottom:16px;">
                <!-- Loaded via JS -->
            </div>
            
            <div style="display:flex; justify-content:flex-end; gap:12px;">
                <button onclick="closeTemplateModal()" style="background:transparent; border:none; color:#8899b4; cursor:pointer; font-size:0.875rem; padding:8px 16px; border-radius:6px;">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let roles = [];
        let templates = [];

        async function openTemplateModal() {
            document.getElementById('templateModal').style.display = 'flex';
            if (templates.length === 0) {
                await loadTemplates();
            }
        }

        function closeTemplateModal() {
            document.getElementById('templateModal').style.display = 'none';
        }

        async function loadTemplates() {
            try {
                const res = await fetch('<?= url('/api/index.php?route=iam&action=templates') ?>');
                const data = await res.json();
                if (data.success) {
                    templates = data.data;
                    renderTemplates();
                }
            } catch (err) { console.error(err); }
        }

        function renderTemplates() {
            const container = document.getElementById('templatesList');
            container.innerHTML = '';
            templates.forEach(t => {
                const item = document.createElement('div');
                item.className = 'role-item';
                item.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <div>
                            <div class="role-name">${t.name}</div>
                            <div class="role-desc">${t.description}</div>
                        </div>
                        <button class="btn-primary" style="padding: 4px 12px; font-size: 0.75rem;" onclick="createFromTemplate(${t.id})">Use</button>
                    </div>
                `;
                container.appendChild(item);
            });
        }

        async function createFromTemplate(templateId) {
            const roleName = document.getElementById('templateRoleName').value.trim();
            if (!roleName) {
                alert('Please enter a name for the new role.');
                return;
            }

            try {
                const res = await fetch('<?= url('/api/index.php?route=iam&action=create_from_template') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ template_id: templateId, role_name: roleName })
                });
                const data = await res.json();
                if (data.success) {
                    closeTemplateModal();
                    document.getElementById('templateRoleName').value = '';
                    alert('Role created successfully!');
                    loadRoles(); // Refresh the list
                } else {
                    alert('Failed to create role: ' + data.error);
                }
            } catch (err) {
                console.error(err);
                alert('A network error occurred.');
            }
        }

        let permissions = [];
        let groupedPermissions = {};
        let currentRoleId = null;
        let isSystemRole = false;

        const permissionGroups = {
            'People Management': ['employees', 'users', 'onboarding'],
            'Time & Attendance': ['attendance', 'leave', 'shifts'],
            'Compensation': ['payroll', 'expenses', 'benefits', 'compensation'],
            'Talent': ['performance', 'surveys', 'ats'],
            'Compliance': ['elr', 'audit'],
            'Platform': ['announcements', 'intelligence', 'knowledge', 'settings', 'assets', 'esm', 'platform']
        };

        function getGroupForKey(key) {
            const prefix = key.split('.')[0];
            for (const [groupName, prefixes] of Object.entries(permissionGroups)) {
                if (prefixes.includes(prefix)) return groupName;
            }
            return 'Other';
        }

        async function loadPermissions() {
            try {
                const res = await fetch('<?= url('/api/index.php?route=iam&action=permissions') ?>');
                const data = await res.json();
                if (data.success) {
                    permissions = data.data;
                    groupedPermissions = {};
                    permissions.forEach(p => {
                        const group = getGroupForKey(p.permission_key);
                        if (!groupedPermissions[group]) groupedPermissions[group] = [];
                        groupedPermissions[group].push(p);
                    });
                    renderPermissions();
                }
            } catch (err) { console.error(err); }
        }

        function renderPermissions() {
            const container = document.getElementById('permissions-container');
            let html = '';
            for (const [group, perms] of Object.entries(groupedPermissions)) {
                html += `
                <div class="perm-group">
                    <div class="perm-group-header">
                        <div class="perm-group-title">${group}</div>
                        <div>
                            <button class="btn-primary" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(255,255,255,0.1); border:none;" onclick="toggleGroup('${group}', true)">All</button>
                            <button class="btn-primary" style="padding: 4px 8px; font-size: 0.75rem; background: rgba(255,255,255,0.1); border:none;" onclick="toggleGroup('${group}', false)">None</button>
                        </div>
                    </div>
                    <div class="perm-list">
                        ${perms.map(p => `
                        <div class="perm-item">
                            <input type="checkbox" id="perm_${p.id}" value="${p.id}" class="perm-chk group-${group.replace(/\s+/g, '-')}">
                            <div>
                                <div class="perm-item-label">${p.permission_key}</div>
                                <div class="perm-item-desc">${p.description}</div>
                            </div>
                        </div>
                        `).join('')}
                    </div>
                </div>`;
            }
            container.innerHTML = html;
        }

        window.toggleGroup = function(group, state) {
            if (isSystemRole) return;
            const checks = document.querySelectorAll(`.group-${group.replace(/\s+/g, '-')}`);
            checks.forEach(c => c.checked = state);
        };

        async function loadRoles() {
            try {
                const res = await fetch('<?= url('/api/index.php?route=iam&action=roles') ?>');
                const data = await res.json();
                if (data.success) {
                    roles = data.data;
                    renderRoles();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderRoles() {
            const container = document.getElementById('roles-container');
            container.innerHTML = roles.map((r, i) => `
                <div class="role-item ${i === 0 ? 'active' : ''}" onclick="selectRole(this, ${r.id}, '${r.name}', ${r.is_system_role})">
                    <div class="role-name">
                        ${r.name}
                        ${r.is_system_role == 1 ? '<span class="sys-badge">System</span>' : ''}
                    </div>
                    <div class="role-desc">${r.description || 'No description provided.'}</div>
                </div>
            `).join('');
            
            if(roles.length > 0) {
                selectRole(container.children[0], roles[0].id, roles[0].name, roles[0].is_system_role);
            }
        }

        window.selectRole = async function(el, roleId, roleName, sysRole) {
            document.querySelectorAll('.role-item').forEach(i => i.classList.remove('active'));
            el.classList.add('active');
            document.getElementById('matrix-header-title').textContent = `${roleName} Permissions`;
            
            currentRoleId = roleId;
            isSystemRole = sysRole == 1;

            document.getElementById('matrix-placeholder').style.display = 'none';
            document.getElementById('matrix-content').style.display = 'flex';

            const btn = document.getElementById('save-perms-btn');
            if (isSystemRole) {
                btn.style.opacity = '0.5';
                btn.style.cursor = 'not-allowed';
                btn.textContent = 'System Role (Locked)';
            } else {
                btn.style.opacity = '1';
                btn.style.cursor = 'pointer';
                btn.textContent = 'Save Permissions';
            }

            // Reset all checkboxes
            document.querySelectorAll('.perm-chk').forEach(c => {
                c.checked = false;
                c.disabled = isSystemRole;
            });

            // Fetch role permissions
            try {
                const res = await fetch(`<?= url('/api/index.php?route=iam&action=role_permissions&role_id=') ?>${roleId}`);
                const data = await res.json();
                if (data.success) {
                    data.data.forEach(pid => {
                        const chk = document.getElementById(`perm_${pid}`);
                        if (chk) chk.checked = true;
                    });
                }
            } catch (err) { console.error(err); }
        };

        window.savePermissions = async function() {
            if (!currentRoleId || isSystemRole) return;
            
            const checked = [];
            document.querySelectorAll('.perm-chk:checked').forEach(c => {
                checked.push(parseInt(c.value));
            });

            try {
                const res = await fetch('<?= url('/api/index.php?route=iam&action=save_role_permissions') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ role_id: currentRoleId, permissions: checked })
                });
                const data = await res.json();
                if (data.success) {
                    alert('Permissions saved successfully!');
                } else {
                    alert('Error saving permissions: ' + data.error);
                }
            } catch (err) {
                alert('Network error while saving permissions.');
            }
        };

        // Initialize
        loadPermissions().then(() => loadRoles());
    </script>
</body>
</html>
