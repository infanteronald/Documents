<?php
/**
 * Formulario para Crear Nuevo Almacén
 * Sistema de Inventario - Sequoia Speed
 */

// Iniciar sesión
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Requerir autenticación
require_once '../../accesos/auth_helper.php';
$current_user = auth_require('inventario', 'crear');

// Definir constante
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

// Función para generar token CSRF
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Obtener datos del formulario si hay errores
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Mensajes de error
$errores = $_SESSION['errores'] ?? [];
unset($_SESSION['errores']);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>➕ Crear Almacén - Sequoia Speed</title>
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
                    <h1 class="page-title">➕ Crear Nuevo Almacén</h1>
                    <div class="breadcrumb">
                        <a href="../../index.php">🏠 Inicio</a>
                        <span>/</span>
                        <a href="../productos.php">📦 Inventario</a>
                        <span>/</span>
                        <a href="index.php">🏪 Almacenes</a>
                        <span>/</span>
                        <span>➕ Crear</span>
                    </div>
                </div>
                <div class="header-actions">
                    <span class="user-info">
                        👤 <?php echo htmlspecialchars($current_user['nombre']); ?>
                    </span>
                    <a href="index.php" class="btn btn-secondary">
                        ← Volver al Listado
                    </a>
                </div>
            </div>
        </header>

        <!-- Mensajes de error -->
        <?php if (!empty($errores)): ?>
            <div class="mensaje mensaje-error">
                <div class="mensaje-contenido">
                    <span class="mensaje-icono">❌</span>
                    <div>
                        <strong>Errores en el formulario:</strong>
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
                <form action="procesar.php" method="POST" id="formCrearAlmacen">
                    <input type="hidden" name="accion" value="crear">
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
                                       value="<?php echo htmlspecialchars($form_data['nombre'] ?? ''); ?>"
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
                                          placeholder="Descripción del almacén, sus características y propósito..."><?php echo htmlspecialchars($form_data['descripcion'] ?? ''); ?></textarea>
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
                                       value="<?php echo htmlspecialchars($form_data['ubicacion'] ?? ''); ?>"
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
                                       value="<?php echo htmlspecialchars($form_data['capacidad_maxima'] ?? ''); ?>"
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
                                    <option value="1" <?php echo (!isset($form_data['activo']) || $form_data['activo'] == '1') ? 'selected' : ''; ?>>
                                        ✅ Activo
                                    </option>
                                    <option value="0" <?php echo (isset($form_data['activo']) && $form_data['activo'] == '0') ? 'selected' : ''; ?>>
                                        ❌ Inactivo
                                    </option>
                                </select>
                                <small class="form-help">Estado inicial del almacén</small>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="form-actions">
                        <button type="submit" class="btn-submit">
                            ✅ Crear Almacén
                        </button>

                        <button type="button" onclick="limpiarFormulario()" class="btn-reset">
                            🔄 Limpiar
                        </button>

                        <a href="index.php" class="btn-cancel">
                            ← Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Función para limpiar formulario
        function limpiarFormulario() {
            if (confirm('¿Estás seguro de que quieres limpiar el formulario?')) {
                document.getElementById('formCrearAlmacen').reset();
                document.getElementById('nombre').focus();
            }
        }

        // Validación del formulario
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('formCrearAlmacen');
            
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
                    submitBtn.innerHTML = '⏳ Creando...';
                }
            });
            
            // Enfocar el primer campo
            document.getElementById('nombre').focus();
        });
    </script>
</body>
</html>