<?php
/**
 * Script de Migración Local - Bold PSE
 * Versión para desarrollo local que no requiere conexión a BD remota
 */

// Configuración de migración
define('MIGRATION_LOG_FILE', __DIR__ . '/logs/migration_local.log');
define('BACKUP_DIR', __DIR__ . '/backups');

// Asegurar directorios
foreach (['/logs', '/backups'] as $dir) {
    if (!is_dir(__DIR__ . $dir)) {
        mkdir(__DIR__ . $dir, 0755, true);
    }
}

/**
 * Clase para manejar la migración local
 */
class BoldLocalMigrationManager {
    private $migrationLogs = [];
    
    /**
     * Ejecutar validación y preparación para migración
     */
    public function prepareForMigration() {
        $this->log("🚀 Preparando sistema para migración Bold PSE");
        
        try {
            // Paso 1: Crear backup de archivos
            $this->log("📋 Paso 1: Creando backup de archivos");
            $this->createFileBackup();
            
            // Paso 2: Verificar archivos mejorados
            $this->log("🔍 Paso 2: Verificando archivos mejorados");
            $this->checkEnhancedFiles();
            
            // Paso 3: Validar sintaxis PHP
            $this->log("🔍 Paso 3: Validando sintaxis PHP");
            $this->validatePHPSyntax();
            
            // Paso 4: Preparar archivos de producción
            $this->log("📦 Paso 4: Preparando archivos de producción");
            $this->prepareProductionFiles();
            
            // Paso 5: Generar instrucciones de despliegue
            $this->log("📝 Paso 5: Generando instrucciones de despliegue");
            $this->generateDeploymentInstructions();
            
            $this->log("✅ Preparación completada exitosamente");
            return $this->generatePreparationReport();
            
        } catch (Exception $e) {
            $this->log("❌ Error en preparación: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Crear backup de archivos críticos
     */
    private function createFileBackup() {
        $backupId = date('Y-m-d_H-i-s');
        $backupPath = BACKUP_DIR . "/backup_files_$backupId";
        mkdir($backupPath, 0755, true);
        
        // Archivos a respaldar
        $filesToBackup = [
            'bold_webhook.php',
            'bold_hash.php', 
            'bold_confirmation.php',
            'index.php',
            'conexion.php'
        ];
        
        foreach ($filesToBackup as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                copy(__DIR__ . '/' . $file, $backupPath . '/' . $file);
                $this->log("  ✅ Backup creado: $file");
            } else {
                $this->log("  ⚠️ Archivo no encontrado: $file");
            }
        }
        
        $this->log("📦 Backup de archivos guardado en: $backupPath");
        return $backupPath;
    }
    
    /**
     * Verificar que los archivos mejorados estén presentes
     */
    private function checkEnhancedFiles() {
        $requiredFiles = [
            'bold_webhook_enhanced.php' => 'Webhook mejorado con retry logic',
            'bold_notification_system.php' => 'Sistema de notificaciones',
            'payment_ux_enhanced.js' => 'Mejoras de experiencia de usuario',
            'payment_ux_enhanced.css' => 'Estilos para UX mejorada',
            'setup_enhanced_webhooks.php' => 'Script de configuración de BD',
            'bold_webhook_monitor.php' => 'Monitor en tiempo real',
            'bold_retry_processor.php' => 'Procesador de retry',
            'test_system_integration.php' => 'Sistema de pruebas',
            'setup_cron_jobs.php' => 'Configurador de cron jobs'
        ];
        
        $missing = [];
        foreach ($requiredFiles as $file => $description) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $this->log("  ✅ $file ($description)");
            } else {
                $missing[] = $file;
                $this->log("  ❌ FALTANTE: $file ($description)");
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Archivos faltantes: " . implode(', ', $missing));
        }
    }
    
    /**
     * Validar sintaxis PHP de archivos críticos
     */
    private function validatePHPSyntax() {
        $filesToValidate = [
            'bold_webhook_enhanced.php',
            'bold_notification_system.php',
            'setup_enhanced_webhooks.php',
            'bold_webhook_monitor.php',
            'bold_retry_processor.php'
        ];
        
        foreach ($filesToValidate as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $output = [];
                $returnCode = 0;
                exec("php -l " . escapeshellarg(__DIR__ . '/' . $file) . " 2>&1", $output, $returnCode);
                
                if ($returnCode === 0) {
                    $this->log("  ✅ Sintaxis PHP válida: $file");
                } else {
                    $this->log("  ❌ Error de sintaxis en $file: " . implode("\n", $output));
                    throw new Exception("Error de sintaxis en $file");
                }
            }
        }
    }
    
