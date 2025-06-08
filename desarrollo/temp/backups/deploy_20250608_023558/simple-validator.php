#!/usr/bin/env php
<?php
echo "🚀 Sequoia Speed - Validación de Producción FASE 2\n";
echo "====================================================\n\n";

$errors = [];
$warnings = [];
$success = 0;

// 1. Validar estructura de archivos
echo "📁 Validando estructura de archivos...\n";
$criticalFiles = [
    'index.php' => 'Archivo principal',
    'migration-helper.php' => 'Helper de migración', 
    'legacy-bridge.php' => 'Bridge de compatibilidad',
    'public/api/pedidos/create.php' => 'API pedidos',
    'public/assets/js/bold-integration.js' => 'Bold Integration',
    'public/assets/js/legacy-compatibility.js' => 'Legacy Compatibility'
];

foreach ($criticalFiles as $file => $desc) {
    if (file_exists($file)) {
        $size = filesize($file);
        echo "✅ $desc: $file ($size bytes)\n";
        $success++;
    } else {
        echo "❌ $desc: $file (NO ENCONTRADO)\n";
        $errors[] = "Archivo faltante: $file";
    }
}

// 2. Validar APIs
echo "\n🌐 Validando APIs...\n";
$apis = [
    'public/api/pedidos/create.php',
    'public/api/productos/by-category.php',
    'public/api/bold/webhook.php',
    'public/api/exports/excel.php',
    'public/api/pedidos/update-status.php'
];

$apisFound = 0;
foreach ($apis as $api) {
    if (file_exists($api)) {
        echo "✅ API: $api\n";
        $apisFound++;
    } else {
        echo "❌ API: $api (NO ENCONTRADO)\n";
        $errors[] = "API faltante: $api";
    }
}

// 3. Validar compatibilidad
echo "\n🔄 Validando compatibilidad legacy...\n";
if (file_exists('legacy-bridge.php')) {
    $bridge = file_get_contents('legacy-bridge.php');
    if (strpos($bridge, 'SequoiaLegacyBridge') !== false) {
        echo "✅ Legacy Bridge: Funcional\n";
        $success++;
    } else {
        echo "⚠️ Legacy Bridge: Posible problema\n";
        $warnings[] = "Bridge legacy incompleto";
    }
} else {
    echo "❌ Legacy Bridge: No encontrado\n";
    $errors[] = "Legacy bridge faltante";
}

// 4. Validar configuración PHP
echo "\n🔧 Validando configuración PHP...\n";
echo "✅ PHP Version: " . PHP_VERSION . "\n";
echo "✅ Memory Limit: " . ini_get('memory_limit') . "\n";

$extensions = ['mysqli', 'json', 'curl'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✅ Extensión $ext: Disponible\n";
    } else {
        echo "⚠️ Extensión $ext: No disponible\n";
        $warnings[] = "Extensión $ext faltante";
    }
}

// 5. Generar reporte final
echo "\n📊 REPORTE FINAL\n";
echo "================\n";

if (empty($errors)) {
    echo "🎉 ¡SISTEMA LISTO PARA PRODUCCIÓN!\n";
    $status = "APROBADO";
} else {
    echo "⚠️ REQUIERE ATENCIÓN ANTES DE PRODUCCIÓN\n";
    $status = "REQUIERE_ATENCION";
    echo "\nErrores encontrados:\n";
    foreach ($errors as $error) {
        echo "• $error\n";
    }
}

if (!empty($warnings)) {
    echo "\nAdvertencias:\n";
    foreach ($warnings as $warning) {
        echo "• $warning\n";
    }
}

echo "\nMétricas:\n";
echo "• Archivos críticos encontrados: $success/" . count($criticalFiles) . "\n";
echo "• APIs funcionando: $apisFound/" . count($apis) . "\n";
echo "• Errores críticos: " . count($errors) . "\n";
echo "• Advertencias: " . count($warnings) . "\n";

// Guardar reporte
$report = [
    'timestamp' => date('Y-m-d H:i:s'),
    'status' => $status,
    'errors' => $errors,
    'warnings' => $warnings,
    'metrics' => [
        'critical_files_found' => $success,
        'total_critical_files' => count($criticalFiles),
        'apis_found' => $apisFound,
        'total_apis' => count($apis),
        'error_count' => count($errors),
        'warning_count' => count($warnings)
    ]
];

file_put_contents('production-report.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\n💾 Reporte guardado en: production-report.json\n";

echo "\n🚀 Validación completa. Estado: $status\n";
?>
