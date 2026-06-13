<?php
// Secure context: only accessible via dashboard.php router
$user = getCurrentUser();
$email = $user ? $user['email'] : ($_SESSION['user_email'] ?? '');
$tenantId = $user ? $user['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');

// Handle task addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    $task_name = trim($_POST['task_name']);
    $task_desc = trim($_POST['task_description'] ?? '');
    
    if (!empty($task_name)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO `employee_tasks` (`tenant_id`, `employee_email`, `task_name`, `task_description`, `is_completed`) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$tenantId, $email, $task_name, $task_desc]);
        } catch (PDOException $e) {
            // Ignore database errors or log them
        }
        header('Location: dashboard.php');
        exit;
    }
}

// Handle task completion toggle
if (isset($_GET['toggle_task'])) {
    $task_id = intval($_GET['toggle_task']);
    try {
        // Double check ownership
        $stmt = $pdo->prepare("SELECT * FROM `employee_tasks` WHERE `id` = ? AND `employee_email` = ? AND `tenant_id` = ?");
        $stmt->execute([$task_id, $email, $tenantId]);
        $task = $stmt->fetch();
        
        if ($task) {
            $new_val = $task['is_completed'] ? 0 : 1;
            $comp_at = $new_val ? date('Y-m-d H:i:s') : null;
            
            $stmt = $pdo->prepare("UPDATE `employee_tasks` SET `is_completed` = ?, `completed_at` = ? WHERE `id` = ? AND `tenant_id` = ?");
            $stmt->execute([$new_val, $comp_at, $task_id, $tenantId]);
        }
    } catch (PDOException $e) {
        // Handle error
    }
    header('Location: dashboard.php');
    exit;
}

