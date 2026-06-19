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
        <div class="brand-logo-gamepad">
            <i data-lucide="gamepad-2"></i>
        </div>
        <span class="brand-text">Respawn Logics <span style="font-size: 0.65em; color: var(--accent-green); vertical-align: super; font-weight: 800;">v.2</span></span>
    </div>
    
    <div class="sidebar-menu">
        <div class="menu-group">
            <div class="group-title">Workspace</div>
            
            <a href="<?= url('/pages/dashboard.php') ?>" class="menu-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?>">
                <i data-lucide="layout-grid"></i>
                <span>Dashboard</span>
            </a>

            <?php if (tenantModuleEnabled('announcements')): ?>
            <a href="<?= url('/pages/announcements.php') ?>" class="menu-item <?= $current_page === 'announcements.php' ? 'active' : '' ?>">
                <i data-lucide="megaphone"></i>
                <span>Company Feed</span>
            </a>
            <?php endif; ?>

            <?php if (tenantModuleEnabled('surveys')): ?>
            <a href="<?= url('/pages/surveys.php') ?>" class="menu-item <?= $current_page === 'surveys.php' ? 'active' : '' ?>">
                <i data-lucide="bar-chart-2"></i>
                <span>Engagement Surveys</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/ai_companion.php') ?>" class="menu-item <?= $current_page === 'ai_companion.php' ? 'active' : '' ?>">
                <i data-lucide="sparkles"></i>
                <span>AI Companion</span>
            </a>
            
            <?php if (hasPermission('attendance.view') && tenantModuleEnabled('attendance')): ?>
            <a href="<?= url('/pages/attendance.php') ?>" class="menu-item <?= $current_page === 'attendance.php' ? 'active' : '' ?>">
                <i data-lucide="clock"></i>
                <span>Attendance Tracking</span>
            </a>
            <?php endif; // end attendance ?>

            <?php if (hasPermission('shifts.manage') && tenantModuleEnabled('shifts')): ?>
            <a href="<?= url('/pages/scheduling.php') ?>" class="menu-item <?= $current_page === 'scheduling.php' ? 'active' : '' ?>">
                <i data-lucide="calendar"></i>
                <span>Shift Scheduler</span>
            </a>
            <?php endif; // end shifts ?>
            
            <?php if ((hasPermission('leave.view') || hasPermission('leave.request')) && tenantModuleEnabled('leave')): ?>
            <a href="<?= url('/pages/leaves.php') ?>" class="menu-item <?= $current_page === 'leaves.php' ? 'active' : '' ?>">
                <i data-lucide="calendar-check"></i>
                <span>Leave Requests</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/org-chart.php') ?>" class="menu-item <?= $current_page === 'org-chart.php' ? 'active' : '' ?>">
                <i data-lucide="network"></i>
                <span>Org Chart Directory</span>
            </a>

            <?php if (hasPermission('users.manage') && tenantModuleEnabled('onboarding')): ?>
            <a href="<?= url('/pages/onboarding_admin.php') ?>" class="menu-item <?= $current_page === 'onboarding_admin.php' ? 'active' : '' ?>">
                <i data-lucide="zap"></i>
                <span>Onboarding</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('assets.manage') && tenantModuleEnabled('assets')): ?>
            <a href="<?= url('/pages/assets.php') ?>" class="menu-item <?= $current_page === 'assets.php' ? 'active' : '' ?>">
                <i data-lucide="laptop"></i>
                <span>Asset Management</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('intelligence.view') && tenantModuleEnabled('intelligence')): ?>
            <a href="<?= url('/pages/intelligence.php') ?>" class="menu-item <?= $current_page === 'intelligence.php' ? 'active' : '' ?>" style="color: #f59e0b;">
                <i data-lucide="brain"></i>
                <span style="font-weight: 600;">Predictive AI</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('ats.view') && tenantModuleEnabled('ats')): ?>
            <a href="<?= url('/frontend/dist/index.html#/ats') ?>" class="menu-item">
                <i data-lucide="crosshair"></i>
                <span>Recruitment / ATS</span>
            </a>
            <?php endif; ?>
            
            <?php if (tenantModuleEnabled('elr')): ?>
            <a href="<?= url('/pages/elr_portal.php') ?>" class="menu-item <?= $current_page === 'elr_portal.php' ? 'active' : '' ?>">
                <i data-lucide="shield-half"></i>
                <span>My HR Cases</span>
            </a>
            <?php endif; ?>

            <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null && tenantModuleEnabled('esm')): ?>
            <?php 
            $esmLink = hasPermission('esm.manage') ? '/pages/esm_admin.php' : '/pages/esm_employee.php';
            ?>
            <a href="<?= url($esmLink) ?>" class="menu-item <?= ($current_page === 'esm_admin.php' || $current_page === 'esm_employee.php') ? 'active' : '' ?>">
                <i data-lucide="headphones"></i>
                <span>IT/HR Service Desk</span>
            </a>
            <?php endif; ?>
        </div>

        <?php if (hasPermission('users.view') || hasPermission('settings.manage')): ?>
        <div class="menu-group">
            <div class="group-title">Administration</div>
            
            <?php if (hasPermission('analytics.view') && tenantModuleEnabled('analytics')): ?>
            <a href="<?= url('/pages/analytics.php') ?>" class="menu-item <?= $current_page === 'analytics.php' ? 'active' : '' ?>">
                <i data-lucide="pie-chart"></i>
                <span>Workforce Analytics</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('users.manage') || hasPermission('shifts.manage')): ?>
            <a href="<?= url('/pages/hr_directory.php') ?>" class="menu-item <?= $current_page === 'hr_directory.php' ? 'active' : '' ?>">
                <i data-lucide="users"></i>
                <span>Employee Directory</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('payroll.manage') && tenantModuleEnabled('payroll')): ?>
            <a href="<?= url('/payroll-frontend/dist/index.html') ?>" class="menu-item">
                <i data-lucide="philippine-peso"></i>
                <span>Payroll Engine</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('compensation.manage')): ?>
            <a href="<?= url('/pages/compensation_admin.php') ?>" class="menu-item <?= $current_page === 'compensation_admin.php' ? 'active' : '' ?>">
                <i data-lucide="scale"></i>
                <span>Compensation &amp; Equity</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('performance.manage') && tenantModuleEnabled('performance')): ?>
            <a href="<?= url('/pages/performance_admin.php') ?>" class="menu-item <?= $current_page === 'performance_admin.php' ? 'active' : '' ?>">
                <i data-lucide="star"></i>
                <span>Performance</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('expenses.manage') && tenantModuleEnabled('expenses')): ?>
            <a href="<?= url('/pages/expenses_admin.php') ?>" class="menu-item <?= $current_page === 'expenses_admin.php' ? 'active' : '' ?>">
                <i data-lucide="receipt"></i>
                <span>Expenses &amp; Claims</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('benefits.manage') && tenantModuleEnabled('benefits')): ?>
            <a href="<?= url('/pages/benefits_admin.php') ?>" class="menu-item <?= $current_page === 'benefits_admin.php' ? 'active' : '' ?>">
                <i data-lucide="gift"></i>
                <span>Benefits &amp; HMO</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('elr.view') && tenantModuleEnabled('elr')): ?>
            <a href="<?= url('/employee-relations-dist/dist/index.html') ?>" class="menu-item <?= $current_page === 'index.html' ? 'active' : '' ?>" style="color:#ef4444;">
                <i data-lucide="gavel"></i>
                <span style="font-weight:600;">ELR Admin Console</span>
            </a>
            <?php endif; ?>

            <?php if (hasPermission('users.view')): ?>
            <a href="<?= url('/pages/admin_users.php') ?>" class="menu-item <?= $current_page === 'admin_users.php' ? 'active' : '' ?>">
                <i data-lucide="user-cog"></i>
                <span>Users</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('users.manage')): ?>
            <a href="<?= url('/pages/admin_roles.php') ?>" class="menu-item <?= $current_page === 'admin_roles.php' ? 'active' : '' ?>">
                <i data-lucide="shield-half"></i>
                <span>Roles &amp; Permissions</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.manage')): ?>
            <a href="<?= url('/pages/tenant_settings.php') ?>" class="menu-item <?= $current_page === 'tenant_settings.php' ? 'active' : '' ?>">
                <i data-lucide="settings"></i>
                <span>Tenant Settings</span>
            </a>
            <?php endif; ?>
            
            <?php if (hasPermission('settings.manage')): ?>
            <a href="<?= url('/pages/knowledge_admin.php') ?>" class="menu-item <?= $current_page === 'knowledge_admin.php' ? 'active' : '' ?>">
                <i data-lucide="book-open"></i>
                <span>Knowledge Base Review</span>
            </a>
            <?php endif; ?>

            <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null): ?>
            <a href="<?= url('/pages/admin_platform_support.php') ?>" class="menu-item <?= $current_page === 'admin_platform_support.php' ? 'active' : '' ?>" style="color:#00e07a;">
                <i data-lucide="satellite"></i>
                <span style="font-weight:600;">Platform Support</span>
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (hasRole(['Platform_Admin', 'Support_Agent', 'Implementation_Specialist'])): ?>
        <div class="menu-group">
            <div class="group-title">Vendor Universe</div>
            
            <a href="<?= url('/pages/saas_admin.php') ?>" class="menu-item <?= $current_page === 'saas_admin.php' ? 'active' : '' ?>">
                <i data-lucide="globe"></i>
                <span>SaaS Headquarters</span>
            </a>
            
            <?php if (hasRole('Platform_Admin')): ?>
            <a href="<?= url('/pages/saas_staff.php') ?>" class="menu-item <?= $current_page === 'saas_staff.php' ? 'active' : '' ?>">
                <i data-lucide="badge-info"></i>
                <span>Vendor Staff</span>
            </a>
            <?php endif; ?>

            <a href="<?= url('/pages/saas_support.php') ?>" class="menu-item <?= $current_page === 'saas_support.php' ? 'active' : '' ?>">
                <i data-lucide="inbox"></i>
                <span>Global Support Inbox</span>
            </a>

            <a href="<?= url('/pages/saas_feedback.php') ?>" class="menu-item <?= $current_page === 'saas_feedback.php' ? 'active' : '' ?>">
                <i data-lucide="star-half"></i>
                <span>Feedback Corner</span>
            </a>
        </div>
        <?php endif; ?>
        
        <?php if (hasPermission('audit.view')): ?>
        <div class="menu-group">
            <div class="group-title">System</div>
            <a href="<?= url('/pages/audit_logs.php') ?>" class="menu-item <?= $current_page === 'audit_logs.php' ? 'active' : '' ?>">
                <i data-lucide="scroll"></i>
                <span>Audit Trail</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="menu-group">
            <div class="group-title">Account</div>
            
            <?php if (isset($user['tenant_id']) && $user['tenant_id'] !== null): ?>
            <a href="#" onclick="openGlobalFeedbackModal(); return false;" class="menu-item">
                <i data-lucide="message-circle"></i>
                <span>Give us Feedback</span>
            </a>
            <?php endif; ?>
            
            <a href="<?= url('/pages/profile.php') ?>" class="menu-item <?= $current_page === 'profile.php' ? 'active' : '' ?>">
                <i data-lucide="user-circle"></i>
                <span>My Profile</span>
            </a>
            
            <div onclick="toggleTheme()" class="gamified-theme-toggle" id="gamified-theme-btn">
                <div class="gamified-bg-sweep"></div>
                
                <div class="gamified-content">
                    <i data-lucide="gamepad-2" class="gamified-icon"></i>
                    <span class="gamified-text" id="gamified-theme-text">Night Ops</span>
                </div>

                <div class="gamified-switch">
                    <div class="gamified-knob">
                        <i class="fa-solid fa-moon" id="gamified-knob-icon"></i>
                    </div>
                </div>
            </div>
            
            <script>
                // Update the text and icon immediately on load or when toggled
                document.addEventListener('DOMContentLoaded', function() {
                    const updateToggleUI = () => {
                        const theme = document.documentElement.getAttribute('data-theme') || 'dark';
                        const textEl = document.getElementById('gamified-theme-text');
                        const iconEl = document.getElementById('gamified-knob-icon');
                        if (theme === 'dark') {
                            textEl.textContent = 'Night Ops';
                            iconEl.className = 'fa-solid fa-moon';
                        } else {
                            textEl.textContent = 'Day Cycle';
                            iconEl.className = 'fa-solid fa-sun';
                        }
                    };
                    
                    // Initial update
                    updateToggleUI();
                    
                    // Hook into toggleTheme to update immediately
                    const originalToggle = window.toggleTheme;
                    window.toggleTheme = function() {
                        originalToggle();
                        updateToggleUI();
                    };
                });
            </script>
        </div>
    </div>
    
    <div class="sidebar-footer">
        <?php if ($user): ?>
            <a href="<?= url('/pages/profile.php') ?>" class="user-footer-card" title="Profile & Settings">
                <div class="user-footer-avatar-box">
                    <?php if (!empty($user['profile_image'])): ?>
                        <img src="<?= url('/uploads/' . htmlspecialchars($user['profile_image'])) ?>" alt="Avatar">
                    <?php else: ?>
                        <span class="user-footer-avatar-initials"><?= $initials ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="user-footer-info">
                    <div class="user-footer-name"><?= htmlspecialchars($user['full_name']) ?></div>
                    <div class="user-footer-role"><?= htmlspecialchars($role_desc) ?></div>
                </div>
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Global Feedback Modal -->
<div id="globalFeedbackModal" class="modal-overlay" style="display:none;">
    <div class="modal-card feedback-modal-card">
        <h3 class="modal-title">
            <i class="fa-regular fa-comment-dots" style="color:var(--accent-green);"></i> Give us Feedback
        </h3>
        <p class="modal-subtitle">We'd love to hear your thoughts, ideas, or report any issues.</p>
        
        <textarea id="globalFeedbackText" rows="5" class="modal-textarea" placeholder="Your feedback..."></textarea>
        
        <div class="modal-actions">
            <button onclick="closeGlobalFeedbackModal()" class="btn-modal-cancel">Cancel</button>
            <button id="submitFeedbackBtn" onclick="submitGlobalFeedback()" class="btn-modal-submit">Submit Feedback</button>
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
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Broadcast to ALL open tabs on the same origin instantly
    try {
        const bc = new BroadcastChannel('respawn_theme');
        bc.postMessage({ theme: newTheme });
        bc.close();
    } catch(e) {}
    
    // Update icon if it exists
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.className = newTheme === 'dark' ? 'fa-solid fa-circle-half-stroke' : 'fa-regular fa-sun';
    }

    fetch('<?= url("/api/index.php?route=iam&action=update_theme") ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            theme: newTheme,
            csrf_token: '<?= $_SESSION["csrf_token"] ?? "" ?>'
        })
    }).then(res => res.json()).catch(err => console.error(err));
}

