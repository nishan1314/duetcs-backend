<?php
/**
 * Role Permissions Assignment API
 * Manages which permissions are assigned to roles
 * 
 * GET: Get permissions for a role
 * POST: Add permission to role
 * PUT: Update all permissions for a role
 * DELETE: Remove permission from role
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

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetRolePermissions($db);
            break;
        case 'POST':
            handleAddPermissionToRole($db);
            break;
        case 'PUT':
            handleUpdateRolePermissions($db);
            break;
        case 'DELETE':
            handleRemovePermissionFromRole($db);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Role permission error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * GET: Get permissions for a specific role
 */
function handleGetRolePermissions($db) {
    $roleId = $_GET['role_id'] ?? null;

    if (!$roleId) {
        throw new Exception('role_id is required');
    }

    $stmt = $db->prepare("
        SELECT 
            ap.id,
            ap.permission_name,
            ap.permission_key,
            ap.module,
            ap.description
        FROM role_permissions rp
        INNER JOIN admin_permissions ap ON rp.permission_id = ap.id
        WHERE rp.role_id = ?
        ORDER BY ap.module, ap.permission_name
    ");
    $stmt_params = [$roleId];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();

    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = [
            'id' => (int)$row['id'],
            'permission_name' => $row['permission_name'],
            'permission_key' => $row['permission_key'],
            'module' => $row['module'],
            'description' => $row['description']
        ];
    }
    $stmt->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $permissions
    ]);
}

/**
 * POST: Add single permission to role
 */
function handleAddPermissionToRole($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['role_id']) || empty($data['permission_id'])) {
        throw new Exception('role_id and permission_id are required');
    }

    $roleId = $data['role_id'];
    $permissionId = $data['permission_id'];

    // Check if already assigned
    $stmt = $db->prepare("SELECT id FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $stmt_params = [$roleId, $permissionId];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Permission already assigned to this role'
        ]);
        exit;
    }
    $stmt->close();

    // Assign permission
    $stmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    $stmt_params = [$roleId, $permissionId];

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception($db->error);
    }
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Permission assigned to role'
    ]);
}

/**
 * PUT: Update all permissions for a role (replace existing)
 */
function handleUpdateRolePermissions($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['role_id'])) {
        throw new Exception('role_id is required');
    }

    $roleId = $data['role_id'];
    $permissionKeys = $data['permission_keys'] ?? []; // Array of permission keys
    
    if (!is_array($permissionKeys)) {
        throw new Exception('permission_keys must be an array');
    }

    // Start transaction
    $db->begin_transaction();

    try {
        // Delete all existing role-permission mappings for this role
        $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ?");
        $stmt_params = [$roleId];
        $stmt->execute($stmt_params ?? null);
        $stmt->close();

        // Add new permissions by key
        if (!empty($permissionKeys)) {
            foreach ($permissionKeys as $permKey) {
                // Get permission ID by key
                $stmt = $db->prepare("SELECT id FROM admin_permissions WHERE permission_key = ?");
                $stmt_params = [$permKey];
                $stmt->execute($stmt_params ?? null);
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $permId = $row['id'];

                    // Insert role-permission mapping
                    $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                    $stmt_params = [$roleId, $permId];
                    $insertStmt->execute($stmt_params ?? null);
                    $insertStmt->close();
                }
                $stmt->close();
            }
        }

        $db->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Role permissions updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

/**
 * DELETE: Remove permission from role
 */
function handleRemovePermissionFromRole($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['role_id']) || empty($data['permission_id'])) {
        throw new Exception('role_id and permission_id are required');
    }

    $roleId = $data['role_id'];
    $permissionId = $data['permission_id'];

    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = ? AND permission_id = ?");
    $stmt_params = [$roleId, $permissionId];

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception($db->error);
    }

    $affectedRows = $db->affected_rows;
    $stmt->close();

    if ($affectedRows === 0) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Permission assignment not found'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Permission removed from role'
    ]);
}

?>
