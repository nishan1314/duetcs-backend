<?php
/**
 * Standalone Login Test
 * This mimics exactly what admin-login.php does
 */

// Show all errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>Login Test</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#f5f5f5;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body>";
echo "<h1>Login Test</h1>";

try {
    require_once __DIR__ . '/../../config/database.php';
    
    // Test credentials
    $testEmail = 'duetcs@duet.ac.bd';
    $testPassword = 'adminduetcs';
    
    echo "<p class='info'>Testing login with:</p>";
    echo "<p>Email: <strong>$testEmail</strong><br>";
    echo "Password: <strong>$testPassword</strong></p>";
    echo "<hr>";
    
    $db = Database::getInstance()->getConnection();
    echo "<p class='success'>✓ Database connection successful</p>";
    
    // Step 1: Check if table exists
    echo "<p class='info'>Step 1: Checking table...</p>";
    $result = $db->query("SHOW TABLES LIKE 'system_admin'");
    if ($result->num_rows === 0) {
        echo "<p class='error'>✗ ERROR: Table 'system_admin' does not exist!</p>";
        echo "</body></html>";
        exit;
    }
    echo "<p class='success'>✓ Table exists</p>";
    
    // Step 2: Find admin by email
    echo "<p class='info'>Step 2: Looking up email...</p>";
    $stmt = $db->prepare("
        SELECT id, name, email, password, designation, phone_number, is_active, last_login 
        FROM system_admin 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $testEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<p class='error'>✗ ERROR: No admin found with email: $testEmail</p>";
        echo "</body></html>";
        exit;
    }
    echo "<p class='success'>✓ Admin found</p>";
    
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    // Step 3: Display admin info
    echo "<h3>Admin Info:</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><td>ID</td><td>{$admin['id']}</td></tr>";
    echo "<tr><td>Name</td><td>{$admin['name']}</td></tr>";
    echo "<tr><td>Email</td><td>{$admin['email']}</td></tr>";
    echo "<tr><td>Designation</td><td>{$admin['designation']}</td></tr>";
    echo "<tr><td>Active</td><td>" . ($admin['is_active'] ? 'Yes' : 'No') . "</td></tr>";
    echo "<tr><td>Password Hash</td><td>" . substr($admin['password'], 0, 30) . "...</td></tr>";
    echo "<tr><td>Hash Length</td><td>" . strlen($admin['password']) . "</td></tr>";
    echo "</table>";
    
    // Step 4: Check if active
    echo "<p class='info'>Step 3: Checking if active...</p>";
    if (!$admin['is_active']) {
        echo "<p class='error'>✗ ERROR: Account is deactivated</p>";
        echo "</body></html>";
        exit;
    }
    echo "<p class='success'>✓ Account is active</p>";
    
    // Step 5: Verify password
    echo "<p class='info'>Step 4: Verifying password...</p>";
    echo "<p>Testing password: '<strong>$testPassword</strong>'<br>";
    echo "Password length: " . strlen($testPassword) . "</p>";
    
    $passwordMatches = password_verify($testPassword, $admin['password']);
    
    if ($passwordMatches) {
        echo "<h2 class='success'>✓ Password verification SUCCESS!</h2>";
        echo "<h2 class='success'>LOGIN WOULD SUCCEED ✓</h2>";
    } else {
        echo "<h2 class='error'>✗ Password verification FAILED!</h2>";
        
        // Additional debugging
        echo "<h3>Debug Info:</h3>";
        echo "<ul>";
        
        // Test if it's a bcrypt hash
        if (substr($admin['password'], 0, 4) === '$2y$' || substr($admin['password'], 0, 4) === '$2a$') {
            echo "<li class='success'>Hash format: Looks like bcrypt (correct)</li>";
        } else {
            echo "<li class='error'>Hash format: NOT bcrypt! (ERROR)</li>";
            echo "<li>Hash starts with: " . htmlspecialchars(substr($admin['password'], 0, 10)) . "</li>";
        }
        
        // Try to generate a new hash for comparison
        $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
        echo "<li>New hash for same password: " . substr($newHash, 0, 30) . "...</li>";
        
        // Test plain text (should NOT match)
        if ($testPassword === $admin['password']) {
            echo "<li class='error'>⚠️ WARNING: Password is stored in PLAIN TEXT!</li>";
        }
        
        echo "</ul>";
        echo "<h2 class='error'>LOGIN WOULD FAIL ✗</h2>";
        echo "<p><strong>Solution:</strong> Run this command to reset the password:</p>";
        echo "<pre>php backend/database/setup_system_admin.php</pre>";
    }
    
    $db->close();
    
} catch (Exception $e) {
    echo "<p class='error'>✗ EXCEPTION: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Error $e) {
    echo "<p class='error'>✗ FATAL ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
