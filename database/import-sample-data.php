<?php
/**
 * Database Sample Data Importer
 * Run this script to import sample data for development/testing
 * 
 * Usage: php import-sample-data.php
 * 
 * Make sure database.php config is properly configured before running this script
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "========================================\n";
echo "DUETCS Database Sample Data Importer\n";
echo "========================================\n\n";

try {
    // Include database configuration
    require_once __DIR__ . '/../config/database.php';
    
    $db = Database::getInstance()->getConnection();
    
    if (!$db) {
        throw new Exception("Failed to connect to database");
    }
    
    echo "✓ Connected to database: " . DB_NAME . "\n";
    echo "✓ Host: " . DB_HOST . "\n\n";
    
    // List of SQL files to import
    $files = [
        'schema.sql' => 'Core Tables',
        'admin_schema.sql' => 'Admin Tables',
        'create_notices_table.sql' => 'Notices Table',
        'sample-content.sql' => 'Sample Test Data',
    ];
    
    $imported = [];
    $failed = [];
    
    foreach ($files as $file => $description) {
        $filePath = __DIR__ . '/' . $file;
        
        if (!file_exists($filePath)) {
            echo "⚠ Skipping $file (not found)\n";
            continue;
        }
        
        echo "Importing $file ($description)...\n";
        
        try {
            $sql = file_get_contents($filePath);
            
            // Split by semicolon and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $sql)), function($stmt) {
                // Remove comments
                $lines = array_filter(array_map('trim', explode("\n", $stmt)), function($line) {
                    return !empty($line) && !startsWith($line, '--') && !startsWith($line, '/*');
                });
                return !empty(implode('', $lines));
            });
            
            foreach ($statements as $statement) {
                if (!empty(trim($statement))) {
                    if (!$db->query($statement)) {
                        // Some errors are expected (like "table already exists")
                        if (strpos($db->error, 'already exists') === false && 
                            strpos($db->error, 'Duplicate entry') === false) {
                            throw new Exception($db->error);
                        }
                    }
                }
            }
            
            echo "  ✓ Imported successfully\n";
            $imported[] = $file;
            
        } catch (Exception $e) {
            echo "  ✗ Error: " . $e->getMessage() . "\n";
            $failed[] = $file;
        }
    }
    
    echo "\n========================================\n";
    echo "Import Summary\n";
    echo "========================================\n";
    echo "✓ Successfully imported: " . count($imported) . "\n";
    if (!empty($imported)) {
        foreach ($imported as $file) {
            echo "  - $file\n";
        }
    }
    
    if (!empty($failed)) {
        echo "\n✗ Failed to import: " . count($failed) . "\n";
        foreach ($failed as $file) {
            echo "  - $file\n";
        }
    }
    
    echo "\n========================================\n";
    
    // Verify tables were created
    echo "\nVerifying tables...\n";
    
    $tables = [
        'users',
        'admin_roles',
        'events',
        'notices',
        'executive_members',
        'payment_records'
    ];
    
    foreach ($tables as $table) {
        $result = $db->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "⚠ Table '$table' not found\n";
        }
    }
    
    echo "\n✓ Database setup complete!\n";
    echo "\nYou can now test the API endpoints:\n";
    echo "- GET http://localhost/duetcs-backend/api/admin/stats.php\n";
    echo "- GET http://localhost/duetcs-backend/api/admin/users.php\n";
    echo "- GET http://localhost/duetcs-backend/api/admin/payments.php\n";
    echo "- GET http://localhost/duetcs-backend/api/admin/roles.php\n";
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Helper function to check if string starts with prefix
 */
function startsWith($string, $prefix) {
    return strpos($string, $prefix) === 0;
}

?>
