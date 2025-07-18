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

// Obtener datos del producto
try {
    $query = "SELECT * FROM productos WHERE id = ? LIMIT 1";
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
    $categorias_query = "SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != '' ORDER BY categoria";
    $categorias_result = $conn->query($categorias_query);
    if ($categorias_result) {
        $categorias = $categorias_result->fetch_all(MYSQLI_ASSOC);
    }

    // Almacenes
    $almacenes_query = "SELECT DISTINCT almacen FROM productos WHERE almacen IS NOT NULL AND almacen != '' ORDER BY almacen";
    $almacenes_result = $conn->query($almacenes_query);
    if ($almacenes_result) {
        $almacenes = $almacenes_result->fetch_all(MYSQLI_ASSOC);
    }

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

    <style>
        /* Reset y estilos base */
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        /* Estilos específicos para el formulario de edición */
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--space-lg);
            background: var(--bg-primary);
            min-height: 100vh;
        }

        .form-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-xl);
            margin-top: var(--space-lg);
        }

        .form-container {
            max-width: 100%;
        }

        .form-grid {
            display: grid;
            gap: var(--space-xl);
        }

        .form-group-section {
            background: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
        }

        .section-title {
            color: var(--text-primary);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 var(--space-lg) 0;
            padding-bottom: var(--space-sm);
            border-bottom: 2px solid var(--color-primary);
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .form-group label {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: var(--space-xs);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            color: var(--text-primary);
            padding: var(--space-sm) var(--space-md);
            font-size: 0.9rem;
            transition: var(--transition-fast);
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 2px rgba(88, 166, 255, 0.2);
        }

        .form-group input:required {
            border-left: 3px solid var(--color-primary);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-group select {
            cursor: pointer;
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23ffffff" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
        }

        .form-group select option {
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: var(--space-sm);
        }

        .form-group input[type="file"] {
            background: var(--bg-tertiary);
            padding: var(--space-sm);
        }

        .form-actions {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-xl);
            padding-top: var(--space-lg);
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .form-actions button,
        .form-actions a {
            padding: var(--space-md) var(--space-lg);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
        }

        .btn-submit {
            background: var(--color-success);
            color: white;
        }

        .btn-submit:hover {
            background: #2ea043;
        }

        .btn-submit:disabled {
            background: var(--text-muted);
            cursor: not-allowed;
        }

        .btn-reset {
            background: var(--color-warning);
            color: white;
        }

        .btn-reset:hover {
            background: #f5b041;
        }

        .btn-delete {
            background: var(--color-danger);
            color: white;
        }

        .btn-delete:hover {
            background: #e74c3c;
        }

        .btn-cancel {
            background: var(--text-secondary);
            color: white;
        }

        .btn-cancel:hover {
            background: var(--text-muted);
        }

        /* Estilos para imagen */
        .current-image {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-md);
            margin-bottom: var(--space-md);
        }

        .current-image p {
            margin: 0 0 var(--space-sm) 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .current-image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            margin: var(--space-sm) 0;
            display: block;
        }

        .btn-delete-image {
            background: var(--color-danger);
            color: white;
            border: none;
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition-fast);
        }

        .btn-delete-image:hover {
            background: #e74c3c;
        }

        .file-input {
            margin-top: var(--space-sm) !important;
        }

        .file-help {
            color: var(--text-secondary);
            display: block;
            margin-top: var(--space-xs);
            font-size: 0.8rem;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            max-width: 400px;
            margin: var(--space-lg);
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
        }

        .modal-title {
            color: var(--text-primary);
            margin: 0;
            font-size: 1.1rem;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-close:hover {
            color: var(--text-primary);
        }

        .modal-message {
            color: var(--text-primary);
            margin-bottom: var(--space-md);
            line-height: 1.5;
        }

        .modal-actions {
            display: flex;
            gap: var(--space-sm);
        }

        .btn-modal-confirm,
        .btn-modal-cancel {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition-fast);
        }

        .btn-modal-confirm {
            background: var(--color-danger);
            color: white;
        }

        .btn-modal-confirm:hover {
            background: #e74c3c;
        }

        .btn-modal-cancel {
            background: var(--text-secondary);
            color: white;
        }

        .btn-modal-cancel:hover {
            background: var(--text-muted);
        }

        /* Botones del header */
        .btn {
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition-fast);
            display: inline-flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9rem;
        }

        .btn-secondary {
            background: var(--text-secondary);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--text-muted);
        }

        .btn-info {
            background: var(--color-info);
            color: white;
        }

        .btn-info:hover {
            background: #2aa8b1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .container {
                padding: var(--space-md);
            }

            .header-content {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .form-actions button,
            .form-actions a {
                text-align: center;
                justify-content: center;
            }

            .modal-content {
                margin: var(--space-sm);
                max-width: calc(100vw - 32px);
            }

            .modal-actions {
                flex-direction: column;
            }

            .producto-info-summary {
                font-size: 0.8rem;
                line-height: 1.6;
                word-break: break-word;
            }
        }

        /* Mensajes */
        .mensaje {
            margin: var(--space-lg) 0;
            padding: var(--space-md);
            border-radius: var(--border-radius);
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
        }

        .mensaje-exito {
            background: rgba(35, 134, 54, 0.1);
            border: 1px solid var(--color-success);
            color: var(--color-success);
        }

        .mensaje-error {
            background: rgba(218, 54, 51, 0.1);
            border: 1px solid var(--color-danger);
            color: var(--color-danger);
        }

        .mensaje-contenido {
            flex: 1;
            display: flex;
            align-items: flex-start;
            gap: var(--space-sm);
        }

        .mensaje-icono {
            font-size: 1.2rem;
        }

        .mensaje-cerrar {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            font-size: 1.2rem;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .mensaje-cerrar:hover {
            opacity: 0.7;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: var(--space-xs);
        }

        .breadcrumb a {
            color: var(--color-primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        /* Header del formulario */
        .header {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .header-left {
            flex: 1;
        }

        .page-title {
            color: var(--text-primary);
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            flex-wrap: wrap;
        }

        .user-info {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        /* Información resumida del producto */
        .producto-info-summary {
            margin-top: var(--space-sm);
            font-size: 0.85rem;
            color: var(--text-secondary);
            line-height: 1.4;
        }


        .estado-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .estado-badge.activo {
            background: rgba(35, 134, 54, 0.2);
            color: var(--color-success);
            border: 1px solid var(--color-success);
        }

        .estado-badge.inactivo {
            background: rgba(139, 148, 158, 0.2);
            color: var(--text-secondary);
            border: 1px solid var(--text-secondary);
        }

        /* Variables CSS específicas para este componente */
        .precio-preview,
        .stock-value,
        .estado-badge,
        .producto-info-card,
        .current-image {
            /* Estos estilos se mantienen del archivo original */
        }
        /* Estilos adicionales específicos para editar producto */
        .precio-preview {
            font-size: 0.9rem;
            color: var(--apple-blue);
            margin-top: 4px;
            font-weight: 600;
        }

        .stock-value {
            font-weight: 600;
        }

        .stock-value.stock-low {
            color: var(--apple-red);
        }

        .estado-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .estado-badge.activo {
            background: var(--apple-teal);
            color: white;
        }

        .estado-badge.inactivo {
            background: var(--apple-gray);
            color: white;
        }

        .producto-info-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            backdrop-filter: blur(20px);
        }

        .producto-info-content {
            display: flex;
            gap: var(--space-lg);
            align-items: flex-start;
        }

        .producto-info-imagen {
            flex-shrink: 0;
            width: 120px;
        }

        .producto-imagen-grande {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: var(--radius-md);
            border: 2px solid var(--glass-border);
        }

        .imagen-placeholder-grande {
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--glass-bg);
            border: 2px dashed var(--glass-border);
            border-radius: var(--radius-md);
            font-size: 2rem;
            color: var(--vscode-text-muted);
        }

        .producto-info-datos h2 {
            color: var(--vscode-text-light);
            margin-bottom: var(--space-sm);
            font-size: 1.5rem;
        }

        .producto-descripcion {
            color: var(--vscode-text-muted);
            margin-bottom: var(--space-md);
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .producto-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            font-size: 0.9rem;
        }

        .meta-item strong {
            color: var(--vscode-text-light);
        }

        .current-image {
            margin-bottom: var(--space-md);
            padding: var(--space-md);
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
        }

        .current-image-label {
            font-size: 0.9rem;
            color: var(--vscode-text-muted);
            margin-bottom: var(--space-sm);
        }

        .current-image-preview {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-sm);
            display: block;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--vscode-text-muted);
            font-size: 0.9rem;
            margin-right: var(--space-md);
        }

        @media (max-width: 768px) {
            .producto-info-content {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .producto-meta {
                justify-content: center;
            }

            .header-actions {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .user-info {
                margin-right: 0;
            }
        }
    </style>
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
                                    <label for="categoria">🏷️ Categoría *</label>
                                    <select id="categoria" name="categoria" required>
                                        <option value="">Seleccionar categoría...</option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['categoria']); ?>" 
                                                    <?php echo ($producto['categoria'] === $cat['categoria']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($cat['categoria']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
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
                                    <label for="almacen">🏪 Almacén *</label>
                                    <select id="almacen" name="almacen" required>
                                        <option value="">Seleccionar almacén...</option>
                                        <?php foreach ($almacenes as $alm): ?>
                                            <option value="<?php echo htmlspecialchars($alm['almacen']); ?>" 
                                                    <?php echo ($producto['almacen'] === $alm['almacen']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($alm['almacen']); ?>
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
            almacen: <?php echo json_encode($producto['almacen']); ?>,
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
