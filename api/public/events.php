<?php
/**
 * Public Events API - Fetch all published events
 * No authentication required
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get filter parameters
    $status = $_GET['status'] ?? null;
    $category = $_GET['category'] ?? null;
    $is_featured = isset($_GET['is_featured']) ? $_GET['is_featured'] : null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100); // Max 100 per page
    $offset = ($page - 1) * $limit;
    
    // Build query
    $query = "SELECT * FROM events WHERE 1=1";
    $countQuery = "SELECT COUNT(*) as total FROM events WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($status) {
        $query .= " AND status = ?";
        $countQuery .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($category) {
        $query .= " AND category = ?";
        $countQuery .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if ($is_featured !== null) {
        $query .= " AND is_featured = ?";
        $countQuery .= " AND is_featured = ?";
        $params[] = (int)$is_featured;
        $types .= "i";
    }
    
    $query .= " ORDER BY event_date DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    // Get total count
    $countStmt = $db->prepare($countQuery);
    if (count($params) > 2) {
        $countParams = array_slice($params, 0, -2);
        $countTypes = substr($types, 0, -2);
        if (!empty($countParams)) {
            $countStmt->bind_param($countTypes, ...$countParams);
        }
    }
    $countStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $countResult = $countStmt;
    $total = $countResult->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Get events
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    $events = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $events[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'events' => $events,
        ],
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Public events API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch events'
    ]);
}
?>
