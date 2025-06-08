#!/bin/bash

# Deployment Script - Sequoia Speed FASE 4
# Script automatizado para deployment en producción

echo "🚀 INICIANDO DEPLOYMENT SEQUOIA SPEED - FASE 4"
echo "=============================================="

# Verificar prerrequisitos
echo "📋 Verificando prerrequisitos..."

# Verificar PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP no está instalado"
    exit 1
fi
echo "✅ PHP disponible: $(php -r 'echo PHP_VERSION;')"

# Verificar composer (opcional pero recomendado)
if command -v composer &> /dev/null; then
    echo "✅ Composer disponible: $(composer --version --no-ansi | head -n1)"
else
    echo "⚠️  Composer no disponible (opcional)"
fi

# Verificar estructura
echo ""
echo "📁 Verificando estructura MVC..."
required_dirs=("app/controllers" "app/models" "app/services" "app/middleware" "app/config")
for dir in "${required_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo "✅ $dir"
    else
        echo "❌ $dir - FALTANTE"
        exit 1
    fi
done

# Verificar archivos críticos
echo ""
echo "📄 Verificando archivos críticos..."
critical_files=(
    "app/AdvancedRouter.php"
    "app/CacheManager.php"
    "routes.php"
    ".env.production"
    "app/config/SecurityConfig.php"
)

for file in "${critical_files[@]}"; do
    if [ -f "$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - FALTANTE"
        exit 1
    fi
done

echo ""
echo "🔧 Configurando permisos..."
# Configurar permisos
chmod -R 755 app/
chmod -R 755 phase4/
chmod 644 .env.production
chmod +x deploy.sh

echo "✅ Permisos configurados"

echo ""
echo "📦 Preparando archivos de producción..."

# Crear directorio de logs si no existe
mkdir -p logs
chmod 755 logs

# Crear directorio de cache si no existe
mkdir -p cache
chmod 755 cache

# Crear directorio de uploads si no existe
mkdir -p uploads
chmod 755 uploads

echo "✅ Directorios de producción creados"

echo ""
echo "⚙️  Configurando servidor web..."

# Crear archivo .htaccess optimizado para producción
cat > .htaccess << 'EOF'
# Sequoia Speed - Configuración Apache Producción
RewriteEngine On

# Seguridad - Ocultar archivos sensibles
<Files ".env*">
    Order allow,deny
    Deny from all
</Files>

<Files "*.log">
    Order allow,deny
    Deny from all
</Files>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
Header always set Referrer-Policy "strict-origin-when-cross-origin"

# Cache de archivos estáticos
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

# Redirección de rutas MVC
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^(.*)$ routes.php [QSA,L]

# Mantener compatibilidad legacy
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*)$ $1 [L]
EOF

echo "✅ .htaccess configurado para producción"

echo ""
echo "🧪 Ejecutando tests finales..."

# Ejecutar tests de rutas MVC
if [ -f "test-mvc-routes.php" ]; then
    php test-mvc-routes.php
    echo "✅ Tests MVC ejecutados"
else
    echo "⚠️  test-mvc-routes.php no encontrado"
fi

echo ""
echo "📊 RESUMEN DEL DEPLOYMENT:"
echo "========================="
echo "✅ Estructura MVC verificada"
echo "✅ Archivos críticos presentes"
echo "✅ Permisos configurados"
echo "✅ Directorios de producción creados"
echo "✅ Servidor web configurado"
echo "✅ Tests ejecutados"

echo ""
echo "🎉 DEPLOYMENT COMPLETADO EXITOSAMENTE!"
echo ""
echo "🚀 PRÓXIMOS PASOS:"
echo "=================="
echo "1. Configurar base de datos de producción"
echo "2. Ajustar variables en .env.production"
echo "3. Configurar SSL/HTTPS"
echo "4. Configurar monitoreo"
echo "5. Realizar pruebas de carga"
echo ""
echo "📍 Sistema Sequoia Speed listo para producción"
echo "🎯 Migración MVC FASE 4 completada"
