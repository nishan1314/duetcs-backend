<?php
/**
 * Test Authentication Endpoint
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/cors.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Check if session exists
    $sessionData = [
        'hasSession' => !empty($_SESSION),
        'sessionId' => session_id(),
        'userId' => $_SESSION['user_id'] ?? null,
        'email' => $_SESSION['email'] ?? null,
        'isVerified' => isset($_SESSION['is_verified']) ? $_SESSION['is_verified'] : null,
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Auth test endpoint',
        'session' => $sessionData,
        'cookies' => count($_COOKIE),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
