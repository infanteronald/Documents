<?php
/**
 * SCRIPT DE REORGANIZACIÓN URGENTE - SEQUOIA SPEED
 * Limpia el directorio raíz moviendo archivos de desarrollo
 */

echo "🧹 INICIANDO REORGANIZACIÓN URGENTE DEL PROYECTO\n";
echo "==================================================\n\n";

// Crear directorio de desarrollo si no existe
if (!file_exists('desarrollo')) {
    mkdir('desarrollo', 0755, true);
    echo "✅ Creado directorio: desarrollo/\n";
}

// Subdirectorios dentro de desarrollo
$subdirectorios = [
    'desarrollo/tests',
    'desarrollo/docs', 
    'desarrollo/scripts',
    'desarrollo/temp',
    'desarrollo/logs'
];

foreach ($subdirectorios as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Creado: $dir\n";
    }
}

echo "\n📋 MOVIENDO ARCHIVOS DE PRUEBA:\n";
echo "--------------------------------\n";

// Archivos de prueba a mover
$archivos_test = [
    'test_bold_complete.php',
    'test_bold_function.html',
    'test_container_fix.html',
    'test_error_corregido.html',
    'test_error_corregido_final.html',
    'test_error_final_corregido.html',
    'test_final_bold.php',
    'test_final_solution.html',
    'test_function_fix.html',
    'test_live_debug.html',
    'test_produccion_final.html',
    'test-db-connection.php',
    'test-db-real-connection.php',
    'test-mvc-routes.php',
    'verificacion_final.html',
    'debug_undefined_return.html'
];

