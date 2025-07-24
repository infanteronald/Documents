<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config_secure.php';
require_once '../ui-helpers.php';
require_once '../php82_helpers.php';

// Obtener progreso de entregas del día
function obtenerProgresoEntregas($conn) {
    $fecha_hoy = date('Y-m-d');
    
    $query = "SELECT 
        COUNT(*) as total_dia,
        SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados,
        SUM(CASE WHEN estado_entrega = 'en_ruta' THEN 1 ELSE 0 END) as en_ruta,
        SUM(CASE WHEN estado_entrega = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado_entrega = 'reintento' THEN 1 ELSE 0 END) as reintento,
        SUM(CASE WHEN estado_entrega = 'devuelto' THEN 1 ELSE 0 END) as devueltos
        FROM pedidos_detal 
        WHERE DATE(fecha) = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progreso = $result->fetch_assoc();
    
    // Calcular porcentajes
    $total = (int)$progreso['total_dia'];
    $entregados = (int)$progreso['entregados'];
    $en_ruta = (int)$progreso['en_ruta'];
    $pendientes = (int)$progreso['pendientes'];
    $reintento = (int)$progreso['reintento'];
    $devueltos = (int)$progreso['devueltos'];
    
    return [
        'total' => $total,
        'entregados' => $entregados,
        'en_ruta' => $en_ruta,
        'pendientes' => $pendientes,
        'reintento' => $reintento,
        'devueltos' => $devueltos,
        'porcentaje_entregados' => $total > 0 ? round(($entregados / $total) * 100, 1) : 0,
        'porcentaje_en_ruta' => $total > 0 ? round(($en_ruta / $total) * 100, 1) : 0,
        'porcentaje_pendientes' => $total > 0 ? round(($pendientes / $total) * 100, 1) : 0
    ];
}

// Obtener progreso por horas del día
function obtenerProgresoHorario($conn) {
    $fecha_hoy = date('Y-m-d');
    
    $query = "SELECT 
        HOUR(fecha) as hora,
        COUNT(*) as total_hora,
        SUM(CASE WHEN estado_entrega = 'entregado' THEN 1 ELSE 0 END) as entregados_hora
        FROM pedidos_detal 
        WHERE DATE(fecha) = ?
        GROUP BY HOUR(fecha)
        ORDER BY hora";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $fecha_hoy);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $progreso_horario = [];
    while ($row = $result->fetch_assoc()) {
        $progreso_horario[] = [
            'hora' => (int)$row['hora'],
            'total' => (int)$row['total_hora'],
            'entregados' => (int)$row['entregados_hora']
        ];
    }
    
    return $progreso_horario;
}

