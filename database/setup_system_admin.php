<?php
/**
 * Complete System Admin Setup Script
 * Creates table and super admin in one go
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    
    echo "Starting System Admin Setup...\n\n";
    
    // Step 1: Create system_admin table
    echo "Step 1: Creating system_admin table...\n";
    $createTableSQL = "
    CREATE TABLE IF NOT EXISTS system_admin (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        designation ENUM('Super Admin', 'Admin', 'Moderator') NOT NULL DEFAULT 'Admin',
        phone_number VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_login TIMESTAMP NULL,
        is_active TINYINT(1) DEFAULT 1,
        INDEX idx_email (email),
        INDEX idx_designation (designation)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($db->query($createTableSQL)) {
        echo "✓ Table created/verified successfully\n\n";
    } else {
        throw new Exception("Error creating table: " . $db->error);
    }
    
    // Step 2: Create/Update Super Admin
    echo "Step 2: Creating Super Admin account...\n";
    
    $email = 'duetcs@duet.ac.bd';
    $password = 'adminduetcs';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Check if super admin exists
    $stmt = $db->prepare("SELECT id, name FROM system_admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $admin = $result->fetch_assoc();
        echo "✓ Super Admin already exists (ID: {$admin['id']}, Name: {$admin['name']})\n";
        
        // Update password
        $stmt = $db->prepare("UPDATE system_admin SET password = ?, is_active = 1 WHERE email = ?");
        $stmt->bind_param("ss", $hashedPassword, $email);
        $stmt->execute();
        echo "✓ Password updated to: adminduetcs\n";
    } else {
        // Insert new super admin
        $stmt = $db->prepare("
            INSERT INTO system_admin (name, email, password, designation, is_active) 
            VALUES (?, ?, ?, 'Super Admin', 1)
        ");
        $name = 'Super Administrator';
        $stmt->bind_param("sss", $name, $email, $hashedPassword);
        
        if ($stmt->execute()) {
            $newId = $stmt->insert_id;
            echo "✓ Super Admin created successfully (ID: $newId)\n";
        } else {
            throw new Exception("Error creating super admin: " . $stmt->error);
        }
    }
    
    echo "\n";
    echo "========================================\n";
    echo "Setup Complete!\n";
    echo "========================================\n";
    echo "Login credentials:\n";
    echo "  Email: duetcs@duet.ac.bd\n";
    echo "  Password: adminduetcs\n";
    echo "========================================\n";
    
    // Step 3: Verify the account
    echo "\nVerifying account...\n";
    $stmt = $db->prepare("SELECT id, name, email, designation, is_active FROM system_admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    
    if ($admin) {
        echo "✓ Account verified:\n";
        echo "  ID: {$admin['id']}\n";
        echo "  Name: {$admin['name']}\n";
        echo "  Email: {$admin['email']}\n";
        echo "  Designation: {$admin['designation']}\n";
        echo "  Active: " . ($admin['is_active'] ? 'Yes' : 'No') . "\n";
    }
    
    $stmt->close();
    $db->close();
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
