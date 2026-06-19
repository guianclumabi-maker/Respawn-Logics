<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$tenantId = $user['tenant_id'] ?? $_SESSION['tenant_id'] ?? '1';

// Function to check if manager or HR
function isManagerOrHR_Admin($userId, $tenantId) {
    global $pdo;
    if (hasPermission('performance.manage')) return true;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `manager_id` = ? AND `tenant_id` = ?");
    $stmt->execute([$userId, $tenantId]);
    return $stmt->fetchColumn() > 0;
}

if (!isManagerOrHR_Admin($user['id'], $tenantId)) {
    header('Location: ' . url('/pages/dashboard.php'));
    exit;
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
?>
<?php $page_title = 'Performance & Talent - Respawn Logic'; ?>
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

        .admin-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; }
        .admin-title h1 { font-size: 1.5rem; font-weight: 700; color: white; margin-bottom: 4px; }
        .admin-title p { font-size: 0.875rem; color: var(--text-muted); }
        .tabs-header { display: flex; border-bottom: 1px solid var(--border-color); margin-bottom: 24px; }
        .tab-btn { padding: 12px 24px; color: var(--text-muted); background: transparent; border: none; border-bottom: 2px solid transparent; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .tab-btn:hover { color: white; }
        .tab-btn.active { color: #00e07a; border-bottom-color: #00e07a; }
        .panel-container { background: rgba(22, 25, 34, 0.7); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 20px; }
        
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; border-bottom: 1px solid var(--border-color); }
        .data-table td { padding: 16px; border-bottom: 1px solid rgba(255, 255, 255, 0.02); vertical-align: middle; color: white; font-size: 0.875rem; }
        .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 100px; font-size: 0.75rem; font-weight: 600; }
        .status-Active { background: rgba(0, 224, 122, 0.1); color: #00e07a; border: 1px solid rgba(0, 224, 122, 0.2); }
        .status-Pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-Finalized { background: rgba(59, 130, 246, 0.1); color: #60a5fa; border: 1px solid rgba(59, 130, 246, 0.2); }

        /* 9 Box Grid CSS */
        .nine-box-container {
            display: grid; grid-template-columns: 50px 1fr 1fr 1fr; grid-template-rows: 1fr 1fr 1fr 50px;
            gap: 4px; width: 100%; height: 600px; padding: 20px; background: rgba(0,0,0,0.2); border-radius: 8px;
        }
        .nine-box-y-axis { grid-column: 1; grid-row: 1 / 4; display: flex; align-items: center; justify-content: center; transform: rotate(-90deg); color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 2px;}
        .nine-box-x-axis { grid-column: 2 / 5; grid-row: 4; display: flex; align-items: center; justify-content: center; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; }
        
        .box { border-radius: 8px; padding: 12px; display: flex; flex-wrap: wrap; gap: 8px; align-content: flex-start; overflow-y: auto;}
        .box-title { width: 100%; font-size: 0.7rem; color: rgba(255,255,255,0.5); text-transform: uppercase; font-weight: 700; margin-bottom: 8px; text-align: center; }
        
        /* High Potential */
        .box-3-1 { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); } /* Enigma */
        .box-3-2 { background: rgba(0, 224, 122, 0.15); border: 1px solid rgba(0, 224, 122, 0.3); } /* Growth Employee */
        .box-3-3 { background: rgba(0, 224, 122, 0.3); border: 1px solid rgba(0, 224, 122, 0.6); } /* Future Leader */
        /* Mod Potential */
        .box-2-1 { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.3); } /* Dilemma */
        .box-2-2 { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); } /* Core Employee */
        .box-2-3 { background: rgba(0, 224, 122, 0.15); border: 1px solid rgba(0, 224, 122, 0.3); } /* High Impact */
        /* Low Potential */
        .box-1-1 { background: rgba(239, 68, 68, 0.3); border: 1px solid rgba(239, 68, 68, 0.6); } /* Risk */
        .box-1-2 { background: rgba(245, 158, 11, 0.15); border: 1px solid rgba(245, 158, 11, 0.3); } /* Solid Professional */
        .box-1-3 { background: rgba(59, 130, 246, 0.15); border: 1px solid rgba(59, 130, 246, 0.3); } /* Trusted Pro */

        .emp-avatar-dot {
            width: 32px; height: 32px; border-radius: 50%; background: #333; color: white;
            display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 600;
            border: 2px solid white; cursor: pointer; transition: transform 0.2s;
        }
        .emp-avatar-dot:hover { transform: scale(1.1); z-index: 10; }

        .modal-overlay { position: fixed; top:0; left:0; right:0; bottom:0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.2s; z-index: 100; }
        .modal-overlay.active { opacity: 1; pointer-events: all; }
        .modal-content { background: #161922; border: 1px solid var(--border-color); border-radius: var(--radius-lg); width: 600px; max-width: 90vw; max-height: 85vh; display: flex; flex-direction: column; }
        .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 20px; overflow-y: auto; flex: 1; }
        .btn-action { background: transparent; border: 1px solid var(--border-color); color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.75rem; cursor: pointer; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 6px; }
        .form-control { width: 100%; background: rgba(26, 29, 39, 0.8); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 12px; color: white; }
    </style>


