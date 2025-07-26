#!/bin/bash

# Script para iniciar el servidor de desarrollo de Sequoia Speed
# Este script maneja la configuración necesaria para desarrollo local

echo "🚀 Iniciando servidor de desarrollo Sequoia Speed..."
echo "📁 Directorio: $(pwd)"
echo "🌐 URL Principal: http://localhost:8000/index.php"
echo "🔐 Login: http://localhost:8000/accesos/login.php"
echo ""
echo "⚠️  IMPORTANTE: Usa /index.php en lugar de / para evitar problemas con .htaccess"
echo "Para detener el servidor presiona Ctrl+C"
echo "=========================================="
echo ""

# Renombrar .htaccess temporalmente si existe
if [ -f .htaccess ]; then
    echo "📝 Renombrando .htaccess a .htaccess.backup para desarrollo..."
    mv .htaccess .htaccess.backup
fi

# Función para restaurar .htaccess al salir
cleanup() {
    echo ""
    echo "🔄 Restaurando .htaccess..."
    if [ -f .htaccess.backup ]; then
        mv .htaccess.backup .htaccess
        echo "✅ .htaccess restaurado"
    fi
    exit 0
}

# Configurar trap para cleanup
trap cleanup INT TERM

# Iniciar servidor PHP simple
php -S localhost:8000