<?php
/**
 * Test simple para verificar entorno FASE 3
 */

echo "🧪 Test Simple - FASE 3 Sequoia Speed\n";
echo "=====================================\n\n";

// Test 1: Verificar estructura
echo "📁 Test 1: Verificando estructura de archivos...\n";

$requiredFiles = [
    "../migration-helper.php",
    "../legacy-bridge.php", 
    "../public/api/pedidos/create.php",
    "../public/api/bold/webhook.php"
];

$passed = 0;
$total = count($requiredFiles);

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✓ " . basename($file) . " - OK\n";
        $passed++;
    } else {
        echo "✗ " . basename($file) . " - FALTA\n";
    }
}

echo "\nResultado Test 1: $passed/$total archivos encontrados\n\n";

// Test 2: Verificar APIs
echo "🌐 Test 2: Verificando APIs migradas...\n";

$apiEndpoints = [
    "../public/api/pedidos/create.php",
    "../public/api/pedidos/update-status.php",
    "../public/api/bold/webhook.php",
    "../public/api/productos/by-category.php",
    "../public/api/exports/excel.php"
];

$apiPassed = 0;
$apiTotal = count($apiEndpoints);

foreach ($apiEndpoints as $api) {
    if (file_exists($api)) {
        echo "✓ " . basename(dirname($api)) . "/" . basename($api) . " - OK\n";
        $apiPassed++;
    } else {
        echo "✗ " . basename(dirname($api)) . "/" . basename($api) . " - FALTA\n";
    }
}

echo "\nResultado Test 2: $apiPassed/$apiTotal APIs encontradas\n\n";

// Test 3: Verificar assets
echo "📦 Test 3: Verificando assets modernos...\n";

$assets = [
    "../public/assets/js/bold-integration.js",
    "../public/assets/js/legacy-compatibility.js",
    "../public/assets/js/asset-updater.js"
];

$assetPassed = 0;
$assetTotal = count($assets);

foreach ($assets as $asset) {
    if (file_exists($asset)) {
        $size = round(filesize($asset) / 1024, 2);
        echo "✓ " . basename($asset) . " - OK ($size KB)\n";
        $assetPassed++;
    } else {
        echo "✗ " . basename($asset) . " - FALTA\n";
    }
}

echo "\nResultado Test 3: $assetPassed/$assetTotal assets encontrados\n\n";

// Resumen final
$totalTests = $total + $apiTotal + $assetTotal;
$totalPassed = $passed + $apiPassed + $assetPassed;
$successRate = round(($totalPassed / $totalTests) * 100, 2);

echo "📊 RESUMEN FINAL:\n";
echo "=================\n";
echo "Total tests: $totalTests\n";
echo "Pasados: $totalPassed\n";
echo "Fallidos: " . ($totalTests - $totalPassed) . "\n";
echo "Tasa de éxito: $successRate%\n\n";

if ($successRate >= 90) {
    echo "🎉 Sistema listo para continuar FASE 3!\n";
} elseif ($successRate >= 70) {
    echo "⚠️  Sistema parcialmente listo - revisar archivos faltantes\n";
} else {
    echo "❌ Sistema no está listo - faltan componentes críticos\n";
}

// Guardar resultados
$results = [
    "timestamp" => date("Y-m-d H:i:s"),
    "total_tests" => $totalTests,
    "passed" => $totalPassed,
    "failed" => $totalTests - $totalPassed,
    "success_rate" => $successRate,
    "status" => $successRate >= 90 ? "ready" : ($successRate >= 70 ? "partial" : "not_ready")
];

if (!is_dir("reports")) {
    mkdir("reports", 0755, true);
}

file_put_contents("reports/simple-test-results.json", json_encode($results, JSON_PRETTY_PRINT));
echo "💾 Resultados guardados en reports/simple-test-results.json\n";
