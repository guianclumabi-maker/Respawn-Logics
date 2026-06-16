<?php
require_once __DIR__ . '/../bootstrap/app.php';
$user = getCurrentUser();
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Helper to get initials
$initials = '';
if ($user) {
    $names = explode(' ', $user['full_name']);
    foreach ($names as $n) {
        $initials .= strtoupper(substr($n, 0, 1));
    }
    $initials = substr($initials, 0, 2);
}

// Generate role and department string description
$role_desc = 'Staff';
if ($user) {
    $role_desc = ucfirst($user['role'] ?? 'employee');
    if (!empty($user['department'])) {
        $role_desc .= ' (' . $user['department'] . ')';
    }
}
?>
<div class="app-sidebar">
    <div class="sidebar-brand">
        <?= renderLogo('sidebar') ?>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-group">
            <div class="group-title">Workspace</div>
            
            <a href="<?= url('/pages/dashboard.php') ?>" class="menu-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-table-cells-large"></i>
                <span>Dashboard</span>
            </a>

            <?php if (tenantModuleEnabled('announcements')): ?>
            <a href="<?= url('/pages/announcements.php') ?>" class="menu-item <?= $current_page === 'announcements.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-bullhorn"></i>
                <span>Company Feed</span>
            </a>
            <?php endif; ?>

            <?php if (tenantModuleEnabled('surveys')): ?>
            <a href="<?= url('/pages/surveys.php') ?>" class="menu-item <?= $current_page === 'surveys.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-simple"></i>
                <span>Engagement Surveys</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/ai_companion.php') ?>" class="menu-item <?= $current_page === 'ai_companion.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
                <span>AI Companion</span>
            </a>
            
            <?php if (hasPermission('attendance.view') && tenantModuleEnabled('attendance')): ?>
            <a href="<?= url('/pages/attendance.php') ?>" class="menu-item <?= $current_page === 'attendance.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-clock"></i>
                <span>Attendance Tracking</span>
            </a>
            <?php endif; // end attendance ?>

            <?php if (hasPermission('shifts.manage') && tenantModuleEnabled('shifts')): ?>
            <a href="<?= url('/pages/scheduling.php') ?>" class="menu-item <?= $current_page === 'scheduling.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Shift Scheduler</span>
            </a>
            <?php endif; // end shifts ?>
            
            <?php if ((hasPermission('leave.view') || hasPermission('leave.request')) && tenantModuleEnabled('leave')): ?>
            <a href="<?= url('/pages/leaves.php') ?>" class="menu-item <?= $current_page === 'leaves.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-calendar-check"></i>
                <span>Leave Requests</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/org-chart.php') ?>" class="menu-item <?= $current_page === 'org-chart.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-sitemap"></i>
                <span>Org Chart Directory</span>
            </a>

            <?php if (hasPermission('users.manage') && tenantModuleEnabled('onboarding')): ?>
            <a href="<?= url('/pages/onboarding_admin.php') ?>" class="menu-item <?= $current_page === 'onboarding_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-bolt-lightning"></i>
                <span>Onboarding</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('assets.manage') && tenantModuleEnabled('assets')): ?>
            <a href="<?= url('/pages/assets.php') ?>" class="menu-item <?= $current_page === 'assets.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-laptop"></i>
                <span>Asset Management</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('intelligence.view') && tenantModuleEnabled('intelligence')): ?>
            <a href="<?= url('/pages/intelligence.php') ?>" class="menu-item <?= $current_page === 'intelligence.php' ? 'active' : '' ?>" style="color: #f59e0b;">
                <i class="fa-solid fa-brain"></i>
                <span style="font-weight: 600;">Predictive AI</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('ats.view') && tenantModuleEnabled('ats')): ?>
            <a href="<?= url('/frontend/dist/index.html#/ats') ?>" class="menu-item">
                <i class="fa-solid fa-crosshairs"></i>
                <span>Recruitment / ATS</span>
            </a>
            <?php endif; ?>
            
            <?php if (tenantModuleEnabled('elr')): ?>
            <a href="<?= url('/pages/elr_portal.php') ?>" class="menu-item <?= $current_page === 'elr_portal.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>My HR Cases</span>
            </a>
            <?php endif; ?>

            <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null && tenantModuleEnabled('esm')): ?>
            <?php 
            $esmLink = hasPermission('esm.manage') ? '/pages/esm_admin.php' : '/pages/esm_employee.php';
            ?>
            <a href="<?= url($esmLink) ?>" class="menu-item <?= ($current_page === 'esm_admin.php' || $current_page === 'esm_employee.php') ? 'active' : '' ?>">
                <i class="fa-solid fa-headset"></i>
                <span>IT/HR Service Desk</span>
            </a>
            <?php endif; ?>
        </div>

        <?php if (hasPermission('users.view') || hasPermission('settings.manage')): ?>
        <div class="menu-group">
            <div class="group-title">Administration</div>
            
            <?php if (hasPermission('analytics.view') && tenantModuleEnabled('analytics')): ?>
            <a href="<?= url('/pages/analytics.php') ?>" class="menu-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-chart-pie"></i>
                <span>Workforce Analytics</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('users.manage') || hasPermission('shifts.manage')): ?>
            <a href="<?= url('/pages/hr_directory.php') ?>" class="menu-item <?= $current_page === 'hr_directory.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-users"></i>
                <span>Employee Directory</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('payroll.manage') && tenantModuleEnabled('payroll')): ?>
            <a href="<?= url('/payroll-frontend/dist/index.html') ?>" class="menu-item">
                <i class="fa-solid fa-peso-sign"></i>
                <span>Payroll Engine</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('compensation.manage')): ?>
            <a href="<?= url('/pages/compensation_admin.php') ?>" class="menu-item <?= $current_page === 'compensation_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-scale-balanced"></i>
                <span>Compensation &amp; Equity</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('performance.manage') && tenantModuleEnabled('performance')): ?>
            <a href="<?= url('/pages/performance_admin.php') ?>" class="menu-item <?= $current_page === 'performance_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-ranking-star"></i>
                <span>Performance</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('expenses.manage') && tenantModuleEnabled('expenses')): ?>
            <a href="<?= url('/pages/expenses_admin.php') ?>" class="menu-item <?= $current_page === 'expenses_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-receipt"></i>
                <span>Expenses &amp; Claims</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('benefits.manage') && tenantModuleEnabled('benefits')): ?>
            <a href="<?= url('/pages/benefits_admin.php') ?>" class="menu-item <?= $current_page === 'benefits_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gift"></i>
                <span>Benefits &amp; HMO</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('elr.view') && tenantModuleEnabled('elr')): ?>
            <a href="<?= url('/employee-relations-dist/dist/index.html') ?>" class="menu-item <?= $current_page === 'index.html' ? 'active' : '' ?>" style="color:#ef4444;">
                <i class="fa-solid fa-gavel"></i>
                <span style="font-weight:600;">ELR Admin Console</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('users.view')): ?>
            <a href="<?= url('/pages/admin_users.php') ?>" class="menu-item <?= $current_page === 'admin_users.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-gear"></i>
                <span>Users</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('users.manage')): ?>
            <a href="<?= url('/pages/admin_roles.php') ?>" class="menu-item <?= $current_page === 'admin_roles.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-shield-halved"></i>
                <span>Roles &amp; Permissions</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.manage')): ?>
            <a href="<?= url('/pages/tenant_settings.php') ?>" class="menu-item <?= $current_page === 'tenant_settings.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear"></i>
                <span>Tenant Settings</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.manage')): ?>
            <a href="<?= url('/pages/knowledge_admin.php') ?>" class="menu-item <?= $current_page === 'knowledge_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-book-open"></i>
                <span>Knowledge Base Review</span>
            </a>
            <?php endif; ?>

            <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null): ?>
            <a href="<?= url('/pages/admin_platform_support.php') ?>" class="menu-item <?= $current_page === 'admin_platform_support.php' ? 'active' : '' ?>" style="color:#00e07a;">
                <i class="fa-solid fa-satellite-dish"></i>
                <span style="font-weight:600;">Platform Support</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (hasRole(['Platform_Admin', 'Support_Agent', 'Implementation_Specialist'])): ?>
        <div class="menu-group">
            <div class="group-title">Vendor Universe</div>
            
            <a href="<?= url('/pages/saas_admin.php') ?>" class="menu-item <?= $current_page === 'saas_admin.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-earth-asia"></i>
                <span>SaaS Headquarters</span>
            </a>
            
            <?php if (hasRole('Platform_Admin')): ?>
            <a href="<?= url('/pages/saas_staff.php') ?>" class="menu-item <?= $current_page === 'saas_staff.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-id-badge"></i>
                <span>Vendor Staff</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/saas_support.php') ?>" class="menu-item <?= $current_page === 'saas_support.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-inbox"></i>
                <span>Global Support Inbox</span>
            </a>

            <a href="<?= url('/pages/saas_feedback.php') ?>" class="menu-item <?= $current_page === 'saas_feedback.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-star-half-stroke"></i>
                <span>Feedback Corner</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (hasPermission('audit.view')): ?>
        <div class="menu-group">
            <div class="group-title">System</div>
            <a href="<?= url('/pages/audit_logs.php') ?>" class="menu-item <?= $current_page === 'audit_logs.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-scroll"></i>
                <span>Audit Trail</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="menu-group">
            <div class="group-title">Account</div>
            
            <a href="<?= url('/pages/profile.php') ?>" class="menu-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-user"></i>
                <span>My Profile</span>
            </a>
            
            <button onclick="toggleTheme()" class="menu-item" style="width:100%; border:none; background:transparent; text-align:left; color:inherit; cursor:pointer;">
                <i class="fa-solid fa-circle-half-stroke" id="theme-icon"></i>
                <span id="theme-text">Toggle Theme</span>
            </button>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null): ?>
        <button onclick="openGlobalFeedbackModal()" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#f0f4ff; border-radius:8px; padding:8px 12px; margin-bottom:12px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; font-size:0.875rem; transition: background 0.2s;" onmouseenter="this.style.background='rgba(255,255,255,0.1)'" onmouseleave="this.style.background='rgba(255,255,255,0.05)'">
            <i class="fa-regular fa-comment-dots"></i>
            Give us Feedback
        </button>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="user-footer-pill">
                <?php if (!empty($user['profile_image'])): ?>
                    <img class="user-footer-avatar" src="<?= url('/uploads/' . htmlspecialchars($user['profile_image'])) ?>" alt="Avatar">
                <?php else: ?>
                    <div class="user-footer-avatar"><?= $initials ?></div>
                <?php endif; ?>
                
                <div class="user-footer-info">
                    <div class="user-footer-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="user-footer-role"><?= htmlspecialchars($role_desc) ?></div>
                </div>
                
                <a href="<?= url('/logout.php') ?>" class="btn-signout-icon" title="Sign Out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Global Feedback Modal -->
