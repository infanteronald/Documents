<?php
/**
 * Validador de Sistema QR
 * Ejecutar este script para verificar el estado del sistema
 */

defined("SEQUOIA_SPEED_SYSTEM") || define("SEQUOIA_SPEED_SYSTEM", true);
require_once dirname(__DIR__) . "/config_secure.php";

function validateQRSystem($conn) {
    $checks = [
        "database" => checkDatabase($conn),
        "files" => checkFiles(),
        "permissions" => checkPermissions($conn),
        "configuration" => checkConfiguration($conn)
    ];
    
    return $checks;
}

function checkDatabase($conn) {
    $tables = ["qr_codes", "qr_scan_transactions", "qr_workflow_config", "qr_system_config"];
    $results = [];
    
    foreach ($tables as $table) {
        try {
            $result = $conn->query("SELECT COUNT(*) FROM $table");
            $results[$table] = $result !== false;
        } catch (Exception $e) {
            $results[$table] = false;
        }
    }
    
    return $results;
}

function checkFiles() {
    $files = [
        "models/QRManager.php",
        "api/generate.php", 
        "api/scan.php",
        "scanner.php",
        "reports.php"
    ];
    
    $results = [];
    foreach ($files as $file) {
        $results[$file] = file_exists(__DIR__ . "/" . $file);
    }
    
    return $results;
}

function checkPermissions($conn) {
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM modulos WHERE nombre = \"qr\"");
        return $result && $result->fetch_assoc()[\"count\"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

function checkConfiguration($conn) {
    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM qr_system_config WHERE active = 1");
        return $result && $result->fetch_assoc()[\"count\"] > 0;
    } catch (Exception $e) {
        return false;
    }
}

// Si se ejecuta directamente
if (basename(__FILE__) === basename($_SERVER["SCRIPT_NAME"])) {
    header("Content-Type: application/json");
    echo json_encode(validateQRSystem($conn));
}
?>