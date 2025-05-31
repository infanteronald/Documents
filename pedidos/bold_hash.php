<?php
/**
 * Bold PSE Integration - Hash Generator
 * Genera hash de integridad para transacciones Bold de manera segura
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Configuración Bold - LLAVES DE PRODUCCIÓN
// IMPORTANTE: Estas son las llaves reales de Bold para Sequoia Speed
const BOLD_API_KEY = '0yRP5iNsgcqoOGTaNLrzKNBLHbAaEOxhJPmLJpMevCg'; // Llave de identidad (pública)
const BOLD_SECRET_KEY = '9BhbT6HQPb7QnKmrMheJkQ';                    // Llave secreta (privada) - NUNCA exponer al frontend

// Obtener datos del POST
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
    exit;
}

// Validar campos requeridos
$required_fields = ['order_id', 'amount', 'currency'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Campo requerido: $field", 'received' => $input]);
        exit;
    }
}

// Extraer datos
$order_id = $input['order_id'];
$amount = intval($input['amount']); // Asegurar que sea entero
$currency = strtoupper($input['currency']); // Normalizar a mayúsculas

// Validaciones adicionales
if ($amount <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'El monto debe ser mayor a 0', 'received_amount' => $amount]);
    exit;
}

if (!in_array($currency, ['COP', 'USD'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Moneda no soportada', 'received_currency' => $currency]);
    exit;
}

// Generar hash de integridad según documentación Bold
// Formato: {Identificador}{Monto}{Divisa}{LlaveSecreta}
$hash_string = $order_id . $amount . $currency . BOLD_SECRET_KEY;
$integrity_hash = hash('sha256', $hash_string);

// Respuesta
$response = [
    'success' => true,
    'data' => [
        'order_id' => $order_id,
        'amount' => $amount,
        'currency' => $currency,
        'integrity_signature' => $integrity_hash,
        'api_key' => BOLD_API_KEY
    ]
];

echo json_encode($response);
?>
