<?php
require_once '../config/cors.php';
require_once '../config/database.php';
require_once '../utils/helpers.php';

// Start session
session_start();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(405, false, 'Method not allowed');
}

// Get session token from header or session
$headers = getallheaders();
$sessionToken = $headers['Authorization'] ?? $_SESSION['session_token'] ?? null;

if (!$sessionToken) {
    sendResponse(401, false, 'Unauthorized - No session token provided');
}

// Remove "Bearer " prefix if present
$sessionToken = str_replace('Bearer ', '', $sessionToken);

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    sendResponse(500, false, 'Database connection failed');
}

// Get user from session token
// Directly fetch handle from 'handles' VIEW where user_id matches
$stmt = $conn->prepare("
    SELECT u.id, u.full_name, u.email, u.student_id, u.department, u.phone_number, u.year_semester, u.is_verified, u.profile_image, u.created_at,
           h.handle as codeforces_handle
    FROM users u
    JOIN login_sessions ls ON u.id = ls.user_id
    LEFT JOIN handles h ON u.id = h.user_id
    WHERE ls.session_token = ? AND ls.expires_at > NOW()
");
$stmt_params = [$sessionToken];
$stmt->execute($stmt_params ?? null);
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    closeDBConnection($conn);
    sendResponse(401, false, 'Invalid or expired session');
}

$user = $result->fetch_assoc();
$stmt->close();
closeDBConnection($conn);

// Return user data
sendResponse(200, true, 'User data retrieved successfully', [
    'user' => $user
]);
?>
