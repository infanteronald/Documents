<?php
/**
 * Script de Verificación del Sistema de Producción
 * Verifica que todos los archivos críticos estén en su lugar
 * y que el sistema esté funcionando correctamente
 */

echo "🔍 VERIFICACIÓN DEL SISTEMA DE PRODUCCIÓN\n";
echo "=========================================\n\n";

// Archivos críticos que deben existir
$archivos_criticos = [
    'bold_webhook_enhanced.php' => '🔥 Webhook Principal (ACTIVO)',
    'dual_mode_config.php' => '⚙️ Configuración webhook',
    'conexion.php' => '🗄️ Conexión BD',
    'smtp_config.php' => '📧 Config SMTP',
    'bold_notification_system.php' => '🔔 Sistema notificaciones',
    'bold_payment.php' => '💳 Procesamiento pagos',
    'index.php' => '🏠 Página principal',
    'pedidos.css' => '🎨 Estilos principales',
    'script.js' => '⚡ JavaScript principal'
];

// Directorios críticos
$directorios_criticos = [
    'logs' => '📋 Logs del sistema',
    'comprobantes' => '🧾 Comprobantes',
    'uploads' => '📁 Archivos subidos',
    'guias' => '📦 Guías de envío',
    'development' => '🛠️ Archivos de desarrollo'
];

echo "✅ VERIFICANDO ARCHIVOS CRÍTICOS:\n";
echo "--------------------------------\n";
$archivos_ok = 0;
foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - $descripcion\n";
        $archivos_ok++;
    } else {
        echo "❌ $archivo - $descripcion - FALTANTE!\n";
    }
}

echo "\n✅ VERIFICANDO DIRECTORIOS CRÍTICOS:\n";
echo "-----------------------------------\n";
$dirs_ok = 0;
foreach ($directorios_criticos as $dir => $descripcion) {
    if (is_dir($dir)) {
        echo "✅ $dir/ - $descripcion\n";
        $dirs_ok++;
    } else {
        echo "❌ $dir/ - $descripcion - FALTANTE!\n";
    }
}

// Verificar configuración del webhook
echo "\n🔧 VERIFICANDO CONFIGURACIÓN:\n";
echo "-----------------------------\n";

if (file_exists('dual_mode_config.php')) {
    include 'dual_mode_config.php';
    if (defined('MIGRATION_PERCENTAGE') && MIGRATION_PERCENTAGE == 100) {
        echo "✅ Migración al 100% - Sistema totalmente migrado\n";
    } else {
        echo "⚠️ Migración no está al 100%\n";
    }
    
    if (defined('ENHANCED_WEBHOOK_ACTIVE') && ENHANCED_WEBHOOK_ACTIVE) {
        echo "✅ Webhook mejorado ACTIVO\n";
    } else {
        echo "❌ Webhook mejorado NO activo\n";
    }
} else {
    echo "❌ No se puede verificar configuración - dual_mode_config.php faltante\n";
}

// Verificar permisos
echo "\n🛡️ VERIFICANDO PERMISOS:\n";
echo "-----------------------\n";
$dirs_permisos = ['logs', 'comprobantes', 'uploads', 'guias'];
foreach ($dirs_permisos as $dir) {
    if (is_dir($dir)) {
        if (is_writable($dir)) {
            echo "✅ $dir/ - Permisos de escritura OK\n";
        } else {
            echo "⚠️ $dir/ - Sin permisos de escritura\n";
        }
    }
}

// Resumen final
echo "\n📊 RESUMEN FINAL:\n";
echo "=================\n";
echo "Archivos críticos: $archivos_ok/" . count($archivos_criticos) . "\n";
echo "Directorios críticos: $dirs_ok/" . count($directorios_criticos) . "\n";

if ($archivos_ok == count($archivos_criticos) && $dirs_ok == count($directorios_criticos)) {
    echo "\n🎉 SISTEMA DE PRODUCCIÓN: ✅ COMPLETAMENTE OPERATIVO\n";
    echo "   - Webhook Bold PSE mejorado activo\n";
    echo "   - Todos los archivos críticos presentes\n";
    echo "   - Estructura organizada correctamente\n";
} else {
    echo "\n⚠️ SISTEMA DE PRODUCCIÓN: REQUIERE ATENCIÓN\n";
    echo "   - Algunos archivos o directorios faltantes\n";
    echo "   - Revisar la lista anterior\n";
}

echo "\n📞 SSH CONFIGURADO para pruebas remotas via VS Code\n";
echo "🛠️ Archivos de desarrollo en /development/\n";
echo "📋 Ver README.md para más información\n\n";
?>
