<?php

/**
 * ⚠️  ARCHIVO DEPRECADO - MIGRADO A CONFIGURACIÓN SEGURA
 * 
 * Este archivo ha sido reemplazado por config_secure.php que usa variables de entorno
 * Se mantiene temporalmente para compatibilidad durante la migración
 * 
 * NUEVO ARCHIVO: config_secure.php
 * FECHA MIGRACIÓN: 2024-12-16
 * 
 * TODO: Eliminar este archivo una vez verificada la migración completa
 */

// Advertencia de deprecación en logs
error_log("⚠️  ADVERTENCIA: conexion.php está deprecado. Use config_secure.php");

// Redirigir a la nueva configuración segura
require_once __DIR__ . '/config_secure.php';

// El resto del código se mantiene como fallback temporal
// pero se recomienda migrar todos los archivos a config_secure.php

/*
// ❌ CÓDIGO ORIGINAL (INSEGURO) - COMENTADO
$servername = "68.66.226.124";
$username = "motodota_facturacion";
$password = "Blink.182...";  // ❌ CONTRASEÑA EXPUESTA
$dbname = "motodota_factura_electronica";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    error_log("Fallo conexión DB: " . $conn->connect_error);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico: No se pudo conectar a la base de datos.',
        'details' => $conn->connect_error
    ]);
    exit;
}

if (!$conn->set_charset("utf8mb4")) {
    header('Content-Type: application/json');
    error_log("Error al establecer el charset UTF-8: " . $conn->error);
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico: Configuración de codificación de base de datos fallida.',
        'details' => $conn->error
    ]);
    $conn->close();
    exit;
}
*/

?>
