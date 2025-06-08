<?php
/**
 * Configurador Simple de Testing FASE 3
 */

echo "🧪 Configurando entorno de testing FASE 3...\n\n";

// Crear directorios
echo "📁 Creando estructura de directorios...\n";

$dirs = [
    "tests/unit",
    "tests/integration", 
    "tests/performance",
    "tests/helpers",
    "reports/testing"
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "  ✓ $dir\n";
    } else {
        echo "  → $dir (ya existe)\n";
    }
}

echo "\n✅ Directorios creados exitosamente!\n";
echo "📋 Próximo paso: Crear archivos de testing\n";