<body>
    <div class="global-glow-green"></div>

    <div class="app-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <div class="admin-header">
                <div class="admin-title">
                    <h1>Performance & Talent Management</h1>
                    <p>Evaluate your team and calibrate talent.</p>
                </div>
            </div>

            <div class="tabs-header">
                <button class="tab-btn active" data-target="team">Team Reviews</button>
                <?php if (hasPermission('performance.manage')): ?>
                <button class="tab-btn" data-target="cycles">Review Cycles</button>
                <button class="tab-btn" data-target="ninebox">9-Box Calibration</button>
                <?php endif; ?>
            </div>

            <!-- Team Reviews Panel -->
            <div id="panel-team" class="panel-container">
                <h3 style="color:white; font-size:1.1rem; margin-bottom: 20px;">My Team's Evaluations</h3>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Cycle</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="team-reviews-list"></tbody>
                </table>
            </div>

            <?php if (hasPermission('performance.manage')): ?>
            <!-- Cycles Panel -->
            <div id="panel-cycles" class="panel-container" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <h3 style="color:white; font-size:1.1rem;">Company Review Cycles</h3>
                    <button class="btn-primary" onclick="document.getElementById('newCycleModal').classList.add('active')">Create Cycle</button>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cycles-list"></tbody>
                </table>
            </div>

            <!-- 9 Box Panel -->
            <div id="panel-ninebox" class="panel-container" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                    <h3 style="color:white; font-size:1.1rem;">Talent Grid (9-Box Calibration)</h3>
                    <select class="form-control" style="width:200px;" id="ninebox-cycle-select" onchange="loadNineBox()"></select>
                </div>
                <div class="nine-box-container">
                    <div class="nine-box-y-axis">Potential (Future Leader)</div>
                    
                    <!-- Row 3 (High Potential) -->
                    <div class="box box-3-1" id="box-1-3"><div class="box-title">Enigma</div></div>
                    <div class="box box-3-2" id="box-2-3"><div class="box-title">Growth Employee</div></div>
                    <div class="box box-3-3" id="box-3-3"><div class="box-title">Future Leader</div></div>

                    <!-- Row 2 (Moderate Potential) -->
                    <div class="box box-2-1" id="box-1-2"><div class="box-title">Dilemma</div></div>
                    <div class="box box-2-2" id="box-2-2"><div class="box-title">Core Employee</div></div>
                    <div class="box box-2-3" id="box-3-2"><div class="box-title">High Impact</div></div>

                    <!-- Row 1 (Low Potential) -->
                    <div class="box box-1-1" id="box-1-1"><div class="box-title">Risk</div></div>
                    <div class="box box-1-2" id="box-2-1"><div class="box-title">Solid Professional</div></div>
                    <div class="box box-1-3" id="box-3-1"><div class="box-title">Trusted Pro</div></div>

                    <div class="nine-box-x-axis">Performance (Current Output)</div>
                </div>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Eval Modal -->
    <div class="modal-overlay" id="evalModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="color:white; font-size: 1.1rem; margin:0;">Evaluate Employee</h3>
                <button class="btn-action" onclick="closeModals()" style="border:none;">X</button>
            </div>
            <div class="modal-body">
                <form id="form-eval" onsubmit="submitEval(event)">
                    <input type="hidden" id="eval_review_id">
                    
                    <div style="background:rgba(255,255,255,0.05); padding:16px; border-radius:8px; margin-bottom:20px;">
                        <h4 style="color:white; font-size:0.875rem; margin-bottom:8px;">Employee's Self Evaluation</h4>
                        <p id="eval_self_comments" style="color:var(--text-muted); font-size:0.875rem; font-style:italic;">Loading...</p>
                    </div>

                    <div class="form-group">
                        <label>Overall Score (1.0 to 5.0)</label>
                        <input type="number" step="0.1" min="1" max="5" class="form-control" id="eval_score" required>
                    </div>

                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div class="form-group">
                            <label>9-Box: Performance (1-3)</label>
                            <select class="form-control" id="eval_perf" required>
                                <option value="1">1 - Below Expectations</option>
                                <option value="2">2 - Meets Expectations</option>
                                <option value="3">3 - Exceeds Expectations</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>9-Box: Potential (1-3)</label>
                            <select class="form-control" id="eval_pot" required>
                                <option value="1">1 - Low (At limit)</option>
                                <option value="2">2 - Moderate (Can grow)</option>
                                <option value="3">3 - High (Future Leader)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Manager Comments (Final)</label>
                        <textarea class="form-control" id="eval_comments" rows="4" required placeholder="Provide constructive feedback..."></textarea>
                    </div>

                    <button type="submit" class="btn-primary" style="width:100%;">Finalize Review</button>
                </form>
            </div>
        </div>
    </div>

    <?php if (hasPermission('performance.manage')): ?>
    <!-- New Cycle Modal -->
    <div class="modal-overlay" id="newCycleModal">
        <div class="modal-content" style="width:400px;">
            <div class="modal-header">
                <h3 style="color:white; font-size: 1.1rem; margin:0;">Create Review Cycle</h3>
                <button class="btn-action" onclick="closeModals()" style="border:none;">X</button>
            </div>
            <div class="modal-body">
                <form id="form-cycle" onsubmit="createCycle(event)">
                    <div class="form-group"><label>Cycle Name</label><input type="text" class="form-control" id="cycle_name" placeholder="e.g. Q3 2026 Annual Review" required></div>
                    <div class="form-group"><label>Start Date</label><input type="date" class="form-control" id="cycle_start" required></div>
                    <div class="form-group"><label>End Date</label><input type="date" class="form-control" id="cycle_end" required></div>
                    <button type="submit" class="btn-primary" style="width:100%;">Create Cycle</button>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
        const tabs = document.querySelectorAll('.tab-btn');
        tabs.forEach(t => {
            t.addEventListener('click', () => {
                tabs.forEach(b => b.classList.remove('active'));
                t.classList.add('active');
                document.querySelectorAll('.panel-container').forEach(p => p.style.display = 'none');
                document.getElementById('panel-' + t.dataset.target).style.display = 'block';
                if(t.dataset.target === 'ninebox') loadNineBox();
            });
        });

        function closeModals() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
        }

        async function loadTeamReviews() {
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=team_reviews') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('team-reviews-list').innerHTML = data.data.map(r => `
                        <tr>
                            <td>
                                <div style="font-weight:600;">${r.employee_name}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${r.job_title || 'N/A'}</div>
                            </td>
                            <td>${r.cycle_name}</td>
                            <td><span class="status-badge status-${r.status.includes('Pending') ? 'Pending' : 'Finalized'}">${r.status}</span></td>
                            <td style="text-align:right;">
                                ${r.status === 'Pending Manager' ? `<button class="btn-primary" style="padding:6px 12px; font-size:0.75rem;" onclick="openEval(${r.id}, \`${r.self_comments || ''}\`)">Evaluate</button>` : `<span style="color:var(--text-muted); font-size:0.75rem;">Done (${r.overall_score_1_to_5}/5)</span>`}
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        function openEval(id, selfComments) {
            document.getElementById('eval_review_id').value = id;
            document.getElementById('eval_self_comments').textContent = selfComments || 'No self evaluation submitted.';
            document.getElementById('evalModal').classList.add('active');
        }

        async function submitEval(e) {
            e.preventDefault();
            const payload = {
                review_id: document.getElementById('eval_review_id').value,
                overall_score_1_to_5: document.getElementById('eval_score').value,
                nine_box_performance: document.getElementById('eval_perf').value,
                nine_box_potential: document.getElementById('eval_pot').value,
                manager_comments: document.getElementById('eval_comments').value
            };
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=submit_manager_eval') ?>`, {
                    method: 'POST', body: JSON.stringify(payload)
                });
                const data = await res.json();
                if(data.success) {
                    closeModals();
                    loadTeamReviews();
                } else { alert(data.error); }
            } catch(e){}
        }

        <?php if (hasPermission('performance.manage')): ?>
        async function loadCycles() {
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=cycles') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('cycles-list').innerHTML = data.data.map(c => `
                        <tr>
                            <td>${c.name}</td>
                            <td>${c.start_date} to ${c.end_date}</td>
                            <td><span class="status-badge status-Active">${c.status}</span></td>
                            <td style="text-align:right;">
                                <button class="btn-action" onclick="initReviews(${c.id})">Deploy Reviews to Company</button>
                            </td>
                        </tr>
                    `).join('');
                    document.getElementById('ninebox-cycle-select').innerHTML = data.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                }
            } catch(e){}
        }

        async function createCycle(e) {
            e.preventDefault();
            const payload = {
                name: document.getElementById('cycle_name').value,
                start_date: document.getElementById('cycle_start').value,
                end_date: document.getElementById('cycle_end').value
            };
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=create_cycle') ?>`, {
                    method: 'POST', body: JSON.stringify(payload)
                });
                if((await res.json()).success) { closeModals(); loadCycles(); }
            } catch(e){}
        }

        async function initReviews(id) {
            if(!confirm("Deploy review shells to all active employees and managers?")) return;
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=initialize_reviews') ?>`, {
                    method: 'POST', body: JSON.stringify({cycle_id: id})
                });
                if((await res.json()).success) { alert("Successfully deployed."); }
            } catch(e){}
        }

        async function loadNineBox() {
            const cycleId = document.getElementById('ninebox-cycle-select').value;
            if(!cycleId) return;

            // Clear boxes
            for(let p=1; p<=3; p++) {
                for(let pot=1; pot<=3; pot++) {
                    const b = document.getElementById(`box-${p}-${pot}`);
                    if(b) {
                        const title = b.querySelector('.box-title').outerHTML;
                        b.innerHTML = title;
                    }
                }
            }

            try {
                const res = await fetch(`<?= url('/performance_api.php?action=nine_box_data&cycle_id=') ?>` + cycleId);
                const data = await res.json();
                if(data.success) {
                    data.data.forEach(r => {
                        if(r.perf && r.pot) {
                            const b = document.getElementById(`box-${r.perf}-${r.pot}`);
                            if(b) {
                                const initials = r.full_name.split(' ').map(n=>n[0]).join('').substring(0,2);
                                b.innerHTML += `<div class="emp-avatar-dot" title="${r.full_name} - Score: ${r.overall_score_1_to_5}">${initials}</div>`;
                            }
                        }
                    });
                }
            } catch(e){}
        }

        loadCycles();
        <?php endif; ?>

        loadTeamReviews();
    </script>
</body>
</html>
