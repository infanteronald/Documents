<?php
/**
 * Helper para Lazy Loading - Sequoia Speed
 */

class LazyLoadHelper {
    public static function lazyImage($src, $alt = "", $class = "") {
        $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='1' height='1'%3E%3C/svg%3E";
        return "<img src=\"$placeholder\" data-src=\"$src\" alt=\"$alt\" class=\"lazy $class\" loading=\"lazy\">";
    }
    
    public static function lazyScript($src, $condition = null) {
        if ($condition && !$condition) return "";
        return "<script>LazyLoader.loadScript('$src');</script>";
    }
    
    public static function lazyCSS($href, $condition = null) {
        if ($condition && !$condition) return "";
        return "<script>LazyLoader.loadCSS('$href');</script>";
    }
    
    public static function criticalCSS() {
        return "
        <style>
        .lazy { opacity: 0; transition: opacity 0.3s; }
        .loaded { opacity: 1; }
        .loading { background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%); background-size: 200% 100%; animation: loading 1.5s infinite; }
        @keyframes loading { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
        </style>";
    }
}
?>
