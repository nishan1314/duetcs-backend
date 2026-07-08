<?php
/**
 * Site Settings API
 * GET - Retrieve all settings
 * PUT - Update settings (requires SUPER_ADMIN)
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

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance()->getConnection();

try {
    switch ($method) {
        case 'GET':
            handleGet($db);
            break;
        case 'PUT':
        case 'POST':
            // Require SUPER_ADMIN for updating settings
            RoleGuard::requireSuperAdmin();
            handleUpdate($db);
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'SERVER_ERROR',
        'message' => 'An error occurred: ' . $e->getMessage()
    ]);
}

/**
 * GET - Retrieve all settings
 */
function handleGet($db) {
    // Check if admin is authenticated for full settings, otherwise return public settings only
    $isAdmin = false;
    try {
        $auth = Auth::getInstance();
        $admin = $auth->user();
        $isAdmin = $admin !== null;
    } catch (Exception $e) {
        // Not authenticated, that's okay for public settings
    }
    
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($tableCheck->affected_rows === 0) {
        // Table doesn't exist, return empty settings
        echo json_encode([
            'success' => true,
            'data' => [],
            'message' => 'Settings table not initialized. Please run the SQL migration.'
        ]);
        return;
    }
    
    $sql = "SELECT setting_key, setting_value, setting_type FROM site_settings";
    $result = $db->query($sql);
    
    if (!$result) {
        throw new Exception("Failed to fetch settings");
    }
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $key = $row['setting_key'];
        $value = $row['setting_value'];
        $type = $row['setting_type'];
        
        // Convert value based on type
        switch ($type) {
            case 'boolean':
                $value = $value === 'true' || $value === '1';
                break;
            case 'number':
                $value = is_numeric($value) ? floatval($value) : 0;
                break;
            case 'json':
                $value = json_decode($value, true);
                break;
        }
        
        // Convert snake_case to camelCase for frontend
        $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $settings[$camelKey] = $value;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);
}

/**
 * PUT/POST - Update settings
 */
function handleUpdate($db) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data provided']);
        return;
    }
    
    // Map of camelCase to snake_case keys
    $keyMap = [
        'siteName' => 'site_name',
        'siteDescription' => 'site_description',
        'contactEmail' => 'contact_email',
        'contactPhone' => 'contact_phone',
        'address' => 'address',
        'enableRegistration' => 'enable_registration',
        'requireEmailVerification' => 'require_email_verification',
        'enableNotifications' => 'enable_notifications',
        'maintenanceMode' => 'maintenance_mode',
        'maintenanceMessage' => 'maintenance_message'
    ];
    
    $updated = 0;
    $errors = [];
    
    foreach ($data as $camelKey => $value) {
        if (!isset($keyMap[$camelKey])) {
            continue; // Skip unknown keys
        }
        
        $snakeKey = $keyMap[$camelKey];
        
        // Convert boolean to string for storage
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        
        // Update or insert setting
        $stmt = $db->prepare("
            INSERT INTO site_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ");
        $stmt_params = [$snakeKey, $value];
        
        if ($stmt->execute()) {
            $updated++;
        } else {
            $errors[] = "Failed to update $camelKey";
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Some settings failed to update',
            'errors' => $errors
        ]);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Settings updated successfully ($updated changed)"
    ]);
}
?>
