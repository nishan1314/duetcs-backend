<?php
require_once '../../config/cors.php';
require_once '../../config/database.php';
require_once '../../utils/helpers.php';
require_once '../../utils/email.php';

// Only allow POST requests for code verification
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['email'])) {
    sendResponse(400, false, 'Email is required');
}

if (empty($input['code'])) {
    sendResponse(400, false, 'Verification code is required');
}

$email = sanitizeInput($input['email']);
$code = sanitizeInput($input['code']);

// Validate email format
if (!isValidEmail($email)) {
    sendResponse(400, false, 'Invalid email format');
}

// Validate code format (6 digits)
if (!preg_match('/^\d{6}$/', $code)) {
    sendResponse(400, false, 'Invalid verification code format');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Get user by email
$stmt = $conn->prepare("SELECT id, full_name, email, is_verified FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(404, false, 'Email not found');
}

$user = $result->fetch_assoc();
$stmt->close();

// Check if already verified
if ($user['is_verified']) {
    closeDBConnection($conn);
    sendResponse(400, false, 'Email already verified');
}

// Get verification record by user_id and code
$stmt = $conn->prepare("
    SELECT id, expires_at, is_used
    FROM email_verifications
    WHERE user_id = ? AND verification_token = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt_params = [$user['id'], $code];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(400, false, 'Invalid verification code');
}

$verification = $result->fetch_assoc();
$stmt->close();

// Check if already used
if ($verification['is_used']) {
    closeDBConnection($conn);
    sendResponse(400, false, 'Verification code already used');
}

// Check if code expired
$currentTime = getCurrentTimestamp();
if ($currentTime > $verification['expires_at']) {
    closeDBConnection($conn);
    sendResponse(400, false, 'Verification code has expired. Please request a new one.');
}

// Update user as verified
$stmt = $conn->prepare("UPDATE users SET is_verified = TRUE WHERE id = ?");
$stmt_params = [$user['id']];
if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to verify email');
}
$stmt->close();

// Mark code as used
$stmt = $conn->prepare("UPDATE email_verifications SET is_used = TRUE WHERE id = ?");
$stmt_params = [$verification['id']];
$stmt->execute($stmt_params ?? null);
$stmt->close();

closeDBConnection($conn);

// Send welcome email
sendWelcomeEmail($user['email'], $user['full_name']);

// Return success response
sendResponse(200, true, 'Email verified successfully! You can now login.', [
    'userId' => $user['id'],
    'email' => $user['email'],
    'fullName' => $user['full_name']
]);
?>
