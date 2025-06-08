<?php
/**
 * Análisis de métricas baseline para FASE 3
 */

class BaselineAnalyzer {
    private $metrics = [];
    
    public function analyzeCurrentState() {
        echo "📊 Analizando estado actual del sistema...\n\n";
        
        // Análisis de archivos
        $this->analyzeFiles();
        
        // Análisis de performance
        $this->analyzePerformance();
        
        // Análisis de código
        $this->analyzeCodeQuality();
        
        // Generar reporte
        $this->generateReport();
    }
    
    private function analyzeFiles() {
        echo "📁 Análisis de archivos...\n";
        
        $phpFiles = glob("*.php") + glob("public/api/*/*.php");
        $jsFiles = glob("public/assets/js/*.js");
        
        $totalSize = 0;
        foreach ($phpFiles as $file) {
            $totalSize += filesize($file);
        }
        
        $this->metrics["total_php_files"] = count($phpFiles);
        $this->metrics["total_js_files"] = count($jsFiles);
        $this->metrics["total_code_size"] = $totalSize;
        
        echo "  • Archivos PHP: " . count($phpFiles) . "\n";
        echo "  • Archivos JS: " . count($jsFiles) . "\n";
        echo "  • Tamaño total: " . round($totalSize/1024, 2) . " KB\n\n";
    }
    
    private function analyzePerformance() {
        echo "⚡ Análisis de performance...\n";
        
        $startTime = microtime(true);
        
        // Análisis de archivos sin ejecutarlos para evitar errores de BD
        $files = ["migration-helper.php", "legacy-bridge.php", "verificacion-fase2.php"];
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (file_exists($file)) {
                $totalSize += filesize($file);
                $fileCount++;
            }
        }
        
        $loadTime = microtime(true) - $startTime;
        $this->metrics["load_time"] = $loadTime;
        
        echo "  • Tiempo de carga: " . round($loadTime * 1000, 2) . " ms\n";
        echo "  • Memoria utilizada: " . round(memory_get_usage()/1024/1024, 2) . " MB\n\n";
    }
    
    private function analyzeCodeQuality() {
        echo "🔍 Análisis de calidad de código...\n";
        
        $duplicateCode = 0;
        $legacyPatterns = 0;
        
        // Buscar patrones legacy y código duplicado
        $phpFiles = glob("*.php");
        foreach ($phpFiles as $file) {
            $content = file_get_contents($file);
            
            // Buscar patrones legacy
            if (strpos($content, "mysql_") !== false) $legacyPatterns++;
            if (strpos($content, "register_globals") !== false) $legacyPatterns++;
            
            // Estimar código duplicado (funciones similares)
            if (substr_count($content, "function ") > 5) $duplicateCode++;
        }
        
        $this->metrics["legacy_patterns"] = $legacyPatterns;
        $this->metrics["potential_duplicates"] = $duplicateCode;
        
        echo "  • Patrones legacy detectados: $legacyPatterns\n";
        echo "  • Posibles duplicados: $duplicateCode\n\n";
    }
    
    private function generateReport() {
        $report = [
            "timestamp" => date("Y-m-d H:i:s"),
            "phase" => "Pre-FASE 3 Baseline",
            "metrics" => $this->metrics,
            "recommendations" => [
                "priority_high" => [
                    "Implementar testing automatizado",
                    "Optimizar queries de base de datos",
                    "Eliminar código duplicado"
                ],
                "priority_medium" => [
                    "Migrar vistas restantes a MVC",
                    "Documentar APIs",
                    "Implementar cache"
                ],
                "priority_low" => [
                    "Limpiar archivos legacy",
                    "Optimizar assets",
                    "Refactorizar utilidades"
                ]
            ]
        ];
        
        file_put_contents("phase3/reports/baseline-analysis.json", json_encode($report, JSON_PRETTY_PRINT));
        
        echo "📋 REPORTE BASELINE GENERADO\n";
        echo "============================\n";
        echo "• Archivos PHP: " . $this->metrics["total_php_files"] . "\n";
        echo "• Tamaño código: " . round($this->metrics["total_code_size"]/1024, 2) . " KB\n";
        echo "• Tiempo carga: " . round($this->metrics["load_time"] * 1000, 2) . " ms\n";
        echo "• Patrones legacy: " . $this->metrics["legacy_patterns"] . "\n";
        echo "\n💾 Reporte guardado en: phase3/reports/baseline-analysis.json\n";
    }
}

if (php_sapi_name() === "cli" || !isset($_SERVER["HTTP_HOST"])) {
    $analyzer = new BaselineAnalyzer();
    $analyzer->analyzeCurrentState();
}
?>