<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

// Start session
session_start();

// Log the request
error_log("Login attempt started");
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Wrong method: " . $_SERVER['REQUEST_METHOD']);
    sendResponse(405, false, 'Method not allowed');
}

// Get JSON input
$inputRaw = file_get_contents('php://input');
error_log("Raw input: " . $inputRaw);

$input = json_decode($inputRaw, true);
error_log("Decoded input: " . json_encode($input));

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    error_log("Missing fields");
    sendResponse(400, false, 'Email and password are required');
}

$email = sanitizeInput($input['email']);
$password = $input['password'];

error_log("Email: " . $email);
error_log("Password length: " . strlen($password));

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    error_log("Database connection failed");
    sendResponse(500, false, 'Database connection failed');
}

error_log("Database connected");

// Get user by email
$stmt = $conn->prepare("SELECT id, full_name, email, student_id, department, year_semester, password, is_verified FROM users WHERE email = ?");
$stmt_params = [$email];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

error_log("Query executed, rows: " . $result->num_rows);

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    error_log("User not found");
    sendResponse(401, false, 'Invalid email or password');
}

$user = $result->fetch_assoc();
$stmt->close();

error_log("User found: ID=" . $user['id'] . ", Email=" . $user['email'] . ", Verified=" . $user['is_verified']);
error_log("Stored password hash: " . substr($user['password'], 0, 20) . "...");

// Verify password
$passwordMatch = password_verify($password, $user['password']);
error_log("Password verification result: " . ($passwordMatch ? 'MATCH' : 'NO MATCH'));

if (!$passwordMatch) {
    closeDBConnection($conn);
    error_log("Password mismatch");
    sendResponse(401, false, 'Invalid email or password');
}

// Check if email is verified
if (!$user['is_verified']) {
    closeDBConnection($conn);
    error_log("Email not verified");
    sendResponse(403, false, 'Please verify your email before logging in');
}

error_log("All checks passed, creating session");

// Generate session token
$sessionToken = generateToken();
$expiresAt = getExpiryTimestamp(168); // 7 days

// Get user IP and user agent
$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

error_log("Session token generated: " . substr($sessionToken, 0, 20) . "...");

// Create login session
$stmt = $conn->prepare("INSERT INTO login_sessions (user_id, session_token, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, ?)");
$stmt_params = [$user['id'], $sessionToken, $ipAddress, $userAgent, $expiresAt];
$executeResult = $stmt->execute($stmt_params ?? null);

error_log("Session insert result: " . ($executeResult ? 'SUCCESS' : 'FAILED'));

if (!$executeResult) {
    error_log("Session insert error: " . $stmt->error);
}

$stmt->close();
closeDBConnection($conn);

// Set session variables
$_SESSION['user_id'] = $user['id'];
$_SESSION['session_token'] = $sessionToken;

// Remove password from user data
unset($user['password']);

error_log("Login successful, sending response");

// Return success response
sendResponse(200, true, 'Login successful', [
    'user' => $user,
    'sessionToken' => $sessionToken
]);
?>
