<?php
include 'conexion.php';

header('Content-Type: application/json');

$id = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : 0;

if(!$id || !isset($_FILES['guia'])) {
    echo json_encode(['success'=>false,'error'=>'Faltan datos o archivo']);
    exit;
}

// Buscar datos del pedido
$res = $conn->query("SELECT correo, nombre FROM pedidos_detal WHERE id = $id LIMIT 1");
if(!$res || $res->num_rows==0) {
    echo json_encode(['success'=>false,'error'=>'Pedido no encontrado']);
    exit;
}
$p = $res->fetch_assoc();

$directorio = __DIR__ . '/guias/';
if (!is_dir($directorio)) mkdir($directorio, 0755);

$ext = pathinfo($_FILES['guia']['name'], PATHINFO_EXTENSION);
$nombreGuia = $id . '.' . strtolower($ext);
$rutaArchivo = $directorio . $nombreGuia;

if(!move_uploaded_file($_FILES['guia']['tmp_name'], $rutaArchivo)){
    echo json_encode(['success'=>false,'error'=>'Error al guardar archivo']);
    exit;
}

// Guarda la ruta de la guía (opcional, en BD)
$conn->query("UPDATE pedidos_detal SET estado='enviado', guia='$nombreGuia' WHERE id=$id");

// --- Enviar email al cliente con la guía adjunta ---
$correo_cliente = $p['correo'];
$nombre_cliente = $p['nombre'];

$asunto = "Tu pedido #$id ha sido enviado";
$mensaje = "Hola $nombre_cliente,\n\nTu pedido #$id ha sido marcado como ENVIADO. Adjuntamos la guía de envío para tu seguimiento.\n\n¡Gracias por tu compra!\nSequoia Speed";
$from = "ventas@sequoiaspeed.com.co";
$boundary = md5(uniqid(time()));
$headers  = "From: Sequoia Speed <$from>\r\n";
$headers .= "Reply-To: $from\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$cuerpo  = "--$boundary\r\n";
$cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
$cuerpo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
$cuerpo .= $mensaje . "\r\n";

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

if(mail($correo_cliente, $asunto, $cuerpo, $headers)){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'Error enviando email al cliente.']);
}
?>