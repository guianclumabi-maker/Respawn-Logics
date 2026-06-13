<?php
header('Content-Type: application/json');
require_once __DIR__ . '/bootstrap/app.php';

$domain = isset($_GET['domain']) ? trim($_GET['domain']) : '';

if (empty($domain)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing domain parameter']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `organization_canvas` WHERE `org_domain` = :domain");
    $stmt->execute([':domain' => $domain]);
    $row = $stmt->fetch();
    
    if ($row) {
        echo json_encode([
            'found' => true,
            'organization' => [
                'name' => $row['org_name'],
                'domain' => $row['org_domain'],
                'industry' => $row['org_industry'],
                'size' => $row['org_size']
            ],
            'nodes' => json_decode($row['nodes'], true),
            'connections' => json_decode($row['connections'], true),
            'columnNames' => json_decode($row['column_names'], true),
            'columnTypes' => json_decode($row['column_types'], true)
        ]);
    } else {
        echo json_encode(['found' => false]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
