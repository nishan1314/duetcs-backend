<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow CORS for health check

try {
    require_once __DIR__ . '/../../config/database.php';
    
    // Attempt to get connection
    $db = Database::getInstance()->getConnection();
    
    if ($db) {
        echo json_encode(['status' => 'ok', 'message' => 'Database connection successful']);
    } else {
        throw new Exception("Connection object is null");
    }
} catch (Exception $e) {
    http_response_code(503); // Service Unavailable
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed']);
}
