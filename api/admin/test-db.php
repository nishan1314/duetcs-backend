<?php
/**
 * Database connection test
 */

// Enable error reporting
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    // Test database config
    require_once __DIR__ . '/../../config/cors.php';
    require_once __DIR__ . '/../../config/database.php';
    
    // Try to get database connection
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("Failed to get database connection");
    }
    
    // Test query
    $result = $db->query("SELECT 1 as test");
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'database' => DB_NAME,
        'host' => DB_HOST,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed',
        'error' => $e->getMessage()
    ]);
}
?>
