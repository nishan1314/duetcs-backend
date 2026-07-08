<?php
/**
 * Admin Logout API
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/AuditLog.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'METHOD_NOT_ALLOWED',
        'message' => 'Only POST method is allowed'
    ]);
    exit;
}

// Log logout if authenticated
if (Auth::getInstance()->check()) {
    AuditLog::logLogout();
}

// Destroy session
Auth::getInstance()->logout();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
