<?php
/**
 * Debug endpoint - Check system_admin table
 * DELETE THIS FILE AFTER DEBUGGING
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');

require_once __DIR__ . '/../../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check if table exists
    $result = $db->query("SHOW TABLES LIKE 'system_admin'");
    $tableExists = $result->num_rows > 0;
    
    $response = [
        'table_exists' => $tableExists,
        'admins' => []
    ];
    
    if ($tableExists) {
        // Get all admins (without passwords)
        $result = $db->query("
            SELECT id, name, email, designation, is_active, created_at 
            FROM system_admin
        ");
        
        while ($row = $result->fetch_assoc()) {
            $response['admins'][] = $row;
        }
        
        // Check super admin specifically
        $stmt = $db->prepare("
            SELECT id, name, email, designation, is_active, 
                   LENGTH(password) as password_length,
                   SUBSTRING(password, 1, 10) as password_prefix
            FROM system_admin 
            WHERE email = ?
        ");
        $email = 'duetcs@duet.ac.bd';
        $stmt_params = [$email];
        $stmt->execute($stmt_params ?? null);
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $response['super_admin'] = $result->fetch_assoc();
            $response['super_admin_exists'] = true;
        } else {
            $response['super_admin_exists'] = false;
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
