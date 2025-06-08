<?php
/**
 * Analizador de Performance FASE 3 - Sequoia Speed
 * Análisis directo sin framework complejo
 */

echo "⚡ ANÁLISIS DE PERFORMANCE FASE 3\n";
echo "================================\n\n";

// 1. Análisis de archivos
echo "📁 Analizando estructura de archivos...\n";

$fileAnalysis = [
    "migration_helper" => "../migration-helper.php",
    "legacy_bridge" => "../legacy-bridge.php",
    "verificacion" => "../verificacion-fase2.php"
];

$fileStats = [];

foreach ($fileAnalysis as $key => $file) {
    if (file_exists($file)) {
        $size = filesize($file);
        $lines = count(file($file));
        $fileStats[$key] = [
            "size" => $size,
            "size_kb" => round($size / 1024, 2),
            "lines" => $lines,
            "exists" => true
        ];
        echo "✓ " . basename($file) . " - {$fileStats[$key]['size_kb']} KB, {$lines} líneas\n";
    } else {
        $fileStats[$key] = ["exists" => false];
        echo "✗ " . basename($file) . " - NO EXISTE\n";
    }
}

// 2. Análisis de APIs
echo "\n🌐 Analizando APIs migradas...\n";

$apiAnalysis = [
    "pedidos_create" => "../public/api/pedidos/create.php",
    "pedidos_update" => "../public/api/pedidos/update-status.php",
    "bold_webhook" => "../public/api/bold/webhook.php",
    "productos_category" => "../public/api/productos/by-category.php",
    "exports_excel" => "../public/api/exports/excel.php"
];

$apiStats = [];

foreach ($apiAnalysis as $key => $api) {
    if (file_exists($api)) {
        $size = filesize($api);
        $content = file_get_contents($api);
        $hasJson = strpos($content, 'json') !== false;
        $hasHeader = strpos($content, 'header') !== false;
        
        $apiStats[$key] = [
            "size" => $size,
            "size_kb" => round($size / 1024, 2),
            "has_json" => $hasJson,
            "has_headers" => $hasHeader,
            "exists" => true
        ];
        echo "✓ " . basename(dirname($api)) . "/" . basename($api) . " - {$apiStats[$key]['size_kb']} KB";
        echo $hasJson ? " (JSON)" : "";
        echo $hasHeader ? " (Headers)" : "";
        echo "\n";
    } else {
        $apiStats[$key] = ["exists" => false];
        echo "✗ " . basename(dirname($api)) . "/" . basename($api) . " - NO EXISTE\n";
    }
}

// 3. Análisis de assets
echo "\n📦 Analizando assets modernos...\n";

$assetAnalysis = [
    "bold_integration" => "../public/assets/js/bold-integration.js",
    "legacy_compatibility" => "../public/assets/js/legacy-compatibility.js", 
    "asset_updater" => "../public/assets/js/asset-updater.js"
];

$assetStats = [];
$totalAssetSize = 0;

foreach ($assetAnalysis as $key => $asset) {
    if (file_exists($asset)) {
        $size = filesize($asset);
        $totalAssetSize += $size;
        $assetStats[$key] = [
            "size" => $size,
            "size_kb" => round($size / 1024, 2),
            "exists" => true
        ];
        echo "✓ " . basename($asset) . " - {$assetStats[$key]['size_kb']} KB\n";
    } else {
        $assetStats[$key] = ["exists" => false];
        echo "✗ " . basename($asset) . " - NO EXISTE\n";
    }
}

// 4. Métricas de performance
echo "\n⏱️ Calculando métricas de performance...\n";

$startTime = microtime(true);
$startMemory = memory_get_usage();

// Simular carga del sistema
for ($i = 0; $i < 100; $i++) {
    $temp = str_repeat("x", 1000);
}

$endTime = microtime(true);
$endMemory = memory_get_usage();

$performanceMetrics = [
    "execution_time" => round(($endTime - $startTime) * 1000, 2),
    "memory_used" => round(($endMemory - $startMemory) / 1024, 2),
    "peak_memory" => round(memory_get_peak_usage() / 1024 / 1024, 2),
    "total_asset_size" => round($totalAssetSize / 1024, 2)
];

echo "Tiempo de ejecución: {$performanceMetrics['execution_time']} ms\n";
echo "Memoria utilizada: {$performanceMetrics['memory_used']} KB\n";
echo "Pico de memoria: {$performanceMetrics['peak_memory']} MB\n";
echo "Tamaño total assets: {$performanceMetrics['total_asset_size']} KB\n";

// 5. Evaluación general
echo "\n📊 EVALUACIÓN GENERAL:\n";
echo "=====================\n";

$totalFiles = count($fileAnalysis);
$existingFiles = count(array_filter($fileStats, function($stat) { return $stat['exists']; }));

$totalApis = count($apiAnalysis);
$existingApis = count(array_filter($apiStats, function($stat) { return $stat['exists']; }));

$totalAssets = count($assetAnalysis);
$existingAssets = count(array_filter($assetStats, function($stat) { return $stat['exists']; }));

$overallScore = round((($existingFiles + $existingApis + $existingAssets) / ($totalFiles + $totalApis + $totalAssets)) * 100, 2);

echo "Archivos principales: $existingFiles/$totalFiles\n";
echo "APIs migradas: $existingApis/$totalApis\n";
echo "Assets modernos: $existingAssets/$totalAssets\n";
echo "Puntuación general: $overallScore%\n\n";

// Status del sistema
if ($overallScore >= 90) {
    echo "🎉 SISTEMA LISTO PARA OPTIMIZACIÓN FASE 3\n";
    $status = "ready";
} elseif ($overallScore >= 70) {
    echo "⚠️ SISTEMA PARCIALMENTE LISTO - Revisar componentes faltantes\n";
    $status = "partial";
} else {
    echo "❌ SISTEMA NO LISTO - Faltan componentes críticos\n";
    $status = "not_ready";
}

// Guardar resultados
$report = [
    "timestamp" => date("Y-m-d H:i:s"),
    "phase" => "FASE 3 - Performance Analysis",
    "file_stats" => $fileStats,
    "api_stats" => $apiStats,
    "asset_stats" => $assetStats,
    "performance_metrics" => $performanceMetrics,
    "summary" => [
        "files_existing" => $existingFiles,
        "files_total" => $totalFiles,
        "apis_existing" => $existingApis,
        "apis_total" => $totalApis,
        "assets_existing" => $existingAssets,
        "assets_total" => $totalAssets,
        "overall_score" => $overallScore,
        "status" => $status
    ]
];

// Crear directorio si no existe
if (!is_dir("reports")) {
    mkdir("reports", 0755, true);
}

file_put_contents("reports/performance-analysis.json", json_encode($report, JSON_PRETTY_PRINT));
echo "💾 Reporte completo guardado en: reports/performance-analysis.json\n";

echo "\n🚀 PRÓXIMOS PASOS FASE 3:\n";
echo "========================\n";
echo "1. Optimizar queries de base de datos\n";
echo "2. Implementar sistema de cache\n";
echo "3. Minificar assets JavaScript\n";
echo "4. Consolidar código duplicado\n";
echo "5. Implementar testing automatizado\n\n";

return $report;
