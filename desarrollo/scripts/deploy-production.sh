#!/bin/bash

# 🚀 SCRIPT DE DEPLOYMENT LIMPIO - SISTEMA SEQUOIA SPEED
# Post-Reorganización Masiva - Solo archivos de producción

echo "🚀 DEPLOYMENT LIMPIO - SEQUOIA SPEED"
echo "====================================="
echo "Fecha: $(date)"
echo ""

# Configuración (AJUSTAR SEGÚN TU SERVIDOR)
LOCAL_PATH="/Users/ronaldinfante/Documents/pedidos"
REMOTE_HOST="usuario@sequoiaspeed.com.co"
REMOTE_PATH="/path/to/production"
BACKUP_REMOTE_PATH="/path/to/backup/$(date +%Y%m%d_%H%M%S)"

echo "📋 CONFIGURACIÓN:"
echo "----------------"
echo "Local: $LOCAL_PATH"
echo "Remoto: $REMOTE_HOST:$REMOTE_PATH"
echo "Backup: $BACKUP_REMOTE_PATH"
echo ""

# Función de verificación pre-deployment
verify_local_structure() {
    echo "🔍 VERIFICANDO ESTRUCTURA LOCAL..."
    
    # Verificar archivos críticos
    critical_files=("bold_webhook_enhanced.php" "index.php" "conexion.php" "orden_pedido.php")
    
    for file in "${critical_files[@]}"; do
        if [ ! -f "$LOCAL_PATH/$file" ]; then
            echo "❌ ERROR: $file no encontrado"
            exit 1
        fi
    done
    
    echo "✅ Estructura local verificada"
}

# Función de backup remoto
backup_remote() {
    echo "💾 CREANDO BACKUP REMOTO..."
    
    ssh $REMOTE_HOST "mkdir -p $BACKUP_REMOTE_PATH"
    ssh $REMOTE_HOST "cp -r $REMOTE_PATH/* $BACKUP_REMOTE_PATH/ 2>/dev/null || true"
    
    echo "✅ Backup creado en: $BACKUP_REMOTE_PATH"
}

# Función de deployment
deploy_production() {
    echo "📦 DESPLEGANDO ARCHIVOS DE PRODUCCIÓN..."
    
    # Sync solo archivos de producción (EXCLUIR desarrollo/)
    rsync -avz --progress \
        --exclude='desarrollo/' \
        --exclude='.git*' \
        --exclude='.DS_Store' \
        --exclude='*.log' \
        --exclude='node_modules/' \
        --exclude='*.tmp' \
        --exclude='development-monitor.sh' \
        --exclude='deploy-production.sh' \
        "$LOCAL_PATH/" \
        "$REMOTE_HOST:$REMOTE_PATH/"
    
    echo "✅ Deployment completado"
}

# Función de verificación post-deployment
verify_remote() {
    echo "🔍 VERIFICANDO DEPLOYMENT REMOTO..."
    
    # Verificar archivos críticos en remoto
    ssh $REMOTE_HOST "cd $REMOTE_PATH && php -l bold_webhook_enhanced.php" && echo "✅ Webhook syntax OK"
    ssh $REMOTE_HOST "cd $REMOTE_PATH && php -l index.php" && echo "✅ Index syntax OK"
    ssh $REMOTE_HOST "cd $REMOTE_PATH && [ -f styles.css ] && echo '✅ CSS files OK'"
    
    echo "✅ Verificación remota completada"
}

# Función principal
main() {
    echo "🎯 INICIANDO DEPLOYMENT LIMPIO..."
    echo ""
    
    # Paso 1: Verificar estructura local
    verify_local_structure
    
    # Paso 2: Crear backup remoto
    # backup_remote  # Descomenta si quieres backup automático
    
    # Paso 3: Desplegar archivos
    echo ""
    echo "⚠️ IMPORTANTE: Este deployment excluye el directorio 'desarrollo/'"
    echo "Solo se subirán archivos de producción esenciales."
    echo ""
    read -p "¿Continuar con el deployment? (y/N): " confirm
    
    if [[ $confirm == [yY] || $confirm == [yY][eE][sS] ]]; then
        deploy_production
        
        # Paso 4: Verificar deployment
        verify_remote
        
        echo ""
        echo "🎉 DEPLOYMENT COMPLETADO EXITOSAMENTE!"
        echo "📊 Estadísticas:"
        echo "   - Solo archivos de producción subidos"
        echo "   - Directorio 'desarrollo/' excluido correctamente"
        echo "   - Sistema optimizado y limpio"
        echo ""
        echo "🔧 Próximos pasos:"
        echo "   1. Verificar funcionamiento en el servidor"
        echo "   2. Probar Bold webhook en producción"
        echo "   3. Monitorear logs del sistema"
        
    else
        echo "❌ Deployment cancelado por el usuario"
        exit 1
    fi
}

# Ejecutar función principal
main "$@"
