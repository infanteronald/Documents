<?php
/**
 * Configuración de Base de Datos
 * 
 * Este archivo centraliza la configuración de BD manteniendo
 * compatibilidad con conexion.php existente
 */

// Cargar variables de entorno si existe .env
if (file_exists(ROOT_PATH . '/.env')) {
    $lines = file(ROOT_PATH . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
        }
    }
}

return [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'motodota_sequoia',
    'username' => getenv('DB_USERNAME') ?: 'motodota_ronald',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];

/**
 * Función helper para obtener conexión MySQLi (compatibilidad)
 */
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $config = include __FILE__;
        
        $conn = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database']
        );
        
        if ($conn->connect_error) {
            logMessage('ERROR', 'Database connection failed: ' . $conn->connect_error);
            die("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset($config['charset']);
        logMessage('INFO', 'Database connection established');
    }
    
    return $conn;
}

/**
 * Función helper para obtener conexión PDO
 */
function getPDOConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        $config = include __FILE__;
        
        $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
        
        try {
            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options']);
            logMessage('INFO', 'PDO connection established');
        } catch (PDOException $e) {
            logMessage('ERROR', 'PDO connection failed: ' . $e->getMessage());
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }
    
    return $pdo;
}
