<?php
/**
 * Roles & Privileges Management API
 * Manage roles, permissions, and user role assignments
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
    // Parse the action from URL
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'roles':
            handleRoles($db, $adminAuth, $method);
            break;
        case 'permissions':
            handlePermissions($db, $adminAuth, $method);
            break;
        case 'assign':
            handleAssignRole($db, $adminAuth);
            break;
        case 'revoke':
            handleRevokeRole($db, $adminAuth);
            break;
        case 'role-permissions':
            handleRolePermissions($db, $adminAuth, $method);
            break;
        default:
            getAllRolesWithPermissions($db, $adminAuth);
    }
} catch (Exception $e) {
    error_log("Roles management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * Get all roles with permissions
 */
function getAllRolesWithPermissions($db, $adminAuth) {
    // TODO: Re-enable permission check in production
    // $adminAuth->requirePermission('roles.view');
    
    error_log("=== getAllRolesWithPermissions START ===");
    
    try {
        // Get all roles
        $rolesStmt = $db->query("
            SELECT id, role_name, role_description, is_active, created_at
            FROM admin_roles
            ORDER BY 
                CASE role_name
                    WHEN 'Super Admin' THEN 1
                    WHEN 'Admin' THEN 2
                    WHEN 'Moderator' THEN 3
                    ELSE 4
                END,
                role_name
        ");
        
        if (!$rolesStmt) {
            throw new Exception("Query error: " . $db->error);
        }
        
        error_log("Roles query executed, rows: " . $rolesStmt->rowCount());
        
        $roles = [];
        while ($role = $rolesStmt->fetch(PDO::FETCH_ASSOC)) {
            // Get permissions for this role
            $permStmt = $db->prepare("
                SELECT p.id, p.permission_name, p.permission_key, p.module
                FROM role_permissions rp
                INNER JOIN admin_permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ?
            ");
            $stmt_params = [$role['id']];
            $permStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
            $permResult = $permStmt;
            
            $permissions = [];
            while ($perm = $permResult->fetch(PDO::FETCH_ASSOC)) {
                $permissions[] = $perm;
            }
            
            // Count users with this role
            $countStmt = $db->prepare("SELECT COUNT(*) as count FROM user_roles WHERE role_id = ?");
            $stmt_params = [$role['id']];
            $countStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
            $countResult = $countStmt;
            $countRow = $countResult->fetch(PDO::FETCH_ASSOC);
            
            $roles[] = [
                'id' => $role['id'],
                'name' => $role['role_name'],
                'description' => $role['role_description'],
                'isActive' => (bool)$role['is_active'],
                'userCount' => $countRow['count'],
                'permissions' => $permissions,
                'createdAt' => $role['created_at']
            ];
        }
        
        error_log("Total roles found: " . count($roles));
        error_log("=== getAllRolesWithPermissions SUCCESS ===");
        
        echo json_encode([
            'success' => true,
            'roles' => $roles
        ]);
    } catch (Exception $e) {
        error_log("getAllRolesWithPermissions ERROR: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Handle roles CRUD
 */
function handleRoles($db, $adminAuth, $method) {
    if ($method === 'GET') {
        $adminAuth->requirePermission('roles.view');
        getAllRolesWithPermissions($db, $adminAuth);
    } else if ($method === 'POST') {
        $adminAuth->requirePermission('roles.manage');
        createRole($db);
    } else if ($method === 'PUT') {
        $adminAuth->requirePermission('roles.manage');
        updateRole($db);
    } else if ($method === 'DELETE') {
        $adminAuth->requirePermission('roles.manage');
        deleteRole($db);
    }
}

/**
 * Create new role
 */
function createRole($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['name'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Role name is required'
        ]);
        return;
    }
    
    $name = trim($input['name']);
    $description = $input['description'] ?? '';
    
    $stmt = $db->prepare("INSERT INTO admin_roles (role_name, role_description) VALUES (?, ?)");
    $stmt_params = [$name, $description];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Role created successfully',
            'role_id' => $stmt->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create role'
        ]);
    }
}

/**
 * Update role
 */
