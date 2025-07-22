<?php
/**
 * EJEMPLO DE INTEGRACIÓN DEL SISTEMA DE NOTIFICACIONES
 * 
 * Este archivo muestra cómo reemplazar los emails internos con notificaciones
 * manteniendo los emails a clientes intactos
 */

// ANTES (con emails internos):
/*
// Enviar email a administradores
$para_admin = "joshua@example.com, jorge@example.com, ventas@example.com";
$asunto = "Nuevo pedido recibido";
$mensaje = "Se ha recibido un nuevo pedido...";
mail($para_admin, $asunto, $mensaje);

// Enviar email al cliente (MANTENER ESTO)
$para_cliente = $email_cliente;
$asunto_cliente = "Confirmación de pedido";
mail($para_cliente, $asunto_cliente, $mensaje_cliente);
*/

// DESPUÉS (con notificaciones):
require_once 'notifications/notification_helpers.php';

// 1. EJEMPLO: Nuevo Pedido
function procesarNuevoPedido($pedido_data) {
    // ... código existente ...
    
    // Reemplazar email interno con notificación
    notificarNuevoPedido($pedido_id, $nombre_cliente, $monto_total);
    
    // MANTENER email al cliente
    enviarEmailConfirmacionCliente($email_cliente, $pedido_data);
}

// 2. EJEMPLO: Pago Confirmado
function confirmarPago($pedido_id) {
    // ... código existente ...
    
    // Reemplazar email interno con notificación
    notificarPagoConfirmado($pedido_id, $monto, $metodo_pago);
    
    // MANTENER email al cliente
    enviarEmailPagoConfirmadoCliente($email_cliente, $pedido_data);
}

// 3. EJEMPLO: En subir_guia.php
// BUSCAR líneas como:
// $destinatarios = "ventas@sequoiaspeed.com.co,joshua.alzate@gmail.com,comercial@sequoiaspeed.com.co";
// mail($destinatarios, $asunto, $mensaje_completo, $headers);

// REEMPLAZAR con:
notificarGuiaAdjuntada($pedido_id, $numero_guia);
// Pero MANTENER el mail() al cliente

// 4. EJEMPLO: En subir_comprobante.php
// BUSCAR emails a joshua/jorge/ventas y REEMPLAZAR con:
notificarComprobanteSubido($pedido_id, 'pago');

// 5. EJEMPLO: Manejo de errores
try {
    // ... algún proceso ...
} catch (Exception $e) {
    // En lugar de email de error
    notificarError('Proceso de pago', $e->getMessage(), $pedido_id);
}

// 6. EJEMPLO: Incluir CSS y JS en páginas
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Otros estilos -->
    <link rel="stylesheet" href="/pedidos/notifications/notifications.css">
</head>
<body>
    <!-- Contenido de la página -->
    
    <!-- Antes del cierre de body -->
    <script src="/pedidos/notifications/notifications.js"></script>
    
    <!-- También puedes mostrar notificaciones desde JavaScript -->
    <script>
    // Ejemplo de notificación manual desde JS
    function ejemploNotificacion() {
        showNotification('success', 'Operación Exitosa', 'El pedido se guardó correctamente', {
            actions: [
                {
                    label: 'Ver Pedido',
                    url: '/pedidos/ver_detalle_pedido.php?id=123',
                    target: '_blank'
                }
            ]
        });
    }
    </script>
</body>
</html>

<?php
// PATRÓN PARA BUSCAR Y REEMPLAZAR:
// 1. Buscar: mail($destinatarios_internos, ...)
// 2. Si $destinatarios incluye joshua, jorge o ventas -> Reemplazar con notificación
// 3. Si es email a cliente -> MANTENER sin cambios

// EMAILS QUE SE DEBEN MANTENER:
// - Confirmación de pedido al cliente
// - Pago confirmado al cliente  
// - Guía de envío al cliente
// - Anulación de pedido al cliente
// - Cualquier comunicación con el cliente

// EMAILS QUE SE REEMPLAZAN CON NOTIFICACIONES:
// - Notificaciones a joshua@...
// - Notificaciones a jorge@...
// - Notificaciones a ventas@...
// - Notificaciones a comercial@...
?>