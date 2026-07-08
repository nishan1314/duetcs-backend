<?php
/**
 * Payment Management API
 * Manage payment records, verification, and reporting
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
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'verify':
            handleVerifyPayment($db, $adminAuth);
            break;
        case 'stats':
            handlePaymentStats($db, $adminAuth);
            break;
        default:
            if ($method === 'GET') {
                handleGetPayments($db, $adminAuth);
            } else if ($method === 'POST') {
                handleCreatePayment($db, $adminAuth);
            } else if ($method === 'PUT') {
                handleUpdatePayment($db, $adminAuth);
            } else if ($method === 'DELETE') {
                handleDeletePayment($db, $adminAuth);
            }
    }
} catch (Exception $e) {
    error_log("Payment management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * Get payments list with filters
 */
function handleGetPayments($db, $adminAuth) {
    // TODO: Re-enable permission check in production
    // $adminAuth->requirePermission('payments.view');
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $paymentType = $_GET['type'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($search !== '') {
        $where[] = "(u.full_name LIKE ? OR u.email LIKE ? OR p.transaction_id LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    if ($status !== 'all') {
        $where[] = "p.payment_status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($paymentType !== 'all') {
        $where[] = "p.payment_type = ?";
        $params[] = $paymentType;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM payment_records p INNER JOIN users u ON p.user_id = u.id WHERE $whereClause";
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
    
    // Get payments
    $sql = "
        SELECT 
            p.id,
            p.transaction_id,
            p.amount,
            p.payment_method,
            p.payment_type,
            p.payment_status,
            p.payment_date,
            p.payment_details,
            p.verified_at,
            p.created_at,
            u.id as user_id,
            u.full_name as user_name,
            u.email as user_email,
            u.student_id,
            v.full_name as verified_by_name
        FROM payment_records p
        INNER JOIN users u ON p.user_id = u.id
        LEFT JOIN users v ON p.verified_by = v.id
        WHERE $whereClause
        ORDER BY p.created_at DESC
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
    
    $payments = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $payments[] = [
            'id' => $row['id'],
            'transactionId' => $row['transaction_id'],
            'amount' => floatval($row['amount']),
            'paymentMethod' => $row['payment_method'],
            'paymentType' => $row['payment_type'],
            'status' => $row['payment_status'],
            'paymentDate' => $row['payment_date'],
            'details' => $row['payment_details'],
            'verifiedAt' => $row['verified_at'],
            'verifiedBy' => $row['verified_by_name'],
            'createdAt' => $row['created_at'],
            'user' => [
                'id' => $row['user_id'],
                'name' => $row['user_name'],
                'email' => $row['user_email'],
                'studentId' => $row['student_id']
            ]
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $payments,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Create payment record
 */
function handleCreatePayment($db, $adminAuth) {
    $adminAuth->requirePermission('payments.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['user_id', 'amount', 'payment_method', 'payment_type'];
    foreach ($required as $field) {
        if (!isset($input[$field])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Field '$field' is required"
            ]);
            return;
        }
    }
    
    $userId = intval($input['user_id']);
    $amount = floatval($input['amount']);
    $paymentMethod = trim($input['payment_method']);
    $paymentType = trim($input['payment_type']);
    $transactionId = $input['transaction_id'] ?? null;
    $paymentStatus = $input['payment_status'] ?? 'pending';
    $paymentDate = $input['payment_date'] ?? date('Y-m-d H:i:s');
    $paymentDetails = $input['payment_details'] ?? null;
    
    $stmt = $db->prepare("
        INSERT INTO payment_records 
        (user_id, transaction_id, amount, payment_method, payment_type, payment_status, payment_date, payment_details)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt_params = [$userId, $transactionId, $amount, $paymentMethod, $paymentType, $paymentStatus, $paymentDate, $paymentDetails];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment record created successfully',
            'payment_id' => $stmt->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create payment record'
        ]);
    }
}

/**
 * Update payment record
 */
function handleUpdatePayment($db, $adminAuth) {
    $adminAuth->requirePermission('payments.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment ID is required'
        ]);
        return;
    }
    
    $paymentId = intval($input['id']);
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($input['transaction_id'])) {
        $updates[] = "transaction_id = ?";
        $params[] = trim($input['transaction_id']);
        $types .= "s";
    }
    
    if (isset($input['amount'])) {
        $updates[] = "amount = ?";
        $params[] = floatval($input['amount']);
        $types .= "d";
    }
    
    if (isset($input['payment_method'])) {
        $updates[] = "payment_method = ?";
        $params[] = trim($input['payment_method']);
        $types .= "s";
    }
    
    if (isset($input['payment_status'])) {
        $updates[] = "payment_status = ?";
        $params[] = trim($input['payment_status']);
        $types .= "s";
    }
    
    if (isset($input['payment_details'])) {
        $updates[] = "payment_details = ?";
        $params[] = trim($input['payment_details']);
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
    
    $params[] = $paymentId;
    $types .= "i";
    
    $sql = "UPDATE payment_records SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update payment'
        ]);
    }
}

