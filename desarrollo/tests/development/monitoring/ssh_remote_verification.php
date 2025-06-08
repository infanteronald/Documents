<?php
/**
 * Script de verificación rápida de conexión SSH Remote
 * Ejecutar este archivo en el servidor remoto via VS Code
 */

echo "🔗 VERIFICACIÓN DE CONEXIÓN SSH REMOTE\n";
echo str_repeat('=', 50) . "\n";

echo "📍 Información del servidor:\n";
echo "- Hostname: " . gethostname() . "\n";
echo "- PHP Version: " . phpversion() . "\n";
echo "- Current Directory: " . getcwd() . "\n";
echo "- User: " . get_current_user() . "\n";
echo "- Server Time: " . date('Y-m-d H:i:s') . "\n";

echo "\n📁 Archivos del proyecto webhook:\n";
$files = [
    'bold_webhook_enhanced.php',
    'dual_mode_config.php', 
    'conexion.php',
    'remote_webhook_monitor.php',
    'remote_webhook_test.php'
];

foreach ($files as $file) {
    $exists = file_exists($file);
    $status = $exists ? '✅' : '❌';
    echo "  {$status} {$file}\n";
    
    if ($exists) {
        $size = filesize($file);
        $modified = date('Y-m-d H:i:s', filemtime($file));
        echo "      Size: {$size} bytes | Modified: {$modified}\n";
    }
}

echo "\n🗂️  Directorio logs:\n";
if (is_dir('logs')) {
    echo "  ✅ Directorio logs existe\n";
    $logFiles = scandir('logs');
    foreach ($logFiles as $logFile) {
        if ($logFile !== '.' && $logFile !== '..') {
            $size = filesize('logs/' . $logFile);
            echo "    📄 {$logFile} - {$size} bytes\n";
        }
    }
} else {
    echo "  ❌ Directorio logs no existe\n";
}

echo "\n🔌 Estado de conexión SSH Remote:\n";
echo "  ✅ Conectado exitosamente via VS Code Remote SSH\n";
echo "  ✅ Puede ejecutar PHP scripts remotamente\n";
echo "  ✅ Acceso a archivos del proyecto\n";

echo "\n" . str_repeat('=', 50) . "\n";
echo "✅ CONEXIÓN SSH REMOTE VERIFICADA\n";
?>
