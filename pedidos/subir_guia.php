<?php
/**
 * Subir Guía de Envío - Con opción de marcar como enviado
 * Sequoia Speed - Gestión de guías de envío
 */

include 'conexion.php';

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
$stmt = $conn->prepare("SELECT correo, nombre FROM pedidos_detal WHERE id = ? LIMIT 1");
if (!$stmt) {
    echo json_encode(['success'=>false,'error'=>'Error en consulta: ' . $conn->error]);
    exit;
}

$stmt->bind_param("i", $id);
if (!$stmt->execute()) {
    echo json_encode(['success'=>false,'error'=>'Error al buscar pedido: ' . $stmt->error]);
    exit;
}

$stmt->bind_result($correo_cliente, $nombre_cliente);
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

// Enviar email al cliente con la guía adjunta
$asunto_cliente = $marcar_enviado ? "🚚 Tu pedido #$id ha sido enviado" : "📋 Guía de envío para tu pedido #$id";
$mensaje_cliente = $marcar_enviado ?
    "Hola $nombre_cliente,\n\nTu pedido #$id ha sido marcado como ENVIADO. Adjuntamos la guía de envío para tu seguimiento.\n\n¡Gracias por tu compra!\nSequoia Speed" :
    "Hola $nombre_cliente,\n\nHemos adjuntado la guía de envío para tu pedido #$id. Pronto recibirás más información sobre el estado de envío.\n\n¡Gracias por tu compra!\nSequoia Speed";

$from = "ventas@sequoiaspeed.com.co";
$boundary = md5(uniqid(time()));
$headers  = "From: Sequoia Speed <$from>\r\n";
$headers .= "Reply-To: $from\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$cuerpo  = "--$boundary\r\n";
$cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
$cuerpo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$cuerpo .= $mensaje_cliente . "\r\n";

if(file_exists($rutaArchivo)){
    $archivo = chunk_split(base64_encode(file_get_contents($rutaArchivo)));
    $tipoArchivo = mime_content_type($rutaArchivo);
    $cuerpo .= "--$boundary\r\n";
    $cuerpo .= "Content-Type: $tipoArchivo; name=\"$nombreGuia\"\r\n";
    $cuerpo .= "Content-Disposition: attachment; filename=\"$nombreGuia\"\r\n";
    $cuerpo .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $cuerpo .= $archivo . "\r\n";
}
$cuerpo .= "--$boundary--";

$email_enviado = mail($correo_cliente, $asunto_cliente, $cuerpo, $headers);

echo json_encode([
    'success' => true,
    'message' => "Guía subida correctamente y pedido marcado como $estado_mensaje",
    'email_enviado' => $email_enviado,
    'marcar_enviado' => $marcar_enviado
]);
?>
