<?php
/**
 * Configuración de base de datos local para pruebas Bold
 * Este archivo permite hacer pruebas sin depender de la BD remota
 */

// Configuración para base de datos local (SQLite para pruebas)
$db_local_file = __DIR__ . '/bold_test.sqlite';

try {
    // Crear conexión SQLite local para pruebas
    $conexion_local = new PDO("sqlite:$db_local_file");
    $conexion_local->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tabla de prueba si no existe
    $create_table = "
    CREATE TABLE IF NOT EXISTS pedidos_bold_test (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        referencia VARCHAR(50) UNIQUE,
        estado VARCHAR(20),
        metodo_pago VARCHAR(30),
        monto DECIMAL(10,2),
        bold_transaction_id VARCHAR(100),
        bold_status VARCHAR(20),
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        webhook_data TEXT,
        intentos_webhook INTEGER DEFAULT 0
    )";
    
    $conexion_local->exec($create_table);
    
    // Insertar algunos datos de prueba
    $insert_test = "INSERT OR IGNORE INTO pedidos_bold_test 
                   (referencia, estado, metodo_pago, monto, bold_status) VALUES 
                   ('TEST001', 'pendiente', 'PSE', 50000, 'PENDING'),
                   ('TEST002', 'pendiente', 'BANCOLOMBIA_BUTTON', 75000, 'PENDING'),
                   ('TEST003', 'pendiente', 'CARD', 100000, 'PENDING')";
    
    $conexion_local->exec($insert_test);
    
    echo "Base de datos local creada exitosamente\n";
    
} catch (Exception $e) {
    echo "Error configurando BD local: " . $e->getMessage() . "\n";
}

// Función para obtener conexión de prueba
function getTestConnection() {
    global $db_local_file;
    try {
        $pdo = new PDO("sqlite:$db_local_file");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (Exception $e) {
        return null;
    }
}

// Función para verificar si estamos en modo de prueba
function isTestMode() {
    return isset($_GET['test_mode']) || isset($_POST['test_mode']) || 
           (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false);
}
?>
