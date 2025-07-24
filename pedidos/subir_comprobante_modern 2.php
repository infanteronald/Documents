<?php
/**
 * Subir comprobante de pago para un pedido
 * Versión compatible con el sistema de pedidos modernizado
 */

header('Content-Type: application/json');

// Verificar parámetros necesarios
$id_pedido = isset($_POST['id_pedido']) ? intval($_POST['id_pedido']) : (isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0);

if (!$id_pedido || !isset($_FILES['comprobante'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Faltan datos o archivo de comprobante'
    ]);
    exit;
}

try {
    // Incluir la configuración de la base de datos
    require_once 'config_secure.php';

    // Buscar datos del pedido
    $stmt = $pdo->prepare("SELECT id, cliente_email, cliente_nombre, total, estado FROM pedidos WHERE id = ? LIMIT 1");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo json_encode([
            'success' => false,
            'error' => 'Pedido no encontrado'
        ]);
        exit;
    }

    // Validar archivo
    $archivo = $_FILES['comprobante'];
    $tamaño_max = 5 * 1024 * 1024; // 5MB

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        echo json_encode([
            'success' => false,
            'error' => 'Error en la subida del archivo'
        ]);
        exit;
    }

    if ($archivo['size'] > $tamaño_max) {
        echo json_encode([
            'success' => false,
            'error' => 'El archivo es demasiado grande (máximo 5MB)'
        ]);
        exit;
    }

    $extensiones_permitidas = ['jpg', 'jpeg', 'png', 'pdf', 'gif'];
    $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $extensiones_permitidas)) {
        echo json_encode([
            'success' => false,
            'error' => 'Formato no permitido. Use JPG, PNG, GIF o PDF'
        ]);
        exit;
    }

    // Crear directorio si no existe
    $directorio = __DIR__ . '/comprobantes/';
    if (!is_dir($directorio)) {
        mkdir($directorio, 0755, true);
    }

    // Nombre único para el archivo
    $nombreComprobante = 'comprobante_' . $id_pedido . '_' . time() . '.' . $ext;
    $rutaArchivo = $directorio . $nombreComprobante;

    // Mover archivo
    if (!move_uploaded_file($archivo['tmp_name'], $rutaArchivo)) {
        echo json_encode([
            'success' => false,
            'error' => 'Error al guardar archivo en el servidor'
        ]);
        exit;
    }

    // Actualizar base de datos - agregar comprobante y actualizar estado si es necesario
    $nuevo_estado = ($pedido['estado'] === 'pendiente') ? 'confirmado' : $pedido['estado'];

    $stmt = $pdo->prepare("
        UPDATE pedidos
        SET comprobante_pago = ?,
            estado = ?,
            fecha_actualizacion = NOW()
        WHERE id = ?
    ");

    if (!$stmt->execute([$nombreComprobante, $nuevo_estado, $id_pedido])) {
        // Si falla la BD, eliminar el archivo subido
        unlink($rutaArchivo);
        echo json_encode([
            'success' => false,
            'error' => 'Error al actualizar base de datos'
        ]);
        exit;
    }

    // Registrar nota del cambio
    try {
        $nota_stmt = $pdo->prepare("
            INSERT INTO pedidos_notas (pedido_id, nota, fecha_creacion)
            VALUES (?, ?, NOW())
        ");
        $nota_stmt->execute([
            $id_pedido,
            'Comprobante de pago subido: ' . $nombreComprobante .
            ($nuevo_estado !== $pedido['estado'] ? '. Estado actualizado a: ' . $nuevo_estado : '')
        ]);
    } catch (Exception $e) {
        // No es crítico si falla el registro de la nota
    }

    // --- Enviar email de confirmación (opcional) ---
    if (!empty($pedido['cliente_email'])) {
        try {
            $correo_cliente = $pedido['cliente_email'];
            $nombre_cliente = $pedido['cliente_nombre'];
            $monto = number_format($pedido['total'], 2, ',', '.');

            $asunto = "Comprobante de pago recibido - Pedido #$id_pedido";
            $mensaje = "Hola $nombre_cliente,\n\n";
            $mensaje .= "Hemos recibido tu comprobante de pago para el pedido #$id_pedido por valor de $$monto.\n\n";
            $mensaje .= "Estamos verificando tu pago y procederemos con el envío de tu pedido.\n\n";
            $mensaje .= "Estado actual del pedido: " . ucfirst($nuevo_estado) . "\n\n";
            $mensaje .= "¡Gracias por tu compra!\nSequoia Speed";

            $from = "ventas@sequoiaspeed.com.co";
            $headers = "From: Sequoia Speed <$from>\r\n";
            $headers .= "Reply-To: $from\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            // Enviar email (sin adjunto por seguridad)
            mail($correo_cliente, $asunto, $mensaje, $headers);
        } catch (Exception $e) {
            // No es crítico si falla el envío del email
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Comprobante subido correctamente',
        'data' => [
            'archivo' => $nombreComprobante,
            'pedido_id' => $id_pedido,
            'estado_anterior' => $pedido['estado'],
            'estado_nuevo' => $nuevo_estado,
            'url_comprobante' => 'comprobantes/' . $nombreComprobante
        ]
    ]);

} catch (PDOException $e) {
    // Si hay error y el archivo se subió, eliminarlo
    if (isset($rutaArchivo) && file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }

    echo json_encode([
        'success' => false,
        'error' => 'Error de base de datos: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Si hay error y el archivo se subió, eliminarlo
    if (isset($rutaArchivo) && file_exists($rutaArchivo)) {
        unlink($rutaArchivo);
    }

    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>
