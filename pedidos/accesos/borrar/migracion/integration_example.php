<?php
/**
 * Ejemplo de Integración del Sistema de Accesos
 * Sequoia Speed - Sistema de Accesos
 * 
 * Este archivo muestra cómo integrar el sistema de accesos
 * con los módulos existentes (ventas, inventario, etc.)
 */

// Incluir el helper de autenticación
require_once __DIR__ . '/auth_helper.php';

// ==============================================
// EJEMPLO 1: Proteger una página completa
// ==============================================

// Requerir autenticación y permisos específicos
$current_user = auth_require('ventas', 'leer');

// Registrar acceso a la página
auth_log('read', 'ventas', 'Acceso a lista de pedidos');

// ==============================================
// EJEMPLO 2: Verificar permisos condicionalmente
// ==============================================

// Verificar si el usuario puede crear pedidos
if (auth_can('ventas', 'crear')) {
    // Mostrar botón de crear pedido
    echo '<a href="crear_pedido.php" class="btn btn-primary">➕ Nuevo Pedido</a>';
}

// Verificar si el usuario puede ver reportes
if (auth_can('reportes', 'leer')) {
    // Mostrar enlace a reportes
    echo '<a href="reportes.php" class="btn btn-info">📊 Ver Reportes</a>';
}

// ==============================================
// EJEMPLO 3: Generar menú de navegación
// ==============================================

// Generar menú basado en permisos del usuario
$navigation_menu = auth_nav_menu('ventas'); // 'ventas' es el módulo actual
echo $navigation_menu;

// ==============================================
// EJEMPLO 4: Mostrar información del usuario
// ==============================================

// Obtener información del usuario actual
$user_info = auth_user_info();
echo $user_info;

// ==============================================
// EJEMPLO 5: Generar botones de acción
// ==============================================

// Para cada pedido en una lista, generar botones según permisos
$pedido_id = 123;
$action_buttons = auth_action_buttons('ventas', $pedido_id, [
    [
        'icon' => '📄',
        'title' => 'Imprimir',
        'class' => 'btn-print',
        'action' => "imprimirPedido({$pedido_id})"
    ]
]);
echo $action_buttons;

// ==============================================
// EJEMPLO 6: Procesar formularios con CSRF
// ==============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verificar token CSRF
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        die('Token CSRF inválido');
    }
    
    // Verificar permisos para la acción
    if (!auth_can('ventas', 'crear')) {
        die('No tienes permisos para crear pedidos');
    }
    
    // Procesar el formulario...
    // ... código para crear pedido ...
    
    // Registrar la acción en auditoría
    auth_log('create', 'ventas', "Pedido creado: #$pedido_id");
}

// Generar token CSRF para el formulario
$csrf_token = auth_csrf();

// ==============================================
// EJEMPLO 7: Verificar roles específicos
// ==============================================

// Verificar si el usuario es administrador
if (auth_is_admin()) {
    // Mostrar opciones de administrador
    echo '<div class="admin-panel">Panel de Administrador</div>';
}

// ==============================================
// EJEMPLO 8: Obtener información del usuario
// ==============================================

// Obtener datos del usuario actual
$current_user = auth_user();
if ($current_user) {
    echo "Bienvenido, " . htmlspecialchars($current_user['nombre']);
}

// ==============================================
// EJEMPLO 9: Integración en listar_pedidos.php
// ==============================================

/*
// Al inicio del archivo listar_pedidos.php
require_once '/pedidos/accesos/auth_helper.php';

// Proteger la página
$current_user = auth_require('ventas', 'leer');

// En el HTML, mostrar botones condicionalmente
<?php if (auth_can('ventas', 'crear')): ?>
    <button onclick="crearPedido()" class="btn btn-primary">➕ Nuevo Pedido</button>
<?php endif; ?>

<?php if (auth_can('ventas', 'actualizar')): ?>
    <button onclick="editarPedido(id)" class="btn btn-warning">✏️ Editar</button>
<?php endif; ?>

<?php if (auth_can('ventas', 'eliminar')): ?>
    <button onclick="eliminarPedido(id)" class="btn btn-danger">🗑️ Eliminar</button>
<?php endif; ?>

// Al procesar acciones
if ($_POST['action'] === 'update_pedido') {
    // Verificar permisos
    if (!auth_can('ventas', 'actualizar')) {
        die('No tienes permisos para actualizar pedidos');
    }
    
    // Procesar actualización...
    
    // Registrar en auditoría
    auth_log('update', 'ventas', "Pedido actualizado: #$pedido_id");
}
*/

// ==============================================
// EJEMPLO 10: Integración en inventario/productos.php
// ==============================================

/*
// Al inicio del archivo inventario/productos.php
require_once '../accesos/auth_helper.php';

// Proteger la página
$current_user = auth_require('inventario', 'leer');

// Registrar acceso
auth_log('read', 'inventario', 'Acceso a lista de productos');

// En el HTML
<?php if (auth_can('inventario', 'crear')): ?>
    <a href="producto_crear.php" class="btn btn-primary">➕ Nuevo Producto</a>
<?php endif; ?>

// En la tabla de productos
foreach ($productos as $producto) {
    echo '<tr>';
    echo '<td>' . htmlspecialchars($producto['nombre']) . '</td>';
    echo '<td>';
    
    // Botones basados en permisos
    if (auth_can('inventario', 'leer')) {
        echo '<button onclick="verProducto(' . $producto['id'] . ')">👁️</button>';
    }
    
    if (auth_can('inventario', 'actualizar')) {
        echo '<button onclick="editarProducto(' . $producto['id'] . ')">✏️</button>';
    }
    
    if (auth_can('inventario', 'eliminar')) {
        echo '<button onclick="eliminarProducto(' . $producto['id'] . ')">🗑️</button>';
    }
    
    echo '</td>';
    echo '</tr>';
}
*/

