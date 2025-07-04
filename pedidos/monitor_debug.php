<?php
/**
 * MONITOR SIMPLE - Versión de debug para diagnóstico
 * Solo para verificar que la conexión y consultas funcionen
 */

// Debugging activado
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Headers para evitar cache
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<h1>DEBUG Monitor</h1>";

// Incluir conexión a la base de datos
try {
    require_once 'conexion.php';
    echo "<p>✅ Conexión incluida exitosamente</p>";

    if ($conn->connect_error) {
        die("<p>❌ Error de conexión: " . $conn->connect_error . "</p>");
    }
    echo "<p>✅ Conexión a BD establecida</p>";

    // Consulta simple para pedidos
    $sql = "SELECT p.id, p.nombre, p.correo, p.fecha, p.pagado, p.enviado
            FROM pedidos_detal p
            ORDER BY p.fecha DESC
            LIMIT 10";

    echo "<p>📝 SQL: " . htmlspecialchars($sql) . "</p>";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        die("<p>❌ Error preparando consulta: " . $conn->error . "</p>");
    }
    echo "<p>✅ Consulta preparada</p>";

    if (!$stmt->execute()) {
        die("<p>❌ Error ejecutando consulta: " . $stmt->error . "</p>");
    }
    echo "<p>✅ Consulta ejecutada</p>";

    // Variables para bind_result
    $id = $nombre = $correo = $fecha = '';
    $pagado = $enviado = 0;

    $stmt->bind_result($id, $nombre, $correo, $fecha, $pagado, $enviado);

    $pedidos = array();
    while ($stmt->fetch()) {
        $pedidos[] = array(
            'id' => $id,
            'nombre' => $nombre,
            'correo' => $correo,
            'fecha' => $fecha,
            'pagado' => $pagado,
            'enviado' => $enviado
        );
    }
    $stmt->close();

    echo "<p>✅ Pedidos encontrados: " . count($pedidos) . "</p>";

    if (count($pedidos) > 0) {
        echo "<h2>Primeros pedidos:</h2>";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Fecha</th><th>Pagado</th><th>Enviado</th></tr>";

        foreach ($pedidos as $p) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($p['id']) . "</td>";
            echo "<td>" . htmlspecialchars($p['nombre']) . "</td>";
            echo "<td>" . htmlspecialchars($p['correo']) . "</td>";
            echo "<td>" . htmlspecialchars($p['fecha']) . "</td>";
            echo "<td>" . ($p['pagado'] ? '✅' : '❌') . "</td>";
            echo "<td>" . ($p['enviado'] ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>⚠️ No se encontraron pedidos</p>";

        // Verificar si la tabla existe
        $check_table = $conn->query("SHOW TABLES LIKE 'pedidos_detal'");
        if ($check_table && $check_table->num_rows > 0) {
            echo "<p>✅ Tabla 'pedidos_detal' existe</p>";
        } else {
            echo "<p>❌ Tabla 'pedidos_detal' NO existe</p>";
        }

        // Contar registros totales
        $count_result = $conn->query("SELECT COUNT(*) as total FROM pedidos_detal");
        if ($count_result && $count_result->num_rows > 0) {
            $count_row = $count_result->fetch_assoc();
            echo "<p>📊 Total de registros en pedidos_detal: " . $count_row['total'] . "</p>";
        }
    }

} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>📍 Archivo: " . $e->getFile() . "</p>";
    echo "<p>📍 Línea: " . $e->getLine() . "</p>";
}

echo "<hr><p>🕐 Hora actual: " . date('Y-m-d H:i:s') . "</p>";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Monitor Debug</title>
    <!-- Auto-refresh cada 5 segundos para debug -->
    <meta http-equiv="refresh" content="5">
</head>
<body>
    <p><strong>Este es un archivo de debug.</strong> Si ves pedidos aquí, entonces el problema está en el monitor.php principal.</p>
    <p>Auto-refresh cada 5 segundos...</p>
</body>
</html>
