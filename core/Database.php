<?php
/**
 * Database Connection Handler
 * Singleton pattern for database connections
 * Supports both PDO and MySQLi with automatic fallback
 */

class Database {
    private static $instance = null;
    private $connection;
    private $connectionType; // 'pdo' or 'mysqli'
    
    private function __construct() {
        // Try PDO first, then MySQLi, then SQLite as fallback
        if ($this->tryPDOConnection()) {
            $this->connectionType = 'pdo';
        } elseif ($this->tryMySQLiConnection()) {
            $this->connectionType = 'mysqli';
        } elseif ($this->trySQLiteConnection()) {
            $this->connectionType = 'sqlite';
        } else {
            throw new Exception("Database connection failed: No suitable database driver available");
        }
    }
    
    /**
     * Try to establish PDO connection
     */
    private function tryPDOConnection() {
        try {
            // Check if mysql driver is available for PDO
            if (!in_array('mysql', PDO::getAvailableDrivers())) {
                return false;
            }
            
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Try to establish MySQLi connection
     */
    private function tryMySQLiConnection() {
        try {
            if (!extension_loaded('mysqli')) {
                return false;
            }
            
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
            
            if ($this->connection->connect_error) {
                return false;
            }
            
            // Set charset
            $this->connection->set_charset(DB_CHARSET);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Try to establish SQLite connection as fallback
     */
    private function trySQLiteConnection() {
        try {
            if (!in_array('sqlite', PDO::getAvailableDrivers())) {
                return false;
            }
            
            // Create data directory if it doesn't exist
            $dataDir = dirname(SQLITE_DB_PATH);
            if (!is_dir($dataDir)) {
                mkdir($dataDir, 0755, true);
            }
            
            $dsn = "sqlite:" . SQLITE_DB_PATH;
            $this->connection = new PDO($dsn);
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Create tables if they don't exist
            $this->createSQLiteTables();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Create SQLite tables
     */
    private function createSQLiteTables() {
        $sql = "
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(10) DEFAULT 'user',
                status VARCHAR(10) DEFAULT 'active',
                last_login DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                status VARCHAR(10) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            
            CREATE TABLE IF NOT EXISTS products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                price DECIMAL(10,2) NOT NULL,
                stock INTEGER DEFAULT 0,
                category_id INTEGER,
                image VARCHAR(255),
                status VARCHAR(10) DEFAULT 'active',
                created_by INTEGER,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id),
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );
        ";
        
        $this->connection->exec($sql);
        
        // Handle migrations for existing tables
        $this->migrateSQLiteTables();
    }
    
    /**
     * Handle SQLite table migrations
     */
    private function migrateSQLiteTables() {
        try {
            // Check if last_login column exists in users table
            $result = $this->connection->query("PRAGMA table_info(users)");
            $columns = $result->fetchAll(PDO::FETCH_ASSOC);
            
            $hasLastLogin = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'last_login') {
                    $hasLastLogin = true;
                    break;
                }
            }
            
            // Add last_login column if it doesn't exist
            if (!$hasLastLogin) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN last_login DATETIME");
            }
        } catch (Exception $e) {
            // Ignore migration errors for now
        }
    }
    
    /**
     * Get database instance
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get database connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Get connection type (pdo or mysqli)
     */
    public function getConnectionType() {
        return $this->connectionType;
    }
    
    /**
     * Execute a query and return results
     */
    public function query($sql, $params = []) {
        try {
            if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
                $stmt = $this->connection->prepare($sql);
                $stmt->execute($params);
                return $stmt;
            } else { // mysqli
                $stmt = $this->connection->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->connection->error);
                }
                
                if (!empty($params)) {
                    $types = str_repeat('s', count($params)); // assume all strings for simplicity
                    $stmt->bind_param($types, ...$params);
                }
                
                $stmt->execute();
                return $stmt;
            }
        } catch (Exception $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }
    
    /**
     * Execute a query and return single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $stmt->fetch();
        } else { // mysqli
            $result = $stmt->get_result();
            return $result ? $result->fetch_assoc() : null;
        }
    }
    
    /**
     * Execute a query and return all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $stmt->fetchAll();
        } else { // mysqli
            $result = $stmt->get_result();
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
    }
    
    /**
     * Execute insert/update/delete and return affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $stmt->rowCount();
        } else { // mysqli
            return $stmt->affected_rows;
        }
    }
    
    /**
     * Get last inserted ID
     */
    public function lastInsertId() {
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $this->connection->lastInsertId();
        } else { // mysqli
            return $this->connection->insert_id;
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $this->connection->beginTransaction();
        } else { // mysqli
            return $this->connection->autocommit(false);
        }
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $this->connection->commit();
        } else { // mysqli
            $result = $this->connection->commit();
            $this->connection->autocommit(true);
            return $result;
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->connectionType === 'pdo' || $this->connectionType === 'sqlite') {
            return $this->connection->rollback();
        } else { // mysqli
            $result = $this->connection->rollback();
            $this->connection->autocommit(true);
            return $result;
        }
    }
    
    /**
     * Get current timestamp in database-specific format
     */
    public function getCurrentTimestamp() {
        if ($this->connectionType === 'sqlite') {
            return "datetime('now')";
        } else {
            return "NOW()";
        }
    }
    
    /**
     * Get current date in database-specific format
     */
    public function getCurrentDate() {
        if ($this->connectionType === 'sqlite') {
            return "date('now')";
        } else {
            return "CURDATE()";
        }
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    private function __wakeup() {}
}