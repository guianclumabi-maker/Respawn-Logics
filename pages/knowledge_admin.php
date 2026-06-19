<?php
require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Ensure user has admin access
if (!hasPermission('settings.manage')) {
    header("Location: dashboard.php");
    exit;
}

// Handle Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    $newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

    $stmt = $pdo->prepare("UPDATE labor_references SET status = ?, reviewed_by = ? WHERE id = ?");
    $stmt->execute([$newStatus, $user['full_name'], $id]);
    
    // Redirect to clear POST data
    header("Location: knowledge_admin.php?success=1");
    exit;
}

// Fetch pending and approved references
$pendingStmt = $pdo->query("SELECT * FROM labor_references WHERE status = 'Pending' ORDER BY created_at DESC");
$pendingRefs = $pendingStmt->fetchAll();

$approvedStmt = $pdo->query("SELECT * FROM labor_references WHERE status = 'Approved' ORDER BY created_at DESC LIMIT 50");
$approvedRefs = $approvedStmt->fetchAll();

$current_page = 'knowledge_admin.php';
?>
<?php $page_title = 'Knowledge Base Admin - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    .global-glow-purple {
            position: fixed; bottom: -150px; right: -100px; width: 600px; height: 600px; border-radius: 50%; background: #9b6dff; filter: blur(140px); opacity: 0.06; pointer-events: none; z-index: -1;
        }

        .kb-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid #e5e7eb;
        }
        .kb-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        .kb-title {
            font-family: 'Space Grotesk';
            font-size: 1.1rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 5px;
        }
        .kb-meta {
            font-size: 0.85rem;
            color: #6b7280;
        }
        .kb-summary {
            font-size: 0.95rem;
            color: #4b5563;
            line-height: 1.5;
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-approved { background: #d1fae5; color: #065f46; }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-approve {
            background: #00e07a;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
        .btn-reject {
            background: #ef4444;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }
    </style>


<body class="theme-light">
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left">
                    <h1 class="page-title">Knowledge Base Review Workflow</h1>
                </div>
            </header>
            
            <div class="content-wrapper">
                <p style="margin-bottom: 20px; color: #6b7280;">Review newly fetched labor advisories before they are injected into the AI Companion's knowledge base.</p>

                <?php if (isset($_GET['success'])): ?>
                    <div style="background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        Reference status updated successfully!
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col" style="flex: 1;">
                        <h2 style="font-family: 'Space Grotesk'; margin-bottom: 15px;">Pending Review (<?= count($pendingRefs) ?>)</h2>
                        <?php if (empty($pendingRefs)): ?>
                            <div class="kb-card text-center" style="color: #6b7280;">No pending references to review.</div>
                        <?php else: ?>
                            <?php foreach ($pendingRefs as $ref): ?>
                                <div class="kb-card">
                                    <div class="kb-header">
                                        <div>
                                            <div class="kb-title"><?= htmlspecialchars($ref['title']) ?></div>
                                            <div class="kb-meta"><?= htmlspecialchars($ref['source_type']) ?> &bull; Fetched: <?= date('M d, Y', strtotime($ref['created_at'])) ?></div>
                                        </div>
                                        <span class="badge badge-pending">Pending Review</span>
                                    </div>
                                    <div class="kb-summary">
                                        <?= nl2br(htmlspecialchars($ref['summary'])) ?>
                                    </div>
                                    <form method="POST" class="action-buttons">
                                        <input type="hidden" name="id" value="<?= $ref['id'] ?>">
                                        <button type="submit" name="action" value="approve" class="btn-approve">Approve & Inject to AI</button>
                                        <button type="submit" name="action" value="reject" class="btn-reject">Reject</button>
                                        <?php if ($ref['official_url']): ?>
                                            <a href="<?= htmlspecialchars($ref['official_url']) ?>" target="_blank" style="padding: 8px 16px; background: #e5e7eb; color: #374151; border-radius: 6px; text-decoration: none; font-weight: 500;">View Official Source</a>
                                        <?php endif; ?>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="col" style="flex: 1;">
                        <h2 style="font-family: 'Space Grotesk'; margin-bottom: 15px;">Recently Approved AI Sources</h2>
                        <?php if (empty($approvedRefs)): ?>
                            <div class="kb-card text-center" style="color: #6b7280;">No approved references yet.</div>
                        <?php else: ?>
                            <?php foreach ($approvedRefs as $ref): ?>
                                <div class="kb-card" style="opacity: 0.8;">
                                    <div class="kb-header" style="margin-bottom: 5px;">
                                        <div>
                                            <div class="kb-title" style="font-size: 1rem;"><?= htmlspecialchars($ref['title']) ?></div>
                                            <div class="kb-meta">Reviewed by: <?= htmlspecialchars($ref['reviewed_by']) ?></div>
                                        </div>
                                        <span class="badge badge-approved">Active in AI</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
