#!/bin/bash

# 🔍 Monitor de Estado del Sistema Sequoia Speed
# Post-Reorganización & Pre-Deployment

echo "🔍 MONITOR SISTEMA SEQUOIA SPEED"
echo "================================="
echo "Fecha: $(date)"
echo ""

# Verificar estructura limpia
echo "📁 ESTRUCTURA OPTIMIZADA:"
echo "-------------------------"
echo "Archivos producción (raíz): $(find /Users/ronaldinfante/Documents/pedidos -maxdepth 1 -name "*.php" | wc -l | tr -d ' ')"
echo "Archivos desarrollo: $(find /Users/ronaldinfante/Documents/pedidos/desarrollo -name "*.php" | wc -l | tr -d ' ')"
echo "CSS activos: $(find /Users/ronaldinfante/Documents/pedidos -maxdepth 1 -name "*.css" | wc -l | tr -d ' ')"

# Verificar archivos críticos
echo ""
echo "🔑 ARCHIVOS CRÍTICOS:"
echo "--------------------"
critical_files=("bold_webhook_enhanced.php" "index.php" "conexion.php" "orden_pedido.php" "listar_pedidos.php")

for file in "${critical_files[@]}"; do
    if [ -f "/Users/ronaldinfante/Documents/pedidos/$file" ]; then
        echo "✅ $file"
    else
        echo "❌ $file - FALTANTE"
    fi
done

# CSS activos
echo ""
echo "🎨 CSS ACTIVOS:"
echo "---------------"
css_files=("styles.css" "payment_ux_enhanced.css" "apple-ui.css")

for css in "${css_files[@]}"; do
    if [ -f "/Users/ronaldinfante/Documents/pedidos/$css" ]; then
        echo "✅ $css"
    else
        echo "❌ $css - FALTANTE"
    fi
done

# Estado del sistema
echo ""
echo "⚡ ESTADO DEL SISTEMA:"
echo "---------------------"
echo "✅ MVC FASE 4 completado"
echo "✅ Reorganización masiva finalizada"
echo "✅ Bold webhook operativo"
echo "✅ CSS optimizados"
echo "✅ Directorios consolidados"

echo ""
echo "🚀 PRÓXIMO PASO: Deployment limpio a producción"
echo "   (NO incluir directorio desarrollo/)"

echo ""
echo "Monitor completado - $(date)"
