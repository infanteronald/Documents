<?php
require_once __DIR__ . "/SimpleCache.php";

/**
 * Cache especializado para consultas de base de datos
 */
class QueryCache {
    private $cache;
    
    public function __construct() {
        $this->cache = new SimpleCache(__DIR__ . "/../../storage/cache/queries");
    }
    
    /**
     * Cache de consulta con parámetros
     */
    public function query($sql, $params = [], $ttl = 1800) {
        $key = $this->generateQueryKey($sql, $params);
        
        return $this->cache->remember($key, function() use ($sql, $params) {
            // Esta función debe ser implementada con la conexión real
            return $this->executeQuery($sql, $params);
        }, $ttl);
    }
    
    /**
     * Invalidar cache por tabla
     */
    public function invalidateTable($tableName) {
        // Eliminar todos los cache que contengan la tabla
        $files = glob($this->cache->getCacheDir() . "/*.cache");
        $deleted = 0;
        
        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if (isset($data["sql"]) && strpos($data["sql"], $tableName) !== false) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
    
    private function generateQueryKey($sql, $params) {
        return "query_" . md5($sql . serialize($params));
    }
    
    private function executeQuery($sql, $params) {
        // Placeholder - implementar con mysqli o PDO real
        return ["cached" => true, "sql" => $sql, "params" => $params];
    }
}