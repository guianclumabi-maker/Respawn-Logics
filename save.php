<?php
header('Content-Type: application/json');
require_once __DIR__ . '/bootstrap/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['organization']['domain'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input data']);
    exit;
}

$org = $input['organization'];
$nodes = json_encode($input['nodes']);
$connections = json_encode($input['connections']);
$columnNames = json_encode($input['columnNames']);
$columnTypes = json_encode($input['columnTypes']);

try {
    $stmt = $pdo->prepare("INSERT INTO `organization_canvas` 
        (`org_name`, `org_domain`, `org_industry`, `org_size`, `nodes`, `connections`, `column_names`, `column_types`)
        VALUES (:name, :domain, :industry, :size, :nodes, :connections, :col_names, :col_types)
        ON DUPLICATE KEY UPDATE
        `org_name` = VALUES(`org_name`),
        `org_industry` = VALUES(`org_industry`),
        `org_size` = VALUES(`org_size`),
        `nodes` = VALUES(`nodes`),
        `connections` = VALUES(`connections`),
        `column_names` = VALUES(`column_names`),
        `column_types` = VALUES(`column_types`)");
        
    $stmt->execute([
        ':name' => $org['name'],
        ':domain' => $org['domain'],
        ':industry' => $org['industry'],
        ':size' => $org['size'],
        ':nodes' => $nodes,
        ':connections' => $connections,
        ':col_names' => $columnNames,
        ':col_types' => $columnTypes
    ]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
