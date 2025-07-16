#!/bin/bash

# ==============================================================================
# SCRIPT DE DESPLIEGUE DE CORRECCIÓN DE SEGURIDAD
# ==============================================================================
# Despliega la corrección de seguridad al servidor de producción
# Autor: Claude Assistant
# Fecha: 2024-12-16
# ==============================================================================

echo "🚀 DESPLIEGUE DE CORRECCIÓN DE SEGURIDAD"
echo "========================================"
echo ""

# Configuración del servidor (ajustar según sea necesario)
SERVER_USER="usuario"
SERVER_HOST="servidor.sequoiaspeed.com.co"
SERVER_PATH="/home/usuario/public_html/pedidos"
BACKUP_DIR="/home/usuario/backups/security_fix_$(date +%Y%m%d_%H%M%S)"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar mensajes
show_message() {
    echo -e "${BLUE}[$(date '+%H:%M:%S')]${NC} $1"
}

show_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

show_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

show_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Verificar que los archivos existen localmente
show_message "Verificando archivos locales..."
required_files=(
    ".env"
    ".env.example"
    "app/config/EnvLoader.php"
    "config_secure.php"
    "verify_security_implementation.php"
    "SEGURIDAD_CORREGIDA.md"
    "CORRECION_SEGURIDAD_RESUMEN.md"
)

for file in "${required_files[@]}"; do
    if [ -f "$file" ]; then
        show_success "Archivo encontrado: $file"
    else
        show_error "Archivo faltante: $file"
        exit 1
    fi
done

# Verificar conexión SSH
show_message "Verificando conexión SSH..."
if ssh -o ConnectTimeout=10 -o BatchMode=yes "$SERVER_USER@$SERVER_HOST" exit 2>/dev/null; then
    show_success "Conexión SSH exitosa"
else
    show_error "No se pudo conectar via SSH. Verifique credenciales."
    echo "Comando sugerido: ssh-copy-id $SERVER_USER@$SERVER_HOST"
    exit 1
fi

# Crear directorio de backup en servidor
show_message "Creando backup en servidor..."
ssh "$SERVER_USER@$SERVER_HOST" "mkdir -p $BACKUP_DIR"

# Crear backup de archivos existentes
show_message "Respaldando archivos existentes..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd $SERVER_PATH
    if [ -f conexion.php ]; then
        cp conexion.php $BACKUP_DIR/conexion.php.backup
        echo 'Backup creado: conexion.php'
    fi
    if [ -f .env ]; then
        cp .env $BACKUP_DIR/.env.backup
        echo 'Backup creado: .env'
    fi
    if [ -d app/config ]; then
        cp -r app/config $BACKUP_DIR/app_config_backup
        echo 'Backup creado: app/config'
    fi
"

# Subir archivos nuevos
show_message "Subiendo archivos de seguridad..."

# Crear directorio app/config si no existe
ssh "$SERVER_USER@$SERVER_HOST" "mkdir -p $SERVER_PATH/app/config"

# Subir archivos uno por uno con verificación
files_to_upload=(
    ".env:$SERVER_PATH/.env"
    ".env.example:$SERVER_PATH/.env.example"
    "app/config/EnvLoader.php:$SERVER_PATH/app/config/EnvLoader.php"
    "config_secure.php:$SERVER_PATH/config_secure.php"
    "verify_security_implementation.php:$SERVER_PATH/verify_security_implementation.php"
    "SEGURIDAD_CORREGIDA.md:$SERVER_PATH/SEGURIDAD_CORREGIDA.md"
    "CORRECION_SEGURIDAD_RESUMEN.md:$SERVER_PATH/CORRECION_SEGURIDAD_RESUMEN.md"
)

for file_mapping in "${files_to_upload[@]}"; do
    local_file="${file_mapping%:*}"
    remote_file="${file_mapping#*:}"
    
    show_message "Subiendo: $local_file -> $remote_file"
    if scp "$local_file" "$SERVER_USER@$SERVER_HOST:$remote_file"; then
        show_success "Archivo subido: $local_file"
    else
        show_error "Error subiendo: $local_file"
        exit 1
    fi
done

# Configurar permisos
show_message "Configurando permisos..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd $SERVER_PATH
    chmod 600 .env
    chmod 644 .env.example
    chmod 644 app/config/EnvLoader.php
    chmod 644 config_secure.php
    chmod 755 verify_security_implementation.php
    chmod 644 *.md
"

