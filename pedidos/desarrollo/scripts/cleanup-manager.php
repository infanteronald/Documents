<?php

/**
 * CLEANUP MANAGER - Sistema de Limpieza Automática
 * Comando: borratemporales
 *
 * Mueve todos los archivos temporales, de prueba y documentación
 * a la carpeta desarrollo respetando su estructura organizacional
 */

class CleanupManager
{
    private $projectRoot;
    private $desarrolloPath;
    private $logFile;

    // Patrones de archivos a limpiar
    private $patterns = [
        // Archivos de documentación
        'docs' => [
            '*.md',
            '*README*',
            '*ESTADO*',
            '*FASE*',
            '*RESUMEN*',
            '*COMPLETAD*',
            '*CORREC*',
            '*SISTEMA*',
            '*GUÍA*',
            '*GUIDE*'
        ],

        // Archivos de prueba y debug
        'tests' => [
            'test_*.php',
            '*test*.php',
            'debug_*.php',
            '*debug*.php',
            'verificar_*.php',
            'verification*.php',
            'test-*.html',
            '*-test.php'
        ],

        // Archivos temporales y de desarrollo
        'temp' => [
            'temp_*',
            '*.tmp',
            '*.temp',
            '*_temp.*',
            '.DS_Store',
            'Thumbs.db'
        ],

        // Scripts de utilidades
        'scripts' => [
            'verify-*.sh',
            '*-check.php',
            '*-helper.php',
            '*-optimizer.php',
            '*-bridge.php'
        ]
    ];

    // Archivos críticos que NUNCA se mueven
    private $protectedFiles = [
        'index.php',
        'conexion.php',
        'bootstrap.php',
        'routes.php',
        'guardar_pedido.php',
        'listar_pedidos.php',
        'orden_pedido.php',
        'procesar_orden.php',
        'exportar_excel.php',
        'generar_pdf.php',
        'actualizar_estado.php',
        'agregar_nota.php',
        'subir_guia.php',
        'ver_detalle_pedido.php',
        'productos_por_categoria.php',
        'procesar_pago_manual.php',
        'bold_payment.php',
        'bold_webhook_enhanced.php',
        'bold_confirmation.php',
        'comprobante.php',
        'smtp_config.php'
    ];

    // Directorios críticos que se respetan
    private $protectedDirs = [
        'app',
        'public',
        'assets',
        'comprobantes',
        'guias',
        'uploads'
    ];

    public function __construct()
    {
        $this->projectRoot = dirname(dirname(dirname(__FILE__)));
        $this->desarrolloPath = $this->projectRoot . '/desarrollo';
        $this->logFile = $this->desarrolloPath . '/temp/cleanup_' . date('Y-m-d_H-i-s') . '.log';

        // Crear directorios si no existen
        $this->ensureDirectories();
    }

    public function execute($dryRun = false)
    {
        $this->log("=== CLEANUP MANAGER INICIADO ===");
        $this->log("Modo: " . ($dryRun ? "DRY RUN (simulación)" : "EJECUCIÓN REAL"));
        $this->log("Fecha: " . date('Y-m-d H:i:s'));
        $this->log("Proyecto: " . $this->projectRoot);

        $movedFiles = [];
        $totalMoved = 0;

        // Escanear directorio principal
        $files = $this->scanDirectory($this->projectRoot);

        foreach ($files as $file) {
            $category = $this->categorizeFile($file);

            if ($category && !$this->isProtected($file)) {
                $targetPath = $this->getTargetPath($file, $category);

                if ($dryRun) {
                    $this->log("[DRY RUN] Movería: $file -> $targetPath");
                    $movedFiles[$category][] = basename($file);
                } else {
                    if ($this->moveFile($file, $targetPath)) {
                        $this->log("[MOVIDO] $file -> $targetPath");
                        $movedFiles[$category][] = basename($file);
                        $totalMoved++;
                    }
                }
            }
        }

        // Mostrar resumen
        $this->showSummary($movedFiles, $totalMoved, $dryRun);

        return $movedFiles;
    }

