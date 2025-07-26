<?php
/**
 * Procesar Categorías de Productos (CRUD)
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación según la acción
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {
    case 'crear':
        require_once '../../accesos/auth_helper.php';
        $current_user = auth_require('inventario', 'crear');
        break;
    case 'editar':
        require_once '../../accesos/auth_helper.php';
        $current_user = auth_require('inventario', 'actualizar');
        break;
    case 'eliminar':
        require_once '../../accesos/auth_helper.php';
        $current_user = auth_require('inventario', 'eliminar');
        break;
    default:
        $_SESSION['mensaje_error'] = 'Acción no válida';
        header('Location: index.php');
        exit;
}

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

/**
 * Función para validar datos de categoría
 */
function validarCategoria($datos) {
    $errores = [];
    
    // Validar nombre
    if (empty($datos['nombre'])) {
        $errores[] = 'El nombre de la categoría es obligatorio';
    } elseif (strlen($datos['nombre']) > 100) {
        $errores[] = 'El nombre no puede exceder los 100 caracteres';
    }
    
    // Validar icono
    if (empty($datos['icono'])) {
        $errores[] = 'Debe seleccionar un icono';
    }
    
    // Validar color
    if (empty($datos['color']) || !preg_match('/^#[0-9a-fA-F]{6}$/', $datos['color'])) {
        $errores[] = 'Debe seleccionar un color válido';
    }
    
    // Validar orden
    if (!is_numeric($datos['orden']) || $datos['orden'] < 0 || $datos['orden'] > 9999) {
        $errores[] = 'El orden debe ser un número entre 0 y 9999';
    }
    
    // Validar estado
    if (!in_array($datos['activa'], ['0', '1'])) {
        $errores[] = 'El estado debe ser activa o inactiva';
    }
    
    return $errores;
}

/**
 * Función para verificar nombre único
 */
function verificarNombreUnico($conn, $nombre, $id_excluir = null) {
    $query = "SELECT id FROM categorias_productos WHERE nombre = ?";
    $params = [$nombre];
    $types = 's';
    
    if ($id_excluir) {
        $query .= " AND id != ?";
        $params[] = $id_excluir;
        $types .= 'i';
    }
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows === 0;
}

