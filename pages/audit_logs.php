<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
if (!hasPermission('audit.view')) {
    header('Location: dashboard.php');
    exit;
}

$current_page = 'audit_logs.php';
?>
<?php $page_title = 'System Audit Trail - Respawn Logics'; ?>
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

        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 1px solid var(--border-color); }
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.75rem; color: var(--text-primary); margin: 0 0 4px 0; }
        .title-block p { color: var(--text-muted); margin: 0; font-size: 0.95rem; }

        /* Filter Bar */
        .filter-bar {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 16px;
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            align-items: center;
        }
        .filter-group { flex: 1; display: flex; align-items: center; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 6px; padding: 0 12px; }
        .filter-group i { color: var(--text-muted); }
        .filter-input, .filter-select {
            width: 100%; border: none; background: transparent; padding: 10px; color: var(--text-primary);
            font-size: 0.9rem; font-family: 'Space Grotesk';
        }
        .filter-input:focus, .filter-select:focus { outline: none; }
        .filter-select { appearance: none; cursor: pointer; }

        /* Data Table */
        .audit-table-container {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            overflow-x: auto;
        }
        .audit-table { width: 100%; border-collapse: collapse; min-width: 900px; }
        .audit-table th {
            padding: 16px; text-align: left; background: rgba(0,0,0,0.2);
            color: var(--text-muted); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;
            border-bottom: 1px solid var(--border-color);
        }
        .audit-table td {
            padding: 16px; border-bottom: 1px solid var(--border-color);
            vertical-align: middle; color: var(--text-primary); font-size: 0.9rem;
        }
        .audit-table tr:hover td { background: rgba(255,255,255,0.02); }

        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar { width: 36px; height: 36px; border-radius: 50%; background: var(--bg-primary); display:flex; align-items:center; justify-content:center; font-weight:bold; object-fit:cover; }
        .user-info { display: flex; flex-direction: column; }
        .user-name { font-weight: 600; color: var(--text-primary); }
        .user-email { font-size: 0.75rem; color: var(--text-muted); }

        .action-badge {
            display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 600;
            background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.2);
        }

        .details-cell {
            max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: var(--text-muted);
            font-family: monospace; font-size: 0.8rem;
        }

        /* Pagination */
        .pagination-container {
            display: flex; justify-content: space-between; align-items: center; padding: 16px;
            background: var(--bg-secondary); border-top: 1px solid var(--border-color);
        }
        .pagination-info { color: var(--text-muted); font-size: 0.9rem; }
        .pagination-controls { display: flex; gap: 8px; }
        .page-btn {
            background: var(--bg-primary); border: 1px solid var(--border-color); color: var(--text-primary);
            padding: 6px 12px; border-radius: 4px; cursor: pointer; transition: all 0.2s;
        }
        .page-btn:hover:not(:disabled) { background: var(--accent-blue); border-color: var(--accent-blue); color: white; }
        .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="title-block">
                        <h1>System Audit Trail</h1>
                        <p>Immutable ledger of all critical system events and user actions.</p>
                    </div>
                    <button class="btn btn-secondary" onclick="fetchLogs()"><i class="fa-solid fa-rotate-right"></i> Refresh Logs</button>
                </div>

                <div class="filter-bar">
                    <div class="filter-group">
                        <i class="fa-solid fa-search"></i>
                        <input type="text" id="searchInput" class="filter-input" placeholder="Search by name, email, or payload details..." oninput="debounceSearch()">
                    </div>
                    <div class="filter-group" style="flex: 0.5;">
                        <i class="fa-solid fa-filter"></i>
                        <select id="actionFilter" class="filter-select" onchange="resetPageAndFetch()">
                            <option value="">All Actions</option>
                            <!-- Injected dynamically -->
                        </select>
                    </div>
                </div>

                <div class="audit-table-container">
                    <table class="audit-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User / Actor</th>
                                <th>Action Type</th>
                                <th>Payload / Details</th>
                            </tr>
                        </thead>
                        <tbody id="logsBody">
                            <tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text-muted);">Loading logs...</td></tr>
                        </tbody>
                    </table>
                    <div class="pagination-container">
                        <div class="pagination-info" id="pageInfo">Showing 0 logs</div>
                        <div class="pagination-controls">
                            <button class="page-btn" id="prevBtn" onclick="changePage(-1)">Previous</button>
                            <button class="page-btn" id="nextBtn" onclick="changePage(1)">Next</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const limit = 50;
        let totalPages = 1;
        let searchTimer;

        function debounceSearch() {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => {
                resetPageAndFetch();
            }, 500);
        }

        function resetPageAndFetch() {
            currentPage = 1;
            fetchLogs();
        }

        function changePage(delta) {
            currentPage += delta;
            if (currentPage < 1) currentPage = 1;
            if (currentPage > totalPages) currentPage = totalPages;
            fetchLogs();
        }

        async function loadFilterOptions() {
            try {
                const res = await fetch(`<?= url('/api/index.php?route=audit&action=fetch_actions') ?>`);
                const data = await res.json();
                if (data.success) {
                    const select = document.getElementById('actionFilter');
                    let html = '<option value="">All Actions</option>';
                    data.data.forEach(action => {
                        html += `<option value="${action}">${action}</option>`;
                    });
                    select.innerHTML = html;
                }
            } catch (e) { console.error(e); }
        }

        async function fetchLogs() {
            const search = document.getElementById('searchInput').value;
            const action = document.getElementById('actionFilter').value;
            
            document.getElementById('logsBody').innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text-muted);"><i class="fa-solid fa-spinner fa-spin"></i> Fetching records...</td></tr>`;

            try {
                const url = `<?= url('/api/index.php?route=audit&action=fetch_logs') ?>&page=${currentPage}&limit=${limit}&search=${encodeURIComponent(search)}&action_filter=${encodeURIComponent(action)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (data.success) {
                    renderTable(data.data);
                    updatePagination(data.meta);
                } else {
                    document.getElementById('logsBody').innerHTML = `<tr><td colspan="4" style="text-align:center; color:#ef4444;">Error loading logs.</td></tr>`;
                }
            } catch (e) {
                console.error(e);
            }
        }

        function renderTable(logs) {
            const tbody = document.getElementById('logsBody');
            if (logs.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding: 40px; color:var(--text-muted);">No logs match your criteria.</td></tr>`;
                return;
            }

            let html = '';
            logs.forEach(log => {
                // Formatting Date
                const dateObj = new Date(log.created_at.replace(' ', 'T'));
                const dateStr = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString();

                // User
                const name = log.full_name || 'System / Unknown';
                const email = log.user_email;
                let avatar = '';
                if (log.profile_image) {
                    avatar = `<img src="<?= url('/uploads/') ?>${log.profile_image}" class="user-avatar">`;
                } else {
                    const initials = name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                    avatar = `<div class="user-avatar">${initials}</div>`;
                }

                html += `
                    <tr>
                        <td style="white-space: nowrap; color: var(--text-secondary);">${dateStr}</td>
                        <td>
                            <div class="user-cell">
                                ${avatar}
                                <div class="user-info">
                                    <span class="user-name">${name}</span>
                                    <span class="user-email">${email}</span>
                                </div>
                            </div>
                        </td>
                        <td><span class="action-badge">${log.action}</span></td>
                        <td class="details-cell" title="${log.details.replace(/"/g, '&quot;')}">${log.details}</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
        }

        function updatePagination(meta) {
            totalPages = meta.total_pages || 1;
            currentPage = meta.page;

            const start = ((currentPage - 1) * meta.limit) + 1;
            let end = currentPage * meta.limit;
            if (end > meta.total) end = meta.total;
            if (meta.total === 0) {
                document.getElementById('pageInfo').innerText = `Showing 0 logs`;
            } else {
                document.getElementById('pageInfo').innerText = `Showing ${start} to ${end} of ${meta.total} logs`;
            }

            document.getElementById('prevBtn').disabled = currentPage <= 1;
            document.getElementById('nextBtn').disabled = currentPage >= totalPages;
        }

        document.addEventListener('DOMContentLoaded', () => {
            loadFilterOptions();
            fetchLogs();
        });
    </script>
</body>
</html>
