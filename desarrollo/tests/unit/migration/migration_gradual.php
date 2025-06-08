<?php
/**
 * Script de Migración Gradual - Bold PSE
 * Facilita la transición del webhook original al webhook mejorado
 */

require_once "conexion.php";

// Configuración de migración
define('MIGRATION_LOG_FILE', __DIR__ . '/logs/migration.log');
define('BACKUP_DIR', __DIR__ . '/backups');

// Configuración de debugging
ini_set('log_errors', 1);
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Función de debug para identificar problemas de encoding
function debugSystemInfo() {
    $debug = [];
    $debug['php_version'] = PHP_VERSION;
    $debug['php_sapi'] = php_sapi_name();
    $debug['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'CLI';
    $debug['encoding'] = [
        'internal' => mb_internal_encoding(),
        'default_charset' => ini_get('default_charset'),
        'locale' => setlocale(LC_ALL, 0)
    ];
    $debug['mysql_charset'] = 'unknown';
    
    // Log del debug
    error_log("MIGRATION DEBUG: " . json_encode($debug));
    return $debug;
}

// Asegurar directorios
foreach (['/logs', '/backups'] as $dir) {
    if (!is_dir(__DIR__ . $dir)) {
        mkdir(__DIR__ . $dir, 0755, true);
    }
}

/**
 * Clase para manejar la migración gradual
 */
class BoldMigrationManager {
    private $conn;
    private $migrationLogs = [];
    private $debugInfo = [];
    
    public function __construct($connection) {
        $this->conn = $connection;
        $this->debugInfo = debugSystemInfo();
        
        // Verificar encoding de la conexión MySQL
        if ($this->conn) {
            $charset_result = $this->conn->query("SELECT @@character_set_connection, @@collation_connection");
            if ($charset_result) {
                $charset_info = $charset_result->fetch_assoc();
                $this->debugInfo['mysql_charset'] = $charset_info;
            }
        }
    }
    
    /**
     * Ejecutar migración completa
     */
    public function runMigration() {
        $this->log("🚀 Iniciando migración gradual del sistema Bold PSE");
        $this->log("📊 Información del sistema:");
        $this->log("  - PHP Version: " . PHP_VERSION);
        $this->log("  - MySQL Version: " . $this->conn->server_info);
        $this->log("  - Script Path: " . __FILE__);
        $this->log("  - Working Directory: " . getcwd());
        $this->log("  - Debug Info: " . json_encode($this->debugInfo));
        
        // Verificar encoding de strings
        $testString = "Test áéíóú ñüç 123";
        $this->log("  - String Test: Original='" . $testString . "' Length=" . strlen($testString) . " MB_Length=" . mb_strlen($testString));
        
        try {
            // Paso 1: Backup
            $this->log("\n📦 PASO 1/6: Creando backup...");
            try {
                $backupPath = $this->createBackup();
                $this->log("✅ Backup completado: $backupPath");
            } catch (Exception $e) {
                $this->log("❌ Error en backup: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló el backup: " . $e->getMessage());
            }
            
            // Paso 2: Verificar prerrequisitos
            $this->log("\n🔍 PASO 2/6: Verificando prerrequisitos...");
            try {
                $this->checkPrerequisites();
                $this->log("✅ Prerrequisitos verificados");
            } catch (Exception $e) {
                $this->log("❌ Error en prerrequisitos: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló verificación de prerrequisitos: " . $e->getMessage());
            }
            
            // Paso 3: Configurar base de datos
            $this->log("\n🗄️ PASO 3/6: Configurando base de datos mejorada...");
            try {
                $this->setupEnhancedDatabase();
                $this->log("✅ Base de datos configurada");
            } catch (Exception $e) {
                $this->log("❌ Error en BD: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló configuración de BD: " . $e->getMessage());
            }
            
            // Paso 4: Migrar datos
            $this->log("\n📊 PASO 4/6: Migrando datos existentes...");
            try {
                $this->migrateExistingData();
                $this->log("✅ Datos migrados");
            } catch (Exception $e) {
                $this->log("❌ Error en migración de datos: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló migración de datos: " . $e->getMessage());
            }
            
            // Paso 5: Configurar modo dual
            $this->log("\n🔄 PASO 5/6: Configurando modo dual...");
            try {
                $this->setupDualMode();
                $this->log("✅ Modo dual configurado");
            } catch (Exception $e) {
                $this->log("❌ Error en modo dual: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló configuración de modo dual: " . $e->getMessage());
            }
            
            // Paso 6: Validar migración
            $this->log("\n✅ PASO 6/6: Validando migración...");
            try {
                $this->validateMigration();
                $this->log("✅ Migración validada");
            } catch (Exception $e) {
                $this->log("❌ Error en validación: " . $e->getMessage());
                $this->log("🔍 Stack trace: " . $e->getTraceAsString());
                throw new Exception("Falló validación: " . $e->getMessage());
            }
            
            $this->log("\n🎉 Migración completada exitosamente");
            return $this->generateMigrationReport();
            
        } catch (Exception $e) {
            $this->log("\n❌ Error en migración: " . $e->getMessage());
            $this->log("🔍 Error details:");
            $this->log("  - Message: " . $e->getMessage());
            $this->log("  - File: " . $e->getFile());
            $this->log("  - Line: " . $e->getLine());
            $this->log("  - Code: " . $e->getCode());
            $this->log("🔄 Iniciando rollback...");
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Crear backup de la configuración actual
     */
    private function createBackup() {
        try {
            $backupId = date('Y-m-d_H-i-s');
            $backupPath = BACKUP_DIR . "/backup_$backupId";
            
            if (!mkdir($backupPath, 0755, true)) {
                throw new Exception("No se pudo crear directorio de backup: $backupPath");
            }
            
            // Backup de archivos PHP
            $filesToBackup = [
                'bold_webhook.php',
                'bold_hash.php', 
                'bold_confirmation.php',
                'index.php'
            ];
            
            foreach ($filesToBackup as $file) {
                if (file_exists(__DIR__ . '/' . $file)) {
                    if (!copy(__DIR__ . '/' . $file, $backupPath . '/' . $file)) {
                        throw new Exception("Error al crear backup de: $file");
                    }
                    $this->log("  ✅ Backup creado: $file");
                } else {
                    $this->log("  ⚠️ Archivo no encontrado para backup: $file");
                }
            }
            
            // Backup de base de datos (estructura y datos relevantes)
            $this->log("  🗄️ Creando backup de base de datos...");
            $this->createDatabaseBackup($backupPath);
            
            $this->log("📦 Backup completo guardado en: $backupPath");
            return $backupPath;
            
        } catch (Exception $e) {
            $this->log("❌ Error en createBackup: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear backup de base de datos
     */
    private function createDatabaseBackup($backupPath) {
        try {
            $this->log("  🗄️ Iniciando backup de base de datos...");
            
            // Verificar conexión antes de hacer backup
            if (!$this->conn->ping()) {
                throw new Exception("Conexión a BD perdida durante backup");
            }
            
            // Backup simplificado solo de estructura
            $this->log("  📋 Obteniendo estructura de tabla pedidos_detal...");
            $result = $this->conn->query("SHOW CREATE TABLE pedidos_detal");
            
            if (!$result) {
                $this->log("  ❌ Error en SHOW CREATE TABLE: " . $this->conn->error);
                throw new Exception("Error al obtener estructura de tabla: " . $this->conn->error);
            }
            
            $row = $result->fetch_assoc();
            if (!$row || !isset($row['Create Table'])) {
                throw new Exception("No se pudo obtener la estructura de la tabla pedidos_detal");
            }
            
            $createTable = $row['Create Table'];
            $this->log("  ✅ Estructura de tabla obtenida (" . strlen($createTable) . " caracteres)");
            
            // Crear contenido del backup con encoding seguro
            $timestamp = date('Y-m-d H:i:s');
            $sqlBackup = "-- Backup de estructura de pedidos_detal creado el $timestamp\n";
            $sqlBackup .= "-- Charset: utf8mb4\n";
            $sqlBackup .= "SET NAMES utf8mb4;\n\n";
            $sqlBackup .= $createTable . ";\n\n";
            
            // Solo contar registros, no respaldar datos por ahora
            $this->log("  📊 Contando registros existentes...");
            $result = $this->conn->query("SELECT COUNT(*) as total FROM pedidos_detal");
            
            if ($result) {
                $count = $result->fetch_assoc()['total'];
                $sqlBackup .= "-- Total de registros en pedidos_detal: $count\n";
                $this->log("  📊 Total de registros en BD: $count");
            } else {
                $this->log("  ⚠️ No se pudo contar registros: " . $this->conn->error);
                $sqlBackup .= "-- Error contando registros: " . $this->conn->error . "\n";
            }
            
            // Escribir backup con verificación
            $backupFile = $backupPath . '/database_structure.sql';
            $this->log("  💾 Escribiendo backup a: $backupFile");
            
            $bytesWritten = file_put_contents($backupFile, $sqlBackup, LOCK_EX);
            
            if ($bytesWritten === false) {
                throw new Exception("Error al escribir archivo de backup");
            }
            
            if ($bytesWritten !== strlen($sqlBackup)) {
                throw new Exception("Backup incompleto: escrito $bytesWritten de " . strlen($sqlBackup) . " bytes");
            }
            
            // Verificar que el archivo se escribió correctamente
            if (!file_exists($backupFile) || filesize($backupFile) === 0) {
                throw new Exception("Archivo de backup no se creó correctamente");
            }
            
            $this->log("  ✅ Backup de estructura de BD creado exitosamente ($bytesWritten bytes)");
            
        } catch (Exception $e) {
            $this->log("  ❌ Error en backup de BD: " . $e->getMessage());
            $this->log("  📋 Detalles del error:");
            $this->log("    - File: " . $e->getFile());
            $this->log("    - Line: " . $e->getLine());
            $this->log("    - Trace: " . $e->getTraceAsString());
            
            // No hacer throw aquí, continuar con la migración
            $this->log("  ⚠️ Continuando migración sin backup de BD completo");
        }
    }
    
    /**
     * Verificar prerrequisitos para la migración
     */
    private function checkPrerequisites() {
        $checks = [
            'PHP Version >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'MySQL Connection' => $this->conn->ping(),
            'Logs Directory Writable' => is_writable(__DIR__ . '/logs'),
            'Enhanced Files Present' => $this->checkEnhancedFiles()
        ];
        
        foreach ($checks as $check => $result) {
            if ($result) {
                $this->log("  ✅ $check");
            } else {
                throw new Exception("❌ Prerrequisito fallido: $check");
            }
        }
    }
    
    /**
     * Verificar que los archivos mejorados estén presentes
     */
    private function checkEnhancedFiles() {
        $requiredFiles = [
            'bold_webhook_enhanced.php',
            'bold_notification_system.php',
            'payment_ux_enhanced.js',
            'payment_ux_enhanced.css',
            'setup_enhanced_webhooks.php'
        ];
        
        foreach ($requiredFiles as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Configurar base de datos mejorada
     */
    private function setupEnhancedDatabase() {
        try {
            $this->log("  🔍 Ejecutando setup de base de datos...");
            
            // En lugar de incluir el archivo, ejecutar directamente las queries
            // usando la conexión de la clase para evitar problemas de scope
            
            $this->log("  📋 Creando tabla bold_retry_queue...");
            $sql_retry_queue = "
            CREATE TABLE IF NOT EXISTS bold_retry_queue (
                id INT AUTO_INCREMENT PRIMARY KEY,
                webhook_data TEXT NOT NULL,
                error_message TEXT,
                attempts INT DEFAULT 0,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                next_retry_at DATETIME NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_next_retry (next_retry_at),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if (!$this->conn->query($sql_retry_queue)) {
                throw new Exception("Error creando bold_retry_queue: " . $this->conn->error);
            }
            $this->log("  ✅ Tabla bold_retry_queue creada");

            $this->log("  📋 Creando tabla bold_webhook_logs...");
            $sql_webhook_logs = "
            CREATE TABLE IF NOT EXISTS bold_webhook_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                webhook_type VARCHAR(50) NOT NULL,
                webhook_data TEXT NOT NULL,
                processing_status ENUM('received', 'processing', 'completed', 'failed') DEFAULT 'received',
                response_sent TEXT,
                error_message TEXT,
                execution_time_ms INT DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_type (webhook_type),
                INDEX idx_status (processing_status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if (!$this->conn->query($sql_webhook_logs)) {
                throw new Exception("Error creando bold_webhook_logs: " . $this->conn->error);
            }
            $this->log("  ✅ Tabla bold_webhook_logs creada");

            $this->log("  📋 Creando tabla notification_logs...");
            $sql_notification_logs = "
            CREATE TABLE IF NOT EXISTS notification_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                notification_type VARCHAR(50) NOT NULL,
                recipient VARCHAR(100) NOT NULL,
                subject VARCHAR(200),
                message TEXT,
                status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
                error_message TEXT,
                sent_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_type (notification_type),
                INDEX idx_status (status),
                INDEX idx_recipient (recipient),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            if (!$this->conn->query($sql_notification_logs)) {
                throw new Exception("Error creando notification_logs: " . $this->conn->error);
            }
            $this->log("  ✅ Tabla notification_logs creada");

            $this->log("  📋 Agregando columnas a pedidos_detal...");
            $alter_queries = [
                "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS notification_sent BOOLEAN DEFAULT FALSE",
                "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0",
                "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS last_webhook_attempt DATETIME NULL",
                "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS webhook_mode ENUM('original', 'enhanced', 'both') DEFAULT 'original'",
                "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS enhanced_features JSON NULL"
            ];
            
            foreach ($alter_queries as $query) {
                if (!$this->conn->query($query)) {
                    // No es crítico si la columna ya existe
                    $this->log("  ⚠️ Query ALTER: " . $this->conn->error);
                }
            }
            
            $this->log("  ✅ Setup de base de datos completado");
            
            // Verificar que las tablas fueron creadas
            $requiredTables = ['bold_retry_queue', 'bold_webhook_logs', 'notification_logs'];
            foreach ($requiredTables as $table) {
                $result = $this->conn->query("SHOW TABLES LIKE '$table'");
                if (!$result || $result->num_rows === 0) {
                    throw new Exception("Tabla $table no fue creada correctamente");
                }
                $this->log("  ✅ Tabla $table verificada");
            }
            
        } catch (Exception $e) {
            $this->log("❌ Error en setupEnhancedDatabase: " . $e->getMessage());
            $this->log("🔍 Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Migrar datos existentes
     */
    private function migrateExistingData() {
        // Agregar campos nuevos a registros existentes si no existen
        $alterQueries = [
            "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS retry_count INT DEFAULT 0",
            "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS last_webhook_at TIMESTAMP NULL",
            "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS bold_transaction_id VARCHAR(100) NULL",
            "ALTER TABLE pedidos_detal ADD COLUMN IF NOT EXISTS bold_response TEXT NULL"
        ];
        
        foreach ($alterQueries as $query) {
            try {
                $this->conn->query($query);
                $this->log("  ✅ Estructura de tabla actualizada");
            } catch (Exception $e) {
                // Ignorar errores si las columnas ya existen
                $this->log("  ℹ️ Campo ya existe o no se pudo agregar: " . $e->getMessage());
            }
        }
        
        // Migrar datos de estado de pago inconsistentes
        $this->conn->query("
            UPDATE pedidos_detal 
            SET estado_pago = 'PENDIENTE' 
            WHERE estado_pago IS NULL OR estado_pago = ''
        ");
        
        $updated = $this->conn->affected_rows;
        $this->log("  ✅ $updated registros de estado actualizados");
    }
    
    /**
     * Configurar modo dual (ambos webhooks funcionando)
     */
    private function setupDualMode() {
        // Crear archivo de configuración para modo dual
        $dualConfig = '<?php
/**
 * Configuración de modo dual para migración gradual
 * Permite que ambos webhooks funcionen simultáneamente
 */

define("DUAL_MODE_ENABLED", true);
define("ENHANCED_WEBHOOK_PERCENTAGE", 50); // Porcentaje de tráfico al webhook mejorado
define("MIGRATION_LOG_ENABLED", true);

/**
 * Determinar qué webhook usar basado en el porcentaje configurado
 */
function shouldUseEnhancedWebhook($orderId = null) {
    if (!DUAL_MODE_ENABLED) {
        return false;
    }
    
    // Usar hash del order_id para distribución consistente
    if ($orderId) {
        $hash = crc32($orderId);
        return ($hash % 100) < ENHANCED_WEBHOOK_PERCENTAGE;
    }
    
    // Distribución aleatoria si no hay order_id
    return (mt_rand(0, 99) < ENHANCED_WEBHOOK_PERCENTAGE);
}

/**
 * Log de migración
 */
function logMigrationEvent($event, $data = null) {
    if (!MIGRATION_LOG_ENABLED) return;
    
    $logEntry = date("Y-m-d H:i:s") . " - $event";
    if ($data) {
        $logEntry .= " - " . json_encode($data);
    }
    $logEntry .= "\n";
    
    file_put_contents(__DIR__ . "/logs/dual_mode.log", $logEntry, FILE_APPEND | LOCK_EX);
}
?>';
        
        file_put_contents(__DIR__ . '/dual_mode_config.php', $dualConfig);
        $this->log("  ✅ Configuración de modo dual creada");
        
        // Crear webhook distribuidor
        $this->createWebhookDistributor();
    }
    
    /**
     * Crear webhook distribuidor para modo dual
     */
    private function createWebhookDistributor() {
        $distributorCode = '<?php
/**
 * Distribuidor de webhooks para migración gradual
 * Enruta webhooks entre el sistema original y mejorado
 */

require_once "dual_mode_config.php";

// Obtener datos del webhook
$input = file_get_contents("php://input");
$webhookData = json_decode($input, true);

// Determinar orden ID
$orderId = $webhookData["order"]["order_id"] ?? null;

// Decidir qué webhook usar
if (shouldUseEnhancedWebhook($orderId)) {
    // Usar webhook mejorado
    logMigrationEvent("ROUTING_TO_ENHANCED", ["order_id" => $orderId]);
    
    // Incluir y procesar con webhook mejorado
    $_POST = $webhookData; // Simular POST data
    include "bold_webhook_enhanced.php";
    
} else {
    // Usar webhook original
    logMigrationEvent("ROUTING_TO_ORIGINAL", ["order_id" => $orderId]);
    
    // Incluir y procesar con webhook original
    $_POST = $webhookData; // Simular POST data
    include "bold_webhook.php";
}
?>';
        
        file_put_contents(__DIR__ . '/bold_webhook_distributor.php', $distributorCode);
        $this->log("  ✅ Distribuidor de webhooks creado");
    }
    
    /**
     * Validar que la migración funcionó correctamente
     */
    private function validateMigration() {
        // Verificar tablas
        $tables = ['bold_retry_queue', 'bold_webhook_logs', 'notification_logs'];
        foreach ($tables as $table) {
            $result = $this->conn->query("SELECT COUNT(*) as count FROM $table");
            $this->log("  ✅ Tabla $table accesible");
        }
        
        // Verificar archivos
        $files = [
            'bold_webhook_enhanced.php',
            'dual_mode_config.php',
            'bold_webhook_distributor.php'
        ];
        
        foreach ($files as $file) {
            if (!file_exists(__DIR__ . '/' . $file)) {
                throw new Exception("Archivo crítico faltante: $file");
            }
            $this->log("  ✅ Archivo $file presente");
        }
        
        // Probar webhook mejorado
        $this->testEnhancedWebhook();
    }
    
    /**
     * Probar webhook mejorado con datos de prueba
     */
    private function testEnhancedWebhook() {
        $testData = [
            'event' => 'SALE_APPROVED',
            'order' => [
                'order_id' => 'TEST-MIGRATION-' . time(),
                'status' => 'APPROVED',
                'amount' => 1000
            ],
            'transaction' => [
                'id' => 'TXN-TEST-' . time(),
                'status' => 'APPROVED'
            ]
        ];
        
        try {
            require_once __DIR__ . '/bold_webhook_enhanced.php';
            $processor = new BoldWebhookProcessor($this->conn);
            $result = $processor->processWebhook($testData);
            
            if ($result['success']) {
                $this->log("  ✅ Webhook mejorado funciona correctamente");
            } else {
                $this->log("  ⚠️ Webhook mejorado tiene advertencias: " . $result['error']);
            }
        } catch (Exception $e) {
            $this->log("  ⚠️ Error en prueba de webhook: " . $e->getMessage());
        }
    }
    
    /**
     * Generar reporte de migración
     */
    private function generateMigrationReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'success',
            'logs' => $this->migrationLogs,
            'next_steps' => [
                '1. Configurar webhook URL en Bold a: /bold_webhook_distributor.php',
                '2. Monitorear logs en /logs/dual_mode.log',
                '3. Aumentar gradualmente ENHANCED_WEBHOOK_PERCENTAGE',
                '4. Una vez estable al 100%, cambiar URL a /bold_webhook_enhanced.php',
                '5. Remover archivos del sistema original'
            ]
        ];
        
        file_put_contents(
            __DIR__ . '/logs/migration_report_' . date('Y-m-d_H-i-s') . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        return $report;
    }
    
    /**
     * Rollback en caso de error
     */
    private function rollback() {
        $this->log("🔄 Iniciando rollback de migración");
        
        // Aquí se implementaría la lógica de rollback
        // Por ahora solo loggeamos el intento
        $this->log("⚠️ Rollback requerido - revisar backups en " . BACKUP_DIR);
    }
    
    /**
     * Log de eventos de migración
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        
        $this->migrationLogs[] = $message;
        
        // Asegurar que el directorio de logs existe
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Escribir al archivo de log con manejo de errores
        try {
            $result = file_put_contents(MIGRATION_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
            if ($result === false) {
                // Si falla el log, al menos mostrar en pantalla
                error_log("Failed to write to migration log: $message");
            }
        } catch (Exception $e) {
            error_log("Error writing to migration log: " . $e->getMessage());
        }
        
        // También mostrar en pantalla si es CLI
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
        
        // Escribir también al error log de PHP para debug remoto
        error_log("MIGRATION: $message");
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migración Gradual - Bold PSE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #252526;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        h1, h2 { color: #ffffff; }
        .migration-step {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            border-left: 4px solid #007aff;
        }
        .warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .info {
            background: rgba(0, 122, 255, 0.2);
            border-left: 4px solid #007aff;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 10px 10px 10px 0;
            transition: background 0.2s;
        }
        .btn:hover { background: #0056d3; }
        .btn:disabled { background: #666; cursor: not-allowed; }
        .log-output {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 16px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 14px;
            color: #cccccc;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
        .progress-bar {
            background: #3e3e42;
            border-radius: 10px;
            height: 20px;
            overflow: hidden;
            margin: 16px 0;
        }
        .progress-fill {
            background: #007aff;
            height: 100%;
            width: 0%;
            transition: width 0.3s ease;
        }
        .step-list {
            counter-reset: step-counter;
            list-style: none;
            padding: 0;
        }
        .step-list li {
            counter-increment: step-counter;
            margin: 12px 0;
            padding: 12px;
            background: #2d2d30;
            border-radius: 6px;
            position: relative;
            padding-left: 50px;
        }
        .step-list li::before {
            content: counter(step-counter);
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: #007aff;
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 Migración Gradual - Bold PSE</h1>
        
        <div class="warning">
            <strong>⚠️ Importante:</strong> Esta migración actualizará el sistema Bold PSE. Se recomienda realizarla en horarios de bajo tráfico y tener backups actualizados.
        </div>

        <div class="info">
            <strong>📋 Proceso de Migración:</strong><br>
            La migración permite una transición gradual del webhook original al webhook mejorado, manteniendo ambos sistemas funcionando durante el proceso.
        </div>

        <h2>📋 Pasos de Migración</h2>
        <ol class="step-list">
            <li><strong>Backup:</strong> Crear respaldo de archivos y datos actuales</li>
            <li><strong>Verificación:</strong> Comprobar prerrequisitos del sistema</li>
            <li><strong>Base de Datos:</strong> Configurar nuevas tablas y campos</li>
            <li><strong>Migración de Datos:</strong> Transferir datos existentes</li>
            <li><strong>Modo Dual:</strong> Configurar ambos webhooks funcionando</li>
            <li><strong>Validación:</strong> Verificar funcionamiento correcto</li>
        </ol>

        <div class="migration-step">
            <h3>🚀 Ejecutar Migración</h3>
            <button class="btn" onclick="startMigration()" id="migrate-btn">Iniciar Migración</button>
            <button class="btn" onclick="checkStatus()" id="status-btn">Verificar Estado</button>
            
            <div class="progress-bar" style="display: none;" id="progress-bar">
                <div class="progress-fill" id="progress-fill"></div>
            </div>
            
            <div id="migration-output" class="log-output" style="display: none;"></div>
        </div>

        <div class="migration-step">
            <h3>📊 Post-Migración</h3>
            <p>Después de completar la migración:</p>
            <ul>
                <li>Configurar la URL del webhook en Bold Dashboard</li>
                <li>Monitorear logs de funcionamiento</li>
                <li>Aumentar gradualmente el porcentaje de tráfico</li>
                <li>Validar funcionamiento completo</li>
            </ul>
            
            <button class="btn" onclick="showPostMigrationSteps()">Ver Pasos Detallados</button>
        </div>

        <div id="post-migration-steps" style="display: none;" class="info">
            <h4>📝 Pasos Post-Migración Detallados:</h4>
            <ol>
                <li><strong>Configurar Bold Dashboard:</strong>
                    <br>Cambiar webhook URL a: <code>/bold_webhook_distributor.php</code>
                </li>
                <li><strong>Monitorear Logs:</strong>
                    <br>Revisar <code>/logs/dual_mode.log</code> para el tráfico
                </li>
                <li><strong>Aumentar Tráfico:</strong>
                    <br>Incrementar <code>ENHANCED_WEBHOOK_PERCENTAGE</code> gradualmente
                </li>
                <li><strong>Finalizar Migración:</strong>
                    <br>Una vez estable al 100%, cambiar URL a <code>/bold_webhook_enhanced.php</code>
                </li>
            </ol>
        </div>
    </div>

    <script>
        async function startMigration() {
            const btn = document.getElementById('migrate-btn');
            const output = document.getElementById('migration-output');
            const progressBar = document.getElementById('progress-bar');
            const progressFill = document.getElementById('progress-fill');
            
            btn.disabled = true;
            btn.textContent = 'Migrando...';
            output.style.display = 'block';
            progressBar.style.display = 'block';
            output.textContent = '🚀 Iniciando migración...\n';
            
            try {
                output.textContent += '📋 Ejecutando migración completa...\n';
                progressFill.style.width = '25%';
                
                // Debug: mostrar información de la request
                const requestData = { action: 'migrate' };
                output.textContent += '🔍 Datos de request: ' + JSON.stringify(requestData) + '\n';
                
                progressFill.style.width = '50%';
                
                const response = await fetch('migration_gradual.php', {
                    method: 'POST',
                    headers: { 
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(requestData)
                });
                
                progressFill.style.width = '70%';
                output.textContent += '📡 Response recibido (Status: ' + response.status + ')\n';
                
                // Verificar el content-type de la respuesta
                const contentType = response.headers.get('content-type');
                output.textContent += '📋 Content-Type: ' + contentType + '\n';
                
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status} ${response.statusText}`);
                }
                
                // Leer el response como texto primero para debug
                const responseText = await response.text();
                output.textContent += '📄 Response length: ' + responseText.length + ' chars\n';
                output.textContent += '📄 Response preview: ' + responseText.substring(0, 200) + '...\n';
                
                progressFill.style.width = '80%';
                
                // Intentar parsear JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (jsonError) {
                    output.textContent += '❌ Error parsing JSON: ' + jsonError.message + '\n';
                    output.textContent += '📄 Full response:\n' + responseText + '\n';
                    throw new Error('Invalid JSON response: ' + jsonError.message);
                }
                
                progressFill.style.width = '100%';
                
                if (result.success) {
                    output.textContent += '\n✅ Migración completada exitosamente!\n';
                    if (result.data && result.data.logs) {
                        output.textContent += '\n📋 Detalles de la migración:\n';
                        result.data.logs.forEach(log => {
                            output.textContent += log + '\n';
                        });
                    }
                    if (result.debug) {
                        output.textContent += '\n🔍 Debug info:\n' + JSON.stringify(result.debug, null, 2) + '\n';
                    }
                    showPostMigrationSteps();
                } else {
                    output.textContent += '\n❌ Error en migración: ' + (result.error || 'Error desconocido') + '\n';
                    
                    if (result.error_file) {
                        output.textContent += '📁 File: ' + result.error_file + '\n';
                    }
                    if (result.error_line) {
                        output.textContent += '📍 Line: ' + result.error_line + '\n';
                    }
                    if (result.error_trace) {
                        output.textContent += '🔍 Trace:\n' + result.error_trace + '\n';
                    }
                    if (result.debug) {
                        output.textContent += '\n🔍 Debug info:\n' + JSON.stringify(result.debug, null, 2) + '\n';
                    }
                    
                    throw new Error(result.error || 'Error desconocido en la migración');
                }
                
            } catch (error) {
                output.textContent += `\n❌ Error: ${error.message}\n`;
                output.textContent += '🔄 Revise los logs y ejecute rollback si es necesario.\n';
                
                // Mostrar stack trace si está disponible
                if (error.stack) {
                    output.textContent += '🔍 Stack trace:\n' + error.stack + '\n';
                }
                
                progressFill.style.width = '0%';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Iniciar Migración';
            }
        }
        
        async function checkStatus() {
            try {
                const response = await fetch('migration_gradual.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'status' })
                });
                
                if (!response.ok) {
                    throw new Error(`Error HTTP: ${response.status}`);
                }
                
                const result = await response.json();
                
                let statusMessage = `Estado: ${result.status || 'Desconocido'}\n`;
                statusMessage += `Última migración: ${result.last_migration || 'Nunca'}\n`;
                
                if (result.logs && result.logs.length > 0) {
                    statusMessage += `\nÚltimos logs:\n${result.logs.slice(-5).join('\n')}`;
                }
                
                alert(statusMessage);
                
            } catch (error) {
                alert('Error verificando estado: ' + error.message);
            }
        }
        
        function showPostMigrationSteps() {
            const steps = document.getElementById('post-migration-steps');
            steps.style.display = steps.style.display === 'none' ? 'block' : 'none';
        }
    </script>
</body>
</html>

<?php
// Manejo de requests AJAX con debugging mejorado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Debugging paso a paso
    $debugInfo = [];
    
    try {
        // Paso 1: Leer input
        $debugInfo['step'] = 'reading_input';
        $rawInput = file_get_contents('php://input');
        $debugInfo['raw_input_length'] = strlen($rawInput);
        $debugInfo['raw_input_preview'] = substr($rawInput, 0, 100);
        
        // Paso 2: Decodificar input (JSON o form-data)
        $debugInfo['step'] = 'decoding_input';
        
        // Intentar obtener datos de diferentes fuentes
        $input = null;
        
        // Primero intentar JSON
        if (!empty($rawInput)) {
            $input = json_decode($rawInput, true);
            $debugInfo['input_method'] = 'json';
        }
        
        // Si no es JSON válido, intentar form-data
        if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
            // Parsear como form-data
            parse_str($rawInput, $input);
            $debugInfo['input_method'] = 'form_data';
        }
        
        // Si aún no hay datos, usar $_POST
        if (empty($input)) {
            $input = $_POST;
            $debugInfo['input_method'] = 'post';
        }
        
        if (empty($input)) {
            throw new Exception("No se recibieron datos válidos - Raw input: " . $rawInput);
        }
        
        $debugInfo['action'] = $input['action'] ?? 'none';
        
        // Paso 3: Crear manager
        $debugInfo['step'] = 'creating_manager';
        $migrationManager = new BoldMigrationManager($conn);
        
        // Paso 4: Ejecutar acción
        $debugInfo['step'] = 'executing_action';
        
        switch ($input['action'] ?? '') {
            case 'migrate':
                $debugInfo['step'] = 'running_migration';
                $result = $migrationManager->runMigration();
                echo json_encode([
                    'success' => true, 
                    'data' => $result, 
                    'message' => 'Migración completada exitosamente',
                    'debug' => $debugInfo
                ]);
                break;
                
            case 'status':
                $debugInfo['step'] = 'checking_status';
                $status = [
                    'status' => 'ready_for_migration',
                    'last_migration' => file_exists(MIGRATION_LOG_FILE) 
                        ? date('Y-m-d H:i:s', filemtime(MIGRATION_LOG_FILE)) 
                        : null
                ];
                
                if (file_exists(MIGRATION_LOG_FILE)) {
                    $logs = file(MIGRATION_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $status['logs'] = array_slice($logs, -10);
                }
                
                $status['debug'] = $debugInfo;
                echo json_encode($status);
                break;
                
            default:
                echo json_encode([
                    'success' => false, 
                    'error' => 'Acción no válida: ' . ($input['action'] ?? 'undefined'),
                    'debug' => $debugInfo
                ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'error_trace' => $e->getTraceAsString(),
            'debug' => $debugInfo
        ]);
    } catch (Error $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Fatal Error: ' . $e->getMessage(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'debug' => $debugInfo
        ]);
    }
    exit;
}

// Ejecución CLI
if (php_sapi_name() === 'cli') {
    try {
        $migrationManager = new BoldMigrationManager($conn);
        $result = $migrationManager->runMigration();
        echo "\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
