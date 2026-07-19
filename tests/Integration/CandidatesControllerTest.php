<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

class CandidatesControllerTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantA;
    protected static $tenantB;
    protected static $adminUser;
    protected static $employeeUser;
    protected static $tenantBUser;

    protected static $jobA;
    protected static $candidateA;
    protected static $appA;

    public static function setUpBeforeClass(): void
    {
        self::startServer();

        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        self::$tenantA = \FixtureHelper::createTenant($pdo, 'Tenant A');
        self::$tenantB = \FixtureHelper::createTenant($pdo, 'Tenant B');

        self::$adminUser    = \FixtureHelper::createUser($pdo, self::$tenantA, 'admin@tenantA.com', 'Super_Admin');
        self::$employeeUser = \FixtureHelper::createUser($pdo, self::$tenantA, 'emp@tenantA.com', 'Employee');
        self::$tenantBUser  = \FixtureHelper::createUser($pdo, self::$tenantB, 'admin@tenantB.com', 'Super_Admin');

        self::$jobA       = \FixtureHelper::createJob($pdo, self::$tenantA, 'Tenant A Job');
        self::$candidateA = \FixtureHelper::createCandidate($pdo, self::$tenantA, 'Tenant A Candidate');
        self::$appA       = \FixtureHelper::createApplication($pdo, self::$tenantA, self::$candidateA, self::$jobA, 'Applied');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    public function testTenantIsolation(): void
    {
        self::loginAs('admin@tenantB.com'); // Tenant B admin
        $r = self::apiGet('/api/index.php?route=candidates&action=candidate&id=' . self::$candidateA);
        // Tenant B must not read Tenant A's candidate.
        $this->assertFalse($r['json']['success'] ?? false, 'cross-tenant read must not succeed');
    }

    public function testAuthorizationForReadsAndWrites(): void
    {
        // Plain employee has no ATS permission -> forbidden.
        self::loginAs('emp@tenantA.com');
        $r = self::apiGet('/api/index.php?route=candidates&action=candidates');
        $this->assertSame(403, $r['code'], 'employee without ats.view should be forbidden');

        // Admin has ATS permission -> allowed.
        self::loginAs('admin@tenantA.com');
        $r = self::apiGet('/api/index.php?route=candidates&action=candidates');
        $this->assertSame(200, $r['code'], 'admin should be allowed');
    }

    public function testInputValidationRejectsInvalidData(): void
    {
        self::loginAs('admin@tenantA.com');
        $base = '/api/index.php?route=candidates';

        // Invalid email format.
        $r = self::apiPost($base, ['action' => 'add_candidate', 'name' => 'Valid Name', 'email' => 'invalid-email-format']);
        $this->assertSame(400, $r['code']);
        $this->assertStringContainsString('Invalid email', (string) $r['body']);

        // Unknown stage.
        $r = self::apiPost($base, ['action' => 'update_stage', 'id' => self::$appA, 'stage' => 'Galactic Overlord']);
        $this->assertSame(400, $r['code']);
        $this->assertStringContainsString('Invalid stage', (string) $r['body']);
    }

    public function testStageTransitionsClearTimestamps(): void
    {
        global $pdo;
        self::loginAs('admin@tenantA.com');
        $base = '/api/index.php?route=candidates';
        $stmt = $pdo->prepare("SELECT hired_at FROM candidate_applications WHERE id = ?");

        // GUARD: moving to Hired via update_stage is REJECTED while no employee
        // record exists for the candidate (drag-to-Hired must not skip hire_candidate,
        // which is what actually creates the employee + credentials).
        $r = self::apiPost($base, ['action' => 'update_stage', 'id' => self::$appA, 'stage' => 'Hired']);
        $this->assertSame(400, $r['code'], 'Hired without an employee record must be rejected');
        $stmt->execute([self::$appA]);
        $this->assertNull($stmt->fetchColumn(), 'hired_at must stay null when the guard rejects');

        // Once an employee with the candidate's email exists in the tenant,
        // the transition is allowed and hired_at is set.
        \FixtureHelper::createUser($pdo, self::$tenantA, 'jane@example.com', 'Employee');
        self::apiPost($base, ['action' => 'update_stage', 'id' => self::$appA, 'stage' => 'Hired']);
        $stmt->execute([self::$appA]);
        $this->assertNotNull($stmt->fetchColumn(), 'hired_at should be set');

        // Transition back to Review -> hired_at should be cleared.
        self::apiPost($base, ['action' => 'update_stage', 'id' => self::$appA, 'stage' => 'Review']);
        $stmt->execute([self::$appA]);
        $this->assertNull($stmt->fetchColumn(), 'hired_at should be cleared');
    }
}
