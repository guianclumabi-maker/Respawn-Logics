<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Locks the server-side authorization gates so a future refactor can't silently reopen them.
 * A low-permission Employee must be denied (403) on sensitive writes; a Super_Admin must not be.
 * (Routes verified against api/index.php: payroll is 'payroll_engine'.)
 */
class AuthorizationRegressionTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;
        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Authz Regression Tenant');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'employee@authz.test', 'Employee');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'superadmin@authz.test', 'Super_Admin');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    public function testEmployeeDeniedEmployeeRelationsWrite(): void
    {
        self::loginAs('employee@authz.test');
        $r = self::apiPost('/api/index.php?route=employee_relations&action=add', ['action' => 'add', 'name' => 'Test Case']);
        $this->assertSame(403, $r['code'], 'Employee must be 403 on ELR add. Body: ' . ($r['body'] ?? ''));
    }

    public function testEmployeeDeniedPayrollRun(): void
    {
        self::loginAs('employee@authz.test');
        $r = self::apiPost('/api/index.php?route=payroll_engine&action=generate_run', ['schedule_id' => 1]);
        $this->assertSame(403, $r['code'], 'Employee must be 403 on payroll generate_run. Body: ' . ($r['body'] ?? ''));
    }

    public function testEmployeeDeniedIamRoleAssign(): void
    {
        self::loginAs('employee@authz.test');
        $r = self::apiPost('/api/index.php?route=iam&action=assign_role', ['user_id' => 1, 'role_id' => 1]);
        $this->assertSame(403, $r['code'], 'Employee must be 403 on IAM assign_role. Body: ' . ($r['body'] ?? ''));
    }

    public function testEmployeeDeniedSuspendEmployee(): void
    {
        self::loginAs('employee@authz.test');
        $r = self::apiPost('/api/index.php?route=core_hr&action=suspend_employee', ['employee_id' => 1, 'reason' => 'x']);
        $this->assertSame(403, $r['code'], 'Employee must be 403 on suspend_employee. Body: ' . ($r['body'] ?? ''));
    }

    public function testSuperAdminNotDeniedEmployeeRelationsWrite(): void
    {
        self::loginAs('superadmin@authz.test');
        $r = self::apiPost('/api/index.php?route=employee_relations&action=add', ['action' => 'add', 'name' => 'Case By Admin']);
        $this->assertNotSame(403, $r['code'], 'Super_Admin must NOT be 403 on ELR add. Body: ' . ($r['body'] ?? ''));
    }
}