// Si es una petición AJAX, devolver JSON
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json');
    
    $progreso = obtenerProgresoEntregas($conn);
    $progreso_horario = obtenerProgresoHorario($conn);
    
    echo json_encode([
        'success' => true,
        'progreso' => $progreso,
        'progreso_horario' => $progreso_horario,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Obtener datos para mostrar en la página
$progreso = obtenerProgresoEntregas($conn);
$progreso_horario = obtenerProgresoHorario($conn);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Progreso de Entregas - VitalCarga</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <link rel="stylesheet" href="../listar_pedidos.css">
    
    <style>
        .progreso-container {
            padding: 20px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .progreso-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .progreso-titulo {
            color: #58a6ff;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .progreso-fecha {
            color: #8b949e;
            font-size: 1rem;
        }
        
        .progreso-general {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .progreso-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 15px;
            background: #0d1117;
            border-radius: 6px;
            border: 1px solid #30363d;
        }
        
        .stat-numero {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: #8b949e;
        }
        
        .entregados { color: #28a745; }
        .en-ruta { color: #007bff; }
        .pendientes { color: #ffc107; }
        .reintento { color: #fd7e14; }
        .devueltos { color: #dc3545; }
        
        .barra-progreso-principal {
            width: 100%;
            height: 40px;
            background: #30363d;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            margin-top: 20px;
        }
        
        .barra-fill {
            height: 100%;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .barra-entregados {
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .progreso-horario {
            background: #21262d;
            border: 1px solid #30363d;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .progreso-horario-titulo {
            color: #e6edf3;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .grafico-horario {
            display: flex;
            align-items: end;
            gap: 8px;
            height: 200px;
            padding: 10px;
            border-bottom: 1px solid #30363d;
        }
        
        .barra-hora {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }
        
        .barra-visual-hora {
            background: linear-gradient(to top, #58a6ff, #79c0ff);
            border-radius: 3px;
            min-height: 10px;
            width: 100%;
            transition: height 0.3s ease;
            position: relative;
            cursor: pointer;
        }
        
        .barra-visual-hora:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #0d1117;
            color: #e6edf3;
            padding: 5px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            white-space: nowrap;
            z-index: 1000;
        }
        
        .hora-label {
            font-size: 0.7rem;
            color: #8b949e;
            writing-mode: vertical-lr;
            text-orientation: mixed;
        }
        
        .controles-progreso {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .btn-control {
            background: #238636;
            color: white;
            border: 1px solid #238636;
            border-radius: 6px;
            padding: 10px 20px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-control:hover {
            background: #2ea043;
            transform: translateY(-1px);
        }
        
        .btn-secondary {
            background: #21262d;
            color: #e6edf3;
            border: 1px solid #30363d;
        }
        
        .btn-secondary:hover {
            background: #30363d;
        }
        
        .ultima-actualizacion {
            text-align: center;
            color: #8b949e;
            font-size: 0.8rem;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .progreso-container {
                padding: 10px;
            }
            
            .progreso-titulo {
                font-size: 1.5rem;
            }
            
            .progreso-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            
            .stat-numero {
                font-size: 1.5rem;
            }
            
            .grafico-horario {
                height: 150px;
                gap: 4px;
            }
            
            .controles-progreso {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<div class="progreso-container">
    <div class="progreso-header">
        <h1 class="progreso-titulo">📊 Progreso de Entregas</h1>
        <p class="progreso-fecha">Fecha: <?php echo date('d/m/Y'); ?></p>
    </div>
    
    <!-- Progreso General -->
    <div class="progreso-general">
        <div class="progreso-stats">
            <div class="stat-item">
                <div class="stat-numero"><?php echo $progreso['total']; ?></div>
                <div class="stat-label">Total del Día</div>
            </div>
            <div class="stat-item">
                <div class="stat-numero entregados"><?php echo $progreso['entregados']; ?></div>
                <div class="stat-label">Entregados</div>
            </div>
            <div class="stat-item">
                <div class="stat-numero en-ruta"><?php echo $progreso['en_ruta']; ?></div>
                <div class="stat-label">En Ruta</div>
            </div>
            <div class="stat-item">
                <div class="stat-numero pendientes"><?php echo $progreso['pendientes']; ?></div>
                <div class="stat-label">Pendientes</div>
            </div>
            <div class="stat-item">
                <div class="stat-numero reintento"><?php echo $progreso['reintento']; ?></div>
                <div class="stat-label">Reintento</div>
            </div>
            <div class="stat-item">
                <div class="stat-numero devueltos"><?php echo $progreso['devueltos']; ?></div>
                <div class="stat-label">Devueltos</div>
            </div>
        </div>
        
        <div class="barra-progreso-principal">
            <div class="barra-fill barra-entregados" style="width: <?php echo $progreso['porcentaje_entregados']; ?>%">
                <?php echo $progreso['porcentaje_entregados']; ?>% Completado
            </div>
        </div>
    </div>
    
    <!-- Progreso Horario -->
    <div class="progreso-horario">
        <h3 class="progreso-horario-titulo">📈 Distribución por Horas</h3>
        <div class="grafico-horario">
            <?php 
            $max_hora = 0;
            foreach ($progreso_horario as $hora_data) {
                if ($hora_data['total'] > $max_hora) {
                    $max_hora = $hora_data['total'];
                }
            }
            
            // Crear array de 24 horas
            $horas_completas = [];
            for ($h = 0; $h < 24; $h++) {
                $horas_completas[$h] = ['hora' => $h, 'total' => 0, 'entregados' => 0];
            }
            
            // Llenar con datos reales
            foreach ($progreso_horario as $hora_data) {
                $horas_completas[$hora_data['hora']] = $hora_data;
            }
            
            foreach ($horas_completas as $hora_data):
                $altura = $max_hora > 0 ? ($hora_data['total'] / $max_hora) * 100 : 5;
                $tooltip = "Hora: {$hora_data['hora']}:00 - Total: {$hora_data['total']} - Entregados: {$hora_data['entregados']}";
            ?>
                <div class="barra-hora">
                    <div class="barra-visual-hora" 
                         style="height: <?php echo $altura; ?>%" 
                         data-tooltip="<?php echo $tooltip; ?>">
                    </div>
                    <div class="hora-label"><?php echo sprintf('%02d:00', $hora_data['hora']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Controles -->
    <div class="controles-progreso">
        <a href="vitalcarga.php" class="btn-control">
            🚚 Gestión de Guías
        </a>
        <a href="dashboard_transportista.php" class="btn-control">
            📊 Dashboard
        </a>
        <button onclick="actualizarProgreso()" class="btn-control btn-secondary">
            🔄 Actualizar
        </button>
        <button onclick="window.print()" class="btn-control btn-secondary">
            🖨️ Imprimir
        </button>
    </div>
    
    <div class="ultima-actualizacion">
        Última actualización: <span id="ultima-actualizacion"><?php echo date('H:i:s'); ?></span>
    </div>
</div>

<script>
// Función para actualizar el progreso
function actualizarProgreso() {
    fetch('progreso_entregas.php?ajax=1')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Actualizar números
            document.querySelector('.stat-numero').textContent = data.progreso.total;
            document.querySelector('.stat-numero.entregados').textContent = data.progreso.entregados;
            document.querySelector('.stat-numero.en-ruta').textContent = data.progreso.en_ruta;
            document.querySelector('.stat-numero.pendientes').textContent = data.progreso.pendientes;
            document.querySelector('.stat-numero.reintento').textContent = data.progreso.reintento;
            document.querySelector('.stat-numero.devueltos').textContent = data.progreso.devueltos;
            
            // Actualizar barra de progreso
            const barraProgreso = document.querySelector('.barra-fill');
            barraProgreso.style.width = data.progreso.porcentaje_entregados + '%';
            barraProgreso.textContent = data.progreso.porcentaje_entregados + '% Completado';
            
            // Actualizar timestamp
            document.getElementById('ultima-actualizacion').textContent = new Date().toLocaleTimeString();
        }
    })
    .catch(error => {
        console.error('Error actualizando progreso:', error);
    });
}

// Actualizar automáticamente cada 30 segundos
setInterval(actualizarProgreso, 30000);

// Mostrar tooltip en hover para móviles
document.addEventListener('DOMContentLoaded', function() {
    const barras = document.querySelectorAll('.barra-visual-hora');
    barras.forEach(barra => {
        barra.addEventListener('click', function() {
            const tooltip = this.getAttribute('data-tooltip');
            alert(tooltip);
        });
    });
});
</script>
</body>
</html>