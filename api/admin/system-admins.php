<?php
/**
 * System Admin Management API
 * For Super Admin to manage other admins and moderators
 * GET: List all system admins
 * POST: Create new system admin
 * PUT: Update system admin
 * DELETE: Delete system admin
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

$adminAuth = new AdminAuth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetSystemAdmins($db, $adminAuth);
            break;
        case 'POST':
            handleCreateSystemAdmin($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateSystemAdmin($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteSystemAdmin($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function handleGetSystemAdmins($db, $adminAuth) {
    // Only Super Admin can view system admins
    $adminAuth->requirePermission('system_admin.view');
    
    $query = "SELECT id, name, email, designation, phone_number, created_at, last_login, is_active 
              FROM system_admin 
              ORDER BY created_at DESC";
    
    $result = $db->query($query);
    $admins = [];
    
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $admins
    ]);
}

function handleCreateSystemAdmin($db, $adminAuth) {
    // Only Super Admin can create system admins
    $adminAuth->requirePermission('system_admin.create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'email', 'password', 'designation'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Field '$field' is required"
            ]);
            return;
        }
    }
    
    $name = trim($input['name']);
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $password = $input['password'];
    $designation = trim($input['designation']);
    $phoneNumber = isset($input['phone_number']) ? trim($input['phone_number']) : null;
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        return;
    }
    
    // Validate designation
    $validDesignations = ['Super Admin', 'Admin', 'Moderator'];
    if (!in_array($designation, $validDesignations)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid designation'
        ]);
        return;
    }
    
    // Check if email already exists
    $stmt = $db->prepare("SELECT id FROM system_admin WHERE email = ?");
    $stmt_params = [$email];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Email already exists'
        ]);
        return;
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert system admin
    $stmt = $db->prepare("
        INSERT INTO system_admin (name, email, password, designation, phone_number)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt_params = [$name, $email, $hashedPassword, $designation, $phoneNumber];
    
    if ($stmt->execute()) {
        $newId = $stmt->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'System admin created successfully',
            'data' => [
                'id' => $newId,
                'name' => $name,
                'email' => $email,
                'designation' => $designation
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create system admin'
        ]);
    }
}

function handleUpdateSystemAdmin($db, $adminAuth) {
    // Only Super Admin can update system admins
    $adminAuth->requirePermission('system_admin.update');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Admin ID is required'
        ]);
        return;
    }
    
    $id = intval($input['id']);
    $updates = [];
    $params = [];
    $types = "";
    
    // Build dynamic update query
    if (isset($input['name']) && trim($input['name']) !== '') {
        $updates[] = "name = ?";
        $params[] = trim($input['name']);
        $types .= "s";
    }
    
    if (isset($input['email']) && trim($input['email']) !== '') {
        $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid email format'
            ]);
            return;
        }
        $updates[] = "email = ?";
        $params[] = $email;
        $types .= "s";
    }
    
    if (isset($input['designation']) && trim($input['designation']) !== '') {
        $updates[] = "designation = ?";
        $params[] = trim($input['designation']);
        $types .= "s";
    }
    
    if (isset($input['phone_number'])) {
        $updates[] = "phone_number = ?";
        $params[] = trim($input['phone_number']);
        $types .= "s";
    }
    
    if (isset($input['password']) && trim($input['password']) !== '') {
        $updates[] = "password = ?";
        $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE system_admin SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'System admin updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update system admin'
        ]);
    }
}

function handleDeleteSystemAdmin($db, $adminAuth) {
    // Only Super Admin can delete system admins
    $adminAuth->requirePermission('system_admin.delete');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Admin ID is required'
        ]);
        return;
    }
    
    $id = intval($input['id']);
    
    // Prevent deleting the last super admin
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM system_admin WHERE designation = 'Super Admin'");
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] <= 1) {
        $stmt = $db->prepare("SELECT designation FROM system_admin WHERE id = ?");
        $stmt_params = [$id];
        $stmt->execute($stmt_params ?? null);
        $admin = $stmt->get_result()->fetch_assoc();
        
        if ($admin && $admin['designation'] === 'Super Admin') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Cannot delete the last Super Admin'
            ]);
            return;
        }
    }
    
    // Delete system admin
    $stmt = $db->prepare("DELETE FROM system_admin WHERE id = ?");
    $stmt_params = [$id];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'System admin deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete system admin'
        ]);
    }
}
