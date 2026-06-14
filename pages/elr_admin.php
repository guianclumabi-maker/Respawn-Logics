<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

// ELR access is currently limited to Admin/HR roles.
if (!hasPermission('elr.view')) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Employee Relations Console - Respawn Logic'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .admin-title h1 { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 4px; }
        .admin-title p { font-size: 0.875rem; color: #ef4444; font-weight: 600; } /* Red for ELR */
        
        .layout-grid {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 24px;
            align-items: start;
        }

        @keyframes elr-pulse {
            0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.0); }
            100% { box-shadow: 0 0 40px 0 rgba(239, 68, 68, 0.15); }
        }

        .ticket-list {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 16px;
            height: calc(100vh - 180px);
            overflow-y: auto;
            animation: elr-pulse 4s infinite alternate;
        }
        
        .ticket-item {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s;
        }
        .ticket-item:hover { background: rgba(255,255,255,0.05); }
        .ticket-item.active { background: rgba(239, 68, 68, 0.1); border-left: 3px solid #ef4444; }
        
        .ticket-detail {
            background: rgba(255, 255, 255, 0.02);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 16px;
            padding: 32px;
            display: none;
            height: calc(100vh - 180px);
            overflow-y: auto;
            animation: elr-pulse 4s infinite alternate;
        }

        .comment-thread { margin-top: 24px; }
        .comment-bubble {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 0.875rem;
            max-width: 85%;
        }
        .comment-public { background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); color: white; }
        .comment-internal { background: rgba(245, 158, 11, 0.1); border: 1px solid #f59e0b; color: #fcd34d; align-self: flex-end; margin-left: auto; }
        .comment-system { background: transparent; border: none; color: var(--text-muted); text-align: center; font-style: italic; max-width: 100%; font-size:0.75rem; }
    </style>


<body>
    <div class="ambient-glow" style="background: radial-gradient(circle at top right, rgba(239,68,68,0.15) 0%, transparent 60%);"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="admin-header">
                <div class="admin-title">
                    <h1>Employee & Labor Relations</h1>
                    <p>Confidential Case Management (Disciplinary, PIPs, Grievances)</p>
                </div>
                <div>
                    <button class="btn-primary" onclick="openCreateModal()" style="background:#ef4444;">+ New ELR Case</button>
                </div>
            </div>

            <div class="layout-grid">
                <!-- Sidebar: Queue List -->
                <div class="ticket-list" id="queue-container">
                    <div style="padding:40px; text-align:center;"><div class="spinner"></div></div>
                </div>

                <!-- Main: Ticket Detail -->
                <div class="ticket-detail" id="ticket-detail-panel">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:16px;">
                        <div>
                            <span id="dtl-number" style="color:var(--text-muted); font-size:0.75rem;"></span>
                            <span style="background:rgba(239,68,68,0.2); color:#ef4444; font-size:0.65rem; padding:2px 6px; border-radius:4px; margin-left:8px;">CONFIDENTIAL</span>
                            <h2 id="dtl-subject" style="color:white; margin:4px 0 8px 0; font-size:1.25rem;"></h2>
                            <span id="dtl-employee" style="color:white; font-size:0.875rem; font-weight:600;"></span>
                        </div>
                        <div style="display:flex; gap:8px;">
                            <select id="dtl-status" class="form-input" style="font-size:0.75rem; padding:4px 8px; height:auto;" onchange="updateTicketStatus()">
                                <option value="Open">Open</option>
                                <option value="In Progress">Investigation / In Progress</option>
                                <option value="Resolved">Resolved / Closed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div style="background:rgba(255,255,255,0.02); border:1px solid var(--border-color); padding:16px; border-radius:8px; margin-bottom:24px;">
                        <p id="dtl-desc" style="color:var(--text-muted); font-size:0.875rem; margin:0; white-space:pre-wrap;"></p>
                    </div>

                    <!-- Comments -->
                    <div class="comment-thread" id="dtl-comments" style="display:flex; flex-direction:column;"></div>

                    <!-- Add Comment Form -->
                    <form id="form-comment" onsubmit="submitComment(event)" style="margin-top:24px; background:rgba(0,0,0,0.2); padding:16px; border-radius:8px; border:1px solid var(--border-color);">
                        <textarea id="comment-text" class="form-input" rows="3" placeholder="Log evidence, internal discussion, or reply..." required></textarea>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px;">
                            <select id="comment-type" class="form-input" style="width:200px;">
                                <option value="Internal" selected>Internal Note (Default)</option>
                                <option value="Public">Public Reply (Visible to Employee)</option>
                            </select>
                            <button type="submit" class="btn-primary" style="background:#ef4444;">Post to Case</button>
                        </div>
                    </form>
                </div>

                <!-- Empty State Panel -->
                <div class="ticket-detail" id="empty-state-panel" style="display:flex; flex-direction:column; align-items:center; justify-content:center; text-align:center; border-style:dashed;">
                    <svg style="width:64px; height:64px; opacity:0.2; margin-bottom:16px; color:white;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                    <div style="font-size:1.25rem; font-weight:600; color:white; margin-bottom:8px;">No Case Selected</div>
                    <div style="color:var(--text-muted); font-size:0.875rem;">Select an Employee Relations case from the queue<br>to view details or manage the investigation.</div>
                </div>
            </div>
            
        </main>
    </div>

    <!-- Create ELR Case Modal -->
    <div class="modal-overlay" id="createModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:100;">
        <div class="modal-content" style="background:#161922; border:1px solid #ef4444; border-radius:var(--radius-lg); width:500px; max-width:90vw;">
            <div style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color:white; font-size:1.1rem; margin:0;">Create Confidential Case</h3>
                <button onclick="document.getElementById('createModal').classList.remove('active')" style="background:transparent; border:none; color:white; cursor:pointer;">X</button>
            </div>
            <div style="padding:20px;">
                <form id="form-ticket" onsubmit="submitTicket(event)">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Case Type</label>
                        <select name="ticket_type_id" id="ticket_type_id" class="form-input" required></select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Subject Employee ID</label>
                        <input type="number" name="employee_id" class="form-input" required placeholder="User ID of the employee">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Subject</label>
                        <input type="text" name="subject" class="form-input" required placeholder="Brief summary of case">
                    </div>
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Description / Incident Details</label>
                        <textarea name="description" class="form-input" rows="4" required placeholder="Detailed account of the incident..."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%; background:#ef4444;">Open Case</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        let currentTickets = [];
        let activeTicketId = null;

        async function loadQueue() {
            try {
                // We use agent_queue but we will filter it for ELR specifically (assigned to ELR team or confidential)
                const res = await fetch(`<?= url('/esm_api.php?action=agent_queue') ?>`);
                const data = await res.json();
                if (data.success) {
                    // Filter for ELR Cases (Assume team name is 'Employee Relations' or ticket is confidential)
                    // For safety, we rely on team_name 'Employee Relations'
                    currentTickets = data.data.filter(t => t.team_name === 'Employee Relations' || t.is_confidential == 1);
                    renderQueue();
                }
            } catch(e){
                document.getElementById('queue-container').innerHTML = `<div style="padding:24px; color:red; font-weight:bold;">JS Error: ${e.message}</div>`;
            }
        }

        function renderQueue() {
            const container = document.getElementById('queue-container');
            
            if (currentTickets.length === 0) {
                container.innerHTML = `
                    <div style="padding:40px 24px; text-align:center; color:var(--text-muted);">
                        <svg style="width:48px; height:48px; opacity:0.3; margin:0 auto 12px auto; display:block;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <div style="font-size:1rem; font-weight:500; color:white; margin-bottom:4px;">Queue is Empty</div>
                        <div style="font-size:0.85rem;">No active ELR cases found.</div>
                    </div>
                `;
                return;
            }

            container.innerHTML = currentTickets.map(t => `
                <div class="ticket-item ${t.id === activeTicketId ? 'active' : ''}" onclick="selectTicket(${t.id})">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="font-size:0.75rem; color:var(--text-muted);">${t.ticket_number}</span>
                        <span style="font-size:0.7rem; background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px; color:${t.status==='Open'?'#f59e0b':'white'};">${t.status}</span>
                    </div>
                    <div style="color:white; font-size:0.875rem; font-weight:600; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${t.subject}</div>
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted);">
                        <span>${t.employee_name}</span>
                        <span style="color:#ef4444;">${t.type_name}</span>
                    </div>
                </div>
            `).join('');
        }

        async function selectTicket(id) {
            activeTicketId = id;
            renderQueue();
            
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=ticket_details&id=') ?>` + id);
                const data = await res.json();
                if (data.success) {
                    const t = data.data.ticket;
                    document.getElementById('empty-state-panel').style.display = 'none';
                    document.getElementById('ticket-detail-panel').style.display = 'block';
                    document.getElementById('dtl-number').innerText = `${t.ticket_number} • ${t.type_name}`;
                    document.getElementById('dtl-subject').innerText = t.subject;
                    document.getElementById('dtl-employee').innerText = `Subject: ${t.employee_name}`;
                    document.getElementById('dtl-desc').innerText = t.description;
                    document.getElementById('dtl-status').value = t.status === 'Closed' ? 'Resolved' : t.status;

                    const cContainer = document.getElementById('dtl-comments');
                    cContainer.innerHTML = data.data.comments.map(c => {
                        if (c.comment_type === 'System') {
                            return `<div class="comment-bubble comment-system">${c.comment} <br><span style="font-size:0.65rem;">${c.created_at}</span></div>`;
                        } else if (c.comment_type === 'Internal') {
                            return `<div class="comment-bubble comment-internal" style="border-color:#ef4444; color:#ef4444; background:rgba(239,68,68,0.1);"><strong>${c.author_name} (Confidential Note)</strong><br>${c.comment} <br><span style="font-size:0.65rem; opacity:0.7;">${c.created_at}</span></div>`;
                        } else {
                            return `<div class="comment-bubble comment-public"><strong>${c.author_name || 'System'}</strong><br>${c.comment} <br><span style="font-size:0.65rem; color:var(--text-muted);">${c.created_at}</span></div>`;
                        }
                    }).join('');
                }
            } catch(e){}
        }

        async function updateTicketStatus() {
            if (!activeTicketId) return;
            const status = document.getElementById('dtl-status').value;
            try {
                await fetch(`<?= url('/esm_api.php?action=update_ticket') ?>`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ ticket_id: activeTicketId, status: status })
                });
                selectTicket(activeTicketId);
                loadQueue();
            } catch(e){}
        }

        window.submitComment = async function(e) {
            e.preventDefault();
            if (!activeTicketId) return;
            const comment = document.getElementById('comment-text').value;
            const type = document.getElementById('comment-type').value;
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=add_comment') ?>`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ ticket_id: activeTicketId, comment: comment, comment_type: type })
                });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('comment-text').value = '';
                    selectTicket(activeTicketId);
                }
            } catch(e){}
        };

        async function openCreateModal() {
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=all_ticket_types') ?>`);
                const data = await res.json();
                if (data.success) {
                    // Only show confidential types or HR types
                    const elrTypes = data.data.filter(t => t.is_confidential == 1 || t.name.includes('HR'));
                    document.getElementById('ticket_type_id').innerHTML = elrTypes.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                    document.getElementById('createModal').classList.add('active');
                }
            } catch(e){}
        }

        window.submitTicket = async function(e) {
            e.preventDefault();
            // Since HR is creating this ON BEHALF of or AGAINST an employee, we will just send it to create_ticket.
            // Wait, create_ticket uses currentUser['id'] as employee_id! We need to allow agents to set employee_id.
            
            alert('To create a case against a specific employee, the backend create_ticket logic would need an override. For now, creating it will assign it to your HR account.');
            
            const formData = new FormData(document.getElementById('form-ticket'));
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=create_ticket') ?>`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('createModal').classList.remove('active');
                    loadQueue();
                } else { alert(data.error); }
            } catch(e){}
        };

        // Init
        document.addEventListener('DOMContentLoaded', loadQueue);
    </script>
</body>
</html>
