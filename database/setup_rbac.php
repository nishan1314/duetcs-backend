<?php
/**
 * RBAC Setup Script
 * Creates tables and default Super Admin
 */

require_once __DIR__ . '/../config/database.php';

echo "Starting RBAC Setup...\n\n";

try {
    $db = Database::getInstance()->getConnection();
    
    // Read and execute schema
    echo "Step 1: Executing RBAC schema...\n";
    $schema = file_get_contents(__DIR__ . '/rbac_schema.sql');
    
    // Split by semicolon and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    
    foreach ($statements as $statement) {
        if (!empty($statement) && !preg_match('/^--/', $statement)) {
            try {
                $db->query($statement);
            } catch (Exception $e) {
                // Ignore duplicate entry errors for INSERT IGNORE
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    echo "  Warning: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    echo "✓ Schema executed successfully\n\n";
    
    // Create or update Super Admin
    echo "Step 2: Setting up Super Admin...\n";
    
    $email = 'duetcs@duet.ac.bd';
    $password = 'adminduetcs';
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $name = 'Super Administrator';
    $role = 'SUPER_ADMIN';
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT id FROM system_admin WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing
        $admin = $result->fetch_assoc();
        $stmt = $db->prepare("UPDATE system_admin SET password = ?, role = ?, name = ?, is_active = 1 WHERE id = ?");
        $stmt->bind_param("sssi", $hashedPassword, $role, $name, $admin['id']);
        $stmt->execute();
        echo "✓ Super Admin updated (ID: {$admin['id']})\n";
    } else {
        // Create new
        $stmt = $db->prepare("INSERT INTO system_admin (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);
        $stmt->execute();
        echo "✓ Super Admin created (ID: {$db->insert_id})\n";
    }
    
    echo "\n========================================\n";
    echo "RBAC Setup Complete!\n";
    echo "========================================\n";
    echo "Login credentials:\n";
    echo "  Email: $email\n";
    echo "  Password: $password\n";
    echo "  Role: $role\n";
    echo "========================================\n\n";
    
    // Verify permissions
    echo "Verifying permissions...\n";
    $result = $db->query("SELECT role, COUNT(*) as count FROM role_permissions GROUP BY role");
    while ($row = $result->fetch_assoc()) {
        echo "  {$row['role']}: {$row['count']} permissions\n";
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
