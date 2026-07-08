<?php
/**
 * Wings Content Management API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../utils/admin-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminAuth = new AdminAuth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetWings($db, $adminAuth);
            break;
        case 'POST':
            handleCreateWing($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateWing($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteWing($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (\Throwable $e) {
    error_log("Wings management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleGetWings($db, $adminAuth) {
    $adminAuth->requirePermission('wings.manage');
    
    $result = $db->query("SELECT * FROM wings ORDER BY display_order ASC, wing_name ASC");
    
    $wings = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $wings[] = [
            'id' => $row['id'],
            'name' => $row['wing_name'],
            'description' => $row['wing_description'],
            'icon' => $row['icon'],
            'color' => $row['color'],
            'image' => $row['image_url'],
            'isActive' => (bool)$row['is_active'],
            'displayOrder' => $row['display_order']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $wings]);
}

function handleCreateWing($db, $adminAuth) {
    $adminAuth->requirePermission('wings.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $icon = $input['icon'] ?? null;
    $color = $input['color'] ?? null;
    $imageUrl = $input['image'] ?? null;
    $displayOrder = intval($input['displayOrder'] ?? 0);
    
    $stmt = $db->prepare("
        INSERT INTO wings (wing_name, wing_description, icon, color, image_url, display_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt_params = [$name, $description, $icon, $color, $imageUrl, $displayOrder];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Wing created successfully', 'wing_id' => $stmt->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create wing']);
    }
}

function handleUpdateWing($db, $adminAuth) {
    $adminAuth->requirePermission('wings.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $wingId = intval($input['id'] ?? 0);
    
    if (!$wingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Wing ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = ['name' => 's', 'description' => 's', 'icon' => 's', 'color' => 's', 
               'image' => 's', 'displayOrder' => 'i', 'isActive' => 'i'];
    $dbFields = ['name' => 'wing_name', 'description' => 'wing_description', 
                 'image' => 'image_url', 'displayOrder' => 'display_order', 
                 'isActive' => 'is_active'];
    
    foreach ($fields as $field => $type) {
        if (isset($input[$field])) {
            $dbField = $dbFields[$field] ?? $field;
            $updates[] = "$dbField = ?";
            $params[] = $input[$field];
            $types .= $type;
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $wingId;
    $types .= "i";
    
    $sql = "UPDATE wings SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Wing updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update wing']);
    }
}

function handleDeleteWing($db, $adminAuth) {
    $adminAuth->requirePermission('wings.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $wingId = intval($input['id'] ?? 0);
    
    if (!$wingId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Wing ID is required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM wings WHERE id = ?");
    $stmt_params = [$wingId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Wing deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete wing']);
    }
}
