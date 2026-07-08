<?php
/**
 * Website Content Management API
 * Manage Hero, About, Features, Legacy sections (stored as JSON)
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
    $section = $_GET['section'] ?? '';
    
    if (empty($section)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Section parameter is required']);
        exit;
    }
    
    switch ($method) {
        case 'GET':
            handleGetContent($db, $adminAuth, $section);
            break;
        case 'PUT':
            handleUpdateContent($db, $adminAuth, $section);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Content management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetContent($db, $adminAuth, $section) {
    $adminAuth->requirePermission('content.view');
    
    $validSections = ['hero', 'about', 'features', 'legacy'];
    if (!in_array($section, $validSections)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid section']);
        return;
    }
    
    $stmt = $db->prepare("SELECT content_data, updated_at FROM website_content WHERE section_name = ?");
    $stmt_params = [$section];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    if ($result->rowCount() > 0) {
        $row = $result->fetch(PDO::FETCH_ASSOC);
        echo json_encode([
            'success' => true,
            'data' => json_decode($row['content_data'], true),
            'updatedAt' => $row['updated_at']
        ]);
    } else {
        // Return empty structure for section
        echo json_encode([
            'success' => true,
            'data' => getDefaultContent($section),
            'updatedAt' => null
        ]);
    }
}

function handleUpdateContent($db, $adminAuth, $section) {
    try {
        error_log("Starting handleUpdateContent for section: " . $section);
        
        // Log raw input for debugging (first 100 chars)
        $rawInput = file_get_contents('php://input');
        error_log("Raw input size: " . strlen($rawInput));
        error_log("Raw input preview: " . substr($rawInput, 0, 100));

        $adminAuth->requirePermission('content.edit');
        error_log("Permission check passed");
        
        $validSections = ['hero', 'about', 'features', 'legacy'];
        if (!in_array($section, $validSections)) {
            error_log("Invalid section: " . $section);
            throw new Exception("Invalid section");
        }
        
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON Decode Error: " . json_last_error_msg());
            throw new Exception("Invalid JSON input: " . json_last_error_msg());
        }
        
        if (!isset($input['content'])) {
            error_log("Missing content key in input");
            throw new Exception("Content data is required");
        }
        
        $contentData = json_encode($input['content']);
        if ($contentData === false) {
             error_log("JSON Encode Error for DB: " . json_last_error_msg());
             throw new Exception("Failed to encode content for database");
        }

        error_log("Payload size for DB: " . strlen($contentData) . " bytes");
        
        // Get user ID safely
        $updatedBy = null;
        if (isset($_SESSION['user_id'])) {
            $updatedBy = $_SESSION['user_id'];
        }
        // System admins might not be in the users table, so we leave it as NULL
        // The column has been altered to allow NULL values
        
        error_log("Updated by User ID: " . ($updatedBy ?? 'NULL'));
        
        // Check if record exists
        $checkStmt = $db->prepare("SELECT id FROM website_content WHERE section_name = ?");
        if (!$checkStmt) throw new Exception("Prepare check failed: " . $db->error);
        
        $stmt_params = [$section];
        $checkStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $checkResult = $checkStmt;
        
        if ($checkResult->rowCount() > 0) {
            // Update existing
            error_log("Updating existing record");
            $stmt = $db->prepare("UPDATE website_content SET content_data = ?, last_updated_by = ? WHERE section_name = ?");
            if (!$stmt) throw new Exception("Prepare update failed: " . $db->error);
            $stmt_params = [$contentData, $updatedBy, $section];
        } else {
            // Insert new
            error_log("Inserting new record");
            $stmt = $db->prepare("INSERT INTO website_content (section_name, content_data, last_updated_by) VALUES (?, ?, ?)");
            if (!$stmt) throw new Exception("Prepare insert failed: " . $db->error);
            $stmt_params = [$section, $contentData, $updatedBy];
        }
        
        if ($stmt->execute()) {
            error_log("Successfully updated section: " . $section);
            echo json_encode(['success' => true, 'message' => 'Content updated successfully']);
        } else {
            error_log("Database execute error: " . $stmt->error);
            throw new Exception("Database error: " . $stmt->error);
        }
    } catch (Exception $e) {
        error_log("handleUpdateContent Exception: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getDefaultContent($section) {
    switch ($section) {
        case 'hero':
            return [
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
        case 'about':
            return [
                'header' => [
                    'title' => "About DUETCS",
                    'subtitle' => "Empowering students through technology education, innovation, and professional development"
                ],
                'mission' => [
                    'title' => "Our Mission",
                    'content' => [
                        "DUET Computer Society, founded on 2010, by Md Mahbub Alam and CSE 7th batch, works alongside with CSE Department, DUET to bring the tech skill gap and new world problem solving and addressing gap down, locally at DUET, Gazipur.",
                        "Computer Science, as a field, is growing faster, in a mesmerizing speed. Tackling new world problems and solving them becomes an important aspect for every CS graduate out there. DUETCS tries and loves to be the part of the process.",
                        "Advancing efforts to bridge the technology skills gap and develop innovative solutions to the complex problems of the modern world."
                    ],
                    'highlights' => [
                        ['label' => "Established", 'value' => "2010"],
                        ['label' => "Alumni Network", 'value' => "500+"]
                    ]
                ],
                'features' => [
                    [
                        'title' => "Technical Workshops",
                        'description' => "Hands-on sessions covering latest technologies and programming languages",
                        'icon' => "BookOpen"
                    ],
                    [
                        'title' => "Networking Events",
                        'description' => "Connect with industry professionals and build meaningful relationships",
                        'icon' => "Users"
                    ],
                    [
                        'title' => "Competitions",
                        'description' => "Participate in hackathons, coding contests, and innovation challenges",
                        'icon' => "Award"
                    ],
                    [
                        'title' => "Career Development",
                        'description' => "Interview prep, resume reviews, and professional skill development",
                        'icon' => "TrendingUp"
                    ]
                ]
            ];
        case 'features':
            return [
                'title' => 'What We Offer',
                'items' => []
            ];
        case 'legacy':
            return [
                'title' => 'Journey of Excellence',
                'description' => "A look back at the creativity and passion that define our club's journey.",
                'milestones' => [
                    [
                        'id' => 1,
                        'title' => "DUET IUPC 2025",
                        'date' => "May 09-10, 2025",
                        'description' => "The Programming Contest segment of the DUET IUPC 2025 offers an exciting opportunity for competitive programmers to showcase their skills.",
                        'mainImage' => "./img/legacy/s1.jpg",
                        'thumbnails' => [
                            "./img/legacy/s1.jpg",
                            "./img/legacy/s2.jpg",
                            "./img/legacy/s3.jpg",
                            "./img/legacy/s4.jpg",
                            "./img/legacy/s5.jpg",
                        ],
                    ],
                    [
                        'id' => 2,
                        'title' => "IDPC 2024",
                        'date' => "March 02, 2024",
                        'description' => "IDPC 2024 continues our commitment to narrowing the tech skills gap and preparing students to tackle real-world problem-solving challenges with confidence.",
                        'mainImage' => "/img/legacy/t1.jpg",
                        'thumbnails' => [
                            "/img/legacy/t1.jpg",
                            "/img/legacy/t2.jpg",
                            "/img/legacy/t3.jpg",
                            "/img/legacy/t4.jpg",
                            "/img/legacy/t5.jpg",
                            "/img/legacy/t6.jpg",
                        ],
                    ],
                ],
                'images' => []
            ];
        default:
            return [];
    }
}
