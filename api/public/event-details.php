<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Enable error logging for debugging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

try {
    // Current path: backend/api/public/event-details.php
    // Database is at: backend/config/database.php
    // Relative path: ../../config/database.php
    require_once __DIR__ . '/../../config/database.php';
    
    $db = Database::getInstance()->getConnection();
    
    $eventId = $_GET['id'] ?? null;
    
    if (!$eventId) {
        throw new Exception('Event ID is required', 400);
    }
    
    // Fetch Main Event Data
    $stmt = $db->prepare("SELECT * FROM events WHERE id = ?");
    if (!$stmt) throw new Exception("Prepare failed: " . $db->error);
    $stmt_params = [$eventId];
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $eventResult = $stmt;
    
    if ($event = $eventResult->fetch(PDO::FETCH_ASSOC)) {
        // Fetch Extended Details (may not exist)
        $details = [];
        $stmtDetails = $db->prepare("SELECT * FROM event_details WHERE event_id = ?");
        if ($stmtDetails) {
            $stmt_params = [$eventId];
            $stmtDetails->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
            $detailsResult = $stmtDetails;
            $details = $detailsResult->fetch(PDO::FETCH_ASSOC) ?: [];
        }
    
        // Build response with event basic info always available
        $response = [
            'id' => (int)$event['id'],
            'title' => $event['title'] ?? '',
            'date' => $event['event_date'] ?? '',
            'endDate' => $event['end_date'] ?? null,
            'time' => $event['event_time'] ? substr($event['event_time'], 0, 5) : '',
            'endTime' => $event['end_time'] ? substr($event['end_time'], 0, 5) : null,
            'location' => $event['venue'] ?? '',
            'description' => $event['description'] ?? '',
            'image' => $event['image_url'] ?? '',
            'category' => $event['category'] ?? '',
            'status' => $event['status'] ?? 'upcoming',
            'registration_link' => $event['registration_link'] ?? '',
            'tags' => [$event['category'] ?? 'Event'],
            
            // Extended Details (with safe defaults)
            'aboutEvent' => $details['about_event'] ?? '',
            'chiefGuest' => null,
            'specialGuests' => [],
            'otherGuests' => [],
            'sponsors' => [],
            'mediaPartners' => [],
            'winners' => [],
            'gallery' => [],
            'schedule' => [],
            'competitionSegments' => []
        ];
        
        // Parse JSON fields only if details exist
        if (!empty($details)) {
            if (!empty($details['chief_guest'])) {
                $response['chiefGuest'] = json_decode($details['chief_guest'], true);
            }
            if (!empty($details['special_guests'])) {
                $decoded = json_decode($details['special_guests'], true);
                $response['specialGuests'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['other_guests'])) {
                $decoded = json_decode($details['other_guests'], true);
                $response['otherGuests'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['sponsors'])) {
                $decoded = json_decode($details['sponsors'], true);
                $response['sponsors'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['media_partners'])) {
                $decoded = json_decode($details['media_partners'], true);
                $response['mediaPartners'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['winners'])) {
                $decoded = json_decode($details['winners'], true);
                $response['winners'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['gallery'])) {
                $decoded = json_decode($details['gallery'], true);
                $response['gallery'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['schedule'])) {
                $decoded = json_decode($details['schedule'], true);
                $response['schedule'] = is_array($decoded) ? $decoded : [];
            }
            if (!empty($details['competition_segments'])) {
                $decoded = json_decode($details['competition_segments'], true);
                if (is_array($decoded) && count($decoded) > 0) {
                    $response['tags'] = $decoded;
                    $response['competitionSegments'] = $decoded;
                }
            }
        }
    
        echo json_encode(['success' => true, 'data' => $response]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
    }
} catch (Exception $e) {
    error_log("API Error: " . $e->getMessage());
    $code = $e->getCode() ?: 500;
    http_response_code($code > 599 ? 500 : $code);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?>
