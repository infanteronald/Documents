<?php
/**
 * Script de monitoreo para procesos SSE
 */

require_once __DIR__ . '/notifications/sse_manager.php';

echo "=== Monitor de Sistema de Notificaciones SSE ===\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar estado del gestor SSE
$sseManager = new SSEManager();
$status = $sseManager->getStatus();

echo "Estado del Sistema:\n";
echo "- Conexiones activas: " . $status['active_connections'] . "\n";
echo "- Límite máximo: " . $status['max_connections'] . "\n";
echo "- Estado: " . ($status['active_connections'] <= $status['max_connections'] ? "NORMAL" : "SOBRECARGADO") . "\n\n";

// Verificar si SSE está deshabilitado
$disableFile = __DIR__ . '/tmp/sse_disabled.flag';
if (file_exists($disableFile)) {
    $data = json_decode(file_get_contents($disableFile), true);
    echo "⚠️  ADVERTENCIA: SSE está DESHABILITADO\n";
    echo "   Deshabilitado desde: " . $data['disabled_at'] . "\n";
    echo "   Razón: " . $data['reason'] . "\n\n";
} else {
    echo "✅ SSE está habilitado\n\n";
}

// Mostrar conexiones activas
if ($status['active_connections'] > 0) {
    echo "Conexiones Activas:\n";
    foreach ($status['connections'] as $pid => $info) {
        $duration = time() - $info['start_time'];
        $lastHeartbeat = time() - $info['last_heartbeat'];
        
        echo "  PID: $pid\n";
        echo "    Duración: {$duration}s\n";
        echo "    Último heartbeat: {$lastHeartbeat}s atrás\n";
        echo "    Estado: " . ($lastHeartbeat > 60 ? "POSIBLEMENTE COLGADO" : "NORMAL") . "\n";
        echo "\n";
    }
}

// Verificar procesos del sistema
echo "Procesos del Sistema:\n";
$processes = shell_exec("ps aux | grep 'notifications_sse.php' | grep -v grep");
if ($processes) {
    $lines = explode("\n", trim($processes));
    echo "  Procesos encontrados: " . count($lines) . "\n";
    
    foreach ($lines as $line) {
        if (preg_match('/\s+(\d+)\s+[\d.]+\s+[\d.]+/', $line, $matches)) {
            $pid = $matches[1];
            echo "    PID: $pid\n";
        }
    }
} else {
    echo "  No se encontraron procesos activos\n";
}

echo "\n=== Comandos Disponibles ===\n";
echo "Deshabilitar SSE: php notifications/disable_sse.php disable\n";
echo "Habilitar SSE: php notifications/disable_sse.php enable\n";
echo "Limpiar procesos: php notifications/cleanup_processes.php\n";
echo "Limpieza forzada: php notifications/cleanup_processes.php --force\n";
echo "Ver este monitor: php monitor_sse.php\n";
?>