<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict Platform Admin check (Only the master owner can add other internal staff)
$user = getCurrentUser();
if (!hasRole(['Platform_Admin', 'Super_Admin'])) {
    header("Location: dashboard.php");
    exit;
}

$current_page = 'saas_staff.php';
?>
<?php $page_title = 'Vendor Staff - SaaS Control Center - Respawn Logic'; ?>
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

        .staff-header {
            background: #0f1422;
            border-radius: 8px;
            padding: 40px 30px;
            margin-bottom: 30px;
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.05);
            border: 1px solid rgba(0, 224, 122, 0.2);
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .btn-add {
            background: rgba(0, 224, 122, 0.1);
            color: #00e07a;
            border: 1px solid rgba(0, 224, 122, 0.2);
            padding: 10px 20px;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
        }
        .btn-add:hover {
            background: #00e07a;
            color: #111827;
            box-shadow: 0 4px 12px rgba(0, 224, 122, 0.2);
        }
        
        .staff-table {
            width: 100%;
            border-collapse: collapse;
            background: #0f1422;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .staff-table th {
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
        .staff-table td {
            padding: 15px 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: #c8d0e0;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
        }
        
        .role-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .role-Platform_Admin { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .role-Support_Agent { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .role-Implementation_Specialist { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(11, 15, 26, 0.8);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.2s;
        }
        .modal-overlay.active {
            opacity: 1;
            pointer-events: all;
        }
        .modal-content {
            background: #0f1422;
            padding: 30px;
            border-radius: 8px;
            width: 450px;
            border: 1px solid rgba(0, 224, 122, 0.2);
            box-shadow: 0 0 40px rgba(0, 224, 122, 0.05);
            transform: translateY(20px);
            transition: transform 0.2s;
        }
        .modal-content h3 {
            color: #fff;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 24px;
        }
        .modal-overlay.active .modal-content {
            transform: translateY(0);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 600;
            color: #5e6a82;
            margin-bottom: 8px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: #0b0f1a;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #00e07a;
        }
        .btn-submit {
            width: 100%;
            background: #00e07a;
            color: #111827;
            border: none;
            padding: 12px;
            border-radius: 4px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }
        .btn-submit:hover {
            background: #00c96a;
        }
        .btn-cancel {
            width: 100%;
            background: transparent;
            color: #8b95a8;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 12px;
            border-radius: 4px;
            font-weight: 600;
            font-family: 'JetBrains Mono', monospace;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            color: #fff;
            border-color: rgba(255,255,255,0.2);
        }

        .spinner {
            display: inline-block;
            width: 24px; height: 24px;
            border: 3px solid rgba(0, 224, 122,0.3);
            border-radius: 50%;
            border-top-color: #00e07a;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Internal Staff Directory</h1>
                </div>
            </header>
            
            <div class="content-wrapper">
                
                <div class="staff-header">
                    <div>
                        <div class="hq-badge">// ACCESS_CONTROL</div>
                        <div class="hq-title">Vendor Staff Directory</div>
                        <div class="hq-subtitle">Manage internal SaaS operators and support agents</div>
                    </div>
                    <button class="btn-add" onclick="openModal()">
                        <i class="fa-solid fa-user-plus"></i> Provision Staff
                    </button>
                </div>

                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Vendor Role</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="staff-tbody">
                        <tr>
                            <td colspan="5" style="text-align: center;"><div class="spinner"></div></td>
                        </tr>
                    </tbody>
                </table>
                
            </div>
        </div>
    </div>
    
    <!-- Provision Modal -->
    <div class="modal-overlay" id="provisionModal">
        <div class="modal-content">
            <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                <h2 style="font-family: 'Space Grotesk'; margin: 0;">Provision Internal Staff</h2>
                <button onclick="closeModal()" style="background: none; border: none; cursor: pointer; color: #6b7280;"><i class="fa-solid fa-xmark fa-xl"></i></button>
            </div>
            
            <form id="provisionForm">
                <div style="display: flex; gap: 10px;">
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">First Name</label>
                        <input type="text" id="p_first" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Last Name</label>
                        <input type="text" id="p_last" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Work Email</label>
                    <input type="email" id="p_email" class="form-control" placeholder="name@respawnlogics.com" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Temporary Password</label>
                    <input type="password" id="p_password" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Vendor Role</label>
                    <select id="p_role" class="form-control">
                        <option value="Support_Agent">Support Agent</option>
                        <option value="Implementation_Specialist">Implementation Specialist</option>
                        <option value="Platform_Admin">Platform Admin</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">Create Account</button>
            </form>
        </div>
    </div>
    
    <script>
        const tbody = document.getElementById('staff-tbody');
        const modal = document.getElementById('provisionModal');
        const form = document.getElementById('provisionForm');
        
        async function loadStaff() {
            try {
                const res = await fetch("<?= url('/api/index.php?route=saas_staff&action=list') ?>");
                const data = await res.json();
                
                if (data.success) {
                    const currentUserId = <?= json_encode($user['id'] ?? null) ?>;
                    tbody.innerHTML = data.data.map(s => `
                        <tr>
                            <td style="font-weight: 600; color: #fff;">${s.full_name}</td>
                            <td>${s.email}</td>
                            <td><span class="role-badge role-${s.role}">${s.role.replace('_', ' ')}</span></td>
                            <td><span style="color: #00e07a; font-weight: 600;">${s.employment_status}</span></td>
                            <td>
                                ${(s.id != currentUserId && s.id != 901) ? `
                                <button onclick="deleteStaff(${s.id})" style="background: none; border: none; color: #ef4444; cursor: pointer;" title="Revoke Access">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                ` : '<span style="color: #9ca3af; font-size: 0.8125rem;">Protected</span>'}
                            </td>
                        </tr>
                    `).join('');
                }
            } catch (e) {
                tbody.innerHTML = `<tr><td colspan="5" style="color: red; text-align: center;">Failed to load data.</td></tr>`;
            }
        }
        
        function openModal() {
            modal.classList.add('active');
        }
        
        function closeModal() {
            modal.classList.remove('active');
            form.reset();
        }
        
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const payload = {
                first_name: document.getElementById('p_first').value,
                last_name: document.getElementById('p_last').value,
                email: document.getElementById('p_email').value,
                password: document.getElementById('p_password').value,
                role: document.getElementById('p_role').value
            };
            
            try {
                const res = await fetch("<?= url('/api/index.php?route=saas_staff&action=create') ?>", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    closeModal();
                    loadStaff();
                } else {
                    alert(data.error);
                }
            } catch (err) {
                alert("Network error.");
            }
        });
        
        async function deleteStaff(id) {
            if (!confirm("Are you sure you want to revoke this staff member's access?")) return;
            try {
                const res = await fetch("<?= url('/api/index.php?route=saas_staff&action=delete') ?>", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ user_id: id })
                });
                const data = await res.json();
                if (data.success) {
                    loadStaff();
                } else {
                    alert(data.error);
                }
            } catch (err) {
                alert("Network error.");
            }
        }
        
        // Init
        loadStaff();
    </script>
</body>
</html>
