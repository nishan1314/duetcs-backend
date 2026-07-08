<?php
/**
 * Codeforces Handles Management API
 * For managing codeforces handles shown on achievements page
 * SUPER_ADMIN and ADMIN with content permission
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/RoleGuard.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require admin authentication
RoleGuard::requireAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'POST':
            handlePost($db);
            break;
        case 'PUT':
        case 'PATCH':
            handlePut($db);
            break;
        case 'DELETE':
            handleDelete($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Codeforces Handles API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

/**
 * GET - List all handles or single handle
 */
function handleGet($db) {
    $id = $_GET['id'] ?? null;
    
    if ($id) {
        // Get single handle
        $stmt = $db->prepare("SELECT id, handle, name, created_at, updated_at FROM codeforces_handles WHERE id = ?");
        $stmt_params = [$id];
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Handle not found']);
            return;
        }
        
        $handle = $result->fetch_assoc();
        $handle['id'] = (int)$handle['id'];
        
        echo json_encode(['success' => true, 'data' => $handle]);
    } else {
        // List all handles
        $search = $_GET['search'] ?? '';
        
        $where = "1=1";
        $params = [];
        $types = "";
        
        if ($search) {
            $searchTerm = "%$search%";
            $where .= " AND (handle LIKE ? OR name LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $types .= "ss";
        }
        
        $sql = "SELECT id, handle, name, created_at, updated_at FROM codeforces_handles WHERE $where ORDER BY created_at DESC";
        
        if (!empty($params)) {
            $stmt = $db->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute($stmt_params ?? null);
            $result = $stmt->get_result();
        } else {
            $result = $db->query($sql);
        }
        
        $handles = [];
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $handles[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $handles,
            'count' => count($handles)
        ]);
    }
}

/**
 * POST - Add new handle
 */
function handlePost($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['handle'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Handle is required']);
        return;
    }
    
    $handle = trim($data['handle']);
    $name = isset($data['name']) ? trim($data['name']) : null;
    
    // Check if handle already exists
    $stmt = $db->prepare("SELECT id FROM codeforces_handles WHERE handle = ?");
    $stmt_params = [$handle];
    $stmt->execute($stmt_params ?? null);
    if ($stmt->get_result()->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Handle already exists']);
        return;
    }
    
    // Validate handle exists on Codeforces (optional but recommended)
    $cfValid = validateCodeforcesHandle($handle);
    if (!$cfValid['valid']) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $cfValid['message']]);
        return;
    }
    
    // Use CF name if no name provided
    if (empty($name) && !empty($cfValid['name'])) {
        $name = $cfValid['name'];
    }
    
    // Insert handle
    $stmt = $db->prepare("INSERT INTO codeforces_handles (handle, name) VALUES (?, ?)");
    $stmt_params = [$handle, $name];
    
    if ($stmt->execute()) {
        $newId = $db->lastInsertId();
        echo json_encode([
            'success' => true,
            'message' => 'Handle added successfully',
            'data' => [
                'id' => $newId,
                'handle' => $handle,
                'name' => $name
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add handle']);
    }
}

/**
 * PUT/PATCH - Update handle
 */
function handlePut($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = $data['id'] ?? $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    // Check if handle exists
    $stmt = $db->prepare("SELECT * FROM codeforces_handles WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $current = $stmt->get_result()->fetch_assoc();
    
    if (!$current) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Handle not found']);
        return;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($data['handle']) && $data['handle'] !== $current['handle']) {
        $newHandle = trim($data['handle']);
        
        // Check uniqueness
        $stmt = $db->prepare("SELECT id FROM codeforces_handles WHERE handle = ? AND id != ?");
        $stmt_params = [$newHandle, $id];
        $stmt->execute($stmt_params ?? null);
        if ($stmt->get_result()->num_rows > 0) {
            http_response_code(409);
            echo json_encode(['success' => false, 'message' => 'Handle already exists']);
            return;
        }
        
        // Validate on Codeforces
        $cfValid = validateCodeforcesHandle($newHandle);
        if (!$cfValid['valid']) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => $cfValid['message']]);
            return;
        }
        
        $updates[] = "handle = ?";
        $params[] = $newHandle;
        $types .= "s";
    }
    
    if (isset($data['name'])) {
        $updates[] = "name = ?";
        $params[] = trim($data['name']) ?: null;
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        return;
    }
    
    $params[] = $id;
    $types .= "i";
    
    $sql = "UPDATE codeforces_handles SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Handle updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update handle']);
    }
}

/**
 * DELETE - Remove handle
 */
function handleDelete($db) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    // Check if exists
    $stmt = $db->prepare("SELECT handle FROM codeforces_handles WHERE id = ?");
    $stmt_params = [$id];
    $stmt->execute($stmt_params ?? null);
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Handle not found']);
        return;
    }
    
    $handle = $result->fetch_assoc()['handle'];
    
    // Delete
    $stmt = $db->prepare("DELETE FROM codeforces_handles WHERE id = ?");
    $stmt_params = [$id];
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Handle '$handle' deleted successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete handle']);
    }
}

/**
 * Validate handle exists on Codeforces
 */
function validateCodeforcesHandle($handle) {
    $url = 'https://codeforces.com/api/user.info?handles=' . urlencode($handle);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return ['valid' => false, 'message' => 'Could not verify handle on Codeforces'];
    }
    
    $data = json_decode($response, true);
    
    if ($data['status'] !== 'OK' || empty($data['result'])) {
        return ['valid' => false, 'message' => 'Handle not found on Codeforces'];
    }
    
    $user = $data['result'][0];
    return [
        'valid' => true,
        'name' => $user['firstName'] ?? $user['handle'],
        'rating' => $user['rating'] ?? 0,
        'rank' => $user['rank'] ?? 'unrated'
    ];
}
