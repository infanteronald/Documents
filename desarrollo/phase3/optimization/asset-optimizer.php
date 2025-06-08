<?php
/**
 * Optimizador de Assets - FASE 3 Sequoia Speed
 * Minificación y optimización de JS/CSS
 */

class AssetOptimizer {
    private $basePath;
    private $optimizations = [];
    private $stats = [];
    
    public function __construct() {
        $this->basePath = dirname(__DIR__, 2);
        $this->stats = [
            'js_files' => 0,
            'css_files' => 0,
            'original_size' => 0,
            'optimized_size' => 0,
            'compression_ratio' => 0
        ];
    }
    
    public function optimize() {
        echo "⚡ OPTIMIZADOR DE ASSETS FASE 3\n";
        echo "===============================\n\n";
        
        $this->createOptimizedDirectories();
        $this->analyzeExistingAssets();
        $this->optimizeJavaScript();
        $this->optimizeCSS();
        $this->createCombinedAssets();
        $this->implementLazyLoading();
        $this->generateAssetReport();
        
        echo "\n✅ Optimización de assets completada!\n";
        $this->displayStats();
    }
    
    private function createOptimizedDirectories() {
        $dirs = [
            $this->basePath . '/assets/optimized',
            $this->basePath . '/assets/optimized/js',
            $this->basePath . '/assets/optimized/css',
            $this->basePath . '/assets/combined'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
                echo "📁 Directorio creado: " . basename($dir) . "\n";
            }
        }
    }
    
    private function analyzeExistingAssets() {
        echo "🔍 Analizando assets existentes...\n";
        
        // Buscar archivos JS
        $jsFiles = glob($this->basePath . '/*.js');
        $jsFiles = array_merge($jsFiles, glob($this->basePath . '/js/*.js'));
        $jsFiles = array_merge($jsFiles, glob($this->basePath . '/assets/*.js'));
        
        // Buscar archivos CSS  
        $cssFiles = glob($this->basePath . '/*.css');
        $cssFiles = array_merge($cssFiles, glob($this->basePath . '/css/*.css'));
        $cssFiles = array_merge($cssFiles, glob($this->basePath . '/assets/*.css'));
        
        $this->stats['js_files'] = count($jsFiles);
        $this->stats['css_files'] = count($cssFiles);
        
        echo "📊 Encontrados: {$this->stats['js_files']} archivos JS, {$this->stats['css_files']} archivos CSS\n";
        
        // Calcular tamaño original
        foreach (array_merge($jsFiles, $cssFiles) as $file) {
            $this->stats['original_size'] += filesize($file);
        }
        
        return ['js' => $jsFiles, 'css' => $cssFiles];
    }
    
    private function optimizeJavaScript() {
        echo "🛠️ Optimizando archivos JavaScript...\n";
        
        $jsFiles = glob($this->basePath . '/*.js');
        $jsFiles = array_merge($jsFiles, glob($this->basePath . '/js/*.js'));
        $jsFiles = array_merge($jsFiles, glob($this->basePath . '/assets/*.js'));
        
        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            $originalSize = strlen($content);
            
            // Minificación básica de JS
            $minified = $this->minifyJavaScript($content);
            $optimizedSize = strlen($minified);
            
            $filename = basename($file, '.js');
            $outputFile = $this->basePath . '/assets/optimized/js/' . $filename . '.min.js';
            
            file_put_contents($outputFile, $minified);
            
            $this->optimizations[] = [
                'file' => basename($file),
                'type' => 'js',
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'compression' => round(($originalSize - $optimizedSize) / $originalSize * 100, 2)
            ];
            
            echo "  ✅ {$filename}.js → {$filename}.min.js ({$this->optimizations[count($this->optimizations)-1]['compression']}% reducción)\n";
        }
    }
    
    private function optimizeCSS() {
        echo "🎨 Optimizando archivos CSS...\n";
        
        $cssFiles = glob($this->basePath . '/*.css');
        $cssFiles = array_merge($cssFiles, glob($this->basePath . '/css/*.css'));
        $cssFiles = array_merge($cssFiles, glob($this->basePath . '/assets/*.css'));
        
        foreach ($cssFiles as $file) {
            $content = file_get_contents($file);
            $originalSize = strlen($content);
            
            // Minificación básica de CSS
            $minified = $this->minifyCSS($content);
            $optimizedSize = strlen($minified);
            
            $filename = basename($file, '.css');
            $outputFile = $this->basePath . '/assets/optimized/css/' . $filename . '.min.css';
            
            file_put_contents($outputFile, $minified);
            
            $this->optimizations[] = [
                'file' => basename($file),
                'type' => 'css',
                'original_size' => $originalSize,
                'optimized_size' => $optimizedSize,
                'compression' => round(($originalSize - $optimizedSize) / $originalSize * 100, 2)
            ];
            
            echo "  ✅ {$filename}.css → {$filename}.min.css ({$this->optimizations[count($this->optimizations)-1]['compression']}% reducción)\n";
        }
    }
    
    private function minifyJavaScript($content) {
        // Minificación básica de JavaScript
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content); // Comentarios multilinea
        $content = preg_replace('/\/\/.*$/m', '', $content); // Comentarios de línea
        $content = preg_replace('/\s+/', ' ', $content); // Espacios múltiples
        $content = preg_replace('/;\s*}/', '}', $content); // Punto y coma antes de }
        $content = trim($content);
        
        return $content;
    }
    
    private function minifyCSS($content) {
        // Minificación básica de CSS
        $content = preg_replace('/\/\*[\s\S]*?\*\//', '', $content); // Comentarios
        $content = preg_replace('/\s+/', ' ', $content); // Espacios múltiples
        $content = preg_replace('/;\s*}/', '}', $content); // Punto y coma antes de }
        $content = preg_replace('/\s*{\s*/', '{', $content); // Espacios alrededor de {
        $content = preg_replace('/;\s*/', ';', $content); // Espacios después de ;
        $content = preg_replace('/:\s*/', ':', $content); // Espacios después de :
        $content = trim($content);
        
        return $content;
    }
    
    private function createCombinedAssets() {
        echo "📦 Creando assets combinados...\n";
        
        // Combinar todos los archivos JS minificados
        $jsContent = '';
        $jsFiles = glob($this->basePath . '/assets/optimized/js/*.min.js');
        
        foreach ($jsFiles as $file) {
            $jsContent .= file_get_contents($file) . "\n";
        }
        
        if (!empty($jsContent)) {
            $combinedJsFile = $this->basePath . '/assets/combined/app.min.js';
            file_put_contents($combinedJsFile, $jsContent);
            echo "  ✅ Creado: app.min.js\n";
        }
        
        // Combinar todos los archivos CSS minificados
        $cssContent = '';
        $cssFiles = glob($this->basePath . '/assets/optimized/css/*.min.css');
        
        foreach ($cssFiles as $file) {
            $cssContent .= file_get_contents($file) . "\n";
        }
        
        if (!empty($cssContent)) {
            $combinedCssFile = $this->basePath . '/assets/combined/app.min.css';
            file_put_contents($combinedCssFile, $cssContent);
            echo "  ✅ Creado: app.min.css\n";
        }
    }
    
    private function implementLazyLoading() {
        echo "🚀 Implementando lazy loading...\n";
        
        // Crear script de lazy loading
        $lazyLoadScript = '
// Lazy Loading Implementation for Sequoia Speed
class LazyLoader {
    constructor() {
        this.imageObserver = null;
        this.init();
    }
    
    init() {
        if ("IntersectionObserver" in window) {
            this.imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove("lazy");
                        img.classList.add("loaded");
                        this.imageObserver.unobserve(img);
                    }
                });
            });
            
            document.addEventListener("DOMContentLoaded", () => {
                const lazyImages = document.querySelectorAll("img[data-src]");
                lazyImages.forEach(img => this.imageObserver.observe(img));
            });
        }
    }
    
    static loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.async = true;
        if (callback) script.onload = callback;
        document.head.appendChild(script);
    }
    
    static loadCSS(href) {
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        document.head.appendChild(link);
    }
}

