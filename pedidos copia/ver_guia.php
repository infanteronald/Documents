<?php
// ver_guia.php
require_once 'config_secure.php';
require_once 'php82_helpers.php';

// Obtener ID de pedido
$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id_pedido <= 0) {
    die("ID de pedido no válido.");
}

// Buscar datos de la guía para ese pedido
$query = "SELECT numero_guia, url_imagen_guia FROM pedidos_detal WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id_pedido);
$stmt->execute();
$stmt->bind_result($numero_guia, $url_imagen_guia);
$stmt->fetch();
$stmt->close();

// Si no hay datos, variables quedan vacías
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ver guía del pedido</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="stylesheet" href="apple-ui.css">
    <style>
        .guia-container {
            max-width: 400px;
            margin: 30px auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 3px 18px #d7dbe6;
            padding: 30px;
            text-align: center;
        }
        .guia-img {
            max-width: 350px;
            border-radius: 10px;
            margin-bottom: 15px;
            box-shadow: 0 1.5px 8px #e0e2e7;
        }
        .guia-boton {
            display: inline-flex;
            align-items: center;
            background: #2362c7;
            color: white;
            border-radius: 8px;
            padding: 10px 24px;
            text-decoration: none;
            font-size: 17px;
            margin-top: 12px;
            transition: background 0.2s;
        }
        .guia-boton:hover {
            background: #153a75;
        }
    </style>
</head>
<body>
    <div class="guia-container">
        <h2>Guía del Pedido #<?= $id_pedido ?></h2>

        <?php if ($url_imagen_guia): ?>
            <img src="<?= h($url_imagen_guia) ?>" alt="Imagen de la guía" class="guia-img">
        <?php endif; ?>

        <div style="margin-bottom: 10px;">
            <label for="numeroGuia"><b>Número de guía:</b></label>
            <input 
                type="text"
                id="numeroGuia"
                value="<?= h($numero_guia) ?>"
                readonly
                style="width: 200px; text-align: center; border-radius: 7px; padding: 6px; border: 1px solid #ccc; background: #f5f5f7; font-size:18px; font-weight: 600;"
            >
        </div>

        <?php if ($numero_guia): ?>
            <a href="https://coordinadora.com/rastreo/rastreo-de-guia/detalle-de-rastreo-de-guia/?guia=<?= urlencode($numero_guia) ?>" 
               target="_blank"
               class="guia-boton"
               title="Rastrear guía en Coordinadora">
                <span style="margin-right: 8px; font-size: 22px;">&#128666;</span> <!-- emoji camión -->
                Rastrear envío
            </a>
        <?php else: ?>
            <div style="color: #e75b1e; font-weight: bold; margin-top: 18px;">No hay número de guía registrado.</div>
        <?php endif; ?>
    </div>
</body>
</html>