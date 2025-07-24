<?php
/**
 * EJECUTOR DE MIGRACIÓN COMPLETA DE CATEGORÍAS
 * Ejecuta automáticamente todos los scripts de migración
 */

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Deshabilitar límites de tiempo
set_time_limit(0);

// Headers para texto plano
header('Content-Type: text/plain; charset=utf-8');

echo "==========================================\n";
echo "  MIGRACIÓN COMPLETA DE CATEGORÍAS\n";
echo "  Sequoia Speed - Sistema de Inventario\n";
echo "==========================================\n\n";

try {
    // PASO 1: Consultar categorías existentes
    echo "PASO 1: Consultando categorías existentes...\n";
    echo "-----------------------------------------------\n";
    
    $query1 = "SELECT DISTINCT categoria, COUNT(*) as total_productos 
               FROM productos 
               WHERE categoria IS NOT NULL 
                 AND categoria != '' 
                 AND categoria != 'null'
               GROUP BY categoria 
               ORDER BY categoria";
    
    $result1 = $conn->query($query1);
    if ($result1) {
        while ($row = $result1->fetch_assoc()) {
            echo "✓ {$row['categoria']} - {$row['total_productos']} productos\n";
        }
        echo "\nTotal categorías encontradas: " . $result1->num_rows . "\n\n";
    }

    // PASO 2: Migrar categorías
    echo "PASO 2: Migrando categorías a categorias_productos...\n";
    echo "-----------------------------------------------------\n";
    
    // Limpiar categorías de prueba
    $conn->query("DELETE FROM categorias_productos WHERE nombre IN ('Repuestos', 'Accesorios', 'Filtros', 'Aceites', 'Neumáticos')");
    echo "✓ Categorías de prueba eliminadas\n";
    
    // Insertar categorías específicas
    $categorias_especificas = [
        ['guantes', 'Guantes de protección y seguridad industrial', '🧤', '#ff6b6b', 10],
        ['botas', 'Botas de seguridad y protección laboral', '🥾', '#4ecdc4', 20],
        ['cascos', 'Cascos de protección industrial', '⛑️', '#45b7d1', 30],
        ['chalecos', 'Chalecos de seguridad y alta visibilidad', '🦺', '#f9ca24', 40],
        ['gafas', 'Gafas de protección y seguridad', '🥽', '#6c5ce7', 50],
        ['mascaras', 'Máscaras y respiradores de protección', '😷', '#a55eea', 60],
        ['overoles', 'Overoles y ropa de trabajo', '👔', '#26de81', 70],
        ['arneses', 'Arneses y equipos de altura', '🔗', '#fd79a8', 80],
        ['herramientas', 'Herramientas y equipos de trabajo', '🔧', '#fdcb6e', 90],
        ['equipos', 'Equipos y maquinaria industrial', '⚙️', '#74b9ff', 100]
    ];
    
    $stmt_insert = $conn->prepare("INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden, activa) VALUES (?, ?, ?, ?, ?, 1)");
    
    foreach ($categorias_especificas as $cat) {
        $stmt_insert->bind_param("ssssi", $cat[0], $cat[1], $cat[2], $cat[3], $cat[4]);
        if ($stmt_insert->execute()) {
            echo "✓ Categoría específica: {$cat[0]} {$cat[2]}\n";
        }
    }
    
    // Migrar categorías adicionales desde productos
    $query_migrar = "INSERT IGNORE INTO categorias_productos (nombre, descripcion, icono, color, orden, activa)
                     SELECT DISTINCT 
                         categoria as nombre,
                         CONCAT('Categoría migrada: ', categoria) as descripcion,
                         '🏷️' as icono,
                         '#58a6ff' as color,
                         (ROW_NUMBER() OVER (ORDER BY categoria) + 10) * 10 as orden,
                         1 as activa
                     FROM productos 
                     WHERE categoria IS NOT NULL 
                       AND categoria != '' 
                       AND categoria != 'null'
                       AND categoria NOT IN (
                         SELECT nombre FROM categorias_productos
                       )";
    
    if ($conn->query($query_migrar)) {
        echo "✓ Categorías adicionales migradas\n";
    }
    
    // Verificar categorías migradas
    $result_check = $conn->query("SELECT * FROM categorias_productos ORDER BY orden, nombre");
    echo "\nCategorías en la nueva tabla:\n";
    while ($row = $result_check->fetch_assoc()) {
        echo "  {$row['icono']} {$row['nombre']} (ID: {$row['id']})\n";
    }
    echo "\n";

    // PASO 3: Actualizar productos
    echo "PASO 3: Actualizando tabla productos...\n";
    echo "--------------------------------------\n";
    
    // Agregar columna categoria_id
    try {
        $conn->query("ALTER TABLE productos ADD COLUMN IF NOT EXISTS categoria_id INT NULL");
        echo "✓ Columna categoria_id agregada\n";
    } catch (Exception $e) {
        echo "⚠ Columna categoria_id ya existe\n";
    }
    
    // Agregar índice
    try {
        $conn->query("ALTER TABLE productos ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id)");
        echo "✓ Índice para categoria_id creado\n";
    } catch (Exception $e) {
        echo "⚠ Índice ya existe\n";
    }
    
    // Actualizar productos con categoria_id
    $query_update = "UPDATE productos p
                     INNER JOIN categorias_productos cp ON p.categoria = cp.nombre
                     SET p.categoria_id = cp.id
                     WHERE p.categoria IS NOT NULL 
                       AND p.categoria != '' 
                       AND p.categoria != 'null'
                       AND p.categoria_id IS NULL";
    
    if ($conn->query($query_update)) {
        $updated = $conn->affected_rows;
        echo "✓ Productos actualizados: {$updated}\n";
    }
    
    // Crear foreign key
    try {
        $conn->query("ALTER TABLE productos 
                      ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria 
                      FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
                      ON UPDATE CASCADE ON DELETE SET NULL");
        echo "✓ Foreign key constraint creada\n";
    } catch (Exception $e) {
        echo "⚠ Foreign key ya existe\n";
    }
    
    // VERIFICACIÓN FINAL
    echo "\nVERIFICACIÓN FINAL:\n";
    echo "==================\n";
    
    $query_final = "SELECT 
                        cp.nombre as categoria_nombre,
                        cp.icono,
                        COUNT(p.id) as total_productos
                    FROM categorias_productos cp
                    LEFT JOIN productos p ON cp.id = p.categoria_id
                    GROUP BY cp.id, cp.nombre, cp.icono
                    ORDER BY cp.orden, cp.nombre";
    
    $result_final = $conn->query($query_final);
    while ($row = $result_final->fetch_assoc()) {
        echo "  {$row['icono']} {$row['categoria_nombre']}: {$row['total_productos']} productos\n";
    }
    
    // Productos sin categoría
    $sin_categoria = $conn->query("SELECT COUNT(*) as total FROM productos WHERE categoria_id IS NULL")->fetch_assoc();
    echo "\nProductos sin categoría: {$sin_categoria['total']}\n";
    
    echo "\n🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE!\n";
    echo "\nYa puedes visitar: https://sequoiaspeed.com.co/pedidos/inventario/categorias/\n";
    echo "Para ver tus categorías migradas con sus iconos correspondientes.\n\n";

} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
}

echo "\n==========================================\n";
echo "  FIN DE LA MIGRACIÓN\n";
echo "==========================================\n";
?>