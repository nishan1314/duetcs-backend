<?php
/**
 * Admin Login API
 * Authenticates system administrators with RBAC
 * Works with existing database schema (designation field)
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/AuditLog.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

try {
    // Get JSON input
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate input
    if (!$data || empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'VALIDATION_ERROR',
            'message' => 'Email and password are required'
        ]);
        exit;
    }
    
    $email = trim($data['email']);
    $password = $data['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'VALIDATION_ERROR',
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Find admin by email (using designation field)
    $stmt = $db->prepare("
        SELECT id, name, email, password, designation, phone_number, is_active, last_login 
        FROM system_admin 
        WHERE email = ?
    ");
    $stmt_params = [$email];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    if ($result->rowCount() === 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'INVALID_CREDENTIALS',
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    $admin = $result->fetch(PDO::FETCH_ASSOC);
    $stmt->close();
    
    // Check if account is active
    if (!$admin['is_active']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'ACCOUNT_DISABLED',
            'message' => 'Your account has been deactivated. Please contact a Super Admin.'
        ]);
        exit;
    }
    
    // Verify password
    if (!password_verify($password, $admin['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'INVALID_CREDENTIALS',
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    // Map designation to role
    $role = Auth::getRole($admin['designation']);
    
    // Get Auth instance and login
    $auth = Auth::getInstance();
    $permissions = $auth->getPermissions($role);
    
    // Update last login
    $stmt = $db->prepare("UPDATE system_admin SET last_login = NOW() WHERE id = ?");
    $stmt_params = [$admin['id']];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $stmt->close();
    
    // Login via Auth class (stores session)
    $auth->login($admin['id'], $role);
    
    // Log the login
    AuditLog::logLogin($admin['id'], $admin['name'], $role);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => (int)$admin['id'],
            'name' => $admin['name'],
            'email' => $admin['email'],
            'role' => $role,
            'designation' => $admin['designation'],
            'phone_number' => $admin['phone_number'],
            'permissions' => $permissions,
            'is_active' => (bool)$admin['is_active'],
            'last_login' => $admin['last_login']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin Login Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred during login. Please try again.',
        'debug' => $e->getMessage()
    ]);
}
