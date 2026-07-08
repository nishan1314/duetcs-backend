<?php
/**
 * Hero Content Management API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../utils/admin-auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminAuth = new AdminAuth();
$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGetHeroContent($db, $adminAuth);
            break;
        case 'POST':
            handleUpdateHeroContent($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Hero content error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetHeroContent($db, $adminAuth) {
    $adminAuth->requirePermission('content.manage');
    
    try {
        $stmt = $db->prepare("SELECT content_data FROM website_content WHERE section_name = 'hero'");
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;
        
        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            $content = json_decode($row['content_data'], true);
            echo json_encode(['success' => true, 'data' => $content]);
        } else {
             // Default structure matching frontend state
             $defaultContent = [
                'title' => "DUET Computer Society",
                'subtitle' => "Empowering Future Tech Leaders",
                'description' => "Join Bangladesh's most vibrant community of computer science enthusiasts. Learn, grow, and innovate with us.",
                'primaryButtonText' => "Join Now",
                'primaryButtonLink' => "/join-now",
                'secondaryButtonText' => "Learn More",
                'secondaryButtonLink' => "/about",
                'stats' => [
                    ['label' => "Active Members", 'value' => "500+"],
                    ['label' => "Events Organized", 'value' => "100+"],
                    ['label' => "Workshops", 'value' => "50+"],
                ],
            ];
            echo json_encode(['success' => true, 'data' => $defaultContent]);
        }
    } catch (Exception $e) {
        error_log("Hero content fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch hero content']);
    }
}

function handleUpdateHeroContent($db, $adminAuth) {
    $adminAuth->requirePermission('content.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
        return;
    }
    
    if (empty($input['title'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        return;
    }
    
    $jsonContent = json_encode($input);
    
    try {
        $stmt = $db->prepare("
            INSERT INTO website_content (section_name, content_data) 
            VALUES ('hero', ?)
            ON DUPLICATE KEY UPDATE content_data = ?, updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt_params = [$jsonContent, $jsonContent];
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Hero content updated successfully']);
        } else {
            throw new Exception($stmt->error);
        }
    } catch (Exception $e) {
        error_log("Hero content update error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update content']);
    }
}
