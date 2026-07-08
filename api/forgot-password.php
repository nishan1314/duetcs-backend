<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';
require_once '../utils/email.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate email
if (empty($input['email'])) {
    sendResponse(400, false, 'Email is required');
}

$email = sanitizeInput($input['email']);

if (!isValidEmail($email)) {
    sendResponse(400, false, 'Invalid email format');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() === 0) {
    $stmt->close();
    closeDBConnection($conn);
    // Return success even if user not found (security - don't reveal if email exists)
    sendResponse(200, true, 'If an account with that email exists, a password reset link has been sent.');
}

$user = $result->fetch(PDO::FETCH_ASSOC);
$stmt->close();

// Check for rate limiting (1 reset request per 2 minutes)
$stmt = $conn->prepare("SELECT created_at FROM password_resets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt_params = [$user['id']];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() > 0) {
    $lastReset = $result->fetch(PDO::FETCH_ASSOC);
    $lastResetTime = strtotime($lastReset['created_at']);
    $currentTime = time();
    
    if (($currentTime - $lastResetTime) < 120) { // 2 minutes
        $stmt->close();
        closeDBConnection($conn);
        sendResponse(429, false, 'Please wait 2 minutes before requesting another password reset');
    }
}
$stmt->close();

// Generate reset token
$resetToken = generateToken(64);
$expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

// Invalidate any existing reset tokens for this user
$stmt = $conn->prepare("UPDATE password_resets SET is_used = TRUE WHERE user_id = ? AND is_used = FALSE");
$stmt_params = [$user['id']];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$stmt->close();

// Insert new reset token
$stmt = $conn->prepare("INSERT INTO password_resets (user_id, reset_token, expires_at) VALUES (?, ?, ?)");
$stmt_params = [$user['id'], $resetToken, $expiresAt];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to generate password reset token');
}
$stmt->close();
closeDBConnection($conn);

// Send password reset email
$emailSent = sendPasswordResetEmail($user['email'], $user['full_name'], $resetToken);

if (!$emailSent) {
    error_log("Failed to send password reset email to: " . $user['email']);
}

// Log for development
error_log("Password Reset Token for " . $user['email'] . ": " . $resetToken);

sendResponse(200, true, 'If an account with that email exists, a password reset link has been sent.', [
    'email' => $email
]);
?>
