#!/bin/bash

# Script de Monitoreo del Modo Dual - Bold PSE
# Uso: ./monitor_dual_mode.sh

echo "🔄 Monitor del Modo Dual - Bold PSE"
echo "=================================="

echo ""
echo "📊 Estado del Distribuidor:"
curl -s -o /dev/null -w "Status: %{http_code} | Tiempo: %{time_total}s\n" "https://sequoiaspeed.com.co/pedidos/bold_webhook_distributor.php"

echo ""
echo "📊 Estado del Webhook Mejorado:"
curl -s -o /dev/null -w "Status: %{http_code} | Tiempo: %{time_total}s\n" "https://sequoiaspeed.com.co/pedidos/bold_webhook_enhanced.php"

echo ""
echo "📊 Estado del Webhook Original:"
curl -s -o /dev/null -w "Status: %{http_code} | Tiempo: %{time_total}s\n" "https://sequoiaspeed.com.co/pedidos/bold_webhook.php"

echo ""
echo "📋 Últimos logs del modo dual (si están disponibles):"
echo "curl -s 'https://sequoiaspeed.com.co/pedidos/logs/dual_mode.log' | tail -10"

echo ""
echo "🔧 Para cambiar el porcentaje, editar el archivo:"
echo "https://sequoiaspeed.com.co/pedidos/dual_mode_config.php"
echo "Cambiar la línea: define('ENHANCED_WEBHOOK_PERCENTAGE', XX);"

echo ""
echo "✅ Comando para verificar configuración actual:"
echo "curl -s 'https://sequoiaspeed.com.co/pedidos/dual_mode_config.php' | grep ENHANCED_WEBHOOK_PERCENTAGE"

echo ""
echo "📈 URLs importantes:"
echo "- Distributor: https://sequoiaspeed.com.co/pedidos/bold_webhook_distributor.php"
echo "- Webhook Mejorado: https://sequoiaspeed.com.co/pedidos/bold_webhook_enhanced.php"
echo "- Monitor: https://sequoiaspeed.com.co/pedidos/dual_mode_monitor.php"
echo "- Configuración: https://sequoiaspeed.com.co/pedidos/dual_mode_config.php"
