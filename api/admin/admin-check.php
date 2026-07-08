<?php
/**
 * Admin Check/Me API
 * Returns current authenticated admin info
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only GET method is allowed'
    ]);
    exit;
}

try {
    $auth = Auth::getInstance();
    
    // Check authentication
    if (!$auth->check()) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'UNAUTHENTICATED',
            'message' => 'Not authenticated'
        ]);
        exit;
    }
    
    $db = Database::getInstance()->getConnection();
    
    // Get fresh data from database (using designation)
    $stmt = $db->prepare("
        SELECT id, name, email, designation, phone_number, is_active, last_login, created_at
        FROM system_admin 
        WHERE id = ?
    ");
    $stmt_params = [$auth->id()];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $auth->logout();
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'USER_NOT_FOUND',
            'message' => 'User no longer exists'
        ]);
        exit;
    }
    
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    // Check if still active
    if (!$admin['is_active']) {
        $auth->logout();
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'ACCOUNT_DISABLED',
            'message' => 'Your account has been deactivated'
        ]);
        exit;
    }
    
    // Map designation to role
    $role = Auth::getRole($admin['designation']);
    $permissions = $auth->getPermissions($role);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $role,
            'designation' => $admin['designation'],
            'phone_number' => $admin['phone_number'],
            'permissions' => $permissions,
            'is_active' => (bool)$admin['is_active'],
            'last_login' => $admin['last_login'],
            'created_at' => $admin['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin Check Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred'
    ]);
}
