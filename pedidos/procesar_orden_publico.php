<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'email_templates.php';
require_once 'notifications/notification_helpers.php';

// VERSION PUBLICA - SIN AUTENTICACION REQUERIDA

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Detectar tipo de formulario
    $es_pedido_simple = isset($_POST['pedido']); // Formulario simple con textarea (index.php)
    $es_carrito_productos = isset($_POST['productos_json']); // Formulario con productos estructurados
    $es_pedido_guardado = isset($_POST['pedido_id']); // Pedido guardado desde orden_pedido.php

    // Debug: mostrar datos recibidos
    echo "<!-- Debug: POST data -->\n";
    echo "<!-- pedido: " . (isset($_POST['pedido']) ? "SÍ" : "NO") . " -->\n";
    echo "<!-- productos_json: " . (isset($_POST['productos_json']) ? "SÍ" : "NO") . " -->\n";
    echo "<!-- pedido_id: " . (isset($_POST['pedido_id']) ? $_POST['pedido_id'] : "NO") . " -->\n";
    echo "<!-- carrito_total: " . (isset($_POST['carrito_total']) ? $_POST['carrito_total'] : "NO") . " -->\n";

    // Validar campos comunes requeridos
    $campos_requeridos = ['monto', 'nombre', 'direccion', 'telefono', 'ciudad', 'barrio', 'correo', 'metodo_pago'];

    // Agregar campo específico según tipo de formulario
    if ($es_pedido_simple) {
        $campos_requeridos[] = 'pedido'; // El textarea del pedido
    } elseif ($es_pedido_guardado) {
        $campos_requeridos[] = 'pedido_id'; // El ID del pedido guardado
    }

    foreach ($campos_requeridos as $campo) {
        if (!isset($_POST[$campo]) || trim($_POST[$campo]) === '') {
            die("Error: El campo '$campo' es requerido. Valor recibido: '" . (isset($_POST[$campo]) ? $_POST[$campo] : 'NO EXISTE') . "'");
        }
    }

    // Recibe los campos comunes del formulario
    $monto = $_POST['monto'];
    
    // Limpiar y convertir monto a número si viene con formato
    if (is_string($monto)) {
        $monto = str_replace(['.', ',', '$', ' '], ['', '', '', ''], $monto);
    }
    $monto = floatval($monto);

    // Debug temporal - agregar log del monto recibido
    error_log("DEBUG MONTO: Valor recibido en POST['monto']: " . var_export($_POST['monto'], true) . " | Valor procesado: " . $monto);

    $nombre         = $_POST['nombre'];
    $direccion      = $_POST['direccion'];
    $telefono       = $_POST['telefono'];
    $ciudad         = $_POST['ciudad'];
    $barrio         = $_POST['barrio'];
    $correo         = $_POST['correo'];
    $metodo_pago    = $_POST['metodo_pago'];
    $descuento      = isset($_POST['descuento']) ? floatval($_POST['descuento']) : 0;

    // Detectar si es un pago Bold (cualquiera de los 3 métodos) y extraer datos específicos
    $metodos_bold = ['PSE Bold', 'Botón Bancolombia', 'Tarjeta de Crédito o Débito'];
    $es_pago_bold = in_array($metodo_pago, $metodos_bold);
    $bold_order_id = null;

    if ($es_pago_bold && isset($_POST['bold_order_id'])) {
        $bold_order_id = trim($_POST['bold_order_id']);
        echo "<!-- Debug: Pago Bold detectado con Order ID: $bold_order_id -->\n";
    }

    // Procesar productos según el tipo de formulario
    $productos_texto = "";

    if ($es_pedido_simple) {
        // Formulario simple: el pedido viene como texto directo
        $productos_texto = isset($_POST['pedido']) ? trim($_POST['pedido']) : "Pedido sin especificar";
    } elseif ($es_pedido_guardado) {
        // Pedido guardado: leer productos desde la base de datos
        $pedido_id_guardado = intval($_POST['pedido_id']);
        $productos_texto = "PEDIDO #$pedido_id_guardado:\n\n";

        $res = $conn->query("SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = $pedido_id_guardado");
        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $subtotal = $row['precio'] * $row['cantidad'];
                $productos_texto .= "• " . $row['nombre'];
                if ($row['talla'] && $row['talla'] != 'N/A') {
                    $productos_texto .= " (Talla: " . $row['talla'] . ")";
                }
                $productos_texto .= "\n";
                $productos_texto .= "  Cantidad: " . $row['cantidad'] . "\n";
                $productos_texto .= "  Precio: $" . number_format($row['precio'], 0) . "\n";
                $productos_texto .= "  Subtotal: $" . number_format($subtotal, 0) . "\n\n";
            }
        } else {
            $productos_texto .= "Error: No se encontraron productos para este pedido\n";
        }
    } else if ($es_carrito_productos) {
        // Formulario con carrito de productos: decodificar JSON de productos
        $productos_json = isset($_POST['productos_json']) ? $_POST['productos_json'] : '[]';
        $productos_array = json_decode($productos_json, true);

        if (is_array($productos_array) && count($productos_array) > 0) {
            foreach ($productos_array as $producto) {
                $nombre = isset($producto['nombre']) ? $producto['nombre'] : 'Producto sin nombre';
                $cantidad = isset($producto['cantidad']) ? $producto['cantidad'] : 1;
                $precio = isset($producto['precio']) ? $producto['precio'] : 0;
                $productos_texto .= "- " . $nombre . " (Cantidad: " . $cantidad . ", Precio: $" . number_format($precio, 0) . ")\n";
            }
        } else {
            $productos_texto = "Carrito de productos (sin detalles válidos)";
        }
    } else {
        // Caso por defecto: intentar construir descripción desde campos disponibles
        $productos_texto = "Pedido personalizado";
        if (isset($_POST['descripcion'])) {
            $productos_texto = trim($_POST['descripcion']);
        } else if (isset($_POST['pedido_texto'])) {
            $productos_texto = trim($_POST['pedido_texto']);
        }
    }

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
        case 'Datafono':
            $datos_pago = "Pago con tarjeta en punto de venta físico";
            break;
        case 'Efectivo_Bogota':
            $datos_pago = "Pago presencial en tienda de Bogotá";
            break;
        case 'Efectivo_Medellin':
            $datos_pago = "Pago presencial en tienda de Medellín";
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

    // PROCESAR ARCHIVO DE COMPROBANTE
    $rutaArchivo = '';
    if (isset($_FILES["comprobante"]) && is_uploaded_file($_FILES["comprobante"]["tmp_name"])) {
        $directorio = "comprobantes/";
        if (!is_dir($directorio)) {
            mkdir($directorio, 0755, true);
        }

        $nombreOriginal = basename($_FILES["comprobante"]["name"]);
        $ext = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
        $nombreAlmacenado = time() . "_" . uniqid() . "." . $ext;
        $rutaArchivo = $directorio . $nombreAlmacenado;

        if (!move_uploaded_file($_FILES["comprobante"]["tmp_name"], $rutaArchivo)) {
            $rutaArchivo = ''; // Si falla, queda vacío
        }
    }

    // GUARDAR O ACTUALIZAR EN LA TABLA PEDIDOS_DETAL
    if ($es_pedido_guardado) {
        // ACTUALIZAR pedido existente
        $pedido_id_guardado = intval($_POST['pedido_id']);
        $stmt = $conn->prepare("UPDATE pedidos_detal SET pedido = ?, monto = ?, descuento = ?, nombre = ?, direccion = ?, telefono = ?, ciudad = ?, barrio = ?, correo = ?, metodo_pago = ?, datos_pago = ?, comprobante = ? WHERE id = ?");

        if (!$stmt) {
            die("Error al preparar la consulta de actualización: " . $conn->error);
        }

        $stmt->bind_param("sddsssssssssi", $productos_texto, $monto, $descuento, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago, $datos_pago, $rutaArchivo, $pedido_id_guardado);

        if (!$stmt->execute()) {
            die("Error al ejecutar la consulta de actualización: " . $stmt->error);
        }

        $numero_pedido = $pedido_id_guardado; // Usar el ID existente
        $stmt->close();
    } else {
        // INSERTAR nuevo pedido
        // Para pedidos públicos, el usuario_id será NULL
        if ($es_pago_bold && $bold_order_id) {
            // Insertar pedido Bold con campos específicos
            $stmt = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, descuento, nombre, direccion, telefono, ciudad, barrio, correo, metodo_pago, usuario_id, datos_pago, comprobante, bold_order_id, estado_pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, 'pendiente')");

            if (!$stmt) {
                die("Error al preparar la consulta de inserción Bold: " . $conn->error);
            }

            $datos_pago = "Pago en proceso - $metodo_pago";
            $stmt->bind_param("sddssssssssss", $productos_texto, $monto, $descuento, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago, $datos_pago, $rutaArchivo, $bold_order_id);
            echo "<!-- Debug: Insertando pedido Bold con Order ID: $bold_order_id -->\n";
        } else {
            // Insertar pedido normal
            $stmt = $conn->prepare("INSERT INTO pedidos_detal (pedido, monto, descuento, nombre, direccion, telefono, ciudad, barrio, correo, metodo_pago, usuario_id, datos_pago, comprobante) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?)");

            if (!$stmt) {
                die("Error al preparar la consulta de inserción: " . $conn->error);
            }

            $stmt->bind_param("sddssssssssss", $productos_texto, $monto, $descuento, $nombre, $direccion, $telefono, $ciudad, $barrio, $correo, $metodo_pago, $datos_pago, $rutaArchivo);
        }

        if (!$stmt->execute()) {
            die("Error al ejecutar la consulta de inserción: " . $stmt->error);
        }

        $numero_pedido = $conn->insert_id;
        $stmt->close();
    }

    // PROCESAR PRODUCTOS PERSONALIZADOS SI EXISTEN (solo para pedidos nuevos)
    if (!$es_pedido_guardado && isset($_POST['productos_personalizados']) && !empty($_POST['productos_personalizados'])) {
        $productos_personalizados = json_decode($_POST['productos_personalizados'], true);

        if (is_array($productos_personalizados)) {
            foreach ($productos_personalizados as $producto_custom) {
                // Obtener categoria_id para "Personalizado"
                $catQuery = $conn->prepare("SELECT id FROM categorias_productos WHERE nombre = 'Personalizado' LIMIT 1");
                $catQuery->execute();
                $catResult = $catQuery->get_result();
                $categoria_id = $catResult->num_rows > 0 ? $catResult->fetch_assoc()['id'] : null;
                $catQuery->close();
                
                // Insertar producto personalizado en la tabla productos
                $stmt_producto = $conn->prepare("INSERT INTO productos (nombre, precio, categoria_id, activo) VALUES (?, ?, ?, 1)");
                $stmt_producto->bind_param("sdi", $producto_custom['nombre'], $producto_custom['precio'], $categoria_id);

                if ($stmt_producto->execute()) {
                    $producto_id = $conn->insert_id;
                    $stmt_producto->close();

                    // Buscar en carrito los items de este producto personalizado para guardar en pedido_detalle
                    if (isset($_POST['carrito_data']) && !empty($_POST['carrito_data'])) {
                        $carrito_data = json_decode($_POST['carrito_data'], true);

                        if (is_array($carrito_data)) {
                            foreach ($carrito_data as $item) {
                                // Verificar si es un producto personalizado que coincide
                                if (isset($item['isCustom']) && $item['isCustom'] && $item['id'] === $producto_custom['id']) {
                                    // Insertar en pedido_detalle
                                    $stmt_detalle = $conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, nombre, precio, cantidad, talla) VALUES (?, ?, ?, ?, ?, ?)");
                                    $stmt_detalle->bind_param("iisdis", $numero_pedido, $producto_id, $item['nombre'], $item['precio'], $item['cantidad'], $item['talla']);
                                    $stmt_detalle->execute();
                                    $stmt_detalle->close();
                                }
                            }
                        }
                    }
                } else {
                    echo "Error al crear producto personalizado: " . $stmt_producto->error . "\n";
                }
            }
        }
    }

    // PROCESAR PRODUCTOS REGULARES DEL CARRITO SI EXISTEN (solo para pedidos nuevos)
    if (!$es_pedido_guardado && isset($_POST['carrito_data']) && !empty($_POST['carrito_data'])) {
        $carrito_data = json_decode($_POST['carrito_data'], true);

        if (is_array($carrito_data)) {
            foreach ($carrito_data as $item) {
                // Solo procesar productos regulares (no personalizados)
                if (!isset($item['isCustom']) || !$item['isCustom']) {
                    // Obtener ID del producto regular
                    $producto_id = is_numeric($item['id']) ? intval($item['id']) : 0;

                    if ($producto_id > 0) {
                        // Insertar en pedido_detalle
                        $stmt_detalle = $conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, nombre, precio, cantidad, talla) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_detalle->bind_param("iisdis", $numero_pedido, $producto_id, $item['nombre'], $item['precio'], $item['cantidad'], $item['talla']);
                        $stmt_detalle->execute();
                        $stmt_detalle->close();
                    }
                }
            }
        }
    }

    // CARGAR PRODUCTOS DETALLADOS DESDE LA BASE DE DATOS PARA EL EMAIL
    $detalle_query = "SELECT nombre, precio, cantidad, talla FROM pedido_detalle WHERE pedido_id = ?";
    $stmt_detalle = $conn->prepare($detalle_query);
    $stmt_detalle->bind_param("i", $numero_pedido);
    $stmt_detalle->execute();

    // Usar bind_result en lugar de get_result para compatibilidad con todos los servidores
    $nombre_prod = $precio_prod = $cantidad_prod = $talla_prod = '';
    $stmt_detalle->bind_result($nombre_prod, $precio_prod, $cantidad_prod, $talla_prod);

    $productos_detallados = [];
    $monto_calculado = 0;
    while ($stmt_detalle->fetch()) {
        $subtotal = $precio_prod * $cantidad_prod;
        $monto_calculado += $subtotal;
        $productos_detallados[] = [
            'nombre' => $nombre_prod,
            'precio' => $precio_prod,
            'cantidad' => $cantidad_prod,
            'talla' => $talla_prod
        ];
    }
    $stmt_detalle->close();

    // Usar el monto calculado de los productos si hay detalles, sino usar el monto del POST
    $monto_final = !empty($productos_detallados) ? $monto_calculado : $monto;

    $pedidoData = [
        'numero_pedido' => $numero_pedido,
        'nombre' => $nombre,
        'correo' => $correo,
        'telefono' => $telefono,
        'ciudad' => $ciudad,
        'barrio' => $barrio,
        'direccion' => $direccion,
        'metodo_pago' => $metodo_pago,
        'monto' => $monto_final,
        'datos_pago' => $datos_pago,
        'pedido_texto' => $productos_texto,
        'detalles' => $productos_detallados,
        'estado_pago' => 'pendiente'
    ];

    // GENERAR EMAIL HTML CON PLANTILLA VSCODE DARK + APPLE
    $htmlBody = EmailTemplates::nuevoPedido($pedidoData);

    // PREPARAR CORREO CON FORMATO HTML
    // REEMPLAZAR DESTINATARIOS INTERNOS CON NOTIFICACIÓN
    notificarNuevoPedido($numero_pedido, $nombre, $monto);
    
    $destinatarios = "$correo"; // Solo enviar al cliente
    $boundary = md5(uniqid(time()));

    $headers  = "From: $nombre <ventas@sequoiaspeed.com.co>\r\n";
    $headers .= "Reply-To: $correo\r\n";
    $headers .= "Cc: $correo\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    // CUERPO DEL EMAIL CON HTML
    $cuerpo  = "--$boundary\r\n";
    $cuerpo .= "Content-Type: text/html; charset=UTF-8\r\n";
    $cuerpo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $cuerpo .= $htmlBody . "\r\n";
    // Adjuntar comprobante solo si hay archivo
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

    // Enviar correo
    mail($destinatarios, "Nueva Orden de Pedido - $nombre (#$numero_pedido)", $cuerpo, $headers);

    // Redirigir directamente al comprobante
    header("Location: comprobante.php?orden=$numero_pedido");
    exit;
} else {
    // Si no es POST, redirigir al inicio
    header("Location: pedido.php");
    exit;
}