// Fetch dashboard statistics
$today = date('Y-m-d');
$clocked_in_today = false;
$clock_time = '--:--';
try {
    // Check attendance for today
    $stmt = $pdo->prepare("SELECT * FROM `attendance` WHERE `employee_email` = ? AND `tenant_id` = ? AND DATE(`time_in`) = ?");
    $stmt->execute([$email, $tenantId, $today]);
    $att = $stmt->fetch();
    if ($att) {
        $clocked_in_today = true;
        $clock_time = date('h:i A', strtotime($att['time_in']));
        if (!empty($att['time_out'])) {
            $clocked_in_today = false; // Clocked out
        }
    }

    // Weekly Working Hours count
    $stmt = $pdo->prepare("SELECT * FROM `attendance` WHERE `employee_email` = ? AND `tenant_id` = ? AND `time_in` >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->execute([$email, $tenantId]);
    $week_logs = $stmt->fetchAll();
    $total_hours = 0;
    foreach ($week_logs as $log) {
        if (!empty($log['time_in']) && !empty($log['time_out'])) {
            $diff = strtotime($log['time_out']) - strtotime($log['time_in']);
            $total_hours += round($diff / 3600, 2);
        }
    }

    // Pending Leaves Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `leave_requests` WHERE `employee_email` = ? AND `tenant_id` = ? AND `status` = 'Pending'");
    $stmt->execute([$email, $tenantId]);
    $pending_leaves = $stmt->fetchColumn();

    // Active Tasks Count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `employee_tasks` WHERE `employee_email` = ? AND `tenant_id` = ? AND `is_completed` = 0");
    $stmt->execute([$email, $tenantId]);
    $active_tasks_count = $stmt->fetchColumn();

    // Fetch todo list
    $stmt = $pdo->prepare("SELECT * FROM `employee_tasks` WHERE `employee_email` = ? AND `tenant_id` = ? ORDER BY `id` DESC LIMIT 10");
    $stmt->execute([$email, $tenantId]);
    $todo_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $total_hours = 0;
    $pending_leaves = 0;
    $active_tasks_count = 0;
    $todo_list = [];
}
?>
<?php $page_title = 'Dashboard - Respawn Logic Portal'; ?>
<?php include __DIR__ . '/../../includes/head.php'; ?>

    <style>
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        @media (max-width: 992px) {
            .dashboard-grid { grid-template-columns: 1fr; }
        }

        .todo-list-wrapper {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 15px;
        }

        .todo-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: rgba(255,255,255,0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            transition: all 0.2s ease;
        }
        .todo-item.completed {
            border-color: rgba(0, 224, 122, 0.2);
            background: rgba(0, 224, 122, 0.02);
        }
        .todo-item:hover {
            border-color: var(--border-color-hover);
        }

        .todo-details {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }

        .todo-checkbox {
            width: 18px;
            height: 18px;
            border-radius: 4px;
            border: 1.5px solid var(--text-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
            flex-shrink: 0;
        }
        .todo-item.completed .todo-checkbox {
            background: var(--accent-green);
            border-color: var(--accent-green);
            color: white;
        }

        .todo-name {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--text-primary);
        }
        .todo-item.completed .todo-name {
            text-decoration: line-through;
            color: var(--text-muted);
        }

        .todo-add-form {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }

        .realtime-clock-widget {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 40px 20px;
            height: 100%;
        }

        .realtime-clock-title {
            font-family: var(--font-heading);
            font-size: 3rem;
            font-weight: 800;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: var(--shadow-glow-green);
            margin: 10px 0;
        }
    </style>


<body>
    <div class="ambient-glow glow-green"></div>
    <div class="ambient-glow glow-cyan"></div>

    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- App Header -->
            <?php include __DIR__ . '/../../includes/app-header.php'; ?>

            <!-- Metrics Highlight Cards -->
            <section class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-label">Weekly Working Hours</div>
                    <div class="metric-value"><?= number_format($total_hours, 1) ?>h</div>
                    <div class="metric-sub">Calculated from past 7 days</div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-label">Today's Clock State</div>
                    <div class="metric-value"><?= $clocked_in_today ? 'In Since' : 'Not Active' ?></div>
                    <div class="metric-sub"><?= $clocked_in_today ? $clock_time : 'No shift active today' ?></div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Pending Leave Requests</div>
                    <div class="metric-value"><?= $pending_leaves ?></div>
                    <div class="metric-sub">Awaiting management approval</div>
                </div>

                <div class="metric-card">
                    <div class="metric-label">Tasks Pending</div>
                    <div class="metric-value"><?= $active_tasks_count ?></div>
                    <div class="metric-sub">Checklist items left to complete</div>
                </div>
            </section>

            <!-- Main Grid Blocks -->
            <div class="dashboard-grid">
                <!-- Clock Widget -->
                <div class="card-panel">
                    <div class="card-title">
                        <span>Real-Time Shift Clock</span>
                        <span class="badge <?= $clocked_in_today ? 'badge-in' : 'badge-out' ?>"><?= $clocked_in_today ? 'Clocked In' : 'Clocked Out' ?></span>
                    </div>
                    <div class="realtime-clock-widget">
                        <div class="realtime-date-display" id="dateDisplay">-- ---- ----</div>
                        <div class="realtime-clock-title" id="clockDisplay">00:00:00</div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 10px;">
                            <?php if ($clocked_in_today): ?>
                                You are clocked in. Work hard, stay focused!
                            <?php else: ?>
                                Tap the Attendance tab in the sidebar to clock in.
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Todo Checklist Widget -->
                <div class="card-panel">
                    <div class="card-title">
                        <span>My Tasks Checklist</span>
                        <span class="badge" style="background: rgba(0, 224, 122, 0.15); color: #c084fc; border: 1px solid rgba(0, 224, 122, 0.3);"><?= $active_tasks_count ?> Active</span>
                    </div>
                    
                    <div class="todo-list-wrapper">
                        <?php if (empty($todo_list)): ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 20px 0; font-size: 0.875rem;">
                                No tasks found. Create one below to begin.
                            </div>
                        <?php else: ?>
                            <?php foreach ($todo_list as $todo): ?>
                                <div class="todo-item <?= $todo['is_completed'] ? 'completed' : '' ?>">
                                    <div class="todo-details">
                                        <a href="dashboard.php?toggle_task=<?= $todo['id'] ?>" class="todo-checkbox">
                                            <?php if ($todo['is_completed']): ?>
                                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
                                                </svg>
                                            <?php endif; ?>
                                        </a>
                                        <span class="todo-name"><?= htmlspecialchars($todo['task_name']) ?></span>
                                    </div>
                                    
                                    <?php if ($todo['is_completed']): ?>
                                        <span style="font-size: 0.75rem; color: var(--accent-green);">Done</span>
                                    <?php else: ?>
                                        <span style="font-size: 0.75rem; color: var(--text-muted);">Active</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form class="todo-add-form" action="dashboard.php" method="POST">
                        <input type="hidden" name="action" value="add_task">
                        <input class="form-input" type="text" name="task_name" required placeholder="New task name..." style="flex: 1; padding: 10px 14px;">
                        <button type="submit" class="btn-primary" style="padding: 10px 18px;">Add</button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
    function updateClock() {
        const now = new Date();
        
        // Time string
        let hours = now.getHours();
        let minutes = now.getMinutes();
        let seconds = now.getSeconds();
        let ampm = hours >= 12 ? 'PM' : 'AM';
        
        hours = hours % 12;
        hours = hours ? hours : 12; 
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        
        const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        document.getElementById('clockDisplay').textContent = timeStr;
        
        // Date string
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const dateStr = now.toLocaleDateString('en-US', options);
        document.getElementById('dateDisplay').textContent = dateStr;
    }
    
    updateClock();
    setInterval(updateClock, 1000);
    </script>
</body>
</html>
