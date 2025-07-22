<?php
/**
 * Formulario para Editar Almacén Existente
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../../accesos/auth_helper.php';
$current_user = auth_require('inventario', 'actualizar');

// Definir constante y conexión
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once '../../config_secure.php';

// Función para generar token CSRF
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Obtener ID del almacén
$almacen_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($almacen_id <= 0) {
    $_SESSION['mensaje_error'] = 'ID de almacén inválido';
    header('Location: index.php');
    exit;
}

// Obtener datos del almacén
try {
    $query = "SELECT * FROM almacenes WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception('Error preparando consulta: ' . $conn->error);
    }

    $stmt->bind_param('i', $almacen_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $almacen = $result->fetch_assoc();

    if (!$almacen) {
        $_SESSION['mensaje_error'] = 'Almacén no encontrado';
        header('Location: index.php');
        exit;
    }

} catch (Exception $e) {
    error_log('Error obteniendo almacén: ' . $e->getMessage());
    $_SESSION['mensaje_error'] = 'Error al obtener el almacén';
    header('Location: index.php');
    exit;
}

// Obtener estadísticas del almacén
try {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT p.id) as total_productos,
            SUM(ia.stock_actual) as stock_total,
            SUM(CASE WHEN ia.stock_actual <= ia.stock_minimo THEN 1 ELSE 0 END) as productos_criticos,
            MAX(ia.fecha_actualizacion) as ultima_actualizacion
        FROM productos p
        INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
        WHERE ia.almacen_id = ? AND p.activo = 1
    ";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param('i', $almacen_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
} catch (Exception $e) {
    $stats = [
        'total_productos' => 0,
        'stock_total' => 0,
        'productos_criticos' => 0,
        'ultima_actualizacion' => null
    ];
}

// Si hay datos en la sesión (errores de validación), mantenerlos
if (isset($_SESSION['form_data'])) {
    $almacen = array_merge($almacen, $_SESSION['form_data']);
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
    <title>✏️ <?php echo htmlspecialchars($almacen['nombre']); ?> - Sequoia Speed</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏪</text></svg>">
    <link rel="stylesheet" href="../productos.css">
    <link rel="stylesheet" href="almacenes.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <div class="header-content">
                <div class="header-left">
                    <h1 class="page-title">✏️ <?php echo htmlspecialchars($almacen['nombre']); ?></h1>
                    <div class="breadcrumb">
                        <a href="../../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="index.php">🏪 Almacenes</a>
                        <span>/</span>
                        <span>✏️ Editar</span>
                    </div>
                    
                    <!-- Información resumida del almacén -->
                    <div class="almacen-info-summary">
                        ID: #<?php echo $almacen['id']; ?> • 
                        Productos: <?php echo number_format($stats['total_productos']); ?> • 
                        Stock Total: <?php echo number_format($stats['stock_total']); ?> • 
                        Estado: <span class="estado-badge <?php echo $almacen['activo'] ? 'activo' : 'inactivo'; ?>"><?php echo $almacen['activo'] ? '✅ Activo' : '❌ Inactivo'; ?></span> • 
                        Creado: <?php echo date('d/m/Y H:i', strtotime($almacen['fecha_creacion'])); ?><?php if (!empty($almacen['fecha_actualizacion'])): ?> • Actualizado: <?php echo date('d/m/Y H:i', strtotime($almacen['fecha_actualizacion'])); ?><?php endif; ?>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver al Listado
                    </a>
                    <a href="detalle.php?id=<?php echo $almacen['id']; ?>" class="btn btn-info">
                        👁️ Ver Detalle
                    </a>
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
                        <strong>Error al actualizar el almacén:</strong>
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
                <form action="procesar.php" method="POST" id="formEditarAlmacen">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" value="<?php echo $almacen['id']; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                    <div class="form-grid">
                        <!-- Información básica -->
                        <div class="form-group-section">
                            <h3 class="section-title">📋 Información Básica</h3>

                            <div class="form-group">
                                <label for="nombre">🏪 Nombre del Almacén *</label>
                                <input type="text"
                                       id="nombre"
                                       name="nombre"
                                       value="<?php echo htmlspecialchars($almacen['nombre']); ?>"
                                       required
                                       maxlength="100"
                                       placeholder="Ej: Almacén Central">
                                <small class="form-help">Nombre único que identifica al almacén</small>
                            </div>

                            <div class="form-group">
                                <label for="descripcion">📝 Descripción</label>
                                <textarea id="descripcion"
                                          name="descripcion"
                                          rows="3"
                                          maxlength="500"
                                          placeholder="Descripción del almacén, sus características y propósito..."><?php echo htmlspecialchars($almacen['descripcion'] ?? ''); ?></textarea>
                                <small class="form-help">Descripción detallada del almacén (opcional)</small>
                            </div>
                        </div>

                        <!-- Información de ubicación -->
                        <div class="form-group-section">
                            <h3 class="section-title">📍 Información de Ubicación</h3>

                            <div class="form-group">
                                <label for="ubicacion">📍 Ubicación *</label>
                                <input type="text"
                                       id="ubicacion"
                                       name="ubicacion"
                                       value="<?php echo htmlspecialchars($almacen['ubicacion']); ?>"
                                       required
                                       maxlength="255"
                                       placeholder="Ej: Calle 123 #45-67, Bogotá, Colombia">
                                <small class="form-help">Dirección física del almacén</small>
                            </div>

                            <div class="form-group">
                                <label for="capacidad_maxima">📦 Capacidad Máxima</label>
                                <input type="number"
                                       id="capacidad_maxima"
                                       name="capacidad_maxima"
                                       value="<?php echo $almacen['capacidad_maxima']; ?>"
                                       min="0"
                                       max="999999"
                                       placeholder="1000">
                                <small class="form-help">Capacidad máxima en metros cuadrados (opcional)</small>
                            </div>
                        </div>

                        <!-- Configuración -->
                        <div class="form-group-section">
                            <h3 class="section-title">⚙️ Configuración</h3>

                            <div class="form-group">
                                <label for="activo">🔧 Estado del Almacén</label>
                                <select id="activo" name="activo">
                                    <option value="1" <?php echo $almacen['activo'] == '1' ? 'selected' : ''; ?>>
                                        ✅ Activo
                                    </option>
                                    <option value="0" <?php echo $almacen['activo'] == '0' ? 'selected' : ''; ?>>
                                        ❌ Inactivo
                                    </option>
                                </select>
                                <small class="form-help">
                                    <?php if ($stats['total_productos'] > 0): ?>
                                        ⚠️ Este almacén tiene <?php echo $stats['total_productos']; ?> productos asociados
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            ✅ Actualizar Almacén
                        </button>

                        <button type="button" onclick="resetearFormulario()" class="btn-reset">
                            🔄 Resetear Cambios
                        </button>

                        <?php if (auth_can('inventario', 'eliminar') && $stats['total_productos'] == 0): ?>
                            <button type="button" onclick="confirmarEliminacion()" class="btn-delete">
                                🗑️ Eliminar Almacén
                            </button>
                        <?php endif; ?>

                        <a href="index.php" class="btn-cancel">
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
            nombre: <?php echo json_encode($almacen['nombre']); ?>,
            descripcion: <?php echo json_encode($almacen['descripcion'] ?? ''); ?>,
            ubicacion: <?php echo json_encode($almacen['ubicacion']); ?>,
            capacidad_maxima: <?php echo $almacen['capacidad_maxima']; ?>,
            activo: <?php echo $almacen['activo']; ?>
        };

        // Resetear formulario a valores originales
        function resetearFormulario() {
            if (confirm('¿Estás seguro de que quieres descartar todos los cambios?')) {
                Object.keys(valoresOriginales).forEach(key => {
                    const element = document.getElementById(key);
                    if (element) {
                        element.value = valoresOriginales[key];
                    }
                });
            }
        }

        // Confirmar eliminación del almacén
        function confirmarEliminacion() {
            document.getElementById('modalTitulo').textContent = '🗑️ Eliminar Almacén';
            document.getElementById('modalMensaje').textContent =
                '¿Estás seguro de que quieres eliminar este almacén? Esta acción no se puede deshacer.';

            document.getElementById('btnConfirmar').onclick = function() {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'procesar.php';
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = <?php echo $almacen['id']; ?>;
                
                form.appendChild(accionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            };

            document.getElementById('modalConfirmacion').style.display = 'flex';
        }

        // Cerrar modal de confirmación
        function cerrarModalConfirmacion() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formEditarAlmacen');

            form.addEventListener('submit', function(e) {
                const nombre = document.getElementById('nombre').value.trim();
                const ubicacion = document.getElementById('ubicacion').value.trim();
                
                if (!nombre) {
                    e.preventDefault();
                    alert('El nombre del almacén es requerido');
                    document.getElementById('nombre').focus();
                    return false;
                }
                
                if (!ubicacion) {
                    e.preventDefault();
                    alert('La ubicación del almacén es requerida');
                    document.getElementById('ubicacion').focus();
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