#!/bin/zsh

# Dashboard de Monitoreo en Tiempo Real - Bold PSE
# Uso: ./realtime_monitor.sh

BOLD_SERVER="https://sequoiaspeed.com.co/pedidos"

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

function print_header() {
    clear
    echo -e "${BLUE}┌─────────────────────────────────────────────────────────────┐${NC}"
    echo -e "${BLUE}│                🔄 BOLD PSE - MONITOR EN TIEMPO REAL           │${NC}"
    echo -e "${BLUE}│                     Sistema de Modo Dual                   │${NC}"
    echo -e "${BLUE}└─────────────────────────────────────────────────────────────┘${NC}"
    echo -e "${CYAN}Última actualización: $(date '+%Y-%m-%d %H:%M:%S')${NC}"
    echo ""
}

function check_system_status() {
    echo -e "${YELLOW}📊 Estado del Sistema:${NC}"
    echo "────────────────────────"
    
    # Verificar distribuidor
    echo -n "🔗 Distribuidor: "
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$BOLD_SERVER/bold_webhook_distributor.php" 2>/dev/null)
    if [[ "$status" == "400" ]]; then
        echo -e "${GREEN}✅ Activo${NC}"
    else
        echo -e "${RED}❌ Error ($status)${NC}"
    fi
    
    # Verificar webhook mejorado
    echo -n "🚀 Webhook Mejorado: "
    status=$(curl -s -o /dev/null -w "%{http_code}" "$BOLD_SERVER/bold_webhook_enhanced.php" 2>/dev/null)
    if [[ "$status" == "200" ]]; then
        echo -e "${GREEN}✅ Activo${NC}"
    else
        echo -e "${RED}❌ Error ($status)${NC}"
    fi
    
    # Verificar webhook original
    echo -n "📦 Webhook Original: "
    status=$(curl -s -o /dev/null -w "%{http_code}" "$BOLD_SERVER/bold_webhook.php" 2>/dev/null)
    if [[ "$status" == "200" ]]; then
        echo -e "${GREEN}✅ Activo${NC}"
    else
        echo -e "${RED}❌ Error ($status)${NC}"
    fi
    
    echo ""
}

function show_dual_mode_stats() {
    echo -e "${YELLOW}📈 Estadísticas del Modo Dual:${NC}"
    echo "─────────────────────────────"
    
    # Obtener logs del modo dual
    local dual_logs=$(curl -s "$BOLD_SERVER/logs/dual_mode.log" 2>/dev/null)
    
    if [[ -n "$dual_logs" ]]; then
        # Contar eventos
        local total_routing=$(echo "$dual_logs" | grep -c "Webhook routing decision" 2>/dev/null || echo "0")
        local enhanced_routing=$(echo "$dual_logs" | grep -c '"use_enhanced":true' 2>/dev/null || echo "0")
        local original_routing=$(echo "$dual_logs" | grep -c '"use_enhanced":false' 2>/dev/null || echo "0")
        
        echo -e "📊 Total de requests enrutados: ${CYAN}$total_routing${NC}"
        echo -e "🚀 Enviados al webhook mejorado: ${CYAN}$enhanced_routing${NC}"
        echo -e "📦 Enviados al webhook original: ${CYAN}$original_routing${NC}"
        
        # Calcular porcentaje real
        if [[ "$total_routing" -gt 0 ]]; then
            local real_percentage=$(( enhanced_routing * 100 / total_routing ))
            echo -e "📊 Porcentaje real al mejorado: ${CYAN}${real_percentage}%${NC} (configurado: 10%)"
        fi
    else
        echo -e "${YELLOW}⏳ Esperando primeros eventos...${NC}"
    fi
    
    echo ""
}

function show_recent_activity() {
    echo -e "${YELLOW}📋 Actividad Reciente:${NC}"
    echo "──────────────────────"
    
    # Últimos 5 eventos del modo dual
    local recent_logs=$(curl -s "$BOLD_SERVER/logs/dual_mode.log" 2>/dev/null | tail -5)
    
    if [[ -n "$recent_logs" ]]; then
        echo "$recent_logs" | while IFS= read -r line; do
            if [[ "$line" == *"use_enhanced\":true"* ]]; then
                echo -e "${GREEN}🚀 $line${NC}"
            elif [[ "$line" == *"use_enhanced\":false"* ]]; then
                echo -e "${BLUE}📦 $line${NC}"
            elif [[ "$line" == *"Error"* ]] || [[ "$line" == *"error"* ]]; then
                echo -e "${RED}❌ $line${NC}"
            else
                echo -e "${CYAN}ℹ️  $line${NC}"
            fi
        done
    else
        echo -e "${YELLOW}⏳ No hay actividad reciente...${NC}"
    fi
    
    echo ""
}

function show_config_info() {
    echo -e "${YELLOW}⚙️ Configuración Actual:${NC}"
    echo "─────────────────────────"
    echo -e "📊 Porcentaje configurado: ${CYAN}10%${NC} al webhook mejorado"
    echo -e "🌐 URL en Bold Dashboard: ${CYAN}$BOLD_SERVER/bold_webhook_distributor.php${NC}"
    echo -e "📋 Modo dual: ${GREEN}ACTIVO${NC}"
    echo ""
}

function show_next_steps() {
    echo -e "${YELLOW}🎯 Próximos Pasos:${NC}"
    echo "─────────────────"
    echo -e "1. ${CYAN}Monitorear durante 1-2 días${NC}"
    echo -e "2. ${CYAN}Si no hay errores, aumentar a 25%${NC}"
    echo -e "3. ${CYAN}Continuar aumentando gradualmente${NC}"
    echo -e "4. ${CYAN}Finalizar al 100%${NC}"
    echo ""
}

function show_commands() {
    echo -e "${YELLOW}🔧 Comandos Útiles:${NC}"
    echo "──────────────────"
    echo -e "${CYAN}Ctrl+C${NC} - Salir del monitor"
    echo -e "${CYAN}r${NC} - Actualizar manualmente (auto-refresh cada 30s)"
    echo ""
}

# Función principal del monitor
function run_monitor() {
    while true; do
        print_header
        check_system_status
        show_dual_mode_stats
        show_recent_activity
        show_config_info
        show_next_steps
        show_commands
        
        echo -e "${BLUE}Actualizando en 30 segundos... (Ctrl+C para salir)${NC}"
        
        # Esperar 30 segundos o input del usuario
        read -t 30 -k 1 input 2>/dev/null
        
        if [[ "$input" == "r" ]]; then
            continue
        fi
    done
}

# Ejecutar monitor
run_monitor
