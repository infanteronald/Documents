#!/bin/bash
# Monitor continuo del webhook mejorado - POST configuración Bold Dashboard
# Ejecutar en el servidor para monitorear webhooks en tiempo real

echo "🔍 MONITOR CONTINUO - WEBHOOK MEJORADO 100% ACTIVO"
echo "================================================="
echo "Bold Dashboard: ✅ https://sequoiaspeed.com.co/pedidos/bold_webhook_enhanced.php"
echo "Estado: ✅ Enviando 4 eventos"
echo "Inicio del monitoreo: $(date)"
echo "Presiona Ctrl+C para detener"
echo ""

# Variables para tracking
LAST_WEBHOOK_SIZE=0
LAST_DISTRIBUTOR_SIZE=0

# Obtener tamaños iniciales
if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log" ]; then
    LAST_WEBHOOK_SIZE=$(wc -c < /home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log)
fi

if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log" ]; then
    LAST_DISTRIBUTOR_SIZE=$(wc -c < /home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log)
fi

# Función de monitoreo
monitor_webhooks() {
    local timestamp=$(date '+%H:%M:%S')
    local webhook_activity=false
    local distributor_activity=false
    
    # Verificar actividad en webhook mejorado
    if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log" ]; then
        CURRENT_WEBHOOK_SIZE=$(wc -c < /home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log)
        if [ $CURRENT_WEBHOOK_SIZE -gt $LAST_WEBHOOK_SIZE ]; then
            webhook_activity=true
            LAST_WEBHOOK_SIZE=$CURRENT_WEBHOOK_SIZE
            echo "🎯 [$timestamp] ¡WEBHOOK MEJORADO ACTIVO!"
            echo "   Últimas líneas:"
            tail -3 /home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log | grep -E "(INFO|ERROR|WARNING)" | tail -2 | sed 's/^/   → /'
        fi
    fi
    
    # Verificar actividad en distributor (NO debería haber)
    if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log" ]; then
        CURRENT_DISTRIBUTOR_SIZE=$(wc -c < /home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log)
        if [ $CURRENT_DISTRIBUTOR_SIZE -gt $LAST_DISTRIBUTOR_SIZE ]; then
            distributor_activity=true
            LAST_DISTRIBUTOR_SIZE=$CURRENT_DISTRIBUTOR_SIZE
            echo "⚠️  [$timestamp] ALERTA: Tráfico en distributor (NO esperado)"
            tail -2 /home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log | sed 's/^/   → /'
        fi
    fi
    
    # Mostrar estado si no hay actividad cada 2 minutos
    if [ $(($(date +%s) % 120)) -eq 0 ] && [ "$webhook_activity" = false ] && [ "$distributor_activity" = false ]; then
        echo "[$timestamp] Sistema monitoreando... (sin actividad)"
    fi
}

# Monitor continuo
echo "Monitoreando webhooks..."
while true; do
    monitor_webhooks
    sleep 10
done
