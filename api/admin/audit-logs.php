<?php
/**
 * Audit Logs API
 * SUPER_ADMIN only
 */

require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Auth.php';
require_once __DIR__ . '/../../utils/RoleGuard.php';
require_once __DIR__ . '/../../utils/AuditLog.php';

header('Content-Type: application/json');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Require audit.view permission (SUPER_ADMIN only)
RoleGuard::requirePermission('audit.view');

// Only GET allowed
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;
    
    $filters = [];
    
    if (!empty($_GET['actor_id'])) {
        $filters['actor_id'] = (int)$_GET['actor_id'];
    }
    
    if (!empty($_GET['action'])) {
        $filters['action'] = $_GET['action'];
    }
    
    if (!empty($_GET['entity_type'])) {
        $filters['entity_type'] = $_GET['entity_type'];
    }
    
    if (!empty($_GET['from_date'])) {
        $filters['from_date'] = $_GET['from_date'];
    }
    
    if (!empty($_GET['to_date'])) {
        $filters['to_date'] = $_GET['to_date'];
    }
    
    $logs = AuditLog::getLogs($filters, $limit, $offset);
    
    // Get total count
    $db = Database::getInstance()->getConnection();
    $where = [];
    $params = [];
    $types = "";
    
    if (!empty($filters['actor_id'])) {
        $where[] = "actor_id = ?";
        $params[] = $filters['actor_id'];
        $types .= "i";
    }
    if (!empty($filters['action'])) {
        $where[] = "action = ?";
        $params[] = $filters['action'];
        $types .= "s";
    }
    if (!empty($filters['entity_type'])) {
        $where[] = "entity_type = ?";
        $params[] = $filters['entity_type'];
        $types .= "s";
    }
    if (!empty($filters['from_date'])) {
        $where[] = "created_at >= ?";
        $params[] = $filters['from_date'];
        $types .= "s";
    }
    if (!empty($filters['to_date'])) {
        $where[] = "created_at <= ?";
        $params[] = $filters['to_date'];
        $types .= "s";
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    $countSql = "SELECT COUNT(*) as total FROM audit_logs $whereClause";
    
    if (!empty($params)) {
        $stmt = $db->prepare($countSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute($stmt_params ?? null);
        $total = $stmt->get_result()->fetch_assoc()['total'];
    } else {
        $total = $db->query($countSql)->fetch_assoc()['total'];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $logs,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => (int)$total,
            'pages' => ceil($total / $limit)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Audit Logs API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred'
    ]);
}