<div id="globalFeedbackModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; backdrop-filter:blur(4px); align-items:center; justify-content:center;">
    <div style="background:#1e2430; border:1px solid rgba(0,224,122,0.15); border-radius:12px; width:400px; max-width:90%; padding:24px; box-shadow:0 10px 40px rgba(0,0,0,0.5);">
        <h3 style="margin-top:0; color:#f0f4ff; font-size:1.25rem; display:flex; align-items:center; gap:10px;">
            <i class="fa-regular fa-comment-dots" style="color:#00e07a;"></i> Give us Feedback
        </h3>
        <p style="color:#8899b4; font-size:0.875rem; margin-bottom:16px;">We'd love to hear your thoughts, ideas, or report any issues.</p>
        
        <textarea id="globalFeedbackText" rows="5" style="width:100%; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:#f0f4ff; border-radius:8px; padding:12px; font-family:inherit; font-size:0.875rem; resize:vertical; outline:none; margin-bottom:16px;" placeholder="Your feedback..."></textarea>
        
        <div style="display:flex; justify-content:flex-end; gap:12px;">
            <button onclick="closeGlobalFeedbackModal()" style="background:transparent; border:1px solid rgba(255,255,255,0.1); color:#8899b4; cursor:pointer; font-size:0.875rem; padding:8px 16px; border-radius:6px;">Cancel</button>
            <button id="submitFeedbackBtn" onclick="submitGlobalFeedback()" style="background:linear-gradient(135deg, #00e07a, #00b8ff); border:none; color:#0b0f1a; cursor:pointer; font-size:0.875rem; padding:8px 16px; border-radius:6px; font-weight:700;">Submit Feedback</button>
        </div>
    </div>
