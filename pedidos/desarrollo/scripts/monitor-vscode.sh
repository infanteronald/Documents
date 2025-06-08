#!/bin/bash

# MONITOR DE RENDIMIENTO VS CODE
# Detecta y soluciona problemas de lentitud automáticamente

echo "🔍 MONITOR DE RENDIMIENTO VS CODE"
echo "================================="

# Función para obtener uso de CPU de VS Code
get_vscode_cpu() {
    ps aux | grep -i "Visual Studio Code" | grep -v grep | awk '{sum += $3} END {printf "%.1f", sum}'
}

# Función para obtener uso de memoria de VS Code
get_vscode_memory() {
    ps aux | grep -i "Visual Studio Code" | grep -v grep | awk '{sum += $4} END {printf "%.1f", sum}'
}

# Función para contar archivos abiertos
count_open_files() {
    lsof -c "Code Helper" 2>/dev/null | wc -l | tr -d ' '
}

# Obtener métricas
CPU_USAGE=$(get_vscode_cpu)
MEMORY_USAGE=$(get_vscode_memory)
OPEN_FILES=$(count_open_files)

echo "📊 MÉTRICAS ACTUALES:"
echo "• CPU: ${CPU_USAGE}%"
echo "• Memoria: ${MEMORY_USAGE}%"
echo "• Archivos abiertos: ${OPEN_FILES}"

# Diagnóstico y recomendaciones
echo ""
echo "🩺 DIAGNÓSTICO:"

# Convertir a entero para comparaciones
CPU_INT=${CPU_USAGE%.*}
MEMORY_INT=${MEMORY_USAGE%.*}

if [ "$CPU_INT" -gt 15 ] 2>/dev/null; then
    echo "⚠️  Alto uso de CPU detectado (${CPU_USAGE}%)"
    echo "💡 Recomendaciones:"
    echo "   - Cierra pestañas innecesarias"
    echo "   - Desactiva extensiones que no uses"
    echo "   - Ejecuta: optimizvscode"
    NEEDS_OPTIMIZATION=true
fi

if [ "$MEMORY_INT" -gt 20 ] 2>/dev/null; then
    echo "⚠️  Alto uso de memoria detectado (${MEMORY_USAGE}%)"
    echo "💡 Recomendaciones:"
    echo "   - Reinicia VS Code"
    echo "   - Limpia caché: optimizvscode"
    NEEDS_OPTIMIZATION=true
fi

if [ "$OPEN_FILES" -gt 1000 ]; then
    echo "⚠️  Muchos archivos abiertos ($OPEN_FILES)"
    echo "💡 Recomendaciones:"
    echo "   - Reinicia VS Code"
    echo "   - Verifica extensiones problemáticas"
    NEEDS_OPTIMIZATION=true
fi

if [ "$NEEDS_OPTIMIZATION" = true ]; then
    echo ""
    echo "🔧 ¿Quieres ejecutar optimización automática? (s/n)"
    read -r response
    if [[ "$response" =~ ^[Ss]$ ]]; then
        echo "🚀 Ejecutando optimización..."
        optimizvscode
        echo ""
        echo "✅ Optimización completada. Reinicia VS Code para mejores resultados."
    fi
else
    echo "✅ VS Code funcionando correctamente"
fi

echo ""
echo "📝 CONSEJOS PARA MANTENER RENDIMIENTO:"
echo "• Ejecuta 'optimizvscode' semanalmente"
echo "• Cierra archivos que no uses"
echo "• Desactiva extensiones innecesarias"
echo "• Usa workspaces específicos para cada parte del proyecto"
