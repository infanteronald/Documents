#!/bin/bash
# Monitor en tiempo real del webhook 100% mejorado

echo "🔍 MONITOR EN TIEMPO REAL - WEBHOOK 100% MEJORADO"
echo "================================================"
echo "Fecha/Hora inicio: $(date)"
echo "Presiona Ctrl+C para detener"
echo ""

# Función para mostrar estado
show_status() {
    echo "$(date '+%H:%M:%S') - Verificando actividad..."
    
    # Verificar si hay actividad en distributor (NO debería haber)
    if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log" ]; then
        DIST_ACTIVITY=$(tail -1 /home/motodota/sequoiaspeed.com.co/pedidos/logs/dual_mode.log)
        if [[ $DIST_ACTIVITY == *"$(date '+%Y-%m-%d')"* ]]; then
            echo "⚠️  ALERTA: Distributor aún recibiendo tráfico!"
            echo "   Último: $DIST_ACTIVITY"
        fi
    fi
    
    # Verificar actividad en webhook mejorado (SÍ debería haber)
    if [ -f "/home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log" ]; then
        ENHANCED_ACTIVITY=$(tail -1 /home/motodota/sequoiaspeed.com.co/pedidos/logs/bold_webhook.log)
        if [[ $ENHANCED_ACTIVITY == *"$(date '+%Y-%m-%d')"* ]]; then
            echo "✅ Webhook mejorado activo!"
            echo "   Último: $ENHANCED_ACTIVITY"
        fi
    fi
    
    echo "---"
}

# Monitor continuo
while true; do
    show_status
    sleep 30
done
