<?php
/**
 * Verificación del sistema Bold PSE sin conexión a base de datos
 * Verifica configuración, archivos y funcionalidad del sistema
 */

echo "=== VERIFICACIÓN DEL SISTEMA BOLD PSE ===\n\n";

// 1. Verificar archivos críticos
echo "1. VERIFICACIÓN DE ARCHIVOS CRÍTICOS:\n";
$archivos_criticos = [
    'index.php' => 'Formulario principal con integración Bold',
    'bold_payment.php' => 'Ventana de pago seguro Bold',
    'bold_webhook_enhanced.php' => 'Webhook principal mejorado',
    'bold_hash.php' => 'Generador de hash Bold',
    'dual_mode_config.php' => 'Configuración dual mode',
    'bold_notification_system.php' => 'Sistema de notificaciones',
    'conexion.php' => 'Conexión a base de datos'
];

foreach ($archivos_criticos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - $descripcion\n";
    } else {
        echo "❌ $archivo - FALTA - $descripcion\n";
    }
}

echo "\n";

// 2. Verificar configuración dual mode
echo "2. VERIFICACIÓN DE CONFIGURACIÓN DUAL MODE:\n";
if (file_exists('dual_mode_config.php')) {
    include 'dual_mode_config.php';
    echo "✅ Configuración cargada correctamente\n";
    echo "📊 ENHANCED_WEBHOOK_PERCENTAGE: " . (defined('ENHANCED_WEBHOOK_PERCENTAGE') ? ENHANCED_WEBHOOK_PERCENTAGE : 'NO DEFINIDO') . "%\n";
    echo "🔧 WEBHOOK_MODE: " . (defined('WEBHOOK_MODE') ? WEBHOOK_MODE : 'NO DEFINIDO') . "\n";
    echo "🌐 ENVIRONMENT: " . (defined('ENVIRONMENT') ? ENVIRONMENT : 'NO DEFINIDO') . "\n";
} else {
    echo "❌ No se encontró dual_mode_config.php\n";
}

echo "\n";

// 3. Verificar configuración Bold
echo "3. VERIFICACIÓN DE CONFIGURACIÓN BOLD:\n";
if (file_exists('bold_hash.php')) {
    $contenido_hash = file_get_contents('bold_hash.php');
    
    // Verificar que tenga las constantes de producción
    if (strpos($contenido_hash, 'x_login') !== false) {
        echo "✅ Configuración x_login encontrada\n";
    } else {
        echo "❌ Configuración x_login no encontrada\n";
    }
    
    if (strpos($contenido_hash, 'x_trans_key') !== false) {
        echo "✅ Configuración x_trans_key encontrada\n";
    } else {
        echo "❌ Configuración x_trans_key no encontrada\n";
    }
    
    if (strpos($contenido_hash, 'api_key') !== false) {
        echo "✅ Configuración api_key encontrada\n";
    } else {
        echo "❌ Configuración api_key no encontrada\n";
    }
} else {
    echo "❌ No se encontró bold_hash.php\n";
}

echo "\n";

// 4. Verificar funciones JavaScript
echo "4. VERIFICACIÓN DE FUNCIONES JAVASCRIPT:\n";
if (file_exists('index.php')) {
    $contenido_index = file_get_contents('index.php');
    
    if (strpos($contenido_index, 'initializeBoldPayment()') !== false) {
        echo "✅ Función initializeBoldPayment() encontrada\n";
    } else {
        echo "❌ Función initializeBoldPayment() no encontrada\n";
    }
    
    if (strpos($contenido_index, 'procesarPagoBold()') !== false) {
        echo "✅ Función procesarPagoBold() encontrada\n";
    } else {
        echo "❌ Función procesarPagoBold() no encontrada\n";
    }
    
    if (strpos($contenido_index, 'PSE Bold') !== false) {
        echo "✅ Opción 'PSE Bold' encontrada en interfaz\n";
    } else {
        echo "❌ Opción 'PSE Bold' no encontrada en interfaz\n";
    }
} else {
    echo "❌ No se encontró index.php\n";
}

echo "\n";

// 5. Verificar webhook mejorado
echo "5. VERIFICACIÓN DE WEBHOOK MEJORADO:\n";
if (file_exists('bold_webhook_enhanced.php')) {
    $contenido_webhook = file_get_contents('bold_webhook_enhanced.php');
    
    if (strpos($contenido_webhook, 'validateBoldSignature') !== false) {
        echo "✅ Función validateBoldSignature encontrada\n";
    } else {
        echo "❌ Función validateBoldSignature no encontrada\n";
    }
    
    if (strpos($contenido_webhook, 'processBoldWebhook') !== false) {
        echo "✅ Función processBoldWebhook encontrada\n";
    } else {
        echo "❌ Función processBoldWebhook no encontrada\n";
    }
    
    if (strpos($contenido_webhook, 'sendNotifications') !== false) {
        echo "✅ Función sendNotifications encontrada\n";
    } else {
        echo "❌ Función sendNotifications no encontrada\n";
    }
} else {
    echo "❌ No se encontró bold_webhook_enhanced.php\n";
}

echo "\n";

// 6. Verificar permisos de archivos
echo "6. VERIFICACIÓN DE PERMISOS:\n";
$archivos_permisos = ['bold_webhook_enhanced.php', 'bold_payment.php', 'bold_hash.php'];
foreach ($archivos_permisos as $archivo) {
    if (file_exists($archivo)) {
        $permisos = substr(sprintf('%o', fileperms($archivo)), -4);
        echo "📁 $archivo: $permisos\n";
    }
}

echo "\n";

// 7. Verificar logs y directorios
echo "7. VERIFICACIÓN DE DIRECTORIOS:\n";
$directorios = ['logs', 'comprobantes', 'uploads'];
foreach ($directorios as $dir) {
    if (is_dir($dir)) {
        echo "✅ Directorio $dir existe\n";
    } else {
        echo "❌ Directorio $dir no existe\n";
    }
}

echo "\n";

// 8. Verificar versión PHP
echo "8. INFORMACIÓN DEL SISTEMA:\n";
echo "🐘 Versión PHP: " . PHP_VERSION . "\n";
echo "🔧 Extensiones requeridas:\n";
echo "   - mysqli: " . (extension_loaded('mysqli') ? '✅ Disponible' : '❌ No disponible') . "\n";
echo "   - curl: " . (extension_loaded('curl') ? '✅ Disponible' : '❌ No disponible') . "\n";
echo "   - json: " . (extension_loaded('json') ? '✅ Disponible' : '❌ No disponible') . "\n";
echo "   - openssl: " . (extension_loaded('openssl') ? '✅ Disponible' : '❌ No disponible') . "\n";

echo "\n=== VERIFICACIÓN COMPLETADA ===\n";
echo "📝 Resumen: El sistema Bold PSE ha sido verificado.\n";
echo "🔗 Para probar el pedido #91, accede a: http://localhost:8080/index.php?pedido=91\n";
echo "🧪 Para test completo, accede a: http://localhost:8080/test_pedido_91.html\n";
?>
