<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();

// Handle Reassignment POST Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reassign_employee') {
    if (strtolower($user['role'] ?? '') === 'admin' || strtolower($user['role'] ?? '') === 'hr') {
        $target_email = $_POST['target_email'] ?? '';
        $new_title = trim($_POST['job_title'] ?? '');
        $new_dept = trim($_POST['department'] ?? '');
        $new_supervisor = trim($_POST['immediate_supervisor'] ?? '');
        
        if (!empty($target_email)) {
            try {
                $pdo->beginTransaction();
                
                $old_stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $old_stmt->execute([$target_email]);
                $old_user = $old_stmt->fetch();
                
                if ($old_user) {
                    $upd_stmt = $pdo->prepare("UPDATE users SET job_title = ?, department = ?, immediate_supervisor = ? WHERE email = ?");
                    $upd_stmt->execute([$new_title, $new_dept, $new_supervisor, $target_email]);
                    
                    $hist_stmt = $pdo->prepare("INSERT INTO employment_history (tenant_id, user_id, change_type, job_title, department, manager_id, effective_date, notes, recorded_by) VALUES (?, ?, 'Reassignment', ?, ?, ?, CURDATE(), ?, ?)");
                    
                    $sup_id = null;
                    if (!empty($new_supervisor)) {
                        $sup_id_stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $sup_id_stmt->execute([$new_supervisor]);
                        $sup_id = $sup_id_stmt->fetchColumn();
                    }
                    
                    $hist_stmt->execute([
                        $old_user['tenant_id'] ?? 1, 
                        $old_user['id'], 
                        $new_title, 
                        $new_dept, 
                        $sup_id, 
                        "Reassigned via Org Chart. Old Supervisor: " . ($old_user['immediate_supervisor'] ?: 'None'), 
                        $user['id'] ?? 0
                    ]);
                }
                
                $pdo->commit();
                header("Location: org-chart.php?email=" . urlencode($target_email) . "&success=1");
                exit;
            } catch (Exception $e) {
                $pdo->rollBack();
                $error_msg = "Failed to reassign: " . $e->getMessage();
            }
        }
    } else {
        $error_msg = "Unauthorized: Only Admins or HR can modify the org structure.";
    }
}

// 1. Determine selected employee
$selected_emp = null;
$selected_email = trim($_GET['email'] ?? '');
if (!empty($selected_email)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
        $stmt->execute([$selected_email]);
        $selected_emp = $stmt->fetch();
    } catch (PDOException $e) {
        $selected_emp = null;
    }
}

// Fallback to logged-in user
if (!$selected_emp) {
    $selected_emp = $user;
}

// Fallback to CEO / highest role ranking user if still not set
if (!$selected_emp) {
    try {
        $stmt = $pdo->query("SELECT * FROM `users` ORDER BY CASE WHEN role = 'admin' THEN 1 WHEN role = 'manager' THEN 2 WHEN role = 'supervisor' THEN 3 ELSE 4 END ASC, `full_name` ASC LIMIT 1");
        $selected_emp = $stmt->fetch();
    } catch (PDOException $e) {
        $selected_emp = null;
    }
}

// 2. Fetch Ancestors Management Chain (CEO -> Supervisor)
$ancestors = [];
if ($selected_emp) {
    $current_supervisor_email = $selected_emp['immediate_supervisor'];
    $visited_emails = [strtolower($selected_emp['email'])]; // Cycle prevention
    
    while (!empty($current_supervisor_email)) {
        if (in_array(strtolower($current_supervisor_email), $visited_emails)) {
            break; // Circular link safeguard
        }
        $visited_emails[] = strtolower($current_supervisor_email);
        
        try {
            $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `email` = ?");
            $stmt->execute([$current_supervisor_email]);
            $sup = $stmt->fetch();
            if ($sup) {
                $ancestors[] = $sup;
                $current_supervisor_email = $sup['immediate_supervisor'];
            } else {
                break;
            }
        } catch (PDOException $e) {
            break;
        }
    }
    $ancestors = array_reverse($ancestors);
}

// 3. Fetch Direct Reports (Immediate Children)
$direct_reports = [];
if ($selected_emp) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM `users` WHERE `immediate_supervisor` = ? ORDER BY `full_name` ASC");
        $stmt->execute([$selected_emp['email']]);
        $direct_reports = $stmt->fetchAll();
    } catch (PDOException $e) {
        $direct_reports = [];
    }
}

// 4. Fetch all employees for Search Autocomplete
$all_employees = [];
try {
    $stmt = $pdo->query("SELECT full_name, email, job_title FROM `users` ORDER BY `full_name` ASC");
    $all_employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_employees = [];
}

