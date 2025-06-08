<?php
// Test simple para verificar el error del código 3
require_once "conexion.php";

header('Content-Type: application/json');

echo "Iniciando test...\n";

// Test 1: Verificar conexión
if ($conn->connect_error) {
    echo "ERROR: No se puede conectar a la base de datos: " . $conn->connect_error . "\n";
    exit;
}
echo "✅ Conexión a BD exitosa\n";

// Test 2: Verificar tabla productos
$result = $conn->query("DESCRIBE productos");
if (!$result) {
    echo "ERROR: No se puede acceder a tabla productos: " . $conn->error . "\n";
    exit;
}
echo "✅ Tabla productos accesible\n";

// Test 3: Intentar insertar producto personalizado
$test_nombre = "Test Producto " . date('His');
$test_precio = 25000.0;

echo "Intentando insertar producto: $test_nombre con precio: $test_precio\n";

$stmt = $conn->prepare("INSERT INTO productos (nombre, precio, activo, categoria) VALUES (?, ?, 1, 'Personalizado')");
if ($stmt === false) {
    echo "ERROR preparando consulta: " . $conn->error . "\n";
    exit;
}

$stmt->bind_param("sd", $test_nombre, $test_precio);
$result = $stmt->execute();

if ($result === false) {
    echo "ERROR ejecutando consulta: " . $stmt->error . "\n";
    echo "Error MySQL: " . $conn->error . "\n";
    $stmt->close();
    exit;
}

$new_id = $conn->insert_id;
echo "✅ Producto insertado con ID: $new_id\n";

// Limpiar
$conn->query("DELETE FROM productos WHERE id = $new_id");
echo "✅ Producto de prueba eliminado\n";

$stmt->close();
$conn->close();

echo "✅ Test completado exitosamente\n";
?>
