<?php
/**
 * Script de configuración de base de datos para Bold PSE
 * Crea las tablas y campos necesarios para manejar pagos Bold
 */

require_once "conexion.php";

echo "<h2>Configuración de Base de Datos - Bold PSE Integration</h2>\n";

try {
    // Verificar si la tabla pedidos existe
    $result = $conn->query("SHOW TABLES LIKE 'pedidos'");
    
    if ($result->num_rows == 0) {
        // Crear tabla pedidos si no existe
        echo "<p>Creando tabla 'pedidos'...</p>\n";
        $sql = "
        CREATE TABLE pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            bold_order_id VARCHAR(100) UNIQUE,
            bold_transaction_id VARCHAR(100),
            nombre VARCHAR(255) NOT NULL,
            direccion TEXT,
            telefono VARCHAR(20),
            correo VARCHAR(255),
            persona_recibe VARCHAR(255),
            horarios VARCHAR(255),
            metodo_pago VARCHAR(50) NOT NULL,
            monto_total DECIMAL(10,2),
            comentario TEXT,
            comprobante_pago VARCHAR(255),
            estado_pago ENUM('pendiente', 'pagado', 'fallido', 'cancelado') DEFAULT 'pendiente',
            estado_pedido ENUM('nuevo', 'procesando', 'enviado', 'entregado', 'cancelado') DEFAULT 'nuevo',
            bold_response JSON,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_pago TIMESTAMP NULL,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Tabla 'pedidos' creada exitosamente</p>\n";
        } else {
            throw new Exception("Error creando tabla pedidos: " . $conn->error);
        }
    } else {
        echo "<p>Tabla 'pedidos' ya existe. Verificando campos Bold...</p>\n";
        
        // Verificar y agregar campos Bold si no existen
        $fieldsToAdd = [
            'bold_order_id' => "ADD COLUMN bold_order_id VARCHAR(100) UNIQUE",
            'bold_transaction_id' => "ADD COLUMN bold_transaction_id VARCHAR(100)",
            'bold_response' => "ADD COLUMN bold_response JSON",
            'fecha_pago' => "ADD COLUMN fecha_pago TIMESTAMP NULL"
        ];
        
        foreach ($fieldsToAdd as $field => $sql) {
            $checkField = $conn->query("SHOW COLUMNS FROM pedidos LIKE '$field'");
            if ($checkField->num_rows == 0) {
                if ($conn->query("ALTER TABLE pedidos $sql") === TRUE) {
                    echo "<p style='color: green;'>✓ Campo '$field' agregado</p>\n";
                } else {
                    echo "<p style='color: orange;'>⚠ Error agregando campo '$field': " . $conn->error . "</p>\n";
                }
            } else {
                echo "<p>✓ Campo '$field' ya existe</p>\n";
            }
        }
    }
    
    // Verificar tabla pedido_detalle
    $result = $conn->query("SHOW TABLES LIKE 'pedido_detalle'");
    
    if ($result->num_rows == 0) {
        echo "<p>Creando tabla 'pedido_detalle'...</p>\n";
        $sql = "
        CREATE TABLE pedido_detalle (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            producto_nombre VARCHAR(255) NOT NULL,
            precio DECIMAL(10,2) NOT NULL,
            cantidad INT NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE
        )";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Tabla 'pedido_detalle' creada exitosamente</p>\n";
        } else {
            throw new Exception("Error creando tabla pedido_detalle: " . $conn->error);
        }
    } else {
        echo "<p>✓ Tabla 'pedido_detalle' ya existe</p>\n";
    }
    
    // Crear tabla de logs Bold si no existe
    $result = $conn->query("SHOW TABLES LIKE 'bold_logs'");
    
    if ($result->num_rows == 0) {
        echo "<p>Creando tabla 'bold_logs'...</p>\n";
        $sql = "
        CREATE TABLE bold_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(100),
            transaction_id VARCHAR(100),
            event_type VARCHAR(50),
            event_data JSON,
            ip_address VARCHAR(45),
            user_agent TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_id (order_id),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_event_type (event_type)
        )";
        
        if ($conn->query($sql) === TRUE) {
            echo "<p style='color: green;'>✓ Tabla 'bold_logs' creada exitosamente</p>\n";
        } else {
            throw new Exception("Error creando tabla bold_logs: " . $conn->error);
        }
    } else {
        echo "<p>✓ Tabla 'bold_logs' ya existe</p>\n";
    }
    
    echo "<hr>\n";
    echo "<h3 style='color: green;'>🎉 Configuración de base de datos completada exitosamente</h3>\n";
    echo "<h4>Próximos pasos:</h4>\n";
    echo "<ol>\n";
    echo "<li>Configurar las llaves de Bold en <code>bold_hash.php</code></li>\n";
    echo "<li>Configurar el webhook en el panel de Bold: <code>https://tudominio.com/bold_webhook.php</code></li>\n";
    echo "<li>Probar la integración con una transacción de prueba</li>\n";
    echo "</ol>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<p><a href='index.php'>← Volver al formulario de pedidos</a></p>\n";
?>
