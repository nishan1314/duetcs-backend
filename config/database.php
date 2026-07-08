<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'duetcs_db');

// Database Singleton Class
class Database {
    private static $instance = null;
    private $connection = null;
    
    private function __construct() {
        $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->connection->connect_error) {
            error_log("Database Connection Failed: " . $this->connection->connect_error);
            die("Database Connection Failed: " . $this->connection->connect_error);
        }
        
        // Set charset to utf8mb4
        $this->connection->set_charset("utf8mb4");
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
