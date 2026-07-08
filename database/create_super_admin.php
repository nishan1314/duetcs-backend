<?php
/**
 * Create Super Admin with properly hashed password
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Hash the password properly
    $password = 'adminduetcs';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if super admin already exists
    $stmt = $db->prepare("SELECT id FROM system_admin WHERE email = ?");
    $email = 'duetcs@duet.ac.bd';
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo "Super Admin already exists.\n";
        
        // Update password if needed
        $stmt = $db->prepare("UPDATE system_admin SET password = ? WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $stmt->execute();
        echo "Password updated successfully.\n";
    } else {
        // Insert new super admin
        $stmt = $db->prepare("
            INSERT INTO system_admin (name, email, password, designation, phone_number) 
            VALUES (?, ?, ?, 'Super Admin', NULL)
        ");
        $name = 'Super Administrator';
        $stmt->bind_param("sss", $name, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            echo "Super Admin created successfully!\n";
            echo "Email: duetcs@duet.ac.bd\n";
            echo "Password: adminduetcs\n";
        } else {
            echo "Error creating super admin: " . $stmt->error . "\n";
        }
    }
    
    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
