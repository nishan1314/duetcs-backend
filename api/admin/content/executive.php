<?php
/**
 * Executive Board Content Management API
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
            handleGetExecutive($db, $adminAuth);
            break;
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (isset($input['action']) && $input['action'] === 'activate_session') {
                handleActivateSession($db, $adminAuth, $input);
            } elseif (isset($input['action']) && $input['action'] === 'delete_session') {
                handleDeleteSession($db, $adminAuth, $input);
            } else {
                handleCreateExecutive($db, $adminAuth); // Standard create
            }
            break;
        case 'PUT':
            handleUpdateExecutive($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteExecutive($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Executive management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetExecutive($db, $adminAuth) {
    $adminAuth->requirePermission('executive.manage');
    
    $session = $_GET['term'] ?? $_GET['session'] ?? 'all';
    $isActive = $_GET['active'] ?? 'all';
    $memberType = $_GET['member_type'] ?? 'all';
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($session !== 'all') {
        $where[] = "em.session = ?";
        $params[] = $session;
        $types .= "s";
    }
    
    if ($isActive !== 'all') {
        $where[] = "em.is_active = ?";
        $params[] = $isActive === 'true' ? 1 : 0;
        $types .= "i";
    }
    
    if ($memberType !== 'all') {
        $where[] = "em.member_type = ?";
        $params[] = $memberType;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
    $sql = "SELECT * 
            FROM executive_members em 
            WHERE $whereClause 
            ORDER BY em.position ASC";
    
    try {
        if (!empty($params)) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute($stmt_params ?? null);
            $result = $stmt->get_result();
        } else {
            $result = $db->query($sql);
        }
        
        $members = [];
        while ($row = $result->fetch_assoc()) {
            $members[] = [
                'id' => $row['id'],
                'name' => $row['name'],
                'position' => $row['position'],
                'memberType' => $row['member_type'] ?? 'student',
                'image' => $row['image_url'],
                'session' => $row['session'],
                'termYear' => $row['session'], // For backwards compatibility
                'designation' => $row['designation'],
                'email' => $row['email'],
                'phone' => $row['phone'],
                'isActive' => (bool)$row['is_active'],
                'studentId' => $row['student_id'] ?? null,
                'yearSemester' => $row['year_sem'] ?? null
            ];
        }
        
        echo json_encode(['success' => true, 'data' => $members]);
    } catch (Exception $e) {
        error_log("Executive fetch error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to fetch executive members: ' . $e->getMessage()]);
    }
}

function handleCreateExecutive($db, $adminAuth) {
    $adminAuth->requirePermission('executive.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $position = trim($input['position'] ?? '');
    $memberType = trim($input['memberType'] ?? 'student');
    $session = $input['termYear'] ?? $input['session'] ?? null;
    $designation = $input['designation'] ?? null;
    $email = $input['email'] ?? null;
    $phone = $input['phone'] ?? null;
    $imageUrl = $input['image'] ?? null;
    $isActive = isset($input['isActive']) ? ($input['isActive'] ? 1 : 0) : 0;
    $studentId = $input['studentId'] ?? null;
    $yearSem = $input['yearSemester'] ?? null;
    
    if (empty($name) || empty($position)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Name and position are required']);
        return;
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO executive_members 
            (name, position, member_type, session, designation, email, phone, image_url, is_active, student_id, year_sem)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_params = [$name, $position, $memberType, $session, $designation, $email, $phone, $imageUrl, $isActive, $studentId, $yearSem];
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Executive member created successfully', 'member_id' => $stmt->lastInsertId()]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create executive member: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        error_log("Executive create error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleUpdateExecutive($db, $adminAuth) {
    $adminAuth->requirePermission('executive.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $memberId = intval($input['id'] ?? 0);
    
    if (!$memberId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = ['name' => 's', 'position' => 's', 'userId' => 'i', 'image' => 's', 
               'bio' => 's', 'email' => 's', 'phone' => 's', 'linkedin' => 's', 'github' => 's', 
               'termYear' => 's', 'designation' => 's', 'memberType' => 's', 'isActive' => 'i',
               'studentId' => 's', 'yearSemester' => 's'];
    $dbFields = ['userId' => 'user_id', 'image' => 'image_url', 'termYear' => 'session', 
                 'memberType' => 'member_type', 'isActive' => 'is_active', 'studentId' => 'student_id', 
                 'yearSemester' => 'year_sem'];
    
    foreach ($fields as $field => $type) {
        if (isset($input[$field])) {
            $dbField = $dbFields[$field] ?? $field;
            $updates[] = "$dbField = ?";
            $params[] = $input[$field];
            $types .= $type;
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $memberId;
    $types .= "i";
    
    $sql = "UPDATE executive_members SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Executive member updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update executive member']);
    }
}

function handleDeleteExecutive($db, $adminAuth) {
    $adminAuth->requirePermission('executive.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $memberId = intval($input['id'] ?? 0);
    
    if (!$memberId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Member ID is required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM executive_members WHERE id = ?");
    $stmt_params = [$memberId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Executive member deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete executive member']);
    }
}

function handleActivateSession($db, $adminAuth, $input) {
    $adminAuth->requirePermission('executive.manage');

    $session = $input['session'] ?? null;

    if (empty($session)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session is required']);
        return;
    }

    try {
        $db->begin_transaction();

        // 1. Deactivate all student members
        $deactivateStmt = $db->prepare("UPDATE executive_members SET is_active = 0 WHERE member_type = 'student'");
        if (!$deactivateStmt->execute()) {
            throw new Exception("Failed to deactivate members: " . $deactivateStmt->error);
        }

        // 2. Activate members of the selected session
        $activateStmt = $db->prepare("UPDATE executive_members SET is_active = 1 WHERE member_type = 'student' AND session = ?");
        $stmt_params = [$session];
        if (!$activateStmt->execute()) {
            throw new Exception("Failed to activate members: " . $activateStmt->error);
        }

        $db->commit();
        echo json_encode(['success' => true, 'message' => "Session $session activated successfully"]);

    } catch (Exception $e) {
        $db->rollback();
        error_log("Session activation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

function handleDeleteSession($db, $adminAuth, $input) {
    $adminAuth->requirePermission('executive.manage');

    $session = $input['session'] ?? null;

    if (empty($session)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Session is required']);
        return;
    }

    try {
        $stmt = $db->prepare("DELETE FROM executive_members WHERE member_type = 'student' AND session = ?");
        $stmt_params = [$session];
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "Session $session deleted successfully"]);
        } else {
            throw new Exception("Failed to delete session: " . $stmt->error);
        }

    } catch (Exception $e) {
        error_log("Session deletion error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
