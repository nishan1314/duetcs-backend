<?php
/**
 * Achievements Content Management API
 * Manages competition wins and achievements
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
            handleGetAchievements($db, $adminAuth);
            break;
        case 'POST':
            handleCreateAchievement($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateAchievement($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteAchievement($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Achievements management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleGetAchievements($db, $adminAuth) {
    $adminAuth->requirePermission('achievements.manage');
    
    $level = $_GET['level'] ?? 'all';
    
    $where = "1=1";
    $params = [];
    $types = "";
    
    if ($level !== 'all') {
        $where = "level = ?";
        $params[] = $level;
        $types = "s";
    }
    
    $sql = "SELECT * FROM achievements 
            WHERE $where 
            ORDER BY is_featured DESC, achievement_date DESC";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
    } else {
        $result = $db->query($sql);
    }
    
    $achievements = [];
    while ($row = $result->fetch_assoc()) {
        $achievements[] = [
            'id' => $row['id'],
            'studentName' => $row['student_name'],
            'competitionName' => $row['competition_name'],
            'position' => $row['position'],
            'level' => $row['level'],
            'date' => $row['achievement_date'],
            'image' => $row['image_url'],
            'description' => $row['description'],
            'teamName' => $row['team_name'],
            'teamLogo' => $row['team_logo_url'],
            'isFeatured' => (bool)$row['is_featured'],
            'createdAt' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $achievements]);
}

function handleCreateAchievement($db, $adminAuth) {
    $adminAuth->requirePermission('achievements.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $studentName = trim($input['studentName'] ?? '');
    $competitionName = trim($input['competitionName'] ?? '');
    $position = trim($input['position'] ?? '');
    $level = $input['level'] ?? 'National';
    $date = $input['date'] ?? date('Y-m-d');
    $imageUrl = $input['image'] ?? null;
    $description = trim($input['description'] ?? '');
    $teamName = trim($input['teamName'] ?? '');
    $teamLogoUrl = $input['teamLogo'] ?? null;
    $isFeatured = isset($input['isFeatured']) ? ($input['isFeatured'] ? 1 : 0) : 0;
    $createdBy = $_SESSION['user_id'];
    
    if (empty($studentName) || empty($competitionName) || empty($position)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Student/Team name, competition name, and position are required']);
        return;
    }
    
    $stmt = $db->prepare("
        INSERT INTO achievements (student_name, competition_name, position, level, achievement_date, image_url, description, team_name, team_logo_url, is_featured)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_params = [$studentName, $competitionName, $position, $level, $date, $imageUrl, $description, $teamName, $teamLogoUrl, $isFeatured];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Achievement created successfully', 'achievement_id' => $stmt->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create achievement: ' . $db->error]);
    }
}

function handleUpdateAchievement($db, $adminAuth) {
    $adminAuth->requirePermission('achievements.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $achievementId = intval($input['id'] ?? 0);
    
    if (!$achievementId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Achievement ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $fieldMapping = [
        'studentName' => ['db' => 'student_name', 'type' => 's'],
        'competitionName' => ['db' => 'competition_name', 'type' => 's'],
        'position' => ['db' => 'position', 'type' => 's'],
        'level' => ['db' => 'level', 'type' => 's'],
        'date' => ['db' => 'achievement_date', 'type' => 's'],
        'image' => ['db' => 'image_url', 'type' => 's'],
        'description' => ['db' => 'description', 'type' => 's'],
        'teamName' => ['db' => 'team_name', 'type' => 's'],
        'teamLogo' => ['db' => 'team_logo_url', 'type' => 's'],
        'isFeatured' => ['db' => 'is_featured', 'type' => 'i']
    ];
    
    foreach ($fieldMapping as $inputField => $config) {
        if (isset($input[$inputField])) {
            $updates[] = "{$config['db']} = ?";
            $params[] = $input[$inputField];
            $types .= $config['type'];
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $achievementId;
    $types .= "i";
    
    $sql = "UPDATE achievements SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Achievement updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update achievement']);
    }
}

function handleDeleteAchievement($db, $adminAuth) {
    $adminAuth->requirePermission('achievements.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $achievementId = intval($input['id'] ?? 0);
    
    if (!$achievementId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Achievement ID is required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM achievements WHERE id = ?");
    $stmt_params = [$achievementId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Achievement deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete achievement']);
    }
}
