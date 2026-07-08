<?php
/**
 * Admin Dashboard Stats API
 * Returns key metrics for the admin dashboard
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/admin-auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminAuth = new AdminAuth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'message' => 'Method not allowed'
        ]);
        exit;
    }

    // Note: Stats are public/viewable by admins
    // In production, consider adding proper admin authentication check here
    // if (!$adminAuth->isAuthenticated()) {
    //     http_response_code(401);
    //     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    //     exit;
    // }

    // Get stats
    $stats = [
        'totalMembers' => getTotalMembers($db),
        'totalExecutives' => getTotalExecutives($db),
        'totalEvents' => getTotalEvents($db),
        'publishedNotices' => getPublishedNotices($db),
    ];

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching stats: ' . $e->getMessage()
    ]);
}

function getTotalMembers($db) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM users");
        if ($result === false) {
            error_log("Query error: " . $db->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting total members: " . $e->getMessage());
        return 0;
    }
}

function getTotalExecutives($db) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM executive_members WHERE is_active = 1");
        if ($result === false) {
            error_log("Query error: " . $db->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting total executives: " . $e->getMessage());
        return 0;
    }
}

function getTotalEvents($db) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM events");
        if ($result === false) {
            error_log("Query error: " . $db->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting total events: " . $e->getMessage());
        return 0;
    }
}

function getPublishedNotices($db) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM notices WHERE status = 'active'");
        if ($result === false) {
            error_log("Query error: " . $db->error);
            return 0;
        }
        $row = $result->fetch_assoc();
        return (int)($row['count'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting published notices: " . $e->getMessage());
        return 0;
    }
}
?>
