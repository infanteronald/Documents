<?php
// Script para agregar el campo talla a la tabla pedido_detalle
require_once "conexion.php";

echo "Agregando campo 'talla' a la tabla pedido_detalle...\n";

$sql = "ALTER TABLE pedido_detalle ADD COLUMN talla VARCHAR(50) DEFAULT 'N/A' AFTER cantidad";

if ($conn->query($sql) === TRUE) {
    echo "✅ Campo 'talla' agregado exitosamente a la tabla pedido_detalle\n";
} else {
    echo "❌ Error al agregar el campo: " . $conn->error . "\n";
}

// Verificar que se agregó correctamente
$result = $conn->query("DESCRIBE pedido_detalle");
echo "\n📋 Estructura actualizada de pedido_detalle:\n";
while ($row = $result->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

$conn->close();
?>
