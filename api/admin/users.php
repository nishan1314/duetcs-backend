<?php
/**
 * Member Users Management API
 * For managing regular members (not admins)
 * SUPER_ADMIN only
 */

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/RoleGuard.php';
require_once __DIR__ . '/../../utils/AuditLog.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require SUPER_ADMIN for user management
RoleGuard::requireSuperAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
        case 'PATCH':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred'
    ]);
}

/**
 * GET - List all members or single member
 */
function handleGet($db) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        // Get single user with all columns
        $stmt = $db->prepare("
            SELECT id, full_name as name, email, student_id, year_semester, department,
                   phone_number, profile_image, is_active, is_verified, 
                   created_at, updated_at
            FROM users 
            WHERE id = ?
        ");
        $stmt_params = [$id];
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            return;
        }
        
        $user = $result->fetch_assoc();
        $user['id'] = (int)$user['id'];
        $user['is_active'] = (bool)$user['is_active'];
        $user['is_verified'] = (bool)$user['is_verified'];
        
        echo json_encode(['success' => true, 'data' => $user]);
    } else {
        // List all members (all users in this table are members)
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $where = ["1=1"];
        $params = [];
        $types = "";
        
        if ($search) {
            $searchTerm = "%$search%";
            $where[] = "(full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "sss";
        }
        
        if ($status === 'active') {
            $where[] = "is_active = 1";
        } else if ($status === 'inactive') {
            $where[] = "is_active = 0";
        }
        
        $whereClause = "WHERE " . implode(" AND ", $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
        if (!empty($params)) {
            $stmt = $db->prepare($countSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute($stmt_params ?? null);
            $total = $stmt->get_result()->fetch_assoc()['total'];
        } else {
            $total = $db->query($countSql)->fetch_assoc()['total'];
        }
        
        // Get users with all relevant columns
        $sql = "SELECT id, full_name as name, email, student_id, year_semester, department,
                       phone_number, profile_image, is_active, is_verified, 
                       created_at, updated_at
                FROM users $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        $users = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['is_active'] = (bool)$row['is_active'];
            $row['is_verified'] = (bool)$row['is_verified'];
            $users[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => (int)$total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }
}

/**
 * POST - Create new member
 */
function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['email'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Name and email are required'
        ]);
        return;
    }
    
    $name = trim($data['name']);
    $email = trim($data['email']);
    $password = $data['password'] ?? bin2hex(random_bytes(8)); // Generate random password if not provided
    $student_id = $data['student_id'] ?? null;
    $year_semester = $data['year_semester'] ?? null;
    $department = $data['department'] ?? null;
    $phone_number = $data['phone_number'] ?? null;
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check email uniqueness
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt_params = [$email];
    $stmt->execute($stmt_params ?? null);
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Check student_id uniqueness if provided
    if ($student_id) {
        $stmt = $db->prepare("SELECT id FROM users WHERE student_id = ?");
        $stmt_params = [$student_id];
        $stmt->execute($stmt_params ?? null);
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Student ID already exists']);
            return;
        }
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert user with all fields
    $stmt = $db->prepare("
        INSERT INTO users (full_name, email, password, student_id, year_semester, department, phone_number, is_active, is_verified)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1, 1)
    ");
    $stmt_params = [$name, $email, $hashedPassword, $student_id, $year_semester, $department, $phone_number];
    
    if ($stmt->execute()) {
        $newId = $db->lastInsertId();
        
        // Log the action
        AuditLog::logCreate('user', $newId, $name, [
            'name' => $name,
            'email' => $email,
            'student_id' => $student_id,
            'phone_number' => $phone_number
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $newId,
                'name' => $name,
                'email' => $email,
                'student_id' => $student_id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create user']);
    }
}

/**
 * PUT/PATCH - Update member
 */
function handlePut($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Get current user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $currentUser = $stmt->get_result()->fetch_assoc();
    
    if (!$currentUser) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    $types = "";
    
    // Map 'name' to 'full_name' in database
    $fieldMap = [
        'name' => 'full_name',
        'email' => 'email',
        'student_id' => 'student_id',
        'year_semester' => 'year_semester',
        'department' => 'department',
        'phone_number' => 'phone_number'
    ];
    
    foreach ($fieldMap as $inputField => $dbField) {
        if (isset($data[$inputField])) {
            $updates[] = "$dbField = ?";
            $params[] = $data[$inputField];
            $types .= "s";
        }
    }
    
    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $types .= "s";
    }
    
    if (array_key_exists('is_active', $data)) {
        $updates[] = "is_active = ?";
        $isActive = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $params[] = $isActive ? 1 : 0;
        $types .= "i";
        
        // Log activation/deactivation using generic log method
        try {
            if ($isActive && !$currentUser['is_active']) {
                AuditLog::log('ACTIVATE', 'user', $id, $currentUser['full_name'], null, ['is_active' => true]);
            } else if (!$isActive && $currentUser['is_active']) {
                AuditLog::log('DEACTIVATE', 'user', $id, $currentUser['full_name'], null, ['is_active' => false]);
            }
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    // Check email uniqueness if updating email
    if (isset($data['email']) && $data['email'] !== $currentUser['email']) {
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt_params = [$data['email'], $id];
        $stmt->execute($stmt_params ?? null);
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Log update (wrap in try-catch to prevent breaking the response)
        try {
            AuditLog::logUpdate('user', $id, $currentUser['full_name'], 
                ['name' => $currentUser['full_name'], 'email' => $currentUser['email']],
                $data
            );
        } catch (Exception $e) {
            error_log("Audit log error: " . $e->getMessage());
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update user']);
    }
}

/**
 * DELETE - Delete member
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'User ID is required']);
        return;
    }
    
    // Get user data
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Delete user
    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
    $stmt_params = [$id];
    
    if ($stmt->execute()) {
        // Log deletion
        AuditLog::logDelete('user', $id, $user['full_name'], [
            'name' => $user['full_name'],
            'email' => $user['email'],
            'student_id' => $user['student_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
}
