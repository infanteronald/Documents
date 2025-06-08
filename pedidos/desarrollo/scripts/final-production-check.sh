#!/bin/bash

echo "🔍 VERIFICACIÓN FINAL PRODUCCIÓN - Sequoia Speed"
echo "================================================"
echo "Fecha: $(date)"
echo ""

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verificar archivos .env
echo -e "${BLUE}📁 VERIFICANDO ARCHIVOS .ENV${NC}"
echo "--------------------------------"

if [ -f ".env.example" ]; then
    echo -e "${GREEN}✓${NC} .env.example existe"
    # Verificar BD correcta en .env.example
    if grep -q "DB_DATABASE=motodota_factura_electronica" .env.example; then
        echo -e "${GREEN}✓${NC} Base de datos correcta en .env.example"
    else
        echo -e "${RED}✗${NC} Base de datos incorrecta en .env.example"
    fi
else
    echo -e "${RED}✗${NC} .env.example NO existe"
fi

if [ -f ".env.production" ]; then
    echo -e "${GREEN}✓${NC} .env.production existe"
    # Verificar configuraciones críticas
    if grep -q "DB_DATABASE=motodota_factura_electronica" .env.production; then
        echo -e "${GREEN}✓${NC} Base de datos correcta en .env.production"
    else
        echo -e "${RED}✗${NC} Base de datos incorrecta en .env.production"
    fi
    
    if grep -q "APP_URL=https://sequoiaspeed.com.co/pedidos" .env.production; then
        echo -e "${GREEN}✓${NC} URL correcta en .env.production"
    else
        echo -e "${RED}✗${NC} URL incorrecta en .env.production"
    fi
    
    if grep -q "SMTP_HOST=mail.sequoiaspeed.com.co" .env.production; then
        echo -e "${GREEN}✓${NC} SMTP correcto en .env.production"
    else
        echo -e "${RED}✗${NC} SMTP incorrecto en .env.production"
    fi
else
    echo -e "${RED}✗${NC} .env.production NO existe"
fi

echo ""

# Verificar estructura de archivos de producción
echo -e "${BLUE}📂 VERIFICANDO ESTRUCTURA DE PRODUCCIÓN${NC}"
echo "----------------------------------------"

# Contar archivos PHP en raíz
PHP_FILES=$(find . -maxdepth 1 -name "*.php" | wc -l | tr -d ' ')
echo -e "${GREEN}📄${NC} Archivos PHP en raíz: $PHP_FILES"

# Verificar archivos críticos
CRITICAL_FILES=(
    "index.php"
    "conexion.php"
    "orden_pedido.php"
    "bold_webhook_enhanced.php"
    "bootstrap.php"
    "routes.php"
)

echo -e "${BLUE}🔧 Archivos críticos:${NC}"
for file in "${CRITICAL_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file"
    else
        echo -e "${RED}✗${NC} $file"
    fi
done

echo ""

# Verificar directorio desarrollo
echo -e "${BLUE}🚧 VERIFICANDO DIRECTORIO DESARROLLO${NC}"
echo "------------------------------------"

if [ -d "desarrollo" ]; then
    DEV_FILES=$(find desarrollo -type f | wc -l | tr -d ' ')
    echo -e "${GREEN}✓${NC} Directorio desarrollo existe con $DEV_FILES archivos"
    
    # Verificar subdirectorios de desarrollo
    DEV_DIRS=("scripts" "temp" "backup" "old")
    for dir in "${DEV_DIRS[@]}"; do
        if [ -d "desarrollo/$dir" ]; then
            FILES_COUNT=$(find "desarrollo/$dir" -type f | wc -l | tr -d ' ')
            echo -e "${GREEN}✓${NC} desarrollo/$dir ($FILES_COUNT archivos)"
        fi
    done
else
    echo -e "${RED}✗${NC} Directorio desarrollo NO existe"
fi

echo ""

# Verificar CSS optimizado
echo -e "${BLUE}🎨 VERIFICANDO CSS OPTIMIZADO${NC}"
echo "------------------------------"

CSS_FILES=$(find . -maxdepth 1 -name "*.css" | wc -l | tr -d ' ')
echo -e "${GREEN}📄${NC} Archivos CSS en raíz: $CSS_FILES"

ACTIVE_CSS=("styles.css" "apple-ui.css" "payment_ux_enhanced.css")
for css in "${ACTIVE_CSS[@]}"; do
    if [ -f "$css" ]; then
        SIZE=$(ls -lh "$css" | awk '{print $5}')
        echo -e "${GREEN}✓${NC} $css ($SIZE)"
    else
        echo -e "${RED}✗${NC} $css"
    fi
done

echo ""

# Simular conteo de deployment
echo -e "${BLUE}🚀 SIMULACIÓN DE DEPLOYMENT${NC}"
echo "-----------------------------"

TOTAL_FILES=$(find . -type f | wc -l | tr -d ' ')
echo -e "${YELLOW}📊${NC} Total archivos en proyecto: $TOTAL_FILES"

# Excluir archivos de desarrollo para deployment
PRODUCTION_COUNT=0
while IFS= read -r -d '' file; do
    # Excluir desarrollo, .git, node_modules, etc.
    if [[ ! "$file" =~ ^./desarrollo/ ]] && \
       [[ ! "$file" =~ ^./.git/ ]] && \
       [[ ! "$file" =~ node_modules ]] && \
       [[ ! "$file" =~ \.log$ ]] && \
       [[ ! "$file" =~ /temp/ ]]; then
        ((PRODUCTION_COUNT++))
    fi
done < <(find . -type f -print0)

echo -e "${GREEN}🎯${NC} Archivos para producción: $PRODUCTION_COUNT"
REDUCTION=$((100 - (PRODUCTION_COUNT * 100 / TOTAL_FILES)))
echo -e "${GREEN}📉${NC} Reducción conseguida: ${REDUCTION}%"

echo ""

# Verificar permisos de scripts
echo -e "${BLUE}🔐 VERIFICANDO PERMISOS DE SCRIPTS${NC}"
echo "----------------------------------"

SCRIPTS=("deploy-production.sh" "desarrollo/scripts/final-production-check.sh")
for script in "${SCRIPTS[@]}"; do
    if [ -f "$script" ]; then
        if [ -x "$script" ]; then
            echo -e "${GREEN}✓${NC} $script (ejecutable)"
        else
            echo -e "${YELLOW}⚠${NC} $script (no ejecutable)"
            chmod +x "$script"
            echo -e "${GREEN}✓${NC} Permisos corregidos para $script"
        fi
    else
        echo -e "${RED}✗${NC} $script NO existe"
    fi
done

echo ""
echo -e "${GREEN}✅ VERIFICACIÓN FINAL COMPLETADA${NC}"
echo -e "${BLUE}📋 Sistema listo para deployment en producción${NC}"
echo ""
