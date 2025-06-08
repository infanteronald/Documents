<?php
echo "🗄️ OPTIMIZADOR DE BASE DE DATOS FASE 3\n";
echo "======================================\n\n";

echo "🔍 Iniciando análisis de base de datos...\n";

// Verificar conexión a base de datos
$configFile = dirname(__DIR__, 2) . '/config/database.php';
if (!file_exists($configFile)) {
    echo "❌ No se encontró archivo de configuración de BD\n";
    echo "📁 Buscando archivos de configuración...\n";
    
    $possibleConfigs = [
        dirname(__DIR__, 2) . '/config.php',
        dirname(__DIR__, 2) . '/config/config.php',
        dirname(__DIR__, 2) . '/includes/config.php',
        dirname(__DIR__, 2) . '/db_config.php'
    ];
    
    foreach ($possibleConfigs as $config) {
        if (file_exists($config)) {
            echo "✅ Encontrado: " . basename($config) . "\n";
        }
    }
} else {
    echo "✅ Configuración de BD encontrada\n";
}

echo "\n📊 Analizando archivos PHP para consultas SQL...\n";

$phpFiles = glob(dirname(__DIR__, 2) . "/*.php");
$queryCount = 0;
$filesAnalyzed = 0;

foreach ($phpFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    // Buscar consultas SQL
    $sqlPatterns = [
        '/SELECT\s+.*?FROM\s+(\w+)/i',
        '/INSERT\s+INTO\s+(\w+)/i',
        '/UPDATE\s+(\w+)\s+SET/i',
        '/DELETE\s+FROM\s+(\w+)/i'
    ];
    
    $fileQueries = 0;
    foreach ($sqlPatterns as $pattern) {
        $fileQueries += preg_match_all($pattern, $content);
    }
    
    if ($fileQueries > 0) {
        $queryCount += $fileQueries;
        $filesAnalyzed++;
        echo "  📄 {$filename}: {$fileQueries} consultas\n";
    }
}

echo "\n📈 RESUMEN DEL ANÁLISIS:\n";
echo "========================\n";
echo "• Archivos analizados: " . count($phpFiles) . "\n";
echo "• Archivos con consultas SQL: {$filesAnalyzed}\n";
echo "• Total consultas encontradas: {$queryCount}\n";

echo "\n✅ Análisis completado!\n";
echo "\n🚀 PRÓXIMO PASO: Crear optimizador de assets\n";
?>
