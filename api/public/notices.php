<?php
/**
 * Public Notices API - Fetch published notices for users
 * GET: List all active/published notices
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

    // Get notices
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM notices WHERE status = 'active'";
    $countResult = $db->query($countSql);
    $countRow = $countResult->fetch(PDO::FETCH_ASSOC);
    $total = $countRow['total'];

    // Get notices
    $sql = "SELECT n.*, u.full_name as created_by_name 
            FROM notices n 
            LEFT JOIN users u ON n.created_by = u.id 
            WHERE n.status = 'active'
            ORDER BY n.priority = 'high' DESC, n.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Database prepare error: ' . $db->error);
    }

    $stmt_params = [$limit, $offset];
    if (!$stmt->execute()) {
        throw new Exception('Database execute error: ' . $stmt->error);
    }

    $result = $stmt;

    $notices = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $notices[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'message' => $row['description'], // For compatibility
            'type' => $row['type'] === 'pdf' ? 'document' : 'announcement',
            'priority' => $row['priority'],
            'status' => $row['status'],
            'pdfUrl' => $row['pdf_url'],
            'pdfName' => $row['pdf_name'],
            'createdBy' => $row['created_by_name'] ?? 'DUETCS Admin',
            'createdAt' => date('M d, Y', strtotime($row['created_at'])),
            'createdAtTime' => $row['created_at'],
            'read' => false, // Users see all as unread initially
        ];
    }

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $notices,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Public notices error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notices',
        'error' => $e->getMessage()
    ]);
}
?>
