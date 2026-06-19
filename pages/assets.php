<?php
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();

if (!hasPermission('assets.manage')) {
    header("Location: " . url('/pages/dashboard.php'));
    exit;
}

// --- POST Logic ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Add Asset
    if ($_POST['action'] === 'add_asset') {
        $tag = trim($_POST['asset_tag'] ?? '');
        $type = trim($_POST['type'] ?? '');
        $make_model = trim($_POST['make_model'] ?? '');
        $serial = trim($_POST['serial_number'] ?? '');
        
        $stmt = $pdo->prepare("INSERT INTO assets (asset_tag, type, make_model, serial_number, status) VALUES (?, ?, ?, ?, 'Available')");
        $stmt->execute([$tag, $type, $make_model, $serial]);
        header("Location: assets.php?success=1");
        exit;
    }
    
    // Assign Asset
    if ($_POST['action'] === 'assign_asset') {
        $asset_id = intval($_POST['asset_id'] ?? 0);
        $email = trim($_POST['assigned_to_email'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        
        $pdo->beginTransaction();
        try {
            // Update Asset Status
            $stmt1 = $pdo->prepare("UPDATE assets SET status = 'Assigned' WHERE id = ?");
            $stmt1->execute([$asset_id]);
            
            // Insert Assignment Ledger
            $stmt2 = $pdo->prepare("INSERT INTO asset_assignments (asset_id, assigned_to_email, assigned_by, notes) VALUES (?, ?, ?, ?)");
            $stmt2->execute([$asset_id, $email, $user['id'] ?? 0, $notes]);
            
            $pdo->commit();
            header("Location: assets.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
    
    // Return Asset
    if ($_POST['action'] === 'return_asset') {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $asset_id = intval($_POST['asset_id'] ?? 0);
        
        $pdo->beginTransaction();
        try {
            // Update Asset Status back to Available
            $stmt1 = $pdo->prepare("UPDATE assets SET status = 'Available' WHERE id = ?");
            $stmt1->execute([$asset_id]);
            
            // Log Return Date
            $stmt2 = $pdo->prepare("UPDATE asset_assignments SET returned_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt2->execute([$assignment_id]);
            
            $pdo->commit();
            header("Location: assets.php?success=1");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
        }
    }
}

// --- Fetch Data ---
$users = $pdo->query("SELECT email, full_name, role FROM users ORDER BY full_name ASC")->fetchAll();

$assets_stmt = $pdo->query("
    SELECT a.*, 
           (SELECT aa.assigned_to_email FROM asset_assignments aa WHERE aa.asset_id = a.id AND aa.returned_at IS NULL LIMIT 1) as current_assignee
    FROM assets a 
    ORDER BY a.created_at DESC
");
$all_assets = $assets_stmt->fetchAll();

$assignments_stmt = $pdo->query("
    SELECT aa.*, a.asset_tag, a.make_model, a.type, u.full_name as assigned_to_name
    FROM asset_assignments aa
    JOIN assets a ON aa.asset_id = a.id
    JOIN users u ON aa.assigned_to_email = u.email
    WHERE aa.returned_at IS NULL
    ORDER BY aa.assigned_at DESC
");
$active_assignments = $assignments_stmt->fetchAll();

$current_page = 'assets.php';
?>
<?php $page_title = 'Asset Management - Respawn Logics'; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>

    <style>

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
        .title-block h1 { font-family: 'Space Grotesk'; font-size: 1.5rem; color: #111827; margin: 0 0 4px 0; }
        .title-block p { color: #6b7280; margin: 0; font-size: 0.95rem; }
        
        .grid-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }
        
        .panel {
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        .panel-header {
            font-family: 'Space Grotesk';
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th {
            text-align: left;
            padding: 12px;
            border-bottom: 2px solid #e5e7eb;
            color: #6b7280;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 16px 12px;
            border-bottom: 1px solid #e5e7eb;
            color: #111827;
            font-size: 0.95rem;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-Available { background: #d1fae5; color: #065f46; }
        .status-Assigned { background: #dbeafe; color: #1e40af; }
        .status-Under { background: #fef3c7; color: #92400e; }
        .status-Retired { background: #f3f4f6; color: #374151; }

        .btn-primary { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; }
        .btn-secondary { background: #ffffff; color: #374151; border: 1px solid #d1d5db; padding: 6px 12px; border-radius: 6px; font-weight: 500; cursor: pointer; font-size: 0.85rem;}
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000;
        }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; padding: 24px; border-radius: 12px; width: 100%; max-width: 500px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #e5e7eb; padding-bottom: 12px; }
        .modal-title { font-family: 'Space Grotesk'; font-size: 1.25rem; font-weight: 600; margin:0; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #6b7280; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 0.875rem; font-weight: 500; margin-bottom: 8px; color: #374151; }
        .form-input, .form-select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.95rem; }
    </style>


<body class="theme-light">
    <div class="layout-wrapper">
        <?php include __DIR__ . '/../includes/sidebar.php'; ?>
        
        <div class="main-content">
            <header class="topbar">
                <div class="topbar-left"><h1 class="page-title">Asset Management</h1></div>
            </header>
            
            <div class="content-wrapper">
                <div class="page-header">
                    <div class="title-block">
                        <h1>Hardware & License Inventory</h1>
                        <p>Track company laptops, monitors, software licenses, and their assignments.</p>
                    </div>
                    <button class="btn-primary" onclick="document.getElementById('addAssetModal').classList.add('active')">+ Register New Asset</button>
                </div>

                <div class="grid-layout">
                    <!-- Left: All Assets -->
                    <div class="panel">
                        <div class="panel-header">Inventory Master List</div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Type</th>
                                    <th>Make/Model</th>
                                    <th>Status</th>
                                    <th>Assignee</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_assets as $a): ?>
                                    <tr>
                                        <td style="font-family: monospace; font-weight:600; color:#4f46e5;"><?= htmlspecialchars($a['asset_tag']) ?></td>
                                        <td><?= htmlspecialchars($a['type']) ?></td>
                                        <td>
                                            <div style="font-weight: 500;"><?= htmlspecialchars($a['make_model']) ?></div>
                                            <div style="font-size:0.75rem; color:#6b7280;">SN: <?= htmlspecialchars($a['serial_number'] ?: 'N/A') ?></div>
                                        </td>
                                        <td>
                                            <?php $st_class = "status-" . explode(' ', $a['status'])[0]; ?>
                                            <span class="status-badge <?= $st_class ?>"><?= htmlspecialchars($a['status']) ?></span>
                                        </td>
                                        <td>
                                            <?= $a['current_assignee'] ? htmlspecialchars($a['current_assignee']) : '<span style="color:#9ca3af;">--</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($a['status'] === 'Available'): ?>
                                                <button class="btn-secondary" onclick="openAssignModal(<?= $a['id'] ?>, '<?= htmlspecialchars(addslashes($a['asset_tag'] . " - " . $a['make_model'])) ?>')">Assign</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_assets)): ?>
                                    <tr><td colspan="6" style="text-align:center; color:#6b7280; padding:30px;">No assets registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Right: Active Assignments -->
                    <div class="panel">
                        <div class="panel-header">Active Assignments</div>
                        <div style="display:flex; flex-direction:column; gap:16px;">
                            <?php foreach ($active_assignments as $aa): ?>
                                <div style="border:1px solid #e5e7eb; border-radius:8px; padding:16px;">
                                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                                        <div>
                                            <div style="font-weight:600; color:#111827;"><?= htmlspecialchars($aa['assigned_to_name']) ?></div>
                                            <div style="font-size:0.8rem; color:#6b7280;"><?= htmlspecialchars($aa['assigned_to_email']) ?></div>
                                        </div>
                                        <form method="POST" style="margin:0;">
                                            <input type="hidden" name="action" value="return_asset">
                                            <input type="hidden" name="assignment_id" value="<?= $aa['id'] ?>">
                                            <input type="hidden" name="asset_id" value="<?= $aa['asset_id'] ?>">
                                            <button type="submit" class="btn-secondary" style="color:#dc2626; border-color:#fca5a5;">Revoke</button>
                                        </form>
                                    </div>
                                    <div style="background:#f9fafb; padding:12px; border-radius:6px; font-size:0.9rem;">
                                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                            <span style="color:#6b7280;">Asset:</span>
                                            <span style="font-weight:500;"><?= htmlspecialchars($aa['make_model']) ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                            <span style="color:#6b7280;">Tag:</span>
                                            <span style="font-family:monospace; color:#4f46e5;"><?= htmlspecialchars($aa['asset_tag']) ?></span>
                                        </div>
                                        <div style="display:flex; justify-content:space-between;">
                                            <span style="color:#6b7280;">Issued:</span>
                                            <span><?= date('M d, Y', strtotime($aa['assigned_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($active_assignments)): ?>
                                <div style="text-align:center; color:#6b7280; padding:20px;">No active assignments.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <!-- Add Asset -->
    <div id="addAssetModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Register New Asset</h2>
                <button class="close-btn" onclick="document.getElementById('addAssetModal').classList.remove('active')">&times;</button>
            </div>
            <form action="assets.php" method="POST">
                <input type="hidden" name="action" value="add_asset">
                <div class="form-group">
                    <label class="form-label">Asset Tag (e.g. LPT-001)</label>
                    <input type="text" name="asset_tag" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        <option value="Laptop">Laptop</option>
                        <option value="Monitor">Monitor</option>
                        <option value="Mobile Device">Mobile Device</option>
                        <option value="Software License">Software License</option>
                        <option value="Access Card">Access Card</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Make & Model</label>
                    <input type="text" name="make_model" class="form-input" placeholder="e.g. MacBook Pro 16 M2" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Serial Number</label>
                    <input type="text" name="serial_number" class="form-input">
                </div>
                <button type="submit" class="btn-primary" style="width:100%;">Save Asset</button>
            </form>
        </div>
    </div>

    <!-- Assign Asset -->
    <div id="assignModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Assign Equipment</h2>
                <button class="close-btn" onclick="document.getElementById('assignModal').classList.remove('active')">&times;</button>
            </div>
            <form action="assets.php" method="POST">
                <input type="hidden" name="action" value="assign_asset">
                <input type="hidden" name="asset_id" id="assign_asset_id">
                
                <div class="form-group">
                    <label class="form-label">Item to Assign</label>
                    <input type="text" id="assign_asset_display" class="form-input" disabled style="background:#f3f4f6;">
                </div>

                <div class="form-group">
                    <label class="form-label">Assign To Employee</label>
                    <select name="assigned_to_email" class="form-select" required>
                        <option value="">-- Choose Employee --</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['full_name']) ?> (<?= htmlspecialchars($u['role']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Notes (Optional)</label>
                    <input type="text" name="notes" class="form-input" placeholder="e.g. Brand new condition">
                </div>

                <button type="submit" class="btn-primary" style="width:100%;">Confirm Assignment</button>
            </form>
        </div>
    </div>

    <script>
        function openAssignModal(id, displayStr) {
            document.getElementById('assign_asset_id').value = id;
            document.getElementById('assign_asset_display').value = displayStr;
            document.getElementById('assignModal').classList.add('active');
        }
    </script>
</body>
</html>
