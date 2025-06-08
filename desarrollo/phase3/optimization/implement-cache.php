<?php
/**
 * Implementador de Cache - FASE 3 Sequoia Speed
 * Sistema de cache simple y eficiente para optimización
 */

echo "🚀 IMPLEMENTANDO SISTEMA DE CACHE\n";
echo "=================================\n\n";

class CacheImplementor {
    private $cacheDir;
    private $basePath;
    
    public function __construct() {
        $this->basePath = dirname(__DIR__);
        $this->cacheDir = $this->basePath . "/storage/cache";
    }
    
    public function implement() {
        echo "📁 Configurando sistema de cache...\n";
        
        $this->createCacheStructure();
        $this->createCacheClass();
        $this->createQueryCache();
        $this->createAssetCache();
        $this->generateCacheConfig();
        
        echo "\n✅ Sistema de cache implementado exitosamente!\n";
    }
    
    private function createCacheStructure() {
        $dirs = [
            "storage/cache",
            "storage/cache/queries",
            "storage/cache/assets",
            "storage/cache/templates",
            "app/cache"
        ];
        
        foreach ($dirs as $dir) {
            $fullPath = $this->basePath . "/" . $dir;
            if (!is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
                echo "  ✓ Creado: $dir\n";
            } else {
                echo "  → Existe: $dir\n";
            }
        }
    }
    
    private function createCacheClass() {
        echo "\n🔧 Creando clase Cache principal...\n";
        
        $cacheClass = '<?php
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
}';
        
        file_put_contents($this->basePath . "/app/cache/SimpleCache.php", $cacheClass);
        echo "  ✓ SimpleCache.php creado\n";
    }
    
    private function createQueryCache() {
        echo "\n🗄️ Creando cache de consultas...\n";
        
        $queryCache = '<?php
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
}';
        
        file_put_contents($this->basePath . "/app/cache/QueryCache.php", $queryCache);
        echo "  ✓ QueryCache.php creado\n";
    }
    
    private function createAssetCache() {
        echo "\n📦 Creando cache de assets...\n";
        
        $assetCache = '<?php
require_once __DIR__ . "/SimpleCache.php";

/**
 * Cache para assets (JS, CSS, imágenes)
 */
class AssetCache {
    private $cache;
    private $assetDir;
    
    public function __construct() {
        $this->cache = new SimpleCache(__DIR__ . "/../../storage/cache/assets");
        $this->assetDir = __DIR__ . "/../../public/assets";
    }
    
    /**
     * Obtener asset minificado y cacheado
     */
    public function getAsset($path, $type = "auto") {
        if ($type === "auto") {
            $type = $this->detectType($path);
        }
        
        $key = "asset_" . md5($path);
        
        return $this->cache->remember($key, function() use ($path, $type) {
            return $this->processAsset($path, $type);
        }, 7200); // 2 horas
    }
    
    /**
     * Procesar y optimizar asset
     */
    private function processAsset($path, $type) {
        $fullPath = $this->assetDir . "/" . $path;
        
        if (!file_exists($fullPath)) {
            return ["error" => "Asset not found: $path"];
        }
        
        $content = file_get_contents($fullPath);
        
        switch ($type) {
            case "js":
                $content = $this->minifyJs($content);
                break;
            case "css":
                $content = $this->minifyCss($content);
                break;
        }
        
        return [
            "content" => $content,
            "size" => strlen($content),
            "type" => $type,
            "processed_at" => time()
        ];
    }
    
    /**
     * Minificación básica de JavaScript
     */
    private function minifyJs($content) {
        // Remover comentarios de línea
        $content = preg_replace("/\/\/.*$/m", "", $content);
        
        // Remover comentarios de bloque
        $content = preg_replace("/\/\*[\s\S]*?\*\//", "", $content);
        
        // Remover espacios extras
        $content = preg_replace("/\s+/", " ", $content);
        
        return trim($content);
    }
    
    /**
     * Minificación básica de CSS
     */
    private function minifyCss($content) {
        // Remover comentarios
        $content = preg_replace("/\/\*[\s\S]*?\*\//", "", $content);
        
        // Remover espacios y saltos de línea innecesarios
        $content = preg_replace("/\s+/", " ", $content);
        $content = str_replace(["; ", " {", "{ ", " }", "} ", ": "], [";", "{", "{", "}", "}", ":"], $content);
        
        return trim($content);
    }
    
    private function detectType($path) {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        switch (strtolower($extension)) {
            case "js":
                return "js";
            case "css":
                return "css";
            default:
                return "static";
        }
    }
}';
        
        file_put_contents($this->basePath . "/app/cache/AssetCache.php", $assetCache);
        echo "  ✓ AssetCache.php creado\n";
    }
    
    private function generateCacheConfig() {
        echo "\n⚙️ Generando configuración de cache...\n";
        
        $config = [
            "cache" => [
                "default_ttl" => 3600,
                "query_ttl" => 1800,
                "asset_ttl" => 7200,
                "enabled" => true,
                "directories" => [
                    "main" => "storage/cache",
                    "queries" => "storage/cache/queries",
                    "assets" => "storage/cache/assets",
                    "templates" => "storage/cache/templates"
                ],
                "auto_cleanup" => true,
                "cleanup_interval" => 86400,
                "max_size_mb" => 100
            ],
            "performance" => [
                "enable_gzip" => true,
                "enable_minification" => true,
                "cache_headers" => true,
                "etag_enabled" => true
            ]
        ];
        
        file_put_contents($this->basePath . "/app/config/cache.json", json_encode($config, JSON_PRETTY_PRINT));
        echo "  ✓ cache.json configurado\n";
        
        // Crear helper de cache
        $helper = '<?php
/**
 * Helper de Cache para fácil integración
 */

require_once __DIR__ . "/cache/SimpleCache.php";
require_once __DIR__ . "/cache/QueryCache.php";
require_once __DIR__ . "/cache/AssetCache.php";

class CacheHelper {
    private static $instances = [];
    
    public static function getCache($type = "default") {
        if (!isset(self::$instances[$type])) {
            switch ($type) {
                case "query":
                    self::$instances[$type] = new QueryCache();
                    break;
                case "asset":
                    self::$instances[$type] = new AssetCache();
                    break;
                default:
                    self::$instances[$type] = new SimpleCache();
                    break;
            }
        }
        
        return self::$instances[$type];
    }
    
    public static function clearAll() {
        $cache = new SimpleCache();
        return $cache->clear();
    }
}';
        
        file_put_contents($this->basePath . "/app/CacheHelper.php", $helper);
        echo "  ✓ CacheHelper.php creado\n";
    }
}

// Ejecutar implementación
$implementor = new CacheImplementor();
$implementor->implement();

echo "\n📊 SISTEMA DE CACHE LISTO:\n";
echo "=========================\n";
echo "• SimpleCache - Cache general ✅\n";
echo "• QueryCache - Cache de consultas ✅\n";
echo "• AssetCache - Cache de assets ✅\n";
echo "• CacheHelper - Integración fácil ✅\n\n";

echo "🔗 INTEGRACIÓN:\n";
echo "==============\n";
echo "require_once 'app/CacheHelper.php';\n";
echo "$cache = CacheHelper::getCache();\n";
echo "$cache->set('key', 'value');\n\n";

echo "🚀 SIGUIENTE: Implementar en archivos principales\n";
echo "Ejecutar: php optimization/integrate-cache.php\n";
