<?php
/**
 * Gestor de conexiones SSE para prevenir procesos múltiples
 */

class SSEManager {
    private $lockFile;
    private $maxConnections = 3;
    private $lockTimeout = 300; // 5 minutos
    
    public function __construct() {
        $this->lockFile = dirname(__DIR__) . '/tmp/sse_connections.lock';
        $this->ensureTmpDir();
    }
    
    private function ensureTmpDir() {
        $tmpDir = dirname($this->lockFile);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }
    }
    
    public function canCreateConnection() {
        $this->cleanupExpiredConnections();
        $currentConnections = $this->getCurrentConnections();
        
        return count($currentConnections) < $this->maxConnections;
    }
    
    public function registerConnection($pid = null) {
        if ($pid === null) {
            $pid = getmypid();
        }
        
        $connections = $this->getCurrentConnections();
        $connections[$pid] = [
            'start_time' => time(),
            'last_heartbeat' => time()
        ];
        
        return $this->saveConnections($connections);
    }
    
    public function updateHeartbeat($pid = null) {
        if ($pid === null) {
            $pid = getmypid();
        }
        
        $connections = $this->getCurrentConnections();
        if (isset($connections[$pid])) {
            $connections[$pid]['last_heartbeat'] = time();
            $this->saveConnections($connections);
        }
    }
    
    public function unregisterConnection($pid = null) {
        if ($pid === null) {
            $pid = getmypid();
        }
        
        $connections = $this->getCurrentConnections();
        unset($connections[$pid]);
        
        return $this->saveConnections($connections);
    }
    
    private function getCurrentConnections() {
        if (!file_exists($this->lockFile)) {
            return [];
        }
        
        $content = file_get_contents($this->lockFile);
        if (!$content) {
            return [];
        }
        
        return json_decode($content, true) ?: [];
    }
    
    private function saveConnections($connections) {
        return file_put_contents($this->lockFile, json_encode($connections, JSON_PRETTY_PRINT));
    }
    
    private function cleanupExpiredConnections() {
        $connections = $this->getCurrentConnections();
        $now = time();
        $cleaned = false;
        
        foreach ($connections as $pid => $info) {
            // Remover conexiones que han excedido el timeout
            if (($now - $info['start_time']) > $this->lockTimeout) {
                unset($connections[$pid]);
                $cleaned = true;
                continue;
            }
            
            // Verificar si el proceso aún existe
            if (!$this->isProcessRunning($pid)) {
                unset($connections[$pid]);
                $cleaned = true;
            }
        }
        
        if ($cleaned) {
            $this->saveConnections($connections);
        }
    }
    
    private function isProcessRunning($pid) {
        if (!is_numeric($pid) || $pid <= 0) {
            return false;
        }
        
        // En sistemas Unix/Linux
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        
        // Método alternativo
        $result = shell_exec("ps -p $pid");
        return (strpos($result, $pid) !== false);
    }
    
    public function getStatus() {
        $connections = $this->getCurrentConnections();
        $this->cleanupExpiredConnections();
        
        return [
            'active_connections' => count($connections),
            'max_connections' => $this->maxConnections,
            'connections' => $connections
        ];
    }
}
?>