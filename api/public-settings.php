<?php
/**
 * Public Site Settings API
 * Returns public-facing settings without authentication
 */

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$db = Database::getInstance()->getConnection();

try {
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'site_settings'");
    if ($tableCheck->rowCount() === 0) {
        // Return default settings if table doesn't exist
        echo json_encode([
            'success' => true,
            'data' => [
                'siteName' => 'DUET Computer Society',
                'siteDescription' => 'Official website of DUET Computer Society',
                'contactEmail' => 'duetcs@duet.ac.bd',
                'contactPhone' => '+880-2-49274000',
                'address' => 'Dhaka University of Engineering & Technology, Gazipur',
                'maintenanceMode' => false
            ]
        ]);
        exit;
    }

    // Public settings keys (only return non-sensitive settings)
    $publicKeys = [
        'site_name',
        'site_description',
        'contact_email',
        'contact_phone',
        'address',
        'maintenance_mode',
        'maintenance_message'
    ];
    
    $placeholders = implode(',', array_fill(0, count($publicKeys), '?'));
    $types = str_repeat('s', count($publicKeys));
    
    $sql = "SELECT setting_key, setting_value, setting_type FROM site_settings WHERE setting_key IN ($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$publicKeys);
    $stmt->execute(isset($stmt_params) ? $stmt_params : null); if(isset($stmt_params)) unset($stmt_params);
    $result = $stmt;
    
    $settings = [];
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
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
        
        // Convert snake_case to camelCase
        $camelKey = lcfirst(str_replace('_', '', ucwords($key, '_')));
        $settings[$camelKey] = $value;
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);

} catch (Exception $e) {
    error_log("Public Settings API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch settings'
    ]);
}
?>
