<?php
/**
 * FASE 4 - Analizador de Archivos Legacy
 * Analiza y categoriza archivos legacy para migración MVC
 */

// Configuración del analizador
$legacyAnalyzer = new LegacyFileAnalyzer();

class LegacyFileAnalyzer 
{
    private $rootPath;
    private $legacyFiles = [];
    private $mvcStructure = [];
    private $migrationPlan = [];
    
    public function __construct($rootPath = '/Users/ronaldinfante/Documents/pedidos') 
    {
        $this->rootPath = $rootPath;
    }
    
    public function analyzeLegacyFiles() 
    {
        echo "🔍 ANALIZANDO ARCHIVOS LEGACY PARA MIGRACIÓN MVC...\n\n";
        
        // Archivos críticos identificados
        $criticalFiles = [
            'listar_pedidos.php' => [
                'controller' => 'PedidoController',
                'methods' => ['index', 'filter', 'search', 'paginate'],
                'complexity' => 'high',
                'priority' => 1
            ],
            'guardar_pedido.php' => [
                'controller' => 'PedidoController', 
                'methods' => ['store', 'validateOrder'],
                'complexity' => 'high',
                'priority' => 1
            ],
            'actualizar_estado.php' => [
                'controller' => 'PedidoController',
                'methods' => ['updateStatus', 'trackChanges'],
                'complexity' => 'medium',
                'priority' => 2
            ],
            'productos_por_categoria.php' => [
                'controller' => 'ProductoController',
                'methods' => ['getByCategory', 'categoryFilter'],
                'complexity' => 'medium', 
                'priority' => 2
            ],
            'ver_detalle_pedido.php' => [
                'controller' => 'PedidoController',
                'methods' => ['show', 'getDetails'],
                'complexity' => 'low',
                'priority' => 3
            ],
            'bold_payment.php' => [
                'controller' => 'PaymentController',
                'methods' => ['processBoldPayment', 'handleWebhook'],
                'complexity' => 'high',
                'priority' => 1
            ]
        ];
        
        foreach ($criticalFiles as $file => $info) {
            $this->analyzeFile($file, $info);
        }
        
        $this->generateMigrationPlan();
        $this->displayAnalysisResults();
        
        return $this->migrationPlan;
    }
    
    private function analyzeFile($filename, $info) 
    {
        $filePath = $this->rootPath . '/' . $filename;
        
        if (!file_exists($filePath)) {
            echo "⚠️  Archivo no encontrado: $filename\n";
            return;
        }
        
        $content = file_get_contents($filePath);
        $lines = count(file($filePath));
        $functions = $this->extractFunctions($content);
        $queries = $this->extractQueries($content);
        
        $this->legacyFiles[$filename] = [
            'info' => $info,
            'metrics' => [
                'lines' => $lines,
                'functions' => count($functions),
                'queries' => count($queries),
                'complexity_score' => $this->calculateComplexity($content)
            ],
            'functions' => $functions,
            'queries' => $queries,
            'dependencies' => $this->findDependencies($content)
        ];
        
        echo "✅ Analizado: $filename ($lines líneas, " . count($functions) . " funciones)\n";
    }
    
    private function extractFunctions($content) 
    {
        preg_match_all('/function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $content, $matches);
        return $matches[1];
    }
    
    private function extractQueries($content) 
    {
        preg_match_all('/\$.*->(query|prepare|execute)\s*\(/', $content, $matches);
        return $matches[0];
    }
    
    private function calculateComplexity($content) 
    {
        $complexity = 0;
        $complexity += substr_count($content, 'if ');
        $complexity += substr_count($content, 'while ');
        $complexity += substr_count($content, 'for ');
        $complexity += substr_count($content, 'switch ');
        $complexity += substr_count($content, 'function ');
        return $complexity;
    }
    
    private function findDependencies($content) 
    {
        $deps = [];
        if (strpos($content, 'include') !== false || strpos($content, 'require') !== false) {
            preg_match_all('/(include|require)(_once)?\s*[\'\"](.*?)[\'\"]/', $content, $matches);
            $deps = array_merge($deps, $matches[3]);
        }
        return $deps;
    }
    
    private function generateMigrationPlan() 
    {
        echo "\n📋 GENERANDO PLAN DE MIGRACIÓN...\n\n";
        
        // Agrupar por controlador
        $controllerGroups = [];
        foreach ($this->legacyFiles as $filename => $data) {
            $controller = $data['info']['controller'];
            if (!isset($controllerGroups[$controller])) {
                $controllerGroups[$controller] = [];
            }
            $controllerGroups[$controller][] = $filename;
        }
        
        // Ordenar por prioridad
        foreach ($controllerGroups as $controller => $files) {
            usort($files, function($a, $b) {
                return $this->legacyFiles[$a]['info']['priority'] <=> $this->legacyFiles[$b]['info']['priority'];
            });
            $controllerGroups[$controller] = $files;
        }
        
        $this->migrationPlan = [
            'controllers' => $controllerGroups,
            'execution_order' => $this->getExecutionOrder($controllerGroups),
            'estimated_time' => $this->estimateMigrationTime()
        ];
    }
    
    private function getExecutionOrder($controllerGroups) 
    {
        $order = [];
        $priorities = [];
        
        foreach ($controllerGroups as $controller => $files) {
            $minPriority = min(array_map(function($file) {
                return $this->legacyFiles[$file]['info']['priority'];
            }, $files));
            $priorities[$controller] = $minPriority;
        }
        
        asort($priorities);
        return array_keys($priorities);
    }
    
    private function estimateMigrationTime() 
    {
        $totalComplexity = 0;
        foreach ($this->legacyFiles as $data) {
            $totalComplexity += $data['metrics']['complexity_score'];
        }
        
        // Estimación: 1 hora por cada 10 puntos de complejidad
        return ceil($totalComplexity / 10);
    }
    
    private function displayAnalysisResults() 
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📊 RESULTADOS DEL ANÁLISIS DE MIGRACIÓN\n";
        echo str_repeat("=", 60) . "\n\n";
        
        foreach ($this->migrationPlan['controllers'] as $controller => $files) {
            echo "🎯 $controller:\n";
            foreach ($files as $file) {
                $data = $this->legacyFiles[$file];
                echo "   └─ $file (Prioridad: {$data['info']['priority']}, ";
                echo "Líneas: {$data['metrics']['lines']}, ";
                echo "Complejidad: {$data['metrics']['complexity_score']})\n";
            }
            echo "\n";
        }
        
        echo "⏱️  Tiempo estimado de migración: {$this->migrationPlan['estimated_time']} horas\n";
        echo "📝 Orden de ejecución: " . implode(' → ', $this->migrationPlan['execution_order']) . "\n\n";
    }
    
    public function saveAnalysisReport() 
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'legacy_files' => $this->legacyFiles,
            'migration_plan' => $this->migrationPlan,
            'summary' => [
                'total_files' => count($this->legacyFiles),
                'total_controllers' => count($this->migrationPlan['controllers']),
                'estimated_hours' => $this->migrationPlan['estimated_time']
            ]
        ];
        
        $reportPath = $this->rootPath . '/phase4/reports/legacy-analysis-report.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "💾 Reporte guardado en: $reportPath\n\n";
        return $reportPath;
    }
}

// Ejecutar análisis
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $analyzer = new LegacyFileAnalyzer();
    $analyzer->analyzeLegacyFiles();
    $analyzer->saveAnalysisReport();
    
    echo "🚀 ANÁLISIS COMPLETADO. Iniciando creación de estructura MVC...\n";
    echo "   Ejecute: php phase4/create-mvc-structure.php\n\n";
}
