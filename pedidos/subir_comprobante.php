<?php
include 'conexion.php';

header('Content-Type: application/json');

$id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;

if(!$id || !isset($_FILES['comprobante'])) {
    echo json_encode(['success'=>false,'error'=>'Faltan datos o archivo']);
    exit;
}

// Buscar datos del pedido
$res = $conn->query("SELECT correo, nombre, monto FROM pedidos_detal WHERE id = $id LIMIT 1");
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

// --- Enviar email de confirmación (opcional) ---
$correo_cliente = $p['correo'];
$nombre_cliente = $p['nombre'];
$monto = number_format($p['monto'], 0, ',', '.');

$asunto = "Comprobante de pago recibido - Pedido #$id";
$mensaje = "Hola $nombre_cliente,\n\nHemos recibido tu comprobante de pago para el pedido #$id por valor de $".$monto.".\n\nEstamos verificando tu pago y procederemos con el envío de tu pedido.\n\n¡Gracias por tu compra!\nSequoia Speed";
$from = "ventas@sequoiaspeed.com.co";
$headers = "From: Sequoia Speed <$from>\r\n";
$headers .= "Reply-To: $from\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

// Enviar email (sin adjunto por seguridad)
mail($correo_cliente, $asunto, $mensaje, $headers);

echo json_encode([
    'success' => true,
    'message' => 'Comprobante subido correctamente',
    'archivo' => $nombreComprobante
]);
?>
