<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

class ShiftControllerTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantA;
    protected static $tenantB;
    protected static $shiftIdsA = [];

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        // Ensure shifts table exists in test DB
        $pdo->exec("CREATE TABLE IF NOT EXISTS `shifts` (
            `id` BIGINT PRIMARY KEY AUTO_INCREMENT,
            `tenant_id` VARCHAR(50) NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `start_time` TIME NOT NULL,
            `end_time` TIME NOT NULL,
            `color` VARCHAR(20) DEFAULT NULL,
            `created_by` BIGINT DEFAULT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        self::$tenantA = \FixtureHelper::createTenant($pdo, 'Shift Tenant A');
        self::$tenantB = \FixtureHelper::createTenant($pdo, 'Shift Tenant B');
        \FixtureHelper::createUser($pdo, self::$tenantA, 'shiftadminA@test.com', 'Super_Admin');
        \FixtureHelper::createUser($pdo, self::$tenantB, 'shiftadminB@test.com', 'Super_Admin');
    }

    private function base(): string
    {
        return '/api/index.php?route=shifts';
    }

    public function testDefaultShiftTypesAutoSeededAndIdempotent(): void
    {
        self::loginAs('shiftadminA@test.com');
        $r = self::apiGet($this->base() . '&action=fetch_shift_types');
        $this->assertTrue($r['json']['success'] ?? false, 'fetch_shift_types should succeed: ' . $r['body']);
        
        $shifts = $r['json']['data'] ?? [];
        $this->assertCount(3, $shifts, 'fresh tenant should get exactly 3 default shifts');

        // Check if idempotent
        $r2 = self::apiGet($this->base() . '&action=fetch_shift_types');
        $shifts2 = $r2['json']['data'] ?? [];
        $this->assertCount(3, $shifts2, 'second call should still return exactly 3 shifts, no duplicates');

        self::$shiftIdsA = array_map(fn($s) => (int)$s['id'], $shifts);
    }

    public function testTenantIsolation(): void
    {
        self::loginAs('shiftadminB@test.com');
        $r = self::apiGet($this->base() . '&action=fetch_shift_types');
        $this->assertTrue($r['json']['success'] ?? false, 'fetch_shift_types should succeed for Tenant B');
        
        $shiftsB = $r['json']['data'] ?? [];
        $this->assertCount(3, $shiftsB, 'Tenant B should also get exactly 3 seeded shifts');

        $shiftIdsB = array_map(fn($s) => (int)$s['id'], $shiftsB);
        $overlap = array_intersect(self::$shiftIdsA, $shiftIdsB);
        $this->assertEmpty($overlap, 'Tenant B should not see Tenant A shift IDs');
    }
}
