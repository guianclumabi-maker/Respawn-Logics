<?php
require_once __DIR__ . '/../bootstrap/app.php';
$user = getCurrentUser();

$first_name = 'User';
$full_name = 'User';
$email = 'user@company.com';
$profile_image = '';
$initials = 'US';

// Make CSRF token available to frontend JS
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<script>
    window.__CSRF_TOKEN__ = <?= json_encode($csrfToken) ?>;
</script>
<?php
if ($user) {
    $full_name = $user['full_name'];
    $email = $user['email'];
    $profile_image = $user['profile_image'] ?? '';
    $names = explode(' ', $full_name);
    $first_name = $names[0];
    
    $temp_initials = '';
    foreach ($names as $n) {
        $temp_initials .= strtoupper(substr($n, 0, 1));
    }
    $initials = substr($temp_initials, 0, 2);
}
?>
<style>
/* Notification Bell Styles */
.header-actions { display: flex; align-items: center; gap: 16px; position: relative; }
.notif-bell-container { position: relative; cursor: pointer; color: var(--text-secondary); transition: color 0.2s; padding: 6px; }
.notif-bell-container:hover { color: var(--text-primary); }
.notif-badge {
    position: absolute; top: 0px; right: 2px;
    background: #ef4444; color: white;
    font-size: 0.65rem; font-weight: 700;
    padding: 2px 5px; border-radius: 10px;
    border: 2px solid var(--bg-primary);
    display: none; /* hidden by default */
}
.notif-dropdown {
    position: absolute; top: 50px; right: 150px;
    width: 340px; background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-md);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5);
    z-index: 1000; display: none; overflow: hidden;
}
.notif-dropdown.show { display: block; }
.notif-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
    background: rgba(0,0,0,0.1);
}
.notif-header h4 { margin: 0; font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
.mark-all-read { font-size: 0.8rem; color: var(--accent-blue); cursor: pointer; }
.mark-all-read:hover { text-decoration: underline; }
.notif-list { max-height: 350px; overflow-y: auto; }
.notif-item {
    padding: 12px 16px; border-bottom: 1px solid var(--border-color);
    display: flex; gap: 12px; transition: background 0.2s; cursor: pointer;
    text-decoration: none; color: inherit;
}
.notif-item:hover { background: rgba(255,255,255,0.03); }
.notif-icon {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.notif-icon.info { background: rgba(59,130,246,0.1); color: #3b82f6; }
.notif-icon.success { background: rgba(16,185,129,0.1); color: #10b981; }
.notif-icon.warning { background: rgba(245,158,11,0.1); color: #f59e0b; }
.notif-icon.error { background: rgba(239,68,68,0.1); color: #ef4444; }
.notif-content { flex-grow: 1; }
.notif-title { font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-bottom: 2px; }
.notif-msg { font-size: 0.8rem; color: var(--text-muted); line-height: 1.3; margin-bottom: 4px; }
.notif-time { font-size: 0.7rem; color: var(--text-secondary); }
.notif-empty { padding: 30px 20px; text-align: center; color: var(--text-muted); font-size: 0.85rem; }
</style>
<?php if (isset($_SESSION['is_impersonating']) && $_SESSION['is_impersonating'] === true): ?>
<style>
    .impersonation-floating-banner {
        position: fixed;
        top: 20px;
        left: 50%;
        transform: translate(-50%, 0);
        z-index: 100000;
        background: linear-gradient(135deg, #ef4444, #b91c1c);
        color: white;
        padding: 10px 24px;
        font-weight: 600;
        font-size: 0.875rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        border-radius: 50px;
        border: 1px solid rgba(255, 255, 255, 0.15);
        animation: hq-pulse-glow 2.5s infinite ease-in-out;
        pointer-events: auto;
        white-space: nowrap;
        font-family: 'Space Grotesk', sans-serif;
    }
    @keyframes hq-pulse-glow {
        0% {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4), 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            transform: translate(-50%, 0) scale(1);
        }
        50% {
            box-shadow: 0 0 30px rgba(239, 68, 68, 0.8), 0 8px 16px -1px rgba(239, 68, 68, 0.3);
            transform: translate(-50%, -2px) scale(1.02);
        }
        100% {
            box-shadow: 0 0 15px rgba(239, 68, 68, 0.4), 0 4px 6px -1px rgba(0, 0, 0, 0.4);
            transform: translate(-50%, 0) scale(1);
        }
    }
    .impersonation-floating-banner a {
        background: white;
        color: #ef4444;
        padding: 6px 16px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.15);
    }
    .impersonation-floating-banner a:hover {
        background: #f3f4f6;
        transform: translate(0, -1px) scale(1.03);
        box-shadow: 0 4px 8px rgba(0,0,0,0.25);
    }
</style>
<div class="impersonation-floating-banner">
    <div style="display: flex; align-items: center; gap: 8px;">
        <i class="fa-solid fa-user-secret" style="font-size: 1.05rem; color: #ffccd5;"></i>
        <span><strong>IMPERSONATION MODE:</strong> You are currently viewing the system as a client Super Admin.</span>
    </div>
    <a href="<?= url('/pages/impersonate.php?action=stop') ?>">
        <i data-lucide="log-out"></i> Return to SaaS Control Center
    </a>
</div>
<?php endif; ?>
<header class="app-header">
    <div class="header-welcome">
        <h1>Welcome back, <?= htmlspecialchars($first_name) ?>!</h1>
        <p>Viewing portal with active configurations.</p>
    </div>
    
    <div class="header-actions">
        <!-- Notification Bell -->
        <div class="notif-bell-container" id="notifBellTrigger">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <span class="notif-badge" id="notifBadge">0</span>
        </div>

        <!-- Notification Dropdown -->
        <div class="notif-dropdown" id="notifDropdownMenu">
            <div class="notif-header">
                <h4>Notifications</h4>
                <span class="mark-all-read" onclick="markAllNotificationsRead()">Mark all read</span>
            </div>
            <div class="notif-list" id="notifListContainer">
                <div class="notif-empty">No new notifications</div>
            </div>
        </div>

        <div class="header-profile-pill" id="profilePillTrigger">
            <?php if (!empty($profile_image)): ?>
                <img class="header-profile-avatar" src="<?= url('/api/index.php?route=auth&action=download_avatar&file=' . htmlspecialchars($profile_image)) ?>" alt="Avatar">
            <?php else: ?>
                <div class="header-profile-avatar"><?= $initials ?></div>
            <?php endif; ?>
            <span class="header-profile-name"><?= htmlspecialchars($full_name) ?></span>
            <!-- Down Arrow Icon -->
            <svg class="header-profile-arrow" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        
        <!-- Profile dropdown menu -->
        <div class="profile-dropdown" id="profileDropdownMenu">
            <div class="dropdown-header">
                <div style="font-weight: 600; font-size: 0.85rem; color: var(--text-primary);"><?= htmlspecialchars($full_name) ?></div>
                <div class="dropdown-email"><?= htmlspecialchars($email) ?></div>
            </div>
            
            <a href="<?= url('/pages/profile.php') ?>" class="dropdown-item">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span>My Profile</span>
            </a>
            
            <a href="<?= url('/pages/org-chart.php') ?>" class="dropdown-item">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span>Org Chart</span>
            </a>
            
            <a href="<?= url('/logout.php') ?>" class="dropdown-item" style="border-top: 1px solid var(--border-color); margin-top: 6px; color: var(--accent-red);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span>Sign Out</span>
            </a>
        </div>
    </div>
</header>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('profilePillTrigger');
    const dropdown = document.getElementById('profileDropdownMenu');
    
    if (trigger && dropdown) {
        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            dropdown.classList.remove('show');
        });
        
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Notification Logic
    const notifTrigger = document.getElementById('notifBellTrigger');
    const notifDropdown = document.getElementById('notifDropdownMenu');
    
    if (notifTrigger && notifDropdown) {
        notifTrigger.addEventListener('click', function(e) {
            e.stopPropagation();
            if (dropdown) dropdown.classList.remove('show'); // close profile
            notifDropdown.classList.toggle('show');
        });
        
        document.addEventListener('click', function() {
            notifDropdown.classList.remove('show');
        });
        
        notifDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    }

    // Polling logic
    function fetchNotifications() {
        fetch('<?= url("/api/index.php?route=notifications&action=fetch_unread") ?>')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    renderNotifications(data.data);
                }
            })
            .catch(err => console.error('Notification poll error:', err));
    }

    function timeAgo(dateString) {
        const date = new Date(dateString.replace(' ', 'T'));
        const now = new Date();
        const seconds = Math.floor((now - date) / 1000);
        let interval = seconds / 31536000;
        if (interval > 1) return Math.floor(interval) + " years ago";
        interval = seconds / 2592000;
        if (interval > 1) return Math.floor(interval) + " months ago";
        interval = seconds / 86400;
        if (interval > 1) return Math.floor(interval) + " days ago";
        interval = seconds / 3600;
        if (interval > 1) return Math.floor(interval) + " hours ago";
        interval = seconds / 60;
        if (interval > 1) return Math.floor(interval) + " mins ago";
        return "Just now";
    }

    function renderNotifications(notifs) {
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifListContainer');
        
        if (notifs.length > 0) {
            badge.innerText = notifs.length > 99 ? '99+' : notifs.length;
            badge.style.display = 'block';
            
            let html = '';
            notifs.forEach(n => {
                let iconClass = n.type || 'info';
                let faIcon = 'fa-info';
                if (iconClass === 'success') faIcon = 'fa-check';
                if (iconClass === 'warning') faIcon = 'fa-exclamation-triangle';
                if (iconClass === 'error') faIcon = 'fa-times';
                
                // If it has a link, wrap in an <a> tag, else a <div>
                const tag = n.link ? 'a' : 'div';
                const href = n.link ? `href="${n.link}"` : '';
                
                html += `
                    <${tag} ${href} class="notif-item" onclick="markNotificationRead(${n.id})">
                        <div class="notif-icon ${iconClass}"><i class="fa-solid ${faIcon}"></i></div>
                        <div class="notif-content">
                            <div class="notif-title">${n.title}</div>
                            <div class="notif-msg">${n.message}</div>
                            <div class="notif-time">${timeAgo(n.created_at)}</div>
                        </div>
                    </${tag}>
                `;
            });
            list.innerHTML = html;
        } else {
            badge.style.display = 'none';
            list.innerHTML = '<div class="notif-empty">No new notifications</div>';
        }
    }

    window.markNotificationRead = function(id) {
        fetch('<?= url("/api/index.php?route=notifications&action=mark_read") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__},
            body: JSON.stringify({id: id})
        }).then(() => fetchNotifications()); // refresh
    };

    window.markAllNotificationsRead = function() {
        fetch('<?= url("/api/index.php?route=notifications&action=mark_all_read") ?>', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': window.__CSRF_TOKEN__}
        }).then(() => fetchNotifications()); // refresh
    };

    // Initial fetch
    fetchNotifications();
    // Poll every 60 seconds
    setInterval(fetchNotifications, 60000);
});
</script>
