<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../utils/helpers.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['token'])) {
    sendResponse(400, false, 'Reset token is required');
}

if (empty($input['password'])) {
    sendResponse(400, false, 'New password is required');
}

$resetToken = sanitizeInput($input['token']);
$newPassword = $input['password'];

// Validate password length
if (strlen($newPassword) < 6) {
    sendResponse(400, false, 'Password must be at least 6 characters long');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Find the reset token
$stmt = $conn->prepare("
    SELECT pr.id, pr.user_id, pr.expires_at, pr.is_used, u.email, u.full_name
    FROM password_resets pr
    JOIN users u ON pr.user_id = u.id
    WHERE pr.reset_token = ?
");
$stmt_params = [$resetToken];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(400, false, 'Invalid or expired reset token');
}

$resetRecord = $result->fetch_assoc();
$stmt->close();

// Check if token is already used
if ($resetRecord['is_used']) {
    closeDBConnection($conn);
    sendResponse(400, false, 'This reset link has already been used');
}

// Check if token is expired
if (strtotime($resetRecord['expires_at']) < time()) {
    closeDBConnection($conn);
    sendResponse(400, false, 'This reset link has expired. Please request a new one.');
}

// Hash the new password
$hashedPassword = hashPassword($newPassword);

// Update user's password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt_params = [$hashedPassword, $resetRecord['user_id']];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to update password');
}
$stmt->close();

// Mark token as used
$stmt = $conn->prepare("UPDATE password_resets SET is_used = TRUE WHERE id = ?");
$stmt_params = [$resetRecord['id']];
$stmt->execute($stmt_params ?? null);
$stmt->close();

// Invalidate all login sessions for security
$stmt = $conn->prepare("DELETE FROM login_sessions WHERE user_id = ?");
$stmt_params = [$resetRecord['user_id']];
$stmt->execute($stmt_params ?? null);
$stmt->close();

closeDBConnection($conn);

sendResponse(200, true, 'Password has been reset successfully. You can now login with your new password.');
?>
