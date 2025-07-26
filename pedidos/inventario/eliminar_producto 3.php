<?php
/**
 * Eliminar/Activar/Desactivar Producto
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

// Iniciar sesión para mensajes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar parámetros
$producto_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_POST['id']) ? intval($_POST['id']) : 0);
$accion = isset($_GET['accion']) ? $_GET['accion'] : (isset($_POST['accion']) ? $_POST['accion'] : '');

// Validar parámetros
if ($producto_id <= 0) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'ID de producto inválido']);
        exit;
    } else {
        $_SESSION['errores'] = ['ID de producto inválido'];
        header('Location: productos.php');
        exit;
    }
}

// Verificar que el producto existe
$query = "SELECT id, nombre, activo, imagen FROM productos WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $producto_id);
$stmt->execute();
$result = $stmt->get_result();
$producto = $result->fetch_assoc();

if (!$producto) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Producto no encontrado']);
        exit;
    } else {
        $_SESSION['errores'] = ['Producto no encontrado'];
        header('Location: productos.php');
        exit;
    }
}

try {
    $conn->begin_transaction();
    
    if ($accion === 'toggle') {
        // Alternar estado activo/inactivo
        $nuevo_estado = isset($_POST['estado']) ? intval($_POST['estado']) : ($producto['activo'] == '1' ? 0 : 1);
        
        $query = "UPDATE productos SET activo = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ii', $nuevo_estado, $producto_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            
            $mensaje = $nuevo_estado == 1 ? 'Producto activado correctamente' : 'Producto desactivado correctamente';
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $mensaje]);
                exit;
            } else {
                $_SESSION['mensaje_exito'] = $mensaje;
                header('Location: productos.php');
                exit;
            }
        } else {
            throw new Exception('Error al actualizar el estado del producto');
        }
        
    } elseif ($accion === 'eliminar') {
        // Eliminación permanente del producto
        
        // Primero eliminar la imagen si existe
        if (!empty($producto['imagen'])) {
            $ruta_imagen = __DIR__ . '/uploads/productos/' . $producto['imagen'];
            if (file_exists($ruta_imagen)) {
                unlink($ruta_imagen);
            }
        }
        
        // Eliminar el producto de la base de datos
        $query = "DELETE FROM productos WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $producto_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            
            $mensaje = "Producto '{$producto['nombre']}' eliminado permanentemente";
            
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => $mensaje]);
                exit;
            } else {
                $_SESSION['mensaje_exito'] = $mensaje;
                header('Location: productos.php');
                exit;
            }
        } else {
            throw new Exception('Error al eliminar el producto');
        }
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    $conn->rollback();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    } else {
        $_SESSION['errores'] = [$e->getMessage()];
        header('Location: productos.php');
        exit;
    }
}

// Si llegamos aquí, redirigir por seguridad
header('Location: productos.php');
exit;
?>