    /**
     * Preparar archivos para producción
     */
    private function prepareProductionFiles() {
        // Crear paquete de despliegue
        $deploymentPath = __DIR__ . '/deployment_package';
        if (!is_dir($deploymentPath)) {
            mkdir($deploymentPath, 0755, true);
        }
        
        // Archivos del sistema mejorado
        $filesToDeploy = [
            'bold_webhook_enhanced.php',
            'bold_notification_system.php',
            'payment_ux_enhanced.js',
            'payment_ux_enhanced.css',
            'setup_enhanced_webhooks.php',
            'bold_webhook_monitor.php',
            'bold_retry_processor.php',
            'test_system_integration.php',
            'setup_cron_jobs.php'
        ];
        
        foreach ($filesToDeploy as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                copy(__DIR__ . '/' . $file, $deploymentPath . '/' . $file);
                $this->log("  ✅ Preparado para despliegue: $file");
            }
        }
        
        // Crear script de instalación
        $this->createInstallationScript($deploymentPath);
        
        $this->log("📦 Paquete de despliegue creado en: $deploymentPath");
    }
    
    /**
     * Crear script de instalación para producción
     */
    private function createInstallationScript($deploymentPath) {
        $installScript = '#!/bin/bash
# Script de Instalación - Bold PSE Mejorado
# Ejecutar en el servidor de producción

echo "🚀 Iniciando instalación del sistema Bold PSE mejorado..."

# Crear backup de archivos actuales
BACKUP_DIR="./backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p $BACKUP_DIR
echo "📋 Creando backup en $BACKUP_DIR..."

# Backup de archivos críticos
cp bold_webhook.php $BACKUP_DIR/ 2>/dev/null || echo "  ⚠️ bold_webhook.php no encontrado"
cp index.php $BACKUP_DIR/ 2>/dev/null || echo "  ⚠️ index.php no encontrado"

# Instalar archivos nuevos
echo "📦 Instalando archivos del sistema mejorado..."
chmod +x *.php
chmod +x *.js
chmod +x *.css

# Ejecutar configuración de base de datos
echo "🗄️ Configurando base de datos..."
php setup_enhanced_webhooks.php

# Configurar permisos
chmod 755 logs/
chmod 755 backups/

echo "✅ Instalación completada"
echo "📝 Siguiente paso: Ejecutar test_system_integration.php para validar"
echo "🔧 Luego configurar cron jobs con setup_cron_jobs.php"
';
        
        file_put_contents($deploymentPath . '/install.sh', $installScript);
        chmod($deploymentPath . '/install.sh', 0755);
        $this->log("  ✅ Script de instalación creado");
    }
    
    /**
     * Generar instrucciones detalladas de despliegue
     */
    private function generateDeploymentInstructions() {
        $instructions = '# Instrucciones de Despliegue - Bold PSE Mejorado

## 📋 Pre-requisitos
- Acceso SSH al servidor de producción
- Permisos de escritura en el directorio web
- Acceso a la base de datos MySQL
- PHP 7.4 o superior

## 🚀 Proceso de Despliegue

### Paso 1: Subir Archivos
```bash
# Subir paquete de despliegue al servidor
scp -r deployment_package/* usuario@servidor:/ruta/del/sitio/
```

### Paso 2: Ejecutar Instalación
```bash
# En el servidor
cd /ruta/del/sitio/
chmod +x install.sh
./install.sh
```

### Paso 3: Configurar Base de Datos
```bash
# Ejecutar setup de BD
php setup_enhanced_webhooks.php
```

### Paso 4: Probar Sistema
```bash
# Ejecutar pruebas
php test_system_integration.php
```

### Paso 5: Configurar Cron Jobs
```bash
# Acceder a configurador web
# URL: https://dominio.com/setup_cron_jobs.php
```

### Paso 6: Configurar Bold Dashboard
1. Ir a Bold Dashboard > Webhooks
2. Cambiar URL temporalmente a: `/bold_webhook_distributor.php`
3. Monitorear logs en `/logs/dual_mode.log`
4. Aumentar gradualmente el porcentaje de tráfico
5. Una vez estable al 100%, cambiar URL a `/bold_webhook_enhanced.php`

## 🔍 Verificación Post-Despliegue

### Verificar Tablas de BD
- `bold_retry_queue`: Cola de retry para webhooks fallidos
- `bold_webhook_logs`: Logs detallados de webhooks
- `notification_logs`: Registro de notificaciones enviadas

### Verificar Archivos de Log
- `logs/webhook_enhanced.log`: Logs del webhook mejorado
- `logs/webhook_errors.log`: Logs de errores
- `logs/dual_mode.log`: Logs del modo dual (migración)

### Verificar Funcionalidades
- Monitor en tiempo real: `/bold_webhook_monitor.php`
- Procesador de retry: `/bold_retry_processor.php`
- Sistema de pruebas: `/test_system_integration.php`

## 🔧 Configuración de Cron Jobs
```bash
# Procesador de retry cada 5 minutos
*/5 * * * * /usr/bin/php /ruta/del/sitio/bold_retry_processor.php

# Limpieza de logs diaria a las 2 AM
0 2 * * * find /ruta/del/sitio/logs/ -name "*.log" -mtime +7 -delete
```

## 📊 Monitoreo
- Dashboard: `/bold_webhook_monitor.php`
- Logs en tiempo real: `tail -f logs/webhook_enhanced.log`
- Estadísticas de BD: Vista `bold_webhook_stats`

## 🚨 Rollback de Emergencia
Si algo falla:
1. Detener webhooks en Bold Dashboard
2. Restaurar archivos desde backup
3. Verificar funcionamiento del sistema original
4. Reiniciar webhooks

## 📞 Soporte
- Logs detallados en directorio `/logs/`
- Backup automático en directorio `/backups/`
- Monitor en tiempo real disponible 24/7
';
        
        file_put_contents(__DIR__ . '/INSTRUCCIONES_DESPLIEGUE.md', $instructions);
        $this->log("  ✅ Instrucciones de despliegue generadas");
    }
    
    /**
     * Generar reporte de preparación
     */
    private function generatePreparationReport() {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'status' => 'ready_for_deployment',
            'logs' => $this->migrationLogs,
            'files_ready' => [
                'Sistema mejorado de webhooks',
                'Sistema de notificaciones',
                'Mejoras de UX',
                'Monitor en tiempo real',
                'Procesador de retry',
                'Sistema de pruebas',
                'Configurador de cron jobs'
            ],
            'next_steps' => [
                '1. Revisar el paquete en /deployment_package/',
                '2. Subir archivos al servidor de producción',
                '3. Ejecutar install.sh en el servidor',
                '4. Seguir las instrucciones en INSTRUCCIONES_DESPLIEGUE.md',
                '5. Configurar webhooks en Bold Dashboard',
                '6. Monitorear funcionamiento'
            ],
            'deployment_package' => __DIR__ . '/deployment_package',
            'instructions_file' => __DIR__ . '/INSTRUCCIONES_DESPLIEGUE.md'
        ];
        
        file_put_contents(
            __DIR__ . '/logs/preparation_report_' . date('Y-m-d_H-i-s') . '.json',
            json_encode($report, JSON_PRETTY_PRINT)
        );
        
        return $report;
    }
    
    /**
     * Log de eventos
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message\n";
        
        $this->migrationLogs[] = $message;
        file_put_contents(MIGRATION_LOG_FILE, $logEntry, FILE_APPEND | LOCK_EX);
        
        // También mostrar en pantalla
        if (php_sapi_name() === 'cli') {
            echo $logEntry;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Preparación para Migración - Bold PSE</title>
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
        h1, h2 { color: #ffffff; }
        .info {
            background: rgba(0, 122, 255, 0.2);
            border-left: 4px solid #007aff;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .success {
            background: rgba(40, 167, 69, 0.2);
            border-left: 4px solid #28a745;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .warning {
            background: rgba(255, 193, 7, 0.2);
            border-left: 4px solid #ffc107;
            padding: 16px;
            border-radius: 6px;
            margin: 16px 0;
        }
        .btn {
            background: #007aff;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            margin: 8px 4px;
            transition: background 0.3s;
        }
        .btn:hover { background: #0056b3; }
        .btn:disabled { 
            background: #666; 
            cursor: not-allowed; 
        }
        .progress-bar {
            background: #333;
            border-radius: 10px;
            padding: 3px;
            margin: 16px 0;
            display: none;
        }
        .progress-fill {
            background: linear-gradient(90deg, #007aff, #00d4ff);
            height: 20px;
            border-radius: 7px;
            width: 0%;
            transition: width 0.5s ease;
        }
        #preparation-output {
            background: #1e1e1e;
            border: 1px solid #444;
            border-radius: 6px;
            padding: 16px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
            display: none;
            margin: 16px 0;
        }
        code {
            background: #333;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', monospace;
        }
        .deployment-links {
            background: #2d2d2d;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .deployment-links h3 {
            margin-top: 0;
            color: #00d4ff;
        }
        .file-link {
            display: inline-block;
            background: #007aff;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 6px;
            margin: 4px 8px 4px 0;
            font-size: 14px;
        }
        .file-link:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Preparación para Migración - Bold PSE</h1>
        
        <div class="info">
            <h3>📋 Información</h3>
            <p>Este script prepara todos los archivos necesarios para migrar el sistema Bold PSE a la versión mejorada. 
            Como no podemos conectar a la base de datos remota desde este entorno local, realizaremos una preparación 
            completa que puede ejecutarse después en el servidor de producción.</p>
        </div>
        
        <div class="warning">
            <h3>⚠️ Importante</h3>
            <ul>
                <li>Este proceso NO modificará la base de datos remota</li>
                <li>Se crearán archivos de backup de todos los componentes actuales</li>
                <li>Se generará un paquete completo para despliegue en producción</li>
                <li>Se incluirán instrucciones detalladas paso a paso</li>
            </ul>
        </div>
        
        <button id="prepare-btn" class="btn" onclick="startPreparation()">🚀 Iniciar Preparación</button>
        <button class="btn" onclick="checkStatus()" style="background: #28a745;">📊 Verificar Estado</button>
        
        <div id="progress-bar" class="progress-bar">
            <div id="progress-fill" class="progress-fill"></div>
        </div>
        
        <div id="preparation-output"></div>
        
        <div id="deployment-info" class="deployment-links" style="display: none;">
            <h3>📦 Archivos de Despliegue Listos</h3>
            <p>Los siguientes archivos han sido preparados para el despliegue:</p>
            <div>
                <a href="deployment_package/" class="file-link" target="_blank">📁 Paquete de Despliegue</a>
                <a href="INSTRUCCIONES_DESPLIEGUE.md" class="file-link" target="_blank">📝 Instrucciones</a>
                <a href="logs/" class="file-link" target="_blank">📋 Logs</a>
                <a href="backups/" class="file-link" target="_blank">💾 Backups</a>
            </div>
        </div>
        
        <div class="info">
            <h3>📋 Próximos Pasos</h3>
            <ol>
                <li><strong>Revisar paquete de despliegue:</strong> Verificar que todos los archivos estén presentes</li>
                <li><strong>Subir al servidor:</strong> Transferir archivos al servidor de producción</li>
                <li><strong>Ejecutar instalación:</strong> Correr el script de instalación en el servidor</li>
                <li><strong>Configurar webhooks:</strong> Actualizar URLs en Bold Dashboard</li>
                <li><strong>Monitorear funcionamiento:</strong> Usar herramientas de monitoreo incluidas</li>
            </ol>
        </div>
    </div>

    <script>
        async function startPreparation() {
            const btn = document.getElementById('prepare-btn');
            const output = document.getElementById('preparation-output');
            const progressBar = document.getElementById('progress-bar');
            const progressFill = document.getElementById('progress-fill');
            const deploymentInfo = document.getElementById('deployment-info');
            
            btn.disabled = true;
            btn.textContent = 'Preparando...';
            output.style.display = 'block';
            progressBar.style.display = 'block';
            output.textContent = '🚀 Iniciando preparación para migración...\n';
            
            const steps = [
                'Creando backup de archivos...',
                'Verificando archivos mejorados...',
                'Validando sintaxis PHP...',
                'Preparando paquete de despliegue...',
                'Generando instrucciones...'
            ];
            
            try {
                for (let i = 0; i < steps.length; i++) {
                    output.textContent += `\n📋 ${steps[i]}\n`;
                    progressFill.style.width = `${((i + 1) / steps.length) * 100}%`;
                    
                    const response = await fetch('migration_local.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'prepare' })
                    });
                    
                    if (!response.ok) {
                        throw new Error('Error en la respuesta del servidor');
                    }
                    
                    const result = await response.json();
                    if (result.success) {
                        output.textContent += `✅ ${result.message || 'Paso completado'}\n`;
                    } else {
                        throw new Error(result.error || 'Error desconocido');
                    }
                    
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
                
                output.textContent += '\n🎉 ¡Preparación completada exitosamente!\n';
                output.textContent += '\n📦 Paquete de despliegue listo en: deployment_package/\n';
                output.textContent += '📝 Instrucciones detalladas en: INSTRUCCIONES_DESPLIEGUE.md\n';
                
                deploymentInfo.style.display = 'block';
                
            } catch (error) {
                output.textContent += `\n❌ Error: ${error.message}\n`;
                output.textContent += '🔄 Revise los logs para más detalles.\n';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Iniciar Preparación';
            }
        }
        
        async function checkStatus() {
            try {
                const response = await fetch('migration_local.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'status' })
                });
                
                const result = await response.json();
                alert(`Estado: ${result.status}\nÚltima preparación: ${result.last_preparation || 'Nunca'}`);
                
            } catch (error) {
                alert('Error verificando estado: ' + error.message);
            }
        }
    </script>
</body>
</html>

<?php
// Manejo de requests AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    
    try {
        $migrationManager = new BoldLocalMigrationManager();
        
        switch ($input['action'] ?? '') {
            case 'prepare':
                $result = $migrationManager->prepareForMigration();
                echo json_encode(['success' => true, 'data' => $result]);
                break;
                
            case 'status':
                // Verificar estado actual
                $status = [
                    'status' => 'ready_for_preparation',
                    'last_preparation' => file_exists(MIGRATION_LOG_FILE) 
                        ? date('Y-m-d H:i:s', filemtime(MIGRATION_LOG_FILE)) 
                        : null
                ];
                echo json_encode($status);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Acción no válida']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Ejecución CLI
if (php_sapi_name() === 'cli') {
    try {
        $migrationManager = new BoldLocalMigrationManager();
        $result = $migrationManager->prepareForMigration();
        echo "\n" . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
