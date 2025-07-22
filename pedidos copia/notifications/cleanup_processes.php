<?php
/**
 * Script para limpiar procesos SSE colgados
 */

require_once __DIR__ . '/sse_manager.php';

class ProcessCleanup {
    private $sseManager;
    
    public function __construct() {
        $this->sseManager = new SSEManager();
    }
    
    public function cleanup() {
        $status = $this->sseManager->getStatus();
        
        echo "Estado actual:\n";
        echo "Conexiones activas: " . $status['active_connections'] . "\n";
        echo "Máximo permitido: " . $status['max_connections'] . "\n";
        
        if ($status['active_connections'] > 0) {
            echo "\nConexiones detectadas:\n";
            foreach ($status['connections'] as $pid => $info) {
                $duration = time() - $info['start_time'];
                echo "PID: $pid, Duración: {$duration}s\n";
            }
        }
        
        // Limpiar conexiones expiradas
        $this->sseManager->getStatus(); // Esto ejecuta la limpieza automática
        
        echo "\nLimpieza completada.\n";
    }
    
    public function forceCleanup() {
        // Buscar procesos PHP que ejecutan notifications_sse.php
        $processes = shell_exec("ps aux | grep 'notifications_sse.php' | grep -v grep");
        
        if ($processes) {
            echo "Procesos encontrados:\n";
            echo $processes;
            
            // Extraer PIDs
            $lines = explode("\n", trim($processes));
            foreach ($lines as $line) {
                if (preg_match('/\s+(\d+)\s+/', $line, $matches)) {
                    $pid = $matches[1];
                    echo "Terminando proceso PID: $pid\n";
                    
                    // Intentar terminar el proceso graciosamente
                    if (function_exists('posix_kill')) {
                        posix_kill($pid, SIGTERM);
                        sleep(2);
                        
                        // Si no se termina, forzar
                        if (posix_kill($pid, 0)) {
                            posix_kill($pid, SIGKILL);
                        }
                    } else {
                        shell_exec("kill $pid");
                        sleep(2);
                        shell_exec("kill -9 $pid");
                    }
                }
            }
        } else {
            echo "No se encontraron procesos de notifications_sse.php\n";
        }
        
        // Limpiar archivo de bloqueo
        $lockFile = dirname(__DIR__) . '/tmp/sse_connections.lock';
        if (file_exists($lockFile)) {
            unlink($lockFile);
            echo "Archivo de bloqueo eliminado.\n";
        }
    }
}

// Ejecutar limpieza
$cleanup = new ProcessCleanup();

if (isset($argv[1]) && $argv[1] === '--force') {
    echo "Ejecutando limpieza forzada...\n";
    $cleanup->forceCleanup();
} else {
    echo "Ejecutando limpieza normal...\n";
    $cleanup->cleanup();
}