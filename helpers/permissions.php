<?php

/**
 * Checks if the current user has a specific permission.
 */
function hasPermission(string $permission) {
    if (!isLoggedIn()) return false;
    return in_array($permission, $_SESSION['permissions'] ?? []);
}

/**
 * Blocks access if the current user lacks the required permission.
 */
function requirePermission(string $permission) {
    if (!hasPermission($permission)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Missing permission: ' . $permission
        ]);
        exit;
    }
}
