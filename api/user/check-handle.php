<?php
/**
 * Check Handle API
 * Checks if a user's handle exists in the codeforces_handles (admin) table
 * Used for auto-fetching handles on the Profile page
 */

require_once '../../config/cors.php';
// Note: cors.php handles OPTIONS preflight and sets Content-Type: application/json

require_once '../../config/database.php';

// Only allow GET requests (OPTIONS is already handled by cors.php)
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get session token from header
$headers = getallheaders();
$sessionToken = $headers['Authorization'] ?? null;

if (!$sessionToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Remove "Bearer " prefix if present
$sessionToken = str_replace('Bearer ', '', $sessionToken);

// Get database connection
$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify session token and get user info
$stmt = $conn->prepare("
    SELECT u.id, u.full_name
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
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Invalid or expired session']);
    exit;
}

$user = $result->fetch(PDO::FETCH_ASSOC);
$userId = $user['id'];
$fullName = $user['full_name'];
$stmt->close();

// First, check if user already has a handle in coder_handles
$stmt = $conn->prepare("SELECT codeforces_handle FROM coder_handles WHERE user_id = ?");
$stmt_params = [$userId];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$existingResult = $stmt;

if ($existingResult->rowCount() > 0) {
    // User already has a handle linked
    $existingHandle = $existingResult->fetch(PDO::FETCH_ASSOC)['codeforces_handle'];
    $stmt->close();
    closeDBConnection($conn);
    echo json_encode([
        'success' => true,
        'found' => true,
        'handle' => $existingHandle,
        'source' => 'profile',
        'message' => 'Handle already linked to your profile'
    ]);
    exit;
}
$stmt->close();

// Check if there's a matching handle in codeforces_handles by user's full name
$stmt = $conn->prepare("
    SELECT handle, name 
    FROM codeforces_handles 
    WHERE LOWER(name) = LOWER(?) 
    LIMIT 1
");
$stmt_params = [$fullName];
$stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
$matchResult = $stmt;

if ($matchResult->rowCount() > 0) {
    $match = $matchResult->fetch(PDO::FETCH_ASSOC);
    $stmt->close();
    closeDBConnection($conn);
    echo json_encode([
        'success' => true,
        'found' => true,
        'handle' => $match['handle'],
        'source' => 'admin_table',
        'message' => 'Handle found matching your name. Click Save to link it to your profile.'
    ]);
    exit;
}
$stmt->close();

closeDBConnection($conn);

// No matching handle found
echo json_encode([
    'success' => true,
    'found' => false,
    'handle' => null,
    'message' => 'No existing handle found'
]);
?>
