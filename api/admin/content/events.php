<?php
/**
 * Events Content Management API
 * CRUD operations for events and event details
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../utils/admin-auth.php';

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
            handleGetEvents($db, $adminAuth);
            break;
        case 'POST':
            handleCreateEvent($db, $adminAuth);
            break;
        case 'PUT':
            handleUpdateEvent($db, $adminAuth);
            break;
        case 'DELETE':
            handleDeleteEvent($db, $adminAuth);
            break;
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Method not allowed'
            ]);
    }
} catch (Exception $e) {
    error_log("Events management error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred'
    ]);
}

/**
 * Get events list with filters
 */
function handleGetEvents($db, $adminAuth) {
    $adminAuth->requirePermission('events.view');
    
    $search = $_GET['search'] ?? '';
    $status = $_GET['status'] ?? 'all';
    $category = $_GET['category'] ?? 'all';
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 20;
    $offset = ($page - 1) * $limit;
    
    $where = ["1=1"];
    $params = [];
    $types = "";
    
    if ($search !== '') {
        $where[] = "(e.title LIKE ? OR e.description LIKE ? OR e.venue LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    if ($status !== 'all') {
        $where[] = "e.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($category !== 'all') {
        $where[] = "e.category = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    $whereClause = implode(" AND ", $where);
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM events e WHERE $whereClause";
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
    
    // Get events
    $sql = "
        SELECT 
            e.id,
            e.title,
            e.description,
            e.event_date,
            e.end_date,
            e.event_time,
            e.end_time,
            e.venue,
            e.category,
            e.status,
            e.image_url,
            e.registration_link,
            e.max_participants,
            e.registration_deadline,
            e.contact_email,
            e.contact_phone,
            e.is_featured,
            e.is_published,
            e.created_at
        FROM events e
        WHERE $whereClause
        ORDER BY e.event_date DESC, e.created_at DESC
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
    
    $events = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'],
            'date' => $row['event_date'],
            'endDate' => $row['end_date'],
            'time' => $row['event_time'] ? substr($row['event_time'], 0, 5) : null,
            'endTime' => $row['end_time'] ? substr($row['end_time'], 0, 5) : null,
            'venue' => $row['venue'],
            'category' => $row['category'],
            'status' => $row['status'],
            'image' => $row['image_url'],
            'registrationLink' => $row['registration_link'],
            'maxParticipants' => $row['max_participants'],
            'registrationDeadline' => $row['registration_deadline'],
            'contactEmail' => $row['contact_email'],
            'contactPhone' => $row['contact_phone'],
            'isFeatured' => (bool) $row['is_featured'],
            'isPublished' => (bool) $row['is_published'],
            'createdAt' => $row['created_at']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'data' => $events,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Create new event
 */
function handleCreateEvent($db, $adminAuth) {
    $adminAuth->requirePermission('events.create');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $required = ['title', 'description', 'event_date', 'event_time', 'venue', 'category'];
    foreach ($required as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => "Field '$field' is required"
            ]);
            return;
        }
    }
    
    $title = trim($input['title']);
    $description = trim($input['description']);
    $eventDate = $input['event_date'];
    $endDate = !empty($input['end_date']) ? $input['end_date'] : null;
    $eventTime = $input['event_time'];
    $endTime = !empty($input['end_time']) ? $input['end_time'] : null;
    $venue = trim($input['venue']);
    $category = trim($input['category']);
    $status = $input['status'] ?? 'upcoming';
    $imageUrl = $input['image'] ?? null;
    $registrationLink = $input['registrationLink'] ?? null;
    $maxParticipants = !empty($input['max_participants']) ? intval($input['max_participants']) : null;
    $registrationDeadline = !empty($input['registration_deadline']) ? $input['registration_deadline'] : null;
    $contactEmail = !empty($input['contact_email']) ? trim($input['contact_email']) : null;
    $contactPhone = !empty($input['contact_phone']) ? trim($input['contact_phone']) : null;
    $isFeatured = isset($input['is_featured']) ? (int) $input['is_featured'] : 0;
    $isPublished = isset($input['is_published']) ? (int) $input['is_published'] : 1;
    
    $stmt = $db->prepare("
        INSERT INTO events 
        (title, description, event_date, end_date, event_time, end_time, venue, category, status, 
         image_url, registration_link, max_participants, registration_deadline, 
         contact_email, contact_phone, is_featured, is_published)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "sssssssssssisssii", 
        $title, $description, $eventDate, $endDate, $eventTime, $endTime, $venue, $category, $status, 
        $imageUrl, $registrationLink, $maxParticipants, $registrationDeadline, 
        $contactEmail, $contactPhone, $isFeatured, $isPublished
    );

    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Event created successfully',
            'event_id' => $stmt->lastInsertId()
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create event: ' . $stmt->error
        ]);
    }
}

/**
 * Update event
 */
function handleUpdateEvent($db, $adminAuth) {
    $adminAuth->requirePermission('events.edit');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Event ID is required'
        ]);
        return;
    }
    
    $eventId = intval($input['id']);
    $updates = [];
    $params = [];
    $types = "";
    
    // Field mappings: input key => [db column, type]
    $fieldMap = [
        'title' => ['title', 's'],
        'description' => ['description', 's'],
        'event_date' => ['event_date', 's'],
        'end_date' => ['end_date', 's'],
        'event_time' => ['event_time', 's'],
        'end_time' => ['end_time', 's'],
        'venue' => ['venue', 's'],
        'category' => ['category', 's'],
        'status' => ['status', 's'],
        'image' => ['image_url', 's'],
        'registrationLink' => ['registration_link', 's'],
        'max_participants' => ['max_participants', 'i'],
        'registration_deadline' => ['registration_deadline', 's'],
        'contact_email' => ['contact_email', 's'],
        'contact_phone' => ['contact_phone', 's'],
        'is_featured' => ['is_featured', 'i'],
        'is_published' => ['is_published', 'i']
    ];
    
    foreach ($fieldMap as $inputKey => $config) {
        if (array_key_exists($inputKey, $input)) {
            $dbField = $config[0];
            $type = $config[1];
            
            // Handle empty values for nullable fields
            $value = $input[$inputKey];
            if ($value === '' || $value === null) {
                // For nullable fields, set to null
                if (in_array($inputKey, ['end_time', 'max_participants', 'registration_deadline', 'contact_email', 'contact_phone', 'image', 'registrationLink'])) {
                    $updates[] = "$dbField = NULL";
                    continue;
                }
            }
            
            $updates[] = "$dbField = ?";
            $params[] = $type === 'i' ? intval($value) : $value;
            $types .= $type;
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'No fields to update'
        ]);
        return;
    }
    
    $params[] = $eventId;
    $types .= "i";
    
    $sql = "UPDATE events SET " . implode(", ", $updates) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Event updated successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update event: ' . $stmt->error
        ]);
    }
}

/**
 * Delete event
 */
function handleDeleteEvent($db, $adminAuth) {
    $adminAuth->requirePermission('events.delete');
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Accept id from query string (DELETE ?id=X) or from JSON body
    $eventId = null;
    if (isset($input['id'])) {
        $eventId = intval($input['id']);
    } elseif (isset($_GET['id'])) {
        $eventId = intval($_GET['id']);
    }

    if (!$eventId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Event ID is required'
        ]);
        return;
    }

    
    $stmt = $db->prepare("DELETE FROM events WHERE id = ?");
    $stmt_params = [$eventId];
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to delete event'
        ]);
    }
}
