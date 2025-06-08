<?php
echo "🚀 Sequoia Speed - Validación Rápida de Producción\n";
echo "================================================\n\n";

// Verificar archivos críticos
$files = [
    'index.php',
    'migration-helper.php', 
    'legacy-bridge.php',
    'public/api/pedidos/create.php',
    'public/assets/js/bold-integration.js'
];

echo "📁 Verificando archivos críticos:\n";
$found = 0;
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file\n";
        $found++;
    } else {
        echo "❌ $file\n";
    }
}

echo "\n📊 Resultado: $found/" . count($files) . " archivos encontrados\n";

if ($found == count($files)) {
    echo "🎉 ¡SISTEMA LISTO PARA PRODUCCIÓN!\n";
} else {
    echo "⚠️ Faltan archivos críticos\n";
}

echo "\n✅ Validación completa\n";
?>
