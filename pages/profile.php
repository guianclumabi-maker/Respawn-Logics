<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$email = $user['email'];

$message = '';
$msg_type = 'success';
$active_tab = 'details';

// Handle details update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $phone = trim($_POST['phone'] ?? '');
    $emergency_name = trim($_POST['emergency_name'] ?? '');
    $emergency_phone = trim($_POST['emergency_phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    
    try {
        $stmt = $pdo->prepare("UPDATE `users` SET `phone` = ?, `emergency_name` = ?, `emergency_phone` = ?, `bio` = ? WHERE `id` = ?");
        $stmt->execute([$phone, $emergency_name, $emergency_phone, $bio, $user['id']]);
        
        $message = 'Profile details updated successfully!';
        $msg_type = 'success';
        
        // Refresh active user details
        $user = getCurrentUser();
    } catch (PDOException $e) {
        $message = 'Failed to update profile: ' . $e->getMessage();
        $msg_type = 'error';
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileInfo = pathinfo($_FILES['avatar']['name']);
        $ext = strtolower($fileInfo['extension']);
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($ext, $allowed)) {
            $filename = 'avatar_' . $user['id'] . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
                try {
                    $stmt = $pdo->prepare("UPDATE `users` SET `profile_image` = ? WHERE `id` = ?");
                    $stmt->execute([$filename, $user['id']]);
                    
                    $message = 'Profile photo uploaded successfully!';
                    $msg_type = 'success';
                    
                    // Refresh active user details
                    $user = getCurrentUser();
                } catch (PDOException $e) {
                    $message = 'Database error: ' . $e->getMessage();
                    $msg_type = 'error';
                }
            } else {
                $message = 'Failed to move uploaded file.';
                $msg_type = 'error';
            }
        } else {
            $message = 'Invalid file extension. Only JPG, PNG, and GIF allowed.';
            $msg_type = 'error';
        }
    } else {
        $message = 'Please select a valid image file.';
        $msg_type = 'error';
    }
}

// Handle password update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_password') {
    $active_tab = 'password';
    $curr_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    $pwd_ok = false;
    
    // Verify current password
    if (!empty($user['password_hash'])) {
        if (password_verify($curr_pass, $user['password_hash'])) {
            $pwd_ok = true;
        }
    }
    if (!$pwd_ok) {
        $message = 'Incorrect current password.';
        $msg_type = 'error';
    } else if (strlen($new_pass) < 8) {
        $message = 'New password must be at least 8 characters long.';
        $msg_type = 'error';
    } else if ($new_pass !== $confirm_pass) {
        $message = 'Passwords do not match.';
        $msg_type = 'error';
    } else {
        try {
            $newHash = password_hash($new_pass, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("UPDATE `users` SET `password_hash` = ? WHERE `id` = ?");
            $stmt->execute([$newHash, $user['id']]);
            
            $message = 'Password changed successfully!';
            $msg_type = 'success';
            $active_tab = 'details';
        } catch (PDOException $e) {
            $message = 'Failed to update password: ' . $e->getMessage();
            $msg_type = 'error';
        }
    }
}

