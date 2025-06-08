#!/bin/zsh

# 🔍 VERIFICADOR DE CONFIGURACIÓN .ENV
# Verifica que los archivos .env tengan la configuración correcta

echo "🔍 VERIFICADOR DE CONFIGURACIÓN .ENV"
echo "===================================="
echo "Fecha: $(date)"
echo ""

LOCAL_PATH="/Users/ronaldinfante/Documents/pedidos"
cd "$LOCAL_PATH"

echo "📋 ARCHIVOS .ENV ENCONTRADOS:"
echo "-----------------------------"
ls -la .env* 2>/dev/null || echo "No se encontraron archivos .env"

echo ""
echo "🔍 VERIFICANDO CONFIGURACIÓN ACTUAL:"
echo "------------------------------------"

# Verificar .env.example
if [ -f ".env.example" ]; then
    echo "✅ .env.example encontrado"
    
    # Verificar configuraciones críticas
    if grep -q "DB_DATABASE=motodota_factura_electronica" .env.example; then
        echo "  ✅ Base de datos configurada correctamente"
    else
        echo "  ❌ Base de datos mal configurada"
    fi
    
    if grep -q "sequoiaspeed.com.co" .env.example; then
        echo "  ✅ URL del proyecto correcta"
    else
        echo "  ❌ URL incorrecta"
    fi
    
    if grep -q "SMTP_HOST=mail.sequoiaspeed.com.co" .env.example; then
        echo "  ✅ SMTP configurado correctamente"
    else
        echo "  ❌ SMTP mal configurado"
    fi
else
    echo "❌ .env.example NO encontrado"
fi

echo ""

# Verificar .env.production
if [ -f ".env.production" ]; then
    echo "✅ .env.production encontrado"
    
    # Verificar configuraciones críticas
    if grep -q "DB_DATABASE=motodota_factura_electronica" .env.production; then
        echo "  ✅ Base de datos producción correcta"
    else
        echo "  ❌ Base de datos producción incorrecta"
        echo "    Debería ser: DB_DATABASE=motodota_factura_electronica"
    fi
    
    if grep -q "sequoiaspeed.com.co" .env.production; then
        echo "  ✅ URL producción correcta"
    else
        echo "  ❌ URL producción incorrecta"
    fi
    
    if grep -q "APP_ENV=production" .env.production; then
        echo "  ✅ Entorno configurado como producción"
    else
        echo "  ❌ Entorno mal configurado"
    fi
else
    echo "❌ .env.production NO encontrado"
fi

echo ""
echo "🎯 CONFIGURACIONES REQUERIDAS PARA PRODUCCIÓN:"
echo "----------------------------------------------"
echo "DB_HOST=68.66.226.124"
echo "DB_DATABASE=motodota_factura_electronica"
echo "DB_USERNAME=motodota_facturacion"
echo "APP_URL=https://sequoiaspeed.com.co/pedidos"
echo "SMTP_HOST=mail.sequoiaspeed.com.co"
echo "APP_ENV=production"
echo "APP_DEBUG=false"

echo ""
echo "⚠️ IMPORTANTE:"
echo "- Los archivos .env SON NECESARIOS para el sistema MVC"
echo "- Se usan en app/config/*.php"
echo "- Deben tener configuración correcta"
echo "- NO deben incluirse en deployment (tienen datos sensibles)"

echo ""
echo "🚀 RECOMENDACIÓN:"
echo "- Mantener .env.example (sin datos sensibles)"
echo "- Corregir .env.production con datos reales"
echo "- Crear .env en servidor con configuración de producción"
echo "- Agregar .env* al .gitignore"

echo ""
echo "Verificación completada - $(date)"
