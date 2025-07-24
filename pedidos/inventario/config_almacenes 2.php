<?php
/**
 * Configuración Centralizada de Almacenes
 * Sistema de Inventario - Sequoia Speed
 * 
 * Centraliza toda la lógica de manejo de almacenes
 * Elimina hardcoding y proporciona una API consistente
 */

// Verificar que se está ejecutando en el contexto correcto
if (!defined('SEQUOIA_SPEED_SYSTEM')) {
    die('Acceso directo no permitido');
}

class AlmacenesConfig {
    
    private static $conn = null;
    
    /**
     * Establecer conexión a la base de datos
     */
    public static function setConnection($connection) {
        self::$conn = $connection;
    }
    
    /**
     * Obtener conexión a la base de datos
     */
    private static function getConnection() {
        if (self::$conn === null) {
            global $conn;
            self::$conn = $conn;
        }
        return self::$conn;
    }
    
    /**
     * Obtener todos los almacenes
     */
    public static function getAlmacenes($activos_solo = true) {
        $conn = self::getConnection();
        $where = $activos_solo ? "WHERE activo = 1" : "";
        $query = "SELECT * FROM almacenes $where ORDER BY nombre";
        
        try {
            $result = $conn->query($query);
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            error_log("Error obteniendo almacenes: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener almacenes ordenados por prioridad
     */
    public static function getAlmacenesPorPrioridad($activos_solo = true) {
        $conn = self::getConnection();
        $where = $activos_solo ? "WHERE activo = 1" : "";
        $query = "SELECT * FROM almacenes $where
                  ORDER BY 
                    CASE codigo 
                        WHEN 'TIENDA_BOG' THEN 1 
                        WHEN 'TIENDA_MED' THEN 2 
                        WHEN 'FABRICA' THEN 3 
                        ELSE 4 
                    END, nombre";
        
        try {
            $result = $conn->query($query);
            return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        } catch (Exception $e) {
            error_log("Error obteniendo almacenes por prioridad: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener almacén por ID
     */
    public static function getAlmacenPorId($id) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT * FROM almacenes WHERE id = ? LIMIT 1");
        
        try {
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error obteniendo almacén por ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener almacén por código
     */
    public static function getAlmacenPorCodigo($codigo) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT * FROM almacenes WHERE codigo = ? LIMIT 1");
        
        try {
            $stmt->bind_param('s', $codigo);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error obteniendo almacén por código: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener almacén por nombre
     */
    public static function getAlmacenPorNombre($nombre) {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT * FROM almacenes WHERE nombre = ? LIMIT 1");
        
        try {
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error obteniendo almacén por nombre: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Obtener icono del almacén
     */
    public static function getIconoAlmacen($almacen) {
        if (is_array($almacen)) {
            return $almacen['icono'] ?? '🏪';
        }
        
        // Si es un ID, obtener el almacén
        if (is_numeric($almacen)) {
            $almacen_data = self::getAlmacenPorId($almacen);
            return $almacen_data['icono'] ?? '🏪';
        }
        
        // Si es un código, obtener el almacén
        $almacen_data = self::getAlmacenPorCodigo($almacen);
        return $almacen_data['icono'] ?? '🏪';
    }
    
    /**
     * Obtener estadísticas de un almacén
     */
    public static function getEstadisticasAlmacen($almacen_id) {
        $conn = self::getConnection();
        $query = "
            SELECT 
                COUNT(p.id) as total_productos,
                COUNT(CASE WHEN p.activo = 1 THEN 1 END) as productos_activos,
                SUM(ia.stock_actual) as stock_total,
                AVG(ia.stock_actual) as stock_promedio,
                SUM(CASE WHEN ia.stock_actual = 0 THEN 1 ELSE 0 END) as sin_stock,
                SUM(CASE WHEN ia.stock_actual <= ia.stock_minimo THEN 1 ELSE 0 END) as stock_critico,
                SUM(CASE WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 1 ELSE 0 END) as stock_bajo,
                SUM(ia.stock_actual * p.precio) as valor_inventario,
                MAX(ia.fecha_actualizacion) as ultima_actualizacion
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
            WHERE ia.almacen_id = ?
        ";
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $almacen_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas de almacén: " . $e->getMessage());
            return [
                'total_productos' => 0,
                'productos_activos' => 0,
                'stock_total' => 0,
                'stock_promedio' => 0,
                'sin_stock' => 0,
                'stock_critico' => 0,
                'stock_bajo' => 0,
                'valor_inventario' => 0,
                'ultima_actualizacion' => null
            ];
        }
    }
    
    /**
     * Validar si un almacén existe
     */
    public static function existeAlmacen($id) {
        $almacen = self::getAlmacenPorId($id);
        return $almacen !== null;
    }
    
    /**
     * Obtener productos de un almacén
     */
    public static function getProductosAlmacen($almacen_id, $filtros = []) {
        $conn = self::getConnection();
        
        $where_conditions = ['ia.almacen_id = ?'];
        $params = [$almacen_id];
        $param_types = 'i';
        
        // Filtro por búsqueda
        if (!empty($filtros['search'])) {
            $where_conditions[] = "(p.nombre LIKE ? OR p.descripcion LIKE ? OR c.nombre LIKE ? OR p.sku LIKE ?)";
            $search_param = '%' . $filtros['search'] . '%';
            $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
            $param_types .= 'ssss';
        }
        
        // Filtro por categoría
        if (!empty($filtros['categoria'])) {
            $where_conditions[] = 'c.nombre = ?';
            $params[] = $filtros['categoria'];
            $param_types .= 's';
        }
        
        // Filtro por estado de stock
        if (!empty($filtros['stock'])) {
            switch ($filtros['stock']) {
                case 'critico':
                    $where_conditions[] = 'ia.stock_actual <= ia.stock_minimo';
                    break;
                case 'bajo':
                    $where_conditions[] = 'ia.stock_actual <= (ia.stock_minimo * 1.5)';
                    break;
                case 'sin_stock':
                    $where_conditions[] = 'ia.stock_actual = 0';
                    break;
                case 'ok':
                    $where_conditions[] = 'ia.stock_actual > (ia.stock_minimo * 1.5)';
                    break;
            }
        }
        
        // Filtro por productos activos
        if (!isset($filtros['incluir_inactivos']) || !$filtros['incluir_inactivos']) {
            $where_conditions[] = 'p.activo = 1';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "
            SELECT 
                p.id, p.nombre, p.descripcion, c.nombre as categoria, p.precio, p.sku, p.imagen, p.activo,
                p.fecha_creacion, p.fecha_actualizacion,
                ia.stock_actual, ia.stock_minimo, ia.stock_maximo, ia.ubicacion_fisica,
                ia.fecha_ultima_entrada, ia.fecha_ultima_salida,
                CASE 
                    WHEN ia.stock_actual = 0 THEN 'sin_stock'
                    WHEN ia.stock_actual <= ia.stock_minimo THEN 'critico'
                    WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 'bajo'
                    ELSE 'ok'
                END as estado_stock,
                CASE 
                    WHEN ia.stock_actual = 0 THEN '🔴'
                    WHEN ia.stock_actual <= ia.stock_minimo THEN '🔴'
                    WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN '🟡'
                    ELSE '🟢'
                END as icono_stock
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
            $where_clause
            ORDER BY 
                CASE 
                    WHEN ia.stock_actual = 0 THEN 1
                    WHEN ia.stock_actual <= ia.stock_minimo THEN 2
                    WHEN ia.stock_actual <= (ia.stock_minimo * 1.5) THEN 3
                    ELSE 4
                END,
                p.nombre ASC
        ";
        
        try {
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo productos de almacén: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener el almacén por defecto
     */
    public static function getAlmacenPorDefecto() {
        // Buscar por prioridad: Tienda Bogotá, luego el primero activo
        $almacen = self::getAlmacenPorCodigo('TIENDA_BOG');
        if ($almacen) {
            return $almacen;
        }
        
        $almacenes = self::getAlmacenes(true);
        return !empty($almacenes) ? $almacenes[0] : null;
    }
    
    /**
     * Obtener categorías de productos en un almacén
     */
    public static function getCategoriasAlmacen($almacen_id) {
        $conn = self::getConnection();
        $query = "
            SELECT DISTINCT c.nombre as categoria
            FROM productos p
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id
            INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
            WHERE ia.almacen_id = ? AND p.activo = 1 AND c.nombre IS NOT NULL
            ORDER BY c.nombre
        ";
        
        try {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $almacen_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error obteniendo categorías de almacén: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Renderizar selector de almacén (HTML)
     */
    public static function renderSelector($name = 'almacen_id', $selected_id = null, $required = true, $incluir_todos = false) {
        $almacenes = self::getAlmacenesPorPrioridad(true);
        $required_attr = $required ? 'required' : '';
        
        $html = "<select name='$name' id='$name' class='form-select' $required_attr>";
        
        if ($incluir_todos) {
            $html .= "<option value=''>Todos los almacenes</option>";
        } else {
            $html .= "<option value=''>Seleccionar almacén...</option>";
        }
        
        foreach ($almacenes as $almacen) {
            $selected = ($selected_id == $almacen['id']) ? 'selected' : '';
            $icono = self::getIconoAlmacen($almacen);
            $html .= "<option value='{$almacen['id']}' $selected>";
            $html .= "$icono {$almacen['nombre']}";
            $html .= "</option>";
        }
        
        $html .= "</select>";
        return $html;
    }
    
    /**
     * Obtener array de opciones para JavaScript
     */
    public static function getOpcionesJS($activos_solo = true) {
        $almacenes = self::getAlmacenes($activos_solo);
        $opciones = [];
        
        foreach ($almacenes as $almacen) {
            $opciones[] = [
                'id' => $almacen['id'],
                'codigo' => $almacen['codigo'],
                'nombre' => $almacen['nombre'],
                'icono' => self::getIconoAlmacen($almacen),
                'activo' => $almacen['activo']
            ];
        }
        
        return $opciones;
    }
    
    /**
     * Verificar si el sistema está migrado
     */
    public static function sistemaEstaMigrado() {
        $conn = self::getConnection();
        
        try {
            // Verificar si existe la tabla de almacenes
            $result = $conn->query("SHOW TABLES LIKE 'almacenes'");
            if ($result->num_rows === 0) {
                return false;
            }
            
            // Verificar si existe la tabla de inventario nueva
            $result = $conn->query("SHOW TABLES LIKE 'inventario_almacen'");
            if ($result->num_rows === 0) {
                return false;
            }
            
            // Verificar si el campo almacen VARCHAR aún existe en productos
            $result = $conn->query("SHOW COLUMNS FROM productos LIKE 'almacen'");
            if ($result->num_rows > 0) {
                return false; // Sistema aún no migrado completamente
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error verificando migración: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener información de migración
     */
    public static function getInfoMigracion() {
        $conn = self::getConnection();
        
        try {
            $info = [
                'almacenes_consolidados' => 0,
                'productos_migrados' => 0,
                'registros_inventario' => 0,
                'sistema_migrado' => false
            ];
            
            // Contar almacenes
            $result = $conn->query("SELECT COUNT(*) as count FROM almacenes");
            if ($result && $row = $result->fetch_assoc()) {
                $info['almacenes_consolidados'] = $row['count'];
            }
            
            // Contar registros de inventario
            $result = $conn->query("SELECT COUNT(*) as count FROM inventario_almacen");
            if ($result && $row = $result->fetch_assoc()) {
                $info['registros_inventario'] = $row['count'];
            }
            
            // Contar productos migrados
            $result = $conn->query("SELECT COUNT(DISTINCT producto_id) as count FROM inventario_almacen");
            if ($result && $row = $result->fetch_assoc()) {
                $info['productos_migrados'] = $row['count'];
            }
            
            $info['sistema_migrado'] = self::sistemaEstaMigrado();
            
            return $info;
        } catch (Exception $e) {
            error_log("Error obteniendo información de migración: " . $e->getMessage());
            return [
                'almacenes_consolidados' => 0,
                'productos_migrados' => 0,
                'registros_inventario' => 0,
                'sistema_migrado' => false
            ];
        }
    }
}

// Función de compatibilidad para código legacy
function obtener_almacenes($activos_solo = true) {
    return AlmacenesConfig::getAlmacenes($activos_solo);
}

function obtener_almacen_por_id($id) {
    return AlmacenesConfig::getAlmacenPorId($id);
}

function obtener_icono_almacen($almacen) {
    return AlmacenesConfig::getIconoAlmacen($almacen);
}
?>