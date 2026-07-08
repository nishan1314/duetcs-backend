<?php
/**
 * User Roles Management API
 * Allows admin to assign/manage user roles
 * 
 * GET: List all available roles
 * POST: Assign role to user
 * PUT: Update user roles
 * DELETE: Remove role from user
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
            handleGetRoles($db);
            break;
        case 'POST':
            handleAssignRole($db);
            break;
        case 'PUT':
            handleUpdateUserRoles($db);
            break;
        case 'DELETE':
            handleRemoveRole($db);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Role management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

/**
 * GET: Get all roles with their permissions and user counts
 */
function handleGetRoles($db) {
    $action = $_GET['action'] ?? 'list';

    if ($action === 'list') {
        // Get all roles
        $query = "
            SELECT 
                ar.id,
                ar.role_name,
                ar.role_description,
                ar.is_active,
                COUNT(DISTINCT ur.user_id) as user_count,
                GROUP_CONCAT(
                    CONCAT(ap.permission_key, ':', ap.permission_name)
                    SEPARATOR '|'
                ) as permissions
            FROM admin_roles ar
            LEFT JOIN role_permissions rp ON ar.id = rp.role_id
            LEFT JOIN admin_permissions ap ON rp.permission_id = ap.id
            LEFT JOIN user_roles ur ON ar.id = ur.role_id
            GROUP BY ar.id
            ORDER BY ar.id ASC
        ";

        $result = $db->query($query);
        
        if (!$result) {
            throw new Exception($db->error);
        }

        $roles = [];
        while ($row = $result->fetch_assoc()) {
            // Parse permissions
            $permissions = [];
            if ($row['permissions']) {
                $permArray = explode('|', $row['permissions']);
                foreach ($permArray as $perm) {
                    if (!empty($perm)) {
                        list($key, $name) = explode(':', $perm);
                        $permissions[] = ['key' => $key, 'name' => $name];
                    }
                }
            }

            $roles[] = [
                'id' => (int)$row['id'],
                'role_name' => $row['role_name'],
                'role_description' => $row['role_description'],
                'is_active' => (bool)$row['is_active'],
                'user_count' => (int)$row['user_count'],
                'permissions' => $permissions
            ];
        }

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);

    } elseif ($action === 'user-roles') {
        // Get roles for a specific user
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            throw new Exception('user_id is required');
        }

        $stmt = $db->prepare("
            SELECT 
                ar.id,
                ar.role_name,
                ar.role_description,
                ur.assigned_at,
                u.full_name as assigned_by_name
            FROM user_roles ur
            INNER JOIN admin_roles ar ON ur.role_id = ar.id
            LEFT JOIN users u ON ur.assigned_by = u.id
            WHERE ur.user_id = ?
            ORDER BY ur.assigned_at DESC
        ");
        $stmt_params = [$userId];
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();

        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = [
                'id' => (int)$row['id'],
                'role_name' => $row['role_name'],
                'role_description' => $row['role_description'],
                'assigned_at' => $row['assigned_at'],
                'assigned_by_name' => $row['assigned_by_name']
            ];
        }
        $stmt->close();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $roles
        ]);
    }
}

/**
 * POST: Assign a role to a user
 */
function handleAssignRole($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['user_id']) || empty($data['role_id'])) {
        throw new Exception('user_id and role_id are required');
    }

    $userId = $data['user_id'];
    $roleId = $data['role_id'];
    $assignedBy = $_SESSION['user_id'] ?? null;

    // Check if user already has this role
    $stmt = $db->prepare("SELECT id FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt_params = [$userId, $roleId];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmt->close();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'User already has this role'
        ]);
        exit;
    }
    $stmt->close();

    // Assign role
    $stmt = $db->prepare("
        INSERT INTO user_roles (user_id, role_id, assigned_by)
        VALUES (?, ?, ?)
    ");
    $stmt_params = [$userId, $roleId, $assignedBy];
    
    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception($db->error);
    }
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Role assigned successfully'
    ]);
}

/**
 * DELETE: Remove a role from a user
 */
function handleRemoveRole($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['user_id']) || empty($data['role_id'])) {
        throw new Exception('user_id and role_id are required');
    }

    $userId = $data['user_id'];
    $roleId = $data['role_id'];

    $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
    $stmt_params = [$userId, $roleId];
    
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
            'message' => 'Role assignment not found'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Role removed successfully'
    ]);
}

/**
 * PUT: Update all roles for a user
 */
function handleUpdateUserRoles($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['user_id'])) {
        throw new Exception('user_id is required');
    }

    $userId = $data['user_id'];
    
    // Handle both role_ids (array of IDs) and roles (array of role names)
    $roleIds = [];
    
    if (!empty($data['role_ids']) && is_array($data['role_ids'])) {
        // Role IDs provided directly
        $roleIds = $data['role_ids'];
    } elseif (!empty($data['roles']) && is_array($data['roles'])) {
        // Role names provided - convert to IDs
        foreach ($data['roles'] as $roleName) {
            $stmt = $db->prepare("SELECT id FROM admin_roles WHERE role_name = ? AND is_active = 1");
            $stmt_params = [$roleName];
            $stmt->execute($stmt_params ?? null);
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $roleIds[] = $row['id'];
            }
            $stmt->close();
        }
    }

    if (empty($roleIds)) {
        throw new Exception('No valid roles provided');
    }

    // Start transaction
    $db->begin_transaction();

    try {
        // Remove all existing roles for this user
        $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ?");
        $stmt_params = [$userId];
        $stmt->execute($stmt_params ?? null);
        $stmt->close();

        // Add new roles
        $assignedBy = $_SESSION['user_id'] ?? null;
        foreach ($roleIds as $roleId) {
            $stmt = $db->prepare("
                INSERT INTO user_roles (user_id, role_id, assigned_by)
                VALUES (?, ?, ?)
            ");
            $stmt_params = [$userId, $roleId, $assignedBy];
            $stmt->execute($stmt_params ?? null);
            $stmt->close();
        }

        $db->commit();

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'User roles updated successfully'
        ]);

    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

?>
