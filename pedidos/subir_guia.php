<?php
/**
 * Subir Guía de Envío - Con opción de marcar como enviado
 * Sequoia Speed - Gestión de guías de envío
 */

include 'conexion.php';
include 'email_templates.php';
require_once 'notifications/notification_helpers.php';

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;
$marcar_enviado = isset($_POST['marcar_enviado']) ? $_POST['marcar_enviado'] === 'true' : false;

if(!$id || !isset($_FILES['guia'])) {
    echo json_encode(['success'=>false,'error'=>'Faltan datos o archivo']);
    exit;
}

// Buscar datos del pedido usando método compatible
$stmt = $conn->prepare("SELECT correo, nombre, monto, descuento, metodo_pago FROM pedidos_detal WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success'=>false,'error'=>'Error en consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    echo json_encode(['success'=>false,'error'=>'Error al buscar pedido: ' . $stmt->error]);
    exit;
}

$stmt->bind_result($correo_cliente, $nombre_cliente, $monto_pedido, $descuento_pedido, $metodo_pago_pedido);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'error'=>'Pedido no encontrado']);
    exit;
}
$stmt->close();

$directorio = __DIR__ . '/guias/';
if (!is_dir($directorio)) mkdir($directorio, 0755);

$ext = pathinfo($_FILES['guia']['name'], PATHINFO_EXTENSION);
$nombreGuia = $id . '.' . strtolower($ext);
$rutaArchivo = $directorio . $nombreGuia;

if(!move_uploaded_file($_FILES['guia']['tmp_name'], $rutaArchivo)){
    echo json_encode(['success'=>false,'error'=>'Error al guardar archivo']);
    exit;
}

// Actualizar base de datos según las opciones seleccionadas
if ($marcar_enviado) {
    // Marcar como enviado y con guía
    $stmt = $conn->prepare("UPDATE pedidos_detal SET enviado='1', tiene_guia='1', guia=? WHERE id=?");
    $estado_mensaje = "enviado y con guía adjunta";
} else {
    // Solo marcar que tiene guía, sin cambiar estado de envío
    $stmt = $conn->prepare("UPDATE pedidos_detal SET tiene_guia='1', guia=? WHERE id=?");
    $estado_mensaje = "con guía adjunta";
}

if (!$stmt) {
    echo json_encode(['success'=>false,'error'=>'Error en actualización: ' . $conn->error]);
    exit;
}

$stmt->bind_param("si", $nombreGuia, $id);
if (!$stmt->execute()) {
    echo json_encode(['success'=>false,'error'=>'Error al actualizar pedido: ' . $stmt->error]);
    exit;
}
$stmt->close();

// Agregar nota interna del cambio
date_default_timezone_set('America/Bogota');
$timestamp = date('Y-m-d H:i:s');
$nota_cambio = "[$timestamp - Sistema] Guía de envío subida" . ($marcar_enviado ? " y pedido marcado como enviado" : "");

// Obtener nota actual y agregar la nueva usando método compatible
$stmt = $conn->prepare("SELECT nota_interna FROM pedidos_detal WHERE id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($notas_existentes);
    $stmt->fetch();
    $stmt->close();

    if (!empty($notas_existentes)) {
        $todas_las_notas = $nota_cambio . "\n\n" . $notas_existentes;
    } else {
        $todas_las_notas = $nota_cambio;
    }

    // Actualizar nota
    $stmt = $conn->prepare("UPDATE pedidos_detal SET nota_interna = ? WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("si", $todas_las_notas, $id);
        $stmt->execute();
        $stmt->close();
    }
}

// Preparar datos del pedido para el template
$pedido_data = [
    'id' => $id,
    'nombre_cliente' => $nombre_cliente,
    'correo_cliente' => $correo_cliente,
    'estado' => $marcar_enviado ? 'enviado' : 'con_guia',
    'archivo_guia' => $nombreGuia,
    'fecha_envio' => date('Y-m-d H:i:s'),
    'monto' => $monto_pedido,
    'total' => $monto_pedido, // Para compatibilidad con templates
    'descuento' => $descuento_pedido,
    'metodo_pago' => $metodo_pago_pedido
];

// Generar email bonito para el cliente usando el template
$asunto_cliente = $marcar_enviado ? "🚚 Tu pedido #$id ha sido enviado - Sequoia Speed" : "📦 Guía de envío adjunta - Pedido #$id - Sequoia Speed";
$html_content_cliente = EmailTemplates::emailEntregaConGuia($id, $nombre_cliente, $pedido_data);

// Configurar headers para email HTML con adjunto
$from = "ventas@sequoiaspeed.com.co";
$boundary = md5(uniqid(time()));
$headers_cliente = "From: Sequoia Speed <$from>\r\n";
$headers_cliente .= "Reply-To: $from\r\n";
$headers_cliente .= "MIME-Version: 1.0\r\n";
$headers_cliente .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

