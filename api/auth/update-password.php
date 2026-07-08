<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../utils/helpers.php';

// Start session
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get session token from header
$headers = getallheaders();
$sessionToken = $headers['Authorization'] ?? null;

if (!$sessionToken) {
    sendResponse(401, false, 'Unauthorized - No session token provided');
}

// Remove "Bearer " prefix if present
$sessionToken = str_replace('Bearer ', '', $sessionToken);

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['currentPassword']) || empty($input['newPassword'])) {
    sendResponse(400, false, 'Current password and new password are required');
}

$currentPassword = $input['currentPassword'];
$newPassword = $input['newPassword'];

// Validate new password strength
if (strlen($newPassword) < 6) {
    sendResponse(400, false, 'New password must be at least 6 characters long');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Get user from session token
$stmt = $conn->prepare("
    SELECT u.id, u.password
    FROM users u
    JOIN login_sessions ls ON u.id = ls.user_id
    WHERE ls.session_token = ? AND ls.expires_at > NOW()
");
$stmt_params = [$sessionToken];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(401, false, 'Invalid or expired session');
}

$user = $result->fetch(PDO::FETCH_ASSOC);
$userId = $user['id'];
$hashedPassword = $user['password'];
$stmt->close();

// Verify current password
if (!verifyPassword($currentPassword, $hashedPassword)) {
    closeDBConnection($conn);
    sendResponse(401, false, 'Current password is incorrect');
}

// Hash new password
$newHashedPassword = hashPassword($newPassword);

// Update password
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$stmt_params = [$newHashedPassword, $userId];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to update password');
}

$stmt->close();
closeDBConnection($conn);

// Return success response
sendResponse(200, true, 'Password updated successfully');
?>
