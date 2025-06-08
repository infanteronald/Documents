<?php
/**
 * Sistema de Cache Simple - Sequoia Speed
 * Cache en archivos para optimización de performance
 */

class SimpleCache {
    private $cacheDir;
    private $defaultTtl = 3600; // 1 hora por defecto
    
    public function __construct($cacheDir = null) {
        $this->cacheDir = $cacheDir ?: __DIR__ . "/../storage/cache";
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    /**
     * Obtener valor del cache
     */
    public function get($key, $default = null) {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return $default;
        }
        
        $data = json_decode(file_get_contents($filename), true);
        
        if ($data === null) {
            return $default;
        }
        
        // Verificar expiración
        if (isset($data["expires"]) && $data["expires"] < time()) {
            unlink($filename);
            return $default;
        }
        
        return $data["value"] ?? $default;
    }
    
    /**
     * Almacenar valor en cache
     */
    public function set($key, $value, $ttl = null) {
        $ttl = $ttl ?: $this->defaultTtl;
        $filename = $this->getFilename($key);
        
        $data = [
            "value" => $value,
            "expires" => time() + $ttl,
            "created" => time()
        ];
        
        return file_put_contents($filename, json_encode($data)) !== false;
    }
    
    /**
     * Verificar si existe en cache
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Eliminar del cache
     */
    public function delete($key) {
        $filename = $this->getFilename($key);
        
        if (file_exists($filename)) {
            return unlink($filename);
        }
        
        return true;
    }
    
    /**
     * Limpiar todo el cache
     */
    public function clear() {
        $files = glob($this->cacheDir . "/*.cache");
        $deleted = 0;
        
        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    /**
     * Obtener o establecer con callback
     */
    public function remember($key, $callback, $ttl = null) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Estadísticas del cache
     */
    public function stats() {
        $files = glob($this->cacheDir . "/*.cache");
        $totalSize = 0;
        $expired = 0;
        
        foreach ($files as $file) {
            $totalSize += filesize($file);
            
            $data = json_decode(file_get_contents($file), true);
            if (isset($data["expires"]) && $data["expires"] < time()) {
                $expired++;
            }
        }
        
        return [
            "total_files" => count($files),
            "total_size_kb" => round($totalSize / 1024, 2),
            "expired_files" => $expired
        ];
    }
    
    private function getFilename($key) {
        $hash = md5($key);
        return $this->cacheDir . "/" . $hash . ".cache";
    }
}