<?php
/**
 * Admin User Info API - Get current admin user with roles and permissions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/admin-auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $adminAuth = new AdminAuth();
    
    // Check authentication
    $adminAuth->requireAdmin();
    
    // Get current user with roles and permissions
    $user = $adminAuth->getCurrentUser();
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit;
    }
    
    // Return user data
    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
    
} catch (Exception $e) {
    error_log("Get admin user error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}
