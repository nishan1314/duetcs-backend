<?php
/**
 * Public Website Content API - Fetch hero, about, features, legacy sections
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $section = $_GET['section'] ?? null;
    
    if (!$section || !in_array($section, ['hero', 'about', 'features', 'legacy'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid section. Valid sections: hero, about, features, legacy']);
        exit;
    }
    
    error_log("Public API: Fetching section [" . ($section ?? 'null') . "]");
    $stmt = $db->prepare("SELECT * FROM website_content WHERE section_name = ? LIMIT 1");
    $stmt_params = [$section];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    if ($result->rowCount() === 0) {
        // Return default content if not found
        $defaultContent = getDefaultContent($section);
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => [
                'content' => [
                    'section_name' => $section,
                    'content_data' => $defaultContent,
                    'updated_at' => date('Y-m-d H:i:s')
                ]
            ]
        ]);
        exit;
    }
    
    $content = $result->fetch(PDO::FETCH_ASSOC);
    error_log("Public API: Found content for [" . $section . "]");
    // Decode JSON content_data
    if (isset($content['content_data'])) {
        $content['content_data'] = json_decode($content['content_data'], true);
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['content' => $content]
    ]);
    
} catch (Exception $e) {
    error_log("Public website content API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch website content']);
}

function getDefaultContent($section) {
    $defaults = [
        'hero' => [
            'title' => 'Welcome to DUETCS',
            'subtitle' => 'DUET Computer Science Society',
            'description' => 'A community of computer science enthusiasts',
            'image_url' => '/img/hero-default.jpg',
            'cta_text' => 'Get Started',
            'cta_link' => '/join'
        ],
        'about' => [
            'title' => 'About DUETCS',
            'description' => 'Learn more about our society',
            'content' => 'DUET Computer Science Society is dedicated to promoting excellence in computer science.',
            'image_url' => '/img/about-default.jpg'
        ],
        'features' => [
            'features' => [
                [
                    'title' => 'Community',
                    'description' => 'Join our vibrant community',
                    'icon' => 'Users'
                ],
                [
                    'title' => 'Learning',
                    'description' => 'Continuous learning opportunities',
                    'icon' => 'BookOpen'
                ],
                [
                    'title' => 'Projects',
                    'description' => 'Collaborate on interesting projects',
                    'icon' => 'Code'
                ]
            ]
        ],
        'legacy' => [
            'title' => 'Journey of Excellence',
            'description' => "A look back at the creativity and passion that define our club's journey.",
            'milestones' => [],
            'images' => []
        ]
    ];
    
    return $defaults[$section] ?? [];
}
?>
