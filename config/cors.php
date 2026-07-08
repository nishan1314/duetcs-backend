<?php
// CORS headers for allowing requests from React frontend

// Get the origin from various possible sources
$origin = '';
if (isset($_SERVER['HTTP_ORIGIN'])) {
    $origin = $_SERVER['HTTP_ORIGIN'];
} elseif (isset($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);
    $origin = $parsed['scheme'] . '://' . $parsed['host'];
    if (isset($parsed['port'])) {
        $origin .= ':' . $parsed['port'];
    }
}

// For development: Allow all local origins (localhost and private IP ranges)
// In production, you should restrict this to specific domains
$isLocalOrigin = preg_match('/^https?:\/\/(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3}|10\.\d{1,3}\.\d{1,3}\.\d{1,3}|172\.(1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3})(:\d+)?$/', $origin);
$isVercel = $origin === 'https://duetcs.vercel.app';

if ($isLocalOrigin || $isVercel) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (!empty($origin)) {
    // If origin is provided but doesn't match our patterns, still try to allow it in development
    // This helps with network access from phones/tablets on local network
    header("Access-Control-Allow-Origin: $origin");
} else {
    // No origin header - likely a direct server request or a same-origin request
    // Allow any localhost/local IP for development
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400"); // Cache preflight for 24 hours
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request - must exit before any other processing
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