// Helper to get initials
function getInitials($fullName) {
    $initials = '';
    $names = explode(' ', $fullName);
    foreach ($names as $n) {
        $initials .= strtoupper(substr($n, 0, 1));
    }

    return substr($initials, 0, 2);
}
?>
<?php $page_title = 'Org Chart Directory - Respawn Logic Portal'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>
        .org-controls { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 30px; background: var(--bg-dark-surface); border: 1px solid var(--border-color); padding: 16px 24px; border-radius: var(--radius-md); }
        .search-group { position: relative; flex: 1; max-width: 400px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--text-muted); pointer-events: none; width: 18px; height: 18px; }
        .search-input { padding-left: 40px !important; }
        .org-chart-container { display: flex; flex-direction: column; align-items: center; width: 100%; margin: 0 auto; max-width: 900px; padding-bottom: 40px; }
        .connector-line { width: 2px; height: 24px; background: rgba(255, 255, 255, 0.15); }
        .ancestors-container { display: flex; flex-direction: column; align-items: center; width: 100%; }
        .ancestor-card, .report-card { background: var(--bg-dark-surface); border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 10px 16px; display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all 0.2s ease; position: relative; }
        .ancestor-card { width: 280px; }
        .report-card { width: 270px; }
        .ancestor-card:hover, .report-card:hover { border-color: var(--border-color-hover); background: rgba(255, 255, 255, 0.04); transform: translateY(-1px); }
        .active-focus-card { background: var(--bg-dark-surface-elevated); border: 2px solid #00e07a; border-radius: var(--radius-md); padding: 20px 24px; width: 420px; box-shadow: 0 0 20px rgba(0, 224, 122, 0.2), var(--shadow-lg); display: flex; flex-direction: column; gap: 14px; position: relative; }
        @media (max-width: 480px) { .active-focus-card { width: 100%; } }
        .active-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; }
        .active-card-avatar { width: 60px; height: 60px; border-radius: 50%; border: 2px solid #00e07a; object-fit: cover; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-family: var(--font-heading); font-weight: 700; font-size: 1.25rem; color: #ffffff; }
        .active-card-details { flex: 1; min-width: 0; }
        .active-card-name { font-family: var(--font-heading); font-size: 1.15rem; font-weight: 700; color: #ffffff; margin-bottom: 2px; }
        .active-card-title { font-size: 0.85rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 4px; }
        .active-card-dept { display: inline-block; font-size: 0.725rem; padding: 2px 8px; border-radius: 4px; background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); color: var(--text-secondary); }
        .active-card-actions { display: flex; gap: 10px; border-top: 1px solid rgba(255, 255, 255, 0.05); padding-top: 12px; margin-top: 2px; justify-content: center; }
        .action-icon-btn { background: transparent; border: none; color: var(--text-secondary); cursor: pointer; font-size: 14px; transition: all 0.2s; display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 50%; border: 1px solid rgba(255, 255, 255, 0.06); text-decoration: none; }
        .action-icon-btn:hover { color: #00e07a; background: rgba(0, 224, 122, 0.1); border-color: rgba(0, 224, 122, 0.2); transform: translateY(-1px); }
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: var(--bg-dark-surface-elevated); padding: 24px; border-radius: 12px; width: 100%; max-width: 500px; border: 1px solid var(--border-color); box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05); padding-bottom: 12px; }
        .modal-title { font-family: var(--font-heading); font-size: 1.25rem; font-weight: 600; margin:0; color: #fff; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: var(--text-secondary); }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; color: var(--text-primary); }
        .form-input { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 0.95rem; background: rgba(0,0,0,0.2); color: #fff; }
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background: rgba(0,0,0,0.3);
            padding: 2px 6px;
            border-radius: 3px;
            border: 1px solid var(--border-color);
        }
    </style>


<body>
    <div class="ambient-glow glow-green" style="background: radial-gradient(circle, rgba(0,224,122,0.15) 0%, transparent 70%);"></div>
    <div class="ambient-glow glow-cyan" style="background: radial-gradient(circle, rgba(6,182,212,0.1) 0%, transparent 70%);"></div>

    <div class="app-wrapper">
        <!-- Sidebar Navigation -->
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <!-- Main Workspace -->
        <main class="main-content">
            <!-- App Header -->
            <?php include __DIR__ . '/../includes/app-header.php'; ?>

            <!-- Interactive Autocomplete Search Box -->
            <div class="org-controls">
                <h2 style="font-family: var(--font-heading); font-size: 1.25rem; font-weight: 700;">Hierarchy Focused Org Chart</h2>
                
                <div class="search-group">
                    <svg class="search-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" id="searchBox" class="form-input search-input" placeholder="Search by name or alias..." list="employee-list" autocomplete="off">
                    <datalist id="employee-list">
                        <?php foreach ($all_employees as $emp): ?>
                            <option value="<?= htmlspecialchars($emp['full_name']) ?>" data-email="<?= htmlspecialchars($emp['email']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>
            </div>

            <!-- Focused Organization Chain Flow Diagram -->
            <div class="org-chart-container">
                
                <!-- A. ANCESTORS MANAGEMENT CHAIN (From CEO down to Supervisor) -->
                <?php if (!empty($ancestors)): ?>
                    <div class="ancestors-container">
                        <?php foreach ($ancestors as $sup): 
                            $sup_initials = getInitials($sup['full_name']);
                            $sup_role = strtolower($sup['role'] ?? 'employee');
                            $sup_gradient = 'linear-gradient(135deg, #00e07a, #06b6d4)';
                            if ($sup_role === 'admin') $sup_gradient = 'var(--grad-company)';
                            else if ($sup_role === 'manager') $sup_gradient = 'var(--grad-division)';
                        ?>
                            <div class="ancestor-card" onclick="window.location.href='org-chart.php?email=<?= urlencode($sup['email']) ?>'">
                                <div class="card-tier-indicator"><?= htmlspecialchars(ucfirst($sup['role'] ?? 'Employee')) ?></div>
                                
                                <?php if (!empty($sup['profile_image'])): ?>
                                    <img class="card-mini-avatar" src="<?= url('/uploads/' . htmlspecialchars($sup['profile_image'])) ?>" alt="Avatar">
                                <?php else: ?>
                                    <div class="card-mini-avatar" style="background: <?= $sup_gradient ?>;"><?= $sup_initials ?></div>
                                <?php endif; ?>
                                
                                <div class="card-mini-details">
                                    <span class="card-mini-name"><?= htmlspecialchars($sup['full_name']) ?></span>
                                    <span class="card-mini-title"><?= htmlspecialchars($sup['job_title'] ?? 'Manager') ?></span>
                                </div>
                            </div>
                            
                            <!-- Connecting vertical line -->
                            <div class="connector-line"></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- B. FOCUSED ACTIVE EMPLOYEE CARD -->
                <?php if ($selected_emp): 
                    $active_initials = getInitials($selected_emp['full_name']);
                    $active_role = strtolower($selected_emp['role'] ?? 'employee');
                    $active_gradient = 'linear-gradient(135deg, #00e07a, #06b6d4)';
                    if ($active_role === 'admin') $active_gradient = 'var(--grad-company)';
                    else if ($active_role === 'manager') $active_gradient = 'var(--grad-division)';
                ?>
                    <div class="active-focus-card">
                        <div class="active-card-top">
                            <div class="active-card-details">
                                <div class="active-card-name"><?= htmlspecialchars($selected_emp['full_name']) ?></div>
                                <div class="active-card-title"><?= htmlspecialchars($selected_emp['job_title'] ?? 'Staff') ?></div>
                                <div style="display: flex; gap: 6px; align-items: center; margin-top: 6px; flex-wrap: wrap;">
                                    <?php if (!empty($selected_emp['department'])): ?>
                                        <div class="active-card-dept"><?= htmlspecialchars($selected_emp['department']) ?></div>
                                    <?php endif; ?>
                                    <div class="active-card-role" style="display: inline-block; font-size: 0.725rem; padding: 2px 8px; border-radius: 4px; background: rgba(0, 224, 122, 0.1); border: 1px solid rgba(0, 224, 122, 0.25); color: #00e07a; text-transform: uppercase; font-weight: 600; font-family: 'Space Grotesk', sans-serif;"><?= htmlspecialchars($selected_emp['role'] ?? 'Employee') ?></div>
                                </div>
                            </div>
                            
                            <?php if (!empty($selected_emp['profile_image'])): ?>
                                <img class="active-card-avatar" src="<?= url('/uploads/' . htmlspecialchars($selected_emp['profile_image'])) ?>" alt="Avatar">
                            <?php else: ?>
                                <div class="active-card-avatar" style="background: <?= $active_gradient ?>;"><?= $active_initials ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Teams Interactive Command Icon Actions -->
                        <div class="active-card-actions">
                            <a href="mailto:<?= htmlspecialchars($selected_emp['email']) ?>" class="action-icon-btn" title="Send Email">
                                <i class="fa-solid fa-envelope"></i>
                            </a>
                            <button type="button" class="action-icon-btn" title="Send Message" onclick="alert('Starting mock chat with <?= htmlspecialchars(addslashes($selected_emp['full_name'])) ?>...')">
                                <i class="fa-solid fa-comment"></i>
                            </button>
                            <button type="button" class="action-icon-btn" title="Phone Call" onclick="alert('Calling phone of <?= htmlspecialchars(addslashes($selected_emp['full_name'])) ?>...')">
                                <i class="fa-solid fa-phone"></i>
                            </button>
                            <button type="button" class="action-icon-btn" title="Video Meeting" onclick="alert('Starting mock video session with <?= htmlspecialchars(addslashes($selected_emp['full_name'])) ?>...')">
                                <i class="fa-solid fa-video"></i>
                            </button>
                            <?php if (strtolower($user['role'] ?? '') === 'admin' || strtolower($user['role'] ?? '') === 'hr'): ?>
                                <button type="button" class="action-icon-btn" title="Edit Structure" style="color: #00e07a; border-color: rgba(0, 224, 122, 0.4);" onclick="document.getElementById('reassignModal').classList.add('active')">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- C. DIRECT REPORTS CONNECTOR LINE -->
                <?php if (!empty($direct_reports)): ?>
                    <div class="connector-line"></div>
                    
                    <div class="divider-container">
                        <div class="horizontal-divider-line"></div>
                        <div class="connector-line"></div>
                    </div>
                <?php endif; ?>

                <!-- D. DIRECT REPORTS CARD GRID -->
                <?php if (!empty($direct_reports)): ?>
                    <div class="reports-grid">
                        <?php foreach ($direct_reports as $child): 
                            $child_initials = getInitials($child['full_name']);
                            $child_role = strtolower($child['role'] ?? 'employee');
                            $child_gradient = 'linear-gradient(135deg, #00e07a, #06b6d4)';
                            if ($child_role === 'manager') $child_gradient = 'var(--grad-division)';
                            else if ($child_role === 'supervisor') $child_gradient = 'var(--grad-hub)';
                        ?>
                            <div class="report-card" onclick="window.location.href='org-chart.php?email=<?= urlencode($child['email']) ?>'">
                                <div class="card-tier-indicator"><?= htmlspecialchars(ucfirst($child['role'] ?? 'Employee')) ?></div>
                                
                                <?php if (!empty($child['profile_image'])): ?>
                                    <img class="card-mini-avatar" src="<?= url('/uploads/' . htmlspecialchars($child['profile_image'])) ?>" alt="Avatar">
                                <?php else: ?>
                                    <div class="card-mini-avatar" style="background: <?= $child_gradient ?>;"><?= $child_initials ?></div>
                                <?php endif; ?>
                                
                                <div class="card-mini-details">
                                    <span class="card-mini-name"><?= htmlspecialchars($child['full_name']) ?></span>
                                    <span class="card-mini-title"><?= htmlspecialchars($child['job_title'] ?? 'Associate') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="connector-line" style="height: 16px;"></div>
                    <div style="text-align: center; color: var(--text-muted); font-size: 0.85rem; background: rgba(0,0,0,0.1); padding: 12px 24px; border: 1px solid var(--border-color); border-radius: var(--radius-sm); margin-top: 8px;">
                        No direct reports report directly to this employee.
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Reassignment Modal -->
    <?php if ($selected_emp && (strtolower($user['role'] ?? '') === 'admin' || strtolower($user['role'] ?? '') === 'hr')): ?>
    <div id="reassignModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Edit Structure: <?= htmlspecialchars($selected_emp['full_name']) ?></h2>
                <button class="close-btn" onclick="document.getElementById('reassignModal').classList.remove('active')">&times;</button>
            </div>
            <form action="org-chart.php" method="POST">
                <input type="hidden" name="action" value="reassign_employee">
                <input type="hidden" name="target_email" value="<?= htmlspecialchars($selected_emp['email']) ?>">
                
                <div class="form-group">
                    <label class="form-label">Job Title</label>
                    <input type="text" name="job_title" class="form-input" value="<?= htmlspecialchars($selected_emp['job_title'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-input" value="<?= htmlspecialchars($selected_emp['department'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Immediate Supervisor</label>
                    <select name="immediate_supervisor" class="form-select">
                        <option value="">-- No Supervisor (Top Level) --</option>
                        <?php foreach ($all_employees as $emp): 
                            if ($emp['email'] === $selected_emp['email']) continue;
                            $selected = (strtolower($emp['email']) === strtolower($selected_emp['immediate_supervisor'] ?? '')) ? 'selected' : '';
                        ?>
                            <option value="<?= htmlspecialchars($emp['email']) ?>" <?= $selected ?>>
                                <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['job_title']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" class="form-input" style="background: #00e07a; color: #09090b; border: none; cursor: pointer; font-weight: 600;">Save Changes</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search Redirect Autocomplete Helper -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchBox = document.getElementById('searchBox');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                const val = this.value.trim().toLowerCase();
                const options = document.querySelectorAll('#employee-list option');
                
                for (let opt of options) {
                    if (opt.value.toLowerCase() === val) {
                        const email = opt.getAttribute('data-email');
                        window.location.href = `org-chart.php?email=${encodeURIComponent(email)}`;
                        break;
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
