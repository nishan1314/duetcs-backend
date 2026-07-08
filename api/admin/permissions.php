<?php
/**
 * Permissions Management API
 * Manages admin permissions
 * 
 * GET: List all permissions
 * POST: Create permission
 * PUT: Update permission
 * DELETE: Delete permission
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
            handleGetPermissions($db);
            break;
        case 'POST':
            handleCreatePermission($db);
            break;
        case 'PUT':
            handleUpdatePermission($db);
            break;
        case 'DELETE':
            handleDeletePermission($db);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Permission management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * GET: Get all permissions, optionally filtered by module
 */
function handleGetPermissions($db) {
    error_log("=== handleGetPermissions START ===");
    
    $module = $_GET['module'] ?? null;

    $query = "
        SELECT 
            id,
            permission_name,
            permission_key,
            module,
            description,
            created_at
        FROM admin_permissions
    ";

    if ($module) {
        $query .= " WHERE module = '" . $db->quote($module) . "'";
    }

    $query .= " ORDER BY module, permission_name ASC";

    error_log("Query: " . $query);
    
    $result = $db->query($query);

    if (!$result) {
        error_log("Query error: " . $db->error);
        throw new Exception($db->error);
    }

    error_log("Permissions query executed, rows: " . $result->num_rows);

    $permissions = [];
    while ($row = $result->fetch_assoc()) {
        $permissions[] = [
            'id' => (int)$row['id'],
            'permission_name' => $row['permission_name'],
            'permission_key' => $row['permission_key'],
            'module' => $row['module'],
            'description' => $row['description'],
            'created_at' => $row['created_at']
        ];
    }

    error_log("Total permissions found: " . count($permissions));
    error_log("=== handleGetPermissions SUCCESS ===");

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'permissions' => $permissions
    ]);
}

/**
 * POST: Create new permission
 */
function handleCreatePermission($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['permission_name']) || empty($data['permission_key']) || empty($data['module'])) {
        throw new Exception('permission_name, permission_key, and module are required');
    }

    $permissionName = $data['permission_name'];
    $permissionKey = $data['permission_key'];
    $module = $data['module'];
    $description = $data['description'] ?? '';

    $stmt = $db->prepare("
        INSERT INTO admin_permissions (permission_name, permission_key, module, description)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_params = [$permissionName, $permissionKey, $module, $description];

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception($db->error);
    }

    $permissionId = $db->lastInsertId();
    $stmt->close();

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'message' => 'Permission created successfully',
        'id' => $permissionId
    ]);
}

/**
 * PUT: Update permission
 */
function handleUpdatePermission($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        throw new Exception('id is required');
    }

    $id = $data['id'];
    $permissionName = $data['permission_name'] ?? null;
    $description = $data['description'] ?? null;

    $updates = [];
    $params = [];
    $types = "";

    if ($permissionName !== null) {
        $updates[] = "permission_name = ?";
        $params[] = $permissionName;
        $types .= "s";
    }

    if ($description !== null) {
        $updates[] = "description = ?";
        $params[] = $description;
        $types .= "s";
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $params[] = $id;
    $types .= "i";

    $query = "UPDATE admin_permissions SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);

    if (!$stmt->execute()) {
        $stmt->close();
        throw new Exception($db->error);
    }

    $stmt->close();

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Permission updated successfully'
    ]);
}

/**
 * DELETE: Delete permission
 */
function handleDeletePermission($db) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['id'])) {
        throw new Exception('id is required');
    }

    $id = $data['id'];

    $stmt = $db->prepare("DELETE FROM admin_permissions WHERE id = ?");
    $stmt_params = [$id];

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
            'message' => 'Permission not found'
        ]);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Permission deleted successfully'
    ]);
}

?>
