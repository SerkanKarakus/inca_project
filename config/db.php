<?php
/**
 * Database Connection configuration and PDO helper.
 * Designed for PHP 8+ and compatible with MySQL.
 */

// Prevent direct access to config files if accessed via web server
if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

// Database Connection Settings
// Automatically switches between Localhost and cPanel Staging/Production environments
$hostHeader = $_SERVER['HTTP_HOST'] ?? '';
$isLocal = (php_sapi_name() === 'cli') || (strpos($hostHeader, 'localhost') !== false || strpos($hostHeader, '127.0.0.1') !== false);

if ($isLocal) {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'inca_catalog');
    define('DB_USER', 'root');
    define('DB_PASS', '');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'incakses_main');
    define('DB_USER', 'incakses_admin');
    define('DB_PASS', 'Selcuk23.');
}
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a singleton PDO connection.
 * 
 * @return PDO
 * @throws PDOException if connection fails
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Fetch associative arrays by default
            PDO::ATTR_EMULATE_PREPARES   => false,                  // True prepared statements for SQLi prevention
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log connection failure details securely (preventing path/credentials leakage to client)
            error_log("Database connection failed: " . $e->getMessage());
            
            // Render user-friendly message
            header("HTTP/1.1 500 Internal Server Error");
            die("Database connection error. Please check configuration or try again later.");
        }
    }
    
    return $pdo;
}
