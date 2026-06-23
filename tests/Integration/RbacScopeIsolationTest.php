<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use PDO;
use ScopeResolver;

class RbacScopeIsolationTest extends TestCase
{
    private $pdo;
    private $tenantId = '999001';
    
    private $adminId;
    private $engMgrId;
    private $engIC1Id;
    private $engIC2Id;
    private $salesMgrId;
    private $salesICId;
    private $directReportId;

    protected function setUp(): void
    {
        global $pdo;
        require_once __DIR__ . '/../../bootstrap/app.php';
        require_once __DIR__ . '/../../backend/services/ScopeResolver.php';
        $this->pdo = $pdo;

        $this->cleanup();

        // 1. Seed org_units
        $this->pdo->exec("INSERT INTO org_units (id, tenant_id, name, parent_id) VALUES 
            (1, '{$this->tenantId}', 'Engineering', NULL),
            (2, '{$this->tenantId}', 'Eng - Backend', 1),
            (3, '{$this->tenantId}', 'Sales', NULL)
        ");

        // 2. Seed roles
        $this->pdo->exec("INSERT INTO roles (id, tenant_id, name) VALUES 
            (990, '{$this->tenantId}', 'Admin Role'),
            (991, '{$this->tenantId}', 'Department Manager Role')
        ");

        // 3. Seed users
        $this->adminId = $this->createUser('admin@test', 990, 'tenant', null, null);
        $this->engMgrId = $this->createUser('eng.mgr@test', 991, 'department', 1, 1);
        $this->engIC1Id = $this->createUser('eng.ic1@test', null, null, 1, null);
        $this->engIC2Id = $this->createUser('eng.ic2@test', null, null, 2, null);
        $this->salesMgrId = $this->createUser('sales.mgr@test', 991, 'department', 3, 3);
        $this->salesICId = $this->createUser('sales.ic@test', null, null, 3, null);
        
        $this->directReportId = $this->createUser('direct.report@test', null, null, 3, $this->engMgrId);
    }

    private function createUser($email, $roleId, $scope, $orgUnitId, $managerId)
    {
        $stmt = $this->pdo->prepare("INSERT INTO users (tenant_id, full_name, email, password_hash, org_unit_id, manager_id) VALUES (?, 'Test User', ?, 'pass', ?, ?)");
        $stmt->execute([$this->tenantId, $email, $orgUnitId, $managerId]);
        $userId = (int)$this->pdo->lastInsertId();

        if ($roleId) {
            $stmtRole = $this->pdo->prepare("INSERT INTO user_roles (user_id, role_id, scope, org_unit_id) VALUES (?, ?, ?, ?)");
            $stmtRole->execute([$userId, $roleId, $scope, $orgUnitId]);
        }
        
        return $userId;
    }

    private function cleanup(): void
    {
        if ($this->pdo) {
            $this->pdo->exec("DELETE FROM user_roles WHERE user_id IN (SELECT id FROM users WHERE tenant_id = '{$this->tenantId}')");
            $this->pdo->exec("DELETE FROM users WHERE tenant_id = '{$this->tenantId}'");
            $this->pdo->exec("DELETE FROM roles WHERE tenant_id = '{$this->tenantId}'");
            $this->pdo->exec("DELETE FROM org_units WHERE tenant_id = '{$this->tenantId}'");
        }
    }

    protected function tearDown(): void
    {
        $this->cleanup();
    }

    private function getUserArray($id, $email)
    {
        return [
            'id' => $id,
            'tenant_id' => $this->tenantId,
            'email' => $email
        ];
    }

    public function testTenantScope()
    {
        $user = $this->getUserArray($this->adminId, 'admin@test');
        $accessible = ScopeResolver::getAccessibleUserIds($this->pdo, $user);
        $this->assertEquals(ScopeResolver::ALL_USERS, $accessible);
        
        $clause = ScopeResolver::getScopeWhereClause($this->pdo, $user);
        $this->assertStringContainsString("1=1", $clause);
    }

    public function testDepartmentScopeHierarchy()
    {
        $user = $this->getUserArray($this->engMgrId, 'eng.mgr@test');
        $accessible = ScopeResolver::getAccessibleUserIds($this->pdo, $user);
        
        $this->assertIsArray($accessible);
        $this->assertContains($this->engMgrId, $accessible);
        $this->assertContains($this->engIC1Id, $accessible);
        $this->assertContains($this->engIC2Id, $accessible);
        
        $this->assertNotContains($this->salesICId, $accessible);
        $this->assertNotContains($this->salesMgrId, $accessible);
    }

    public function testDepartmentScopeManagerIdSafeguard()
    {
        $user = $this->getUserArray($this->engMgrId, 'eng.mgr@test');
        $accessible = ScopeResolver::getAccessibleUserIds($this->pdo, $user);
        
        $this->assertContains($this->directReportId, $accessible);
    }

    public function testCrossDepartmentIsolation()
    {
        $user = $this->getUserArray($this->salesMgrId, 'sales.mgr@test');
        $accessible = ScopeResolver::getAccessibleUserIds($this->pdo, $user);
        
        $this->assertIsArray($accessible);
        $this->assertContains($this->salesMgrId, $accessible);
        $this->assertContains($this->salesICId, $accessible);
        $this->assertContains($this->directReportId, $accessible);
        
        $this->assertNotContains($this->engMgrId, $accessible);
        $this->assertNotContains($this->engIC1Id, $accessible);
        $this->assertNotContains($this->engIC2Id, $accessible);
    }

    public function testHasScopedAccess()
    {
        $user = $this->getUserArray($this->engMgrId, 'eng.mgr@test');
        $this->assertTrue(ScopeResolver::hasScopedAccess($this->pdo, $user, $this->engIC2Id));
        $this->assertFalse(ScopeResolver::hasScopedAccess($this->pdo, $user, $this->salesICId));
    }

    public function testFailClosedNoOrgUnit()
    {
        $failClosedId = $this->createUser('fail.closed@test', 991, 'department', null, null);
        
        $user = $this->getUserArray($failClosedId, 'fail.closed@test');
        $accessible = ScopeResolver::getAccessibleUserIds($this->pdo, $user);
        
        $this->assertIsArray($accessible);
        $this->assertCount(1, $accessible);
        $this->assertEquals([$failClosedId], $accessible);
    }

    public function testGetScopeWhereClauseAliases()
    {
        $userEmpty = $this->getUserArray($this->salesICId, 'sales.ic@test');
        $clauseEmpty = ScopeResolver::getScopeWhereClause($this->pdo, $userEmpty, 'usr');
        $this->assertStringContainsString("usr.id IN (", $clauseEmpty);
        
        $userEngMgr = $this->getUserArray($this->engMgrId, 'eng.mgr@test');
        $clauseEng = ScopeResolver::getScopeWhereClause($this->pdo, $userEngMgr, 'employee');
        $this->assertStringContainsString("employee.id IN (", $clauseEng);
        $this->assertStringNotContainsString((string)$this->salesMgrId, $clauseEng);
    }
}
