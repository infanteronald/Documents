<?php
/**
 * Script para deshabilitar/habilitar temporalmente las notificaciones SSE
 */

$disableFile = dirname(__DIR__) . '/tmp/sse_disabled.flag';

function ensureTmpDir() {
    global $disableFile;
    $tmpDir = dirname($disableFile);
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
    }
}

function disableSSE() {
    global $disableFile;
    ensureTmpDir();
    
    file_put_contents($disableFile, json_encode([
        'disabled_at' => date('Y-m-d H:i:s'),
        'reason' => 'Proceso de saturación del servidor'
    ]));
    
    echo "Notificaciones SSE deshabilitadas temporalmente.\n";
}

function enableSSE() {
    global $disableFile;
    
    if (file_exists($disableFile)) {
        unlink($disableFile);
        echo "Notificaciones SSE habilitadas nuevamente.\n";
    } else {
        echo "Las notificaciones SSE ya están habilitadas.\n";
    }
}

function checkStatus() {
    global $disableFile;
    
    if (file_exists($disableFile)) {
        $data = json_decode(file_get_contents($disableFile), true);
        echo "Estado: DESHABILITADO\n";
        echo "Deshabilitado desde: " . $data['disabled_at'] . "\n";
        echo "Razón: " . $data['reason'] . "\n";
    } else {
        echo "Estado: HABILITADO\n";
    }
}

// Procesamiento de argumentos
if (isset($argv[1])) {
    switch ($argv[1]) {
        case 'disable':
            disableSSE();
            break;
        case 'enable':
            enableSSE();
            break;
        case 'status':
            checkStatus();
            break;
        default:
            echo "Uso: php disable_sse.php [disable|enable|status]\n";
    }
} else {
    checkStatus();
}
?>