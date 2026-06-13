<?php
// Requires: $supportMode ('platform' or 'esm'), $supportRole ('agent' or 'client'), $pageTitle
require_once __DIR__ . '/../bootstrap/app.php';
requireLogin();

$user = getCurrentUser();

$ticketTable = $supportMode === 'platform' ? 'platform_tickets' : 'service_tickets';
$commentTable = $supportMode === 'platform' ? 'platform_ticket_comments' : 'ticket_comments';
$apiRoute = $supportMode === 'platform' ? 'platform_support' : 'esm_support';

// Determine query conditions based on role
$where = "";
$params = [];

if ($supportRole === 'client') {
    // Client sees only their own tickets (for ESM it's their created tickets, for Platform it's their tenant's tickets if they are a tenant user)
    if ($supportMode === 'platform') {
        $where = "WHERE pt.tenant_id = ?";
        $params[] = $user['tenant_id'];
    } else {
        $where = "WHERE pt.tenant_id = ? AND pt.created_by = ?";
        $params[] = $user['tenant_id'];
        $params[] = $user['id'];
    }
} else {
    // Agent sees all tickets in their domain
    if ($supportMode === 'esm') {
        $where = "WHERE pt.tenant_id = ?";
        $params[] = $user['tenant_id'];
    }
    // Platform Agent sees everything (no where clause needed)
}

$assignedColumn = $supportMode === 'platform' ? 'assigned_to' : 'assigned_to_user_id';

// Map Database Tickets to React UI format
$sqlTickets = "
    SELECT pt.*, 
           u.first_name, u.last_name, u.email,
           t.company_name as tenant_name,
           a.first_name as a_first_name, a.last_name as a_last_name
    FROM {$ticketTable} pt
    LEFT JOIN users u ON pt.created_by = u.id
    LEFT JOIN tenants t ON pt.tenant_id = t.id
    LEFT JOIN users a ON pt.{$assignedColumn} = a.id
    $where
    ORDER BY pt.created_at DESC
";
$stmt = $pdo->prepare($sqlTickets);
$stmt->execute($params);
$dbTickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch comments for these tickets
$ticketIds = array_column($dbTickets, 'id');
$commentsByTicket = [];

if (!empty($ticketIds)) {
    $inClause = implode(',', array_map('intval', $ticketIds));
    $stmtComments = $pdo->query("
        SELECT ptc.*, 
               u.first_name, u.last_name, u.email,
               ur.role_id, r.name as role_name
        FROM {$commentTable} ptc
        LEFT JOIN users u ON ptc.user_id = u.id
        LEFT JOIN user_roles ur ON u.id = ur.user_id
        LEFT JOIN roles r ON ur.role_id = r.id
        WHERE ptc.ticket_id IN ($inClause)
        ORDER BY ptc.created_at ASC
    ");
    $dbComments = $stmtComments->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dbComments as $comment) {
        $tid = $comment['ticket_id'];
        if (!isset($commentsByTicket[$tid])) {
            $commentsByTicket[$tid] = [];
        }
        
        $authorName = ($comment['first_name'] || $comment['last_name']) ? trim($comment['first_name'] . ' ' . $comment['last_name']) : $comment['email'];
        if (!$authorName) $authorName = "Unknown User";
        $authorInitials = strtoupper(substr($comment['first_name'] ?? 'U', 0, 1) . substr($comment['last_name'] ?? 'U', 0, 1));
        
        $isAgent = in_array($comment['role_name'] ?? '', ['Platform_Admin', 'Support_Agent', 'Implementation_Specialist', 'Admin', 'Master Admin', 'Master_Admin']) ? true : false; 
        $attachments = !empty($comment['attachments']) ? json_decode($comment['attachments'], true) : [];

        $commentsByTicket[$tid][] = [
            'id' => 'm' . $comment['id'],
            'author' => [
                'name' => $authorName,
                'initials' => $authorInitials,
                'color' => $isAgent ? "#6366f1" : "#3b82f6",
                'role' => $isAgent ? 'agent' : 'user'
            ],
            'body' => $comment['comment'],
            'timestamp' => gmdate("Y-m-d\TH:i:s\Z", strtotime($comment['created_at'])),
            'internal' => str_starts_with($comment['comment'], '[SYSTEM]'),
            'attachments' => $attachments
        ];
    }
}

// Fetch all possible agents for the Assignee picker
$reactAgents = [];
if ($supportMode === 'platform') {
    $stmtAgents = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        WHERE r.name IN ('Platform_Admin', 'Support_Agent', 'Implementation_Specialist')
    ");
} else {
    $stmtAgents = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        JOIN user_roles ur ON u.id = ur.user_id
        JOIN roles r ON ur.role_id = r.id
        WHERE r.name IN ('Admin', 'HR') AND u.tenant_id = ?
    ");
    $stmtAgents->execute([$user['tenant_id']]);
}
$dbAgents = $stmtAgents->fetchAll(PDO::FETCH_ASSOC);
foreach ($dbAgents as $a) {
    $aName = ($a['first_name'] || $a['last_name']) ? trim($a['first_name'] . ' ' . $a['last_name']) : $a['email'];
    $aInitials = strtoupper(substr($a['first_name'] ?? 'A', 0, 1) . substr($a['last_name'] ?? 'A', 0, 1));
    $reactAgents[] = [
        'id' => $a['id'],
        'name' => $aName,
        'initials' => $aInitials,
        'color' => "#6366f1"
    ];
}

