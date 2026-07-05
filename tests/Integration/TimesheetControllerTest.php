<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Guards the timesheet entry + approval flow that feeds payroll:
 * save (Pending) -> approve (Approved) -> list, plus tenant isolation.
 */
class TimesheetControllerTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantId;
    protected static $empId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        self::$tenantId = \FixtureHelper::createTenant($pdo, 'TS Tenant');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'ts.admin@test.com', 'Super_Admin');
        self::$empId = \FixtureHelper::createUser($pdo, self::$tenantId, 'ts.emp@test.com', 'Employee');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    private function base(): string
    {
        return '/api/index.php?route=timesheets';
    }

    public function testSaveApproveAndList(): void
    {
        self::loginAs('ts.admin@test.com');

        // Save a draft timesheet -> starts Pending.
        $r = self::apiPost($this->base() . '&action=save', [
            'employee_id' => self::$empId, 'timesheet_date' => '2026-06-01', 'regular_hours' => 8,
        ]);
        $this->assertTrue($r['json']['success'] ?? false, 'save should succeed: ' . $r['body']);

        $list = self::apiGet($this->base() . '&action=list&start_date=2026-06-01&end_date=2026-06-01&employee_id=' . self::$empId);
        $this->assertTrue($list['json']['success'] ?? false);
        $this->assertCount(1, $list['json']['timesheets']);
        $this->assertSame('Pending', $list['json']['timesheets'][0]['status']);
        $tsId = (int) $list['json']['timesheets'][0]['id'];

        // Approve it -> the compliance checkpoint.
        $a = self::apiPost($this->base() . '&action=approve', ['ids' => [$tsId]]);
        $this->assertTrue($a['json']['success'] ?? false);

        $list2 = self::apiGet($this->base() . '&action=list&start_date=2026-06-01&end_date=2026-06-01&employee_id=' . self::$empId);
        $this->assertSame('Approved', $list2['json']['timesheets'][0]['status']);
    }

    public function testTenantIsolation(): void
    {
        global $pdo;
        $otherTenant = \FixtureHelper::createTenant($pdo, 'TS Other');
        \FixtureHelper::createUser($pdo, $otherTenant, 'ts.other@test.com', 'Super_Admin');

        self::loginAs('ts.other@test.com');
        $list = self::apiGet($this->base() . '&action=list&start_date=2026-06-01&end_date=2026-06-30');
        $this->assertTrue($list['json']['success'] ?? false);
        foreach (($list['json']['timesheets'] ?? []) as $t) {
            $this->assertNotSame((int) self::$empId, (int) $t['employee_id'], 'must not see another tenant\'s timesheets');
        }
    }
}
