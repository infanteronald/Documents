<?php
/**
 * Cache Manager - Sistema de gestión de cache
 * Sequoia Speed - FASE 4
 */

class CacheManager {
    private static $instance = null;
    private $cache = [];
    private $enabled = true;
    private $prefix = 'sequoia_';
    
    private function __construct() {
        // Singleton pattern
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener valor del cache
     */
    public function get($key, $default = null) {
        if (!$this->enabled) {
            return $default;
        }
        
        $fullKey = $this->prefix . $key;
        
        // Si tenemos Redis disponible
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $value = $redis->get($fullKey);
                $redis->close();
                
                if ($value !== false) {
                    return unserialize($value);
                }
            } catch (Exception $e) {
                // Fallback a cache en memoria
            }
        }
        
        // Cache en memoria como fallback
        return isset($this->cache[$fullKey]) ? $this->cache[$fullKey] : $default;
    }
    
    /**
     * Guardar valor en cache
     */
    public function set($key, $value, $ttl = 3600) {
        if (!$this->enabled) {
            return false;
        }
        
        $fullKey = $this->prefix . $key;
        
        // Si tenemos Redis disponible
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $result = $redis->setex($fullKey, $ttl, serialize($value));
                $redis->close();
                return $result;
            } catch (Exception $e) {
                // Fallback a cache en memoria
            }
        }
        
        // Cache en memoria como fallback
        $this->cache[$fullKey] = $value;
        return true;
    }
    
    /**
     * Eliminar valor del cache
     */
    public function delete($key) {
        $fullKey = $this->prefix . $key;
        
        // Si tenemos Redis disponible
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $result = $redis->del($fullKey);
                $redis->close();
                return $result > 0;
            } catch (Exception $e) {
                // Fallback a cache en memoria
            }
        }
        
        // Cache en memoria como fallback
        unset($this->cache[$fullKey]);
        return true;
    }
    
    /**
     * Limpiar todo el cache
     */
    public function clear() {
        // Si tenemos Redis disponible
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $keys = $redis->keys($this->prefix . '*');
                if (!empty($keys)) {
                    $redis->del($keys);
                }
                $redis->close();
            } catch (Exception $e) {
                // Fallback a cache en memoria
            }
        }
        
        // Cache en memoria
        $this->cache = [];
        return true;
    }
    
    /**
     * Cache con callback
     */
    public function remember($key, $callback, $ttl = 3600) {
        $value = $this->get($key);
        
        if ($value === null) {
            $value = $callback();
            $this->set($key, $value, $ttl);
        }
        
        return $value;
    }
    
    /**
     * Habilitar/deshabilitar cache
     */
    public function setEnabled($enabled) {
        $this->enabled = (bool)$enabled;
    }
    
    /**
     * Verificar si el cache está habilitado
     */
    public function isEnabled() {
        return $this->enabled;
    }
    
    /**
     * Obtener estadísticas del cache
     */
    public function getStats() {
        $stats = [
            'enabled' => $this->enabled,
            'memory_items' => count($this->cache),
            'redis_available' => extension_loaded('redis') && class_exists('Redis')
        ];
        
        if ($stats['redis_available']) {
            try {
                $redis = new Redis();
                $redis->connect('127.0.0.1', 6379);
                $info = $redis->info();
                $stats['redis_memory'] = $info['used_memory_human'] ?? 'N/A';
                $stats['redis_keys'] = count($redis->keys($this->prefix . '*'));
                $redis->close();
            } catch (Exception $e) {
                $stats['redis_error'] = $e->getMessage();
            }
        }
        
        return $stats;
    }
}
