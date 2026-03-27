<?php
/**
 * Database Configuration
 * Loads environment variables and establishes database connection
 */

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            $value = trim($value, '"\'');
            
            // Set environment variable
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
    }
    return true;
}

// Load .env file
$envPath = dirname(__DIR__) . '/.env';
loadEnv($envPath);

// Get environment variables with defaults
function env($key, $default = null) {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Database configuration array
$dbConfig = [
    'host' => env('DB_HOST', 'localhost'),
    'port' => env('DB_PORT', '3306'),
    'database' => env('DB_NAME', 'universal_corporate'),
    'username' => env('DB_USER', 'root'),
    'password' => env('DB_PASS', ''),
    'charset' => env('DB_CHARSET', 'utf8mb4'),
];

/**
 * Create database connection
 * @return PDO|null
 */
function getDatabaseConnection() {
    global $dbConfig;
    
    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['charset']
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], $options);
        return $pdo;
    } catch (PDOException $e) {
        if (env('APP_DEBUG', false)) {
            error_log("Database Connection Error: " . $e->getMessage());
        }
        return null;
    }
}

return $dbConfig;
