#!/bin/bash

# ==============================================================================
# SCRIPT DE LIMPIEZA DE ARCHIVOS DE BACKUP
# ==============================================================================
# Limpia los archivos de backup creados durante la migración de seguridad
# Autor: Claude Assistant
# Fecha: 2024-12-16
# ==============================================================================

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}==========================================${NC}"
echo -e "${BLUE}  LIMPIEZA DE ARCHIVOS DE BACKUP${NC}"
echo -e "${BLUE}==========================================${NC}"
echo ""

# Función para mostrar archivos
show_file_info() {
    local file=$1
    if [ -f "$file" ]; then
        local size=$(ls -lh "$file" | awk '{print $5}')
        local date=$(ls -lh "$file" | awk '{print $6, $7, $8}')
        echo -e "  📄 ${YELLOW}$file${NC} (${size}, ${date})"
    fi
}

# Buscar archivos de backup
echo -e "${YELLOW}Buscando archivos de backup...${NC}"
echo ""

backup_files=(
    "conexion.php.backup"
    "conexion.php.backup.original"
    "conexion.php.backup.*"
    "*.backup"
    "*.bak"
    "*_backup"
    "backup_*"
)

found_files=()

# Buscar archivos que coincidan con los patrones
for pattern in "${backup_files[@]}"; do
    while IFS= read -r -d '' file; do
        found_files+=("$file")
    done < <(find . -maxdepth 3 -name "$pattern" -type f -print0 2>/dev/null)
done

# Eliminar duplicados
found_files=($(echo "${found_files[@]}" | tr ' ' '\n' | sort -u | tr '\n' ' '))

if [ ${#found_files[@]} -eq 0 ]; then
    echo -e "${GREEN}✅ No se encontraron archivos de backup${NC}"
    echo ""
    exit 0
fi

# Mostrar archivos encontrados
echo -e "${YELLOW}Se encontraron ${#found_files[@]} archivos de backup:${NC}"
echo ""

total_size=0
for file in "${found_files[@]}"; do
    if [ -f "$file" ]; then
        show_file_info "$file"
        size_bytes=$(stat -f%z "$file" 2>/dev/null || stat -c%s "$file" 2>/dev/null)
        total_size=$((total_size + size_bytes))
    fi
done

# Convertir tamaño total a formato legible
if command -v numfmt >/dev/null 2>&1; then
    human_size=$(numfmt --to=iec-i --suffix=B $total_size)
else
    human_size="${total_size} bytes"
fi

echo ""
echo -e "${BLUE}Espacio total ocupado: ${human_size}${NC}"
echo ""

# Verificar el estado del sistema antes de proceder
echo -e "${YELLOW}Verificando el estado del sistema...${NC}"

# Ejecutar script de verificación si existe
if [ -f "verify_security_implementation.php" ]; then
    echo -e "${BLUE}Ejecutando verificación de seguridad...${NC}"
    php verify_security_implementation.php > /tmp/verify_output.txt 2>&1
    
    if grep -q "SISTEMA DE SEGURIDAD IMPLEMENTADO CORRECTAMENTE" /tmp/verify_output.txt; then
        echo -e "${GREEN}✅ Sistema de seguridad funcionando correctamente${NC}"
    else
        echo -e "${YELLOW}⚠️  Advertencia: El sistema puede tener problemas${NC}"
        echo -e "${YELLOW}   Revisa el output de verify_security_implementation.php${NC}"
    fi
    rm -f /tmp/verify_output.txt
else
    echo -e "${YELLOW}⚠️  No se encontró script de verificación${NC}"
fi

echo ""

# Preguntar confirmación
echo -e "${RED}⚠️  ADVERTENCIA: Esta acción eliminará permanentemente los archivos de backup${NC}"
echo -e "${YELLOW}Asegúrate de que el sistema esté funcionando correctamente antes de continuar${NC}"
echo ""
echo -e "${BLUE}¿Deseas eliminar estos archivos? (s/n)${NC}"
read -r respuesta

if [ "$respuesta" != "s" ] && [ "$respuesta" != "S" ]; then
    echo ""
    echo -e "${YELLOW}Operación cancelada${NC}"
    exit 0
fi

# Segunda confirmación para mayor seguridad
echo ""
echo -e "${RED}¿Estás ABSOLUTAMENTE SEGURO? Esta acción no se puede deshacer (s/n)${NC}"
read -r confirmacion

if [ "$confirmacion" != "s" ] && [ "$confirmacion" != "S" ]; then
    echo ""
    echo -e "${YELLOW}Operación cancelada${NC}"
    exit 0
fi

# Eliminar archivos
echo ""
echo -e "${YELLOW}Eliminando archivos de backup...${NC}"

errors=0
for file in "${found_files[@]}"; do
    if [ -f "$file" ]; then
        if rm -f "$file"; then
            echo -e "${GREEN}✅ Eliminado: $file${NC}"
        else
            echo -e "${RED}❌ Error al eliminar: $file${NC}"
            errors=$((errors + 1))
        fi
    fi
done

echo ""

# Buscar y limpiar directorios de backup vacíos
echo -e "${YELLOW}Buscando directorios de backup vacíos...${NC}"
backup_dirs=$(find . -maxdepth 3 -type d -name "*backup*" -empty 2>/dev/null)

if [ -n "$backup_dirs" ]; then
    echo "$backup_dirs" | while read -r dir; do
        if rmdir "$dir" 2>/dev/null; then
            echo -e "${GREEN}✅ Directorio vacío eliminado: $dir${NC}"
        fi
    done
else
    echo -e "${GREEN}✅ No se encontraron directorios de backup vacíos${NC}"
fi

# Resumen final
echo ""
echo -e "${BLUE}==========================================${NC}"
if [ $errors -eq 0 ]; then
    echo -e "${GREEN}✅ LIMPIEZA COMPLETADA EXITOSAMENTE${NC}"
    echo -e "${GREEN}   ${#found_files[@]} archivos eliminados${NC}"
    echo -e "${GREEN}   Espacio liberado: ${human_size}${NC}"
else
    echo -e "${YELLOW}⚠️  LIMPIEZA COMPLETADA CON ERRORES${NC}"
    echo -e "${YELLOW}   ${errors} archivos no se pudieron eliminar${NC}"
fi
echo -e "${BLUE}==========================================${NC}"

# Recomendaciones finales
echo ""
echo -e "${BLUE}Recomendaciones:${NC}"
echo "1. Verifica que el sistema siga funcionando correctamente"
echo "2. Si hay problemas, restaura desde el backup del servidor"
echo "3. Documenta esta limpieza en el log de cambios"
echo ""

# Crear log de limpieza
log_file="cleanup_log_$(date +%Y%m%d_%H%M%S).txt"
echo "Limpieza de backups ejecutada: $(date)" > "$log_file"
echo "Archivos eliminados: ${#found_files[@]}" >> "$log_file"
echo "Espacio liberado: ${human_size}" >> "$log_file"
echo "Errores: $errors" >> "$log_file"

echo -e "${BLUE}Log de limpieza guardado en: $log_file${NC}"
echo ""

exit 0