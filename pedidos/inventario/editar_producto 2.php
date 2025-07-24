<?php
/**
 * Formulario para Editar Producto Existente
 * Sequoia Speed - Módulo de Inventario
 */

// Configuración de errores para producción
error_reporting(0);
ini_set('display_errors', 0);

// Iniciar sesión antes de incluir auth_helper
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../accesos/auth_helper.php';

// Verificar si el usuario está autenticado primero
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header('Location: ../accesos/login.php');
    exit;
}

// Proteger la página - requiere permisos de inventario
try {
    $current_user = auth_require('inventario', 'actualizar');
} catch (Exception $e) {
    // Si hay un error con los permisos, redirigir con mensaje
    $_SESSION['error_msg'] = 'No tienes permisos para editar productos';
    header('Location: productos.php');
    exit;
}

// Registrar acceso
auth_log('read', 'inventario', 'Acceso al formulario de edición de producto');

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';
require_once 'config_almacenes.php';

// Configurar conexión para AlmacenesConfig
AlmacenesConfig::setConnection($conn);

// Función para generar token CSRF si no existe
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Obtener ID del producto
$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id <= 0) {
    $_SESSION['error_msg'] = 'ID de producto inválido';
    header('Location: productos.php');
    exit;
}

// Obtener datos del producto con información de inventario
try {
    $query = "SELECT p.*, 
                     ia.almacen_id, ia.stock_actual, ia.stock_minimo, ia.stock_maximo,
                     ia.ubicacion_fisica, a.nombre as almacen_nombre
              FROM productos p
              LEFT JOIN inventario_almacen ia ON p.id = ia.producto_id
              LEFT JOIN almacenes a ON ia.almacen_id = a.id
              WHERE p.id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param('i', $producto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $producto = $result->fetch_assoc();

    if (!$producto) {
        $_SESSION['error_msg'] = 'Producto no encontrado';
        header('Location: productos.php');
        exit;
    }

    // Registrar acceso específico al producto
    auth_log('read', 'inventario', 'Consulta de producto ID: ' . $producto_id);

} catch (Exception $e) {
    error_log('Error obteniendo producto: ' . $e->getMessage());
    $_SESSION['error_msg'] = 'Error al obtener el producto';
    header('Location: productos.php');
    exit;
}

// Obtener categorías existentes para el selector
$categorias = [];
$almacenes = [];

try {
    // Categorías
    $categorias_query = "SELECT id, nombre, icono, color FROM categorias_productos WHERE activa = 1 ORDER BY orden ASC, nombre ASC";
    $categorias_result = $conn->query($categorias_query);
    if ($categorias_result) {
        $categorias = $categorias_result->fetch_all(MYSQLI_ASSOC);
    }

    // Almacenes usando la nueva configuración
    $almacenes = AlmacenesConfig::getAlmacenes();

} catch (Exception $e) {
    error_log('Error obteniendo categorías/almacenes: ' . $e->getMessage());
    // Continuar con arrays vacíos
}

// Si hay datos en la sesión (errores de validación), mantenerlos
if (isset($_SESSION['form_data'])) {
    $producto = array_merge($producto, $_SESSION['form_data']);
    unset($_SESSION['form_data']);
}

// Mensajes de error
$errores = $_SESSION['errores'] ?? [];
unset($_SESSION['errores']);

// Mensaje de éxito
$mensaje_exito = $_SESSION['mensaje_exito'] ?? '';
unset($_SESSION['mensaje_exito']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>✏️ Editar Producto - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>✏️</text></svg>">
    <link rel="stylesheet" href="productos.css">
    <link rel="stylesheet" href="../notifications/notifications.css">

</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">✏️ <?php echo htmlspecialchars($producto['nombre']); ?></h1>
                    <div class="breadcrumb">
                        <a href="../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="productos.php">📦 Productos</a>
                        <span>/</span>
                        <span>✏️ Editar</span>
                    </div>
                    
                    <!-- Información resumida del producto -->
                    <div class="producto-info-summary">
                        ID: #<?php echo $producto['id']; ?> • 
                        Estado: <span class="estado-badge <?php echo $producto['activo'] ? 'activo' : 'inactivo'; ?>"><?php echo $producto['activo'] ? '✅ Activo' : '❌ Inactivo'; ?></span> • 
                        Creado: <?php echo date('d/m/Y H:i', strtotime($producto['fecha_creacion'])); ?><?php if (!empty($producto['fecha_actualizacion'])): ?> • Última actualización: <?php echo date('d/m/Y H:i', strtotime($producto['fecha_actualizacion'])); ?><?php endif; ?>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <a href="productos.php" class="btn btn-secondary">
                        ← Volver al Listado
                    </a>
                    <?php if (auth_can('inventario', 'leer')): ?>
                        <button onclick="verHistorial(<?php echo $producto['id']; ?>)" class="btn btn-info">
                            📋 Ver Historial
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <!-- Mensajes -->
        <?php if (!empty($mensaje_exito)): ?>
            <div class="mensaje mensaje-exito">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">✅</span>
                    <span><?php echo htmlspecialchars($mensaje_exito); ?></span>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errores)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">❌</span>
                    <div>
                        <strong>Error al actualizar el producto:</strong>
                        <ul>
                            <?php foreach ($errores as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                <button onclick="this.parentElement.remove()" class="mensaje-cerrar">×</button>
            </div>
        <?php endif; ?>


        <!-- Formulario -->
        <div class="form-section">
            <div class="form-container">
                <form action="procesar_producto.php" method="POST" enctype="multipart/form-data" id="formEditarProducto">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                    <input type="hidden" name="imagen_actual" value="<?php echo htmlspecialchars($producto['imagen'] ?? ''); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-grid">
                        <!-- Información básica -->
                        <div class="form-group-section">
                            <h3 class="section-title">📋 Información Básica</h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="nombre">📦 Nombre del Producto *</label>
                                    <input type="text"
                                           id="nombre"
                                           name="nombre"
                                           value="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                           required
                                           maxlength="255"
                                           placeholder="Ej: Camiseta deportiva Nike">
                                </div>

                                <div class="form-group">
                                    <label for="sku">🔖 SKU (Código único)</label>
                                    <input type="text"
                                           id="sku"
                                           name="sku"
                                           value="<?php echo htmlspecialchars($producto['sku'] ?? ''); ?>"
                                           maxlength="40"
                                           placeholder="Ej: NIKE-001">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="descripcion">📝 Descripción</label>
                                <textarea id="descripcion"
                                          name="descripcion"
                                          rows="3"
                                          placeholder="Descripción detallada del producto..."><?php echo htmlspecialchars($producto['descripcion'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="categoria_id">🏷️ Categoría *</label>
                                    <select id="categoria_id" name="categoria_id" required>
                                        <option value="">-- Seleccionar categoría --</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" 
                                                    <?php echo ($producto['categoria_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['icono'] . ' ' . $cat['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-help">
                                        <a href="categorias/index.php" target="_blank">🗂️ Gestionar categorías</a>
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="precio">💰 Precio *</label>
                                    <input type="number"
                                           id="precio"
                                           name="precio"
                                           value="<?php echo $producto['precio']; ?>"
                                           required
                                           min="0"
                                           step="1"
                                           placeholder="0">
                                </div>
                            </div>
                        </div>

                        <!-- Inventario -->
                        <div class="form-group-section">
                            <h3 class="section-title">📊 Control de Inventario</h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="stock_actual">📦 Stock Actual *</label>
                                    <input type="number"
                                           id="stock_actual"
                                           name="stock_actual"
                                           value="<?php echo $producto['stock_actual']; ?>"
                                           required
                                           min="0"
                                           placeholder="0">
                                </div>

                                <div class="form-group">
                                    <label for="stock_minimo">🔴 Stock Mínimo *</label>
                                    <input type="number"
                                           id="stock_minimo"
                                           name="stock_minimo"
                                           value="<?php echo $producto['stock_minimo']; ?>"
                                           required
                                           min="0"
                                           placeholder="5">
                                </div>

                                <div class="form-group">
                                    <label for="stock_maximo">🟢 Stock Máximo *</label>
                                    <input type="number"
                                           id="stock_maximo"
                                           name="stock_maximo"
                                           value="<?php echo $producto['stock_maximo']; ?>"
                                           required
                                           min="1"
                                           placeholder="100">
                                </div>
                            </div>
                        </div>

                        <!-- Ubicación y Estado -->
                        <div class="form-group-section">
                            <h3 class="section-title">🏪 Ubicación y Estado</h3>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="almacen_id">🏪 Almacén *</label>
                                    <select id="almacen_id" name="almacen_id" required>
                                        <option value="">Seleccionar almacén...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo $almacen['id']; ?>" 
                                                    <?php echo ($producto['almacen_id'] == $almacen['id']) ? 'selected' : ''; ?>>
                                                <?php echo AlmacenesConfig::getIconoAlmacen($almacen) . ' ' . htmlspecialchars($almacen['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="activo">⚙️ Estado del Producto</label>
                                    <select id="activo" name="activo">
                                        <option value="1" <?php echo $producto['activo'] == '1' ? 'selected' : ''; ?>>✅ Activo</option>
                                        <option value="0" <?php echo $producto['activo'] == '0' ? 'selected' : ''; ?>>❌ Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Imagen -->
                        <div class="form-group-section">
                            <h3 class="section-title">📷 Imagen del Producto</h3>

                            <div class="form-group">
                                <label for="imagen">📸 Imagen del Producto</label>

                                <?php if (!empty($producto['imagen']) && file_exists('uploads/productos/' . $producto['imagen'])): ?>
                                    <div class="current-image" id="currentImageContainer">
                                        <p>Imagen actual:</p>
                                        <img src="uploads/productos/<?php echo htmlspecialchars($producto['imagen']); ?>"
                                             alt="Imagen actual"
                                             class="current-image-preview">
                                        <br>
                                        <button type="button" onclick="eliminarImagenActual()" class="btn-delete-image">
                                            🗑️ Eliminar imagen actual
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <input type="file"
                                       id="imagen"
                                       name="imagen"
                                       accept="image/*"
                                       class="file-input">
                                <small class="file-help">JPG, PNG o WebP (máx. 5MB)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            ✅ Actualizar Producto
                        </button>

                        <button type="button" onclick="resetearFormulario()" class="btn-reset">
                            🔄 Resetear Cambios
                        </button>

                        <?php if (auth_can('inventario', 'eliminar')): ?>
                            <button type="button" onclick="confirmarEliminacion()" class="btn-delete">
                                🗑️ Eliminar Producto
                            </button>
                        <?php endif; ?>

                        <a href="productos.php" class="btn-cancel">
                            ← Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmación -->
    <div id="modalConfirmacion" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitulo" class="modal-title"></h3>
                <button onclick="cerrarModalConfirmacion()" class="modal-close">×</button>
            </div>
            <div class="modal-body">
                <p id="modalMensaje" class="modal-message"></p>
                <div class="modal-actions">
                    <button id="btnConfirmar" class="btn-modal-confirm">Confirmar</button>
                    <button onclick="cerrarModalConfirmacion()" class="btn-modal-cancel">Cancelar</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Valores originales del formulario
        const valoresOriginales = {
            nombre: <?php echo json_encode($producto['nombre']); ?>,
            sku: <?php echo json_encode($producto['sku'] ?? ''); ?>,
            descripcion: <?php echo json_encode($producto['descripcion'] ?? ''); ?>,
            categoria: <?php echo json_encode($producto['categoria']); ?>,
            precio: <?php echo $producto['precio']; ?>,
            stock_actual: <?php echo $producto['stock_actual']; ?>,
            stock_minimo: <?php echo $producto['stock_minimo']; ?>,
            stock_maximo: <?php echo $producto['stock_maximo']; ?>,
            almacen_id: <?php echo json_encode($producto['almacen_id'] ?? ''); ?>,
            activo: <?php echo $producto['activo']; ?>
        };

        // Eliminar imagen actual
        function eliminarImagenActual() {
            if (confirm('¿Estás seguro de que quieres eliminar la imagen actual?')) {
                const currentImage = document.getElementById('currentImageContainer');
                if (currentImage) {
                    currentImage.style.display = 'none';
                }

                // Agregar campo hidden para indicar que se debe eliminar la imagen
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'eliminar_imagen';
                input.value = '1';
                document.getElementById('formEditarProducto').appendChild(input);

                alert('Imagen marcada para eliminación. Guarda los cambios para confirmar.');
            }
        }

        // Resetear formulario a valores originales
        function resetearFormulario() {
            if (confirm('¿Estás seguro de que quieres descartar todos los cambios?')) {
                Object.keys(valoresOriginales).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        element.value = valoresOriginales[key];
                    }
                });

                // Mostrar imagen actual si existe
                const currentImage = document.getElementById('currentImageContainer');
                if (currentImage) {
                    currentImage.style.display = 'block';
                }

                // Remover campo de eliminar imagen si existe
                const eliminarInput = document.querySelector('input[name="eliminar_imagen"]');
                if (eliminarInput) {
                    eliminarInput.remove();
                }
            }
        }

        // Confirmar eliminación del producto
        function confirmarEliminacion() {
            document.getElementById('modalTitulo').textContent = '🗑️ Eliminar Producto';
            document.getElementById('modalMensaje').textContent =
                '¿Estás seguro de que quieres eliminar este producto? Esta acción no se puede deshacer.';

            document.getElementById('btnConfirmar').onclick = function() {
                window.location.href = 'eliminar_producto.php?id=<?php echo $producto['id']; ?>&accion=eliminar';
            };

            document.getElementById('modalConfirmacion').style.display = 'flex';
        }

        // Cerrar modal de confirmación
        function cerrarModalConfirmacion() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }

        // Mostrar notificación simple
        function mostrarNotificacion(mensaje, tipo) {
            alert(mensaje);
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formEditarProducto');

            form.addEventListener('submit', function(e) {
                // Validación básica
                const stockActual = parseInt(document.getElementById('stock_actual').value) || 0;
                const stockMinimo = parseInt(document.getElementById('stock_minimo').value) || 0;
                const stockMaximo = parseInt(document.getElementById('stock_maximo').value) || 0;

                if (stockMaximo <= stockMinimo) {
                    e.preventDefault();
                    alert('El stock máximo debe ser mayor que el mínimo');
                    return false;
                }

                if (stockActual > stockMaximo) {
                    e.preventDefault();
                    alert('El stock actual no puede ser mayor que el máximo');
                    return false;
                }

                // Mostrar loader
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '⏳ Actualizando...';
                }
            });
        });
    </script>
</body>
</html>
