<?php
/**
 * Leer Categorías Existentes en la Tabla Productos
 * Sistema de Inventario - Sequoia Speed
 */

// Configuración directa de base de datos
$host = '127.0.0.1';
$username = 'motodota_facturas';
$password = 'f4ctur45_m0t0d0t4_2024';
$database = 'motodota_facturas';
$port = 3306;

echo "🔌 Conectando a la base de datos para leer categorías existentes...\n";

try {
    $conn = new mysqli($host, $username, $password, $database, $port);
    
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    
    echo "✅ Conectado exitosamente a la base de datos\n\n";
    
    // Leer categorías existentes en la tabla productos
    echo "📋 Leyendo categorías existentes en la tabla productos...\n";
    
    $query = "SELECT DISTINCT categoria 
              FROM productos 
              WHERE categoria IS NOT NULL 
                AND categoria != '' 
                AND TRIM(categoria) != ''
              ORDER BY categoria ASC";
    
    $result = $conn->query($query);
    
    if (!$result) {
        throw new Exception("Error ejecutando consulta: " . $conn->error);
    }
    
    $categorias_existentes = [];
    
    echo "\n📂 Categorías encontradas en la tabla productos:\n";
    echo "=" . str_repeat("=", 50) . "\n";
    
    $contador = 1;
    while ($row = $result->fetch_assoc()) {
        $categoria = trim($row['categoria']);
        if (!empty($categoria)) {
            $categorias_existentes[] = $categoria;
            echo sprintf("%2d. %s\n", $contador, $categoria);
            $contador++;
        }
    }
    
    echo "=" . str_repeat("=", 50) . "\n";
    echo "📊 Total de categorías únicas encontradas: " . count($categorias_existentes) . "\n\n";
    
    // Contar productos por categoría
    echo "📈 Conteo de productos por categoría:\n";
    echo "-" . str_repeat("-", 50) . "\n";
    
    foreach ($categorias_existentes as $categoria) {
        $count_query = "SELECT COUNT(*) as total FROM productos WHERE categoria = ?";
        $stmt = $conn->prepare($count_query);
        $stmt->bind_param('s', $categoria);
        $stmt->execute();
        $count_result = $stmt->get_result();
        $total = $count_result->fetch_assoc()['total'];
        
        echo sprintf("%-30s: %3d productos\n", $categoria, $total);
        $stmt->close();
    }
    
    echo "-" . str_repeat("-", 50) . "\n\n";
    
    // Crear script SQL específico con estas categorías
    echo "🛠️ Generando script SQL con categorías específicas...\n";
    
    $sql_content = "-- =====================================================\n";
    $sql_content .= "-- MIGRACIÓN DE CATEGORÍAS ESPECÍFICAS DEL SISTEMA\n";
    $sql_content .= "-- Sistema de Inventario - Sequoia Speed\n";
    $sql_content .= "-- Generado automáticamente desde categorías existentes\n";
    $sql_content .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sql_content .= "-- =====================================================\n\n";
    
    // 1. Crear tabla
    $sql_content .= "-- 1. Crear tabla de categorías\n";
    $sql_content .= "CREATE TABLE IF NOT EXISTS categorias_productos (\n";
    $sql_content .= "    id INT PRIMARY KEY AUTO_INCREMENT,\n";
    $sql_content .= "    nombre VARCHAR(100) NOT NULL UNIQUE,\n";
    $sql_content .= "    descripcion TEXT,\n";
    $sql_content .= "    icono VARCHAR(10) DEFAULT '🏷️',\n";
    $sql_content .= "    color VARCHAR(7) DEFAULT '#58a6ff',\n";
    $sql_content .= "    activa BOOLEAN DEFAULT TRUE,\n";
    $sql_content .= "    orden INT DEFAULT 0,\n";
    $sql_content .= "    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
    $sql_content .= "    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
    $sql_content .= "    \n";
    $sql_content .= "    INDEX idx_nombre (nombre),\n";
    $sql_content .= "    INDEX idx_activa (activa),\n";
    $sql_content .= "    INDEX idx_orden (orden)\n";
    $sql_content .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";
    
    // 2. Insertar categorías específicas
    $sql_content .= "-- 2. Insertar categorías específicas encontradas en el sistema\n";
    $sql_content .= "INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden) VALUES\n";
    
    // Asignar iconos y colores específicos según el nombre de la categoría
    $iconos_colores = [
        'Personalizado' => ['🎨', '#ff6b6b'],
        'personalizado' => ['🎨', '#ff6b6b'],
        'Electrónicos' => ['📱', '#3498db'],
        'Electronics' => ['📱', '#3498db'],
        'electrónicos' => ['📱', '#3498db'],
        'Ropa' => ['👕', '#e74c3c'],
        'ropa' => ['👕', '#e74c3c'],
        'Clothing' => ['👕', '#e74c3c'],
        'Hogar' => ['🏠', '#27ae60'],
        'hogar' => ['🏠', '#27ae60'],
        'Home' => ['🏠', '#27ae60'],
        'Deportes' => ['⚽', '#f39c12'],
        'deportes' => ['⚽', '#f39c12'],
        'Sports' => ['⚽', '#f39c12'],
        'Libros' => ['📚', '#9b59b6'],
        'libros' => ['📚', '#9b59b6'],
        'Books' => ['📚', '#9b59b6'],
        'Alimentos' => ['🍎', '#e67e22'],
        'alimentos' => ['🍎', '#e67e22'],
        'Food' => ['🍎', '#e67e22'],
        'Herramientas' => ['🔧', '#34495e'],
        'herramientas' => ['🔧', '#34495e'],
        'Tools' => ['🔧', '#34495e'],
        'Juguetes' => ['🧸', '#e91e63'],
        'juguetes' => ['🧸', '#e91e63'],
        'Toys' => ['🧸', '#e91e63'],
        'Accesorios' => ['💍', '#8e44ad'],
        'accesorios' => ['💍', '#8e44ad'],
        'Accessories' => ['💍', '#8e44ad'],
        'Belleza' => ['💄', '#f1c40f'],
        'belleza' => ['💄', '#f1c40f'],
        'Beauty' => ['💄', '#f1c40f'],
        'Automotor' => ['🚗', '#2c3e50'],
        'automotor' => ['🚗', '#2c3e50'],
        'Automotive' => ['🚗', '#2c3e50'],
        'Salud' => ['💊', '#1abc9c'],
        'salud' => ['💊', '#1abc9c'],
        'Health' => ['💊', '#1abc9c'],
        'Música' => ['🎵', '#e67e22'],
        'musica' => ['🎵', '#e67e22'],
        'Music' => ['🎵', '#e67e22'],
        'Arte' => ['🎨', '#9b59b6'],
        'arte' => ['🎨', '#9b59b6'],
        'Art' => ['🎨', '#9b59b6']
    ];
    
    $valores_sql = [];
    foreach ($categorias_existentes as $index => $categoria) {
        $orden = ($index + 1) * 10;
        
        // Buscar icono y color específico
        $icono = '🏷️'; // Por defecto
        $color = '#58a6ff'; // Por defecto
        
        foreach ($iconos_colores as $clave => $config) {
            if (strcasecmp($categoria, $clave) === 0 || strpos(strtolower($categoria), strtolower($clave)) !== false) {
                $icono = $config[0];
                $color = $config[1];
                break;
            }
        }
        
        $descripcion = "Categoría del sistema: " . $categoria;
        $valores_sql[] = sprintf("('%s', '%s', '%s', '%s', %d)", 
            $conn->real_escape_string($categoria),
            $conn->real_escape_string($descripcion),
            $conn->real_escape_string($icono),
            $conn->real_escape_string($color),
            $orden
        );
    }
    
    $sql_content .= implode(",\n", $valores_sql) . ";\n\n";
    
    // 3. Agregar columna categoria_id
    $sql_content .= "-- 3. Agregar columna categoria_id a productos (si no existe)\n";
    $sql_content .= "ALTER TABLE productos ADD COLUMN IF NOT EXISTS categoria_id INT NULL;\n";
    $sql_content .= "ALTER TABLE productos ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id);\n\n";
    
    // 4. Migrar datos
    $sql_content .= "-- 4. Migrar productos existentes para usar categoria_id\n";
    $sql_content .= "UPDATE productos p\n";
    $sql_content .= "INNER JOIN categorias_productos cp ON p.categoria = cp.nombre\n";
    $sql_content .= "SET p.categoria_id = cp.id\n";
    $sql_content .= "WHERE p.categoria IS NOT NULL AND p.categoria != '';\n\n";
    
    // 5. Crear foreign key
    $sql_content .= "-- 5. Crear foreign key constraint\n";
    $sql_content .= "ALTER TABLE productos \n";
    $sql_content .= "ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria \n";
    $sql_content .= "FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) \n";
    $sql_content .= "ON UPDATE CASCADE ON DELETE SET NULL;\n\n";
    
    // 6. Crear vista
    $sql_content .= "-- 6. Crear vista para estadísticas de categorías\n";
    $sql_content .= "CREATE OR REPLACE VIEW vista_categorias_estadisticas AS\n";
    $sql_content .= "SELECT \n";
    $sql_content .= "    cp.id,\n";
    $sql_content .= "    cp.nombre,\n";
    $sql_content .= "    cp.descripcion,\n";
    $sql_content .= "    cp.icono,\n";
    $sql_content .= "    cp.color,\n";
    $sql_content .= "    cp.activa,\n";
    $sql_content .= "    cp.orden,\n";
    $sql_content .= "    cp.fecha_creacion,\n";
    $sql_content .= "    cp.fecha_actualizacion,\n";
    $sql_content .= "    COALESCE(COUNT(p.id), 0) as total_productos,\n";
    $sql_content .= "    COALESCE(COUNT(CASE WHEN p.activo = 1 THEN 1 END), 0) as productos_activos,\n";
    $sql_content .= "    COALESCE(SUM(CASE WHEN p.activo = 1 AND ia.stock_actual > 0 THEN ia.stock_actual ELSE 0 END), 0) as stock_total,\n";
    $sql_content .= "    COALESCE(AVG(CASE WHEN p.activo = 1 THEN p.precio END), 0) as precio_promedio\n";
    $sql_content .= "FROM categorias_productos cp\n";
    $sql_content .= "LEFT JOIN productos p ON cp.id = p.categoria_id\n";
    $sql_content .= "LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id\n";
    $sql_content .= "GROUP BY cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color, cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion\n";
    $sql_content .= "ORDER BY cp.orden ASC, cp.nombre ASC;\n";
    
    // Guardar el script SQL
    $archivo_sql = __DIR__ . '/migracion_categorias_especificas.sql';
    file_put_contents($archivo_sql, $sql_content);
    
    echo "✅ Script SQL generado: " . $archivo_sql . "\n\n";
    
    // Mostrar resumen del script generado
    echo "📋 Resumen del script generado:\n";
    echo "- Tabla: categorias_productos\n";
    echo "- Categorías a migrar: " . count($categorias_existentes) . "\n";
    echo "- Columna: categoria_id (FK)\n";
    echo "- Vista: vista_categorias_estadisticas\n";
    echo "- Foreign Key: fk_productos_categoria\n\n";
    
    echo "🚀 Para ejecutar la migración:\n";
    echo "php " . __DIR__ . "/ejecutar_migracion_especifica.php\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}