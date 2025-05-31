<?php
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Recibe los campos del formulario
    $pedido         = $_POST['pedido'];
    $monto          = $_POST['monto'];
    $nombre         = $_POST['nombre'];
    $direccion      = $_POST['direccion'];
    $telefono       = $_POST['telefono'];
    $correo         = $_POST['correo'];
    $persona_recibe = $_POST['persona_recibe'];
    $horarios       = $_POST['horarios'];
    $metodo_pago    = $_POST['metodo_pago'];

    // Datos de pago según método
    switch ($metodo_pago) {
      case 'Nequi':
      case 'Transfiya':
        $datos_pago = "3213260357";
        break;
      case 'Bancolombia':
        $datos_pago = "Ahorros 03500000175 Ronald Infante";
        break;
      case 'Provincial':
        $datos_pago = "Ahorros 0958004765 Ronald Infante";
        break;
      case 'PSE':
        $datos_pago = "Solicitar link de pago a su asesor";
        break;
      case 'Contra entrega':
        $datos_pago = "No requiere pago anticipado";
        break;
      default:
        $datos_pago = "";
    }

    // PROCESAR ARCHIVO
    $rutaArchivo = '';
    if (isset($_FILES["comprobante"]) && is_uploaded_file($_FILES["comprobante"]["tmp_name"])) {
        $directorio = "comprobantes/";
        if (!is_dir($directorio)) mkdir($directorio, 0755, true);

        $nombreOriginal = basename($_FILES["comprobante"]["name"]);
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $nombreAlmacenado = time() . "_" . uniqid() . "." . $ext;
        $rutaArchivo = $directorio . $nombreAlmacenado;
        if (!move_uploaded_file($_FILES["comprobante"]["tmp_name"], $rutaArchivo)) {
            $rutaArchivo = ''; // Si falla, queda vacío
        }
    }

    // GUARDAR EN BD
    $stmt = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, nombre, direccion, telefono, correo, persona_recibe, horarios, metodo_pago, datos_pago, comprobante, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'sin_enviar')");
    $stmt->bind_param("sdsssssssss", $pedido, $monto, $nombre, $direccion, $telefono, $correo, $persona_recibe, $horarios, $metodo_pago, $datos_pago, $rutaArchivo);
    $stmt->execute();
    $numero_pedido = $conn->insert_id;
    $stmt->close();

    // PREPARAR EL CORREO
    $boundary = md5(uniqid(time()));
    $mensaje = "Número de pedido: #$numero_pedido\n\n";
    $mensaje .= "Se ha realizado una nueva orden de pedido:\n\n".
        "Pedido: $pedido\nMonto: $monto\nNombre: $nombre\nDirección: $direccion\nTeléfono: $telefono\nCorreo: $correo\n".
        "Persona que recibe: $persona_recibe\nHorarios: $horarios\nMétodo de pago: $metodo_pago\nDatos pago: $datos_pago\n";

    $destinatarios = "ventas@sequoiaspeed.com.co,jorgejosecardozo@gmail.com,joshuagamer95@gmail.com";
    $headers  = "From: $nombre <ventas@sequoiaspeed.com.co>\r\n";
    $headers .= "Reply-To: $correo\r\n";
    $headers .= "Cc: $correo\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    $cuerpo  = "--$boundary\r\n";
    $cuerpo .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $cuerpo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $cuerpo .= $mensaje . "\r\n";

    // Adjuntar solo si hay comprobante
    if ($rutaArchivo && file_exists($rutaArchivo)) {
        $archivo = chunk_split(base64_encode(file_get_contents($rutaArchivo)));
        $tipoArchivo = mime_content_type($rutaArchivo);
        $nombreParaCorreo = basename($rutaArchivo);
        $cuerpo .= "--$boundary\r\n";
        $cuerpo .= "Content-Type: $tipoArchivo; name=\"$nombreParaCorreo\"\r\n";
        $cuerpo .= "Content-Disposition: attachment; filename=\"$nombreParaCorreo\"\r\n";
        $cuerpo .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $cuerpo .= $archivo . "\r\n";
    }
    $cuerpo .= "--$boundary--";

    mail($destinatarios, "Nueva Orden de Pedido de $nombre", $cuerpo, $headers);

    header("Location: index.php?success=1&pedido=$numero_pedido");
    exit;
}
?>