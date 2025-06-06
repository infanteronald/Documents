#!/bin/zsh

# Script de Gestión del Modo Dual - Bold PSE
# Uso: ./manage_dual_mode.sh [comando] [valor]
# Comandos: status, set-percentage, increase, test, logs

BOLD_SERVER="https://sequoiaspeed.com.co/pedidos"
WEBHOOK_DISTRIBUTOR="$BOLD_SERVER/bold_webhook_distributor.php"
WEBHOOK_ENHANCED="$BOLD_SERVER/bold_webhook_enhanced.php"
WEBHOOK_ORIGINAL="$BOLD_SERVER/bold_webhook.php"

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

function print_header() {
    echo -e "${BLUE}🔄 Gestión del Modo Dual - Bold PSE${NC}"
    echo -e "${BLUE}===================================${NC}"
    echo ""
}

function check_status() {
    echo -e "${YELLOW}📊 Verificando estado del sistema...${NC}"
    echo ""
    
    echo -n "🔗 Distribuidor: "
    status=$(curl -s -o /dev/null -w "%{http_code}" -X POST "$WEBHOOK_DISTRIBUTOR")
    if [[ "$status" == "400" ]]; then
        echo -e "${GREEN}✅ Funcionando (400 - Datos vacíos esperado)${NC}"
    else
        echo -e "${RED}❌ Status: $status${NC}"
    fi
    
    echo -n "🚀 Webhook Mejorado: "
    status=$(curl -s -o /dev/null -w "%{http_code}" "$WEBHOOK_ENHANCED")
    if [[ "$status" == "200" ]]; then
        echo -e "${GREEN}✅ Funcionando${NC}"
    else
        echo -e "${RED}❌ Status: $status${NC}"
    fi
    
    echo -n "📦 Webhook Original: "
    status=$(curl -s -o /dev/null -w "%{http_code}" "$WEBHOOK_ORIGINAL")
    if [[ "$status" == "200" ]]; then
        echo -e "${GREEN}✅ Funcionando${NC}"
    else
        echo -e "${RED}❌ Status: $status${NC}"
    fi
    
    echo ""
}

function test_webhooks() {
    echo -e "${YELLOW}🧪 Realizando pruebas de conectividad...${NC}"
    echo ""
    
    # Test del distribuidor con datos de prueba
    echo "🔍 Probando distribuidor..."
    response=$(curl -s -X POST -H "Content-Type: application/json" \
        -d '{"transaction":{"id":"TEST_12345"},"status":"approved"}' \
        "$WEBHOOK_DISTRIBUTOR" 2>&1)
    
    if [[ "$response" == *"Error"* ]] || [[ "$response" == *"inválida"* ]]; then
        echo -e "${GREEN}✅ Distribuidor rechaza datos de prueba correctamente${NC}"
    else
        echo -e "${YELLOW}⚠️  Respuesta inesperada: $response${NC}"
    fi
    
    echo ""
}

function show_next_steps() {
    echo -e "${BLUE}📋 Próximos Pasos Recomendados:${NC}"
    echo ""
    echo -e "${YELLOW}1. CRÍTICO - Configurar URL en Bold Dashboard:${NC}"
    echo "   $WEBHOOK_DISTRIBUTOR"
    echo ""
    echo -e "${YELLOW}2. Monitorear logs después del cambio de URL:${NC}"
    echo "   - Los logs se crearán automáticamente cuando Bold envíe webhooks reales"
    echo "   - Ubicación: $BOLD_SERVER/logs/dual_mode.log"
    echo ""
    echo -e "${YELLOW}3. Cronograma de aumento gradual:${NC}"
    echo "   📅 Día 1-2: 10% (actual)"
    echo "   📅 Día 3-4: 25%"
    echo "   📅 Día 5-6: 50%"
    echo "   📅 Día 7-8: 75%"
    echo "   📅 Día 9+:  100%"
    echo ""
    echo -e "${YELLOW}4. Comandos útiles:${NC}"
    echo "   ./manage_dual_mode.sh status    # Ver estado"
    echo "   ./manage_dual_mode.sh test      # Probar conectividad"
    echo "   ./manage_dual_mode.sh logs      # Ver logs (cuando estén disponibles)"
    echo ""
}

function check_logs() {
    echo -e "${YELLOW}📋 Verificando logs disponibles...${NC}"
    echo ""
    
    # Intentar acceder a logs
    log_response=$(curl -s -o /dev/null -w "%{http_code}" "$BOLD_SERVER/logs/dual_mode.log")
    
    if [[ "$log_response" == "200" ]]; then
        echo -e "${GREEN}✅ Logs disponibles${NC}"
        echo "Últimas 10 líneas:"
        curl -s "$BOLD_SERVER/logs/dual_mode.log" | tail -10
    else
        echo -e "${YELLOW}⚠️  Logs aún no creados (se crearán cuando Bold envíe webhooks reales)${NC}"
        echo "Status: $log_response"
    fi
    
    echo ""
}

# Función principal
function main() {
    print_header
    
    case "${1:-status}" in
        "status")
            check_status
            show_next_steps
            ;;
        "test")
            check_status
            test_webhooks
            ;;
        "logs")
            check_logs
            ;;
        "help"|"-h"|"--help")
            echo "Uso: $0 [comando]"
            echo ""
            echo "Comandos disponibles:"
            echo "  status    - Ver estado del sistema (por defecto)"
            echo "  test      - Probar conectividad de webhooks"
            echo "  logs      - Verificar logs disponibles"
            echo "  help      - Mostrar esta ayuda"
            echo ""
            ;;
        *)
            echo -e "${RED}❌ Comando desconocido: $1${NC}"
            echo "Usa '$0 help' para ver comandos disponibles"
            exit 1
            ;;
    esac
}

# Ejecutar función principal
main "$@"
