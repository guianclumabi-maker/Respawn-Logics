<?php
require 'tests/bootstrap.php';
global $pdo;
echo "In transaction: " . ($pdo->inTransaction() ? 'YES' : 'NO') . "\n";
