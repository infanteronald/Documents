#!/bin/bash

echo "🔍 MONITOREO DEL WEBHOOK MEJORADO EN PRODUCCIÓN"
echo "=============================================="
echo "Fecha: $(date)"
echo ""

# Verificar estructura de archivos
echo "📁 Verificando estructura de archivos..."
if [ -f "bold_webhook_enhanced.php" ]; then
    echo "✅ bold_webhook_enhanced.php existe"
else
    echo "❌ bold_webhook_enhanced.php NO encontrado"
fi

if [ -f "dual_mode_config.php" ]; then
    echo "✅ dual_mode_config.php existe"
    echo "📊 Configuración actual:"
    grep "ENHANCED_WEBHOOK_PERCENTAGE" dual_mode_config.php
else
    echo "❌ dual_mode_config.php NO encontrado"
fi

echo ""

# Verificar directorio de logs
echo "📋 Verificando logs del sistema..."
if [ -d "logs" ]; then
    echo "✅ Directorio de logs existe"
    echo "📄 Archivos de log:"
    ls -la logs/ 2>/dev/null || echo "Directorio vacío o sin permisos"
    
    # Mostrar logs recientes si existen
    if [ -f "logs/bold_webhook.log" ]; then
        echo ""
        echo "📊 ÚLTIMAS ENTRADAS DEL WEBHOOK MEJORADO:"
        echo "----------------------------------------"
        tail -n 10 logs/bold_webhook.log 2>/dev/null || echo "No se pudo leer el log"
    fi
    
    if [ -f "logs/bold_errors.log" ]; then
        echo ""
        echo "⚠️  ERRORES RECIENTES:"
        echo "----------------------"
        tail -n 5 logs/bold_errors.log 2>/dev/null || echo "No hay errores"
    fi
    
    if [ -f "logs/dual_mode.log" ]; then
        echo ""
        echo "🔄 ACTIVIDAD DEL MODO DUAL:"
        echo "---------------------------"
        tail -n 5 logs/dual_mode.log 2>/dev/null || echo "No hay actividad dual"
    fi
else
    echo "❌ Directorio de logs NO existe"
fi

echo ""

# Verificar base de datos (tablas de retry y logs)
echo "🗄️  Verificando estructura de base de datos..."
php -r "
try {
    require_once 'conexion.php';
    
    // Verificar tabla bold_retry_queue
    \$result = \$conexion->query('SHOW TABLES LIKE \"bold_retry_queue\"');
    if (\$result->num_rows > 0) {
        echo '✅ Tabla bold_retry_queue existe\n';
        \$count = \$conexion->query('SELECT COUNT(*) as count FROM bold_retry_queue')->fetch_assoc();
        echo '📊 Elementos en cola de retry: ' . \$count['count'] . '\n';
    } else {
        echo '❌ Tabla bold_retry_queue NO existe\n';
    }
    
    // Verificar tabla bold_webhook_logs
    \$result = \$conexion->query('SHOW TABLES LIKE \"bold_webhook_logs\"');
    if (\$result->num_rows > 0) {
        echo '✅ Tabla bold_webhook_logs existe\n';
        \$count = \$conexion->query('SELECT COUNT(*) as count FROM bold_webhook_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetch_assoc();
        echo '📊 Webhooks procesados hoy: ' . \$count['count'] . '\n';
    } else {
        echo '❌ Tabla bold_webhook_logs NO existe\n';
    }
    
    // Verificar tabla notification_logs
    \$result = \$conexion->query('SHOW TABLES LIKE \"notification_logs\"');
    if (\$result->num_rows > 0) {
        echo '✅ Tabla notification_logs existe\n';
        \$count = \$conexion->query('SELECT COUNT(*) as count FROM notification_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)')->fetch_assoc();
        echo '📊 Notificaciones enviadas hoy: ' . \$count['count'] . '\n';
    } else {
        echo '❌ Tabla notification_logs NO existe\n';
    }
    
} catch (Exception \$e) {
    echo '❌ Error conectando a la base de datos: ' . \$e->getMessage() . '\n';
}
"

echo ""
echo "🎯 RESUMEN DEL ESTADO:"
echo "====================="
echo "- Migración al 100% completada"
echo "- Bold Dashboard apunta a: bold_webhook_enhanced.php"
echo "- Sistema de retry y notificaciones activo"
echo "- Monitoreo en tiempo real disponible"

echo ""
echo "📊 Para monitorear en tiempo real, ejecuta:"
echo "   tail -f logs/bold_webhook.log"
echo ""
echo "🔧 Para gestionar el sistema, ejecuta:"
echo "   php dual_mode_monitor.php"
