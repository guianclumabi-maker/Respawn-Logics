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
    protected static $defaultPipelineId;

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

    /** A fresh tenant should get a default board auto-seeded with the six standard phases. */
    public function testDefaultPipelineAutoSeeded(): void
    {
        self::loginAs('elradminA@test.com');
        $r = self::apiGet($this->base() . '&action=pipelines');
        $this->assertTrue($r['json']['success'] ?? false, 'pipelines should load: ' . $r['body']);

        $default = null;
        foreach (($r['json']['pipelines'] ?? []) as $p) {
            if (($p['name'] ?? '') === 'Disciplinary Process') { $default = $p; }
        }
        $this->assertNotNull($default, 'a default pipeline should be auto-seeded for a new tenant');
        $this->assertSame(6, (int) $default['stage_count'], 'default board should have six phases');
        self::$defaultPipelineId = (int) $default['id'];

        // Phases should be in the intended order.
        $pg = self::apiGet($this->base() . '&action=pipeline&id=' . self::$defaultPipelineId);
        $names = array_map(fn($s) => $s['name'], $pg['json']['pipeline']['stages']);
        $this->assertSame(
            ['AWOL Pool', 'Return-to-Work Notice', 'NTE', 'Hearing', 'Notice of Decision', 'Resolved'],
            $names,
            'seeded phases should be in the standard due-process order'
        );
    }

    /** reorder_stages persists a whole new order in one call. */
    public function testReorderStagesPersists(): void
    {
        $this->assertNotNull(self::$defaultPipelineId, 'default pipeline must exist first');
        self::loginAs('elradminA@test.com');

        $pg = self::apiGet($this->base() . '&action=pipeline&id=' . self::$defaultPipelineId);
        $stageIds = array_map(fn($s) => (int) $s['id'], $pg['json']['pipeline']['stages']);
        $reversed = array_reverse($stageIds);

        $r = self::apiPost($this->base() . '&action=reorder_stages', [
            'pipeline_id' => self::$defaultPipelineId,
            'order'       => $reversed,
        ]);
        $this->assertTrue($r['json']['success'] ?? false, 'reorder should succeed: ' . $r['body']);

        $pg2 = self::apiGet($this->base() . '&action=pipeline&id=' . self::$defaultPipelineId);
        $newIds = array_map(fn($s) => (int) $s['id'], $pg2['json']['pipeline']['stages']);
        $this->assertSame($reversed, $newIds, 'stages should return in the newly saved order');
    }

    /** A tenant cannot reorder another tenant's pipeline. */
    public function testReorderRejectsCrossTenant(): void
    {
        $this->assertNotNull(self::$defaultPipelineId, 'tenant A pipeline must exist first');
        self::loginAs('elradminB@test.com');

        $r = self::apiPost($this->base() . '&action=reorder_stages', [
            'pipeline_id' => self::$defaultPipelineId, // belongs to tenant A
            'order'       => [1, 2, 3],
        ]);
        $this->assertFalse($r['json']['success'] ?? true, 'Tenant B must not reorder Tenant A pipeline');
    }
}