// Persistent BroadcastChannel listener — syncs theme when ANY other tab (PHP or React)
// calls toggleTheme() and broadcasts. Keeps PHP pages already open in sync.
(function() {
    try {
        const _respawnThemeChannel = new BroadcastChannel('respawn_theme');
        _respawnThemeChannel.onmessage = function(e) {
            if (!e.data || !e.data.theme) return;
            const t = e.data.theme;
            document.documentElement.setAttribute('data-theme', t);
            localStorage.setItem('theme', t);
            // Update gamified toggle UI text and icon
            const textEl = document.getElementById('gamified-theme-text');
            const iconEl = document.getElementById('gamified-knob-icon');
            const themeIcon = document.getElementById('theme-icon');
            if (textEl) textEl.textContent = t === 'dark' ? 'Night Ops' : 'Day Cycle';
            if (iconEl) iconEl.className = t === 'dark' ? 'fa-solid fa-moon' : 'fa-solid fa-sun';
            if (themeIcon) themeIcon.className = t === 'dark' ? 'fa-solid fa-circle-half-stroke' : 'fa-regular fa-sun';
        };
    } catch(e) {}
})();

// Initial icon setup
document.addEventListener('DOMContentLoaded', () => {
    const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
    const icon = document.getElementById('theme-icon');
    if (icon) {
        icon.className = currentTheme === 'dark' ? 'fa-solid fa-circle-half-stroke' : 'fa-regular fa-sun';
    }
});
</script>
