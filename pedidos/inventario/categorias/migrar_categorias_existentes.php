<?php
/**
 * Migración de Categorías Existentes
 * Lee las categorías de la tabla productos y las migra a categorias_productos
 */

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

echo "=== MIGRACIÓN DE CATEGORÍAS EXISTENTES ===\n\n";

try {
    // Verificar si existe la tabla productos
    $check_productos = $conn->query("SHOW TABLES LIKE 'productos'");
    if ($check_productos->num_rows == 0) {
        echo "❌ Error: No existe la tabla 'productos'\n";
        exit;
    }

    // Verificar si existe la columna categoria en productos
    $check_columna = $conn->query("SHOW COLUMNS FROM productos LIKE 'categoria'");
    if ($check_columna->num_rows == 0) {
        echo "❌ Error: No existe la columna 'categoria' en la tabla productos\n";
        exit;
    }

    echo "✅ Tabla productos encontrada\n";

    // Obtener categorías únicas existentes
    $query_categorias = "SELECT DISTINCT categoria, COUNT(*) as total_productos 
                        FROM productos 
                        WHERE categoria IS NOT NULL 
                        AND categoria != '' 
                        AND categoria != 'null'
                        GROUP BY categoria 
                        ORDER BY categoria";

    $result = $conn->query($query_categorias);
    $categorias_existentes = $result->fetch_all(MYSQLI_ASSOC);

    echo "📊 Categorías encontradas en productos:\n";
    foreach ($categorias_existentes as $cat) {
        echo "   - {$cat['categoria']} ({$cat['total_productos']} productos)\n";
    }
    echo "\n";

    if (empty($categorias_existentes)) {
        echo "⚠️ No se encontraron categorías en la tabla productos\n";
        exit;
    }

    // Asignar iconos y colores por categoría
    $iconos_categorias = [
        'guantes' => ['🧤', '#ff6b6b'],
        'botas' => ['🥾', '#4ecdc4'],
        'cascos' => ['⛑️', '#45b7d1'],
        'chalecos' => ['🦺', '#f9ca24'],
        'gafas' => ['🥽', '#6c5ce7'],
        'mascaras' => ['😷', '#a55eea'],
        'overoles' => ['👔', '#26de81'],
        'arneses' => ['🔗', '#fd79a8'],
        'herramientas' => ['🔧', '#fdcb6e'],
        'equipos' => ['⚙️', '#74b9ff'],
        'repuestos' => ['🔩', '#e17055'],
        'accesorios' => ['✨', '#00b894'],
        'filtros' => ['🛡️', '#0984e3'],
        'aceites' => ['🛢️', '#fdcb6e'],
        'neumaticos' => ['🛞', '#636e72'],
        'llantas' => ['🛞', '#636e72'],
        'baterias' => ['🔋', '#e84393'],
        'luces' => ['💡', '#ffeaa7'],
        'frenos' => ['🛑', '#d63031'],
        'suspension' => ['🔧', '#74b9ff']
    ];

    // Migrar cada categoría
    $migradas = 0;
    $existentes = 0;
    $errores = 0;

    foreach ($categorias_existentes as $index => $cat) {
        $nombre = trim($cat['categoria']);
        $nombre_lower = strtolower($nombre);
        
        // Buscar icono y color apropiado
        $icono = '🏷️'; // Icono por defecto
        $color = '#58a6ff'; // Color por defecto
        
        foreach ($iconos_categorias as $key => $values) {
            if (strpos($nombre_lower, $key) !== false) {
                $icono = $values[0];
                $color = $values[1];
                break;
            }
        }
        
        $descripcion = "Categoría migrada automáticamente: " . $nombre;
        $orden = ($index + 1) * 10;
        
        // Verificar si ya existe
        $stmt_check = $conn->prepare("SELECT id FROM categorias_productos WHERE nombre = ?");
        $stmt_check->bind_param("s", $nombre);
        $stmt_check->execute();
        $exists = $stmt_check->get_result()->fetch_assoc();
        
        if ($exists) {
            echo "⚠️ Ya existe: $nombre\n";
            $existentes++;
            continue;
        }
        
        // Insertar nueva categoría
        try {
            $stmt_insert = $conn->prepare("INSERT INTO categorias_productos (nombre, descripcion, icono, color, orden, activa) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt_insert->bind_param("ssssi", $nombre, $descripcion, $icono, $color, $orden);
            
            if ($stmt_insert->execute()) {
                echo "✅ Migrada: $nombre ($icono $color)\n";
                $migradas++;
            } else {
                echo "❌ Error insertando: $nombre\n";
                $errores++;
            }
        } catch (Exception $e) {
            echo "❌ Error en $nombre: " . $e->getMessage() . "\n";
            $errores++;
        }
    }

    echo "\n=== RESUMEN DE MIGRACIÓN ===\n";
    echo "✅ Migradas: $migradas\n";
    echo "⚠️ Ya existían: $existentes\n";
    echo "❌ Errores: $errores\n";

    // Ahora actualizar productos para usar categoria_id
    echo "\n=== ACTUALIZANDO PRODUCTOS ===\n";
    
    // Agregar columna categoria_id si no existe
    try {
        $conn->query("ALTER TABLE productos ADD COLUMN IF NOT EXISTS categoria_id INT NULL");
        $conn->query("ALTER TABLE productos ADD INDEX IF NOT EXISTS idx_categoria_id (categoria_id)");
        echo "✅ Columna categoria_id agregada\n";
    } catch (Exception $e) {
        echo "⚠️ Columna categoria_id ya existe o error: " . $e->getMessage() . "\n";
    }

    // Actualizar productos con categoria_id
    $productos_actualizados = 0;
    foreach ($categorias_existentes as $cat) {
        $nombre = trim($cat['categoria']);
        
        $stmt_update = $conn->prepare("
            UPDATE productos p
            INNER JOIN categorias_productos cp ON cp.nombre = ?
            SET p.categoria_id = cp.id
            WHERE p.categoria = ? AND p.categoria_id IS NULL
        ");
        $stmt_update->bind_param("ss", $nombre, $nombre);
        $stmt_update->execute();
        
        $updated = $conn->affected_rows;
        if ($updated > 0) {
            echo "✅ Actualizados $updated productos de categoría: $nombre\n";
            $productos_actualizados += $updated;
        }
    }

    echo "\n📊 Total productos actualizados: $productos_actualizados\n";

    // Crear foreign key constraint
    try {
        $conn->query("
            ALTER TABLE productos 
            ADD CONSTRAINT IF NOT EXISTS fk_productos_categoria 
            FOREIGN KEY (categoria_id) REFERENCES categorias_productos(id) 
            ON UPDATE CASCADE ON DELETE SET NULL
        ");
        echo "✅ Foreign key constraint creada\n";
    } catch (Exception $e) {
        echo "⚠️ Foreign key ya existe o error: " . $e->getMessage() . "\n";
    }

    echo "\n🎉 MIGRACIÓN COMPLETADA EXITOSAMENTE\n";

} catch (Exception $e) {
    echo "❌ Error general: " . $e->getMessage() . "\n";
}
?>