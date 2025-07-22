<?php
/**
 * Procesador de Operaciones CRUD para Almacenes
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../../accesos/auth_helper.php';

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Verificar token CSRF
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['mensaje_error'] = 'Token de seguridad inválido';
    header('Location: index.php');
    exit;
}

// Obtener acción
$accion = $_POST['accion'] ?? '';
$errores = [];

// Función para validar datos comunes
function validarDatosComunes($datos) {
    $errores = [];
    
    // Validar nombre
    if (empty($datos['nombre'])) {
        $errores[] = 'El nombre del almacén es requerido';
    } elseif (strlen($datos['nombre']) > 100) {
        $errores[] = 'El nombre del almacén no puede tener más de 100 caracteres';
    }
    
    // Validar ubicación
    if (empty($datos['ubicacion'])) {
        $errores[] = 'La ubicación es requerida';
    } elseif (strlen($datos['ubicacion']) > 255) {
        $errores[] = 'La ubicación no puede tener más de 255 caracteres';
    }
    
    // Validar descripción
    if (!empty($datos['descripcion']) && strlen($datos['descripcion']) > 500) {
        $errores[] = 'La descripción no puede tener más de 500 caracteres';
    }
    
    // Validar capacidad máxima
    if (!empty($datos['capacidad_maxima']) && (!is_numeric($datos['capacidad_maxima']) || $datos['capacidad_maxima'] < 0)) {
        $errores[] = 'La capacidad máxima debe ser un número positivo';
    }
    
    // Validar estado
    if (!isset($datos['activo']) || !in_array($datos['activo'], ['0', '1'])) {
        $errores[] = 'El estado del almacén es inválido';
    }
    
    return $errores;
}

// Función para limpiar datos
function limpiarDatos($datos) {
    return [
        'nombre' => trim($datos['nombre']),
        'descripcion' => trim($datos['descripcion'] ?? ''),
        'ubicacion' => trim($datos['ubicacion']),
        'capacidad_maxima' => !empty($datos['capacidad_maxima']) ? intval($datos['capacidad_maxima']) : 0,
        'activo' => intval($datos['activo'])
    ];
}

// Procesar según la acción
switch ($accion) {
    case 'crear':
        // Verificar permisos
        $current_user = auth_require('inventario', 'crear');
        
        // Obtener y limpiar datos
        $datos = limpiarDatos($_POST);
        
        // Validar datos
        $errores = validarDatosComunes($datos);
        
        // Validar que no exista el nombre
        if (empty($errores)) {
            try {
                $check_query = "SELECT id FROM almacenes WHERE nombre = ? LIMIT 1";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('s', $datos['nombre']);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errores[] = 'Ya existe un almacén con ese nombre';
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $errores[] = 'Error al verificar el nombre del almacén';
                error_log('Error en verificación de nombre: ' . $e->getMessage());
            }
        }
        
        // Si hay errores, redirigir con errores
        if (!empty($errores)) {
            $_SESSION['errores'] = $errores;
            $_SESSION['form_data'] = $_POST;
            header('Location: crear.php');
            exit;
        }
        
        // Insertar nuevo almacén
        try {
            $insert_query = "
                INSERT INTO almacenes (nombre, descripcion, ubicacion, capacidad_maxima, activo) 
                VALUES (?, ?, ?, ?, ?)
            ";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param(
                'sssii',
                $datos['nombre'],
                $datos['descripcion'],
                $datos['ubicacion'],
                $datos['capacidad_maxima'],
                $datos['activo']
            );
            
            if ($insert_stmt->execute()) {
                $almacen_id = $conn->insert_id;
                
                // Registrar en auditoría
                auth_log('create', 'almacenes', "Almacén creado: {$datos['nombre']} (ID: {$almacen_id})");
                
                $_SESSION['mensaje_exito'] = 'Almacén creado exitosamente';
                header('Location: index.php');
            } else {
                $_SESSION['errores'] = ['Error al crear el almacén'];
                $_SESSION['form_data'] = $_POST;
                header('Location: crear.php');
            }
            
            $insert_stmt->close();
        } catch (Exception $e) {
            error_log('Error al crear almacén: ' . $e->getMessage());
            $_SESSION['errores'] = ['Error interno al crear el almacén'];
            $_SESSION['form_data'] = $_POST;
            header('Location: crear.php');
        }
        break;
        
    case 'editar':
        // Verificar permisos
        $current_user = auth_require('inventario', 'actualizar');
        
        // Obtener ID
        $almacen_id = intval($_POST['id'] ?? 0);
        if ($almacen_id <= 0) {
            $_SESSION['mensaje_error'] = 'ID de almacén inválido';
            header('Location: index.php');
            exit;
        }
        
        // Obtener y limpiar datos
        $datos = limpiarDatos($_POST);
        
        // Validar datos
        $errores = validarDatosComunes($datos);
        
        // Validar que no exista el nombre en otro almacén
        if (empty($errores)) {
            try {
                $check_query = "SELECT id FROM almacenes WHERE nombre = ? AND id != ? LIMIT 1";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bind_param('si', $datos['nombre'], $almacen_id);
                $check_stmt->execute();
                
                if ($check_stmt->get_result()->num_rows > 0) {
                    $errores[] = 'Ya existe otro almacén con ese nombre';
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $errores[] = 'Error al verificar el nombre del almacén';
                error_log('Error en verificación de nombre: ' . $e->getMessage());
            }
        }
        
        // Si hay errores, redirigir con errores
        if (!empty($errores)) {
            $_SESSION['errores'] = $errores;
            $_SESSION['form_data'] = $_POST;
            header("Location: editar.php?id={$almacen_id}");
            exit;
        }
        
        // Actualizar almacén
        try {
            $update_query = "
                UPDATE almacenes 
                SET nombre = ?, descripcion = ?, ubicacion = ?, capacidad_maxima = ?, activo = ?
                WHERE id = ?
            ";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param(
                'sssiii',
                $datos['nombre'],
                $datos['descripcion'],
                $datos['ubicacion'],
                $datos['capacidad_maxima'],
                $datos['activo'],
                $almacen_id
            );
            
            if ($update_stmt->execute()) {
                // Registrar en auditoría
                auth_log('update', 'almacenes', "Almacén actualizado: {$datos['nombre']} (ID: {$almacen_id})");
                
                $_SESSION['mensaje_exito'] = 'Almacén actualizado exitosamente';
                header('Location: index.php');
            } else {
                $_SESSION['errores'] = ['Error al actualizar el almacén'];
                $_SESSION['form_data'] = $_POST;
                header("Location: editar.php?id={$almacen_id}");
            }
            
            $update_stmt->close();
        } catch (Exception $e) {
            error_log('Error al actualizar almacén: ' . $e->getMessage());
            $_SESSION['errores'] = ['Error interno al actualizar el almacén'];
            $_SESSION['form_data'] = $_POST;
            header("Location: editar.php?id={$almacen_id}");
        }
        break;
        
    case 'eliminar':
        // Verificar permisos
        $current_user = auth_require('inventario', 'eliminar');
        
        // Obtener ID
        $almacen_id = intval($_POST['id'] ?? 0);
        if ($almacen_id <= 0) {
            $_SESSION['mensaje_error'] = 'ID de almacén inválido';
            header('Location: index.php');
            exit;
        }
        
        try {
            // Verificar que el almacén existe
            $check_query = "SELECT nombre FROM almacenes WHERE id = ? LIMIT 1";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param('i', $almacen_id);
            $check_stmt->execute();
            $almacen_result = $check_stmt->get_result();
            
            if ($almacen_result->num_rows === 0) {
                $_SESSION['mensaje_error'] = 'El almacén no existe';
                header('Location: index.php');
                exit;
            }
            
            $almacen_data = $almacen_result->fetch_assoc();
            $almacen_nombre = $almacen_data['nombre'];
            $check_stmt->close();
            
            // Verificar que no tenga productos asociados
            $productos_query = "SELECT COUNT(*) as total FROM inventario_almacen WHERE almacen_id = ?";
            $productos_stmt = $conn->prepare($productos_query);
            $productos_stmt->bind_param('i', $almacen_id);
            $productos_stmt->execute();
            $productos_result = $productos_stmt->get_result()->fetch_assoc();
            
            if ($productos_result['total'] > 0) {
                $_SESSION['mensaje_error'] = 'No se puede eliminar el almacén porque tiene ' . $productos_result['total'] . ' productos asociados';
                header('Location: index.php');
                exit;
            }
            $productos_stmt->close();
            
            // Eliminar almacén
            $delete_query = "DELETE FROM almacenes WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_query);
            $delete_stmt->bind_param('i', $almacen_id);
            
            if ($delete_stmt->execute()) {
                // Registrar en auditoría
                auth_log('delete', 'almacenes', "Almacén eliminado: {$almacen_nombre} (ID: {$almacen_id})");
                
                $_SESSION['mensaje_exito'] = 'Almacén eliminado exitosamente';
            } else {
                $_SESSION['mensaje_error'] = 'Error al eliminar el almacén';
            }
            
            $delete_stmt->close();
        } catch (Exception $e) {
            error_log('Error al eliminar almacén: ' . $e->getMessage());
            $_SESSION['mensaje_error'] = 'Error interno al eliminar el almacén';
        }
        
        header('Location: index.php');
        break;
        
    default:
        $_SESSION['mensaje_error'] = 'Acción no válida';
        header('Location: index.php');
        break;
}

exit;
?>