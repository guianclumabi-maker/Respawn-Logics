<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class PDOConnectionTest extends TestCase
{
    public function testWhichDB()
    {
        global $pdo;
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        echo "\n\nPHPUnit Parent PDO is connected to: " . $db . "\n\n";
        $this->assertEquals('employee_system_test', $db);
    }
}
