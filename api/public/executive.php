<?php
/**
 * Public Executive Board API
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
    
    $active = $_GET['active'] ?? false;
    $session = $_GET['session'] ?? $_GET['term_year'] ?? null;
    $memberType = $_GET['member_type'] ?? null;
    
    $query = "SELECT * FROM executive_members WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($active) {
        $query .= " AND is_active = 1";
    }
    
    if ($session) {
        $query .= " AND session = ?";
        $params[] = $session;
        $types .= "s";
    }
    
    if ($memberType) {
        $query .= " AND member_type = ?";
        $params[] = $memberType;
        $types .= "s";
    }
    
    $query .= " ORDER BY position ASC";
    
    $stmt = $db->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    $members = [];
    while ($row = $result->fetch_assoc()) {
        $members[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'position' => $row['position'],
            'memberType' => $row['member_type'],
            'session' => $row['session'],
            'designation' => $row['designation'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'image' => $row['image_url'],
            'isActive' => (bool)$row['is_active'],
            'studentId' => $row['student_id'] ?? null,
            'yearSemester' => $row['year_sem'] ?? null
        ];
    }
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => ['members' => $members]
    ]);
    
} catch (Exception $e) {
    error_log("Public executive API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch executive members: ' . $e->getMessage()]);
}
?>
