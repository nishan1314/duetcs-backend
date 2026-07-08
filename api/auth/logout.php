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

// Get session token from request or session
$input = json_decode(file_get_contents('php://input'), true);
$sessionToken = $input['sessionToken'] ?? $_SESSION['session_token'] ?? null;

if ($sessionToken) {
    // Get database connection
    $conn = getDBConnection();
    if ($conn) {
        // Delete session from database
        $stmt = $conn->prepare("DELETE FROM login_sessions WHERE session_token = ?");
        $stmt_params = [$sessionToken];
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $stmt->close();
        closeDBConnection($conn);
    }
}

// Destroy PHP session
session_unset();
session_destroy();

// Return success response
sendResponse(200, true, 'Logout successful');
?>
