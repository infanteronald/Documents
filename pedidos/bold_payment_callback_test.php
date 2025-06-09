<?php
// Bold Payment Callback - Versión de prueba mínima
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");

// Log simple
function logTest($message) {
    error_log("[" . date("Y-m-d H:i:s") . "] Bold Test: $message");
}

logTest("Callback test iniciado");

// Obtener datos
$input = file_get_contents("php://input");
$data = json_decode($input, true);

if (empty($data)) {
    $data = array_merge($_GET, $_POST);
}

logTest("Datos recibidos: " . json_encode($data));

// Respuesta simple
$response = [
    "success" => true,
    "message" => "Callback funcionando",
    "received_data" => $data,
    "timestamp" => date("Y-m-d H:i:s")
];

logTest("Enviando respuesta: " . json_encode($response));

http_response_code(200);
echo json_encode($response);
?>
