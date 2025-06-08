<?php
/**
 * Configuración para pruebas del sistema de pedidos
 * Este archivo define las configuraciones específicas para el entorno de pruebas
 */

// Configuración de base de datos para pruebas
define('TEST_DB_HOST', 'localhost');
define('TEST_DB_NAME', 'pedidos_test'); // Base de datos separada para pruebas
define('TEST_DB_USER', 'test_user');
define('TEST_DB_PASS', 'test_password');

// Configuración de Bold para pruebas (usar credenciales de sandbox)
define('TEST_BOLD_API_KEY', 'sandbox_key_here');
define('TEST_BOLD_SECRET', 'sandbox_secret_here');
define('TEST_BOLD_ENVIRONMENT', 'sandbox');

// Configuración de archivos para pruebas
define('TEST_UPLOADS_DIR', __DIR__ . '/fixtures/uploads/');
define('TEST_COMPROBANTES_DIR', __DIR__ . '/fixtures/comprobantes/');
define('TEST_LOGS_DIR', __DIR__ . '/logs/');

// Configuración de email para pruebas
define('TEST_SMTP_HOST', 'mailtrap.io'); // Usar servicio de testing
define('TEST_SMTP_PORT', 2525);
define('TEST_SMTP_USER', 'test_smtp_user');
define('TEST_SMTP_PASS', 'test_smtp_pass');

// URLs de prueba
define('TEST_BASE_URL', 'http://localhost/pedidos/tests/');
define('TEST_WEBHOOK_URL', 'http://localhost/pedidos/tests/fixtures/webhook_receiver.php');

// Configuraciones generales para pruebas
define('TEST_MODE', true);
define('TEST_DEBUG', true);
define('TEST_LOG_LEVEL', 'DEBUG');

// Datos de prueba predeterminados
$TEST_SAMPLE_PRODUCT = [
    'nombre' => 'Producto de Prueba',
    'precio' => 25000,
    'categoria' => 'test',
    'descripcion' => 'Producto para testing'
];

$TEST_SAMPLE_CUSTOMER = [
    'nombre' => 'Cliente Test',
    'email' => 'test@example.com',
    'telefono' => '+57 300 123 4567',
    'direccion' => 'Dirección de prueba 123'
];

$TEST_SAMPLE_ORDER = [
    'total' => 50000,
    'estado' => 'pendiente',
    'metodo_pago' => 'bold_test'
];

// Función para obtener conexión de BD de pruebas
function getTestDbConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . TEST_DB_HOST . ";dbname=" . TEST_DB_NAME,
            TEST_DB_USER,
            TEST_DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        return $pdo;
    } catch (PDOException $e) {
        throw new Exception("Error conectando a BD de pruebas: " . $e->getMessage());
    }
}

// Función para limpiar datos de prueba
function cleanTestData() {
    $pdo = getTestDbConnection();
    
    // Limpiar tablas en orden correcto (considerando foreign keys)
    $tables = ['pedidos_productos', 'pedidos', 'productos_test', 'usuarios_test'];
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("DELETE FROM $table WHERE created_for_test = 1");
        } catch (PDOException $e) {
            // Tabla podría no existir, continuar
        }
    }
}

// Función para preparar entorno de pruebas
function setupTestEnvironment() {
    // Crear directorios necesarios
    $dirs = [TEST_UPLOADS_DIR, TEST_COMPROBANTES_DIR, TEST_LOGS_DIR];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Limpiar logs anteriores
    $logFiles = glob(TEST_LOGS_DIR . '*.log');
    foreach ($logFiles as $logFile) {
        unlink($logFile);
    }
}