// ==============================================
// EJEMPLO 11: Middleware para AJAX
// ==============================================

/*
// En archivos AJAX, verificar permisos
if (!auth_can('ventas', 'actualizar')) {
    echo json_encode(['error' => 'No tienes permisos para esta acción']);
    exit;
}

// Procesar la acción AJAX...

// Registrar en auditoría
auth_log('update', 'ventas', 'Actualización vía AJAX');
*/

// ==============================================
// EJEMPLO 12: Personalizar según el usuario
// ==============================================

// Obtener información del usuario
$user = auth_user();
$user_roles = AuthHelper::getCurrentUserRoles();
$user_permissions = AuthHelper::getCurrentUserPermissions();

// Personalizar la interfaz según el rol
foreach ($user_roles as $role) {
    switch ($role['nombre']) {
        case 'super_admin':
            // Mostrar todas las opciones
            break;
        case 'vendedor':
            // Mostrar solo opciones de ventas
            break;
        case 'consultor':
            // Mostrar solo opciones de consulta
            break;
    }
}

// ==============================================
// EJEMPLO 13: Verificar timeout de sesión
// ==============================================

// Verificar si la sesión ha expirado (útil en páginas con mucha interacción)
if (!AuthHelper::checkSessionTimeout(30)) { // 30 minutos
    header('Location: /pedidos/accesos/login.php?timeout=1');
    exit;
}

// ==============================================
// EJEMPLO 14: Integración con JavaScript
// ==============================================

// Pasar información de permisos a JavaScript
echo '<script>';
echo 'window.userPermissions = ' . json_encode(AuthHelper::getCurrentUserPermissions()) . ';';
echo 'window.userRoles = ' . json_encode(AuthHelper::getCurrentUserRoles()) . ';';
echo '</script>';

/*
// En JavaScript
function canUserCreate() {
    return window.userPermissions.some(p => p.modulo === 'ventas' && p.tipo_permiso === 'crear');
}

if (canUserCreate()) {
    // Mostrar botón crear
}
*/

// ==============================================
// EJEMPLO 15: Logout
// ==============================================

// En un botón de logout
if (isset($_GET['logout'])) {
    auth_logout();
    header('Location: /pedidos/accesos/login.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ejemplo de Integración - Sistema de Accesos</title>
    <link rel="stylesheet" href="../inventario/productos.css">
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="header-content">
                <h1>Ejemplo de Integración</h1>
                <div class="user-info">
                    <?php echo auth_user_info(); ?>
                </div>
            </div>
        </header>

        <nav class="navigation">
            <?php echo auth_nav_menu('ejemplo'); ?>
        </nav>

        <main class="main-content">
            <h2>Botones basados en permisos</h2>
            
            <div class="action-section">
                <?php if (auth_can('ventas', 'crear')): ?>
                    <button class="btn btn-primary">➕ Crear Pedido</button>
                <?php endif; ?>
                
                <?php if (auth_can('inventario', 'leer')): ?>
                    <button class="btn btn-info">📦 Ver Inventario</button>
                <?php endif; ?>
                
                <?php if (auth_can('reportes', 'leer')): ?>
                    <button class="btn btn-secondary">📊 Ver Reportes</button>
                <?php endif; ?>
                
                <?php if (auth_is_admin()): ?>
                    <button class="btn btn-warning">⚙️ Administración</button>
                <?php endif; ?>
            </div>

            <h2>Información del Usuario</h2>
            <div class="user-details">
                <p><strong>Nombre:</strong> <?php echo htmlspecialchars($current_user['nombre']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($current_user['email']); ?></p>
                <p><strong>Roles:</strong> 
                    <?php foreach (AuthHelper::getCurrentUserRoles() as $role): ?>
                        <span class="badge"><?php echo htmlspecialchars($role['nombre']); ?></span>
                    <?php endforeach; ?>
                </p>
            </div>

            <h2>Formulario con CSRF</h2>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo auth_csrf(); ?>">
                <button type="submit" name="action" value="test">Enviar</button>
            </form>
        </main>
    </div>

    <script>
        // Información del usuario disponible en JavaScript
        window.currentUser = <?php echo json_encode($current_user); ?>;
        window.userPermissions = <?php echo json_encode(AuthHelper::getCurrentUserPermissions()); ?>;
        window.userRoles = <?php echo json_encode(AuthHelper::getCurrentUserRoles()); ?>;
        
        // Función para verificar permisos en JavaScript
        function canUser(module, permission) {
            return window.userPermissions.some(p => 
                p.modulo === module && p.tipo_permiso === permission
            );
        }
        
        // Función para verificar roles en JavaScript
        function hasRole(roleName) {
            return window.userRoles.some(r => r.nombre === roleName);
        }
        
        // Ejemplo de uso
        console.log('¿Puede crear ventas?', canUser('ventas', 'crear'));
        console.log('¿Es administrador?', hasRole('admin') || hasRole('super_admin'));
    </script>
</body>
</html>