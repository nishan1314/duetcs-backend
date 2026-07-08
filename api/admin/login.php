<?php
/**
 * Admin Login API - Verify admin credentials and return user info with roles
 */

// Enable all error reporting immediately
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// Load CORS configuration FIRST before any headers
require_once __DIR__ . '/../../config/cors.php';

// Handle preflight OPTIONS immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Check method early
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Expected POST, got ' . $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

error_log("=== LOGIN.PHP START ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none'));

// Load dependencies
try {
    require_once __DIR__ . '/../../config/database.php';
    error_log("✓ Database config loaded");
} catch (Exception $e) {
    error_log("✗ Database config failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

try {
    require_once __DIR__ . '/../../utils/admin-auth.php';
    error_log("✓ Admin auth loaded");
} catch (Exception $e) {
    error_log("✗ Admin auth failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Auth error']);
    exit;
}
try {
    // Get request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    error_log("Login request body: " . json_encode($input));
    
    // Validate input
    if (!isset($input['email']) || !isset($input['password'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email and password are required'
        ]);
        exit;
    }
    
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $password = $input['password'];
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }
    
    // Get database connection
    $db = Database::getInstance()->getConnection();
    
    // Find user by email
    $stmt = $db->prepare("
        SELECT id, full_name, email, student_id, department, 
               year_semester, password, profile_image, is_verified
        FROM users 
        WHERE email = ?
    ");
    $stmt_params = [$email];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }
    
    // Check if email is verified
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Please verify your email before logging in'
        ]);
        exit;
    }
    
    // Check if user has admin privileges
    $adminAuth = new AdminAuth();
    $isAdmin = $adminAuth->isAdmin($user['id']);
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Access denied. Admin privileges required.'
        ]);
        exit;
    }
    
    // Start session and store user info
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['is_admin'] = true;
    
    error_log("✓ Session created for user: " . $email);
    
    // Get user roles and permissions
    $roles = $adminAuth->getUserRoles($user['id']);
    $permissions = $adminAuth->getUserPermissions($user['id']);
    
    // Remove password from response
    unset($user['password']);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'student_id' => $user['student_id'],
            'department' => $user['department'],
            'year_semester' => $user['year_semester'],
            'profile_image' => $user['profile_image'],
            'is_admin' => true,
            'roles' => $roles,
            'permissions' => $permissions
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Admin login error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during login: ' . $e->getMessage()
    ]);
}

error_log("=== LOGIN.PHP END ===");
?>