function updateRole($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Role ID is required'
        ]);
        return;
    }
    
    $roleId = intval($input['id']);
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($input['name'])) {
        $updates[] = "role_name = ?";
        $params[] = trim($input['name']);
        $types .= "s";
    }
    
    if (isset($input['description'])) {
        $updates[] = "role_description = ?";
        $params[] = trim($input['description']);
        $types .= "s";
    }
    
    if (isset($input['is_active'])) {
        $updates[] = "is_active = ?";
        $params[] = $input['is_active'] ? 1 : 0;
        $types .= "i";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    $params[] = $roleId;
    $types .= "i";
    
    $sql = "UPDATE admin_roles SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Role updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update role'
        ]);
    }
}

/**
 * Delete role
 */
function deleteRole($db) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Role ID is required'
        ]);
        return;
    }
    
    $roleId = intval($input['id']);
    
    // Prevent deletion of system roles
    $stmt = $db->prepare("SELECT role_name FROM admin_roles WHERE id = ?");
    $stmt_params = [$roleId];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    if ($result->rowCount() === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Role not found'
        ]);
        return;
    }
    
    $role = $result->fetch(PDO::FETCH_ASSOC);
    $systemRoles = ['Super Admin', 'Admin', 'Member'];
    
    if (in_array($role['role_name'], $systemRoles)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Cannot delete system role'
        ]);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM admin_roles WHERE id = ?");
    $stmt_params = [$roleId];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete role'
        ]);
    }
}

/**
 * Get all permissions
 */
function handlePermissions($db, $adminAuth, $method) {
    $adminAuth->requirePermission('roles.view');
    
    $stmt = $db->query("
        SELECT id, permission_name, permission_key, module, description
        FROM admin_permissions
        ORDER BY module, permission_name
    ");
    
    $permissions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $permissions[] = $row;
    }
    
    // Group by module
    $grouped = [];
    foreach ($permissions as $perm) {
        $module = $perm['module'];
        if (!isset($grouped[$module])) {
            $grouped[$module] = [];
        }
        $grouped[$module][] = $perm;
    }
    
    echo json_encode([
        'success' => true,
        'permissions' => $permissions,
        'grouped' => $grouped
    ]);
}

/**
 * Assign role to user
 */
function handleAssignRole($db, $adminAuth) {
    $adminAuth->requirePermission('roles.assign');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['role_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User ID and Role ID are required'
        ]);
        return;
    }
    
    $userId = intval($input['user_id']);
    $roleId = intval($input['role_id']);
    $assignedBy = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        INSERT INTO user_roles (user_id, role_id, assigned_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE assigned_by = ?
    ");
    $stmt_params = [$userId, $roleId, $assignedBy, $assignedBy];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Role assigned successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to assign role'
        ]);
    }
}

/**
 * Revoke role from user
 */
function handleRevokeRole($db, $adminAuth) {
    $adminAuth->requirePermission('roles.assign');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['user_id']) || !isset($input['role_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User ID and Role ID are required'
        ]);
        return;
    }
    
    $userId = intval($input['user_id']);
    $roleId = intval($input['role_id']);
    
    $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt_params = [$userId, $roleId];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Role revoked successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to revoke role'
        ]);
    }
}

/**
 * Manage role permissions
 */
function handleRolePermissions($db, $adminAuth, $method) {
    $adminAuth->requirePermission('roles.manage');
    
    if ($method !== 'PUT') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        return;
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['role_id']) || !isset($input['permission_ids'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Role ID and permission IDs are required'
        ]);
        return;
    }
    
    $roleId = intval($input['role_id']);
    $permissionIds = $input['permission_ids'];
    
    // Start transaction
    $db->begin_transaction();
    
    try {
        // Delete existing permissions
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt_params = [$roleId];
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        
        // Insert new permissions
        if (!empty($permissionIds)) {
            $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            foreach ($permissionIds as $permId) {
                $permId = intval($permId);
                $stmt_params = [$roleId, $permId];
                $insertStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Role permissions updated successfully'
        ]);
    } catch (Exception $e) {
        $db->rollback();
        error_log("Update role permissions error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update role permissions'
        ]);
    }
}
