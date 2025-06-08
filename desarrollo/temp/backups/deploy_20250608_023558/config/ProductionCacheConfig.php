<?php
/**
 * Configuración de Cache para Producción
 * Sequoia Speed - Redis Cache System
 */

class ProductionCacheConfig {
    private static $redis = null;
    private static $enabled = true;
    private static $prefix = 'sequoia_prod_';
    
    /**
     * Inicializar conexión Redis
     */
    public static function init() {
        if (!extension_loaded('redis')) {
            self::$enabled = false;
            error_log('Redis extension not loaded, cache disabled');
            return false;
        }
        
        try {
            self::$redis = new Redis();
            self::$redis->connect('127.0.0.1', 6379);
            
            // Configurar opciones de producción
            self::$redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
            self::$redis->setOption(Redis::OPT_PREFIX, self::$prefix);
            
            return true;
        } catch (Exception $e) {
            self::$enabled = false;
            error_log('Redis connection failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Configurar cache para diferentes tipos de datos
     */
    public static function configureCacheStrategies() {
        return [
            'pedidos' => [
                'ttl' => 300, // 5 minutos
                'strategy' => 'write_through',
                'tags' => ['pedidos', 'business_data']
            ],
            'productos' => [
                'ttl' => 1800, // 30 minutos
                'strategy' => 'lazy_loading',
                'tags' => ['productos', 'catalog']
            ],
            'usuarios' => [
                'ttl' => 600, // 10 minutos
                'strategy' => 'write_through',
                'tags' => ['usuarios', 'auth']
            ],
            'reportes' => [
                'ttl' => 3600, // 1 hora
                'strategy' => 'lazy_loading',
                'tags' => ['reportes', 'analytics']
            ],
            'configuracion' => [
                'ttl' => 86400, // 24 horas
                'strategy' => 'write_through',
                'tags' => ['config', 'system']
            ]
        ];
    }
    
    /**
     * Cache inteligente con tags
     */
    public static function set($key, $value, $ttl = null, $tags = []) {
        if (!self::isEnabled()) {
            return false;
        }
        
        $strategies = self::configureCacheStrategies();
        $category = self::getCategoryFromKey($key);
        
        if (isset($strategies[$category])) {
            $config = $strategies[$category];
            $ttl = $ttl ?: $config['ttl'];
            $tags = array_merge($tags, $config['tags']);
        }
        
        $ttl = $ttl ?: 3600; // Default 1 hour
        
        try {
            // Guardar valor principal
            $result = self::$redis->setex($key, $ttl, json_encode($value));
            
            // Guardar tags para invalidación
            foreach ($tags as $tag) {
                self::$redis->sadd("tag:$tag", $key);
                self::$redis->expire("tag:$tag", $ttl + 3600); // Tags duran más
            }
            
            return $result;
        } catch (Exception $e) {
            error_log('Cache set failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener valor del cache
     */
    public static function get($key, $default = null) {
        if (!self::isEnabled()) {
            return $default;
        }
        
        try {
            $value = self::$redis->get($key);
            return $value !== false ? json_decode($value, true) : $default;
        } catch (Exception $e) {
            error_log('Cache get failed: ' . $e->getMessage());
            return $default;
        }
    }
    
    /**
     * Invalidar cache por tags
     */
    public static function invalidateByTag($tag) {
        if (!self::isEnabled()) {
            return false;
        }
        
        try {
            $keys = self::$redis->smembers("tag:$tag");
            if (!empty($keys)) {
                self::$redis->del($keys);
            }
            self::$redis->del("tag:$tag");
            return true;
        } catch (Exception $e) {
            error_log('Cache invalidation failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cache con callback y fallback
     */
    public static function remember($key, $callback, $ttl = 3600, $tags = []) {
        $value = self::get($key);
        
        if ($value === null) {
            $value = $callback();
            if ($value !== null) {
                self::set($key, $value, $ttl, $tags);
            }
        }
        
        return $value;
    }
    
    /**
     * Limpiar cache completo (usar con cuidado)
     */
    public static function flush() {
        if (!self::isEnabled()) {
            return false;
        }
        
        try {
            $keys = self::$redis->keys(self::$prefix . '*');
            if (!empty($keys)) {
                return self::$redis->del($keys);
            }
            return true;
        } catch (Exception $e) {
            error_log('Cache flush failed: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Estadísticas del cache
     */
    public static function getStats() {
        if (!self::isEnabled()) {
            return ['enabled' => false];
        }
        
        try {
            $info = self::$redis->info();
            $keyCount = count(self::$redis->keys(self::$prefix . '*'));
            
            return [
                'enabled' => true,
                'connection' => 'active',
                'keys' => $keyCount,
                'memory_used' => $info['used_memory_human'] ?? 'N/A',
                'hits' => $info['keyspace_hits'] ?? 0,
                'misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => self::calculateHitRate($info),
                'uptime' => $info['uptime_in_seconds'] ?? 0
            ];
        } catch (Exception $e) {
            return [
                'enabled' => true,
                'connection' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Configurar warming del cache
     */
    public static function warmUp() {
        if (!self::isEnabled()) {
            return false;
        }
        
        $warmUpData = [
            'productos_populares' => function() {
                // Simular carga de productos populares
                return ['producto1', 'producto2', 'producto3'];
            },
            'configuracion_app' => function() {
                return [
                    'version' => '4.0',
                    'maintenance' => false,
                    'features' => ['bold_payment', 'reports', 'cache']
                ];
            },
            'estadisticas_rapidas' => function() {
                return [
                    'pedidos_hoy' => 0,
                    'ingresos_hoy' => 0,
                    'productos_activos' => 0
                ];
            }
        ];
        
        foreach ($warmUpData as $key => $callback) {
            self::remember($key, $callback, 3600, ['warmup']);
        }
        
        return true;
    }
    
    private static function isEnabled() {
        return self::$enabled && self::$redis !== null;
    }
    
    private static function getCategoryFromKey($key) {
        if (strpos($key, 'pedido') !== false) return 'pedidos';
        if (strpos($key, 'producto') !== false) return 'productos';
        if (strpos($key, 'usuario') !== false) return 'usuarios';
        if (strpos($key, 'reporte') !== false) return 'reportes';
        if (strpos($key, 'config') !== false) return 'configuracion';
        
        return 'general';
    }
    
    private static function calculateHitRate($info) {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;
        
        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }
}

// Inicializar al cargar
ProductionCacheConfig::init();
