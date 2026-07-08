<?php
/**
 * Public News API - Fetch published news articles
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
    
    // Check if fetching by slug
    if (isset($_GET['slug'])) {
        $slug = $_GET['slug'];
        $stmt = $db->prepare("
            SELECT * FROM news 
            WHERE slug = ? AND status = 'published'
            LIMIT 1
        ");
        $stmt_params = [$slug];
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;
        
        if ($result->rowCount() === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Article not found']);
            exit;
        }
        
        $article = $result->fetch(PDO::FETCH_ASSOC);
        
        // Increment view count
        $updateStmt = $db->prepare("UPDATE news SET view_count = view_count + 1 WHERE id = ?");
        $stmt_params = [$article['id']];
        $updateStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => ['article' => $article]
        ]);
        exit;
    }
    
    // Get paginated news list
    $category = $_GET['category'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 10), 100);
    $offset = ($page - 1) * $limit;
    
    // Build query
    $query = "SELECT * FROM news WHERE status = 'published'";
    $countQuery = "SELECT COUNT(*) as total FROM news WHERE status = 'published'";
    $params = [];
    $types = "";
    
    if ($category) {
        $query .= " AND category = ?";
        $countQuery .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $query .= " ORDER BY published_at DESC LIMIT ? OFFSET ?";
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
    
    // Get news
    $stmt = $db->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    $news = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $news[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['news' => $news],
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Public news API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch news'
    ]);
}
?>