// Fetch Tags
$tagsByTicket = [];
if (!empty($ticketIds)) {
    $inClause = implode(',', array_map('intval', $ticketIds));
    $stmtTags = $pdo->query("SELECT ticket_id, tag FROM {$tablePrefix}_ticket_tags WHERE ticket_id IN ($inClause)");
    $dbTags = $stmtTags->fetchAll(PDO::FETCH_ASSOC);
    foreach ($dbTags as $tagRow) {
        $tid = $tagRow['ticket_id'];
        if (!isset($tagsByTicket[$tid])) $tagsByTicket[$tid] = [];
        $tagsByTicket[$tid][] = $tagRow['tag'];
    }
}

$reactTickets = [];
foreach ($dbTickets as $db) {
    $statusMap = [
        'Open' => 'open',
        'In Progress' => 'in_progress',
        'Resolved' => 'resolved',
        'Closed' => 'closed'
    ];
    $status = $statusMap[$db['status']] ?? 'open';
    $priority = strtolower($db['priority']);

    $reporterName = ($db['first_name'] || $db['last_name']) ? trim($db['first_name'] . ' ' . $db['last_name']) : $db['email'];
    if (!$reporterName) $reporterName = "Unknown User";
    $reporterInitials = strtoupper(substr($db['first_name'] ?? 'U', 0, 1) . substr($db['last_name'] ?? 'U', 0, 1));

    $assignee = null;
    if ($db['assigned_to']) {
        $aName = trim($db['a_first_name'] . ' ' . $db['a_last_name']);
        if (!$aName) $aName = "Agent";
        $aInitials = strtoupper(substr($db['a_first_name'] ?? 'A', 0, 1) . substr($db['a_last_name'] ?? 'A', 0, 1));
        $assignee = [
            'name' => $aName,
            'initials' => $aInitials,
            'color' => "#6366f1"
        ];
    }

    $reactTickets[] = [
        'id' => 'TKT-' . $db['id'],
        'title' => $db['subject'],
        'description' => $db['description'],
        'status' => $status,
        'priority' => $priority,
        'category' => $supportMode === 'platform' && $db['tenant_name'] ? "Tenant: " . $db['tenant_name'] : "Internal",
        'assignee' => $assignee,
        'reporter' => [
            'name' => $reporterName,
            'initials' => $reporterInitials,
            'color' => "#3b82f6"
        ],
        'created' => gmdate("Y-m-d\TH:i:s\Z", strtotime($db['created_at'])),
        'updated' => gmdate("Y-m-d\TH:i:s\Z", strtotime($db['updated_at'])),
        'sla_breach_at' => $db['sla_breach_at'] ? gmdate("Y-m-d\TH:i:s\Z", strtotime($db['sla_breach_at'])) : null,
        'csat_score' => $db['csat_score'] ?? null,
        'csat_comment' => $db['csat_comment'] ?? null,
        'messages' => $commentsByTicket[$db['id']] ?? [],
        'tags' => array_values(array_unique(array_merge([$db['tenant_name'] ?? 'General'], $tagsByTicket[$db['id']] ?? [])))
    ];
}

$assetDir = __DIR__ . '/../assets/ticket-dashboard';
$jsFile = ''; $cssFile = '';
if (is_dir($assetDir)) {
    $files = scandir($assetDir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'js') $jsFile = $file;
        elseif (pathinfo($file, PATHINFO_EXTENSION) === 'css') $cssFile = $file;
    }
}
?>
<?php $page_title = $pageTitle; ?>
<?php include __DIR__ . '/../includes/head.php'; ?>
<body>
    <script>
        window.__INITIAL_DATA__ = <?= json_encode(array_values($reactTickets)) ?>;
        window.__AGENTS__ = <?= json_encode(array_values($reactAgents)) ?>;
        window.__ROLE__ = <?= json_encode($supportRole) ?>;
        window.__API_ROUTE__ = <?= json_encode($apiRoute) ?>;
        window.__CSRF_TOKEN__ = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
        window.__USER_INITIALS__ = <?= json_encode(strtoupper(substr($user['first_name'] ?? 'U', 0, 1) . substr($user['last_name'] ?? 'U', 0, 1))) ?>;
    </script>
    <div id="root"></div>
    <?php if ($jsFile): ?>
        <script type="module" crossorigin src="<?= url('/assets/ticket-dashboard/' . $jsFile) ?>"></script>
    <?php else: ?>
        <div style="color:white; text-align:center; padding: 50px;">
            <h2>React App Not Built</h2>
            <p>Please run <code>npm run build</code> in the ticket dashboard directory.</p>
        </div>
    <?php endif; ?>
</body>
</html>
