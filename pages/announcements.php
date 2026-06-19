<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();
$current_page = 'announcements.php';

$can_post = hasPermission('announcements.manage');
?>
<?php $page_title = 'Company Feed - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

        .feed-container {
            max-width: 680px;
            margin: 0 auto;
            padding-bottom: 50px;
        }
        .page-header { margin-bottom: 24px; }
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.75rem; color: var(--text-primary); margin: 0 0 4px 0; }
        .title-block p { color: var(--text-muted); margin: 0; font-size: 0.95rem; }
        
        /* Compose Box */
        .compose-box {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .compose-header { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
        .compose-avatar { width: 44px; height: 44px; border-radius: 50%; object-fit: cover; background: var(--bg-primary); display:flex; align-items:center; justify-content:center; font-weight:bold;}
        .compose-input {
            width: 100%; background: var(--bg-primary); border: 1px solid var(--border-color);
            border-radius: var(--radius-md); padding: 12px 16px; color: var(--text-primary);
            resize: vertical; min-height: 80px; font-family: 'Space Grotesk';
        }
        .compose-input:focus { outline: none; border-color: var(--accent-blue); }
        .compose-actions {
            display: flex; justify-content: space-between; align-items: center;
            margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border-color);
        }
        .image-upload-btn {
            color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 8px;
            padding: 8px 12px; border-radius: 6px; transition: all 0.2s;
        }
        .image-upload-btn:hover { background: rgba(255,255,255,0.05); color: var(--text-primary); }
        .image-preview-container { display: none; margin-top: 16px; position: relative; }
        .image-preview { max-width: 100%; max-height: 300px; border-radius: 8px; object-fit: cover; }
        .remove-image {
            position: absolute; top: 8px; right: 8px; background: rgba(0,0,0,0.6);
            color: white; width: 28px; height: 28px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }

        /* Feed Post */
        .post-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .post-header { padding: 16px; display: flex; align-items: center; gap: 12px; }
        .post-avatar { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: var(--bg-primary); display:flex; align-items:center; justify-content:center; font-weight:bold;}
        .post-author { font-weight: 600; color: var(--text-primary); font-size: 0.95rem; }
        .post-title { font-size: 0.8rem; color: var(--text-muted); }
        .post-time { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }
        
        .post-caption { padding: 0 16px 16px 16px; color: #d1d5db; font-size: 0.95rem; line-height: 1.5; white-space: pre-wrap; }
        .post-image { width: 100%; max-height: 500px; object-fit: cover; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); }
        
        .loader {
            display: block; width: 40px; height: 40px;
            border: 4px solid rgba(255,255,255,0.1); border-radius: 50%;
            border-top-color: var(--accent-blue); animation: spin 1s ease-in-out infinite;
            margin: 40px auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>


<body>
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <?php include __DIR__ . '/../includes/app-header.php'; ?>
            
            <div class="content-wrapper">
                <div class="feed-container">
                    <div class="page-header">
                        <div class="title-block">
                            <h1>Company Feed</h1>
                            <p>Global announcements, updates, and news.</p>
                        </div>
                    </div>

                    <?php if ($can_post): ?>
                    <!-- Compose Box -->
                    <div class="compose-box">
                        <form id="composeForm">
                            <div class="compose-header">
                                <?php if (!empty($user['profile_image'])): ?>
                                    <img src="<?= url('/uploads/' . $user['profile_image']) ?>" class="compose-avatar">
                                <?php else: ?>
                                    <div class="compose-avatar"><?= substr(str_replace(' ', '', $user['full_name']), 0, 2) ?></div>
                                <?php endif; ?>
                                <textarea id="postCaption" class="compose-input" placeholder="Share an announcement with the entire company..."></textarea>
                            </div>
                            
                            <div class="image-preview-container" id="previewContainer">
                                <div class="remove-image" onclick="removeImage()"><i class="fa-solid fa-times"></i></div>
                                <img id="imagePreview" class="image-preview" src="">
                            </div>

                            <div class="compose-actions">
                                <label class="image-upload-btn">
                                    <i class="fa-solid fa-image" style="color: #00e07a;"></i> Attach Image
                                    <input type="file" id="postImage" accept="image/*" style="display: none;" onchange="previewFile()">
                                </label>
                                <button type="button" class="btn btn-primary" onclick="submitPost()" id="submitBtn"><i class="fa-solid fa-paper-plane"></i> Post Announcement</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Feed Stream -->
                    <div id="feedStream">
                        <div class="loader"></div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function timeAgo(dateString) {
            const date = new Date(dateString.replace(' ', 'T'));
            const now = new Date();
            const seconds = Math.floor((now - date) / 1000);
            let interval = seconds / 31536000;
            if (interval > 1) return Math.floor(interval) + "y";
            interval = seconds / 2592000;
            if (interval > 1) return Math.floor(interval) + "mo";
            interval = seconds / 86400;
            if (interval > 1) return Math.floor(interval) + "d";
            interval = seconds / 3600;
            if (interval > 1) return Math.floor(interval) + "h";
            interval = seconds / 60;
            if (interval > 1) return Math.floor(interval) + "m";
            return "Just now";
        }

        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag] || tag)
            );
        }

        async function loadFeed() {
            try {
                const res = await fetch(`<?= url('/api/index.php?route=announcements&action=fetch_posts') ?>`);
                const data = await res.json();
                
                const container = document.getElementById('feedStream');
                
                if (data.success) {
                    if (data.data.length === 0) {
                        container.innerHTML = `<div style="text-align:center; color:var(--text-muted); padding:40px;">No announcements have been posted yet.</div>`;
                        return;
                    }
                    
                    let html = '';
                    data.data.forEach(p => {
                        const initials = p.full_name.split(' ').map(n=>n[0]).join('').substring(0,2).toUpperCase();
                        const avatar = p.profile_image ? `<img src="<?= url('/uploads/') ?>${p.profile_image}" class="post-avatar">` : `<div class="post-avatar">${initials}</div>`;
                        
                        let imgHtml = '';
                        if (p.image_path) {
                            imgHtml = `<img src="<?= url('/uploads/') ?>${p.image_path}" class="post-image" loading="lazy">`;
                        }

                        html += `
                            <div class="post-card">
                                <div class="post-header">
                                    ${avatar}
                                    <div>
                                        <div class="post-author">${escapeHTML(p.full_name)}</div>
                                        <div class="post-title">${escapeHTML(p.job_title)}</div>
                                        <div class="post-time">${timeAgo(p.created_at)}</div>
                                    </div>
                                </div>
                                <div class="post-caption">${escapeHTML(p.caption)}</div>
                                ${imgHtml}
                            </div>
                        `;
                    });
                    container.innerHTML = html;
                } else {
                    container.innerHTML = `<div style="color:#ef4444; padding:20px;">Failed to load feed.</div>`;
                }
            } catch (e) {
                console.error(e);
            }
        }

        function previewFile() {
            const preview = document.getElementById('imagePreview');
            const file = document.getElementById('postImage').files[0];
            const container = document.getElementById('previewContainer');
            
            if (file) {
                const reader = new FileReader();
                reader.onloadend = function() {
                    preview.src = reader.result;
                    container.style.display = 'block';
                }
                reader.readAsDataURL(file);
            } else {
                preview.src = "";
                container.style.display = 'none';
            }
        }

        function removeImage() {
            document.getElementById('postImage').value = '';
            document.getElementById('imagePreview').src = '';
            document.getElementById('previewContainer').style.display = 'none';
        }

        async function submitPost() {
            const caption = document.getElementById('postCaption').value;
            const fileInput = document.getElementById('postImage');
            const file = fileInput.files[0];
            const btn = document.getElementById('submitBtn');

            if (!caption.trim() && !file) {
                alert("Please write a message or attach an image.");
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';

            const formData = new FormData();
            formData.append('caption', caption);
            if (file) {
                formData.append('image', file);
            }
            // Include CSRF Token in form data if not in header, though fetch below doesn't send header easily with FormData without manual iteration. Let's just append it.
            formData.append('csrf_token', window.__CSRF_TOKEN__);

            try {
                // For file uploads, we use POST and omit Content-Type header so browser sets boundary
                const res = await fetch(`<?= url('/api/index.php?route=announcements&action=create_post') ?>`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': window.__CSRF_TOKEN__
                    }
                });
                
                const data = await res.json();
                if (data.success) {
                    document.getElementById('postCaption').value = '';
                    removeImage();
                    await loadFeed();
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                console.error(e);
                alert('Failed to post announcement.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Post Announcement';
            }
        }

        document.addEventListener('DOMContentLoaded', loadFeed);
    </script>
</body>
</html>
