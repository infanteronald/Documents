<?php
require_once 'config_secure.php';
include 'email_templates.php';
require_once 'notifications/notification_helpers.php';

header('Content-Type: application/json');

$id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : (isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0);

if(!$id || !isset($_FILES['comprobante'])) {
    echo json_encode(['success'=>false,'error'=>'Faltan datos o archivo']);
    exit;
}

// Buscar datos del pedido
$res = $conn->query("SELECT correo, nombre, monto, descuento, metodo_pago FROM pedidos_detal WHERE id = $id LIMIT 1");
if(!$res || $res->num_rows==0) {
    echo json_encode(['success'=>false,'error'=>'Pedido no encontrado']);
    exit;
}
$p = $res->fetch_assoc();

// Validar archivo
$archivo = $_FILES['comprobante'];
$tamaño_max = 5 * 1024 * 1024; // 5MB

if($archivo['size'] > $tamaño_max) {
    echo json_encode(['success'=>false,'error'=>'El archivo es demasiado grande (máx. 5MB)']);
    exit;
}

$extensiones_permitidas = ['jpg', 'jpeg', 'png', 'pdf'];
$ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

if(!in_array($ext, $extensiones_permitidas)) {
    echo json_encode(['success'=>false,'error'=>'Formato no permitido. Use JPG, PNG o PDF']);
    exit;
}

// Crear directorio si no existe
$directorio = __DIR__ . '/comprobantes/';
if (!is_dir($directorio)) {
    mkdir($directorio, 0755, true);
}

// Nombre único para el archivo
$nombreComprobante = 'comprobante_' . $id . '_' . time() . '.' . $ext;
$rutaArchivo = $directorio . $nombreComprobante;

// Mover archivo
if(!move_uploaded_file($archivo['tmp_name'], $rutaArchivo)){
    echo json_encode(['success'=>false,'error'=>'Error al guardar archivo']);
    exit;
}

// Actualizar base de datos - marca como pagado y con comprobante
$stmt = $conn->prepare("UPDATE pedidos_detal SET comprobante = ?, tiene_comprobante = '1', pagado = 1 WHERE id = ?");
$stmt->bind_param("si", $nombreComprobante, $id);

if(!$stmt->execute()) {
    // Si falla la BD, eliminar el archivo subido
    unlink($rutaArchivo);
    echo json_encode(['success'=>false,'error'=>'Error al actualizar base de datos']);
    exit;
}

// Preparar datos del pedido para el template
$correo_cliente = $p['correo'];
$nombre_cliente = $p['nombre'];
$descuento = $p['descuento'] ?? 0;
$monto_final = $p['monto'];

$pedido_data = [
    'id' => $id,
    'nombre_cliente' => $nombre_cliente,
    'correo_cliente' => $correo_cliente,
    'monto' => $monto_final, // Campo principal
    'total' => $monto_final, // Para compatibilidad con templates
    'descuento' => $descuento,
    'metodo_pago' => $p['metodo_pago'],
    'archivo_comprobante' => $nombreComprobante,
    'es_efectivo' => false
];

// Generar email bonito para el cliente usando el template
$asunto_cliente = "💳 Comprobante recibido - Pedido #$id - Sequoia Speed";
$html_content_cliente = EmailTemplates::emailComprobanteRecibido($id, $nombre_cliente, $pedido_data);

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

// Adjuntar comprobante al email del cliente
if(file_exists($rutaArchivo)){
    $archivo = chunk_split(base64_encode(file_get_contents($rutaArchivo)));
    $tipoArchivo = mime_content_type($rutaArchivo);
    $cuerpo_cliente .= "--$boundary\r\n";
    $cuerpo_cliente .= "Content-Type: $tipoArchivo; name=\"$nombreComprobante\"\r\n";
    $cuerpo_cliente .= "Content-Disposition: attachment; filename=\"$nombreComprobante\"\r\n";
    $cuerpo_cliente .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $cuerpo_cliente .= $archivo . "\r\n";
}
$cuerpo_cliente .= "--$boundary--";

// Enviar email bonito al cliente (MANTENER)
$email_cliente_enviado = mail($correo_cliente, $asunto_cliente, $cuerpo_cliente, $headers_cliente);

// REEMPLAZAR EMAIL A VENTAS CON NOTIFICACIÓN
$monto_formateado = number_format($monto_final, 0, ',', '.');
notificarComprobanteSubido($id, 'pago');

// También notificar que el pago fue confirmado
notificarPagoConfirmado($id, $monto_final, $p['metodo_pago']);

$email_ventas_enviado = true; // Para compatibilidad con el response

echo json_encode([
    'success' => true,
    'message' => 'Comprobante subido correctamente',
    'archivo' => $nombreComprobante,
    'email_cliente_enviado' => $email_cliente_enviado,
    'email_ventas_enviado' => $email_ventas_enviado
]);
?>
