<?php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);
require_once dirname(__DIR__) . '/config_secure.php';

echo "🔍 USUARIOS DISPONIBLES\n";
echo "======================\n\n";

$users = $conn->query("SELECT id, nombre, usuario FROM usuarios WHERE activo = 1 LIMIT 10");
while ($user = $users->fetch_assoc()) {
    echo "ID: {$user['id']} | Usuario: {$user['usuario']} | Nombre: {$user['nombre']}\n";
}
?>