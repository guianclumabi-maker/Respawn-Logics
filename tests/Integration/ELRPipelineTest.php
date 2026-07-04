<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

/**
 * Critical-path coverage for the ELR pipeline module:
 * template CRUD + merge-field detection, and tenant isolation.
 * Uses the shared CSRF/cookie-aware client from HttpTestServer.
 */
class ELRPipelineTest extends TestCase
{
    use HttpTestServer;

    protected static $tenantA;
    protected static $tenantB;

    public static function setUpBeforeClass(): void
    {
        self::startServer();
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        self::$tenantA = \FixtureHelper::createTenant($pdo, 'ELR Tenant A');
        self::$tenantB = \FixtureHelper::createTenant($pdo, 'ELR Tenant B');
        // Super_Admin so the ELR view + investigate (manage) checks pass.
        \FixtureHelper::createUser($pdo, self::$tenantA, 'elradminA@test.com', 'Super_Admin');
        \FixtureHelper::createUser($pdo, self::$tenantB, 'elradminB@test.com', 'Super_Admin');
    }

    public static function tearDownAfterClass(): void
    {
        self::stopServer();
    }

    private function base(): string
    {
        return '/api/index.php?route=elr_pipeline';
    }

    public function testLoginSucceeds(): void
    {
        $r = self::loginAs('elradminA@test.com');
        $this->assertSame(200, $r['code'], 'login should return 200');
        $this->assertTrue($r['json']['success'] ?? false, 'login should succeed');
    }

    public function testSaveTemplateDetectsMergeFields(): void
    {
        self::loginAs('elradminA@test.com');
        $r = self::apiPost($this->base() . '&action=save_template', [
            'name'     => 'RTWN',
            'doc_type' => 'RTWN',
            'body'     => 'To {{employee_name}} ({{employee_id}}), absent since {{awol_start_date}}.',
        ]);
        $this->assertTrue($r['json']['success'] ?? false, 'save_template should succeed: ' . $r['body']);
        $tplId = $r['json']['id'];

        $list = self::apiGet($this->base() . '&action=templates');
        $this->assertTrue($list['json']['success'] ?? false);

        $found = null;
        foreach ($list['json']['templates'] as $t) {
            if ((int)$t['id'] === (int)$tplId) { $found = $t; }
        }
        $this->assertNotNull($found, 'created template should appear in the list');
        $this->assertEqualsCanonicalizing(
            ['employee_name', 'employee_id', 'awol_start_date'],
            $found['merge_fields'],
            'merge fields should be auto-detected from the body'
        );
    }

    public function testMissingFieldsRejected(): void
    {
        self::loginAs('elradminA@test.com');
        $r = self::apiPost($this->base() . '&action=save_template', ['name' => 'Incomplete']);
        $this->assertFalse($r['json']['success'] ?? true, 'incomplete template should be rejected gracefully');
    }

    public function testTenantIsolationOnTemplates(): void
    {
        // Tenant A creates a template.
        self::loginAs('elradminA@test.com');
        $r = self::apiPost($this->base() . '&action=save_template', [
            'name' => 'A-only', 'doc_type' => 'RTWN', 'body' => 'x {{employee_name}}',
        ]);
        $aTplId = (int)($r['json']['id'] ?? 0);
        $this->assertGreaterThan(0, $aTplId);

        // Tenant B must never see it.
        self::loginAs('elradminB@test.com');
        $list = self::apiGet($this->base() . '&action=templates');
        foreach (($list['json']['templates'] ?? []) as $t) {
            $this->assertNotSame($aTplId, (int)$t['id'], 'Tenant B must not see Tenant A template (isolation leak)');
        }

        // And B cannot fetch it directly.
        $direct = self::apiGet($this->base() . '&action=template&id=' . $aTplId);
        $this->assertFalse($direct['json']['success'] ?? true, 'Tenant B must not fetch Tenant A template by id');
    }
}
