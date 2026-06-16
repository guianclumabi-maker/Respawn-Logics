<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$email = $user ? $user['email'] : ($_SESSION['user_email'] ?? '');
$tenantId = $user ? $user['tenant_id'] : ($_SESSION['tenant_id'] ?? '1');

$message = '';
$msg_type = 'success';

function getBusinessDays($start_date, $end_date) {
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $end->modify('+1 day'); // Include end date
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end);
    $days = 0;
    foreach ($period as $dt) {
        if ($dt->format('N') < 6) {
            $days++;
        }
    }
    return $days;
}

// Fetch Leave Balances
try {
    $bal_stmt = $pdo->prepare("SELECT leave_type, total_allowance, used_balance FROM `leave_balances` WHERE `employee_email` = ? AND `tenant_id` = ?");
    $bal_stmt->execute([$email, $tenantId]);
    $leave_balances = $bal_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $leave_balances = [];
}


// Check if user has supervisor or manager responsibilities
try {
    $stmt_sup = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `immediate_supervisor` = ? AND `tenant_id` = ?");
    $stmt_sup->execute([$email, $tenantId]);
    $is_supervisor = $stmt_sup->fetchColumn() > 0;

    $stmt_mgr = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `department_manager` = ? AND `tenant_id` = ?");
    $stmt_mgr->execute([$email, $tenantId]);
    $is_manager = $stmt_mgr->fetchColumn() > 0;
} catch (PDOException $e) {
    $is_supervisor = false;
    $is_manager = false;
}

