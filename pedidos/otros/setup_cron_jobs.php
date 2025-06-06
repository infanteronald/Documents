<?php
/**
 * Configurador de Cron Jobs para Bold PSE
 * Configura las tareas automáticas del sistema
 */

// Configuraciones de cron jobs
$cronJobs = [
    [
        'name' => 'Bold Retry Processor',
        'description' => 'Procesa webhooks fallidos cada 5 minutos',
        'schedule' => '*/5 * * * *',
        'command' => 'cd ' . __DIR__ . ' && /usr/bin/php bold_retry_processor.php --cron',
        'log_file' => __DIR__ . '/logs/cron_retry.log'
    ],
    [
        'name' => 'Bold Webhook Cleanup',
        'description' => 'Limpia logs antiguos diariamente a las 2 AM',
        'schedule' => '0 2 * * *',
        'command' => 'cd ' . __DIR__ . ' && /usr/bin/php -r "
            // Limpiar logs de más de 30 días
            $logFiles = glob(\"logs/*.log\");
            foreach(\$logFiles as \$file) {
                if(filemtime(\$file) < strtotime(\"-30 days\")) {
                    unlink(\$file);
                }
            }
            echo \"Logs antiguos limpiados: \" . date(\"Y-m-d H:i:s\") . \"\\n\";
        "',
        'log_file' => __DIR__ . '/logs/cron_cleanup.log'
    ],
    [
        'name' => 'Bold Database Maintenance',
        'description' => 'Mantenimiento de base de datos semanal',
        'schedule' => '0 3 * * 0',
        'command' => 'cd ' . __DIR__ . ' && /usr/bin/php database_maintenance.php',
        'log_file' => __DIR__ . '/logs/cron_maintenance.log'
    ]
];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurador de Cron Jobs - Bold PSE</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', sans-serif;
            background: #1e1e1e;
            color: #cccccc;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: #252526;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }
        h1, h2 {
            color: #ffffff;
        }
        .cron-job {
            background: #1e1e1e;
            border-radius: 8px;
            padding: 20px;
            margin: 16px 0;
            border-left: 4px solid #007aff;
        }
        .cron-schedule {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #2d2d30;
            padding: 8px 12px;
            border-radius: 6px;
            display: inline-block;
            margin: 8px 0;
            color: #ffc107;
        }
        .cron-command {
            font-family: 'Monaco', 'Consolas', monospace;
            background: #2d2d30;
            padding: 12px;
            border-radius: 6px;
            margin: 8px 0;
            white-space: pre-wrap;
            word-break: break-all;
            color: #28a745;
        }
        .install-section {
            background: #2d2d30;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        .code-block {
            background: #1e1e1e;
            border: 1px solid #3e3e42;
            border-radius: 6px;
            padding: 16px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 14px;
            color: #cccccc;
            overflow-x: auto;
            margin: 12px 0;
        }
        .copy-btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin: 4px;
        }
        .copy-btn:hover {
            background: #0056d3;
        }
        .warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .info {
            background: rgba(0, 122, 255, 0.2);
            border-left: 4px solid #007aff;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-active { background: #28a745; }
        .status-inactive { background: #dc3545; }
        .status-unknown { background: #ffc107; }
    </style>
</head>
<body>
    <div class="container">
        <h1>⚙️ Configurador de Cron Jobs - Bold PSE</h1>
        
        <div class="info">
            <strong>📋 Instrucciones:</strong><br>
            Este configurador te ayuda a establecer las tareas automáticas necesarias para el correcto funcionamiento del sistema Bold PSE mejorado.
        </div>

        <h2>🕒 Cron Jobs Requeridos</h2>

        <?php foreach ($cronJobs as $index => $job): ?>
        <div class="cron-job">
            <h3>
                <span class="status-indicator status-unknown"></span>
                <?= htmlspecialchars($job['name']) ?>
            </h3>
            <p><?= htmlspecialchars($job['description']) ?></p>
            
            <div>
                <strong>Horario:</strong>
                <div class="cron-schedule"><?= htmlspecialchars($job['schedule']) ?></div>
                <small style="color: #999;">(formato: minuto hora día mes día_semana)</small>
            </div>
            
            <div>
                <strong>Comando:</strong>
                <div class="cron-command"><?= htmlspecialchars($job['command']) ?></div>
            </div>
            
            <div>
                <strong>Log:</strong> <?= htmlspecialchars($job['log_file']) ?>
            </div>
            
            <button class="copy-btn" onclick="copyCronEntry(<?= $index ?>)">
                📋 Copiar Entrada Cron
            </button>
        </div>
        <?php endforeach; ?>

        <div class="install-section">
            <h2>🚀 Instalación Automática</h2>
            
            <div class="warning">
                <strong>⚠️ Importante:</strong> La instalación automática requiere permisos de administrador. Si no tienes acceso al crontab del sistema, copia las entradas manualmente.
            </div>

            <h3>Opción 1: Instalación Manual</h3>
            <p>1. Abre el editor de crontab:</p>
            <div class="code-block">crontab -e</div>
            
            <p>2. Agrega estas líneas al final del archivo:</p>
            <div class="code-block" id="full-crontab">
<?php foreach ($cronJobs as $job): ?>
# <?= $job['name'] ?> - <?= $job['description'] ?>

<?= $job['schedule'] ?> <?= $job['command'] ?> >> <?= $job['log_file'] ?> 2>&1

<?php endforeach; ?>
            </div>
            <button class="copy-btn" onclick="copyText('full-crontab')">📋 Copiar Todo</button>

            <h3>Opción 2: Script de Instalación</h3>
            <p>Ejecuta este comando como administrador:</p>
            <div class="code-block">sudo php <?= __FILE__ ?> --install</div>

            <h3>📊 Verificar Instalación</h3>
            <p>Para verificar que los cron jobs están activos:</p>
            <div class="code-block">crontab -l | grep "bold"</div>
        </div>

        <div class="install-section">
            <h2>📝 Archivos de Mantenimiento</h2>
            <p>El sistema también necesita el siguiente archivo para mantenimiento de base de datos:</p>
            <button class="copy-btn" onclick="createMaintenanceFile()">🔧 Crear Archivo de Mantenimiento</button>
            <div id="maintenance-status"></div>
        </div>

        <div class="install-section">
            <h2>🔍 Estado de Cron Jobs</h2>
            <button class="copy-btn" onclick="checkCronStatus()">🔄 Verificar Estado</button>
            <div id="cron-status"></div>
        </div>
    </div>

    <script>
        const cronJobs = <?= json_encode($cronJobs) ?>;

        function copyCronEntry(index) {
            const job = cronJobs[index];
            const cronEntry = `# ${job.name} - ${job.description}\n${job.schedule} ${job.command} >> ${job.log_file} 2>&1\n`;
            
            navigator.clipboard.writeText(cronEntry).then(() => {
                showNotification('✅ Entrada cron copiada al portapapeles');
            }).catch(err => {
                console.error('Error copiando:', err);
                showNotification('❌ Error copiando al portapapeles');
            });
        }

        function copyText(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                showNotification('✅ Texto copiado al portapapeles');
            }).catch(err => {
                console.error('Error copiando:', err);
                showNotification('❌ Error copiando al portapapeles');
            });
        }

        async function createMaintenanceFile() {
            const statusDiv = document.getElementById('maintenance-status');
            statusDiv.innerHTML = '<p style="color: #ffc107;">⏳ Creando archivo de mantenimiento...</p>';
            
            try {
                const response = await fetch('setup_cron_jobs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_maintenance' })
                });
                
                const result = await response.json();
                if (result.success) {
                    statusDiv.innerHTML = '<p style="color: #28a745;">✅ Archivo de mantenimiento creado correctamente</p>';
                } else {
                    statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error: ' + result.error + '</p>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error creando archivo: ' + error.message + '</p>';
            }
        }

        async function checkCronStatus() {
            const statusDiv = document.getElementById('cron-status');
            statusDiv.innerHTML = '<p style="color: #ffc107;">⏳ Verificando estado de cron jobs...</p>';
            
            try {
                const response = await fetch('setup_cron_jobs.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'check_status' })
                });
                
                const result = await response.json();
                if (result.success) {
                    let html = '<div style="background: #1e1e1e; padding: 16px; border-radius: 8px; margin-top: 12px;">';
                    html += '<h4>Estado actual de cron jobs:</h4>';
                    
                    result.data.forEach(job => {
                        const statusClass = job.active ? 'status-active' : 'status-inactive';
                        html += `<div style="margin: 8px 0;"><span class="status-indicator ${statusClass}"></span>${job.name}: ${job.active ? 'Activo' : 'Inactivo'}</div>`;
                    });
                    
                    html += '</div>';
                    statusDiv.innerHTML = html;
                } else {
                    statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error verificando estado: ' + result.error + '</p>';
                }
            } catch (error) {
                statusDiv.innerHTML = '<p style="color: #dc3545;">❌ Error verificando estado: ' + error.message + '</p>';
            }
        }

        function showNotification(message) {
            // Crear notificación temporal
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed; top: 20px; right: 20px; z-index: 1000;
                background: #007aff; color: white; padding: 12px 20px;
                border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                font-size: 14px; max-width: 300px;
            `;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>

<?php
// Manejo de comandos CLI
if (php_sapi_name() === 'cli') {
    if (isset($argv[1]) && $argv[1] === '--install') {
        installCronJobs();
    }
}

// Manejo de requests AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    switch ($input['action'] ?? '') {
        case 'create_maintenance':
            echo json_encode(createMaintenanceFile());
            break;
        case 'check_status':
            echo json_encode(checkCronJobsStatus());
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
    }
    exit;
}

function installCronJobs() {
    global $cronJobs;
    
    echo "🚀 Instalando cron jobs para Bold PSE...\n";
    
    // Obtener crontab actual
    $currentCrontab = shell_exec('crontab -l 2>/dev/null') ?: '';
    $newCrontab = $currentCrontab;
    
    foreach ($cronJobs as $job) {
        $cronLine = "{$job['schedule']} {$job['command']} >> {$job['log_file']} 2>&1";
        
        // Verificar si ya existe
        if (strpos($currentCrontab, $job['name']) === false) {
            $newCrontab .= "\n# {$job['name']} - {$job['description']}\n";
            $newCrontab .= "$cronLine\n";
            echo "✅ Agregado: {$job['name']}\n";
        } else {
            echo "⚠️  Ya existe: {$job['name']}\n";
        }
    }
    
    // Aplicar nuevo crontab
    if ($newCrontab !== $currentCrontab) {
        $tmpFile = tempnam(sys_get_temp_dir(), 'crontab_');
        file_put_contents($tmpFile, $newCrontab);
        
        $result = shell_exec("crontab $tmpFile 2>&1");
        unlink($tmpFile);
        
        if ($result) {
            echo "❌ Error instalando crontab: $result\n";
        } else {
            echo "✅ Cron jobs instalados correctamente\n";
        }
    } else {
        echo "✅ Todos los cron jobs ya estaban instalados\n";
    }
}

function createMaintenanceFile() {
    $maintenanceScript = '<?php
/**
 * Script de mantenimiento de base de datos para Bold PSE
 */

require_once "conexion.php";

echo "🔧 Iniciando mantenimiento de base de datos: " . date("Y-m-d H:i:s") . "\n";

try {
    // Limpiar registros antiguos de retry queue (más de 7 días)
    $conn->query("DELETE FROM bold_retry_queue WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $deleted = $conn->affected_rows;
    echo "🗑️  Eliminados $deleted registros antiguos de retry queue\n";
    
    // Limpiar logs de webhook antiguos (más de 30 días)
    $conn->query("DELETE FROM bold_webhook_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $deleted = $conn->affected_rows;
    echo "🗑️  Eliminados $deleted logs de webhook antiguos\n";
    
    // Limpiar logs de notificaciones antiguas (más de 60 días)
    $conn->query("DELETE FROM notification_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
    $deleted = $conn->affected_rows;
    echo "🗑️  Eliminados $deleted logs de notificaciones antiguos\n";
    
    // Optimizar tablas
    $tables = ["pedidos_detal", "bold_retry_queue", "bold_webhook_logs", "notification_logs"];
    foreach ($tables as $table) {
        $conn->query("OPTIMIZE TABLE $table");
        echo "⚡ Optimizada tabla $table\n";
    }
    
    echo "✅ Mantenimiento completado: " . date("Y-m-d H:i:s") . "\n";
    
} catch (Exception $e) {
    echo "❌ Error en mantenimiento: " . $e->getMessage() . "\n";
}
?>';
    
    $filename = __DIR__ . '/database_maintenance.php';
    $success = file_put_contents($filename, $maintenanceScript);
    
    if ($success) {
        chmod($filename, 0755);
        return ['success' => true, 'message' => 'Archivo de mantenimiento creado'];
    } else {
        return ['success' => false, 'error' => 'No se pudo crear el archivo'];
    }
}

function checkCronJobsStatus() {
    global $cronJobs;
    
    $currentCrontab = shell_exec('crontab -l 2>/dev/null') ?: '';
    $status = [];
    
    foreach ($cronJobs as $job) {
        $status[] = [
            'name' => $job['name'],
            'active' => strpos($currentCrontab, $job['name']) !== false
        ];
    }
    
    return ['success' => true, 'data' => $status];
}
?>
