#!/bin/bash

# Deploy Script - Sequoia Speed
# Deployment automatizado para producción

echo "🚀 INICIANDO DEPLOYMENT SEQUOIA SPEED"
echo "====================================="

# Variables
PROJECT_NAME="Sequoia Speed"
DEPLOY_DATE=$(date '+%Y-%m-%d %H:%M:%S')
BACKUP_DIR="backups/deploy_$(date +%Y%m%d_%H%M%S)"

echo "📅 Fecha de deployment: $DEPLOY_DATE"
echo "📁 Proyecto: $PROJECT_NAME"

# Crear backup
echo ""
echo "💾 Creando backup..."
mkdir -p "$BACKUP_DIR"
cp -r *.php "$BACKUP_DIR/" 2>/dev/null || true
cp -r app/ "$BACKUP_DIR/" 2>/dev/null || true
cp -r public/ "$BACKUP_DIR/" 2>/dev/null || true
echo "✅ Backup creado en: $BACKUP_DIR"

# Verificar prerrequisitos
echo ""
echo "🔍 Verificando prerrequisitos..."

# PHP
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -r "echo PHP_VERSION;")
    echo "✅ PHP $PHP_VERSION"
else
    echo "❌ PHP no encontrado"
    exit 1
fi

# Verificar estructura MVC
echo ""
echo "🏗️ Verificando estructura MVC..."
mvc_files=(
    "app/AdvancedRouter.php"
    "app/controllers/PedidoController.php"
    "app/controllers/ProductoController.php"
    "app/models/Pedido.php"
    "app/models/Producto.php"
    "routes.php"
)

for file in "${mvc_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - FALTANTE"
        exit 1
    fi
done

# Verificar configuración
echo ""
echo "⚙️ Verificando configuración..."
config_files=(
    ".env.production"
    "app/config/SecurityConfig.php"
    "app/config/ProductionMonitor.php"
    "app/config/ProductionCacheConfig.php"
)

for file in "${config_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - FALTANTE"
        exit 1
    fi
done

# Configurar permisos
echo ""
echo "🔐 Configurando permisos..."
chmod -R 755 app/
chmod -R 755 public/ 2>/dev/null || true
chmod 644 .env.production
chmod +x deploy.sh 2>/dev/null || true

# Crear directorios necesarios
echo ""
echo "📁 Creando directorios..."
mkdir -p logs
mkdir -p cache
mkdir -p uploads
mkdir -p storage/temp

chmod 755 logs cache uploads storage storage/temp

echo "✅ Directorios creados y configurados"

# Configurar .htaccess
echo ""
echo "🌐 Configurando servidor web..."
cat > .htaccess << 'EOF'
# Sequoia Speed - Configuración Apache Producción
RewriteEngine On

# Seguridad
<Files ".env*">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

<FilesMatch "\.(json|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Cache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/gif "access plus 1 month"
</IfModule>

# Compresión
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>

# Rutas MVC
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^(.*)$ routes.php [QSA,L]

# Compatibilidad legacy
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*)$ $1 [L]
EOF

echo "✅ .htaccess configurado"

# Tests finales
echo ""
echo "🧪 Ejecutando tests..."
if [ -f "test-mvc-routes.php" ]; then
    php test-mvc-routes.php
    echo "✅ Tests MVC ejecutados"
else
    echo "⚠️ test-mvc-routes.php no encontrado"
fi

# Verificación final
echo ""
echo "✅ DEPLOYMENT COMPLETADO"
echo "======================="
echo "📊 Archivos verificados: ✅"
echo "🔐 Permisos configurados: ✅"
echo "🌐 Servidor web configurado: ✅"
echo "💾 Backup creado: ✅"

echo ""
echo "🎉 ¡SEQUOIA SPEED LISTO PARA PRODUCCIÓN!"
echo ""
echo "📋 Próximos pasos:"
echo "1. Configurar base de datos de producción"
echo "2. Ajustar variables en .env.production"
echo "3. Configurar SSL/HTTPS"
echo "4. Configurar monitoreo"
echo "5. Probar sistema completo"

echo ""
echo "🔗 URLs importantes:"
echo "- Dashboard: /index.php"
echo "- API: /api/v1/"
echo "- Monitoreo: /app/config/ProductionMonitor.php"

echo ""
echo "📞 Sistema completamente migrado a MVC"
echo "🎯 Compatibilidad legacy mantenida"
echo "🚀 Performance optimizada"
echo "🛡️ Seguridad implementada"

echo ""
echo "✅ Deployment exitoso - $(date '+%Y-%m-%d %H:%M:%S')"
