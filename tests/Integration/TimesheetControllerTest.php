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

    public function testGenerateDraftFromAttendance(): void
    {
        global $pdo;

        // Seed two attendance punches for the employee (weekdays, no holiday):
        //  - 8h shift  -> 60min break deducted -> 7h regular
        //  - 11h shift -> 60min break deducted -> 10h worked -> 8 regular + 2 overtime
        $ins = $pdo->prepare("INSERT INTO `attendance` (`tenant_id`, `employee_email`, `time_in`, `time_out`) VALUES (?, 'ts.emp@test.com', ?, ?)");
        $ins->execute([self::$tenantId, '2026-07-06 09:00:00', '2026-07-06 17:00:00']); // Mon
        $ins->execute([self::$tenantId, '2026-07-07 09:00:00', '2026-07-07 20:00:00']); // Tue

        self::loginAs('ts.admin@test.com');
        $gen = self::apiPost($this->base() . '&action=generate_draft', [
            'start_date' => '2026-07-06', 'end_date' => '2026-07-07', 'employee_id' => self::$empId,
        ]);
        $this->assertTrue($gen['json']['success'] ?? false, 'generate_draft should succeed: ' . $gen['body']);
        $this->assertSame(2, (int) ($gen['json']['drafted'] ?? 0));

        $list = self::apiGet($this->base() . '&action=list&start_date=2026-07-06&end_date=2026-07-07&employee_id=' . self::$empId);
        $byDate = [];
        foreach ($list['json']['timesheets'] as $t) { $byDate[$t['timesheet_date']] = $t; }

        $this->assertArrayHasKey('2026-07-06', $byDate);
        $this->assertEqualsWithDelta(7.0, (float) $byDate['2026-07-06']['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $byDate['2026-07-06']['overtime_hours'], 0.01);
        $this->assertSame('Pending', $byDate['2026-07-06']['status']);

        $this->assertArrayHasKey('2026-07-07', $byDate);
        $this->assertEqualsWithDelta(8.0, (float) $byDate['2026-07-07']['regular_hours'], 0.01);
        $this->assertEqualsWithDelta(2.0, (float) $byDate['2026-07-07']['overtime_hours'], 0.01);
    }

    public function testGenerateDraftDoesNotOverwriteApproved(): void
    {
        // 2026-06-01 was approved in testSaveApproveAndList. A punch on the same day
        // must NOT clobber the manager-approved row.
        global $pdo;
        $pdo->prepare("INSERT INTO `attendance` (`tenant_id`, `employee_email`, `time_in`, `time_out`) VALUES (?, 'ts.emp@test.com', '2026-06-01 08:00:00', '2026-06-01 18:00:00')")
            ->execute([self::$tenantId]);

        self::loginAs('ts.admin@test.com');
        $gen = self::apiPost($this->base() . '&action=generate_draft', [
            'start_date' => '2026-06-01', 'end_date' => '2026-06-01', 'employee_id' => self::$empId,
        ]);
        $this->assertTrue($gen['json']['success'] ?? false);
        $this->assertSame(1, (int) ($gen['json']['skipped_approved'] ?? 0));

        $list = self::apiGet($this->base() . '&action=list&start_date=2026-06-01&end_date=2026-06-01&employee_id=' . self::$empId);
        $this->assertSame('Approved', $list['json']['timesheets'][0]['status']);
        $this->assertEqualsWithDelta(8.0, (float) $list['json']['timesheets'][0]['regular_hours'], 0.01);
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
