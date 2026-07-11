<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

class PlatformAdminSecurityTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantId;
    protected static $platformUserId;
    protected static $tenantUserId;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        // Create a regular tenant
        self::$tenantId = \FixtureHelper::createTenant($pdo, 'Security Test Tenant');
        
        // Create Tenant Super_Admin
        $tenantUser = \FixtureHelper::createUser($pdo, self::$tenantId, 'tenant_sa@test.com', 'Super_Admin');
        self::$tenantUserId = $tenantUser;
        
        // Create Platform_Admin (in platform tenant '1')
        $platformUser = \FixtureHelper::createUser($pdo, '1', 'platform_admin@test.com', 'Platform_Admin');
        self::$platformUserId = $platformUser;
    }

    public function testTenantSuperAdminIsDenied()
    {
        self::loginAs('tenant_sa@test.com');
        
        // 1. platform_support
        $r = self::apiGet('/api/index.php?route=platform_support&action=list_tickets');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied platform_support. ' . ($r['body'] ?? ''));
        
        // 2. health check
        $r = self::apiGet('/api/index.php?route=health&action=check');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied health check. ' . ($r['body'] ?? ''));
        
        // 3. saas_staff
        $r = self::apiGet('/api/index.php?route=saas_staff&action=list');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied saas_staff. ' . ($r['body'] ?? ''));
        
        // 4. vendor_dashboard.php
        $r = self::apiGet('/pages/views/vendor_dashboard.php?action=get_vendor_stats');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied vendor_dashboard stats. ' . ($r['body'] ?? ''));
        
        // 5. impersonate.php
        $r = self::apiGet('/pages/impersonate.php?action=start&tenant_id=' . self::$tenantId);
        $this->assertStringContainsString('Unauthorized', $r['body'] ?? '', 'Tenant SA should be denied impersonate start. ' . ($r['body'] ?? ''));
        
        // 6. platform_tenant_list
        $r = self::apiGet('/api/index.php?route=iam&action=platform_tenant_list');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied platform_tenant_list. ' . ($r['body'] ?? ''));

        // 7. config check
        $r = self::apiGet('/api/index.php?route=health&action=config_check');
        $this->assertEquals(403, $r['code'] ?? 403, 'Tenant SA should be denied config check. ' . ($r['body'] ?? ''));
    }

    public function testPlatformAdminIsAllowed()
    {
        self::loginAs('platform_admin@test.com');
        
        // 1. platform_support
        $r = self::apiGet('/api/index.php?route=platform_support&action=list_tickets');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access platform_support');
        
        // 2. health check
        $r = self::apiGet('/api/index.php?route=health&action=check');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access health check');
        
        // 3. saas_staff
        $r = self::apiGet('/api/index.php?route=saas_staff&action=list');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access saas_staff');
        
        // 4. vendor_dashboard.php
        $r = self::apiGet('/pages/views/vendor_dashboard.php?action=get_vendor_stats');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access vendor_dashboard stats');
        
        // 5. platform_tenant_list
        $r = self::apiGet('/api/index.php?route=iam&action=platform_tenant_list');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access platform_tenant_list');

        // 6. config check
        $r = self::apiGet('/api/index.php?route=health&action=config_check');
        $this->assertEquals(200, $r['code'], 'Platform Admin should access config check');
        $data = json_decode($r['body'], true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('storage_resume', $data);
        $this->assertArrayHasKey('storage_file', $data);
        $this->assertArrayHasKey('mail', $data);
        $this->assertArrayHasKey('set', $data['storage_resume']);
        $this->assertArrayHasKey('writable', $data['storage_resume']);
        $this->assertArrayHasKey('api_key_set', $data['mail']);
        $this->assertArrayHasKey('from_set', $data['mail']);
    }
}
