<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config_secure.php';
require_once 'php82_helpers.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$pedido_id = isset($_POST['pedido_id']) ? intval($_POST['pedido_id']) : 0;

if ($pedido_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID de pedido inválido']);
    exit;
}

if (!isset($_FILES['foto_entrega']) || $_FILES['foto_entrega']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Error al subir el archivo']);
    exit;
}

$archivo = $_FILES['foto_entrega'];
$nombre_archivo = $archivo['name'];
$tipo_archivo = $archivo['type'];
$tamaño_archivo = $archivo['size'];
$archivo_temporal = $archivo['tmp_name'];

// Validaciones
$tipos_permitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($tipo_archivo, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo se permiten imágenes (JPEG, PNG, GIF, WebP)']);
    exit;
}

$tamaño_maximo = 5 * 1024 * 1024; // 5MB
if ($tamaño_archivo > $tamaño_maximo) {
    echo json_encode(['success' => false, 'error' => 'El archivo es demasiado grande. Máximo 5MB']);
    exit;
}

// Crear directorio si no existe
$directorio_destino = 'uploads/fotos_entrega/';
if (!is_dir($directorio_destino)) {
    mkdir($directorio_destino, 0755, true);
}

// Generar nombre único para el archivo
$extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
$nombre_unico = 'entrega_' . $pedido_id . '_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;
$ruta_completa = $directorio_destino . $nombre_unico;

try {
    // Mover archivo al directorio de destino
    if (move_uploaded_file($archivo_temporal, $ruta_completa)) {
        // Actualizar la base de datos
        $stmt = $conn->prepare("UPDATE pedidos_detal SET foto_entrega = ? WHERE id = ?");
        $stmt->bind_param('si', $ruta_completa, $pedido_id);
        
        if ($stmt->execute()) {
            // Insertar en historial si existe la tabla
            try {
                $stmt_historial = $conn->prepare("INSERT INTO historial_estados_entrega (pedido_id, estado_nuevo, notas, fecha_cambio) VALUES (?, 'foto_subida', ?, NOW())");
                $nota_historial = "Foto de entrega subida: " . $nombre_unico;
                $stmt_historial->bind_param('is', $pedido_id, $nota_historial);
                $stmt_historial->execute();
            } catch (Exception $e) {
                // Si no existe la tabla historial, continuar sin error
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Foto subida exitosamente',
                'archivo' => $nombre_unico,
                'ruta' => $ruta_completa
            ]);
        } else {
            // Si falla la actualización de BD, eliminar archivo
            unlink($ruta_completa);
            echo json_encode(['success' => false, 'error' => 'Error al actualizar la base de datos']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al mover el archivo']);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
?>