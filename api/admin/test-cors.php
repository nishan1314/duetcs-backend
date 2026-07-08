<?php
/**
 * Simple CORS test endpoint
 */

// Enable error reporting for debugging
ini_set('display_errors', '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Log all request details
error_log("=== REQUEST DEBUG ===");
error_log("Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Origin: " . ($_SERVER['HTTP_ORIGIN'] ?? 'none'));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'none'));

require_once __DIR__ . '/../../config/cors.php';

try {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'CORS test successful',
        'method' => $_SERVER['REQUEST_METHOD'],
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'headers' => [
            'content-type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
            'http-origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

