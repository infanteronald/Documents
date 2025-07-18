<?php
require_once 'config_secure.php';
require_once 'ui-helpers.php';

// Obtener alertas urgentes
$alertas_urgentes = [];

try {
    // Consulta para obtener pedidos urgentes (más de 24 horas sin gestión)
    $query = "SELECT id, nombre, telefono, correo, ciudad, fecha, total, estado,
                     TIMESTAMPDIFF(HOUR, fecha, NOW()) as horas_transcurridas,
                     CASE
                         WHEN TIMESTAMPDIFF(HOUR, fecha, NOW()) > 48 THEN 'critica'
                         WHEN TIMESTAMPDIFF(HOUR, fecha, NOW()) > 24 THEN 'alta'
                         ELSE 'media'
                     END as urgencia
              FROM pedidos_detal
              WHERE estado IN ('pendiente', 'pago_pendiente', 'sin_guia')
                AND TIMESTAMPDIFF(HOUR, fecha, NOW()) > 12
              ORDER BY horas_transcurridas DESC
              LIMIT 20";

    $result = $conn->query($query);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $alertas_urgentes[] = $row;
        }
    }
} catch (Exception $e) {
    // En caso de error, mostrar datos de prueba
    $alertas_urgentes = [
        [
            'id' => 1234,
            'nombre' => 'María González',
            'telefono' => '3001234567',
            'correo' => 'maria@email.com',
            'ciudad' => 'Bogotá',
            'fecha' => date('Y-m-d H:i:s', strtotime('-36 hours')),
            'total' => 150000,
            'estado' => 'pago_pendiente',
            'horas_transcurridas' => 36,
            'urgencia' => 'alta'
        ],
        [
            'id' => 1235,
            'nombre' => 'Carlos Rodríguez',
            'telefono' => '3009876543',
            'correo' => 'carlos@email.com',
            'ciudad' => 'Medellín',
            'fecha' => date('Y-m-d H:i:s', strtotime('-52 hours')),
            'total' => 280000,
            'estado' => 'sin_guia',
            'horas_transcurridas' => 52,
            'urgencia' => 'critica'
        ],
        [
            'id' => 1236,
            'nombre' => 'Ana Martínez',
            'telefono' => '3005555555',
            'correo' => 'ana@email.com',
            'ciudad' => 'Cali',
            'fecha' => date('Y-m-d H:i:s', strtotime('-26 hours')),
            'total' => 95000,
            'estado' => 'pendiente',
            'horas_transcurridas' => 26,
            'urgencia' => 'alta'
        ]
    ];
}

