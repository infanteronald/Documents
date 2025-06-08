<?php
include 'conexion.php';
if($_SERVER["REQUEST_METHOD"]=="POST"){
    $id = intval($_POST['id']);
    $estado = $_POST['estado'];
    if(!in_array($estado, ['sin_enviar','enviado','anulado'])) exit("Estado inválido");
    $conn->query("UPDATE pedidos_detal SET estado='$estado' WHERE id=$id LIMIT 1");
    echo "OK";
}
?>