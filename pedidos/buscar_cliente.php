<?php
require_once 'config_secure.php';
require_once 'ui-helpers.php';

// Procesar búsqueda si se envió
$busqueda = '';
$resultados = [];
$mensaje = '';

if (isset($_POST['buscar']) && !empty($_POST['termino'])) {
    $termino = trim($_POST['termino']);
    $busqueda = $termino;

    // Buscar por diferentes criterios
    $query = "SELECT id, nombre, telefono, correo, ciudad, direccion, fecha, total, estado
              FROM pedidos_detal
              WHERE nombre LIKE ?
                 OR telefono LIKE ?
                 OR correo LIKE ?
                 OR id = ?
              ORDER BY fecha DESC
              LIMIT 20";

    $stmt = $conn->prepare($query);
    $termino_like = "%$termino%";
    $termino_id = is_numeric($termino) ? $termino : 0;
    $stmt->bind_param("sssi", $termino_like, $termino_like, $termino_like, $termino_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $resultados[] = $row;
    }

    if (empty($resultados)) {
        $mensaje = "No se encontraron resultados para: '$termino'";
    } else {
        $mensaje = "Se encontraron " . count($resultados) . " resultado(s) para: '$termino'";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🔍 Buscar Cliente - Sequoia Speed</title>
    <link rel="stylesheet" href="listar_pedidos.css">
    <style>
        .search-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: #21262d;
            border-radius: 12px;
            border: 1px solid #30363d;
        }

        .search-form {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .search-input {
            flex: 1;
            padding: 12px;
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            color: #e6edf3;
            font-size: 16px;
        }

        .search-btn {
            background: #238636;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
        }

        .search-btn:hover {
            background: #2ea043;
        }

        .search-tips {
            background: #0d1117;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
            color: #8b949e;
        }

        .resultado-item {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            transition: all 0.2s;
        }

        .resultado-item:hover {
            border-color: #58a6ff;
            background: rgba(88, 166, 255, 0.05);
        }

        .cliente-nombre {
            font-size: 18px;
            font-weight: 600;
            color: #58a6ff;
            margin-bottom: 8px;
        }

        .cliente-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            font-size: 14px;
        }

        .info-item {
            color: #e6edf3;
        }

        .info-label {
            color: #8b949e;
            font-weight: 500;
        }

        .cliente-acciones {
            margin-top: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-accion {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-ver {
            background: #58a6ff;
            color: white;
        }

        .btn-whatsapp {
            background: #25d366;
            color: white;
        }

        .btn-llamar {
            background: #007bff;
            color: white;
        }

        .mensaje {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .mensaje.info {
            background: rgba(88, 166, 255, 0.1);
            color: #58a6ff;
            border: 1px solid rgba(88, 166, 255, 0.3);
        }

        .mensaje.warning {
            background: rgba(255, 159, 10, 0.1);
            color: #ff9f0a;
            border: 1px solid rgba(255, 159, 10, 0.3);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-lista">
            <h1>🔍 Buscar Cliente</h1>
            <p>Búsqueda rápida por nombre, teléfono, correo o número de pedido</p>
        </div>

        <div class="search-container">
            <form method="POST" class="search-form">
                <input type="text"
                       name="termino"
                       class="search-input"
                       placeholder="Ingresa nombre, teléfono, correo o número de pedido..."
                       value="<?php echo htmlspecialchars($busqueda); ?>"
                       autofocus>
                <button type="submit" name="buscar" class="search-btn">
                    🔍 Buscar
                </button>
            </form>

            <div class="search-tips">
                <strong>💡 Consejos de búsqueda:</strong><br>
                • Nombre: "Juan Pérez" o "María"<br>
                • Teléfono: "300123456" o "3001234567"<br>
                • Correo: "cliente@email.com" o parte del correo<br>
                • Pedido: Número exacto como "1234"
            </div>

            <?php if (!empty($mensaje)): ?>
                <div class="mensaje <?php echo empty($resultados) ? 'warning' : 'info'; ?>">
                    <?php echo $mensaje; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($resultados)): ?>
                <div class="resultados">
                    <?php foreach ($resultados as $cliente): ?>
                        <div class="resultado-item">
                            <div class="cliente-nombre">
                                👤 <?php echo htmlspecialchars($cliente['nombre']); ?>
                                <span style="font-size: 14px; color: #8b949e; font-weight: normal;">
                                    - Pedido #<?php echo $cliente['id']; ?>
                                </span>
                            </div>

                            <div class="cliente-info">
                                <div class="info-item">
                                    <span class="info-label">📞 Teléfono:</span>
                                    <?php echo htmlspecialchars($cliente['telefono']); ?>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📧 Correo:</span>
                                    <?php echo htmlspecialchars($cliente['correo']); ?>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">🏙️ Ciudad:</span>
                                    <?php echo htmlspecialchars($cliente['ciudad']); ?>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📅 Fecha:</span>
                                    <?php echo date('d/m/Y H:i', strtotime($cliente['fecha'])); ?>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">💰 Total:</span>
                                    $<?php echo number_format($cliente['total'], 0, ',', '.'); ?>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">📊 Estado:</span>
                                    <span style="color: #58a6ff;"><?php echo ucfirst($cliente['estado']); ?></span>
                                </div>
                            </div>

                            <div class="cliente-acciones">
                                <a href="ver_detalle_pedido.php?id=<?php echo $cliente['id']; ?>"
                                   class="btn-accion btn-ver">
                                    👁️ Ver Pedido
                                </a>
                                <?php if (!empty($cliente['telefono'])): ?>
                                    <?php
                                    $telefono_whatsapp = preg_replace('/[^0-9]/', '', $cliente['telefono']);
                                    if (strlen($telefono_whatsapp) === 10) {
                                        $telefono_whatsapp = '57' . $telefono_whatsapp;
                                    }
                                    ?>
                                    <a href="https://wa.me/<?php echo $telefono_whatsapp; ?>"
                                       target="_blank"
                                       class="btn-accion btn-whatsapp">
                                        💬 WhatsApp
                                    </a>
                                    <a href="tel:<?php echo $cliente['telefono']; ?>"
                                       class="btn-accion btn-llamar">
                                        📞 Llamar
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="text-align: center; margin: 20px;">
            <a href="index_empleado.html" style="color: #58a6ff; text-decoration: none;">
                ← Volver al Panel de Empleado
            </a>
        </div>
    </div>
</body>
</html>
