<?php
/**
 * Admin Users Management API (System Admins)
 * SUPER_ADMIN only - Full CRUD for admin accounts
 * Works with existing schema (designation field)
 */

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

// Require authentication
RoleGuard::requireAuth();

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
    error_log("Admin Users API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred',
        'debug' => $e->getMessage()
    ]);
}

/**
 * GET - List all admins or single admin
 */
function handleGet($db) {
    RoleGuard::requirePermission('admins.view');
    
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        // Get single admin
        $stmt = $db->prepare("
            SELECT id, name, email, designation, phone_number, is_active, last_login, created_at, updated_at
            FROM system_admin 
            WHERE id = ?
        ");
        $stmt_params = [$id];
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Admin not found']);
            return;
        }
        
        $admin = $result->fetch_assoc();
        $admin['id'] = (int)$admin['id'];
        $admin['is_active'] = (bool)$admin['is_active'];
        $admin['role'] = Auth::getRole($admin['designation']);
        
        echo json_encode(['success' => true, 'data' => $admin]);
    } else {
        // List all admins
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        
        $where = [];
        $params = [];
        $types = "";
        
        if ($search) {
            $searchTerm = "%$search%";
            $where[] = "(name LIKE ? OR email LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }
        
        if ($role) {
            // Map role to designation
            $designation = Auth::getDesignation($role);
            $where[] = "designation = ?";
            $params[] = $designation;
            $types .= "s";
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM system_admin $whereClause";
        if (!empty($params)) {
            $stmt = $db->prepare($countSql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute($stmt_params ?? null);
            $total = $stmt->get_result()->fetch_assoc()['total'];
        } else {
            $total = $db->query($countSql)->fetch_assoc()['total'];
        }
        
        // Get admins
        $sql = "SELECT id, name, email, designation, phone_number, is_active, last_login, created_at
                FROM system_admin $whereClause 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['is_active'] = (bool)$row['is_active'];
            $row['role'] = Auth::getRole($row['designation']);
            $admins[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $admins,
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
 * POST - Create new admin
 */
function handlePost($db) {
    RoleGuard::requirePermission('admins.create');
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validate required fields
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name, email, and password are required']);
        return;
    }
    
    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    // Check email uniqueness
    $stmt = $db->prepare("SELECT id FROM system_admin WHERE email = ?");
    $stmt_params = [$data['email']];
    $stmt->execute($stmt_params ?? null);
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        return;
    }
    
    // Map role to designation (default to Admin)
    $role = $data['role'] ?? 'ADMIN';
    $designation = Auth::getDesignation($role);
    
    // Only SUPER_ADMIN can create another SUPER_ADMIN
    $auth = Auth::getInstance();
    if ($role === 'SUPER_ADMIN' && $auth->role() !== 'SUPER_ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot create Super Admin']);
        return;
    }
    
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $phone = $data['phone_number'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO system_admin (name, email, password, designation, phone_number, is_active) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");
    $stmt_params = [$data['name'], $data['email'], $hashedPassword, $designation, $phone];
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create admin']);
        return;
    }
    
    $newId = $db->lastInsertId();
    
    // Log action
    AuditLog::logCreate('admin', $newId, $data['name'], [
        'name' => $data['name'],
        'email' => $data['email'],
        'role' => $role,
        'designation' => $designation
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin created successfully',
        'data' => ['id' => $newId]
    ]);
}

/**
 * PUT - Update admin
 */
function handlePut($db) {
    RoleGuard::requirePermission('admins.edit');
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
        return;
    }
    
    $id = (int)$data['id'];
    $auth = Auth::getInstance();
    
    // Get current admin data
    $stmt = $db->prepare("SELECT id, name, email, designation, is_active FROM system_admin WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        return;
    }
    
    $currentAdmin = $result->fetch_assoc();
    $beforeData = $currentAdmin;
    
    // Prevent non-super-admin from editing super admin
    $currentRole = Auth::getRole($currentAdmin['designation']);
    if ($currentRole === 'SUPER_ADMIN' && $auth->role() !== 'SUPER_ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot edit Super Admin']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = $data['name'];
        $types .= "s";
    }
    
    if (isset($data['email'])) {
        // Check email uniqueness
        $stmt = $db->prepare("SELECT id FROM system_admin WHERE email = ? AND id != ?");
        $stmt_params = [$data['email'], $id];
        $stmt->execute($stmt_params ?? null);
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists']);
            return;
        }
        $updates[] = "email = ?";
        $params[] = $data['email'];
        $types .= "s";
    }
    
    if (isset($data['role'])) {
        $newDesignation = Auth::getDesignation($data['role']);
        // Only SUPER_ADMIN can promote to SUPER_ADMIN
        if ($data['role'] === 'SUPER_ADMIN' && $auth->role() !== 'SUPER_ADMIN') {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Cannot promote to Super Admin']);
            return;
        }
        $updates[] = "designation = ?";
        $params[] = $newDesignation;
        $types .= "s";
    }
    
    if (isset($data['phone_number'])) {
        $updates[] = "phone_number = ?";
        $params[] = $data['phone_number'];
        $types .= "s";
    }
    
    if (isset($data['is_active'])) {
        // Cannot deactivate yourself
        if ($id === $auth->id() && !$data['is_active']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account']);
            return;
        }
        $updates[] = "is_active = ?";
        $params[] = $data['is_active'] ? 1 : 0;
        $types .= "i";
    }
    
    if (isset($data['password']) && !empty($data['password'])) {
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $sql = "UPDATE system_admin SET " . implode(", ", $updates) . " WHERE id = ?";
    $params[] = $id;
    $types .= "i";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update admin']);
        return;
    }
    
    // Log action
    AuditLog::logUpdate('admin', $id, $currentAdmin['name'], $beforeData, $data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin updated successfully'
    ]);
}

/**
 * DELETE - Delete admin
 */
function handleDelete($db) {
    RoleGuard::requirePermission('admins.delete');
    
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Admin ID is required']);
        return;
    }
    
    $id = (int)$data['id'];
    $auth = Auth::getInstance();
    
    // Cannot delete yourself
    if ($id === $auth->id()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Get admin data
    $stmt = $db->prepare("SELECT id, name, designation FROM system_admin WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Admin not found']);
        return;
    }
    
    $admin = $result->fetch_assoc();
    
    // Prevent non-super-admin from deleting super admin
    $adminRole = Auth::getRole($admin['designation']);
    if ($adminRole === 'SUPER_ADMIN' && $auth->role() !== 'SUPER_ADMIN') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Cannot delete Super Admin']);
        return;
    }
    
    // Delete admin
    $stmt = $db->prepare("DELETE FROM system_admin WHERE id = ?");
    $stmt_params = [$id];
    
    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete admin']);
        return;
    }
    
    // Log action
    AuditLog::logDelete('admin', $id, $admin['name']);
    
    echo json_encode([
        'success' => true,
        'message' => 'Admin deleted successfully'
    ]);
}