</div>

<script>
function openGlobalFeedbackModal() {
    document.getElementById('globalFeedbackModal').style.display = 'flex';
    document.getElementById('globalFeedbackText').value = '';
    document.getElementById('globalFeedbackText').focus();
}

function closeGlobalFeedbackModal() {
    document.getElementById('globalFeedbackModal').style.display = 'none';
}

function submitGlobalFeedback() {
    const text = document.getElementById('globalFeedbackText').value.trim();
    if (!text) return;
    
    const btn = document.getElementById('submitFeedbackBtn');
    btn.innerText = 'Submitting...';
    btn.disabled = true;

    fetch('<?= url("/api/index.php?route=platform_support&action=submit_feedback") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ feedback: text })
    })
    .then(res => res.json())
    .then(data => {
        btn.innerText = 'Submit Feedback';
        btn.disabled = false;
        if (data.success) {
            closeGlobalFeedbackModal();
            alert("Thank you for your feedback!");
        } else {
            alert("Failed to submit feedback. " + (data.error || ''));
        }
    })
    .catch(err => {
        btn.innerText = 'Submit Feedback';
        btn.disabled = false;
        alert("An error occurred. Please try again.");
    });
}

function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);
    
    // Update icon if it exists
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.className = newTheme === 'dark' ? 'fa-solid fa-circle-half-stroke' : 'fa-regular fa-sun';
    }

    fetch('<?= url("/api/index.php?route=iam&action=update_theme") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ theme: newTheme })
    }).then(res => res.json()).catch(err => console.error(err));
}

// Initial icon setup
document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.className = currentTheme === 'dark' ? 'fa-solid fa-circle-half-stroke' : 'fa-regular fa-sun';
    }
});
</script>
