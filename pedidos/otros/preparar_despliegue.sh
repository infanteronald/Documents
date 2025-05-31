#!/bin/bash

# 🚀 Script de Preparación para Despliegue en Producción
# Archivo: preparar_despliegue.sh
# Fecha: 31 de mayo de 2025

echo "🚀 Preparando archivos para despliegue en sequoiaspeed.com.co/pedidos/"
echo "=================================================="

# Crear directorio de despliegue
DEPLOY_DIR="./deploy_$(date +%Y%m%d_%H%M%S)"
mkdir -p "$DEPLOY_DIR"

echo "📁 Directorio de despliegue: $DEPLOY_DIR"

# Copiar archivos principales
echo "📋 Copiando archivos principales..."

# Archivos críticos que deben subirse
cp orden_pedido.php "$DEPLOY_DIR/"
cp productos_por_categoria.php "$DEPLOY_DIR/"
cp crear_pedido_inicial.php "$DEPLOY_DIR/"
cp index.php "$DEPLOY_DIR/"

# Archivos de soporte
cp apple-ui.css "$DEPLOY_DIR/"
cp pedidos.js "$DEPLOY_DIR/"
cp script.js "$DEPLOY_DIR/"
cp logo.png "$DEPLOY_DIR/"

# Archivos de gestión (opcionales pero recomendados)
cp listar_pedidos.php "$DEPLOY_DIR/"
cp ver_detalle_pedido.php "$DEPLOY_DIR/"
cp actualizar_estado.php "$DEPLOY_DIR/"
cp exportar_excel.php "$DEPLOY_DIR/"

echo "✅ Archivos copiados al directorio de despliegue"

# Crear archivo de notas para el despliegue
cat > "$DEPLOY_DIR/INSTRUCCIONES_DESPLIEGUE.txt" << 'EOF'
🚀 INSTRUCCIONES DE DESPLIEGUE
==============================

📁 ARCHIVOS PRINCIPALES (OBLIGATORIOS):
- orden_pedido.php ✅ CORREGIDO
- productos_por_categoria.php ✅ ACTUALIZADO
- crear_pedido_inicial.php ✅ FUNCIONAL
- index.php ✅ MEJORADO

📁 ARCHIVOS DE SOPORTE:
- apple-ui.css (estilos)
- pedidos.js (funcionalidades)
- script.js (utilidades)
- logo.png (imagen)

📁 ARCHIVOS DE GESTIÓN (OPCIONALES):
- listar_pedidos.php
- ver_detalle_pedido.php
- actualizar_estado.php
- exportar_excel.php

⚠️ IMPORTANTE:
1. NO subir conexion.php (usar el existente en el servidor)
2. Hacer backup del orden_pedido.php actual antes de reemplazar
3. Verificar permisos de archivos después de subir
4. Probar funcionalidad inmediatamente después del despliegue

🧪 TESTS POST-DESPLIEGUE:
1. Abrir orden_pedido.php en el navegador
2. Seleccionar una categoría → deben cargar productos
3. Crear un producto personalizado → debe agregarse al carrito
4. Finalizar un pedido → debe generar URL compartible
5. Verificar que llegan los emails de confirmación

📞 SOPORTE:
- Si hay errores, revisar logs del servidor
- Para debug, usar console.log en navegador
- Documentación completa en carpeta 'otros/'

✅ SISTEMA LISTO PARA PRODUCCIÓN
EOF

# Crear archivo de verificación rápida
cat > "$DEPLOY_DIR/verificar_despliegue.php" << 'EOF'
<?php
/**
 * 🔍 Verificación Rápida Post-Despliegue
 * Ejecutar después de subir archivos al servidor
 */

echo "<h2>🔍 Verificación Post-Despliegue</h2>";
echo "<p>Fecha: " . date('Y-m-d H:i:s') . "</p>";

// Verificar archivos principales
$archivos = [
    'orden_pedido.php' => 'Archivo principal',
    'productos_por_categoria.php' => 'API de productos',
    'crear_pedido_inicial.php' => 'Procesamiento de pedidos',
    'conexion.php' => 'Conexión a BD (existente)'
];

echo "<h3>📁 Verificación de Archivos:</h3>";
foreach ($archivos as $archivo => $descripcion) {
    if (file_exists($archivo)) {
        echo "✅ $archivo - $descripcion<br>";
    } else {
        echo "❌ $archivo - FALTA<br>";
    }
}

// Verificar conexión a BD
echo "<h3>🗄️ Verificación de Base de Datos:</h3>";
if (file_exists('conexion.php')) {
    try {
        require_once 'conexion.php';
        if ($conn) {
            echo "✅ Conexión a base de datos establecida<br>";
            
            // Verificar tablas
            $result = $conn->query("SHOW TABLES");
            echo "✅ Acceso a tablas: " . $result->num_rows . " tablas encontradas<br>";
        } else {
            echo "❌ Error en conexión a base de datos<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Archivo conexion.php no encontrado<br>";
}

echo "<h3>🧪 Próximos pasos:</h3>";
echo "<ol>";
echo "<li><a href='orden_pedido.php' target='_blank'>Probar sistema completo</a></li>";
echo "<li><a href='productos_por_categoria.php?categoria=Camisetas' target='_blank'>Probar API de productos</a></li>";
echo "<li>Crear un pedido de prueba</li>";
echo "<li>Verificar emails de confirmación</li>";
echo "</ol>";

echo "<p><strong>✅ Si todo muestra ✅, el despliegue fue exitoso</strong></p>";
?>
EOF

# Mostrar resumen
echo ""
echo "📊 RESUMEN DEL DESPLIEGUE:"
echo "=========================="
echo "📁 Directorio: $DEPLOY_DIR"
echo "📄 Archivos preparados: $(ls -1 "$DEPLOY_DIR" | wc -l | tr -d ' ')"
echo ""
echo "📋 ARCHIVOS INCLUIDOS:"
ls -la "$DEPLOY_DIR"
echo ""
echo "🎯 PRÓXIMOS PASOS:"
echo "1. Comprimir la carpeta $DEPLOY_DIR"
echo "2. Subir contenido a sequoiaspeed.com.co/pedidos/"
echo "3. Ejecutar verificar_despliegue.php en el servidor"
echo "4. Probar funcionalidad completa"
echo ""
echo "✅ Preparación completada exitosamente!"

# Crear archivo ZIP para facilitar el upload
if command -v zip &> /dev/null; then
    ZIP_NAME="deploy_orden_pedido_$(date +%Y%m%d_%H%M%S).zip"
    cd "$DEPLOY_DIR"
    zip -r "../$ZIP_NAME" ./*
    cd ..
    echo "📦 Archivo ZIP creado: $ZIP_NAME"
    echo "👆 Sube este archivo ZIP al servidor y extráelo en la carpeta pedidos/"
fi
