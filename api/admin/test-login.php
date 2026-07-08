<?php
/**
 * Simple login test without dependencies
 */

header('Content-Type: application/json; charset=UTF-8');

// Load CORS
require_once __DIR__ . '/../../config/cors.php';

error_log("Test Login - Method: " . $_SERVER['REQUEST_METHOD']);

// Only allow POST or OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Expected POST, got ' . $_SERVER['REQUEST_METHOD']
    ]);
    exit;
}

// Get the input
$input = json_decode(file_get_contents('php://input'), true);

error_log("Test Login Input: " . json_encode($input));

echo json_encode([
    'success' => true,
    'message' => 'Test login received POST correctly',
    'received' => $input,
    'method' => $_SERVER['REQUEST_METHOD']
]);
?>
