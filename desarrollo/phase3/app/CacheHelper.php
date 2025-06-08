<?php
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
}