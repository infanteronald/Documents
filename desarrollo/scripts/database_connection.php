<?php
/**
 * Configuración de Conexión a Base de Datos - Sequoia Speed
 * Soporta tanto MySQLi (legacy) como PDO (nuevo sistema MVC)
 */

// Configuración de base de datos
$db_config = [
    'host' => '68.66.226.124',
    'username' => 'motodota_facturacion',
    'password' => 'Blink.182...',
    'database' => 'motodota_facturacion',
    'charset' => 'utf8mb4',
    'port' => 3306
];

// Configuración de opciones PDO
$pdo_options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    PDO::ATTR_TIMEOUT => 30
];

try {
    // Conexión PDO para el nuevo sistema MVC
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";
    $pdo = new PDO($dsn, $db_config['username'], $db_config['password'], $pdo_options);
    
    // Establecer zona horaria
    $pdo->exec("SET time_zone = '-05:00'"); // Zona horaria Colombia
    
} catch (PDOException $e) {
    // Log del error para el administrador
    error_log("Error de conexión PDO: " . $e->getMessage());
    
    // En modo desarrollo, mostrar detalles
    if (defined('APP_ENV') && APP_ENV === 'development') {
        $error_details = $e->getMessage();
    } else {
        $error_details = 'Error de conexión a la base de datos';
    }
    
    // Respuesta JSON para APIs
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Error crítico: No se pudo conectar a la base de datos.',
            'details' => $error_details
        ]);
        exit;
    }
    
    // Respuesta HTML para páginas web
    die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
}

// Mantener compatibilidad con MySQLi para archivos legacy
try {
    $servername = $db_config['host'];
    $username = $db_config['username'];
    $password = $db_config['password'];
    $dbname = $db_config['database'];
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Establecer UTF-8 para MySQLi
    if (!$conn->set_charset("utf8mb4")) {
        error_log("Error al establecer el charset UTF-8: " . $conn->error);
        throw new Exception("Error de configuración de charset: " . $conn->error);
    }
    
    // Establecer zona horaria para MySQLi
    $conn->query("SET time_zone = '-05:00'");
    
} catch (Exception $e) {
    error_log("Error de conexión MySQLi: " . $e->getMessage());
    
    // Solo mostrar error si PDO también falló
    if (!isset($pdo)) {
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Error crítico: No se pudo conectar a la base de datos.',
                'details' => $e->getMessage()
            ]);
            exit;
        }
        die("Error de conexión a la base de datos. Por favor, inténtelo más tarde.");
    }
}

// Función auxiliar para obtener conexión PDO
function getDBConnection() {
    global $pdo;
    return $pdo;
}

// Función auxiliar para obtener conexión MySQLi (compatibilidad)
function getMySQLiConnection() {
    global $conn;
    return $conn;
}

// Función para verificar conexión
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query('SELECT 1');
        return ['status' => 'success', 'message' => 'Conexión exitosa'];
    } catch (PDOException $e) {
        return ['status' => 'error', 'message' => $e->getMessage()];
    }
}
?>