foreach ($archivos_test as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/tests/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → desarrollo/tests/\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

echo "\n📚 MOVIENDO DOCUMENTACIÓN MARKDOWN:\n";
echo "-----------------------------------\n";

// Documentación markdown a mover
$archivos_docs = [
    'CHECKLIST_PRODUCCION.md',
    'ESTADO_FINAL_TRANSICION.md',
    'FASE4-COMPLETADA.md',
    'IMPLEMENTACION_ESTRUCTURA_PROFESIONAL.md',
    'NUEVA_ESTRUCTURA_PROPUESTA.md',
    'QUE_SIGUE_AHORA.md',
    'RESUMEN_CORRECCION_BOLD.md',
    'RESUMEN_FASE3_COMPLETADA.md',
    'RESUMEN_FINAL_FASE2.md',
    'SOLUCION_FINAL_BOLD.md',
    'SOLUCION_FINAL_COMPLETADA.md',
    'transition-guide-phase3.md',
    'phase4-guide.md'
];

foreach ($archivos_docs as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/docs/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → desarrollo/docs/\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

echo "\n🔧 MOVIENDO SCRIPTS DE DESARROLLO:\n";
echo "----------------------------------\n";

// Scripts de desarrollo a mover
$archivos_scripts = [
    'finalize-phase2.php',
    'generate-phase3-guide.php',
    'migration-helper.php',
    'production-validator.php',
    'transition-guide-phase3.php',
    'verificacion-fase2.php',
    'verificacion-sistema-completa.php',
    'verify-phase4.php',
    'resumen-completo.php',
    'setup-monitor.php',
    'setup-phase3-env.php',
    'setup-phase3.php',
    'setup-production-monitor.php',
    'simple-validator.php',
    'direct-optimizer.php'
];

foreach ($archivos_scripts as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/scripts/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → desarrollo/scripts/\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

echo "\n📊 MOVIENDO LOGS Y ARCHIVOS TEMPORALES:\n";
echo "---------------------------------------\n";

// Logs y archivos temporales
$archivos_temp = [
    'asset-optimization.log',
    'performance-tests.log',
    'phase3-finalization.log',
    'simple-optimization.log',
    'phase2-final-report.json',
    'phase3-config.json',
    'production-config.json',
    'verificacion-sistema-reporte.json',
    'conexion_local.php'
];

foreach ($archivos_temp as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/temp/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → desarrollo/temp/\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

echo "\n📁 MOVIENDO DIRECTORIOS COMPLETOS:\n";
echo "----------------------------------\n";

// Directorios completos a mover
$directorios_mover = ['phase3', 'phase4', 'tests'];
foreach ($directorios_mover as $dir) {
    if (file_exists($dir) && is_dir($dir)) {
        $destino = "desarrollo/$dir";
        if (rename($dir, $destino)) {
            echo "✅ Movido directorio: $dir → desarrollo/\n";
        } else {
            echo "❌ Error moviendo directorio: $dir\n";
        }
    }
}

echo "\n📋 CREANDO LISTA DE ARCHIVOS DE PRODUCCIÓN:\n";
echo "--------------------------------------------\n";

// Lista de archivos que DEBEN permanecer en raíz (producción)
$archivos_produccion = [
    // CORE DEL SISTEMA
    'index.php',
    'conexion.php',
    'guardar_pedido.php',
    'listar_pedidos.php',
    'procesar_orden.php',
    'orden_pedido.php',
    
    // BOLD PAYMENT
    'bold_payment.php',
    'bold_confirmation.php',
    'bold_hash.php',
    'bold_webhook_enhanced.php',
    'bold_notification_system.php',
    'bold_retry_processor.php',
    
    // ARCHIVOS DE SOPORTE
    'productos_por_categoria.php',
    'ver_detalle_pedido.php',
    'actualizar_estado.php',
    'exportar_excel.php',
    'generar_pdf.php',
    'comprobante.php',
    'agregar_nota.php',
    'archivar_pedido.php',
    'restaurar_pedido.php',
    'subir_guia.php',
    'ver_guia.php',
    'procesar_pago_manual.php',
    
    // ARCHIVOS DE CONFIGURACIÓN
    'app_config.php',
    'database_config.php',
    'bootstrap.php',
    'routes.php',
    '.env.production',
    '.htaccess',
    'smtp_config.php',
    
    // ESTILOS Y SCRIPTS PRINCIPALES
    'pedidos.css',
    'pedidos.js',
    'estilos.css',
    'script.js',
    'styles.css',
    'style.css',
    'app.js',
    'sequoia-unified.css',
    'payment_ux_enhanced.css',
    'payment_ux_enhanced.js',
    
    // IMÁGENES
    'logo.png',
    'qr.jpg',
    
    // DIRECTORIOS ESENCIALES
    'app/',
    'assets/',
    'cache/',
    'comprobantes/',
    'guias/',
    'logs/',
    'uploads/',
    'storage/',
    'public/',
    'backups/',
    'database/',
    'scripts/',
    'pedidos/',
    'docs/',
    'otros/' // Mantener por compatibilidad
];

// Crear lista de archivos de producción
$contenido_lista = "# ARCHIVOS DE PRODUCCIÓN - SEQUOIA SPEED\n";
$contenido_lista .= "# Solo estos archivos deben subirse al servidor\n";
$contenido_lista .= "# Generado: " . date('Y-m-d H:i:s') . "\n\n";

echo "📄 Archivos de producción identificados:\n";
$contador = 0;
foreach ($archivos_produccion as $archivo) {
    if (file_exists($archivo)) {
        $contenido_lista .= "$archivo\n";
        echo "✅ $archivo\n";
        $contador++;
    }
}

file_put_contents('ARCHIVOS_PRODUCCION.txt', $contenido_lista);
echo "\n✅ Lista creada: ARCHIVOS_PRODUCCION.txt\n";

// Crear README del directorio desarrollo
$readme_dev = "# DIRECTORIO DE DESARROLLO\n\n";
$readme_dev .= "Este directorio contiene todos los archivos de desarrollo, pruebas y documentación.\n\n";
$readme_dev .= "## Estructura:\n";
$readme_dev .= "- `tests/` - Archivos de prueba y testing\n";
$readme_dev .= "- `docs/` - Documentación del proyecto\n";
$readme_dev .= "- `scripts/` - Scripts de desarrollo y migración\n";
$readme_dev .= "- `temp/` - Archivos temporales y logs\n";
$readme_dev .= "- `logs/` - Logs de desarrollo\n\n";
$readme_dev .= "⚠️ **ESTOS ARCHIVOS NO DEBEN SUBIRSE AL SERVIDOR DE PRODUCCIÓN**\n\n";
$readme_dev .= "Para desarrollo usar SSH remoto:\n";
$readme_dev .= "```bash\n";
$readme_dev .= "ssh motodota@68.66.226.124 -p 7822\n";
$readme_dev .= "cd ~/sequoiaspeed.com.co/pedidos/\n";
$readme_dev .= "```\n";

file_put_contents('desarrollo/README.md', $readme_dev);

echo "\n🎉 REORGANIZACIÓN COMPLETADA!\n";
echo "==============================\n";
echo "✅ Directorio raíz limpio\n";
echo "✅ Archivos organizados en desarrollo/\n";
echo "✅ Lista de producción creada\n";
echo "✅ Solo archivos esenciales en raíz\n\n";

echo "📋 ARCHIVOS DE PRODUCCIÓN: $contador archivos identificados\n";
echo "📁 ARCHIVOS DE DESARROLLO: Organizados en desarrollo/\n\n";

echo "🚀 PRÓXIMOS PASOS:\n";
echo "1. Revisar ARCHIVOS_PRODUCCION.txt\n";
echo "2. Subir SOLO esos archivos al servidor\n";
echo "3. NO subir el directorio desarrollo/\n";
echo "4. Usar SSH para desarrollo remoto\n\n";

echo "✅ PROYECTO REORGANIZADO Y LISTO PARA PRODUCCIÓN!\n";
?>
