<?php
/**
 * Archivo para incluir en todas las páginas del admin
 * NO incluir en ver_detalle_pedido_cliente.php
 */

// Verificar si NO estamos en página de cliente
$current_page = basename($_SERVER['PHP_SELF']);
if ($current_page === 'ver_detalle_pedido_cliente.php') {
    return; // No incluir notificaciones en página de cliente
}
?>

<!-- Sistema de Notificaciones -->
<link rel="stylesheet" href="/pedidos/notifications/notifications.css">

<!-- Contenedor de notificaciones (se crea automáticamente) -->
<div class="notifications-container"></div>

<!-- Script del sistema de notificaciones -->
<script src="/pedidos/notifications/notifications.js"></script>

<script>
// Configuración adicional si es necesaria
document.addEventListener('DOMContentLoaded', function() {
    // El sistema se inicializa automáticamente
    console.log('Sistema de notificaciones cargado');
});

// Función helper para mostrar notificaciones desde PHP
window.showPHPNotification = function(type, title, message, actions) {
    if (window.showNotification) {
        showNotification(type, title, message, { actions: actions || [] });
    }
};
</script>

<?php
// Incluir helpers de notificaciones para uso en PHP
require_once __DIR__ . '/notification_helpers.php';
?>