#!/bin/zsh

# 🚀 DEMOSTRACIÓN DE DEPLOYMENT - SEQUOIA SPEED
# Muestra qué archivos se desplegarían en producción

echo "🚀 DEMO DEPLOYMENT LIMPIO - SEQUOIA SPEED"
echo "=========================================="
echo "Fecha: $(date)"
echo ""

LOCAL_PATH="/Users/ronaldinfante/Documents/pedidos"
DEMO_DEPLOY_PATH="/tmp/sequoia_deployment_demo"

echo "📋 CONFIGURACIÓN DE DEMO:"
echo "------------------------"
echo "Local: $LOCAL_PATH"
echo "Demo deployment: $DEMO_DEPLOY_PATH"
echo ""

# Limpiar directorio demo previo
rm -rf "$DEMO_DEPLOY_PATH"
mkdir -p "$DEMO_DEPLOY_PATH"

echo "🔍 VERIFICANDO ARCHIVOS A DESPLEGAR..."
echo ""

# Simular rsync con exclusiones
echo "📦 ARCHIVOS QUE SE DESPLEGARÍAN:"
echo "-------------------------------"

cd "$LOCAL_PATH"

# Contar archivos totales
total_files=$(find . -type f | wc -l | tr -d ' ')
echo "Total de archivos en proyecto: $total_files"

# Contar archivos que se excluirían
excluded_files=$(find . -path './desarrollo/*' -o -name '.git*' -o -name '.DS_Store' -o -name '*.log' -o -name 'node_modules' -o -name '*.tmp' -o -name 'development-monitor.sh' -o -name 'deploy-*.sh' | wc -l | tr -d ' ')
echo "Archivos que se EXCLUIRÍAN: $excluded_files"

# Calcular archivos de producción
production_files=$((total_files - excluded_files))
echo "Archivos de PRODUCCIÓN a desplegar: $production_files"

echo ""
echo "🎯 ARCHIVOS CRÍTICOS DE PRODUCCIÓN:"
echo "-----------------------------------"

critical_files=(
    "bold_webhook_enhanced.php"
    "index.php" 
    "conexion.php"
    "orden_pedido.php"
    "listar_pedidos.php"
    "productos_por_categoria.php"
    "styles.css"
    "payment_ux_enhanced.css"
    "apple-ui.css"
    ".htaccess"
)

for file in "${critical_files[@]}"; do
    if [ -f "$file" ]; then
        size=$(ls -lh "$file" | awk '{print $5}')
        echo "✅ $file ($size)"
        # Copiar a demo
        cp "$file" "$DEMO_DEPLOY_PATH/"
    else
        echo "❌ $file - NO ENCONTRADO"
    fi
done

echo ""
echo "📁 DIRECTORIOS QUE SE INCLUIRÍAN:"
echo "--------------------------------"

production_dirs=(
    "comprobantes"
    "guias" 
    "uploads"
    "logs"
    "app"
    "assets"
    "public"
)

for dir in "${production_dirs[@]}"; do
    if [ -d "$dir" ]; then
        file_count=$(find "$dir" -type f | wc -l | tr -d ' ')
        echo "✅ $dir/ ($file_count archivos)"
        # Copiar estructura a demo
        cp -r "$dir" "$DEMO_DEPLOY_PATH/" 2>/dev/null || true
    else
        echo "⚪ $dir/ - No existe"
    fi
done

echo ""
echo "🚫 DIRECTORIOS QUE SE EXCLUIRÍAN:"
echo "--------------------------------"
echo "❌ desarrollo/ ($(find desarrollo -type f | wc -l | tr -d ' ') archivos)"
echo "❌ .git/ (control de versiones)"
echo "❌ node_modules/ (dependencias)"
echo "❌ *.log (archivos de log)"
echo "❌ *.tmp (archivos temporales)"

echo ""
echo "📊 ESTADÍSTICAS FINALES:"
echo "------------------------"
demo_files=$(find "$DEMO_DEPLOY_PATH" -type f | wc -l | tr -d ' ')
echo "Archivos en deployment demo: $demo_files"
echo "Porcentaje de reducción: $(( (excluded_files * 100) / total_files ))%"

echo ""
echo "🎯 DEMO CREADA EN: $DEMO_DEPLOY_PATH"
echo ""
echo "📋 Para ver el contenido del deployment:"
echo "   ls -la $DEMO_DEPLOY_PATH"
echo ""
echo "✅ DEMO DE DEPLOYMENT COMPLETADA"
echo "   El sistema está listo para deployment real"
echo "   Solo se necesita configurar las credenciales SSH del servidor"
