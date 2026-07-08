<?php
/**
 * Gallery Management API
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
            handleGetGallery($db, $adminAuth);
            break;
        case 'POST':
            handleCreateGalleryItem($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateGalleryItem($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteGalleryItem($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Gallery management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetGallery($db, $adminAuth) {
    $adminAuth->requirePermission('gallery.manage');
    
    $category = $_GET['category'] ?? 'all';
    $eventId = $_GET['event_id'] ?? null;
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($category !== 'all') {
        $where[] = "g.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if ($eventId) {
        $where[] = "g.event_id = ?";
        $params[] = intval($eventId);
        $types .= "i";
    }
    
    $whereClause = implode(" AND ", $where);
    
    $sql = "SELECT g.*, u.full_name as uploaded_by_name, e.title as event_title 
            FROM gallery_images g 
            INNER JOIN users u ON g.uploaded_by = u.id 
            LEFT JOIN events e ON g.event_id = e.id
            WHERE $whereClause 
            ORDER BY g.is_featured DESC, g.display_order ASC, g.created_at DESC";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;
    } else {
        $result = $db->query($sql);
    }
    
    $images = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $images[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'imageUrl' => $row['image_url'],
            'category' => $row['category'],
            'eventId' => $row['event_id'],
            'eventTitle' => $row['event_title'],
            'displayOrder' => $row['display_order'],
            'isFeatured' => (bool)$row['is_featured'],
            'uploadedBy' => $row['uploaded_by_name'],
            'createdAt' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $images]);
}

function handleCreateGalleryItem($db, $adminAuth) {
    $adminAuth->requirePermission('gallery.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = $input['title'] ?? null;
    $description = $input['description'] ?? null;
    $imageUrl = trim($input['imageUrl'] ?? '');
    $category = $input['category'] ?? null;
    $eventId = isset($input['eventId']) ? intval($input['eventId']) : null;
    $displayOrder = intval($input['displayOrder'] ?? 0);
    $isFeatured = isset($input['isFeatured']) ? ($input['isFeatured'] ? 1 : 0) : 0;
    $uploadedBy = $_SESSION['user_id'];
    
    if (empty($imageUrl)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image URL is required']);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO gallery_images (title, description, image_url, category, event_id, display_order, is_featured, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_params = [$title, $description, $imageUrl, $category, $eventId, $displayOrder, $isFeatured, $uploadedBy];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Gallery item created successfully', 'image_id' => $stmt->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create gallery item']);
    }
}

function handleUpdateGalleryItem($db, $adminAuth) {
    $adminAuth->requirePermission('gallery.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $imageId = intval($input['id'] ?? 0);
    
    if (!$imageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = ['title' => 's', 'description' => 's', 'imageUrl' => 's', 'category' => 's', 
               'eventId' => 'i', 'displayOrder' => 'i', 'isFeatured' => 'i'];
    $dbFields = ['imageUrl' => 'image_url', 'eventId' => 'event_id', 
                 'displayOrder' => 'display_order', 'isFeatured' => 'is_featured'];
    
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
    
    $params[] = $imageId;
    $types .= "i";
    
    $sql = "UPDATE gallery_images SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Gallery item updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update gallery item']);
    }
}

function handleDeleteGalleryItem($db, $adminAuth) {
    $adminAuth->requirePermission('gallery.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $imageId = intval($input['id'] ?? 0);
    
    if (!$imageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Image ID is required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM gallery_images WHERE id = ?");
    $stmt_params = [$imageId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Gallery item deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete gallery item']);
    }
}
