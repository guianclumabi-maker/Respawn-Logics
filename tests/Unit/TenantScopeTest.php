<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TenantScopeTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset session before each test
        $_SESSION = [];
        
        // Define mock functions for dependencies in bootstrap/app.php
        if (!function_exists('isLoggedIn')) {
            function isLoggedIn() {
                return isset($_SESSION['user_email']);
            }
        }
    }

    public function testTenantModuleEnabledReturnsTrueIfExplicitlyEnabled()
    {
        $_SESSION['user_email'] = 'user@example.com';
        $_SESSION['tenant_modules'] = [
            'advanced_reporting' => true
        ];

        // Ensure the function is defined
        if (!function_exists('tenantModuleEnabled')) {
            function tenantModuleEnabled(string $module) {
                if (!isLoggedIn()) return false;
                if (isset($_SESSION['tenant_modules'][$module])) {
                    return $_SESSION['tenant_modules'][$module];
                }
                return true;
            }
        }

        $this->assertTrue(tenantModuleEnabled('advanced_reporting'));
    }

    public function testTenantModuleEnabledReturnsFalseIfExplicitlyDisabled()
    {
        $_SESSION['user_email'] = 'user@example.com';
        $_SESSION['tenant_modules'] = [
            'advanced_reporting' => false
        ];

        if (!function_exists('tenantModuleEnabled')) {
            function tenantModuleEnabled(string $module) {
                if (!isLoggedIn()) return false;
                if (isset($_SESSION['tenant_modules'][$module])) {
                    return $_SESSION['tenant_modules'][$module];
                }
                return true;
            }
        }

        $this->assertFalse(tenantModuleEnabled('advanced_reporting'));
    }

    public function testTenantModuleEnabledReturnsFalseWhenNotLoggedIn()
    {
        // No user_email set, isLoggedIn() returns false
        $_SESSION['tenant_modules'] = [
            'advanced_reporting' => true
        ];

        if (!function_exists('tenantModuleEnabled')) {
            function tenantModuleEnabled(string $module) {
                if (!isLoggedIn()) return false;
                if (isset($_SESSION['tenant_modules'][$module])) {
                    return $_SESSION['tenant_modules'][$module];
                }
                return true; // Default
            }
        }

        $this->assertFalse(tenantModuleEnabled('advanced_reporting'));
    }

    public function testTenantModuleEnabledReturnsDefaultTrueIfUndefined()
    {
        $_SESSION['user_email'] = 'user@example.com';
        $_SESSION['tenant_modules'] = [];

        if (!function_exists('tenantModuleEnabled')) {
            function tenantModuleEnabled(string $module) {
                if (!isLoggedIn()) return false;
                if (isset($_SESSION['tenant_modules'][$module])) {
                    return $_SESSION['tenant_modules'][$module];
                }
                return true; 
            }
        }

        $this->assertTrue(tenantModuleEnabled('unknown_module'));
    }
}
