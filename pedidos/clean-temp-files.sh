#!/bin/bash

# ====================================================
# 🧹 LIMPIADOR COMPLETO DE ARCHIVOS TEMPORALES
# Script automatizado para mantener el proyecto limpio
# ====================================================

echo "🧹 LIMPIADOR COMPLETO DE ARCHIVOS TEMPORALES"
echo "📂 Directorio: $(pwd)"
echo ""

# Mostrar ayuda detallada
if [[ "$1" == "--help" ]]; then
    echo "💡 USO: $0 [--dry-run]"
    echo ""
    echo "🎯 CATEGORÍAS DE LIMPIEZA:"
    echo "  📄 Documentación temporal: README_*, DUMMY*, TEST*.md, EXAMPLE*.md"
    echo "  🧪 Archivos de pruebas: test_*.*, *.spec.*, *.test.*"
    echo "  📝 Scripts temporales: script_*.*, experiment_*.*, draft_*.*"
    echo "  💾 Respaldos: *.bak, *.old, *.backup, *~"
    echo "  🍎 Sistema: .DS_Store, Thumbs.db"
    echo "  📭 Archivos vacíos: archivos de 0 bytes"    echo "  🤖 Copilot/VSCode: archivos temporales de desarrollo"
    echo ""
    echo "🔧 OPCIONES:"
    echo "  --dry-run   Simular limpieza sin borrar archivos"
    echo "  --help      Mostrar esta ayuda"
    echo ""
    echo "🚀 FUNCIONALIDADES:"
    echo "  • Ejecuta borratemporales automáticamente"
    echo "  • Limpia múltiples categorías de archivos"
    echo "  • Modo simulación para verificar cambios"
    echo "  • Contador de archivos procesados"
    exit 0
fi

# Configurar modo de ejecución
DRY_RUN=false
if [[ "$1" == "--dry-run" ]]; then
    DRY_RUN=true
    echo "🔍 MODO SIMULACIÓN - No se borrarán archivos"
fi

total=0

echo "🎯 INICIANDO LIMPIEZA POR CATEGORÍAS:"
echo ""

# ====================================================
# 📄 CATEGORÍA 1: DOCUMENTACIÓN TEMPORAL
# ====================================================
echo "📄 [1/7] Documentación temporal..."
doc_count=0
for file in README_*.md DUMMY*.md TEST*.md EXAMPLE*.md TUTORIAL*.md; do
    if [[ -f "$file" ]]; then
        ((doc_count++))
        ((total++))
        if [[ "$DRY_RUN" == "true" ]]; then
            echo "    🔍 [SIMULACIÓN] Borraría: $file"
        else
            rm -f "$file" && echo "    🗑️  Borrado: $file"
        fi
    fi
done
echo "    ✅ Documentos temporales: $doc_count archivos"

# ====================================================
# 🧪 CATEGORÍA 2: ARCHIVOS DE PRUEBAS NO OFICIALES
# ====================================================
echo ""
echo "🧪 [2/7] Archivos de pruebas no oficiales..."
test_count=0
for file in test_*.* *.spec.* *.test.* prueba_*.* testing_*.*; do
    if [[ -f "$file" ]]; then
        ((test_count++))
        ((total++))
        if [[ "$DRY_RUN" == "true" ]]; then
            echo "    �� [SIMULACIÓN] Borraría: $file"
        else
            rm -f "$file" && echo "    🗑️  Borrado: $file"
        fi
    fi
done
echo "    ✅ Archivos de prueba: $test_count archivos"

# ====================================================
# 📝 CATEGORÍA 3: SCRIPTS TEMPORALES
# ====================================================
echo ""
echo "📝 [3/7] Scripts temporales..."
script_count=0
for file in script_*.* experiment_*.* draft_*.* scratch_*.* temp_*.*; do
    if [[ -f "$file" ]]; then
        ((script_count++))
        ((total++))
        if [[ "$DRY_RUN" == "true" ]]; then
            echo "    🔍 [SIMULACIÓN] Borraría: $file"
        else
            rm -f "$file" && echo "    🗑️  Borrado: $file"
        fi
    fi
done
echo "    ✅ Scripts temporales: $script_count archivos"

# ====================================================
# 💾 CATEGORÍA 4: ARCHIVOS DE RESPALDO
# ====================================================
echo ""
echo "💾 [4/7] Archivos de respaldo..."
backup_count=0
for file in *.bak *.old *.backup *~ *.orig *.swp; do
    if [[ -f "$file" ]]; then
        ((backup_count++))
        ((total++))
        if [[ "$DRY_RUN" == "true" ]]; then
            echo "    🔍 [SIMULACIÓN] Borraría: $file"
        else
            rm -f "$file" && echo "    🗑️  Borrado: $file"
        fi
    fi
