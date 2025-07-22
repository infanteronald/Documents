<?php
/**
 * Migración Simple de Categorías
 */

// Configuración directa de base de datos
$host = '127.0.0.1';
$username = 'motodota_facturas';
$password = 'f4ctur45_m0t0d0t4_2024';
$database = 'motodota_facturas';
$port = 3306;

echo "🔌 Conectando a la base de datos...\n";

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    echo "✅ Conectado exitosamente a la base de datos\n\n";
    
    // 1. Crear tabla de categorías
    echo "📋 1. Creando tabla categorias_productos...\n";
    $sql1 = "CREATE TABLE IF NOT EXISTS categorias_productos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        descripcion TEXT,
        icono VARCHAR(10) DEFAULT '🏷️',
        color VARCHAR(7) DEFAULT '#58a6ff',
        activa BOOLEAN DEFAULT TRUE,
        orden INT DEFAULT 0,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        
        INDEX idx_nombre (nombre),
        INDEX idx_activa (activa),
        INDEX idx_orden (orden)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql1)) {
        echo "   ✅ Tabla creada correctamente\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
    
    // 2. Insertar categorías por defecto
    echo "\n📋 2. Insertando categorías por defecto...\n";
    $sql2 = "INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden) VALUES
        ('Electrónicos', 'Dispositivos electrónicos y tecnología', '📱', '#3498db', 10),
        ('Ropa', 'Vestimenta y accesorios', '👕', '#e74c3c', 20),
        ('Hogar', 'Artículos para el hogar y decoración', '🏠', '#27ae60', 30),
        ('Deportes', 'Equipamiento deportivo y fitness', '⚽', '#f39c12', 40),
        ('Libros', 'Literatura y material educativo', '📚', '#9b59b6', 50),
        ('Alimentos', 'Productos alimenticios', '🍎', '#e67e22', 60),
        ('Herramientas', 'Herramientas y equipos de trabajo', '🔧', '#34495e', 70),
        ('Juguetes', 'Juguetes y entretenimiento', '🧸', '#e91e63', 80)";
    
    if ($conn->query($sql2)) {
        echo "   ✅ Categorías por defecto insertadas\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
    
    // 3. Agregar columna categoria_id a productos (si no existe)
    echo "\n📋 3. Agregando columna categoria_id a productos...\n";
    $sql3 = "ALTER TABLE productos ADD COLUMN IF NOT EXISTS categoria_id INT NULL";
    
    if ($conn->query($sql3)) {
        echo "   ✅ Columna agregada correctamente\n";
    } else {
        echo "   ⚠️ Nota: " . $conn->error . "\n";
    }
    
    // 4. Agregar índice
    echo "\n📋 4. Agregando índice categoria_id...\n";
    $sql4 = "ALTER TABLE productos ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id)";
    
    if ($conn->query($sql4)) {
        echo "   ✅ Índice agregado correctamente\n";
    } else {
        echo "   ⚠️ Nota: " . $conn->error . "\n";
    }
    
    // 5. Migrar categorías existentes
    echo "\n📋 5. Migrando categorías existentes desde campo VARCHAR...\n";
    $sql5 = "INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, orden)
             SELECT DISTINCT 
                 categoria as nombre,
                 CONCAT('Categoría migrada automáticamente: ', categoria) as descripcion,
                 '🏷️' as icono,
                 ROW_NUMBER() OVER (ORDER BY categoria) * 10 + 100 as orden
             FROM productos 
             WHERE categoria IS NOT NULL 
               AND categoria != '' 
               AND categoria NOT IN (SELECT nombre FROM categorias_productos)
             ORDER BY categoria";
    
    if ($conn->query($sql5)) {
        echo "   ✅ Categorías existentes migradas\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
    
    // 6. Actualizar productos para usar categoria_id
    echo "\n📋 6. Actualizando productos para usar categoria_id...\n";
    $sql6 = "UPDATE productos p
             INNER JOIN categorias_productos cp ON p.categoria = cp.nombre
             SET p.categoria_id = cp.id
             WHERE p.categoria IS NOT NULL AND p.categoria != ''";
    
    if ($conn->query($sql6)) {
        $affected = $conn->affected_rows;
        echo "   ✅ $affected productos actualizados\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
    
    // 7. Crear vista de estadísticas
    echo "\n📋 7. Creando vista de estadísticas...\n";
    $sql7 = "CREATE OR REPLACE VIEW vista_categorias_estadisticas AS
             SELECT 
                 cp.id,
                 cp.nombre,
                 cp.descripcion,
                 cp.icono,
                 cp.color,
                 cp.activa,
                 cp.orden,
                 cp.fecha_creacion,
                 cp.fecha_actualizacion,
                 COALESCE(COUNT(p.id), 0) as total_productos,
                 COALESCE(COUNT(CASE WHEN p.activo = 1 THEN 1 END), 0) as productos_activos,
                 COALESCE(SUM(CASE WHEN p.activo = 1 AND ia.stock_actual > 0 THEN ia.stock_actual ELSE 0 END), 0) as stock_total,
                 COALESCE(AVG(CASE WHEN p.activo = 1 THEN p.precio END), 0) as precio_promedio
             FROM categorias_productos cp
             LEFT JOIN productos p ON cp.id = p.categoria_id
             LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id
             GROUP BY cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color, cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion
             ORDER BY cp.orden ASC, cp.nombre ASC";
    
    if ($conn->query($sql7)) {
        echo "   ✅ Vista de estadísticas creada\n";
    } else {
        echo "   ❌ Error: " . $conn->error . "\n";
    }
    
    // Verificar resultados finales
    echo "\n📊 Verificación final:\n";
    
    $result = $conn->query("SELECT COUNT(*) as total FROM categorias_productos");
    if ($result) {
        $total_categorias = $result->fetch_assoc()['total'];
        echo "   📂 Total categorías: $total_categorias\n";
    }
    
    $result = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id IS NOT NULL");
    if ($result) {
        $productos_migrados = $result->fetch_assoc()['total'];
        echo "   📦 Productos con categoría asignada: $productos_migrados\n";
    }
    
    echo "\n🎉 ¡Migración de categorías completada exitosamente!\n";
    echo "🔗 Acceso: http://localhost/pedidos/inventario/categorias/\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>