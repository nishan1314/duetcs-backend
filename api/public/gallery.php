<?php
/**
 * Public Gallery API
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $featured = $_GET['featured'] ?? false;
    $category = $_GET['category'] ?? null;
    $eventId = $_GET['event_id'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 12), 100);
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT * FROM gallery_images WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM gallery_images WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($featured) {
        $query .= " AND is_featured = 1";
        $countQuery .= " AND is_featured = 1";
    }
    
    if ($category) {
        $query .= " AND category = ?";
        $countQuery .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if ($eventId) {
        $query .= " AND event_id = ?";
        $countQuery .= " AND event_id = ?";
        $params[] = (int)$eventId;
        $types .= "i";
    }
    
    // Get total count
    $countStmt = $db->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute($stmt_params ?? null);
    $countResult = $countStmt->get_result();
    $total = $countResult->fetch_assoc()['total'];
    
    $query .= " ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    $images = [];
    while ($row = $result->fetch_assoc()) {
        $images[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['images' => $images],
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Public gallery API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch gallery images']);
}
?>
