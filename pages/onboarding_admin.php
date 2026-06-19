<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

$user = getCurrentUser();
$tenant_id = $user['tenant_id'] ?? $_SESSION['tenant_id'] ?? 1;

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'trigger_workflow') {
        $email = $_POST['employee_email'] ?? '';
        $template_id = $_POST['template_id'] ?? 0;
        
        $emp_stmt = $pdo->prepare("SELECT full_name, role FROM users WHERE email = ? AND tenant_id = ?");
        $emp_stmt->execute([$email, $tenant_id]);
        $emp = $emp_stmt->fetch();
        
        if ($emp && $template_id) {
            $pdo->beginTransaction();
            try {
                $ins_wf = $pdo->prepare("INSERT INTO employee_workflows (tenant_id, employee_name, employee_role, template_id, status, completion_percentage, start_date) VALUES (?, ?, ?, ?, 'In Progress', 0, CURDATE())");
                $ins_wf->execute([$tenant_id, $emp['full_name'], $emp['role'], $template_id]);
                $workflow_id = $pdo->lastInsertId();
                
                $tasks_stmt = $pdo->prepare("SELECT task_name, department_owner FROM workflow_tasks WHERE template_id = ? AND tenant_id = ?");
                $tasks_stmt->execute([$template_id, $tenant_id]);
                $tasks = $tasks_stmt->fetchAll();
                
                $ins_task = $pdo->prepare("INSERT INTO employee_workflow_tasks (workflow_id, task_name, department_owner, is_completed) VALUES (?, ?, ?, 0)");
                foreach ($tasks as $t) {
                    $ins_task->execute([$workflow_id, $t['task_name'], $t['department_owner']]);
                }
                
                $pdo->commit();
                header("Location: onboarding_admin.php?success=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    } elseif ($_POST['action'] === 'toggle_task') {
        $task_id = $_POST['task_id'] ?? 0;
        $workflow_id = $_POST['workflow_id'] ?? 0;
        $is_completed = isset($_POST['is_completed']) ? 1 : 0;
        
        $pdo->beginTransaction();
        try {
            $upd_task = $pdo->prepare("UPDATE employee_workflow_tasks SET is_completed = ?, completed_at = IF(? = 1, NOW(), NULL) WHERE id = ?");
            $upd_task->execute([$is_completed, $is_completed, $task_id]);
            
            $calc_stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM employee_workflow_tasks WHERE workflow_id = ?");
            $calc_stmt->execute([$workflow_id]);
            $stats = $calc_stmt->fetch();
            
            $percentage = ($stats['total'] > 0) ? round(($stats['completed'] / $stats['total']) * 100) : 0;
            $status = ($percentage == 100) ? 'Completed' : 'In Progress';
            
            $upd_wf = $pdo->prepare("UPDATE employee_workflows SET completion_percentage = ?, status = ? WHERE id = ?");
            $upd_wf->execute([$percentage, $status, $workflow_id]);
            
            $pdo->commit();
            header("Location: onboarding_admin.php?view_wf=" . $workflow_id);
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// Fetch Active Workflows
$stmt = $pdo->prepare("SELECT * FROM employee_workflows WHERE tenant_id = ? ORDER BY created_at DESC");
$stmt->execute([$tenant_id]);
$workflows = $stmt->fetchAll();

// Fetch Data for Modals
$users = $pdo->prepare("SELECT email, full_name, role FROM users WHERE tenant_id = ? ORDER BY full_name ASC");
$users->execute([$tenant_id]);
$users = $users->fetchAll();

$templates = $pdo->prepare("SELECT * FROM workflow_templates WHERE tenant_id = ? ORDER BY name ASC");
$templates->execute([$tenant_id]);
$templates = $templates->fetchAll();

// Fetch Tasks if viewing a specific workflow
$view_tasks = [];
$view_wf_name = "";
$view_wf_id = 0;
if (isset($_GET['view_wf'])) {
    $view_wf_id = intval($_GET['view_wf']);
    $vt_stmt = $pdo->prepare("SELECT * FROM employee_workflow_tasks WHERE workflow_id = ?");
    $vt_stmt->execute([$view_wf_id]);
    $view_tasks = $vt_stmt->fetchAll();
    
    $vw_stmt = $pdo->prepare("SELECT employee_name FROM employee_workflows WHERE id = ?");
    $vw_stmt->execute([$view_wf_id]);
    $view_wf_name = $vw_stmt->fetchColumn();
}

$current_page = 'onboarding_admin.php';
?>
<?php $page_title = 'Onboarding & Offboarding - Respawn Logics'; ?>
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

        .page-header {
            background: #ffffff;
            padding: 24px;
            border-radius: 16px;
            margin-bottom: 24px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            border: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .title-block h1 {
            font-family: 'Space Grotesk';
            font-size: 1.5rem;
            color: #111827;
            margin: 0 0 4px 0;
        }
        .title-block p {
            color: #6b7280;
            margin: 0;
            font-size: 0.95rem;
        }
        
        .kanban-board {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }
        .kanban-col {
            background: #f9fafb;
            border-radius: 12px;
            padding: 16px;
            border: 1px solid #e5e7eb;
            min-height: 500px;
        }
        .col-header {
            font-family: 'Space Grotesk';
            font-weight: 600;
            font-size: 1.1rem;
            color: #374151;
            margin-bottom: 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .col-count {
            background: #e5e7eb;
            color: #4b5563;
            padding: 2px 8px;
            border-radius: 9999px;
            font-size: 0.8rem;
        }
        
        .wf-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #f3f4f6;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .wf-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .wf-name {
            font-weight: 600;
            color: #111827;
            font-size: 1.05rem;
        }
        .wf-role {
            font-size: 0.85rem;
            color: #6b7280;
            margin-bottom: 12px;
        }
        
        /* Progress Bar */
        .progress-wrapper {
            background: #e5e7eb;
            border-radius: 9999px;
            height: 8px;
            width: 100%;
            overflow: hidden;
            margin-bottom: 6px;
        }
        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 0.3s ease;
        }
        .fill-blue { background: #3b82f6; }
        .fill-green { background: #00e07a; }
        
        .progress-text {
            font-size: 0.75rem;
            color: #4b5563;
            font-weight: 500;
            text-align: right;
        }

        .btn-primary {
            background: #4f46e5;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .modal-content {
            background: white;
            padding: 24px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px;
        }
        .modal-title { font-family: 'Space Grotesk'; font-size: 1.25rem; font-weight: 600; margin:0; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; color: #374151; }
        .form-select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; }
        
        .task-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin-bottom: 8px;
        }
        .task-info { flex-grow: 1; }
        .task-name { font-weight: 500; color: #111827; }
        .task-dept { font-size: 0.75rem; color: #6b7280; background: #f3f4f6; padding: 2px 6px; border-radius: 4px; }
    </style>


<body class="theme-light">
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Lifecycle Workflows</h1>
                </div>
            </header>
            
            <div class="content-wrapper">
                
                <div class="page-header">
                    <div class="title-block">
                        <h1>Onboarding & Offboarding</h1>
                        <p>Track automated IT, Compliance, and HR checklists for employee transitions.</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('triggerModal').classList.add('active')">+ Trigger New Workflow</button>
                </div>

                <div class="kanban-board">
                    <!-- Column: Just Started -->
                    <div class="kanban-col">
                        <div class="col-header">
                            Getting Started <span class="col-count">1</span>
                        </div>
                        <?php foreach ($workflows as $w): if ($w['completion_percentage'] < 50 && $w['status'] !== 'Completed'): ?>
                            <div class="wf-card" onclick="window.location.href='onboarding_admin.php?view_wf=<?= $w['id'] ?>'">
                                <div class="wf-name"><?= htmlspecialchars($w['employee_name']) ?></div>
                                <div class="wf-role"><?= htmlspecialchars($w['employee_role']) ?></div>
                                <div class="progress-wrapper">
                                    <div class="progress-fill fill-blue" style="width: <?= $w['completion_percentage'] ?>%;"></div>
                                </div>
                                <div class="progress-text"><?= $w['completion_percentage'] ?>% Tasks Completed</div>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>

                    <!-- Column: Almost Done -->
                    <div class="kanban-col">
                        <div class="col-header">
                            In Progress <span class="col-count">1</span>
                        </div>
                        <?php foreach ($workflows as $w): if ($w['completion_percentage'] >= 50 && $w['status'] !== 'Completed'): ?>
                            <div class="wf-card" onclick="window.location.href='onboarding_admin.php?view_wf=<?= $w['id'] ?>'">
                                <div class="wf-name"><?= htmlspecialchars($w['employee_name']) ?></div>
                                <div class="wf-role"><?= htmlspecialchars($w['employee_role']) ?></div>
                                <div class="progress-wrapper">
                                    <div class="progress-fill fill-blue" style="width: <?= $w['completion_percentage'] ?>%;"></div>
                                </div>
                                <div class="progress-text"><?= $w['completion_percentage'] ?>% Tasks Completed</div>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>

                    <!-- Column: Completed -->
                    <div class="kanban-col" style="opacity: 0.8;">
                        <div class="col-header">
                            Completed <span class="col-count">1</span>
                        </div>
                        <?php foreach ($workflows as $w): if ($w['status'] === 'Completed'): ?>
                            <div class="wf-card" onclick="window.location.href='onboarding_admin.php?view_wf=<?= $w['id'] ?>'">
                                <div class="wf-name"><?= htmlspecialchars($w['employee_name']) ?></div>
                                <div class="wf-role"><?= htmlspecialchars($w['employee_role']) ?></div>
                                <div class="progress-wrapper">
                                    <div class="progress-fill fill-green" style="width: 100%;"></div>
                                </div>
                                <div class="progress-text" style="color:#00e07a;">100% Completed</div>
                            </div>
                        <?php endif; endforeach; ?>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <!-- Trigger Workflow Modal -->
    <div id="triggerModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Trigger New Workflow</h2>
                <button class="close-btn" onclick="document.getElementById('triggerModal').classList.remove('active')">&times;</button>
            </div>
            <form action="onboarding_admin.php" method="POST">
                <input type="hidden" name="action" value="trigger_workflow">
                
                <div class="form-group">
                    <label class="form-label">Select Employee</label>
                    <select name="employee_email" class="form-select" required>
                        <option value="">-- Choose Employee --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Workflow Template</label>
                    <select name="template_id" class="form-select" required>
                        <option value="">-- Choose Template --</option>
                        <?php foreach ($templates as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="btn-primary" style="width:100%; margin-top: 10px;">Launch Workflow</button>
            </form>
        </div>
    </div>

    <!-- Task View Modal -->
    <div id="taskModal" class="modal-overlay <?= isset($_GET['view_wf']) ? 'active' : '' ?>">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h2 class="modal-title">Tasks for <?= htmlspecialchars($view_wf_name) ?></h2>
                <button class="close-btn" onclick="window.location.href='onboarding_admin.php'">&times;</button>
            </div>
            
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($view_tasks)): ?>
                    <p style="color: #6b7280; text-align: center;">No tasks found.</p>
                <?php else: ?>
                    <?php foreach ($view_tasks as $task): ?>
                        <div class="task-row" style="<?= $task['is_completed'] ? 'opacity: 0.6;' : '' ?>">
                            <div class="task-info">
                                <div class="task-name" style="<?= $task['is_completed'] ? 'text-decoration: line-through;' : '' ?>">
                                    <?= htmlspecialchars($task['task_name']) ?>
                                </div>
                                <span class="task-dept"><?= htmlspecialchars($task['department_owner']) ?></span>
                            </div>
                            
                            <form action="onboarding_admin.php" method="POST" style="margin:0;">
                                <input type="hidden" name="action" value="toggle_task">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <input type="hidden" name="workflow_id" value="<?= $view_wf_id ?>">
                                
                                <label style="display:flex; align-items:center; cursor:pointer;">
                                    <input type="checkbox" name="is_completed" value="1" <?= $task['is_completed'] ? 'checked' : '' ?> onchange="this.form.submit()" style="width: 20px; height: 20px; cursor:pointer;">
                                </label>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</body>
</html>
