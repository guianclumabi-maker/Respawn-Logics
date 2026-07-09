<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Guards the login-response completeness bug: logging in must return the SAME full profile
 * (role + permissions + is_super) that GET current_user returns, so the frontend never shows
 * a "lite" admin-less UI until a full page reload re-hydrates the session.
 */
class AuthLoginTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Auth Tenant');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'auth.super@test.com', 'Super_Admin');
        \FixtureHelper::createUser($pdo, self::$tenantId, 'auth.emp@test.com', 'Employee');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    public function testLoginReturnsFullProfileForSuperAdmin(): void
    {
        $r = self::loginAs('auth.super@test.com');
        $this->assertTrue($r['json']['success'] ?? false, 'login should succeed: ' . $r['body']);

        $user = $r['json']['user'] ?? [];
        $this->assertSame('Super_Admin', $user['role'] ?? null, 'login response must include role');
        $this->assertTrue($user['is_super'] ?? false, 'Super_Admin must be flagged is_super at login');
        $this->assertArrayHasKey('permissions', $user, 'login response must include permissions');
        $this->assertIsArray($user['permissions']);
        $this->assertSame('auth.super@test.com', $user['email'] ?? null);
    }

    public function testLoginFlagsNonSuperUserCorrectly(): void
    {
        $r = self::loginAs('auth.emp@test.com');
        $this->assertTrue($r['json']['success'] ?? false, $r['body']);

        $user = $r['json']['user'] ?? [];
        $this->assertFalse($user['is_super'] ?? true, 'a plain Employee must not be is_super');
        $this->assertArrayHasKey('role', $user);
        $this->assertArrayHasKey('permissions', $user);
    }

    public function testLoginPayloadMatchesCurrentUser(): void
    {
        // The whole point of the fix: the login response and current_user agree.
        $login = self::loginAs('auth.super@test.com');
        $loginUser = $login['json']['user'] ?? [];

        $cu = self::apiGet('/api.php?action=current_user');
        $cuUser = $cu['json']['user'] ?? [];
        $this->assertNotEmpty($cuUser, 'current_user should return a user: ' . $cu['body']);

        // Assert they return the same keys
        $loginKeys = array_keys($loginUser);
        $cuKeys = array_keys($cuUser);
        sort($loginKeys);
        sort($cuKeys);
        $this->assertEquals($loginKeys, $cuKeys, 'keys must match exactly across endpoints');

        $this->assertSame($loginUser['role'] ?? 'x', $cuUser['role'] ?? 'y', 'role must match across endpoints');
        $this->assertSame($loginUser['is_super'] ?? null, $cuUser['is_super'] ?? null, 'is_super must match across endpoints');
        $this->assertSame($loginUser['theme'] ?? null, $cuUser['theme'] ?? null, 'theme must match across endpoints');
        $this->assertSame((int)($loginUser['id'] ?? -1), (int)($cuUser['id'] ?? -2), 'same user id');
    }

    public function testLoginWritesAuditLog(): void
    {
        global $pdo;
        
        // Clear existing login audits for this tenant to avoid false positives
        $pdo->prepare("DELETE FROM `audit_logs` WHERE `tenant_id` = ? AND `action` = 'Login'")->execute([self::$tenantId]);

        $r = self::loginAs('auth.emp@test.com');
        $this->assertTrue($r['json']['success'] ?? false, $r['body']);

        // Check if audit log was written
        $stmt = $pdo->prepare("SELECT * FROM `audit_logs` WHERE `tenant_id` = ? AND `user_email` = ? AND `action` = 'Login' ORDER BY `id` DESC LIMIT 1");
        $stmt->execute([self::$tenantId, 'auth.emp@test.com']);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotEmpty($log, 'Audit log row for Login must exist');
        $this->assertSame('User signed in successfully.', $log['details']);
    }
}