/**
 * Delete payment record
 */
function handleDeletePayment($db, $adminAuth) {
    $adminAuth->requirePermission('payments.manage');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment ID is required'
        ]);
        return;
    }
    
    $paymentId = intval($input['id']);
    
    $stmt = $db->prepare("DELETE FROM payment_records WHERE id = ?");
    $stmt_params = [$paymentId];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete payment'
        ]);
    }
}

/**
 * Verify payment
 */
function handleVerifyPayment($db, $adminAuth) {
    $adminAuth->requirePermission('payments.verify');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Payment ID is required'
        ]);
        return;
    }
    
    $paymentId = intval($input['id']);
    $verifiedBy = $_SESSION['user_id'];
    $verifiedAt = date('Y-m-d H:i:s');
    
    $stmt = $db->prepare("
        UPDATE payment_records 
        SET payment_status = 'completed', verified_by = ?, verified_at = ?
        WHERE id = ?
    ");
    $stmt_params = [$verifiedBy, $verifiedAt, $paymentId];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Payment verified successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to verify payment'
        ]);
    }
}

/**
 * Get payment statistics
 */
function handlePaymentStats($db, $adminAuth) {
    $adminAuth->requirePermission('payments.view');
    
    // Total payments
    $totalStmt = $db->query("SELECT COUNT(*) as count, SUM(amount) as sum FROM payment_records");
    $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
    
    // Pending payments
    $pendingStmt = $db->query("SELECT COUNT(*) as count, SUM(amount) as sum FROM payment_records WHERE payment_status = 'pending'");
    $pendingRow = $pendingStmt->fetch(PDO::FETCH_ASSOC);
    
    // Completed payments
    $completedStmt = $db->query("SELECT COUNT(*) as count, SUM(amount) as sum FROM payment_records WHERE payment_status = 'completed'");
    $completedRow = $completedStmt->fetch(PDO::FETCH_ASSOC);
    
    // By payment type
    $typeStmt = $db->query("
        SELECT payment_type, COUNT(*) as count, SUM(amount) as sum
        FROM payment_records
        GROUP BY payment_type
    ");
    $byType = [];
    while ($row = $typeStmt->fetch(PDO::FETCH_ASSOC)) {
        $byType[$row['payment_type']] = [
            'count' => $row['count'],
            'sum' => floatval($row['sum'])
        ];
    }
    
    // Recent payments
    $recentStmt = $db->query("
        SELECT payment_date, COUNT(*) as count, SUM(amount) as sum
        FROM payment_records
        WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY payment_date DESC
    ");
    $recent = [];
    while ($row = $recentStmt->fetch(PDO::FETCH_ASSOC)) {
        $recent[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => [
            'total' => [
                'count' => $totalRow['count'],
                'amount' => floatval($totalRow['sum'])
            ],
            'pending' => [
                'count' => $pendingRow['count'],
                'amount' => floatval($pendingRow['sum'])
            ],
            'completed' => [
                'count' => $completedRow['count'],
                'amount' => floatval($completedRow['sum'])
            ],
            'byType' => $byType,
            'recent' => $recent
        ]
    ]);
}