try {
    $conn->begin_transaction();
    
    switch ($accion) {
        case 'crear':
            // Obtener datos del formulario
            $datos = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'icono' => $_POST['icono'] ?? '🏷️',
                'color' => $_POST['color'] ?? '#58a6ff',
                'activa' => $_POST['activa'] ?? '1',
                'orden' => (int)($_POST['orden'] ?? 0)
            ];
            
            // Validar datos
            $errores = validarCategoria($datos);
            
            // Verificar nombre único
            if (empty($errores) && !verificarNombreUnico($conn, $datos['nombre'])) {
                $errores[] = 'Ya existe una categoría con ese nombre';
            }
            
            if (!empty($errores)) {
                $_SESSION['mensaje_error'] = implode('<br>', $errores);
                $_SESSION['form_data'] = $datos;
                header('Location: crear_categoria.php');
                exit;
            }
            
            // Insertar categoría
            $query = "INSERT INTO categorias_productos 
                      (nombre, descripcion, icono, color, activa, orden) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssii', 
                $datos['nombre'], 
                $datos['descripcion'], 
                $datos['icono'], 
                $datos['color'], 
                $datos['activa'], 
                $datos['orden']
            );
            
            if ($stmt->execute()) {
                $categoria_id = $conn->insert_id;
                $conn->commit();
                
                $_SESSION['mensaje_exito'] = "Categoría '{$datos['nombre']}' creada exitosamente";
                header('Location: index.php');
                exit;
            } else {
                throw new Exception('Error al crear la categoría');
            }
            break;
            
        case 'editar':
            // Obtener ID
            $categoria_id = (int)($_POST['id'] ?? 0);
            
            if ($categoria_id <= 0) {
                throw new Exception('ID de categoría inválido');
            }
            
            // Verificar que existe
            $query_exist = "SELECT nombre FROM categorias_productos WHERE id = ?";
            $stmt_exist = $conn->prepare($query_exist);
            $stmt_exist->bind_param('i', $categoria_id);
            $stmt_exist->execute();
            $categoria_actual = $stmt_exist->get_result()->fetch_assoc();
            
            if (!$categoria_actual) {
                throw new Exception('Categoría no encontrada');
            }
            
            // Obtener datos del formulario
            $datos = [
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'icono' => $_POST['icono'] ?? '🏷️',
                'color' => $_POST['color'] ?? '#58a6ff',
                'activa' => $_POST['activa'] ?? '1',
                'orden' => (int)($_POST['orden'] ?? 0)
            ];
            
            // Validar datos
            $errores = validarCategoria($datos);
            
            // Verificar nombre único (excluyendo la categoría actual)
            if (empty($errores) && !verificarNombreUnico($conn, $datos['nombre'], $categoria_id)) {
                $errores[] = 'Ya existe una categoría con ese nombre';
            }
            
            if (!empty($errores)) {
                $_SESSION['mensaje_error'] = implode('<br>', $errores);
                $_SESSION['form_data'] = $datos;
                header("Location: editar_categoria.php?id=$categoria_id");
                exit;
            }
            
            // Actualizar categoría
            $query = "UPDATE categorias_productos 
                      SET nombre = ?, descripcion = ?, icono = ?, color = ?, activa = ?, orden = ?
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('ssssiii', 
                $datos['nombre'], 
                $datos['descripcion'], 
                $datos['icono'], 
                $datos['color'], 
                $datos['activa'], 
                $datos['orden'],
                $categoria_id
            );
            
            if ($stmt->execute()) {
                $conn->commit();
                
                $_SESSION['mensaje_exito'] = "Categoría '{$datos['nombre']}' actualizada exitosamente";
                header('Location: index.php');
                exit;
            } else {
                throw new Exception('Error al actualizar la categoría');
            }
            break;
            
        case 'eliminar':
            // Obtener ID
            $categoria_id = (int)($_GET['id'] ?? 0);
            
            if ($categoria_id <= 0) {
                throw new Exception('ID de categoría inválido');
            }
            
            // Verificar que existe
            $query_exist = "SELECT nombre FROM categorias_productos WHERE id = ?";
            $stmt_exist = $conn->prepare($query_exist);
            $stmt_exist->bind_param('i', $categoria_id);
            $stmt_exist->execute();
            $categoria = $stmt_exist->get_result()->fetch_assoc();
            
            if (!$categoria) {
                throw new Exception('Categoría no encontrada');
            }
            
            // Verificar que no tiene productos asignados
            $query_productos = "SELECT COUNT(*) as total FROM productos WHERE categoria_id = ?";
            $stmt_productos = $conn->prepare($query_productos);
            $stmt_productos->bind_param('i', $categoria_id);
            $stmt_productos->execute();
            $total_productos = $stmt_productos->get_result()->fetch_assoc()['total'];
            
            if ($total_productos > 0) {
                throw new Exception("No se puede eliminar la categoría porque tiene $total_productos productos asignados");
            }
            
            // Eliminar categoría
            $query = "DELETE FROM categorias_productos WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $categoria_id);
            
            if ($stmt->execute()) {
                $conn->commit();
                
                $_SESSION['mensaje_exito'] = "Categoría '{$categoria['nombre']}' eliminada exitosamente";
                header('Location: index.php');
                exit;
            } else {
                throw new Exception('Error al eliminar la categoría');
            }
            break;
            
        default:
            throw new Exception('Acción no reconocida');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    
    $_SESSION['mensaje_error'] = $e->getMessage();
    
    // Redirigir según la acción
    switch ($accion) {
        case 'crear':
            if (isset($datos)) {
                $_SESSION['form_data'] = $datos;
            }
            header('Location: crear_categoria.php');
            break;
        case 'editar':
            if (isset($datos) && isset($categoria_id)) {
                $_SESSION['form_data'] = $datos;
                header("Location: editar_categoria.php?id=$categoria_id");
            } else {
                header('Location: index.php');
            }
            break;
        default:
            header('Location: index.php');
    }
    exit;
}

// Si llegamos aquí, redirigir por seguridad
header('Location: index.php');
exit;
?>