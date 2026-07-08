<?php
/**
 * Debug endpoint to see what the request looks like
 */

header('Content-Type: application/json; charset=UTF-8');

// Load CORS first
require_once __DIR__ . '/../../config/cors.php';

$debug = [
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => $_SERVER['REQUEST_URI'],
    'headers' => [
        'content-type' => $_SERVER['CONTENT_TYPE'] ?? 'none',
        'origin' => $_SERVER['HTTP_ORIGIN'] ?? 'none',
        'user-agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'none',
    ],
    'body_size' => strlen(file_get_contents('php://input')),
    'session_status' => session_status(),
    'timestamp' => date('Y-m-d H:i:s'),
];

http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Debug info received',
    'debug' => $debug
]);
?>