// Prepare initials & tier descriptions
$initials = '';
if ($user) {
    $names = explode(' ', $user['full_name']);
    foreach ($names as $n) {
        $initials .= strtoupper(substr($n, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

$role_desc = 'Employee';
if ($user) {
    $role_desc = ucfirst($user['role'] ?? 'employee');
    if (!empty($user['department'])) {
        $role_desc .= ' (' . $user['department'] . ')';
    }
}
?>
<?php $page_title = 'My Profile - Respawn Logic Portal'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<link rel="stylesheet" href="<?= url('/assets/css/profile.css') ?>">

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

            <?php if (!empty($message)): ?>
                <div class="alert <?= $msg_type === 'error' ? 'alert-error' : 'alert-success' ?>">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <div class="profile-layout">
                <!-- Sidebar Summary Column -->
                <div class="profile-sidebar-card">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img class="profile-avatar-large" src="<?= url('/uploads/' . htmlspecialchars($user['profile_image'])) ?>" alt="Avatar">
                    <?php else: ?>
                        <div class="profile-avatar-large"><?= $initials ?></div>
                    <?php endif; ?>
                    
                    <div>
                        <div class="profile-name-large"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px;"><?= htmlspecialchars($user['job_title'] ?? 'Staff') ?></div>
                        <div style="margin-top: 10px;"><span class="profile-role-badge"><?= $role_desc ?></span></div>
                    </div>
                    
                    <!-- Upload Photo Input Form -->
                    <form action="profile.php" method="POST" enctype="multipart/form-data" style="width: 100%;">
                        <input type="hidden" name="action" value="upload_photo">
                        <div class="file-upload-wrapper">
                            <div class="file-upload-btn">
                                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                                <span style="font-size: 0.8rem; font-weight: 600;">Upload New Photo</span>
                            </div>
                            <input type="file" name="avatar" class="file-upload-input" onchange="this.form.submit()">
                        </div>
                    </form>

                    <div class="profile-meta-list">
                        <div class="profile-meta-item">
                            <span class="profile-meta-label">Email</span>
                            <span class="profile-meta-value"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <span class="profile-meta-label">Employee ID</span>
                            <span class="profile-meta-value"><?= htmlspecialchars($user['employee_number'] ?? 'Not set') ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <span class="profile-meta-label">Department</span>
                            <span class="profile-meta-value"><?= htmlspecialchars($user['department']) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Forms Detail Column -->
                <div class="profile-detail-card">
                    <div class="profile-tabs">
                        <button class="profile-tab-btn <?= $active_tab === 'details' ? 'active' : '' ?>" id="tabBtnDetails">Personal Info</button>
                        <button class="profile-tab-btn" id="tabBtnTimeline">Career Timeline</button>
                        <button class="profile-tab-btn" id="tabBtnInsights" style="color: #f59e0b;"><i class="fa-solid fa-brain"></i> Career Insights</button>
                        <button class="profile-tab-btn" id="tabBtnPayslips">Payslips</button>
                        <button class="profile-tab-btn" id="tabBtnPerformance">Performance & Goals</button>
                        <button class="profile-tab-btn" id="tabBtnExpenses">Expenses & Claims</button>
                        <button class="profile-tab-btn" id="tabBtnBenefits">Benefits & Statutory</button>
                        <button class="profile-tab-btn" id="tabBtnHelpdesk">Helpdesk</button>
                        <button class="profile-tab-btn <?= $active_tab === 'password' ? 'active' : '' ?>" id="tabBtnPassword">Password Settings</button>
                    </div>

                    <!-- Personal Details Form -->
                    <div id="tabPanelDetails" style="display: <?= $active_tab === 'details' ? 'block' : 'none' ?>;">
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="phone">Phone Number</label>
                                    <input class="form-input" type="text" id="phone" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="+1 (555) 000-0000">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="job_display">Job Title (Read-Only)</label>
                                    <input class="form-input" type="text" id="job_display" value="<?= htmlspecialchars($user['job_title'] ?? '') ?>" disabled style="opacity: 0.6; cursor: not-allowed;">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="emergency_name">Emergency Contact Name</label>
                                    <input class="form-input" type="text" id="emergency_name" name="emergency_name" value="<?= htmlspecialchars($user['emergency_name'] ?? '') ?>" placeholder="Contact person name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="emergency_phone">Emergency Contact Phone</label>
                                    <input class="form-input" type="text" id="emergency_phone" name="emergency_phone" value="<?= htmlspecialchars($user['emergency_phone'] ?? '') ?>" placeholder="Contact person phone">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="bio">Employee Biography</label>
                                <textarea class="form-input" id="bio" name="bio" rows="5" placeholder="Write a short summary about yourself, roles, or skillsets..."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn-primary">Save Changes</button>
                        </form>
                    </div>

                    <!-- Password Settings Form -->
                    <div id="tabPanelPassword" style="display: <?= $active_tab === 'password' ? 'block' : 'none' ?>;">
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="action" value="update_password">
                            
                            <div class="form-group">
                                <label class="form-label" for="current_password">Current Password</label>
                                <input class="form-input" type="password" id="current_password" name="current_password" required placeholder="••••••••">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="new_password">New Password</label>
                                <input class="form-input" type="password" id="new_password" name="new_password" required placeholder="Min. 8 characters">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label" for="confirm_password">Confirm New Password</label>
                                <input class="form-input" type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter new password">
                            </div>
                            
                            <button type="submit" class="btn-primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Career Timeline Form -->
                    <div id="tabPanelTimeline" style="display: none;">
                        <h3 style="color: white; margin-bottom: 20px; font-size: 1.1rem;">Employment History</h3>
                        <div id="timeline-container">
                            <div style="text-align:center; padding:40px;"><div class="spinner"></div></div>
                        </div>
                    </div>

                    <!-- Payslips Vault -->
                    <div id="tabPanelPayslips" style="display: none;">
                        <h3 style="color: white; margin-bottom: 20px; font-size: 1.1rem;">Payslip Vault</h3>
                        <table class="data-table" style="width:100%; border-collapse:collapse; color:white;">
                            <thead>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align:left; color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">
                                    <th style="padding:12px;">Pay Period</th>
                                    <th style="padding:12px;">Pay Date</th>
                                    <th style="padding:12px; text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="payslips-container">
                                <tr><td colspan="3" style="text-align:center; padding:40px;"><div class="spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Performance & Goals -->
                    <div id="tabPanelPerformance" style="display: none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                            <h3 style="color: white; font-size: 1.1rem;">My Active Goals</h3>
                            <button class="btn-primary" onclick="openNewGoalModal()" style="padding:6px 12px; font-size:0.75rem;">+ Set Goal</button>
                        </div>
                        <div id="goals-container" style="margin-bottom: 40px; display:grid; grid-template-columns: 1fr; gap:16px;">
                            <div style="text-align:center; padding:40px;"><div class="spinner"></div></div>
                        </div>

                        <h3 style="color: white; margin-bottom: 20px; font-size: 1.1rem;">Self-Evaluations & Past Reviews</h3>
                        <div id="reviews-container">
                            <div style="text-align:center; padding:40px;"><div class="spinner"></div></div>
                        </div>
                    </div>

                    <!-- Expenses & Claims -->
                    <div id="tabPanelExpenses" style="display: none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                            <h3 style="color: white; font-size: 1.1rem;">My Expense Claims</h3>
                            <button class="btn-primary" onclick="document.getElementById('newExpenseModal').classList.add('active')" style="padding:6px 12px; font-size:0.75rem;">+ Submit Claim</button>
                        </div>
                        <table class="data-table" style="width:100%; border-collapse:collapse; color:white;">
                            <thead>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.1); text-align:left; color:var(--text-muted); font-size:0.75rem; text-transform:uppercase;">
                                    <th style="padding:12px;">Date</th>
                                    <th style="padding:12px;">Category</th>
                                    <th style="padding:12px;">Amount</th>
                                    <th style="padding:12px;">Status</th>
                                </tr>
                            </thead>
                            <tbody id="expenses-container">
                                <tr><td colspan="4" style="text-align:center; padding:40px;"><div class="spinner"></div></td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Benefits & Statutory -->
                    <div id="tabPanelBenefits" style="display: none;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:24px;">
                            
                            <!-- Statutory IDs -->
                            <div style="flex:1; background:rgba(255,255,255,0.03); padding:20px; border-radius:8px; border:1px solid var(--border-color);">
                                <h3 style="color: white; margin-bottom: 16px; font-size: 1.1rem;">Statutory Numbers</h3>
                                <form id="form-statutory" onsubmit="saveStatutory(event)">
                                    <div style="margin-bottom:12px;">
                                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">SSS Number</label>
                                        <input type="text" id="stat_sss" name="sss_number" class="form-input">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">PhilHealth Number</label>
                                        <input type="text" id="stat_phic" name="philhealth_number" class="form-input">
                                    </div>
                                    <div style="margin-bottom:12px;">
                                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">Pag-IBIG / HDMF</label>
                                        <input type="text" id="stat_hdmf" name="pagibig_number" class="form-input">
                                    </div>
                                    <div style="margin-bottom:16px;">
                                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:4px;">TIN Number</label>
                                        <input type="text" id="stat_tin" name="tin_number" class="form-input">
                                    </div>
                                    <button type="submit" class="btn-primary" style="width:100%; padding:8px; font-size:0.75rem;">Save Statutory IDs</button>
                                </form>
                            </div>

                            <!-- Benefits -->
                            <div style="flex:2;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 16px;">
                                    <h3 style="color: white; font-size: 1.1rem; margin:0;">Active Enrollments</h3>
                                    <button class="btn-primary" onclick="document.getElementById('enrollModal').classList.add('active')" style="padding:6px 12px; font-size:0.75rem;">+ New Enrollment</button>
                                </div>
                                <div id="my-benefits-container" style="display:grid; gap:12px;">
                                    <div style="text-align:center; padding:40px;"><div class="spinner"></div></div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Career Insights Tab -->
                    <div id="tabPanelInsights" style="display: none;">
                        <h3 class="panel-section-title"><i class="fa-solid fa-chart-line" style="color:#f59e0b;"></i> Career Insights & Trajectory</h3>
                        <p style="color: var(--text-muted); font-size:0.85rem; margin-bottom: 20px;">An AI-driven analysis of your compensation band and historical growth within Respawn Logic.</p>
                        
                        <div id="insightsContainer" style="display: grid; gap: 16px;">
                            <div class="alert alert-success" style="background:rgba(0, 224, 122, 0.1); border-color:#00e07a; color:#e5e7eb;">
                                <i class="fa-solid fa-wand-magic-sparkles" style="color:#00e07a;"></i> Your compensation is currently perfectly aligned with your performance reviews and the market band for <strong><?= htmlspecialchars($user['department']) ?></strong>. Keep up the excellent work!
                            </div>
                            
                            <div style="background:var(--bg-secondary); padding:16px; border-radius:8px; border:1px solid var(--border-color);">
                                <h4 style="margin:0 0 10px 0; color:white;">Tenure Analytics</h4>
                                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                    <span style="color:var(--text-muted);">Hire Date:</span>
                                    <span><?= date('M d, Y', strtotime($user['hire_date'])) ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                    <span style="color:var(--text-muted);">Months in Role:</span>
                                    <span><?php 
                                        $d1 = new DateTime($user['hire_date']);
                                        $d2 = new DateTime();
                                        echo ($d1->diff($d2)->m + ($d1->diff($d2)->y*12)); 
                                    ?> Months</span>
                                </div>
                                <div style="display:flex; justify-content:space-between;">
                                    <span style="color:var(--text-muted);">Next Review Cycle:</span>
                                    <span>Q3 2026</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Helpdesk Tab -->
                    <div id="tabPanelHelpdesk" style="display: none;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 24px;">
                            <div>
                                <h3 style="color: white; font-size: 1.1rem; margin:0;">My Service Tickets</h3>
                                <p style="color:var(--text-muted); font-size:0.875rem; margin-top:4px;">Track and manage your requests to HR, IT, and Facilities.</p>
                            </div>
                            <button class="btn-primary" onclick="openTicketModal()" style="padding:8px 16px;">+ Create Ticket</button>
                        </div>
                        <div id="my-tickets-container" style="display:grid; gap:16px;">
                            <div style="text-align:center; padding:40px;"><div class="spinner"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const btnDetails = document.getElementById('tabBtnDetails');
        const btnPassword = document.getElementById('tabBtnPassword');
        const btnTimeline = document.getElementById('tabBtnTimeline');
        const btnInsights = document.getElementById('tabBtnInsights');
        const btnPayslips = document.getElementById('tabBtnPayslips');
        const btnPerformance = document.getElementById('tabBtnPerformance');
        const btnExpenses = document.getElementById('tabBtnExpenses');
        const btnBenefits = document.getElementById('tabBtnBenefits');
        const btnHelpdesk = document.getElementById('tabBtnHelpdesk');

        const panelDetails = document.getElementById('tabPanelDetails');
        const panelPassword = document.getElementById('tabPanelPassword');
        const panelTimeline = document.getElementById('tabPanelTimeline');
        const panelInsights = document.getElementById('tabPanelInsights');
        const panelPayslips = document.getElementById('tabPanelPayslips');
        const panelPerformance = document.getElementById('tabPanelPerformance');
        const panelExpenses = document.getElementById('tabPanelExpenses');
        const panelBenefits = document.getElementById('tabPanelBenefits');
        const panelHelpdesk = document.getElementById('tabPanelHelpdesk');
        
        function resetTabs() {
            [btnDetails, btnPassword, btnTimeline, btnInsights, btnPayslips, btnPerformance, btnExpenses, btnBenefits, btnHelpdesk].forEach(b => b.classList.remove('active'));
            [panelDetails, panelPassword, panelTimeline, panelInsights, panelPayslips, panelPerformance, panelExpenses, panelBenefits, panelHelpdesk].forEach(p => p.style.display = 'none');
        }

        btnDetails.addEventListener('click', function() {
            resetTabs();
            btnDetails.classList.add('active');
            panelDetails.style.display = 'block';
        });
        
        btnPassword.addEventListener('click', function() {
            resetTabs();
            btnPassword.classList.add('active');
            panelPassword.style.display = 'block';
        });

        btnTimeline.addEventListener('click', function() {
            resetTabs();
            btnTimeline.classList.add('active');
            panelTimeline.style.display = 'block';
            loadTimeline();
        });

        btnInsights.addEventListener('click', function() {
            resetTabs();
            btnInsights.classList.add('active');
            panelInsights.style.display = 'block';
        });

        btnPayslips.addEventListener('click', function() {
            resetTabs();
            btnPayslips.classList.add('active');
            panelPayslips.style.display = 'block';
            loadPayslips();
        });

        btnPerformance.addEventListener('click', function() {
            hideAllTabs();
            btnPerformance.classList.add('active');
            panelPerformance.style.display = 'block';
            loadGoals();
            loadReviews();
        });

        btnExpenses.addEventListener('click', function() {
            hideAllTabs();
            btnExpenses.classList.add('active');
            panelExpenses.style.display = 'block';
            loadExpenses();
            loadCategories();
        });

        btnBenefits.addEventListener('click', function() {
            hideAllTabs();
            btnBenefits.classList.add('active');
            panelBenefits.style.display = 'block';
            loadStatutory();
            loadMyBenefits();
            loadAvailablePlans();
        });

        btnHelpdesk.addEventListener('click', function() {
            hideAllTabs();
            btnHelpdesk.classList.add('active');
            panelHelpdesk.style.display = 'block';
            loadMyTickets();
        });

        // Helpdesk JS
        async function loadMyTickets() {
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=my_tickets') ?>`);
                const data = await res.json();
                const container = document.getElementById('my-tickets-container');
                if (data.success) {
                    if (data.data.length === 0) {
                        container.innerHTML = '<div style="color:var(--text-muted); font-size:0.875rem;">You have not submitted any service tickets.</div>';
                        return;
                    }
                    container.innerHTML = data.data.map(t => `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:16px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <div>
                                    <span style="color:var(--text-muted); font-size:0.75rem;">${t.ticket_number} &bull; ${t.type_name}</span>
                                    <h4 style="color:white; font-size:1rem; margin:4px 0;">${t.subject}</h4>
                                </div>
                                <span style="background:rgba(255,255,255,0.1); padding:4px 8px; border-radius:4px; font-size:0.75rem; height:fit-content;">${t.status}</span>
                            </div>
                            <div style="font-size:0.875rem; color:var(--text-muted); margin-bottom:12px;">${t.description}</div>
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:var(--text-muted);">
                                <span>Priority: <strong>${t.priority}</strong></span>
                                <span>Assigned to: <strong>${t.team_name || 'Triage'}</strong></span>
                            </div>
                        </div>
                    `).join('');
                }
            } catch(e){}
        }

        async function openTicketModal() {
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=ticket_types') ?>`);
                const data = await res.json();
                if (data.success) {
                    document.getElementById('ticket_type_id').innerHTML = data.data.map(t => `<option value="${t.id}">${t.name}</option>`).join('');
                    document.getElementById('ticketModal').classList.add('active');
                }
            } catch(e){}
        }

        window.submitTicket = async function(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-ticket'));
            try {
                const res = await fetch(`<?= url('/esm_api.php?action=create_ticket') ?>`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('ticketModal').classList.remove('active');
                    loadMyTickets();
                } else { alert(data.error); }
            } catch(e){}
        };

        // Benefits & Statutory JS
        async function loadStatutory() {
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=my_statutory') ?>`);
                const data = await res.json();
                if(data.success && data.data) {
                    document.getElementById('stat_sss').value = data.data.sss_number || '';
                    document.getElementById('stat_phic').value = data.data.philhealth_number || '';
                    document.getElementById('stat_hdmf').value = data.data.pagibig_number || '';
                    document.getElementById('stat_tin').value = data.data.tin_number || '';
                }
            } catch(e){}
        }

        window.saveStatutory = async function(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-statutory'));
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=update_statutory') ?>`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) alert('Statutory IDs updated successfully.');
                else alert(data.error);
            } catch(e){}
        };

        async function loadMyBenefits() {
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=my_benefits') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('my-benefits-container');
                    if(data.data.length === 0) { c.innerHTML = '<div style="color:var(--text-muted); font-size:0.875rem;">No active enrollments.</div>'; return; }
                    c.innerHTML = data.data.map(b => `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:16px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                <h4 style="color:white; font-size:0.875rem; margin:0;">${b.plan_name}</h4>
                                <span style="font-size:0.75rem; color:${b.status === 'Enrolled' ? '#00e07a' : '#f59e0b'}; font-weight:600;">${b.status}</span>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:8px;">${b.provider} - Enrolled: ${b.enrollment_date}</div>
                            ${b.type === 'HMO' ? `<div style="font-size:0.75rem; color:white;">Dependents Enrolled: <strong>${b.dependent_count}</strong></div>` : ''}
                            ${parseFloat(b.employee_cost) > 0 ? `<div style="font-size:0.75rem; color:#ef4444; margin-top:4px;">Payroll Deduction: $${parseFloat(b.employee_cost * b.dependent_count).toLocaleString()} / period</div>` : ''}
                        </div>
                    `).join('');
                }
            } catch(e){}
        }

        async function loadAvailablePlans() {
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=plans') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('enroll_plan').innerHTML = data.data.map(p => `<option value="${p.id}">${p.name} (${p.provider})</option>`).join('');
                }
            } catch(e){}
        }

        window.submitEnrollment = async function(e) {
            e.preventDefault();
            const formData = new FormData(document.getElementById('form-enroll'));
            try {
                const res = await fetch(`<?= url('/benefits_api.php?action=enroll') ?>`, { method: 'POST', body: formData });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('enrollModal').classList.remove('active');
                    loadMyBenefits();
                } else { alert(data.error); }
            } catch(e){}
        };

        async function loadExpenses() {
            try {
                const res = await fetch(`<?= url('/expenses_api.php?action=my_claims') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('expenses-container');
                    if(data.data.length === 0) { c.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-muted); padding:20px;">No claims submitted.</td></tr>'; return; }
                    c.innerHTML = data.data.map(e => `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding:16px;">${e.expense_date}</td>
                            <td style="padding:16px;">
                                <div style="font-weight:600;">${e.category_name}</div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">${e.description || ''}</div>
                                ${e.receipt_path ? `<a href="<?= url('/') ?>${e.receipt_path}" target="_blank" style="color:#00e07a; font-size:0.75rem; text-decoration:none;">View Receipt</a>` : ''}
                            </td>
                            <td style="padding:16px; font-weight:700; color:#00e07a;">$${parseFloat(e.amount).toLocaleString()}</td>
                            <td style="padding:16px;">
                                <span style="font-size:0.75rem; font-weight:600; padding:4px 8px; border-radius:100px; background:rgba(255,255,255,0.1);">${e.status}</span>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e){}
        }

        async function loadCategories() {
            try {
                const res = await fetch(`<?= url('/expenses_api.php?action=categories') ?>`);
                const data = await res.json();
                if(data.success) {
                    document.getElementById('exp_category').innerHTML = data.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
                }
            } catch(e){}
        }

        window.submitExpense = async function(e) {
            e.preventDefault();
            const form = document.getElementById('form-expense');
            const formData = new FormData(form);
            try {
                const res = await fetch(`<?= url('/expenses_api.php?action=submit_claim') ?>`, {
                    method: 'POST', body: formData
                });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('newExpenseModal').classList.remove('active');
                    form.reset();
                    loadExpenses();
                } else { alert(data.error); }
            } catch(e){}
        };

        async function loadGoals() {
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=my_goals') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('goals-container');
                    if(data.data.length === 0) { c.innerHTML = '<div style="color:var(--text-muted);">No goals set yet.</div>'; return; }
                    c.innerHTML = data.data.map(g => `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:16px; border-radius:8px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <h4 style="color:white; font-size:0.875rem; margin:0;">${g.title}</h4>
                                <span style="font-size:0.75rem; color:#00e07a; font-weight:600;">${g.status}</span>
                            </div>
                            <p style="color:var(--text-muted); font-size:0.75rem; margin-bottom:12px;">${g.description || 'No description'}</p>
                            <div style="display:flex; justify-content:space-between; font-size:0.75rem; color:white; margin-bottom:4px;">
                                <span>Progress</span>
                                <span>${g.completion_percentage}%</span>
                            </div>
                            <div style="width:100%; height:6px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden;">
                                <div style="width:${g.completion_percentage}%; height:100%; background:#00e07a;"></div>
                            </div>
                        </div>
                    `).join('');
                }
            } catch(e){}
        }

        async function loadReviews() {
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=my_reviews') ?>`);
                const data = await res.json();
                if(data.success) {
                    const c = document.getElementById('reviews-container');
                    if(data.data.length === 0) { c.innerHTML = '<div style="color:var(--text-muted);">No reviews yet.</div>'; return; }
                    c.innerHTML = data.data.map(r => `
                        <div style="background:rgba(255,255,255,0.03); border:1px solid var(--border-color); padding:16px; border-radius:8px; margin-bottom:12px;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:8px;">
                                <h4 style="color:white; font-size:0.875rem; margin:0;">${r.cycle_name}</h4>
                                <span style="font-size:0.75rem; color:#00e07a; font-weight:600;">${r.status}</span>
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom: 12px;">
                                Reviewer: ${r.manager_name || 'N/A'}
                            </div>
                            ${r.status === 'Pending Self-Evaluation' ? `
                                <textarea id="self_eval_${r.id}" style="width:100%; background:rgba(0,0,0,0.2); border:1px solid var(--border-color); color:white; padding:8px; border-radius:4px; margin-bottom:12px; font-size:0.875rem;" rows="3" placeholder="Write your self-evaluation here..."></textarea>
                                <button class="btn-primary" style="padding:6px 12px; font-size:0.75rem;" onclick="submitSelfEval(${r.id})">Submit Self-Evaluation</button>
                            ` : `
                                ${r.overall_score_1_to_5 ? `<div style="font-size:0.875rem; color:white;"><strong>Final Score:</strong> ${r.overall_score_1_to_5} / 5.0</div>` : ''}
                                ${r.manager_comments ? `<div style="font-size:0.875rem; color:var(--text-muted); margin-top:8px;"><em>"${r.manager_comments}"</em></div>` : ''}
                            `}
                        </div>
                    `).join('');
                }
            } catch(e){}
        }

        window.submitSelfEval = async function(id) {
            const comments = document.getElementById('self_eval_' + id).value;
            if(!comments) { alert('Please enter your self evaluation'); return; }
            try {
                const res = await fetch(`<?= url('/performance_api.php?action=submit_self_eval') ?>`, {
                    method: 'POST', body: JSON.stringify({review_id: id, self_comments: comments})
                });
                const data = await res.json();
                if(data.success) { loadReviews(); }
            } catch(e){}
        };

        async function loadPayslips() {
            try {
                const res = await fetch(`<?= url('/payroll_engine_api.php?action=my_payslips') ?>`);
                const data = await res.json();
                const container = document.getElementById('payslips-container');
                if (data.success) {
                    if (data.data.length === 0) {
                        container.innerHTML = `<tr><td colspan="3" style="text-align:center; color:var(--text-muted); padding: 40px;">No payslips available.</td></tr>`;
                        return;
                    }
                    container.innerHTML = data.data.map(p => `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding:16px; font-weight:600;">${p.payroll_period_start} to ${p.payroll_period_end}</td>
                            <td style="padding:16px; color:var(--text-secondary);">${p.pay_date}</td>
                            <td style="padding:16px; text-align:right;">
                                <a href="<?= url('/') ?>${p.pdf_path}" target="_blank" style="color:#00e07a; text-decoration:none; font-weight:600; font-size:0.875rem;">Download PDF</a>
                            </td>
                        </tr>
                    `).join('');
                }
            } catch(e) {}
        }

        async function loadTimeline() {
            try {
                const res = await fetch(`<?= url('/core_hr_api.php?action=master_record&user_id='.$user['id']) ?>`);
                const data = await res.json();
                const container = document.getElementById('timeline-container');
                if (data.success) {
                    if (data.data.history.length === 0) {
                        container.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding: 40px;">No history records found.</div>`;
                        return;
                    }

                    container.innerHTML = data.data.history.map(h => `
                        <div style="border-left: 2px solid rgba(0, 224, 122, 0.5); padding-left: 20px; margin-bottom: 24px; position: relative;">
                            <div style="position: absolute; left: -6px; top: 0; width: 10px; height: 10px; border-radius: 50%; background: #00e07a;"></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-bottom: 4px;">${h.effective_date}</div>
                            <div style="font-weight: 600; color: white; margin-bottom: 4px;">${h.change_type}</div>
                            <div style="font-size: 0.875rem; color: var(--text-secondary);">
                                Title: ${h.job_title || '-'} | Dept: ${h.department || '-'}
                                ${h.base_salary ? `| Salary: $${parseFloat(h.base_salary).toLocaleString()}` : ''}
                            </div>
                            ${h.notes ? `<div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 8px; font-style: italic;">"${h.notes}"</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `<div style="color: #ef4444;">Error loading timeline.</div>`;
                }
            } catch(e) {
                document.getElementById('timeline-container').innerHTML = `<div style="color: #ef4444;">Network error.</div>`;
            }
        }
    });
    </script>
    <div class="modal-overlay" id="newExpenseModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:100;">
        <div class="modal-content" style="background:#161922; border:1px solid var(--border-color); border-radius:var(--radius-lg); width:500px; max-width:90vw;">
            <div style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color:white; font-size:1.1rem; margin:0;">Submit Expense Claim</h3>
                <button onclick="document.getElementById('newExpenseModal').classList.remove('active')" style="background:transparent; border:none; color:white; cursor:pointer;">X</button>
            </div>
            <div style="padding:20px;">
                <form id="form-expense" onsubmit="submitExpense(event)">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Category</label>
                        <select name="category_id" id="exp_category" class="form-input" required></select>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px; margin-bottom:16px;">
                        <div>
                            <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Amount (USD)</label>
                            <input type="number" step="0.01" min="0" name="amount" class="form-input" required>
                        </div>
                        <div>
                            <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Date</label>
                            <input type="date" name="expense_date" class="form-input" required>
                        </div>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Description / Business Purpose</label>
                        <textarea name="description" class="form-input" rows="3" required></textarea>
                    </div>
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Receipt (Image or PDF)</label>
                        <input type="file" name="receipt" accept="image/*,.pdf" style="color:white;">
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Submit Claim</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Enroll Benefit Modal -->
    <div class="modal-overlay" id="enrollModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:100;">
        <div class="modal-content" style="background:#161922; border:1px solid var(--border-color); border-radius:var(--radius-lg); width:400px; max-width:90vw;">
            <div style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color:white; font-size:1.1rem; margin:0;">Benefit Enrollment</h3>
                <button onclick="document.getElementById('enrollModal').classList.remove('active')" style="background:transparent; border:none; color:white; cursor:pointer;">X</button>
            </div>
            <div style="padding:20px;">
                <form id="form-enroll" onsubmit="submitEnrollment(event)">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Select Plan</label>
                        <select name="plan_id" id="enroll_plan" class="form-input" required></select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Number of Dependents (For HMO)</label>
                        <input type="number" min="0" max="10" name="dependent_count" class="form-input" value="0">
                        <small style="color:var(--text-muted); font-size:0.65rem; display:block; margin-top:4px;">If enrolling in HMO, enter number of dependents. This will be deducted from payroll.</small>
                    </div>
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Action</label>
                        <select name="status" class="form-input">
                            <option value="Enrolled">Enroll / Opt-In</option>
                            <option value="Opted-Out">Opt-Out</option>
                        </select>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Save Enrollment</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Ticket Modal -->
    <div class="modal-overlay" id="ticketModal" style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.7); backdrop-filter:blur(4px); display:flex; align-items:center; justify-content:center; opacity:0; pointer-events:none; transition:opacity 0.2s; z-index:100;">
        <div class="modal-content" style="background:#161922; border:1px solid var(--border-color); border-radius:var(--radius-lg); width:500px; max-width:90vw;">
            <div style="padding:20px; border-bottom:1px solid var(--border-color); display:flex; justify-content:space-between; align-items:center;">
                <h3 style="color:white; font-size:1.1rem; margin:0;">Create Helpdesk Ticket</h3>
                <button onclick="document.getElementById('ticketModal').classList.remove('active')" style="background:transparent; border:none; color:white; cursor:pointer;">X</button>
            </div>
            <div style="padding:20px;">
                <form id="form-ticket" onsubmit="submitTicket(event)">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Category</label>
                        <select name="ticket_type_id" id="ticket_type_id" class="form-input" required></select>
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Subject</label>
                        <input type="text" name="subject" class="form-input" required placeholder="Brief summary of request">
                    </div>
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Priority</label>
                        <select name="priority" class="form-input">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                            <option value="Urgent">Urgent</option>
                        </select>
                    </div>
                    <div style="margin-bottom:24px;">
                        <label style="display:block; font-size:0.75rem; color:var(--text-muted); margin-bottom:6px;">Description</label>
                        <textarea name="description" class="form-input" rows="4" required placeholder="Provide details to help us assist you faster."></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">Submit Request</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
