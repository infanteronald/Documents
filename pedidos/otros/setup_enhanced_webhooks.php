<?php
/**
 * Script de configuración para sistema mejorado de webhooks Bold
 * Crea tablas necesarias para retry logic y logging avanzado
 */

require_once "conexion.php";

echo "<h2>🚀 Configuración del Sistema Mejorado de Webhooks Bold</h2>\n";

try {
    // 1. Crear tabla para cola de retry
    echo "<h3>1. Creando tabla bold_retry_queue...</h3>\n";
    $sql_retry_queue = "
    CREATE TABLE IF NOT EXISTS bold_retry_queue (
        id INT AUTO_INCREMENT PRIMARY KEY,
        webhook_data TEXT NOT NULL,
        error_message TEXT,
        attempts INT DEFAULT 0,
        status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        next_retry_at DATETIME,
        processed_at DATETIME NULL,
        INDEX idx_status (status),
        INDEX idx_next_retry (next_retry_at),
        INDEX idx_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($sql_retry_queue)) {
        echo "<p style='color: green;'>✅ Tabla bold_retry_queue creada exitosamente</p>\n";
    } else {
        throw new Exception("Error creando bold_retry_queue: " . $conn->error);
    }

    // 2. Crear tabla para logs de webhooks
    echo "<h3>2. Creando tabla bold_webhook_logs...</h3>\n";
    $sql_webhook_logs = "
    CREATE TABLE IF NOT EXISTS bold_webhook_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        webhook_id VARCHAR(100),
        event_type VARCHAR(50),
        order_id VARCHAR(100),
        transaction_id VARCHAR(100),
        status ENUM('success', 'error', 'warning', 'info') DEFAULT 'info',
        message TEXT,
        webhook_data JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        processing_time_ms INT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_webhook_id (webhook_id),
        INDEX idx_order_id (order_id),
        INDEX idx_status (status),
        INDEX idx_created (created_at),
        INDEX idx_event_type (event_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($sql_webhook_logs)) {
        echo "<p style='color: green;'>✅ Tabla bold_webhook_logs creada exitosamente</p>\n";
    } else {
        throw new Exception("Error creando bold_webhook_logs: " . $conn->error);
    }

    // 3. Agregar campos adicionales a pedidos_detal si no existen
    echo "<h3>3. Verificando campos en pedidos_detal...</h3>\n";
    
    // Verificar y agregar campo retry_count
    $result = $conn->query("SHOW COLUMNS FROM pedidos_detal LIKE 'retry_count'");
    if ($result->num_rows == 0) {
        $sql_add_retry = "ALTER TABLE pedidos_detal ADD COLUMN retry_count INT DEFAULT 0 AFTER bold_response";
        if ($conn->query($sql_add_retry)) {
            echo "<p style='color: green;'>✅ Campo retry_count agregado a pedidos_detal</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Error agregando retry_count: " . $conn->error . "</p>\n";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Campo retry_count ya existe en pedidos_detal</p>\n";
    }

    // Verificar y agregar campo last_webhook_at
    $result = $conn->query("SHOW COLUMNS FROM pedidos_detal LIKE 'last_webhook_at'");
    if ($result->num_rows == 0) {
        $sql_add_webhook_time = "ALTER TABLE pedidos_detal ADD COLUMN last_webhook_at DATETIME NULL AFTER retry_count";
        if ($conn->query($sql_add_webhook_time)) {
            echo "<p style='color: green;'>✅ Campo last_webhook_at agregado a pedidos_detal</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Error agregando last_webhook_at: " . $conn->error . "</p>\n";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Campo last_webhook_at ya existe en pedidos_detal</p>\n";
    }

    // 4. Crear vista para estadísticas de webhooks
    echo "<h3>4. Creando vista de estadísticas...</h3>\n";
    $sql_stats_view = "
    CREATE OR REPLACE VIEW bold_webhook_stats AS
    SELECT 
        DATE(created_at) as fecha,
        event_type,
        status,
        COUNT(*) as total_eventos,
        COUNT(DISTINCT order_id) as ordenes_unicas,
        AVG(processing_time_ms) as tiempo_promedio_ms,
        MIN(created_at) as primer_evento,
        MAX(created_at) as ultimo_evento
    FROM bold_webhook_logs 
    GROUP BY DATE(created_at), event_type, status
    ORDER BY fecha DESC, event_type;
    ";
    
    if ($conn->query($sql_stats_view)) {
        echo "<p style='color: green;'>✅ Vista bold_webhook_stats creada exitosamente</p>\n";
    } else {
        echo "<p style='color: orange;'>⚠️ Error creando vista: " . $conn->error . "</p>\n";
    }

    // 5. Insertar configuración de ejemplo
    echo "<h3>5. Insertando datos de configuración...</h3>\n";
    
    // Verificar que no existan datos de prueba duplicados
    $result = $conn->query("SELECT COUNT(*) as count FROM bold_webhook_logs WHERE webhook_id = 'SETUP_TEST'");
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $sql_test_log = "
        INSERT INTO bold_webhook_logs (
            webhook_id, event_type, order_id, status, message, ip_address, user_agent
        ) VALUES (
            'SETUP_TEST', 'setup.completed', 'SETUP-001', 'success', 
            'Sistema de webhooks mejorado configurado exitosamente', 
            '" . ($_SERVER['REMOTE_ADDR'] ?? 'localhost') . "', 
            '" . ($_SERVER['HTTP_USER_AGENT'] ?? 'Setup Script') . "'
        )";
        
        if ($conn->query($sql_test_log)) {
            echo "<p style='color: green;'>✅ Log de configuración insertado</p>\n";
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Log de configuración ya existe</p>\n";
    }

    echo "<hr>\n";
    echo "<h3>🎉 Configuración Completada</h3>\n";
    echo "<p><strong>Características implementadas:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>🔄 Sistema de retry con cola persistente</li>\n";
    echo "<li>📝 Logging detallado con métricas de rendimiento</li>\n";
    echo "<li>🛡️ Validaciones robustas de datos</li>\n";
    echo "<li>🚫 Detección automática de duplicados</li>\n";
    echo "<li>📊 Estadísticas y monitoreo en tiempo real</li>\n";
    echo "<li>⚡ Procesamiento asíncrono de fallos</li>\n";
    echo "</ul>\n";

    echo "<h4>🔗 Enlaces útiles:</h4>\n";
    echo "<ul>\n";
    echo "<li><a href='bold_webhook_enhanced.php' target='_blank'>🚀 Webhook Mejorado</a></li>\n";
    echo "<li><a href='bold_webhook_monitor.php' target='_blank'>📊 Monitor de Webhooks</a></li>\n";
    echo "<li><a href='bold_retry_processor.php' target='_blank'>🔄 Procesador de Retry</a></li>\n";
    echo "<li><a href='bold_webhook.php' target='_blank'>📎 Webhook Original</a></li>\n";
    echo "</ul>\n";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error durante la configuración: " . $e->getMessage() . "</p>\n";
    error_log("Error en setup_enhanced_webhooks: " . $e->getMessage());
}
?>

<style>
    body { 
        font-family: Arial, sans-serif; 
        max-width: 800px; 
        margin: 20px auto; 
        padding: 20px; 
        background: #f5f5f5; 
    }
    h2, h3 { 
        color: #333; 
        border-bottom: 2px solid #007bff; 
        padding-bottom: 5px; 
    }
    ul { 
        background: white; 
        padding: 15px; 
        border-radius: 5px; 
        box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
    }
    a { 
        color: #007bff; 
        text-decoration: none; 
        font-weight: bold; 
    }
    a:hover { 
        text-decoration: underline; 
    }
</style>
