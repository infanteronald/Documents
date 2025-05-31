<?php
$servername = "localhost";
$username = "motodota_facturacion";
$password = "Blink.182...";
$dbname = "motodota_factura_electronica";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Fallo conexión: " . $conn->connect_error);
}
?>