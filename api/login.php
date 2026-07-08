<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

// Start session
session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(405, false, 'Method not allowed');
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    sendResponse(400, false, 'Email and password are required');
}

$email = sanitizeInput($input['email']);
$password = $input['password'];

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Get user by email
$stmt = $conn->prepare("SELECT id, full_name, email, student_id, department, year_semester, password, is_verified, is_active, profile_image, created_at FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$result = $stmt;

if ($result->rowCount() === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(401, false, 'Invalid email or password');
}

$user = $result->fetch(PDO::FETCH_ASSOC);
$stmt->close();

// Verify password
if (!verifyPassword($password, $user['password'])) {
    closeDBConnection($conn);
    sendResponse(401, false, 'Invalid email or password');
}

// Check if account is active
if (!$user['is_active']) {
    closeDBConnection($conn);
    sendResponse(403, false, 'Your account has been deactivated. Please contact support.');
}

// Check if email is verified
if (!$user['is_verified']) {
    closeDBConnection($conn);
    sendResponse(403, false, 'Please verify your email before logging in');
}

// Generate session token
$sessionToken = generateToken();
$expiresAt = getExpiryTimestamp(168); // 7 days

// Get user IP and user agent
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Create login session
$stmt = $conn->prepare("INSERT INTO login_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt_params = [$user['id'], $sessionToken, $ipAddress, $userAgent, $expiresAt];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$stmt->close();

closeDBConnection($conn);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['session_token'] = $sessionToken;

// Remove password from user data
unset($user['password']);

// Return success response
sendResponse(200, true, 'Login successful', [
    'user' => $user,
    'sessionToken' => $sessionToken
]);
?>
