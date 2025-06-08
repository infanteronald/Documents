<?php
/**
 * SCRIPT DE REORGANIZACIÓN DEL PROYECTO SEQUOIA SPEED
 * Limpia el directorio raíz moviendo archivos no esenciales
 */

echo "🧹 INICIANDO REORGANIZACIÓN DEL PROYECTO SEQUOIA SPEED\n";
echo "========================================================\n\n";

// Crear directorios de organización
$directorios = [
    'desarrollo/tests',
    'desarrollo/docs', 
    'desarrollo/temp',
    'desarrollo/scripts',
    'desarrollo/backups'
];

foreach ($directorios as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
        echo "✅ Creado directorio: $dir\n";
    }
}

// ARCHIVOS DE PRUEBA A MOVER
$archivos_test = [
    'test_bold_complete.php',
    'test_bold_function.html',
    'test_container_fix.html', 
    'test_error_corregido_final.html',
    'test_error_corregido.html',
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

// ARCHIVOS DE DOCUMENTACIÓN A MOVER
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

// ARCHIVOS TEMPORALES Y SCRIPTS A MOVER
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

// ARCHIVOS DE LOG Y TEMPORALES
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

// MOVER ARCHIVOS DE PRUEBA
echo "\n📋 MOVIENDO ARCHIVOS DE PRUEBA:\n";
echo "--------------------------------\n";
foreach ($archivos_test as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/tests/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → $destino\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

// MOVER DOCUMENTACIÓN
echo "\n📚 MOVIENDO DOCUMENTACIÓN:\n";
echo "---------------------------\n";
foreach ($archivos_docs as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/docs/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → $destino\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

// MOVER SCRIPTS
echo "\n⚙️ MOVIENDO SCRIPTS DE DESARROLLO:\n";
echo "-----------------------------------\n";
foreach ($archivos_scripts as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/scripts/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → $destino\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

// MOVER ARCHIVOS TEMPORALES
echo "\n🗑️ MOVIENDO ARCHIVOS TEMPORALES:\n";
echo "---------------------------------\n";
foreach ($archivos_temp as $archivo) {
    if (file_exists($archivo)) {
        $destino = "desarrollo/temp/$archivo";
        if (rename($archivo, $destino)) {
            echo "✅ Movido: $archivo → $destino\n";
        } else {
            echo "❌ Error moviendo: $archivo\n";
        }
    }
}

// MOVER DIRECTORIOS COMPLETOS DE DESARROLLO
echo "\n📁 MOVIENDO DIRECTORIOS DE DESARROLLO:\n";
echo "---------------------------------------\n";

$directorios_mover = ['phase3', 'phase4', 'tests', 'development'];
foreach ($directorios_mover as $dir) {
    if (file_exists($dir) && is_dir($dir)) {
        $destino = "desarrollo/$dir";
        if (rename($dir, $destino)) {
            echo "✅ Movido directorio: $dir → $destino\n";
        } else {
            echo "❌ Error moviendo directorio: $dir\n";
        }
    }
}

// CREAR LISTA DE ARCHIVOS DE PRODUCCIÓN
echo "\n📋 CREANDO LISTA DE ARCHIVOS DE PRODUCCIÓN:\n";
echo "---------------------------------------------\n";

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
    
    // ARCHIVOS DE CONFIGURACIÓN
    'app_config.php',
    'database_config.php',
    'bootstrap.php',
    'routes.php',
    '.env.production',
    '.htaccess',
    
    // ESTILOS Y SCRIPTS
    'pedidos.css',
    'pedidos.js',
    'estilos.css',
    'script.js',
    'styles.css',
    'style.css',
    
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
    'otros/' // Mantener por compatibilidad
];

$contenido_lista = "# ARCHIVOS DE PRODUCCIÓN - SEQUOIA SPEED\n";
$contenido_lista .= "# Solo estos archivos deben subirse al servidor\n";
$contenido_lista .= "# Generado: " . date('Y-m-d H:i:s') . "\n\n";

foreach ($archivos_produccion as $archivo) {
    if (file_exists($archivo)) {
        $contenido_lista .= "$archivo\n";
    }
}

file_put_contents('ARCHIVOS_PRODUCCION.txt', $contenido_lista);
echo "✅ Lista de archivos de producción creada: ARCHIVOS_PRODUCCION.txt\n";

// CREAR README DE DESARROLLO
$readme_dev = "# DIRECTORIO DE DESARROLLO\n\n";
$readme_dev .= "Este directorio contiene todos los archivos de desarrollo, pruebas y documentación.\n\n";
$readme_dev .= "## Estructura:\n";
$readme_dev .= "- `tests/` - Archivos de prueba y testing\n";
$readme_dev .= "- `docs/` - Documentación del proyecto\n";
$readme_dev .= "- `scripts/` - Scripts de desarrollo y migración\n";
$readme_dev .= "- `temp/` - Archivos temporales y logs\n";
$readme_dev .= "- `backups/` - Respaldos del sistema\n\n";
$readme_dev .= "⚠️ **ESTOS ARCHIVOS NO DEBEN SUBIRSE A PRODUCCIÓN**\n";

file_put_contents('desarrollo/README.md', $readme_dev);

echo "\n🎉 REORGANIZACIÓN COMPLETADA!\n";
echo "==============================\n";
echo "✅ Directorio raíz limpio\n";
echo "✅ Archivos organizados en desarrollo/\n";
echo "✅ Lista de producción creada\n";
echo "✅ Solo archivos esenciales en raíz\n\n";

echo "📋 PRÓXIMOS PASOS:\n";
echo "1. Revisar ARCHIVOS_PRODUCCION.txt\n";
echo "2. Subir solo esos archivos al servidor\n";
echo "3. NO subir el directorio desarrollo/\n\n";

echo "🔒 ARCHIVOS DE PRODUCCIÓN IDENTIFICADOS:\n";
$contador = 0;
foreach ($archivos_produccion as $archivo) {
    if (file_exists($archivo)) {
        $contador++;
    }
}
echo "Total: $contador archivos esenciales\n";
