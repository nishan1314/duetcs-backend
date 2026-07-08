<?php
/**
 * Test Script to Verify System Admin Setup
 * Access this via: http://localhost/duetcs-backend/database/test_admin.php
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>System Admin Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0; }
        .info { color: #004085; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 4px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #4CAF50; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        .test-form { background: #f9f9f9; padding: 15px; border-radius: 4px; margin: 20px 0; }
        input { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        button { background: #4CAF50; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #45a049; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 System Admin Setup Test</h1>
        
        <?php
        try {
            $db = Database::getInstance()->getConnection();
            
            // Test 1: Check if table exists
            echo "<h2>Test 1: Table Existence</h2>";
            $result = $db->query("SHOW TABLES LIKE 'system_admin'");
            if ($result->num_rows > 0) {
                echo "<div class='success'>✓ Table 'system_admin' exists</div>";
            } else {
                echo "<div class='error'>✗ Table 'system_admin' does NOT exist</div>";
                echo "<div class='info'>Run the SQL file: backend/database/setup_system_admin.sql</div>";
                exit;
            }
            
            // Test 2: Check table structure
            echo "<h2>Test 2: Table Structure</h2>";
            $result = $db->query("DESCRIBE system_admin");
            echo "<table><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>{$row['Field']}</td>";
                echo "<td>{$row['Type']}</td>";
                echo "<td>{$row['Null']}</td>";
                echo "<td>{$row['Key']}</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // Test 3: Check for super admin
            echo "<h2>Test 3: Super Admin Account</h2>";
            $stmt = $db->prepare("SELECT id, name, email, designation, is_active, created_at FROM system_admin WHERE email = ?");
            $email = 'duetcs@duet.ac.bd';
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $admin = $result->fetch_assoc();
                echo "<div class='success'>✓ Super Admin account found</div>";
                echo "<table>";
                foreach ($admin as $key => $value) {
                    echo "<tr><th>$key</th><td>$value</td></tr>";
                }
                echo "</table>";
            } else {
                echo "<div class='error'>✗ Super Admin account NOT found</div>";
                echo "<div class='info'>Run: php backend/database/setup_system_admin.php</div>";
            }
            
            // Test 4: Password verification test
            if (isset($_POST['test_password'])) {
                echo "<h2>Test 4: Password Verification</h2>";
                $testPassword = $_POST['password'];
                
                $stmt = $db->prepare("SELECT id, name, password FROM system_admin WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $admin = $result->fetch_assoc();
                    $passwordMatches = password_verify($testPassword, $admin['password']);
                    
                    if ($passwordMatches) {
                        echo "<div class='success'>✓ Password verification SUCCESSFUL</div>";
                        echo "<p>The password '<code>$testPassword</code>' is correct for admin ID: {$admin['id']} ({$admin['name']})</p>";
                    } else {
                        echo "<div class='error'>✗ Password verification FAILED</div>";
                        echo "<p>The password '<code>$testPassword</code>' is INCORRECT</p>";
                        echo "<p><strong>Password hash in database:</strong><br><code style='word-break: break-all;'>{$admin['password']}</code></p>";
                        echo "<div class='info'>The password might need to be reset. Run setup_system_admin.php again.</div>";
                    }
                }
            }
            
            $stmt->close();
            $db->close();
            
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
        }
        ?>
        
        <div class="test-form">
            <h2>Test Password</h2>
            <form method="POST">
                <label>Email:</label>
                <input type="email" name="email" value="duetcs@duet.ac.bd" readonly>
                
                <label>Password to test:</label>
                <input type="text" name="password" placeholder="Enter password to test" required>
                
                <button type="submit" name="test_password">Test Password</button>
            </form>
        </div>
        
        <div class="info">
            <h3>Expected Login Credentials:</h3>
            <p><strong>Email:</strong> duetcs@duet.ac.bd</p>
            <p><strong>Password:</strong> adminduetcs</p>
        </div>
    </div>
</body>
</html>
