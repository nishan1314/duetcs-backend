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

// Validate required fields
if (empty($input['email'])) {
    sendResponse(400, false, 'Email is required');
}

$email = sanitizeInput($input['email']);

// Validate email
if (!isValidEmail($email)) {
    sendResponse(400, false, 'Invalid email format');
}

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Check if user exists
$stmt = $conn->prepare("SELECT id, full_name, email, is_verified FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(404, false, 'Email not found');
}

$user = $result->fetch(PDO::FETCH_ASSOC);
$stmt->close();

// Check if already verified
if ($user['is_verified']) {
    closeDBConnection($conn);
    sendResponse(400, false, 'Email already verified. You can login now.');
}

// Check for recent verification emails (prevent spam)
$stmt = $conn->prepare("
    SELECT created_at 
    FROM email_verifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt_params = [$user['id']];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() > 0) {
    $lastEmail = $result->fetch(PDO::FETCH_ASSOC);
    $lastSent = strtotime($lastEmail['created_at']);
    $now = time();
    $minutesAgo = ($now - $lastSent) / 60;
    
    // Prevent sending more than once per minute
    if ($minutesAgo < 1) {
        $stmt->close();
        closeDBConnection($conn);
        sendResponse(429, false, 'Please wait a minute before requesting another verification email');
    }
}
$stmt->close();

// Invalidate old codes (mark as used)
$stmt = $conn->prepare("UPDATE email_verifications SET is_used = TRUE WHERE user_id = ? AND is_used = FALSE");
$stmt_params = [$user['id']];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$stmt->close();

// Generate new 6-digit verification code with 15-minute expiry
$verificationCode = generateVerificationCode();
$expiryMinutes = 15;
$expiresAt = getExpiryTimestampMinutes($expiryMinutes);

// Insert new verification code
$stmt = $conn->prepare("INSERT INTO email_verifications (user_id, verification_token, expires_at) VALUES (?, ?, ?)");
$stmt_params = [$user['id'], $verificationCode, $expiresAt];

if (!$stmt->execute()) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(500, false, 'Failed to generate verification code');
}

$stmt->close();
closeDBConnection($conn);

// Send verification email with code
$emailSent = sendVerificationEmail($email, $user['full_name'], $verificationCode, $expiryMinutes);

if (!$emailSent) {
    error_log("Failed to resend verification email to: " . $email);
    sendResponse(500, false, 'Failed to send verification email. Please try again later.');
}

// Return success response
sendResponse(200, true, 'Verification code sent! Please check your inbox.', [
    'email' => $email
]);
?>
