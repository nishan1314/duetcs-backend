<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../../config/cors.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../utils/admin-auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$adminAuth = new AdminAuth();
$adminAuth->requirePermission('events.edit');

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    $db = Database::getInstance()->getConnection();
    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $eventId = $_GET['event_id'] ?? null;
        if (!$eventId) {
            throw new Exception('Event ID is required', 400);
        }

        $stmt = $db->prepare("SELECT * FROM event_details WHERE event_id = ?");
        $stmt_params = [$eventId];
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;

        if ($row = $result->fetch(PDO::FETCH_ASSOC)) {
            // Decode JSON fields
            $jsonFields = ['chief_guest', 'special_guests', 'other_guests', 'sponsors', 'media_partners', 'winners', 'gallery', 'schedule', 'competition_segments'];
            foreach ($jsonFields as $field) {
                $row[$field] = isset($row[$field]) ? json_decode($row[$field], true) : null;
            }
            
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => true, 'data' => null]);
        }

    } elseif ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        if (!$rawInput) {
             throw new Exception('No input data received', 400);
        }
        $input = json_decode($rawInput, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON input: ' . json_last_error_msg(), 400);
        }
        
        $eventId = $input['event_id'] ?? null;
        if (!$eventId) {
            throw new Exception('Event ID is required', 400);
        }

        // Prepare data

        $aboutEvent = $input['about_event'] ?? '';
        $chiefGuest = json_encode($input['chief_guest'] ?? null);
        $specialGuests = json_encode($input['special_guests'] ?? []);
        $otherGuests = json_encode($input['other_guests'] ?? []);
        $sponsors = json_encode($input['sponsors'] ?? []);
        $mediaPartners = json_encode($input['media_partners'] ?? []);
        $winners = json_encode($input['winners'] ?? []);
        $gallery = json_encode($input['gallery'] ?? []);
        $schedule = json_encode($input['schedule'] ?? []);
        $competitionSegments = json_encode($input['competition_segments'] ?? []);

        // Check if exists
        $stmt = $db->prepare("SELECT id FROM event_details WHERE event_id = ?");
        $stmt_params = [$eventId];
        $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
        $result = $stmt;

        if ($result->rowCount() > 0) {
            // Update
            $sql = "UPDATE event_details SET 
                    about_event = ?, 
                    chief_guest = ?, 
                    special_guests = ?, 
                    other_guests = ?, 
                    sponsors = ?, 
                    media_partners = ?, 
                    winners = ?, 
                    gallery = ?,
                    schedule = ?,
                    competition_segments = ?
                    WHERE event_id = ?";
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
            $stmt->bind_param("ssssssssssi", 
                $aboutEvent, $chiefGuest, $specialGuests, 
                $otherGuests, $sponsors, $mediaPartners, $winners, $gallery, $schedule, $competitionSegments, $eventId);
        } else {
            // Insert
            $sql = "INSERT INTO event_details 
                    (event_id, about_event, chief_guest, special_guests, other_guests, sponsors, media_partners, winners, gallery, schedule, competition_segments) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
            $stmt->bind_param("issssssssss", 
                $eventId, $aboutEvent, $chiefGuest, $specialGuests, 
                $otherGuests, $sponsors, $mediaPartners, $winners, $gallery, $schedule, $competitionSegments);
        }

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event details saved successfully']);
        } else {
            throw new Exception('Database error: ' . $stmt->error, 500);
        }

    } else {
        throw new Exception('Method not allowed', 405);
    }

} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code > 599 ? 500 : $code); // Ensure valid HTTP code
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
    error_log($e->getMessage());
}
?>
