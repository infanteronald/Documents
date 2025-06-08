<?php
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
}