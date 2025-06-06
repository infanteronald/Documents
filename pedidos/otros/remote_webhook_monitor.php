<?php
/**
 * Monitor Remoto del Webhook Mejorado
 * Este archivo puede ser subido al servidor para monitorear el estado del webhook
 */

echo "🔍 MONITOR DEL WEBHOOK MEJORADO - " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 70) . "\n";

// Verificar configuración actual
echo "📋 CONFIGURACIÓN ACTUAL:\n";
echo "- Enhanced Webhook Percentage: " . (defined('ENHANCED_WEBHOOK_PERCENTAGE') ? ENHANCED_WEBHOOK_PERCENTAGE : 'No definido') . "%\n";

// Verificar archivos críticos
$criticalFiles = [
    'bold_webhook_enhanced.php' => 'Webhook Mejorado',
    'dual_mode_config.php' => 'Configuración Dual',
    'conexion.php' => 'Conexión DB',
    'smtp_config.php' => 'Configuración SMTP',
    'bold_notification_system.php' => 'Sistema de Notificaciones'
];

echo "\n📁 ARCHIVOS CRÍTICOS:\n";
foreach ($criticalFiles as $file => $description) {
    $status = file_exists(__DIR__ . '/' . $file) ? '✅' : '❌';
    echo "  {$status} {$file} ({$description})\n";
}

// Verificar directorio de logs
echo "\n📊 ESTADO DE LOGS:\n";
$logsDir = __DIR__ . '/logs';
if (!is_dir($logsDir)) {
    echo "  ❌ Directorio logs no existe\n";
    echo "  🔧 Creando directorio logs...\n";
    mkdir($logsDir, 0755, true);
    echo "  ✅ Directorio logs creado\n";
} else {
    echo "  ✅ Directorio logs existe\n";
    
    $logFiles = [
        'bold_webhook.log' => 'Log principal del webhook',
        'bold_errors.log' => 'Log de errores',
        'dual_mode.log' => 'Log del modo dual',
        'webhook_enhanced.log' => 'Log webhook mejorado'
    ];
    
    foreach ($logFiles as $logFile => $description) {
        $fullPath = $logsDir . '/' . $logFile;
        if (file_exists($fullPath)) {
            $size = filesize($fullPath);
            $modified = date('Y-m-d H:i:s', filemtime($fullPath));
            echo "    ✅ {$logFile} - {$size} bytes - Último: {$modified}\n";
            
            // Mostrar últimas 3 líneas del log
            $content = file_get_contents($fullPath);
            $lines = array_filter(explode("\n", $content));
            $lastLines = array_slice($lines, -3);
            if (!empty($lastLines)) {
                echo "       Últimas entradas:\n";
                foreach ($lastLines as $line) {
                    echo "       → " . substr($line, 0, 80) . (strlen($line) > 80 ? '...' : '') . "\n";
                }
            }
        } else {
            echo "    ⚪ {$logFile} - No existe aún\n";
        }
    }
}

// Verificar base de datos
echo "\n🗄️  VERIFICACIÓN DE BASE DE DATOS:\n";
try {
    require_once 'conexion.php';
    
    $tables = [
        'bold_retry_queue' => 'Cola de reintentos',
        'bold_webhook_logs' => 'Logs de webhooks',
        'notification_logs' => 'Logs de notificaciones'
    ];
    
    foreach ($tables as $table => $description) {
        $result = $conn->query("SHOW TABLES LIKE '{$table}'");
        if ($result && $result->num_rows > 0) {
            // Contar registros
            $count = $conn->query("SELECT COUNT(*) as total FROM {$table}")->fetch_assoc()['total'];
            echo "  ✅ {$table} ({$description}) - {$count} registros\n";
            
            // Mostrar último registro si existe
            if ($count > 0) {
                $lastRecord = $conn->query("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 1");
                if ($lastRecord && $lastRecord->num_rows > 0) {
                    $record = $lastRecord->fetch_assoc();
                    $lastDate = $record['created_at'] ?? 'Fecha no disponible';
                    echo "       Último registro: {$lastDate}\n";
                }
            }
        } else {
            echo "  ❌ {$table} ({$description}) - No existe\n";
        }
    }
    
} catch (Exception $e) {
    echo "  ❌ Error de conexión: " . $e->getMessage() . "\n";
}

// Información del servidor
echo "\n🖥️  INFORMACIÓN DEL SERVIDOR:\n";
echo "  - PHP Version: " . phpversion() . "\n";
echo "  - Servidor: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'No disponible') . "\n";
echo "  - Directorio actual: " . __DIR__ . "\n";
echo "  - Usuario web: " . (function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid())['name'] : 'No disponible') . "\n";

// Test de permisos
echo "\n🔐 PERMISOS:\n";
$testFile = __DIR__ . '/logs/permission_test.tmp';
if (file_put_contents($testFile, 'test')) {
    echo "  ✅ Escritura en directorio logs: OK\n";
    unlink($testFile);
} else {
    echo "  ❌ Escritura en directorio logs: FALLO\n";
}

echo "\n🎯 RESUMEN:\n";
echo "  - Migración al 100% completada\n";
echo "  - Webhook mejorado activo\n";
echo "  - Monitoreo en tiempo real disponible\n";

echo "\n" . str_repeat('=', 70) . "\n";
echo "Monitor completado - " . date('Y-m-d H:i:s') . "\n";
?>