// Inicializar lazy loader
new LazyLoader();
';
        
        $lazyLoadFile = $this->basePath . '/assets/optimized/js/lazy-loader.min.js';
        file_put_contents($lazyLoadFile, $this->minifyJavaScript($lazyLoadScript));
        echo "  ✅ Creado: lazy-loader.min.js\n";
        
        // Crear helper para implementar lazy loading
        $this->createLazyLoadHelper();
    }
    
    private function createLazyLoadHelper() {
        $helperContent = '<?php
/**
 * Helper para Lazy Loading - Sequoia Speed
 */

class LazyLoadHelper {
    public static function lazyImage($src, $alt = "", $class = "") {
        $placeholder = "data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'1\' height=\'1\'%3E%3C/svg%3E";
        return "<img src=\\"$placeholder\\" data-src=\\"$src\\" alt=\\"$alt\\" class=\\"lazy $class\\" loading=\\"lazy\\">";
    }
    
    public static function lazyScript($src, $condition = null) {
        if ($condition && !$condition) return "";
        return "<script>LazyLoader.loadScript(\\'$src\\');</script>";
    }
    
    public static function lazyCSS($href, $condition = null) {
        if ($condition && !$condition) return "";
        return "<script>LazyLoader.loadCSS(\\'$href\\');</script>";
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
?>';
        
        $helperFile = $this->basePath . '/app/LazyLoadHelper.php';
        file_put_contents($helperFile, $helperContent);
        echo "  ✅ Creado: LazyLoadHelper.php\n";
    }
    
    private function generateAssetReport() {
        echo "📊 Generando reporte de optimización...\n";
        
        // Calcular estadísticas finales
        foreach ($this->optimizations as $opt) {
            $this->stats['optimized_size'] += $opt['optimized_size'];
        }
        
        $this->stats['compression_ratio'] = $this->stats['original_size'] > 0 
            ? round(($this->stats['original_size'] - $this->stats['optimized_size']) / $this->stats['original_size'] * 100, 2)
            : 0;
            
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => 'FASE 3 - Asset Optimization',
            'stats' => $this->stats,
            'optimizations' => $this->optimizations,
            'files_created' => [
                'lazy-loader.min.js',
                'LazyLoadHelper.php',
                'app.min.js',
                'app.min.css'
            ]
        ];
        
        $reportFile = $this->basePath . '/phase3/reports/asset-optimization-report.json';
        file_put_contents($reportFile, json_encode($report, JSON_PRETTY_PRINT));
        echo "  ✅ Reporte guardado en: reports/asset-optimization-report.json\n";
    }
    
    private function displayStats() {
        echo "\n📈 ESTADÍSTICAS DE OPTIMIZACIÓN:\n";
        echo "=================================\n";
        echo "• Archivos JS procesados: {$this->stats['js_files']}\n";
        echo "• Archivos CSS procesados: {$this->stats['css_files']}\n";
        echo "• Tamaño original: " . $this->formatBytes($this->stats['original_size']) . "\n";
        echo "• Tamaño optimizado: " . $this->formatBytes($this->stats['optimized_size']) . "\n";
        echo "• Compresión total: {$this->stats['compression_ratio']}%\n";
        
        if (count($this->optimizations) > 0) {
            echo "\n🏆 TOP OPTIMIZACIONES:\n";
            echo "=====================\n";
            usort($this->optimizations, function($a, $b) {
                return $b['compression'] <=> $a['compression'];
            });
            
            foreach (array_slice($this->optimizations, 0, 5) as $opt) {
                echo "• {$opt['file']}: {$opt['compression']}% reducción\n";
            }
        }
    }
    
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}

// Ejecutar optimización
$optimizer = new AssetOptimizer();
$optimizer->optimize();

echo "\n🚀 PRÓXIMO PASO:\n";
echo "===============\n";
echo "php phase3/run-performance-tests.php\n";
?>
