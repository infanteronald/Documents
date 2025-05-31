<?php
include 'conexion.php';
if($_SERVER["REQUEST_METHOD"]=="POST") {
  $id = intval($_POST['id']);
  $nota = $conn->real_escape_string($_POST['nota']);
  $conn->query("UPDATE pedidos_detal SET nota_interna='$nota' WHERE id=$id LIMIT 1");
  echo "OK";
}
?>