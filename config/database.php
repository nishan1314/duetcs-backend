<?php
// Database configuration
define('DB_HOST', 'db.mnvmqcnrgyldrmzswego.supabase.co');
define('DB_PORT', '5432');
define('DB_USER', 'postgres');
define('DB_PASS', 'duetcsadmin@@@');
define('DB_NAME', 'postgres');

// Database Singleton Class
class Database {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        try {
            $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
            $this->connection = new PDO($dsn, DB_USER, DB_PASS);
            
            // Set error mode to exception
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Legacy function for backward compatibility
function getDBConnection() {
    try {
        return Database::getInstance()->getConnection();
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

// Close database connection
function closeDBConnection($conn) {
    if ($conn) {
        // Don't close singleton connection, it stays open
        // $conn->close();
    }
}
?>