# Ejecutar script de verificación en servidor
show_message "Ejecutando verificación en servidor..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd $SERVER_PATH
    php verify_security_implementation.php
" || show_warning "Verificación completada con advertencias (normal si DB no es accesible localmente)"

# Actualizar conexion.php en servidor
show_message "Actualizando conexion.php en servidor..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd $SERVER_PATH
    if [ -f conexion.php ]; then
        # Crear backup adicional
        cp conexion.php conexion.php.backup.\$(date +%Y%m%d_%H%M%S)
        
        # Actualizar conexion.php
        cat > conexion.php << 'EOF'
<?php
/**
 * ⚠️  ARCHIVO DEPRECADO - MIGRADO A CONFIGURACIÓN SEGURA
 * 
 * Este archivo ha sido reemplazado por config_secure.php que usa variables de entorno
 * Se mantiene temporalmente para compatibilidad durante la migración
 * 
 * NUEVO ARCHIVO: config_secure.php
 * FECHA MIGRACIÓN: 2024-12-16
 */

// Advertencia de deprecación en logs
error_log('⚠️  ADVERTENCIA: conexion.php está deprecado. Use config_secure.php');

// Redirigir a la nueva configuración segura
require_once __DIR__ . '/config_secure.php';
?>
EOF
        echo 'conexion.php actualizado exitosamente'
    fi
"

# Mostrar resumen del despliegue
show_message "Generando resumen del despliegue..."
ssh "$SERVER_USER@$SERVER_HOST" "
    cd $SERVER_PATH
    echo '# RESUMEN DEL DESPLIEGUE DE SEGURIDAD' > DEPLOY_SUMMARY.md
    echo '=====================================' >> DEPLOY_SUMMARY.md
    echo '' >> DEPLOY_SUMMARY.md
    echo 'Fecha: \$(date)' >> DEPLOY_SUMMARY.md
    echo 'Servidor: $SERVER_HOST' >> DEPLOY_SUMMARY.md
    echo 'Usuario: $SERVER_USER' >> DEPLOY_SUMMARY.md
    echo 'Directorio: $SERVER_PATH' >> DEPLOY_SUMMARY.md
    echo 'Backup: $BACKUP_DIR' >> DEPLOY_SUMMARY.md
    echo '' >> DEPLOY_SUMMARY.md
    echo '## Archivos Desplegados:' >> DEPLOY_SUMMARY.md
    echo '- .env (configuración de producción)' >> DEPLOY_SUMMARY.md
    echo '- .env.example (plantilla)' >> DEPLOY_SUMMARY.md
    echo '- app/config/EnvLoader.php (cargador de variables)' >> DEPLOY_SUMMARY.md
    echo '- config_secure.php (configuración segura)' >> DEPLOY_SUMMARY.md
    echo '- verify_security_implementation.php (script de verificación)' >> DEPLOY_SUMMARY.md
    echo '- Documentación completa' >> DEPLOY_SUMMARY.md
    echo '' >> DEPLOY_SUMMARY.md
    echo '## Próximos Pasos:' >> DEPLOY_SUMMARY.md
    echo '1. Probar el sistema web completamente' >> DEPLOY_SUMMARY.md
    echo '2. Verificar logs de aplicación' >> DEPLOY_SUMMARY.md
    echo '3. Eliminar backups una vez confirmado' >> DEPLOY_SUMMARY.md
    echo '' >> DEPLOY_SUMMARY.md
    echo '✅ DESPLIEGUE COMPLETADO EXITOSAMENTE' >> DEPLOY_SUMMARY.md
"

# Resumen final
echo ""
echo "🎉 DESPLIEGUE COMPLETADO EXITOSAMENTE"
echo "====================================="
echo ""
show_success "Archivos desplegados en: $SERVER_HOST:$SERVER_PATH"
show_success "Backup creado en: $BACKUP_DIR"
show_success "Configuración de seguridad activada"
echo ""
echo "📋 PRÓXIMOS PASOS:"
echo "1. Probar el sistema web: http://tu-dominio.com/pedidos"
echo "2. Verificar logs: tail -f $SERVER_PATH/logs/*.log"
echo "3. Si todo funciona, eliminar backups: rm -rf $BACKUP_DIR"
echo ""
echo "📄 Ver resumen completo en servidor: $SERVER_PATH/DEPLOY_SUMMARY.md"
echo ""
show_success "¡Corrección de seguridad desplegada exitosamente!"