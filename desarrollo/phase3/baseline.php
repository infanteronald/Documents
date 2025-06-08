<?php
echo "📊 Analizando baseline para FASE 3...\n";

$phpFiles = glob("*.php");
$jsFiles = glob("public/assets/js/*.js");
$totalSize = array_sum(array_map("filesize", $phpFiles));

echo "• Archivos PHP: " . count($phpFiles) . "\n";
echo "• Archivos JS: " . count($jsFiles) . "\n";
echo "• Tamaño total PHP: " . round($totalSize/1024, 2) . " KB\n";

// Análisis sin ejecutar archivos para evitar errores de BD
$legacyPatterns = 0;
$modernFiles = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    
    // Detectar patrones legacy
    if (strpos($content, 'mysql_') !== false) $legacyPatterns++;
    if (strpos($content, '$_GET') !== false && strpos($content, 'filter_') === false) $legacyPatterns++;
    
    // Detectar archivos modernos
    if (strpos($content, 'class ') !== false) $modernFiles++;
    if (strpos($content, 'namespace ') !== false) $modernFiles++;
}

echo "• Patrones legacy detectados: $legacyPatterns\n";
echo "• Archivos con estructura moderna: $modernFiles\n";

// Verificar APIs migradas
$apis = glob("public/api/*/*.php");
echo "• APIs REST disponibles: " . count($apis) . "\n";

// Memoria actual sin ejecución
$memoryUsage = memory_get_usage();
echo "• Memoria utilizada (análisis): " . round($memoryUsage/1024/1024, 2) . " MB\n";

$report = [
    "timestamp" => date("Y-m-d H:i:s"),
    "phase" => "Pre-FASE 3 Baseline",
    "files" => [
        "php_files" => count($phpFiles),
        "js_files" => count($jsFiles),
        "api_files" => count($apis),
        "modern_files" => $modernFiles
    ],
    "size_analysis" => [
        "total_php_kb" => round($totalSize/1024, 2),
        "avg_file_size_kb" => round($totalSize/1024/count($phpFiles), 2)
    ],
    "quality_metrics" => [
        "legacy_patterns" => $legacyPatterns,
        "modernization_ratio" => round(($modernFiles / count($phpFiles)) * 100, 1)
    ],
    "recommendations" => [
        "priority_high" => [
            "Implementar testing automatizado",
            "Eliminar patrones legacy (" . $legacyPatterns . " detectados)",
            "Optimizar archivos grandes"
        ],
        "priority_medium" => [
            "Migrar " . (count($phpFiles) - $modernFiles) . " archivos a estructura moderna",
            "Documentar " . count($apis) . " APIs existentes",
            "Implementar cache y optimización"
        ]
    ]
];

file_put_contents("phase3/reports/baseline.json", json_encode($report, JSON_PRETTY_PRINT));
echo "\n✅ Baseline completo guardado en phase3/reports/baseline.json\n";

echo "\n📋 RESUMEN BASELINE\n";
echo "==================\n";
echo "• Total archivos PHP: " . count($phpFiles) . "\n";
echo "• APIs modernas: " . count($apis) . "\n";
echo "• Modernización: " . round(($modernFiles / count($phpFiles)) * 100, 1) . "%\n";
echo "• Patrones legacy: $legacyPatterns\n";
echo "• Tamaño total: " . round($totalSize/1024, 2) . " KB\n";

echo "\n🎯 OBJETIVOS FASE 3\n";
echo "==================\n";
echo "• Reducir patrones legacy en 50%\n";
echo "• Alcanzar 90% de cobertura de testing\n";
echo "• Mejorar performance en 40%\n";
echo "• Completar migración MVC al 100%\n";
?>