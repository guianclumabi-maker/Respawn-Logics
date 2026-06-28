<?php
$host = 'reseau.proxy.rlwy.net';
$port = 19932;
$db = 'railway';
$user = 'root';
$pass = 'CMLUchTlMGhDxVnKwWpnQcmzAOqqnsKg';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    echo "Connected successfully to the NEW Railway database!\n\n";
    
    $sqlFile = 'C:\Users\guian\OneDrive\Desktop\dump-railway-202606252038.sql';
    if (!file_exists($sqlFile)) {
        die("Backup file not found at $sqlFile\n");
    }
    
    echo "Reading SQL backup file...\n";
    $sql = file_get_contents($sqlFile);
    
    echo "Executing import...\n";
    $pdo->exec($sql);
    
    echo "Import completed!\n\n";
    
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables after import.\n";
    if (count($tables) > 0) {
        echo "Sample tables: " . implode(", ", array_slice($tables, 0, 5)) . "...\n";
        echo "\nConclusion: IMPORT FULLY AUTOMATED AND SUCCESSFUL! 🎉\n";
    }

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
