<?php
// Generate random token
function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Send JSON response
function sendResponse($statusCode, $success, $message, $data = null) {
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Get current timestamp for MySQL
function getCurrentTimestamp() {
    return date('Y-m-d H:i:s');
}

// Get expiry timestamp (default 24 hours from now)
function getExpiryTimestamp($hours = 24) {
    return date('Y-m-d H:i:s', strtotime("+{$hours} hours"));
}

// Get expiry timestamp in minutes
function getExpiryTimestampMinutes($minutes = 15) {
    return date('Y-m-d H:i:s', strtotime("+{$minutes} minutes"));
}

// Generate 6-digit verification code
function generateVerificationCode($length = 6) {
    return str_pad(random_int(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
}
?>
