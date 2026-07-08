<?php
/**
 * Public Achievements API
 * Returns competition wins for public display
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
    
    $featured = $_GET['featured'] ?? false;
    $level = $_GET['level'] ?? null;
    $limit = min((int)($_GET['limit'] ?? 50), 100);
    
    $query = "SELECT * FROM achievements WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($featured) {
        $query .= " AND is_featured = 1";
    }
    
    if ($level && in_array($level, ['National', 'International'])) {
        $query .= " AND level = ?";
        $params[] = $level;
        $types .= "s";
    }
    
    $query .= " ORDER BY is_featured DESC, achievement_date DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    $achievements = [];
    while ($row = $result->fetch_assoc()) {
        $achievements[] = [
            'id' => (int)$row['id'],
            'studentName' => $row['student_name'],
            'competitionName' => $row['competition_name'],
            'position' => $row['position'],
            'level' => $row['level'],
            'date' => $row['achievement_date'],
            'image' => $row['image_url'],
            'description' => $row['description'],
            'teamName' => $row['team_name'],
            'teamLogo' => $row['team_logo_url'],
            'isFeatured' => (bool)$row['is_featured']
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['achievements' => $achievements],
        'total' => count($achievements)
    ]);
    
} catch (Exception $e) {
    error_log("Public achievements API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch achievements']);
}
?>