// Crear cuerpo del email HTML para cliente
$cuerpo_cliente = "--$boundary\r\n";
$cuerpo_cliente .= "Content-Type: text/html; charset=UTF-8\r\n";
$cuerpo_cliente .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$cuerpo_cliente .= $html_content_cliente . "\r\n";

// Adjuntar guía al email del cliente
if(file_exists($rutaArchivo)){
    $archivo = chunk_split(base64_encode(file_get_contents($rutaArchivo)));
    $tipoArchivo = mime_content_type($rutaArchivo);
    $cuerpo_cliente .= "--$boundary\r\n";
    $cuerpo_cliente .= "Content-Type: $tipoArchivo; name=\"$nombreGuia\"\r\n";
    $cuerpo_cliente .= "Content-Disposition: attachment; filename=\"$nombreGuia\"\r\n";
    $cuerpo_cliente .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $cuerpo_cliente .= $archivo . "\r\n";
}
$cuerpo_cliente .= "--$boundary--";

// Enviar email bonito al cliente
$email_cliente_enviado = mail($correo_cliente, $asunto_cliente, $cuerpo_cliente, $headers_cliente);

// Generar email bonito para ventas usando el template
$correo_ventas = "ventas@sequoiaspeed.com.co";
$asunto_ventas = "🔔 Copia: Guía adjuntada - Pedido #$id - Sequoia Speed";

// Crear contenido HTML personalizado para ventas
$html_content_ventas = EmailTemplates::getMainTemplate(
    "Copia: Guía adjuntada - Pedido #$id",
    '
    <div class="section">
        <h2>🔔 Notificación de Guía Adjuntada</h2>
        <p>Se ha adjuntado la guía de envío para el pedido #' . $id . '</p>
    </div>

    <div class="info-card">
        <h3>Información del Pedido</h3>
        <div class="info-row">
            <span class="info-label">Pedido:</span>
            <span class="info-value">#' . $id . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Cliente:</span>
            <span class="info-value">' . htmlspecialchars($nombre_cliente) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span class="info-value">' . htmlspecialchars($correo_cliente) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Estado:</span>
            <span class="info-value"><span class="status-badge ' . ($marcar_enviado ? 'status-success' : 'status-pending') . '">' . ($marcar_enviado ? 'ENVIADO' : 'Con guía adjunta') . '</span></span>
        </div>
        <div class="info-row">
            <span class="info-label">Archivo:</span>
            <span class="info-value">' . htmlspecialchars($nombreGuia) . '</span>
        </div>
        <div class="info-row">
            <span class="info-label">Fecha:</span>
            <span class="info-value">' . date('d/m/Y H:i:s') . '</span>
        </div>
    </div>

    <div class="section">
        <p style="color: #8b949e !important; font-size: 14px; font-style: italic;">
            Esta es una copia automática del email enviado al cliente.
        </p>
    </div>',
    'Sequoia Speed - Sistema de Gestión'
);

// Configurar headers para email de ventas
$headers_ventas = "From: Sequoia Speed <$from>\r\n";
$headers_ventas .= "Reply-To: $from\r\n";
$headers_ventas .= "MIME-Version: 1.0\r\n";
$headers_ventas .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

// Crear cuerpo del email HTML para ventas
$cuerpo_ventas = "--$boundary\r\n";
$cuerpo_ventas .= "Content-Type: text/html; charset=UTF-8\r\n";
$cuerpo_ventas .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$cuerpo_ventas .= $html_content_ventas . "\r\n";

// Adjuntar guía al email de ventas
if(file_exists($rutaArchivo)){
    $archivo = chunk_split(base64_encode(file_get_contents($rutaArchivo)));
    $tipoArchivo = mime_content_type($rutaArchivo);
    $cuerpo_ventas .= "--$boundary\r\n";
    $cuerpo_ventas .= "Content-Type: $tipoArchivo; name=\"$nombreGuia\"\r\n";
    $cuerpo_ventas .= "Content-Disposition: attachment; filename=\"$nombreGuia\"\r\n";
    $cuerpo_ventas .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $cuerpo_ventas .= $archivo . "\r\n";
}
$cuerpo_ventas .= "--$boundary--";

// REEMPLAZAR EMAIL A VENTAS CON NOTIFICACIÓN
notificarGuiaAdjuntada($id, $nombreGuia);

// Si se marcó como enviado, también notificar ese cambio
if ($marcar_enviado === 'true') {
    notificarCambioEstado($id, 'pendiente', 'enviado');
}

$email_ventas_enviado = true; // Para compatibilidad con el response

echo json_encode([
    'success' => true,
    'message' => "Guía subida correctamente y pedido marcado como $estado_mensaje",
    'email_cliente_enviado' => $email_cliente_enviado,
    'email_ventas_enviado' => $email_ventas_enviado,
    'marcar_enviado' => $marcar_enviado
]);
?>
