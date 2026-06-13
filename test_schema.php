<?php
require_once 'config/db_respawn.php';
echo "Schema OK\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables (" . count($tables) . "): " . implode(", ", $tables) . "\n";
foreach ($tables as $t) {
    $cols = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'")->fetchColumn();
    echo "  $t: $cols columns\n";
}
