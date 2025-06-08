<?php
$jsContent = file_get_contents('/Users/ronaldinfante/Documents/pedidos/app.js');
$cssContent = file_get_contents('/Users/ronaldinfante/Documents/pedidos/style.css');

// Minificar JS
$minifiedJs = preg_replace('/\/\/.*$/m', '', $jsContent);
$minifiedJs = preg_replace('/\s+/', ' ', $minifiedJs);
$minifiedJs = trim($minifiedJs);

// Minificar CSS
$minifiedCss = preg_replace('/\/\*[\s\S]*?\*\//', '', $cssContent);
$minifiedCss = preg_replace('/\s+/', ' ', $minifiedCss);
$minifiedCss = trim($minifiedCss);

// Crear archivos optimizados
file_put_contents('/Users/ronaldinfante/Documents/pedidos/assets/optimized/js/app.min.js', $minifiedJs);
file_put_contents('/Users/ronaldinfante/Documents/pedidos/assets/optimized/css/style.min.css', $minifiedCss);

// Crear archivo combinado
$combinedJs = $minifiedJs;
$combinedCss = $minifiedCss;

file_put_contents('/Users/ronaldinfante/Documents/pedidos/assets/combined/app.min.js', $combinedJs);
file_put_contents('/Users/ronaldinfante/Documents/pedidos/assets/combined/app.min.css', $combinedCss);

echo "Assets optimizados exitosamente\n";
?>
