#!/bin/bash

echo "🔧 SCRIPT PREVENTIVO - ARCHIVOS QUE REAPARECEN"
echo "============================================="
echo ""

# Detectar archivos vacíos problemáticos
ARCHIVOS_VACIOS=$(find . -type f \( -name "*.md" -o -name "*.php" -o -name "*.html" -o -name "*.js" -o -name "*.css" -o -name "*.txt" -o -name "*.json" -o -name "*.sh" \) -size 0 2>/dev/null | wc -l)

if [ $ARCHIVOS_VACIOS -gt 0 ]; then
    echo "⚠️  ENCONTRADOS $ARCHIVOS_VACIOS archivos vacíos problemáticos"
    echo "🧹 ELIMINANDO ARCHIVOS VACÍOS..."
    find . -type f \( -name "*.md" -o -name "*.php" -o -name "*.html" -o -name "*.js" -o -name "*.css" -o -name "*.txt" -o -name "*.json" -o -name "*.sh" \) -size 0 -delete 2>/dev/null
    echo "✅ Archivos vacíos eliminados"
else
    echo "✅ No se encontraron archivos problemáticos"
fi

echo ""
echo "📊 ESTADO ACTUAL:"
echo "   Archivos PHP: $(find . -name "*.php" -type f | wc -l)"
echo "   Archivos CSS: $(find . -name "*.css" -type f | wc -l)"
echo "   Archivos JS: $(find . -name "*.js" -type f | wc -l)"
echo "   Archivos MD: $(find . -name "*.md" -type f | wc -l)"

echo ""
echo "🎯 VERIFICACIÓN COMPLETADA ✅"
