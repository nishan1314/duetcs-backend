<?php
/**
 * Public Wings API
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
    
    $result = $db->query("
        SELECT * FROM wings 
        WHERE is_active = 1 
        ORDER BY display_order ASC
    ");
    
    $wings = [];
    while ($row = $result->fetch_assoc()) {
        $wings[] = $row;
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['wings' => $wings]
    ]);
    
} catch (Exception $e) {
    error_log("Public wings API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch wings']);
}
?>
