#!/bin/bash

# Script para restaurar configuraciones de VSCode
# Uso: ./restore_vscode.sh

echo "=== Restauración de configuraciones de VSCode ==="

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# 1. Crear directorios necesarios
echo -e "${YELLOW}Creando directorios de configuración...${NC}"
mkdir -p ~/Library/Application\ Support/Code/User/
mkdir -p ~/Library/Application\ Support/Code/User/snippets
mkdir -p .vscode

# 2. Restaurar configuraciones globales
echo -e "${YELLOW}Restaurando configuraciones globales...${NC}"
if [ -f "conf/vscode/global/settings.json" ]; then
    cp conf/vscode/global/settings.json ~/Library/Application\ Support/Code/User/
    echo -e "${GREEN}✓ settings.json restaurado${NC}"
fi

if [ -f "conf/vscode/global/keybindings.json" ]; then
    cp conf/vscode/global/keybindings.json ~/Library/Application\ Support/Code/User/
    echo -e "${GREEN}✓ keybindings.json restaurado${NC}"
fi

if [ -d "conf/vscode/global/snippets" ]; then
    cp -r conf/vscode/global/snippets/* ~/Library/Application\ Support/Code/User/snippets/
    echo -e "${GREEN}✓ Snippets restaurados${NC}"
fi

# 3. Restaurar configuraciones del proyecto
echo -e "${YELLOW}Restaurando configuraciones del proyecto...${NC}"
if [ -d "conf/vscode/project" ]; then
    cp -r conf/vscode/project/* .vscode/
    echo -e "${GREEN}✓ Configuraciones del proyecto restauradas${NC}"
fi

# 4. Instalar extensiones
echo -e "${YELLOW}Instalando extensiones...${NC}"
if [ -f "conf/vscode/extensions.txt" ]; then
    # Verificar si el comando code está disponible
    if command -v code &> /dev/null; then
        while IFS= read -r extension || [ -n "$extension" ]; do
            # Ignorar líneas vacías y comentarios
            if [[ ! -z "$extension" && ! "$extension" =~ ^# ]]; then
                echo -e "Instalando: $extension"
                code --install-extension "$extension" --force
            fi
        done < conf/vscode/extensions.txt
        echo -e "${GREEN}✓ Extensiones instaladas${NC}"
    else
        echo -e "${RED}⚠ El comando 'code' no está disponible.${NC}"
        echo -e "  Para instalar las extensiones manualmente:"
        echo -e "  1. Abre VSCode"
        echo -e "  2. Ve a View > Command Palette (Cmd+Shift+P)"
        echo -e "  3. Escribe 'Shell Command: Install 'code' command in PATH'"
        echo -e "  4. Ejecuta este script nuevamente"
        echo ""
        echo -e "  O instala las extensiones manualmente desde conf/vscode/extensions.txt"
    fi
fi

echo -e "${GREEN}=== Restauración completada ===${NC}"
echo ""
echo "Notas adicionales:"
echo "- Si VSCode estaba abierto, ciérralo y ábrelo nuevamente"
echo "- Algunas extensiones pueden requerir reiniciar VSCode"
echo "- Revisa que todas las configuraciones se hayan aplicado correctamente"