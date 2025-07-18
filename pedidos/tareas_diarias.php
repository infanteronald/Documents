<?php
require_once 'config_secure.php';
require_once 'ui-helpers.php';

// Obtener tareas del día
$tareas_hoy = [
    [
        'id' => 1,
        'tipo' => 'pago_pendiente',
        'titulo' => 'Confirmar pago pendiente',
        'descripcion' => 'Pedido #1234 - Cliente envió comprobante hace 2 horas',
        'prioridad' => 'alta',
        'tiempo' => '2h',
        'accion' => 'subir_comprobante.php?id=1234'
    ],
    [
        'id' => 2,
        'tipo' => 'sin_guia',
        'titulo' => 'Asignar guía de envío',
        'descripcion' => 'Pedido #1235 - Pago confirmado, listo para envío',
        'prioridad' => 'media',
        'tiempo' => '4h',
        'accion' => 'transporte/vitalcarga.php'
    ],
    [
        'id' => 3,
        'tipo' => 'cliente_llamada',
        'titulo' => 'Cliente solicitó información',
        'descripcion' => 'María González - Pregunta sobre estado de entrega',
        'prioridad' => 'alta',
        'tiempo' => '30m',
        'accion' => 'tel:3001234567'
    ],
    [
        'id' => 4,
        'tipo' => 'restock',
        'titulo' => 'Producto agotado',
        'descripcion' => 'Verificar disponibilidad de "Producto X"',
        'prioridad' => 'baja',
        'tiempo' => '1h',
        'accion' => 'productos_por_categoria.php'
    ]
];

