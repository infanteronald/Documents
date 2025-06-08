<?php
// Test simple del optimizador de assets

$basePath = dirname(__DIR__, 2);

echo "Optimizando assets...\n";

// Buscar archivos JS y CSS
$jsFiles = glob($basePath . '/*.js');
$cssFiles = glob($basePath . '/*.css');

echo "JS files encontrados: " . count($jsFiles) . "\n";
echo "CSS files encontrados: " . count($cssFiles) . "\n";

foreach ($jsFiles as $file) {
    echo "JS: " . basename($file) . "\n";
}

foreach ($cssFiles as $file) {
    echo "CSS: " . basename($file) . "\n";
}

// Crear directorios
$dirs = [
    $basePath . '/assets/optimized/js',
    $basePath . '/assets/optimized/css',
    $basePath . '/assets/combined'
];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Directorio creado: $dir\n";
    }
}

// Optimizar archivos encontrados
foreach ($jsFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file, '.js');
    
    // Minificación básica
    $minified = preg_replace('/\/\/.*$/m', '', $content); // Comentarios línea
    $minified = preg_replace('/\s+/', ' ', $minified); // Espacios múltiples
    $minified = trim($minified);
    
    $outputFile = $basePath . '/assets/optimized/js/' . $filename . '.min.js';
    file_put_contents($outputFile, $minified);
    echo "Optimizado: {$filename}.js → {$filename}.min.js\n";
}

foreach ($cssFiles as $file) {
    $content = file_get_contents($file);
    $filename = basename($file, '.css');
    
    // Minificación básica
    $minified = preg_replace('/\/\*[\s\S]*?\*\//', '', $content); // Comentarios
    $minified = preg_replace('/\s+/', ' ', $minified); // Espacios múltiples
    $minified = trim($minified);
    
    $outputFile = $basePath . '/assets/optimized/css/' . $filename . '.min.css';
    file_put_contents($outputFile, $minified);
    echo "Optimizado: {$filename}.css → {$filename}.min.css\n";
}

echo "Optimización completada!\n";
?>
