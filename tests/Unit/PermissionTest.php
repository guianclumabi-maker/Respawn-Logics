<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class PermissionTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset session before each test
        $_SESSION = [];
    }

    public function testHasPermissionReturnsTrueWhenPermissionExists()
    {
        // Simulate a logged-in user
        $_SESSION['user_email'] = 'test@example.com';
        
        // Simulate permissions in session
        $_SESSION['permissions'] = ['view_dashboard', 'edit_users'];

        // Mock isLoggedIn() manually if not loaded, but assuming helpers are loaded
        require_once __DIR__ . '/../../helpers/permissions.php';

        if (!function_exists('isLoggedIn')) {
            function isLoggedIn() {
                return isset($_SESSION['user_email']);
            }
        }

        $this->assertTrue(hasPermission('view_dashboard'));
        $this->assertTrue(hasPermission('edit_users'));
    }

    public function testHasPermissionReturnsFalseWhenPermissionMissing()
    {
        $_SESSION['user_email'] = 'test@example.com';
        $_SESSION['permissions'] = ['view_dashboard'];

        $this->assertFalse(hasPermission('delete_users'));
    }

    public function testHasPermissionReturnsFalseWhenNotLoggedIn()
    {
        // Session has no user_email, so isLoggedIn() will return false
        $_SESSION['permissions'] = ['view_dashboard'];

        $this->assertFalse(hasPermission('view_dashboard'));
    }
}