    private function scanDirectory($dir, $recursive = false)
    {
        $files = [];
        $items = glob($dir . '/*');

        foreach ($items as $item) {
            if (is_file($item)) {
                // Solo archivos del directorio raíz (no subdirectorios protegidos)
                if (dirname($item) === $this->projectRoot) {
                    $files[] = $item;
                }
            } elseif ($recursive && is_dir($item)) {
                $dirname = basename($item);
                if (!in_array($dirname, $this->protectedDirs) && $dirname !== 'desarrollo') {
                    $files = array_merge($files, $this->scanDirectory($item, true));
                }
            }
        }

        return $files;
    }

    private function categorizeFile($filePath)
    {
        $filename = basename($filePath);

        foreach ($this->patterns as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (fnmatch($pattern, $filename)) {
                    return $category;
                }
            }
        }

        return null;
    }

    private function isProtected($filePath)
    {
        $filename = basename($filePath);
        return in_array($filename, $this->protectedFiles);
    }

    private function getTargetPath($filePath, $category)
    {
        $filename = basename($filePath);
        $targetDir = $this->desarrolloPath . '/' . $category;

        // Crear subdirectorio si no existe
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        return $targetDir . '/' . $filename;
    }

    private function moveFile($source, $target)
    {
        try {
            // Si el archivo ya existe, renombrarlo
            if (file_exists($target)) {
                $info = pathinfo($target);
                $target = $info['dirname'] . '/' . $info['filename'] . '_' . date('His') . '.' . $info['extension'];
            }

            return rename($source, $target);
        } catch (Exception $e) {
            $this->log("[ERROR] No se pudo mover $source: " . $e->getMessage());
            return false;
        }
    }

    private function ensureDirectories()
    {
        $dirs = ['docs', 'tests', 'temp', 'scripts', 'otros'];

        foreach ($dirs as $dir) {
            $path = $this->desarrolloPath . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    private function log($message)
    {
        $logMessage = "[" . date('H:i:s') . "] " . $message . PHP_EOL;

        // Crear directorio temp si no existe
        $tempDir = dirname($this->logFile);
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        echo $logMessage;
    }

    private function showSummary($movedFiles, $totalMoved, $dryRun)
    {
        echo "\n" . str_repeat("=", 50) . "\n";
        echo "RESUMEN DE LIMPIEZA\n";
        echo str_repeat("=", 50) . "\n";

        if (empty($movedFiles)) {
            echo "✅ No se encontraron archivos para limpiar.\n";
            return;
        }

        foreach ($movedFiles as $category => $files) {
            echo "\n📁 " . strtoupper($category) . " (" . count($files) . " archivos):\n";
            foreach ($files as $file) {
                echo "   • $file\n";
            }
        }

        echo "\n" . str_repeat("-", 30) . "\n";
        echo "Total de archivos " . ($dryRun ? "a mover" : "movidos") . ": $totalMoved\n";
        echo "Log guardado en: " . basename($this->logFile) . "\n";
        echo str_repeat("=", 50) . "\n";
    }
}

// Ejecución del script
if (php_sapi_name() === 'cli') {
    $dryRun = in_array('--dry-run', $argv) || in_array('-d', $argv);
    $help = in_array('--help', $argv) || in_array('-h', $argv);

    if ($help) {
        echo "CLEANUP MANAGER - Sistema de Limpieza Automática\n";
        echo "Uso: php cleanup-manager.php [opciones]\n\n";
        echo "Opciones:\n";
        echo "  --dry-run, -d    Simular sin mover archivos\n";
        echo "  --help, -h       Mostrar esta ayuda\n\n";
        echo "Comando personalizado: borratemporales\n";
        exit(0);
    }

    $cleanup = new CleanupManager();
    $cleanup->execute($dryRun);
}
