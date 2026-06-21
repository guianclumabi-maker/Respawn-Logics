<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/HttpTestServer.php';

class CandidatesControllerTest extends TestCase {
    use HttpTestServer;

    protected static $tenantA;
    protected static $tenantB;
    protected static $adminUser;
    protected static $employeeUser;
    protected static $tenantBUser;
    
    protected static $jobA;
    protected static $candidateA;
    protected static $appA;

    protected static $cookies = [];
    protected static $csrfToken = '';

    public static function setUpBeforeClass(): void {
        // Start built-in server
        self::startServer();

        // Include bootstrap to establish PDO
        require_once __DIR__ . '/../bootstrap.php';
        global $pdo;

        // Seed data
        self::$tenantA = \FixtureHelper::createTenant($pdo, 'Tenant A');
        self::$tenantB = \FixtureHelper::createTenant($pdo, 'Tenant B');

        self::$adminUser = \FixtureHelper::createUser($pdo, self::$tenantA, 'admin@tenantA.com', 'Admin');
        self::$employeeUser = \FixtureHelper::createUser($pdo, self::$tenantA, 'emp@tenantA.com', 'Employee');
        self::$tenantBUser = \FixtureHelper::createUser($pdo, self::$tenantB, 'admin@tenantB.com', 'Admin');

        self::$jobA = \FixtureHelper::createJob($pdo, self::$tenantA, 'Tenant A Job');
        self::$candidateA = \FixtureHelper::createCandidate($pdo, self::$tenantA, 'Tenant A Candidate');
        self::$appA = \FixtureHelper::createApplication($pdo, self::$tenantA, self::$candidateA, self::$jobA, 'Applied');
    }

    public static function tearDownAfterClass(): void {
        self::stopServer();
    }

    protected function authenticate($email, $password = 'password123') {
        $url = "http://" . self::$serverHost . "/api/index.php?route=auth&action=login";
        $data = json_encode(['email' => $email, 'password' => $password]);
        
        $options = [
            'http' => [
                'header'  => "Content-type: application/json\r\n",
                'method'  => 'POST',
                'content' => $data,
                'ignore_errors' => true
            ]
        ];
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        // Extract cookies
        self::$cookies = [];
        if (isset($http_response_header)) {
            foreach ($http_response_header as $hdr) {
                if (preg_match('/^Set-Cookie:\s*([^;]+)/', $hdr, $matches)) {
                    self::$cookies[] = $matches[1];
                }
            }
        }

        // Get CSRF token
        $csrfUrl = "http://" . self::$serverHost . "/api/index.php?route=auth&action=current_user";
        $resp = $this->makeRequest('GET', $csrfUrl);
        $json = json_decode($resp['body'], true);
        if ($json && isset($json['csrf_token'])) {
            self::$csrfToken = $json['csrf_token'];
        }
    }

    protected function makeRequest($method, $url, $data = null) {
        $headers = [];
        if ($data !== null) {
            $headers[] = "Content-type: application/json";
        }
        if (!empty(self::$cookies)) {
            $headers[] = "Cookie: " . implode('; ', self::$cookies);
        }
        if (self::$csrfToken) {
            $headers[] = "X-CSRF-Token: " . self::$csrfToken;
        }

        $options = [
            'http' => [
                'header'  => implode("\r\n", $headers) . "\r\n",
                'method'  => $method,
                'ignore_errors' => true
            ]
        ];
        if ($data !== null) {
            $options['http']['content'] = is_array($data) ? json_encode($data) : $data;
        }

        $context  = stream_context_create($options);
        $body = file_get_contents($url, false, $context);
        
        // Parse status code
        $status = 200;
        if (isset($http_response_header[0]) && preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $m)) {
            $status = (int)$m[1];
        }

        return ['status' => $status, 'body' => $body];
    }

    public function testTenantIsolation() {
        $this->authenticate('admin@tenantB.com'); // Tenant B user
        
        $url = "http://" . self::$serverHost . "/api/index.php?route=candidates&action=candidate&id=" . self::$candidateA;
        $response = $this->makeRequest('GET', $url);
        
        // Should return 404/403 or empty because it doesn't belong to Tenant B
        $json = json_decode($response['body'], true);
        $this->assertFalse($json['success'] ?? false);
    }

    public function testAuthorizationForReadsAndWrites() {
        // Log in as plain employee without ATS permissions
        $this->authenticate('emp@tenantA.com');

        $url = "http://" . self::$serverHost . "/api/index.php?route=candidates&action=candidates";
        $response = $this->makeRequest('GET', $url);
        
        $this->assertEquals(403, $response['status']);

        // Log in as Admin with ATS permissions
        $this->authenticate('admin@tenantA.com');
        $response = $this->makeRequest('GET', $url);
        $this->assertEquals(200, $response['status']);
    }

    public function testInputValidationRejectsInvalidData() {
        $this->authenticate('admin@tenantA.com');
        $url = "http://" . self::$serverHost . "/api/index.php?route=candidates";
        
        // 1. Invalid Email
        $data = [
            'action' => 'add_candidate',
            'name' => 'Valid Name',
            'email' => 'invalid-email-format'
        ];
        $response = $this->makeRequest('POST', $url, $data);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('Invalid email', $response['body']);

        // 2. Unknown Stage
        $stageData = [
            'action' => 'update_stage',
            'id' => self::$appA,
            'stage' => 'Galactic Overlord' // Invalid stage
        ];
        $response = $this->makeRequest('POST', $url, $stageData);
        $this->assertEquals(400, $response['status']);
        $this->assertStringContainsString('Invalid stage', $response['body']);
    }

    public function testStageTransitionsClearTimestamps() {
        global $pdo;
        $this->authenticate('admin@tenantA.com');
        $url = "http://" . self::$serverHost . "/api/index.php?route=candidates";

        // Transition to Hired
        $this->makeRequest('POST', $url, [
            'action' => 'update_stage',
            'id' => self::$appA,
            'stage' => 'Hired'
        ]);

        $stmt = $pdo->prepare("SELECT hired_at FROM candidate_applications WHERE id = ?");
        $stmt->execute([self::$appA]);
        $this->assertNotNull($stmt->fetchColumn(), "hired_at should be set");

        // Transition back to Review
        $this->makeRequest('POST', $url, [
            'action' => 'update_stage',
            'id' => self::$appA,
            'stage' => 'Review'
        ]);

        $stmt->execute([self::$appA]);
        $this->assertNull($stmt->fetchColumn(), "hired_at should be cleared");
    }
}
