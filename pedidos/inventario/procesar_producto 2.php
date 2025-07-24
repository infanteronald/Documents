<?php
/**
 * Procesador de Productos - Crear y Editar
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Iniciar sesión para mensajes
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: productos.php');
    exit;
}

// Obtener acción
$accion = $_POST['accion'] ?? '';
$errores = [];
$producto_id = null;

// Validar acción
if (!in_array($accion, ['crear', 'editar'])) {
    $_SESSION['errores'] = ['Acción no válida'];
    header('Location: productos.php');
    exit;
}

// Validar campos requeridos
$campos_requeridos = [
    'nombre' => 'El nombre del producto es requerido',
    'categoria_id' => 'La categoría es requerida',
    'precio' => 'El precio es requerido',
    'stock_actual' => 'El stock actual es requerido',
    'stock_minimo' => 'El stock mínimo es requerido',
    'stock_maximo' => 'El stock máximo es requerido',
    'almacen_id' => 'El almacén es requerido'
];

foreach ($campos_requeridos as $campo => $mensaje) {
    if (empty($_POST[$campo])) {
        $errores[] = $mensaje;
    }
}

// Validar tipos de datos
if (!empty($_POST['precio']) && (!is_numeric($_POST['precio']) || $_POST['precio'] < 0)) {
    $errores[] = 'El precio debe ser un número mayor o igual a 0';
}

if (!empty($_POST['stock_actual']) && (!is_numeric($_POST['stock_actual']) || $_POST['stock_actual'] < 0)) {
    $errores[] = 'El stock actual debe ser un número mayor o igual a 0';
}

if (!empty($_POST['stock_minimo']) && (!is_numeric($_POST['stock_minimo']) || $_POST['stock_minimo'] < 0)) {
    $errores[] = 'El stock mínimo debe ser un número mayor o igual a 0';
}

if (!empty($_POST['stock_maximo']) && (!is_numeric($_POST['stock_maximo']) || $_POST['stock_maximo'] < 1)) {
    $errores[] = 'El stock máximo debe ser un número mayor a 0';
}

// Validar que stock máximo sea mayor que mínimo
if (!empty($_POST['stock_maximo']) && !empty($_POST['stock_minimo'])) {
    if ($_POST['stock_maximo'] <= $_POST['stock_minimo']) {
        $errores[] = 'El stock máximo debe ser mayor que el stock mínimo';
    }
}

// Validar SKU único (si se proporciona)
if (!empty($_POST['sku'])) {
    $sku = trim($_POST['sku']);
    $query = "SELECT id FROM productos WHERE sku = ?";
    
    if ($accion === 'editar' && !empty($_POST['id'])) {
        $query .= " AND id != ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $sku, $_POST['id']);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param('s', $sku);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errores[] = 'El SKU ya existe para otro producto';
    }
}

// Validar longitudes
$longitudes = [
    'nombre' => 255,
    'categoria' => 50,
    'sku' => 40
];

foreach ($longitudes as $campo => $max_length) {
    if (!empty($_POST[$campo]) && strlen($_POST[$campo]) > $max_length) {
        $errores[] = "El campo $campo no puede tener más de $max_length caracteres";
    }
}

// Procesar imagen si se subió
$nombre_imagen = '';
$imagen_procesada = false;

if (!empty($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $resultado_imagen = procesarImagen($_FILES['imagen']);
    
    if ($resultado_imagen['success']) {
        $nombre_imagen = $resultado_imagen['filename'];
        $imagen_procesada = true;
    } else {
        $errores[] = $resultado_imagen['error'];
    }
}

// Si hay errores, regresar al formulario
if (!empty($errores)) {
    $_SESSION['errores'] = $errores;
    $_SESSION['form_data'] = $_POST;
    
    if ($accion === 'crear') {
        header('Location: crear_producto.php');
    } else {
        header('Location: editar_producto.php?id=' . $_POST['id']);
    }
    exit;
}

// Preparar datos para inserción/actualización
$datos = [
    'nombre' => trim($_POST['nombre']),
    'descripcion' => trim($_POST['descripcion']),
    'categoria_id' => (int)$_POST['categoria_id'],
    'precio' => (int)$_POST['precio'],
    'stock_actual' => (int)$_POST['stock_actual'],
    'stock_minimo' => (int)$_POST['stock_minimo'],
    'stock_maximo' => (int)$_POST['stock_maximo'],
    'almacen_id' => (int)$_POST['almacen_id'],
    'activo' => $_POST['activo'] === '1' ? 1 : 0,
    'sku' => !empty($_POST['sku']) ? trim($_POST['sku']) : null
];

// Validar que el almacén exista
if (!AlmacenesConfig::existeAlmacen($datos['almacen_id'])) {
    $errores[] = 'El almacén seleccionado no existe';
}

try {
    $conn->begin_transaction();
    
    if ($accion === 'crear') {
        // Crear producto (sin campos de almacén/stock en tabla principal)
        $query = "INSERT INTO productos (nombre, descripcion, categoria_id, precio, activo, sku, imagen) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiiiis', 
            $datos['nombre'],
            $datos['descripcion'],
            $datos['categoria_id'],
            $datos['precio'],
            $datos['activo'],
            $datos['sku'],
            $nombre_imagen
        );
        
        if ($stmt->execute()) {
            $producto_id = $conn->insert_id;
            
            // Crear registro en inventario_almacen
            $query_inventario = "INSERT INTO inventario_almacen (producto_id, almacen_id, stock_actual, stock_minimo, stock_maximo) 
                                VALUES (?, ?, ?, ?, ?)";
            $stmt_inventario = $conn->prepare($query_inventario);
            $stmt_inventario->bind_param('iiiii', 
                $producto_id,
                $datos['almacen_id'],
                $datos['stock_actual'],
                $datos['stock_minimo'],
                $datos['stock_maximo']
            );
            
            if ($stmt_inventario->execute()) {
                $mensaje = "Producto creado exitosamente";
                
                // Notificar si el stock está bajo
                if ($datos['stock_actual'] <= $datos['stock_minimo']) {
                    $mensaje .= " ⚠️ Advertencia: El stock actual está por debajo del mínimo permitido";
                }
            } else {
                throw new Exception('Error al crear el inventario: ' . $stmt_inventario->error);
            }
            
        } else {
            throw new Exception('Error al crear el producto: ' . $stmt->error);
        }
        
    } else {
        // Editar producto
        $producto_id = (int)$_POST['id'];
        
        // Obtener datos actuales del producto
        $query_actual = "SELECT imagen FROM productos WHERE id = ?";
        $stmt_actual = $conn->prepare($query_actual);
        $stmt_actual->bind_param('i', $producto_id);
        $stmt_actual->execute();
        $resultado_actual = $stmt_actual->get_result();
        $producto_actual = $resultado_actual->fetch_assoc();
        
        // Determinar qué imagen usar
        $imagen_final = $producto_actual['imagen'];
        
        if ($imagen_procesada) {
            // Nueva imagen subida
            $imagen_final = $nombre_imagen;
            
            // Eliminar imagen anterior si existe
            if (!empty($producto_actual['imagen'])) {
                $ruta_imagen_anterior = __DIR__ . '/uploads/productos/' . $producto_actual['imagen'];
                if (file_exists($ruta_imagen_anterior)) {
                    unlink($ruta_imagen_anterior);
                }
            }
        } elseif (!empty($_POST['eliminar_imagen'])) {
            // Eliminar imagen actual
            if (!empty($producto_actual['imagen'])) {
                $ruta_imagen_anterior = __DIR__ . '/uploads/productos/' . $producto_actual['imagen'];
                if (file_exists($ruta_imagen_anterior)) {
                    unlink($ruta_imagen_anterior);
                }
            }
            $imagen_final = null;
        }
        
        // Actualizar producto (sin campos stock)
        $query = "UPDATE productos SET 
                  nombre = ?, descripcion = ?, categoria_id = ?, precio = ?, 
                  activo = ?, sku = ?, imagen = ?
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssiissi', 
            $datos['nombre'],
            $datos['descripcion'],
            $datos['categoria_id'],
            $datos['precio'],
            $datos['activo'],
            $datos['sku'],
            $imagen_final,
            $producto_id
        );
        
        if ($stmt->execute()) {
            // Actualizar inventario en almacén
            $inventario_query = "UPDATE inventario_almacen SET 
                                stock_actual = ?, stock_minimo = ?, stock_maximo = ?,
                                almacen_id = ?, fecha_actualizacion = CURRENT_TIMESTAMP
                                WHERE producto_id = ?";
            $inventario_stmt = $conn->prepare($inventario_query);
            $inventario_stmt->bind_param('iiiii', 
                $datos['stock_actual'],
                $datos['stock_minimo'],
                $datos['stock_maximo'],
                $datos['almacen_id'],
                $producto_id
            );
            
            if (!$inventario_stmt->execute()) {
                throw new Exception('Error al actualizar el inventario: ' . $inventario_stmt->error);
            }
            
            $mensaje = "Producto actualizado exitosamente";
            
            // Notificar si el stock está bajo
            if ($datos['stock_actual'] <= $datos['stock_minimo']) {
                $mensaje .= " ⚠️ Advertencia: El stock actual está por debajo del mínimo permitido";
            }
            
        } else {
            throw new Exception('Error al actualizar el producto: ' . $stmt->error);
        }
    }
    
    $conn->commit();
    
    // Mensaje de éxito
    $_SESSION['mensaje_exito'] = $mensaje;
    
    // Notificar cambios de stock bajo
    if ($datos['stock_actual'] <= $datos['stock_minimo']) {
        notificarStockBajo($producto_id, $datos['nombre'], $datos['stock_actual'], $datos['stock_minimo']);
    }
    
    // Redirigir según la acción
    if ($accion === 'crear') {
        header('Location: productos.php');
    } else {
        header('Location: editar_producto.php?id=' . $producto_id);
    }
    
} catch (Exception $e) {
    $conn->rollback();
    
    // Limpiar imagen si se subió pero falló la BD
    if ($imagen_procesada && !empty($nombre_imagen)) {
        $ruta_imagen = __DIR__ . '/uploads/productos/' . $nombre_imagen;
        if (file_exists($ruta_imagen)) {
            unlink($ruta_imagen);
        }
    }
    
    $_SESSION['errores'] = [$e->getMessage()];
    $_SESSION['form_data'] = $_POST;
    
    if ($accion === 'crear') {
        header('Location: crear_producto.php');
    } else {
        header('Location: editar_producto.php?id=' . $_POST['id']);
    }
}

exit;

/**
 * Función para procesar imagen subida
 */