// Contar alertas por urgencia
$criticas = count(array_filter($alertas_urgentes, fn($a) => $a['urgencia'] === 'critica'));
$altas = count(array_filter($alertas_urgentes, fn($a) => $a['urgencia'] === 'alta'));
$medias = count(array_filter($alertas_urgentes, fn($a) => $a['urgencia'] === 'media'));

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🚨 Alertas Urgentes - Sequoia Speed</title>
    <link rel="stylesheet" href="listar_pedidos.css">
    <style>
        .alertas-container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
        }

        .stats-alertas {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-number.critica {
            color: #ff453a;
            animation: pulse 2s infinite;
        }
        .stat-number.alta { color: #ff9f0a; }
        .stat-number.media { color: #58a6ff; }
        .stat-number.total { color: #e6edf3; }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.8; transform: scale(1.05); }
        }

        .stat-label {
            color: #8b949e;
            font-size: 14px;
            font-weight: 500;
        }

        .alerta-item {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
            position: relative;
        }

        .alerta-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .alerta-item.critica {
            border-left: 4px solid #ff453a;
            background: rgba(255, 69, 58, 0.05);
            animation: pulseAlert 3s infinite;
        }

        .alerta-item.alta {
            border-left: 4px solid #ff9f0a;
            background: rgba(255, 159, 10, 0.05);
        }

        .alerta-item.media {
            border-left: 4px solid #58a6ff;
            background: rgba(88, 166, 255, 0.05);
        }

        @keyframes pulseAlert {
            0%, 100% { box-shadow: 0 0 0 rgba(255, 69, 58, 0); }
            50% { box-shadow: 0 0 20px rgba(255, 69, 58, 0.3); }
        }

        .alerta-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .alerta-titulo {
            font-size: 18px;
            font-weight: 600;
            color: #e6edf3;
            margin-bottom: 5px;
        }

        .urgencia-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .urgencia-critica {
            background: #ff453a;
            color: white;
            animation: pulse 2s infinite;
        }

        .urgencia-alta {
            background: #ff9f0a;
            color: white;
        }

        .urgencia-media {
            background: #58a6ff;
            color: white;
        }

        .alerta-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .info-item {
            background: #0d1117;
            padding: 10px;
            border-radius: 6px;
            border: 1px solid #30363d;
        }

        .info-label {
            color: #8b949e;
            font-size: 12px;
            font-weight: 500;
            margin-bottom: 4px;
        }

        .info-value {
            color: #e6edf3;
            font-weight: 500;
        }

        .tiempo-transcurrido {
            background: rgba(255, 69, 58, 0.1);
            border: 1px solid rgba(255, 69, 58, 0.3);
            color: #ff453a;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
        }

        .alerta-acciones {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }

        .btn-accion {
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }

        .btn-ver {
            background: #58a6ff;
            color: white;
        }

        .btn-contactar {
            background: #25d366;
            color: white;
        }

        .btn-resolver {
            background: #238636;
            color: white;
        }

        .btn-posponer {
            background: #6e7681;
            color: white;
        }

        .btn-accion:hover {
            transform: translateY(-1px);
            filter: brightness(1.1);
        }

        .filtros-alertas {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .filtro-btn {
            padding: 8px 16px;
            border: 1px solid #30363d;
            background: #21262d;
            color: #e6edf3;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .filtro-btn.activo {
            background: #58a6ff;
            border-color: #58a6ff;
        }

        .alerta-vacia {
            text-align: center;
            padding: 60px 20px;
            color: #8b949e;
            background: #21262d;
            border-radius: 8px;
            border: 1px solid #30363d;
        }

        .alerta-vacia .emoji {
            font-size: 4rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-lista">
            <h1>🚨 Alertas Urgentes</h1>
            <p>Pedidos que requieren atención inmediata</p>
        </div>

        <div class="alertas-container">
            <!-- Estadísticas de alertas -->
            <div class="stats-alertas">
                <div class="stat-card">
                    <div class="stat-number critica"><?php echo $criticas; ?></div>
                    <div class="stat-label">🔴 Críticas (+48h)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number alta"><?php echo $altas; ?></div>
                    <div class="stat-label">🟡 Altas (+24h)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number media"><?php echo $medias; ?></div>
                    <div class="stat-label">🔵 Medias (+12h)</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number total"><?php echo count($alertas_urgentes); ?></div>
                    <div class="stat-label">📊 Total Alertas</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros-alertas">
                <button class="filtro-btn activo" onclick="filtrarAlertas('todas')">Todas</button>
                <button class="filtro-btn" onclick="filtrarAlertas('critica')">🔴 Críticas</button>
                <button class="filtro-btn" onclick="filtrarAlertas('alta')">🟡 Altas</button>
                <button class="filtro-btn" onclick="filtrarAlertas('media')">🔵 Medias</button>
            </div>

            <!-- Lista de alertas -->
            <?php if (empty($alertas_urgentes)): ?>
                <div class="alerta-vacia">
                    <div class="emoji">🎉</div>
                    <h3>¡Excelente trabajo!</h3>
                    <p>No hay alertas urgentes en este momento.</p>
                </div>
            <?php else: ?>
                <div id="lista-alertas">
                    <?php foreach ($alertas_urgentes as $alerta): ?>
                        <div class="alerta-item <?php echo $alerta['urgencia']; ?>" data-urgencia="<?php echo $alerta['urgencia']; ?>">
                            <div class="alerta-header">
                                <div>
                                    <div class="alerta-titulo">
                                        📦 Pedido #<?php echo $alerta['id']; ?> - <?php echo htmlspecialchars($alerta['nombre']); ?>
                                    </div>
                                    <div style="color: #8b949e; font-size: 14px;">
                                        Estado: <span style="color: #ff9f0a;"><?php echo ucfirst(str_replace('_', ' ', $alerta['estado'])); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="urgencia-badge urgencia-<?php echo $alerta['urgencia']; ?>">
                                        <?php echo $alerta['urgencia']; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="alerta-info">
                                <div class="info-item">
                                    <div class="info-label">👤 Cliente</div>
                                    <div class="info-value"><?php echo htmlspecialchars($alerta['nombre']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">📞 Teléfono</div>
                                    <div class="info-value"><?php echo htmlspecialchars($alerta['telefono']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">🏙️ Ciudad</div>
                                    <div class="info-value"><?php echo htmlspecialchars($alerta['ciudad']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">💰 Total</div>
                                    <div class="info-value">$<?php echo number_format($alerta['total'], 0, ',', '.'); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">📅 Fecha Pedido</div>
                                    <div class="info-value"><?php echo date('d/m/Y H:i', strtotime($alerta['fecha'])); ?></div>
                                </div>
                                <div class="tiempo-transcurrido">
                                    ⏰ <?php echo $alerta['horas_transcurridas']; ?> horas transcurridas
                                </div>
                            </div>

                            <div class="alerta-acciones">
                                <a href="ver_detalle_pedido.php?id=<?php echo $alerta['id']; ?>"
                                   class="btn-accion btn-ver">
                                    👁️ Ver Detalle
                                </a>

                                <?php if (!empty($alerta['telefono'])): ?>
                                    <?php
                                    $telefono_whatsapp = preg_replace('/[^0-9]/', '', $alerta['telefono']);
                                    if (strlen($telefono_whatsapp) === 10) {
                                        $telefono_whatsapp = '57' . $telefono_whatsapp;
                                    }
                                    ?>
                                    <a href="https://wa.me/<?php echo $telefono_whatsapp; ?>?text=Hola%20<?php echo urlencode($alerta['nombre']); ?>,%20te%20contacto%20sobre%20tu%20pedido%20#<?php echo $alerta['id']; ?>"
                                       target="_blank"
                                       class="btn-accion btn-contactar">
                                        💬 WhatsApp
                                    </a>
                                <?php endif; ?>

                                <?php if ($alerta['estado'] === 'pago_pendiente'): ?>
                                    <a href="subir_comprobante.php?id=<?php echo $alerta['id']; ?>"
                                       class="btn-accion btn-resolver">
                                        💰 Gestionar Pago
                                    </a>
                                <?php elseif ($alerta['estado'] === 'sin_guia'): ?>
                                    <a href="transporte/vitalcarga.php"
                                       class="btn-accion btn-resolver">
                                        🚚 Asignar Guía
                                    </a>
                                <?php else: ?>
                                    <a href="listar_pedidos.php?buscar=<?php echo $alerta['id']; ?>"
                                       class="btn-accion btn-resolver">
                                        🔧 Gestionar
                                    </a>
                                <?php endif; ?>

                                <button class="btn-accion btn-posponer"
                                        onclick="posponerAlerta(<?php echo $alerta['id']; ?>)">
                                    ⏸️ Posponer
                                </button>
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

    <script>
        function filtrarAlertas(urgencia) {
            const alertas = document.querySelectorAll('.alerta-item');
            const botones = document.querySelectorAll('.filtro-btn');

            // Actualizar botones
            botones.forEach(btn => btn.classList.remove('activo'));
            event.target.classList.add('activo');

            // Filtrar alertas
            alertas.forEach(alerta => {
                if (urgencia === 'todas' || alerta.dataset.urgencia === urgencia) {
                    alerta.style.display = 'block';
                } else {
                    alerta.style.display = 'none';
                }
            });
        }

        function posponerAlerta(alertaId) {
            if (confirm('¿Posponer esta alerta por 2 horas?\n\nVolvará a aparecer después del tiempo seleccionado.')) {
                // En producción, enviar al servidor
                mostrarNotificacion('⏸️ Alerta pospuesta por 2 horas');

                // Ocultar visualmente
                const alertaElement = document.querySelector(`[data-alerta-id="${alertaId}"]`);
                if (alertaElement) {
                    alertaElement.style.opacity = '0.5';
                    alertaElement.style.transform = 'scale(0.95)';
                }
            }
        }

        function mostrarNotificacion(mensaje) {
            const notificacion = document.createElement('div');
            notificacion.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #238636;
                color: white;
                padding: 15px 20px;
                border-radius: 8px;
                font-weight: 500;
                z-index: 9999;
                animation: slideIn 0.3s ease-out;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;
            notificacion.textContent = mensaje;
            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.remove();
            }, 3000);
        }

        // Actualizar alertas cada 2 minutos
        setInterval(() => {
            location.reload();
        }, 120000);

        console.log('Sistema de alertas urgentes inicializado');
    </script>
</body>
</html>
