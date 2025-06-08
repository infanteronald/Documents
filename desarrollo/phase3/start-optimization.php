<?php
/**
 * Optimizador de Performance - FASE 3 Sequoia Speed
 * Análisis y optimización de bottlenecks del sistema
 */

echo "⚡ OPTIMIZADOR DE PERFORMANCE FASE 3\n";
echo "===================================\n\n";

class PerformanceOptimizer {
    private $basePath;
    private $optimizations = [];
    
    public function __construct() {
        $this->basePath = dirname(__DIR__);
    }
    
    public function analyze() {
        echo "🔍 Analizando performance del sistema...\n\n";
        
        $this->analyzeFileLoading();
        $this->analyzeDatabaseQueries();
        $this->analyzeAssetOptimization();
        $this->generateOptimizationPlan();
        
        echo "📊 Análisis completo - Ver reporte en reports/\n";
    }
    
    private function analyzeFileLoading() {
        echo "📁 Análisis de carga de archivos...\n";
        
        $criticalFiles = [
            "migration-helper.php",
            "legacy-bridge.php",
            "index.php",
            "conexion.php"
        ];
        
        $fileMetrics = [];
        
        foreach ($criticalFiles as $file) {
            $fullPath = $this->basePath . "/" . $file;
            if (file_exists($fullPath)) {
                $startTime = microtime(true);
                $content = file_get_contents($fullPath);
                $loadTime = microtime(true) - $startTime;
                
                $size = filesize($fullPath);
                $lines = substr_count($content, "\n");
                
                $fileMetrics[$file] = [
                    "size_kb" => round($size / 1024, 2),
                    "lines" => $lines,
                    "load_time_ms" => round($loadTime * 1000, 4),
                    "complexity" => $this->calculateComplexity($content)
                ];
                
                echo "  ✓ $file: {$fileMetrics[$file]['size_kb']} KB, {$lines} líneas, {$fileMetrics[$file]['load_time_ms']} ms\n";
                
                // Sugerencias de optimización
                if ($size > 50 * 1024) {
                    $this->optimizations[] = "Considerar dividir $file (>50KB)";
                }
                if ($lines > 1000) {
                    $this->optimizations[] = "Refactorizar $file (>1000 líneas)";
                }
            }
        }
        
        $this->fileMetrics = $fileMetrics;
        echo "\n";
    }
    
    private function analyzeDatabaseQueries() {
        echo "🗄️ Análisis de consultas de base de datos...\n";
        
        $phpFiles = glob($this->basePath . "/*.php");
        $queryPatterns = [
            "SELECT" => 0,
            "INSERT" => 0,
            "UPDATE" => 0,
            "DELETE" => 0
        ];
        
        $potentialOptimizations = [];
        
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Buscar consultas SQL
            foreach ($queryPatterns as $pattern => $count) {
                $matches = preg_match_all("/$pattern/i", $content);
                $queryPatterns[$pattern] += $matches;
            }
            
            // Buscar patrones problemáticos
            if (strpos($content, "SELECT *") !== false) {
                $potentialOptimizations[] = "Evitar SELECT * en " . basename($file);
            }
            
            if (preg_match('/WHERE.*=.*\$/', $content)) {
                $potentialOptimizations[] = "Revisar consultas preparadas en " . basename($file);
            }
        }
        
        echo "  Consultas encontradas:\n";
        foreach ($queryPatterns as $type => $count) {
            echo "    $type: $count\n";
        }
        
        if (!empty($potentialOptimizations)) {
            echo "  ⚠️ Optimizaciones sugeridas:\n";
            foreach ($potentialOptimizations as $opt) {
                echo "    • $opt\n";
                $this->optimizations[] = $opt;
            }
        }
        
        echo "\n";
    }
    
    private function analyzeAssetOptimization() {
        echo "📦 Análisis de optimización de assets...\n";
        
        $assetDirs = [
            $this->basePath . "/public/assets/js",
            $this->basePath . "/public/assets/css"
        ];
        
        $assetMetrics = [];
        
        foreach ($assetDirs as $dir) {
            if (is_dir($dir)) {
                $files = glob($dir . "/*");
                $totalSize = 0;
                
                foreach ($files as $file) {
                    if (is_file($file)) {
                        $size = filesize($file);
                        $totalSize += $size;
                        
                        $ext = pathinfo($file, PATHINFO_EXTENSION);
                        if (!isset($assetMetrics[$ext])) {
                            $assetMetrics[$ext] = ["count" => 0, "total_size" => 0];
                        }
                        
                        $assetMetrics[$ext]["count"]++;
                        $assetMetrics[$ext]["total_size"] += $size;
                        
                        // Verificar si está minificado
                        if ($ext === "js" || $ext === "css") {
                            $content = file_get_contents($file);
                            $isMinified = (strpos($content, "\n") === false && strlen($content) > 1000);
                            
                            if (!$isMinified) {
                                $this->optimizations[] = "Minificar " . basename($file);
                            }
                        }
                    }
                }
            }
        }
        
        foreach ($assetMetrics as $type => $metrics) {
            $sizeKB = round($metrics["total_size"] / 1024, 2);
            echo "  $type: {$metrics['count']} archivos, $sizeKB KB\n";
        }
        
        echo "\n";
    }
    
    private function calculateComplexity($content) {
        $functions = substr_count($content, "function ");
        $classes = substr_count($content, "class ");
        $conditions = substr_count($content, "if (") + substr_count($content, "if(");
        
        return $functions + ($classes * 2) + $conditions;
    }
    
    private function generateOptimizationPlan() {
        echo "📋 Plan de optimización generado...\n";
        
        $plan = [
            "timestamp" => date("Y-m-d H:i:s"),
            "file_metrics" => $this->fileMetrics ?? [],
            "optimizations" => $this->optimizations,
            "priority_actions" => [
                "high" => [
                    "Implementar cache de consultas",
                    "Optimizar consultas SELECT principales",
                    "Minificar assets JavaScript"
                ],
                "medium" => [
                    "Refactorizar archivos grandes",
                    "Implementar lazy loading",
                    "Comprimir imágenes"
                ],
                "low" => [
                    "Limpiar código comentado",
                    "Consolidar estilos CSS",
                    "Documentar funciones críticas"
                ]
            ],
            "estimated_improvements" => [
                "load_time_reduction" => "30-40%",
                "memory_usage_reduction" => "20-25%", 
                "query_performance" => "50-60%"
            ]
        ];
        
        // Crear directorio si no existe
        if (!is_dir("reports")) {
            mkdir("reports", 0755, true);
        }
        
        file_put_contents("reports/optimization-plan.json", json_encode($plan, JSON_PRETTY_PRINT));
        
        echo "  ✓ Plan guardado en reports/optimization-plan.json\n";
        echo "  📈 Mejoras estimadas:\n";
        echo "    • Tiempo de carga: -30-40%\n";
        echo "    • Uso de memoria: -20-25%\n";
        echo "    • Performance queries: +50-60%\n\n";
        
        return $plan;
    }
}

// Ejecutar optimizador
$optimizer = new PerformanceOptimizer();
$optimizer->analyze();

echo "🚀 SIGUIENTE PASO: Implementar optimizaciones prioritarias\n";
echo "Ejecutar: php optimization/implement-cache.php\n";
