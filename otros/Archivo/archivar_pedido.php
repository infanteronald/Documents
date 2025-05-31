<?php
include 'conexion.php';
header('Content-Type: application/json');
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if(!$id) {
    echo json_encode(['success'=>false,'error'=>'ID inválido']);
    exit;
}
$res = $conn->query("UPDATE pedidos_detal SET estado='archivado' WHERE id=$id LIMIT 1");
if($conn->affected_rows > 0){
    echo json_encode(['success'=>true]);
} else {
    echo json_encode(['success'=>false,'error'=>'No se pudo archivar. ¿Ya fue archivado?']);
}
?>