done
echo "    ✅ Archivos de respaldo: $backup_count archivos"

# ====================================================
# 🍎 CATEGORÍA 5: ARCHIVOS DEL SISTEMA
# ====================================================
echo ""
echo "🍎 [5/7] Archivos del sistema..."
system_count=0
for file in .DS_Store Thumbs.db desktop.ini .*.tmp; do
    if [[ -f "$file" ]]; then
        ((system_count++))
        ((total++))
        if [[ "$DRY_RUN" == "true" ]]; then
            echo "    �� [SIMULACIÓN] Borraría: $file"
        else
            rm -f "$file" && echo "    🗑️  Borrado: $file"
        fi
    fi
done

# ====================================================
# 📭 CATEGORÍA 6: ARCHIVOS VACÍOS
# ====================================================
echo ""
echo "📭 [6/7] Archivos vacíos (0 bytes)..."
empty_count=0

# Buscar archivos vacíos excluyendo directorios importantes y archivos ocultos
empty_files=$(find . -maxdepth 1 -type f -size 0 -not -name ".*" -not -path "./app/*" -not -path "./public/*" -not -path "./assets/*" 2>/dev/null)

if [[ -n "$empty_files" ]]; then
    while IFS= read -r file; do
        if [[ -f "$file" ]]; then
            # Verificar que no sea un archivo importante
            filename=$(basename "$file")
            if [[ "$filename" != ".gitkeep" && "$filename" != ".htaccess" && "$filename" != "index.html" ]]; then
                # Verificar que no coincida con otros patrones ya manejados
                skip_file=false
                case "$filename" in
                    README_*.md|DUMMY*.md|TEST*.md|EXAMPLE*.md|TUTORIAL*.md) skip_file=true ;;
                    test_*.*|*.spec.*|*.test.*|prueba_*.*|testing_*.*) skip_file=true ;;
                    script_*.*|experiment_*.*|draft_*.*|scratch_*.*|temp_*.*) skip_file=true ;;
                    *.bak|*.old|*.backup|*~|*.orig|*.swp) skip_file=true ;;
                esac
                
                if [[ "$skip_file" == "false" ]]; then
                    ((empty_count++))
                    ((total++))
                    if [[ "$DRY_RUN" == "true" ]]; then
                        echo "    🔍 [SIMULACIÓN] Borraría archivo vacío: $file"
                    else
                        rm -f "$file" && echo "    🗑️  Borrado archivo vacío: $file"
                    fi
                fi
            fi
        fi
    done <<< "$empty_files"
fi
echo "    ✅ Archivos vacíos: $empty_count archivos"
echo "    ✅ Archivos del sistema: $system_count archivos"

# ====================================================
# 🤖 CATEGORÍA 6: BORRATEMPORALES AUTOMÁTICO
# ====================================================
echo ""
echo "🤖 [7/7] Ejecutando borratemporales..."
if [[ "$DRY_RUN" == "true" ]]; then
    echo "    🔍 [SIMULACIÓN] Se ejecutaría: borratemporales"
    echo "    ℹ️  Use sin --dry-run para ejecutar la limpieza completa"
else
    echo "    🚀 Ejecutando limpieza automática..."
    # Ejecutar borratemporales (es un alias)
    source ~/.zshrc 2>/dev/null || true
    if command -v borratemporales &> /dev/null; then
        borratemporales
    else
        # Fallback directo al script PHP
        php /Users/ronaldinfante/Documents/pedidos-development/desarrollo/scripts/borratemporales.php
    fi
fi

# ====================================================
# 📊 RESUMEN FINAL
# ====================================================
echo ""
echo "══════════════════════════════════════════"
echo "📊 RESUMEN DE LIMPIEZA"
echo "══════════════════════════════════════════"
echo "📄 Documentación temporal: $doc_count archivos"
echo "🧪 Archivos de prueba: $test_count archivos"
echo "📝 Scripts temporales: $script_count archivos"
echo "💾 Archivos de respaldo: $backup_count archivos"
echo "🍎 Archivos del sistema: $system_count archivos"
echo "📭 Archivos vacíos: $empty_count archivos"echo "──────────────────────────────────────────"
echo "✅ Total archivos encontrados: $total"

if [[ "$DRY_RUN" == "false" ]]; then
    if [[ $total -gt 0 ]]; then
        echo "🚀 ¡Proyecto completamente limpio!"
    else
        echo "🎉 ¡El proyecto ya estaba limpio!"
    fi
    echo "🤖 Limpieza automática completada con borratemporales"
else
    echo "🔍 Simulación completada - Use sin --dry-run para limpiar"
fi

echo "══════════════════════════════════════════"
