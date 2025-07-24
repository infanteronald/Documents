<?php
/**
 * Leer Categorías Directamente desde el Sistema
 * Sistema de Inventario - Sequoia Speed
 */

// Primero intentar con la configuración del sistema
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

echo "🔌 Intentando conectar usando configuración del sistema...\n";

try {
    // Intentar con la configuración existente del sistema
    require_once '../../config_secure.php';
    
    if ($conn && $conn->ping()) {
        echo "✅ Conectado usando configuración del sistema\n\n";
    } else {
        throw new Exception("Conexión no disponible");
    }
    
} catch (Exception $e) {
    echo "❌ Error con configuración del sistema: " . $e->getMessage() . "\n";
    echo "🔄 Intentando conexión directa...\n";
    
    // Configuraciones alternativas para probar
    $configs = [
        [
            'host' => 'localhost',
            'username' => 'motodota_facturas',
            'password' => 'f4ctur45_m0t0d0t4_2024',
            'database' => 'motodota_facturas',
            'port' => 3306
        ],
        [
            'host' => '68.66.226.124',
            'username' => 'motodota_facturas',
            'password' => 'f4ctur45_m0t0d0t4_2024',
            'database' => 'motodota_facturas',
            'port' => 3306
        ]
    ];
    
    $conn = null;
    foreach ($configs as $config) {
        try {
            echo "Probando: {$config['host']}:{$config['port']}...\n";
            $conn = new mysqli($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
            
            if (!$conn->connect_error) {
                echo "✅ Conectado a {$config['host']}\n\n";
                break;
            } else {
                echo "❌ Error: " . $conn->connect_error . "\n";
                $conn = null;
            }
        } catch (Exception $ex) {
            echo "❌ Excepción: " . $ex->getMessage() . "\n";
            $conn = null;
        }
    }
    
    if (!$conn) {
        throw new Exception("No se pudo establecer conexión con ninguna configuración");
    }
}

// Proceder con la lectura de categorías
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

$productos_por_categoria = [];
foreach ($categorias_existentes as $categoria) {
    $count_query = "SELECT COUNT(*) as total FROM productos WHERE categoria = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param('s', $categoria);
    $stmt->execute();
    $count_result = $stmt->get_result();
    $total = $count_result->fetch_assoc()['total'];
    $productos_por_categoria[$categoria] = $total;
    
    echo sprintf("%-30s: %3d productos\n", $categoria, $total);
    $stmt->close();
}

echo "-" . str_repeat("-", 50) . "\n\n";

// Generar script específico con categorías reales
echo "🛠️ Generando script de migración específico...\n";

$sql_especifico = "-- =====================================================\n";
$sql_especifico .= "-- MIGRACIÓN ESPECÍFICA DE CATEGORÍAS REALES\n";
$sql_especifico .= "-- Sistema: Sequoia Speed - Inventario\n";
$sql_especifico .= "-- Categorías encontradas: " . count($categorias_existentes) . "\n";
$sql_especifico .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
$sql_especifico .= "-- =====================================================\n\n";

// Crear tabla
$sql_especifico .= "CREATE TABLE IF NOT EXISTS categorias_productos (\n";
$sql_especifico .= "    id INT PRIMARY KEY AUTO_INCREMENT,\n";
$sql_especifico .= "    nombre VARCHAR(100) NOT NULL UNIQUE,\n";
$sql_especifico .= "    descripcion TEXT,\n";
$sql_especifico .= "    icono VARCHAR(10) DEFAULT '🏷️',\n";
$sql_especifico .= "    color VARCHAR(7) DEFAULT '#58a6ff',\n";
$sql_especifico .= "    activa BOOLEAN DEFAULT TRUE,\n";
$sql_especifico .= "    orden INT DEFAULT 0,\n";
$sql_especifico .= "    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n";
$sql_especifico .= "    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n";
$sql_especifico .= "    INDEX idx_nombre (nombre),\n";
$sql_especifico .= "    INDEX idx_activa (activa),\n";
$sql_especifico .= "    INDEX idx_orden (orden)\n";
$sql_especifico .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n\n";

// Insertar categorías específicas
$sql_especifico .= "-- Insertar categorías específicas del sistema actual\n";
$sql_especifico .= "INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden) VALUES\n";

$iconos_por_categoria = [
    'personalizado' => ['🎨', '#ff6b6b'],
    'electronics' => ['📱', '#3498db'],
    'electrónicos' => ['📱', '#3498db'],
    'ropa' => ['👕', '#e74c3c'],
    'clothing' => ['👕', '#e74c3c'],
    'hogar' => ['🏠', '#27ae60'],
    'home' => ['🏠', '#27ae60'],
    'deportes' => ['⚽', '#f39c12'],
    'sports' => ['⚽', '#f39c12'],
    'libros' => ['📚', '#9b59b6'],
    'books' => ['📚', '#9b59b6'],
    'alimentos' => ['🍎', '#e67e22'],
    'food' => ['🍎', '#e67e22'],
    'herramientas' => ['🔧', '#34495e'],
    'tools' => ['🔧', '#34495e'],
    'juguetes' => ['🧸', '#e91e63'],
    'toys' => ['🧸', '#e91e63'],
    'accesorios' => ['💍', '#8e44ad'],
    'accessories' => ['💍', '#8e44ad'],
    'belleza' => ['💄', '#f1c40f'],
    'beauty' => ['💄', '#f1c40f'],
    'automotor' => ['🚗', '#2c3e50'],
    'automotive' => ['🚗', '#2c3e50'],
    'salud' => ['💊', '#1abc9c'],
    'health' => ['💊', '#1abc9c'],
    'música' => ['🎵', '#e67e22'],
    'music' => ['🎵', '#e67e22']
];

$valores = [];
foreach ($categorias_existentes as $index => $categoria) {
    $orden = ($index + 1) * 10;
    $icono = '🏷️';
    $color = '#58a6ff';
    
    // Buscar icono específico
    $cat_lower = strtolower(trim($categoria));
    foreach ($iconos_por_categoria as $key => $config) {
        if (strpos($cat_lower, $key) !== false || $cat_lower === $key) {
            $icono = $config[0];
            $color = $config[1];
            break;
        }
    }
    
    $descripcion = "Categoría del sistema (" . $productos_por_categoria[$categoria] . " productos)";
    $categoria_escaped = str_replace("'", "''", $categoria);
    $descripcion_escaped = str_replace("'", "''", $descripcion);
    
    $valores[] = "('$categoria_escaped', '$descripcion_escaped', '$icono', '$color', $orden)";
}

$sql_especifico .= implode(",\n", $valores) . ";\n\n";

// Agregar columna categoria_id
$sql_especifico .= "ALTER TABLE productos ADD COLUMN IF NOT EXISTS categoria_id INT NULL;\n";
$sql_especifico .= "ALTER TABLE productos ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id);\n\n";

// Migrar datos
$sql_especifico .= "UPDATE productos p\n";
$sql_especifico .= "INNER JOIN categorias_productos cp ON p.categoria = cp.nombre\n";
$sql_especifico .= "SET p.categoria_id = cp.id\n";
$sql_especifico .= "WHERE p.categoria IS NOT NULL AND p.categoria != '';\n\n";

// Foreign key
$sql_especifico .= "ALTER TABLE productos \n";
$sql_especifico .= "ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria \n";
$sql_especifico .= "FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) \n";
$sql_especifico .= "ON UPDATE CASCADE ON DELETE SET NULL;\n\n";

// Vista
$sql_especifico .= "CREATE OR REPLACE VIEW vista_categorias_estadisticas AS\n";
$sql_especifico .= "SELECT \n";
$sql_especifico .= "    cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color,\n";
$sql_especifico .= "    cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion,\n";
$sql_especifico .= "    COALESCE(COUNT(p.id), 0) as total_productos,\n";
$sql_especifico .= "    COALESCE(COUNT(CASE WHEN p.activo = 1 THEN 1 END), 0) as productos_activos,\n";
$sql_especifico .= "    COALESCE(SUM(CASE WHEN p.activo = 1 AND ia.stock_actual > 0 THEN ia.stock_actual ELSE 0 END), 0) as stock_total,\n";
$sql_especifico .= "    COALESCE(AVG(CASE WHEN p.activo = 1 THEN p.precio END), 0) as precio_promedio\n";
$sql_especifico .= "FROM categorias_productos cp\n";
$sql_especifico .= "LEFT JOIN productos p ON cp.id = p.categoria_id\n";
$sql_especifico .= "LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id\n";
$sql_especifico .= "GROUP BY cp.id, cp.nombre, cp.descripcion, cp.icono, cp.color, cp.activa, cp.orden, cp.fecha_creacion, cp.fecha_actualizacion\n";
$sql_especifico .= "ORDER BY cp.orden ASC, cp.nombre ASC;\n";

// Guardar archivo
$archivo = __DIR__ . '/migracion_categorias_especificas.sql';
file_put_contents($archivo, $sql_especifico);

echo "✅ Archivo generado: migracion_categorias_especificas.sql\n";
echo "📊 Categorías incluidas: " . count($categorias_existentes) . "\n\n";

echo "🚀 Para ejecutar la migración:\n";
echo "php " . __DIR__ . "/ejecutar_migracion_especifica.php\n\n";

echo "📂 Categorías que se migrarán:\n";
foreach ($categorias_existentes as $i => $cat) {
    echo sprintf("  %2d. %-25s (%d productos)\n", $i+1, $cat, $productos_por_categoria[$cat]);
}

$conn->close();
?>