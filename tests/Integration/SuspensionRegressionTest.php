<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Locks the employee suspension / reinstatement feature:
 *  - HR can suspend (status -> Suspended); a suspended employee is excluded from payroll and blocked from clock-in.
 *  - Cross-tenant suspend is rejected (IDOR guard in SuspensionService).
 *  - Reinstatement restores Active and closes the suspension record.
 * (Restored: this file was lost in a workspace reset after first being written.)
 */
class SuspensionRegressionTest extends TestCase
{
    use HttpTestServer;

    protected static $pdo;
    protected static $tenantA;
    protected static $tenantB;
    protected static $empId;        // Employee in tenant A (on a payroll schedule)
    protected static $foreignEmpId; // Employee in tenant B
    protected static $schedId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;
        self::$pdo = $pdo;

        self::$tenantA = \FixtureHelper::createTenant($pdo, 'Suspension Tenant A');
        self::$tenantB = \FixtureHelper::createTenant($pdo, 'Suspension Tenant B');

        \FixtureHelper::createUser($pdo, self::$tenantA, 'hr@susp.test', 'Super_Admin');
        self::$empId        = \FixtureHelper::createUser($pdo, self::$tenantA, 'emp@susp.test', 'Employee');
        self::$foreignEmpId = \FixtureHelper::createUser($pdo, self::$tenantB, 'foreign@susp.test', 'Employee');

        // Put the tenant-A employee on a payroll schedule with a salary so payroll would pick
        // them up WHILE Active — the whole point is they drop out once Suspended.
        $pdo->prepare("INSERT INTO payroll_schedules (tenant_id, name, frequency) VALUES (?, 'Monthly', 'Monthly')")
            ->execute([self::$tenantA]);
        self::$schedId = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE users SET base_salary = 30000, payroll_schedule_id = ?, is_mwe = 0 WHERE id = ?")
            ->execute([self::$schedId, self::$empId]);
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    public function testHrCanSuspend(): void
    {
        self::loginAs('hr@susp.test');
        $r = self::apiPost('/api/index.php?route=core_hr&action=suspend_employee', [
            'employee_id' => self::$empId,
            'reason'      => 'Test suspension',
        ]);
        $this->assertTrue($r['json']['success'] ?? false, 'HR suspend should succeed. Body: ' . ($r['body'] ?? ''));

        $status = self::$pdo->query('SELECT employment_status FROM users WHERE id = ' . (int) self::$empId)->fetchColumn();
        $this->assertSame('Suspended', $status);
    }

    /** @depends testHrCanSuspend */
    public function testSuspendedEmployeeExcludedFromPayroll(): void
    {
        require_once __DIR__ . '/../../backend/services/PayrollService.php';
        $svc = new \PayrollService(self::$pdo);
        // The only employee on this schedule is now suspended -> no active employees to run.
        $res = $svc->generateRun(self::$tenantA, self::$schedId, '2026-06-01', '2026-06-30', '2026-06-30', self::$empId, 'Regular');
        $this->assertFalse($res['success'] ?? true, 'A suspended employee must not be included in a payroll run.');
    }

    /** @depends testHrCanSuspend */
    public function testSuspendedEmployeeCannotClockIn(): void
    {
        self::loginAs('emp@susp.test'); // login is not blocked; clock-in is
        $r = self::apiPost('/api/index.php?route=attendance&action=clock_in', []);
        $this->assertSame(403, $r['code'], 'Suspended employee must be 403 on clock-in. Body: ' . ($r['body'] ?? ''));
    }

    public function testCannotSuspendForeignTenantEmployee(): void
    {
        self::loginAs('hr@susp.test'); // tenant A HR
        $r = self::apiPost('/api/index.php?route=core_hr&action=suspend_employee', [
            'employee_id' => self::$foreignEmpId,
            'reason'      => 'IDOR attempt',
        ]);
        $this->assertFalse($r['json']['success'] ?? true, 'Must not be able to suspend an employee from another tenant.');
        $status = self::$pdo->query('SELECT employment_status FROM users WHERE id = ' . (int) self::$foreignEmpId)->fetchColumn();
        $this->assertSame('Active', $status, 'Foreign tenant employee must stay Active.');
    }

    /** @depends testHrCanSuspend */
    public function testReinstateRestoresActive(): void
    {
        self::loginAs('hr@susp.test');
        $r = self::apiPost('/api/index.php?route=core_hr&action=reinstate_employee', ['employee_id' => self::$empId]);
        $this->assertTrue($r['json']['success'] ?? false, 'Reinstate should succeed. Body: ' . ($r['body'] ?? ''));

        $status = self::$pdo->query('SELECT employment_status FROM users WHERE id = ' . (int) self::$empId)->fetchColumn();
        $this->assertSame('Active', $status);

        $record = self::$pdo->query('SELECT status FROM employee_suspensions WHERE employee_id = ' . (int) self::$empId . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
        $this->assertSame('Lifted', $record, 'The suspension record should be marked Lifted on reinstatement.');
    }
}