// Marcar tarea como completada
if (isset($_POST['completar_tarea'])) {
    $tarea_id = (int)$_POST['tarea_id'];
    // En producción, actualizar en base de datos
    echo json_encode(['success' => true]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>✅ Mis Tareas del Día - Sequoia Speed</title>
    <link rel="stylesheet" href="listar_pedidos.css">
    <style>
        .tareas-container {
            max-width: 900px;
            margin: 20px auto;
            padding: 20px;
        }

        .stats-tareas {
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
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-number.alta { color: #ff453a; }
        .stat-number.media { color: #ff9f0a; }
        .stat-number.baja { color: #30d158; }
        .stat-number.total { color: #58a6ff; }

        .stat-label {
            color: #8b949e;
            font-size: 14px;
        }

        .tarea-item {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.2s;
            position: relative;
        }

        .tarea-item:hover {
            border-color: #58a6ff;
            background: rgba(88, 166, 255, 0.05);
        }

        .tarea-item.alta {
            border-left: 4px solid #ff453a;
        }

        .tarea-item.media {
            border-left: 4px solid #ff9f0a;
        }

        .tarea-item.baja {
            border-left: 4px solid #30d158;
        }

        .tarea-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .tarea-titulo {
            font-size: 16px;
            font-weight: 600;
            color: #e6edf3;
            margin-bottom: 5px;
        }

        .tarea-tiempo {
            background: rgba(88, 166, 255, 0.1);
            color: #58a6ff;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .tarea-descripcion {
            color: #8b949e;
            margin-bottom: 15px;
            line-height: 1.4;
        }

        .tarea-acciones {
            display: flex;
            gap: 10px;
            align-items: center;
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

        .btn-realizar {
            background: #238636;
            color: white;
        }

        .btn-realizar:hover {
            background: #2ea043;
        }

        .btn-completar {
            background: #58a6ff;
            color: white;
        }

        .btn-completar:hover {
            background: #79c0ff;
        }

        .btn-posponer {
            background: #6e7681;
            color: white;
        }

        .prioridad-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .prioridad-alta {
            background: rgba(255, 69, 58, 0.2);
            color: #ff453a;
        }

        .prioridad-media {
            background: rgba(255, 159, 10, 0.2);
            color: #ff9f0a;
        }

        .prioridad-baja {
            background: rgba(48, 209, 88, 0.2);
            color: #30d158;
        }

        .filtros-tareas {
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

        .mensaje-vacio {
            text-align: center;
            padding: 40px;
            color: #8b949e;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-lista">
            <h1>✅ Mis Tareas del Día</h1>
            <p>Lista personalizada de pendientes y acciones importantes</p>
        </div>

        <div class="tareas-container">
            <!-- Estadísticas de tareas -->
            <div class="stats-tareas">
                <div class="stat-card">
                    <div class="stat-number alta" id="stat-alta">2</div>
                    <div class="stat-label">🔴 Alta Prioridad</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number media" id="stat-media">1</div>
                    <div class="stat-label">🟡 Media Prioridad</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number baja" id="stat-baja">1</div>
                    <div class="stat-label">🟢 Baja Prioridad</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number total" id="stat-total">4</div>
                    <div class="stat-label">📋 Total Tareas</div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filtros-tareas">
                <button class="filtro-btn activo" onclick="filtrarTareas('todas')">Todas</button>
                <button class="filtro-btn" onclick="filtrarTareas('alta')">Alta Prioridad</button>
                <button class="filtro-btn" onclick="filtrarTareas('media')">Media Prioridad</button>
                <button class="filtro-btn" onclick="filtrarTareas('baja')">Baja Prioridad</button>
            </div>

            <!-- Lista de tareas -->
            <div id="lista-tareas">
                <?php foreach ($tareas_hoy as $tarea): ?>
                    <div class="tarea-item <?php echo $tarea['prioridad']; ?>" data-prioridad="<?php echo $tarea['prioridad']; ?>">
                        <div class="tarea-header">
                            <div>
                                <div class="tarea-titulo">
                                    <?php echo getTipoIcon($tarea['tipo']); ?>
                                    <?php echo htmlspecialchars($tarea['titulo']); ?>
                                    <span class="prioridad-badge prioridad-<?php echo $tarea['prioridad']; ?>">
                                        <?php echo $tarea['prioridad']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="tarea-tiempo">
                                ⏰ Hace <?php echo $tarea['tiempo']; ?>
                            </div>
                        </div>

                        <div class="tarea-descripcion">
                            <?php echo htmlspecialchars($tarea['descripcion']); ?>
                        </div>

                        <div class="tarea-acciones">
                            <a href="<?php echo $tarea['accion']; ?>" class="btn-accion btn-realizar">
                                🚀 Realizar Acción
                            </a>
                            <button class="btn-accion btn-completar" onclick="completarTarea(<?php echo $tarea['id']; ?>)">
                                ✅ Marcar Completada
                            </button>
                            <button class="btn-accion btn-posponer" onclick="posponerTarea(<?php echo $tarea['id']; ?>)">
                                ⏸️ Posponer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="text-align: center; margin: 20px;">
            <a href="index_empleado.html" style="color: #58a6ff; text-decoration: none;">
                ← Volver al Panel de Empleado
            </a>
        </div>
    </div>

    <script>
        function filtrarTareas(prioridad) {
            const tareas = document.querySelectorAll('.tarea-item');
            const botones = document.querySelectorAll('.filtro-btn');

            // Actualizar botones
            botones.forEach(btn => btn.classList.remove('activo'));
            event.target.classList.add('activo');

            // Filtrar tareas
            tareas.forEach(tarea => {
                if (prioridad === 'todas' || tarea.dataset.prioridad === prioridad) {
                    tarea.style.display = 'block';
                } else {
                    tarea.style.display = 'none';
                }
            });
        }

        function completarTarea(tareaId) {
            if (confirm('¿Marcar esta tarea como completada?')) {
                // Enviar al servidor
                fetch('tareas_diarias.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `completar_tarea=1&tarea_id=${tareaId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Remover visualmente
                        const tareaElement = document.querySelector(`[data-tarea-id="${tareaId}"]`);
                        if (tareaElement) {
                            tareaElement.style.opacity = '0.5';
                            tareaElement.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                tareaElement.remove();
                                actualizarContadores();
                            }, 300);
                        }
                        mostrarNotificacion('✅ Tarea completada exitosamente');
                    }
                });
            }
        }

        function posponerTarea(tareaId) {
            if (confirm('¿Posponer esta tarea para más tarde?')) {
                // Aquí implementarías la lógica de posponer
                mostrarNotificacion('⏸️ Tarea pospuesta para más tarde');
            }
        }

        function actualizarContadores() {
            const tareas = document.querySelectorAll('.tarea-item');
            const alta = document.querySelectorAll('.tarea-item.alta').length;
            const media = document.querySelectorAll('.tarea-item.media').length;
            const baja = document.querySelectorAll('.tarea-item.baja').length;

            document.getElementById('stat-alta').textContent = alta;
            document.getElementById('stat-media').textContent = media;
            document.getElementById('stat-baja').textContent = baja;
            document.getElementById('stat-total').textContent = tareas.length;
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
            `;
            notificacion.textContent = mensaje;
            document.body.appendChild(notificacion);

            setTimeout(() => {
                notificacion.remove();
            }, 3000);
        }
    </script>
</body>
</html>

<?php
function getTipoIcon($tipo) {
    switch ($tipo) {
        case 'pago_pendiente': return '💰';
        case 'sin_guia': return '📦';
        case 'cliente_llamada': return '📞';
        case 'restock': return '📋';
        default: return '📌';
    }
}
?>
