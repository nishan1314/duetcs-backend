<?php
/**
 * News Content Management API
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
            handleGetNews($db, $adminAuth);
            break;
        case 'POST':
            handleCreateNews($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateNews($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteNews($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("News management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function handleGetNews($db, $adminAuth) {
    $adminAuth->requirePermission('news.view');
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($search !== '') {
        $where[] = "(title LIKE ? OR description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "ss";
    }
    
    if ($status !== 'all') {
        $where[] = "status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($category !== 'all') {
        $where[] = "category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
    $sql = "SELECT n.*, u.full_name as author_name FROM news n 
            INNER JOIN users u ON n.author_id = u.id 
            WHERE $whereClause ORDER BY created_at DESC";
    
    if (!empty($params)) {
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;
    } else {
        $result = $db->query($sql);
    }
    
    $news = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $news[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'description' => $row['description'],
            'content' => $row['content'],
            'image' => $row['image_url'],
            'category' => $row['category'],
            'author' => $row['author_name'],
            'status' => $row['status'],
            'publishedAt' => $row['published_at'],
            'viewCount' => $row['view_count'],
            'createdAt' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'data' => $news]);
}

function handleCreateNews($db, $adminAuth) {
    $adminAuth->requirePermission('news.create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $title = trim($input['title'] ?? '');
    $slug = trim($input['slug'] ?? strtolower(str_replace(' ', '-', $title)));
    $description = trim($input['description'] ?? '');
    $content = trim($input['content'] ?? '');
    $imageUrl = $input['image'] ?? null;
    $category = $input['category'] ?? null;
    $status = $input['status'] ?? 'draft';
    $publishedAt = $status === 'published' ? date('Y-m-d H:i:s') : null;
    $authorId = $_SESSION['user_id'];
    
    $stmt = $db->prepare("
        INSERT INTO news (title, slug, description, content, image_url, category, status, published_at, author_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_params = [$title, $slug, $description, $content, $imageUrl, $category, $status, $publishedAt, $authorId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'News created successfully', 'news_id' => $stmt->lastInsertId()]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create news']);
    }
}

function handleUpdateNews($db, $adminAuth) {
    $adminAuth->requirePermission('news.edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $newsId = intval($input['id'] ?? 0);
    
    if (!$newsId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'News ID is required']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = ['title' => 's', 'slug' => 's', 'description' => 's', 'content' => 's', 
               'image' => 's', 'category' => 's', 'status' => 's'];
    
    foreach ($fields as $field => $type) {
        if (isset($input[$field])) {
            $dbField = $field === 'image' ? 'image_url' : $field;
            $updates[] = "$dbField = ?";
            $params[] = $input[$field];
            $types .= $type;
        }
    }
    
    if (isset($input['status']) && $input['status'] === 'published') {
        $updates[] = "published_at = NOW()";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $newsId;
    $types .= "i";
    
    $sql = "UPDATE news SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'News updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update news']);
    }
}

function handleDeleteNews($db, $adminAuth) {
    $adminAuth->requirePermission('news.delete');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $newsId = intval($input['id'] ?? 0);
    
    if (!$newsId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'News ID is required']);
        return;
    }
    
    $stmt = $db->prepare("DELETE FROM news WHERE id = ?");
    $stmt_params = [$newsId];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'News deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete news']);
    }
}
