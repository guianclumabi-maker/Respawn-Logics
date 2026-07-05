<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Guards the guided-tour progress endpoints: a tour starts un-completed, marking it complete
 * is reflected in status, reset clears it, and one user's progress never leaks to another.
 */
class TourControllerTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Tour Tenant');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'tour.a@test.com', 'Employee');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'tour.b@test.com', 'Employee');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    private function base(): string
    {
        return '/api/index.php?route=tours';
    }

    public function testCompleteThenStatusReflectsIt(): void
    {
        self::loginAs('tour.a@test.com');

        // Starts un-completed.
        $r0 = self::apiGet($this->base() . '&action=status&tour=payroll');
        $this->assertTrue($r0['json']['success'] ?? false, $r0['body']);
        $this->assertFalse($r0['json']['completed']);

        // Mark complete.
        $c = self::apiPost($this->base() . '&action=complete', ['tour_name' => 'payroll']);
        $this->assertTrue($c['json']['success'] ?? false, $c['body']);

        // Now reported complete, both single and list form.
        $r1 = self::apiGet($this->base() . '&action=status&tour=payroll');
        $this->assertTrue($r1['json']['completed']);

        $list = self::apiGet($this->base() . '&action=status');
        $this->assertContains('payroll', $list['json']['completed']);
    }

    public function testResetClearsIt(): void
    {
        self::loginAs('tour.a@test.com');
        $reset = self::apiPost($this->base() . '&action=reset', ['tour_name' => 'payroll']);
        $this->assertTrue($reset['json']['success'] ?? false);

        $r = self::apiGet($this->base() . '&action=status&tour=payroll');
        $this->assertFalse($r['json']['completed']);
    }

    public function testProgressIsPerUser(): void
    {
        // User A completes the tour...
        self::loginAs('tour.a@test.com');
        self::apiPost($this->base() . '&action=complete', ['tour_name' => 'payroll']);

        // ...User B must still see it as un-completed.
        self::loginAs('tour.b@test.com');
        $r = self::apiGet($this->base() . '&action=status&tour=payroll');
        $this->assertTrue($r['json']['success'] ?? false);
        $this->assertFalse($r['json']['completed'], "one user's tour progress must not leak to another");
    }
}
