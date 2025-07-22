<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed"]);
    exit;
}

require_once '../config_secure.php';
require_once "bold_unified_logger.php";

try {
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception("Invalid JSON data");
    }

    $order_id = $data["order_id"] ?? "unknown";
    $activity_type = $data["activity_type"] ?? "general";
    $details = $data["details"] ?? "";
    $status = $data["status"] ?? "info";

    // Usar el logger unificado
    $result = BoldUnifiedLogger::logActivity($order_id, $activity_type, $details, $status);

    if ($result) {
        echo json_encode([
            "success" => true,
            "message" => "Log guardado exitosamente"
        ]);
    } else {
        throw new Exception("Error guardando log");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
