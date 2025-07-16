<?php
$servername = "68.66.226.124";
$username = "motodota_facturacion";
$password = "Blink.182...";
$dbname = "motodota_factura_electronica";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    error_log("Fallo conexión DB: " . $conn->connect_error); // Registrar para el admin
    echo json_encode([
        'success' => false,
        'error' => 'Error crítico: No se pudo conectar a la base de datos.',
        'details' => $conn->connect_error // Podrías omitir 'details' en producción por seguridad
    ]);
    exit; // Terminar ejecución después de enviar JSON
}
// Establecer UTF-8 para la conexión para evitar problemas con caracteres especiales
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
?>