function procesarImagen($archivo) {
    $directorio_upload = __DIR__ . '/uploads/productos/';
    
    // Crear directorio si no existe
    if (!is_dir($directorio_upload)) {
        if (!mkdir($directorio_upload, 0755, true)) {
            return ['success' => false, 'error' => 'No se pudo crear el directorio de imágenes'];
        }
    }
    
    // Validar que sea una imagen
    $info_archivo = getimagesize($archivo['tmp_name']);
    if (!$info_archivo) {
        return ['success' => false, 'error' => 'El archivo no es una imagen válida'];
    }
    
    // Validar tamaño (máximo 5MB)
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'La imagen no puede ser mayor a 5MB'];
    }
    
    // Validar tipo MIME
    $tipos_permitidos = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($info_archivo['mime'], $tipos_permitidos)) {
        return ['success' => false, 'error' => 'Solo se permiten imágenes JPG, PNG o WebP'];
    }
    
    // Generar nombre único
    $extension = match($info_archivo['mime']) {
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        default => 'jpg'
    };
    
    $nombre_archivo = uniqid('producto_') . '.' . $extension;
    $ruta_destino = $directorio_upload . $nombre_archivo;
    
    // Crear imagen desde el archivo
    $imagen_origen = match($info_archivo['mime']) {
        'image/jpeg' => imagecreatefromjpeg($archivo['tmp_name']),
        'image/png' => imagecreatefrompng($archivo['tmp_name']),
        'image/webp' => imagecreatefromwebp($archivo['tmp_name']),
        default => null
    };
    
    if (!$imagen_origen) {
        return ['success' => false, 'error' => 'Error al procesar la imagen'];
    }
    
    // Obtener dimensiones originales
    $ancho_original = imagesx($imagen_origen);
    $alto_original = imagesy($imagen_origen);
    
    // Calcular nuevas dimensiones (máximo 800x600)
    $max_ancho = 800;
    $max_alto = 600;
    
    if ($ancho_original <= $max_ancho && $alto_original <= $max_alto) {
        // No es necesario redimensionar
        $nuevo_ancho = $ancho_original;
        $nuevo_alto = $alto_original;
    } else {
        // Calcular proporción
        $proporcion_ancho = $max_ancho / $ancho_original;
        $proporcion_alto = $max_alto / $alto_original;
        $proporcion = min($proporcion_ancho, $proporcion_alto);
        
        $nuevo_ancho = round($ancho_original * $proporcion);
        $nuevo_alto = round($alto_original * $proporcion);
    }
    
    // Crear imagen redimensionada
    $imagen_destino = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    
    // Preservar transparencia para PNG
    if ($info_archivo['mime'] === 'image/png') {
        imagealphablending($imagen_destino, false);
        imagesavealpha($imagen_destino, true);
        $transparente = imagecolorallocatealpha($imagen_destino, 255, 255, 255, 127);
        imagefill($imagen_destino, 0, 0, $transparente);
    }
    
    // Redimensionar
    imagecopyresampled(
        $imagen_destino, $imagen_origen,
        0, 0, 0, 0,
        $nuevo_ancho, $nuevo_alto,
        $ancho_original, $alto_original
    );
    
    // Guardar imagen
    $guardado = match($info_archivo['mime']) {
        'image/jpeg' => imagejpeg($imagen_destino, $ruta_destino, 90),
        'image/png' => imagepng($imagen_destino, $ruta_destino, 6),
        'image/webp' => imagewebp($imagen_destino, $ruta_destino, 90),
        default => false
    };
    
    // Limpiar memoria
    imagedestroy($imagen_origen);
    imagedestroy($imagen_destino);
    
    if ($guardado) {
        return ['success' => true, 'filename' => $nombre_archivo];
    } else {
        return ['success' => false, 'error' => 'Error al guardar la imagen'];
    }
}

/**
 * Función para notificar stock bajo
 */
function notificarStockBajo($producto_id, $nombre_producto, $stock_actual, $stock_minimo) {
    // Aquí puedes implementar la lógica de notificación
    // Por ejemplo, enviar email, notificación push, etc.
    
    $mensaje = "⚠️ Stock bajo: $nombre_producto tiene $stock_actual unidades (mínimo: $stock_minimo)";
    
    // Log para registro
    error_log("STOCK BAJO - Producto ID: $producto_id, Nombre: $nombre_producto, Stock: $stock_actual/$stock_minimo");
    
    // Aquí podrías agregar más lógica de notificación
    // Por ejemplo, enviar email a administradores, crear alerta, etc.
}
?>