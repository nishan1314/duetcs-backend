<?php
/**
 * Notice Management API
 * Manage notices with PDF upload support
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
    switch ($method) {
        case 'GET':
            handleGetNotices($db, $adminAuth);
            break;
        case 'POST':
            handleCreateNotice($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateNotice($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteNotice($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Notice management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * Get notices list with filters
 */
function handleGetNotices($db, $adminAuth) {
    $adminAuth->requirePermission('notices.view');
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $priority = $_GET['priority'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($search !== '') {
        $where[] = "(n.title LIKE ? OR n.description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }
    
    if ($status !== 'all') {
        $where[] = "n.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($priority !== 'all') {
        $where[] = "n.priority = ?";
        $params[] = $priority;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM notices n WHERE $whereClause";
    if (!empty($params)) {
        $countStmt = $db->prepare($countSql);
        $countStmt->bind_param($types, ...$params);
        $countStmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $totalResult = $countStmt;
    } else {
        $totalResult = $db->query($countSql);
    }
    $totalRow = $totalResult->fetch(PDO::FETCH_ASSOC);
    $total = $totalRow['total'];
    
    // Get notices
    $sql = "
        SELECT 
            n.id,
            n.title,
            n.description,
            n.notice_type,
            n.pdf_url,
            n.pdf_name,
            n.priority,
            n.status,
            n.created_at,
            n.updated_at,
            u.full_name as created_by_name
        FROM notices n
        LEFT JOIN users u ON n.created_by = u.id
        WHERE $whereClause
        ORDER BY 
            CASE n.priority
                WHEN 'high' THEN 1
                WHEN 'medium' THEN 2
                WHEN 'low' THEN 3
            END,
            n.created_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $db->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    $notices = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $notices[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'type' => $row['notice_type'],
            'pdfUrl' => $row['pdf_url'],
            'pdfName' => $row['pdf_name'],
            'priority' => $row['priority'],
            'status' => $row['status'],
            'createdBy' => $row['created_by_name'],
            'createdAt' => date('Y-m-d', strtotime($row['created_at'])),
            'updatedAt' => $row['updated_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $notices,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Create new notice
 */
function handleCreateNotice($db, $adminAuth) {
    $adminAuth->requirePermission('notices.create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['title']) || !isset($input['description'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Title and description are required'
        ]);
        return;
    }
    
    $title = trim($input['title']);
    $description = trim($input['description']);
    $noticeType = $input['type'] ?? 'message';
    $pdfUrl = $input['pdfUrl'] ?? null;
    $pdfName = $input['pdfName'] ?? null;
    $priority = $input['priority'] ?? 'medium';
    $status = $input['status'] ?? 'active';
    $createdBy = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
    
    // For system admins, set created_by to null since admin_id references system_admin table, not users
    $isSystemAdmin = isset($_SESSION['admin_id']) && isset($_SESSION['admin_role']);
    if ($isSystemAdmin) {
        $createdBy = null; // System admins don't have a user record
    }
    
    // Handle NULL created_by properly
    if ($createdBy === null) {
        $stmt = $db->prepare("
            INSERT INTO notices 
            (title, description, notice_type, pdf_url, pdf_name, priority, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL)
        ");
        $stmt_params = [$title, $description, $noticeType, $pdfUrl, $pdfName, $priority, $status];
    } else {
        $stmt = $db->prepare("
            INSERT INTO notices 
            (title, description, notice_type, pdf_url, pdf_name, priority, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_params = [$title, $description, $noticeType, $pdfUrl, $pdfName, $priority, $status, $createdBy];
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notice created successfully',
            'notice_id' => $stmt->lastInsertId()
        ]);
    } else {
        error_log("Notice creation failed: " . $stmt->error);
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create notice: ' . $stmt->error
        ]);
    }
}

/**
 * Update notice
 */
function handleUpdateNotice($db, $adminAuth) {
    $adminAuth->requirePermission('notices.edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Notice ID is required'
        ]);
        return;
    }
    
    $noticeId = intval($input['id']);
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($input['title'])) {
        $updates[] = "title = ?";
        $params[] = trim($input['title']);
        $types .= "s";
    }
    
    if (isset($input['description'])) {
        $updates[] = "description = ?";
        $params[] = trim($input['description']);
        $types .= "s";
    }
    
    if (isset($input['type'])) {
        $updates[] = "notice_type = ?";
        $params[] = $input['type'];
        $types .= "s";
    }
    
    if (isset($input['pdfUrl'])) {
        $updates[] = "pdf_url = ?";
        $params[] = $input['pdfUrl'];
        $types .= "s";
    }
    
    if (isset($input['pdfName'])) {
        $updates[] = "pdf_name = ?";
        $params[] = $input['pdfName'];
        $types .= "s";
    }
    
    if (isset($input['priority'])) {
        $updates[] = "priority = ?";
        $params[] = $input['priority'];
        $types .= "s";
    }
    
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = $input['status'];
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    $params[] = $noticeId;
    $types .= "i";
    
    $sql = "UPDATE notices SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notice updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update notice'
        ]);
    }
}

/**
 * Delete notice
 */
function handleDeleteNotice($db, $adminAuth) {
    $adminAuth->requirePermission('notices.delete');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Notice ID is required'
        ]);
        return;
    }
    
    $noticeId = intval($input['id']);
    
    // Get PDF path to delete file
    $stmt = $db->prepare("SELECT pdf_url FROM notices WHERE id = ?");
    $stmt_params = [$noticeId];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    if ($result->rowCount() > 0) {
        $notice = $result->fetch(PDO::FETCH_ASSOC);
        
        // Delete the notice
        $deleteStmt = $db->prepare("DELETE FROM notices WHERE id = ?");
        $stmt_params = [$noticeId];
        
        if ($deleteStmt->execute()) {
            // Delete PDF file if exists
            if ($notice['pdf_url']) {
                $pdfPath = __DIR__ . '/../../' . $notice['pdf_url'];
                if (file_exists($pdfPath)) {
                    unlink($pdfPath);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Notice deleted successfully'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete notice'
            ]);
        }
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Notice not found'
        ]);
    }
}