// 1. Process Leave Application Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
    $leave_type = trim($_POST['leave_type'] ?? '');
    $start_date = trim($_POST['start_date'] ?? '');
    $end_date = trim($_POST['end_date'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    
    if (empty($leave_type) || empty($start_date) || empty($end_date)) {
        $message = 'Please fill in all required fields.';
        $msg_type = 'error';
    } else if (strtotime($start_date) > strtotime($end_date)) {
        $message = 'Start date cannot be after end date.';
        $msg_type = 'error';
    } else {
        $requested_days = getBusinessDays($start_date, $end_date);
        
        // Verify balance
        $available_balance = 0;
        foreach ($leave_balances as $lb) {
            if ($lb['leave_type'] === $leave_type) {
                $available_balance = $lb['total_allowance'] - $lb['used_balance'];
                break;
            }
        }
        
        if ($requested_days > $available_balance) {
            $message = "Insufficient balance. You requested {$requested_days} day(s), but only have {$available_balance} day(s) available for {$leave_type}.";
            $msg_type = 'error';
        } else {
            try {
                // Check if this employee has a supervisor or manager.
                $supervisor_email = trim($user ? ($user['immediate_supervisor'] ?? '') : '');
                $manager_email    = trim($user ? ($user['department_manager'] ?? '') : '');
                
                $tl_decision_default = empty($supervisor_email) ? 'Approved' : 'Pending';
                $mgr_decision_default = empty($manager_email) ? 'Approved' : 'Pending';
                
                // If both levels are empty, it auto-approves completely
                $initial_status = (empty($supervisor_email) && empty($manager_email)) ? 'Approved' : 'Pending';
                
                $stmt = $pdo->prepare("INSERT INTO `leave_requests` (`tenant_id`, `employee_email`, `leave_type`, `start_date`, `end_date`, `reason`, `status`, `tl_decision`, `manager_decision`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tenantId, $email, $leave_type, $start_date, $end_date, $reason, $initial_status, $tl_decision_default, $mgr_decision_default]);
                
                $message = 'Your leave request has been submitted successfully!';
                $msg_type = 'success';
                
                header("Location: leaves.php?message=" . urlencode($message) . "&type=" . $msg_type);
                exit;
            } catch (PDOException $e) {
                $message = 'Failed to submit leave request: ' . $e->getMessage();
                $msg_type = 'error';
            }
        }
    }
}

// 2. Process Level 1 Supervisor Decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'tl_decision') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $decision = $_POST['decision'] ?? ''; // 'Approved' or 'Rejected'
    $comments = trim($_POST['comments'] ?? '');
    
    if ($request_id > 0 && ($decision === 'Approved' || $decision === 'Rejected')) {
        try {
            // Verify that the logged-in user is indeed the supervisor of the applicant
            $stmt = $pdo->prepare("SELECT lr.*, u.immediate_supervisor FROM `leave_requests` lr JOIN `users` u ON lr.employee_email = u.email WHERE lr.id = ? AND lr.tenant_id = ?");
            $stmt->execute([$request_id, $tenantId]);
            $req = $stmt->fetch();
            
            if ($req && strtolower($req['immediate_supervisor']) === strtolower($email)) {
                $status = ($decision === 'Rejected') ? 'Rejected' : 'Pending';
                
                // If the employee has NO manager, and TL approves, auto-approve the whole request
                $emp_manager = trim($user ? ($user['department_manager'] ?? '') : '');
                if ($decision === 'Approved' && empty($emp_manager)) {
                    $status = 'Approved';
                    // Deduct balance
                    $req_days = getBusinessDays($req['start_date'], $req['end_date']);
                    $deduct_stmt = $pdo->prepare("UPDATE `leave_balances` SET `used_balance` = `used_balance` + ? WHERE `employee_email` = ? AND `leave_type` = ? AND `tenant_id` = ?");
                    $deduct_stmt->execute([$req_days, $req['employee_email'], $req['leave_type'], $tenantId]);
                }
                
                $manager_dec = (empty($emp_manager) && $decision === 'Approved') ? 'Approved' : $req['manager_decision'];
                
                $stmt_update = $pdo->prepare("UPDATE `leave_requests` SET `tl_decision` = ?, `tl_decided_by` = ?, `tl_decision_date` = NOW(), `tl_comments` = ?, `status` = ?, `manager_decision` = ? WHERE `id` = ? AND `tenant_id` = ?");
                $stmt_update->execute([$decision, $email, $comments, $status, $manager_dec, $request_id, $tenantId]);
                
                // Write audit log entry
                $stmt_audit = $pdo->prepare("INSERT INTO `audit_logs` (`user_email`, `action`, `details`) VALUES (?, 'Leave Decided by Supervisor', ?)");
                $stmt_audit->execute([$email, "Supervisor {$decision} leave request #{$request_id} with comments: {$comments}"]);
                
                // In-app notification to employee
                $notif_body = "Your leave request #{$request_id} has been {$decision} by your supervisor.";
                if ($decision === 'Rejected' && !empty($comments)) {
                    $notif_body .= " Remarks: \"{$comments}\"";
                }
                $pdo->prepare("INSERT INTO `notifications` (`tenant_id`, `user_email`, `title`, `body`, `type`, `link`) VALUES (?, ?, ?, ?, 'leave', '/pages/leaves.php')")
                    ->execute([$tenantId, $req['employee_email'], "Leave Request Update", $notif_body]);
                
                $message = "Decision submitted successfully for request #{$request_id}!";
                $msg_type = 'success';
                header("Location: leaves.php?message=" . urlencode($message) . "&type=" . $msg_type);
                exit;
            } else {
                $message = "Unauthorized action or request not found.";
                $msg_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Failed to submit decision: ' . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = 'Invalid decision inputs.';
        $msg_type = 'error';
    }
}

// 3. Process Level 2 Manager Decision
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manager_decision') {
    $request_id = intval($_POST['request_id'] ?? 0);
    $decision = $_POST['decision'] ?? ''; // 'Approved' or 'Rejected'
    $comments = trim($_POST['comments'] ?? '');
    
    if ($request_id > 0 && ($decision === 'Approved' || $decision === 'Rejected')) {
        try {
            // Verify that the logged-in user is indeed the department manager of the applicant
            $stmt = $pdo->prepare("SELECT lr.*, u.department_manager FROM `leave_requests` lr JOIN `users` u ON lr.employee_email = u.email WHERE lr.id = ? AND lr.tenant_id = ?");
            $stmt->execute([$request_id, $tenantId]);
            $req = $stmt->fetch();
            
            if ($req && strtolower($req['department_manager']) === strtolower($email)) {
                $status = ($decision === 'Approved') ? 'Approved' : 'Rejected';
                
                $stmt_update = $pdo->prepare("UPDATE `leave_requests` SET `manager_decision` = ?, `manager_decided_by` = ?, `manager_decision_date` = NOW(), `manager_comments` = ?, `status` = ? WHERE `id` = ? AND `tenant_id` = ?");
                $stmt_update->execute([$decision, $email, $comments, $status, $request_id, $tenantId]);
                
                // If Approved, deduct balance
                if ($status === 'Approved') {
                    $req_days = getBusinessDays($req['start_date'], $req['end_date']);
                    $deduct_stmt = $pdo->prepare("UPDATE `leave_balances` SET `used_balance` = `used_balance` + ? WHERE `employee_email` = ? AND `leave_type` = ? AND `tenant_id` = ?");
                    $deduct_stmt->execute([$req_days, $req['employee_email'], $req['leave_type'], $tenantId]);
                }

                // Write audit log entry
                $stmt_audit = $pdo->prepare("INSERT INTO `audit_logs` (`user_email`, `action`, `details`) VALUES (?, 'Leave Decided by Manager', ?)");
                $stmt_audit->execute([$email, "Manager {$decision} leave request #{$request_id} with comments: {$comments}"]);
                
                // In-app notification to employee
                $notif_body = "Your leave request #{$request_id} has been given a final {$decision} by your manager.";
                if ($decision === 'Rejected' && !empty($comments)) {
                    $notif_body .= " Remarks: \"{$comments}\"";
                }
                $pdo->prepare("INSERT INTO `notifications` (`tenant_id`, `user_email`, `title`, `body`, `type`, `link`) VALUES (?, ?, ?, ?, 'leave', '/pages/leaves.php')")
                    ->execute([$tenantId, $req['employee_email'], "Leave Request Final Decision", $notif_body]);
                
                $message = "Final decision submitted successfully for request #{$request_id}!";
                $msg_type = 'success';
                header("Location: leaves.php?message=" . urlencode($message) . "&type=" . $msg_type);
                exit;
            } else {
                $message = "Unauthorized action or request not found.";
                $msg_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Failed to submit decision: ' . $e->getMessage();
            $msg_type = 'error';
        }
    } else {
        $message = 'Invalid decision inputs.';
        $msg_type = 'error';
    }
}

// 4. Fetch pending supervisor requests (Level 1)
$pending_tl = [];
if ($is_supervisor) {
    try {
        $stmt = $pdo->prepare("SELECT lr.*, u.full_name as employee_name, u.job_title 
                               FROM `leave_requests` lr 
                               JOIN `users` u ON lr.employee_email = u.email 
                               WHERE u.immediate_supervisor = ? AND lr.tenant_id = ? AND lr.tl_decision = 'Pending' AND lr.status = 'Pending'
                               ORDER BY lr.created_at ASC");
        $stmt->execute([$email, $tenantId]);
        $pending_tl = $stmt->fetchAll();
    } catch (PDOException $e) {
        $pending_tl = [];
    }
}

// 5. Fetch pending manager requests (Level 2)
$pending_manager = [];
if ($is_manager) {
    try {
        $stmt = $pdo->prepare("SELECT lr.*, u.full_name as employee_name, u.job_title 
                               FROM `leave_requests` lr 
                               JOIN `users` u ON lr.employee_email = u.email 
                               WHERE u.department_manager = ? AND lr.tenant_id = ? AND lr.tl_decision = 'Approved' AND lr.manager_decision = 'Pending' AND lr.status = 'Pending'
                               ORDER BY lr.created_at ASC");
        $stmt->execute([$email, $tenantId]);
        $pending_manager = $stmt->fetchAll();
    } catch (PDOException $e) {
        $pending_manager = [];
    }
}

// 6. Fetch user's own leave history
try {
    $stmt = $pdo->prepare("SELECT * FROM `leave_requests` WHERE `employee_email` = ? AND `tenant_id` = ? ORDER BY `created_at` DESC");
    $stmt->execute([$email, $tenantId]);
    $leaves = $stmt->fetchAll();
} catch (PDOException $e) {
    $leaves = [];
}

// Resolve query status message
if (isset($_GET['message'])) {
    $message = $_GET['message'];
    $msg_type = $_GET['type'] ?? 'success';
}

// Map email to full name for immediate supervisor resolution
$all_users = [];
try {
    $all_users_stmt = $pdo->prepare("SELECT email, full_name FROM users WHERE tenant_id = ?");
    $all_users_stmt->execute([$tenantId]);
    while ($r = $all_users_stmt->fetch()) {
        $all_users[strtolower($r['email'])] = $r['full_name'];
    }
} catch (PDOException $e) {
    // Ignore
}
?>
<?php $page_title = 'Leave Requests - Respawn Logic Portal'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        .leaves-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 30px;
        }
        @media (max-width: 992px) {
            .leaves-layout { grid-template-columns: 1fr; }
        }
        
        .pending-approvals-box {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .pending-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 16px;
        }
        
        .pending-header-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            padding-bottom: 10px;
        }
        
        .requester-info strong {
            font-family: var(--font-heading);
            color: #ffffff;
            font-size: 0.95rem;
        }
        
        .requester-info span {
            display: block;
            font-size: 0.775rem;
            color: var(--text-secondary);
        }
        
        .pending-details-row {
            font-size: 0.85rem;
            color: var(--text-primary);
            line-height: 1.4;
            margin-bottom: 16px;
        }
        
        .pending-details-row div {
            margin-bottom: 6px;
        }
        
        .approval-actions {
            display: flex;
            gap: 12px;
        }
        
        .badge-tl {
            background: rgba(6, 182, 212, 0.15);
            color: var(--accent-cyan);
            border: 1px solid rgba(6, 182, 212, 0.3);
        }
        
        .badge-mgr {
            background: rgba(0, 224, 122, 0.15);
            color: #c084fc;
            border: 1px solid rgba(0, 224, 122, 0.3);
        }
    </style>


<body>
    <div class="ambient-glow glow-green"></div>
    <div class="ambient-glow glow-cyan"></div>

    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- App Header -->
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Leave Management</h1>
                </div>
            </header>

            <?php if ($message): ?>
                <div class="alert alert-<?= $msg_type ?>">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Leave Balances Widget -->
            <div class="card-panel" style="margin-bottom: 30px;">
                <div class="card-title">My Leave Balances</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                    <?php if (empty($leave_balances)): ?>
                        <p style="color: var(--text-secondary); font-size: 0.875rem;">No leave balances configured.</p>
                    <?php else: ?>
                        <?php foreach ($leave_balances as $lb): 
                            $available = $lb['total_allowance'] - $lb['used_balance'];
                            $percent = ($lb['total_allowance'] > 0) ? ($lb['used_balance'] / $lb['total_allowance']) * 100 : 0;
                            // Color logic: green if <50%, yellow if <80%, red if >80%
                            $barColor = '#00e07a'; // green
                            if ($percent >= 80) $barColor = '#ef4444'; // red
                            else if ($percent >= 50) $barColor = '#f59e0b'; // yellow
                        ?>
                        <div style="background: rgba(255,255,255,0.02); padding: 15px; border-radius: var(--radius-md); border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                                <strong style="color: #fff; font-size: 0.95rem;"><?= htmlspecialchars($lb['leave_type']) ?></strong>
                                <span style="color: var(--text-secondary); font-size: 0.85rem;"><?= $available ?> days left</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: rgba(0,0,0,0.3); border-radius: 4px; overflow: hidden;">
                                <div style="width: <?= $percent ?>%; height: 100%; background: <?= $barColor ?>; border-radius: 4px; transition: width 0.3s ease;"></div>
                            </div>
                            <div style="margin-top: 8px; font-size: 0.75rem; color: var(--text-secondary); text-align: right;">
                                <?= $lb['used_balance'] ?> / <?= $lb['total_allowance'] ?> used
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="leaves-layout">
                <!-- Apply Leave Form -->
                <div class="card-panel">
                    <div class="card-title">Apply for Leave</div>
                    
                    <form action="leaves.php" method="POST">
                        <input type="hidden" name="action" value="apply_leave">
                        
                        <div class="form-group">
                            <label class="form-label" for="leave_type">Leave Type</label>
                            <select class="form-input" id="leave_type" name="leave_type" required style="background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); color: var(--text-primary); cursor: pointer; appearance: none; -webkit-appearance: none;">
                                <option value="" style="background: var(--bg-dark-base);">-- Select Leave --</option>
                                <option value="Sick Leave" style="background: var(--bg-dark-base);">Sick Leave</option>
                                <option value="Vacation Leave" style="background: var(--bg-dark-base);">Vacation Leave</option>
                                <option value="Emergency Leave" style="background: var(--bg-dark-base);">Emergency Leave</option>
                                <option value="Maternity/Paternity Leave" style="background: var(--bg-dark-base);">Maternity/Paternity Leave</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="start_date">Start Date</label>
                            <input class="form-input" type="date" id="start_date" name="start_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="end_date">End Date</label>
                            <input class="form-input" type="date" id="end_date" name="end_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="reason">Reason / Comments</label>
                            <textarea class="form-input" id="reason" name="reason" rows="4" placeholder="Brief explanation of your leave request..."></textarea>
                        </div>
                        
                        <button type="submit" class="btn-primary w-full" style="margin-top: 10px;">
                            <span>Submit Request</span>
                            <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Right Workspace Side (Approvals + History) -->
                <div>
                    <!-- 1. PENDING APPROVALS (If Supervisor or Manager) -->
                    <?php if (!empty($pending_tl) || !empty($pending_manager)): ?>
                        <div class="card-panel" style="margin-bottom: 30px;">
                            <div class="card-title" style="color: var(--accent-purple);">Pending Leave Approvals</div>
                            <div class="pending-approvals-box">
                                
                                <!-- Team Lead Level 1 Approvals -->
                                <?php foreach ($pending_tl as $req): ?>
                                    <div class="pending-card" style="border-left: 4px solid var(--accent-cyan);">
                                        <div class="pending-header-row">
                                            <div class="requester-info">
                                                <strong><?= htmlspecialchars($req['employee_name']) ?></strong>
                                                <span><?= htmlspecialchars($req['job_title'] ?? 'Staff') ?></span>
                                            </div>
                                            <span class="badge badge-tl">Level 1 (Supervisor)</span>
                                        </div>
                                        
                                        <div class="pending-details-row">
                                            <div><strong>Type:</strong> <?= htmlspecialchars($req['leave_type']) ?></div>
                                            <div><strong>Range:</strong> <?= date('M d, Y', strtotime($req['start_date'])) ?> to <?= date('M d, Y', strtotime($req['end_date'])) ?></div>
                                            <div><strong>Reason:</strong> "<?= htmlspecialchars($req['reason'] ?? 'No reason provided') ?>"</div>
                                        </div>
                                        
                                        <form action="leaves.php" method="POST">
                                            <input type="hidden" name="action" value="tl_decision">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            
                                            <div class="form-group" style="margin-bottom: 12px;">
                                                <input class="form-input" type="text" name="comments" placeholder="Add comments/remarks..." style="padding: 8px 12px; font-size: 0.85rem;">
                                            </div>
                                            <div class="approval-actions">
                                                <button type="submit" name="decision" value="Approved" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; background: var(--accent-green); box-shadow: none;">Approve</button>
                                                <button type="submit" name="decision" value="Rejected" class="btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Manager Level 2 Approvals -->
                                <?php foreach ($pending_manager as $req): ?>
                                    <?php
                                        // Manager Collision Check
                                        $col_stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN users u ON lr.employee_email = u.email WHERE u.department = (SELECT department FROM users WHERE email = ?) AND lr.status = 'Approved' AND lr.start_date <= ? AND lr.end_date >= ? AND lr.id != ? AND lr.tenant_id = ?");
                                        $col_stmt->execute([$req['employee_email'], $req['end_date'], $req['start_date'], $req['id'], $tenantId]);
                                        $collisions = $col_stmt->fetchColumn();
                                    ?>
                                    <div class="pending-card" style="border-left: 4px solid var(--accent-purple);">
                                        <div class="pending-header-row">
                                            <div class="requester-info">
                                                <strong><?= htmlspecialchars($req['employee_name']) ?></strong>
                                                <span><?= htmlspecialchars($req['job_title'] ?? 'Staff') ?></span>
                                            </div>
                                            <span class="badge badge-mgr">Level 2 (Manager)</span>
                                        </div>
                                        
                                        <?php if ($collisions > 0): ?>
                                        <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 8px 12px; border-radius: 4px; margin-bottom: 12px; color: #fca5a5; font-size: 0.85rem; display: flex; align-items: center; gap: 8px;">
                                            ⚠️ Warning: <?= $collisions ?> other employee(s) in this department are already on leave during these dates.
                                        </div>
                                        <?php endif; ?>

                                        <div class="pending-details-row">
                                            <div><strong>Type:</strong> <?= htmlspecialchars($req['leave_type']) ?></div>
                                            <div><strong>Range:</strong> <?= date('M d, Y', strtotime($req['start_date'])) ?> to <?= date('M d, Y', strtotime($req['end_date'])) ?></div>
                                            <div><strong>Reason:</strong> "<?= htmlspecialchars($req['reason'] ?? 'No reason provided') ?>"</div>
                                            <?php if (!empty($req['tl_comments'])): ?>
                                                <div style="font-size: 0.8rem; color: var(--accent-cyan); margin-top: 6px; background: rgba(6, 182, 212, 0.05); padding: 6px 10px; border-radius: 4px;">
                                                    Supervisor Remarks: "<?= htmlspecialchars($req['tl_comments']) ?>"
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <form action="leaves.php" method="POST">
                                            <input type="hidden" name="action" value="manager_decision">
                                            <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                            
                                            <div class="form-group" style="margin-bottom: 12px;">
                                                <input class="form-input" type="text" name="comments" placeholder="Add manager comments/remarks..." style="padding: 8px 12px; font-size: 0.85rem;">
                                            </div>
                                            <div class="approval-actions">
                                                <button type="submit" name="decision" value="Approved" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; background: var(--accent-purple); box-shadow: none;">Final Approve</button>
                                                <button type="submit" name="decision" value="Rejected" class="btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);">Reject</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endforeach; ?>
                                
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. LEAVE REQUEST HISTORY -->
                    <div class="card-panel">
                        <div class="card-title">Leave Request History</div>
                        
                        <div class="table-container">
                            <table class="dark-table">
                                <thead>
                                    <tr>
                                        <th>Date Applied</th>
                                        <th>Leave Type</th>
                                        <th>Date Range</th>
                                        <th>Reason</th>
                                        <th>Approval Status</th>
                                        <th>Comments / Decision Log</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($leaves)): ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 30px 0;">You have not submitted any leave requests yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($leaves as $leave): 
                                            $applied_at = date('M d, Y', strtotime($leave['created_at']));
                                            $dates = date('M d', strtotime($leave['start_date'])) . ' - ' . date('M d, Y', strtotime($leave['end_date']));
                                            $status = $leave['status'];
                                            
                                            // Format detailed hierarchy status description
                                            $status_text = $status;
                                            $badge_class = 'badge-pending';
                                            
                                            if ($status === 'Pending') {
                                                if ($leave['tl_decision'] === 'Pending') {
                                                    $status_text = 'Pending TL';
                                                } else if ($leave['tl_decision'] === 'Approved' && $leave['manager_decision'] === 'Pending') {
                                                    $status_text = 'Pending Mgr';
                                                }
                                            } else if ($status === 'Approved') {
                                                $badge_class = 'badge-approved';
                                            } else if ($status === 'Rejected') {
                                                $badge_class = 'badge-rejected';
                                            }
                                            
                                            // Construct logical decision log
                                            $decision_log = [];
                                            
                                            // Supervisor level log
                                            if ($leave['tl_decision'] !== 'Pending') {
                                                $tl_name = isset($all_users[strtolower($leave['tl_decided_by'])]) ? $all_users[strtolower($leave['tl_decided_by'])] : $leave['tl_decided_by'];
                                                $tl_action = ($leave['tl_decision'] === 'Approved') ? 'Recommended' : 'Rejected';
                                                $tl_log = "Supervisor ({$tl_action})";
                                                if (!empty($leave['tl_comments'])) {
                                                    $tl_log .= ": \"" . htmlspecialchars($leave['tl_comments']) . "\"";
                                                }
                                                $decision_log[] = $tl_log;
                                            }
                                            
                                            // Manager level log
                                            if ($leave['manager_decision'] !== 'Pending') {
                                                $mgr_name = isset($all_users[strtolower($leave['manager_decided_by'])]) ? $all_users[strtolower($leave['manager_decided_by'])] : $leave['manager_decided_by'];
                                                $mgr_action = ($leave['manager_decision'] === 'Approved') ? 'Approved' : 'Rejected';
                                                $mgr_log = "Manager ({$mgr_action})";
                                                if (!empty($leave['manager_comments'])) {
                                                    $mgr_log .= ": \"" . htmlspecialchars($leave['manager_comments']) . "\"";
                                                }
                                                $decision_log[] = $mgr_log;
                                            }
                                            
                                            $log_text = empty($decision_log) ? '--' : implode(' | ', $decision_log);
                                        ?>
                                            <tr>
                                                <td><?= $applied_at ?></td>
                                                <td><strong><?= htmlspecialchars($leave['leave_type']) ?></strong></td>
                                                <td><?= $dates ?></td>
                                                <td style="max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($leave['reason'] ?? '') ?>"><?= htmlspecialchars($leave['reason'] ?? '--') ?></td>
                                                <td><span class="badge <?= $badge_class ?>"><?= htmlspecialchars($status_text) ?></span></td>
                                                <td style="max-width: 300px; font-size: 0.8rem; color: var(--text-secondary); line-height: 1.3;" title="<?= htmlspecialchars($log_text) ?>"><?= $log_text ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
