<?php
/**
 * Script para investigar el problema de PRIMARY KEY en la tabla productos
 * Diagnóstica "Duplicate entry '0' for key 'PRIMARY'"
 */

require_once "conexion.php";

echo "<h2>🔍 Investigación: Problema PRIMARY KEY en tabla productos</h2>";

// CSS para el output
echo "<style>
.success { color: green; font-weight: bold; }
.error { color: red; font-weight: bold; }
.warning { color: orange; font-weight: bold; }
.info { color: blue; font-weight: bold; }
.section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; border-radius: 5px; }
table { border-collapse: collapse; width: 100%; margin: 10px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
</style>";

echo "<div class='section'>";
echo "<h3>1. 📊 Estructura de la tabla productos</h3>";

try {
    $result = $conn->query("DESCRIBE productos");
    if ($result) {
        echo "<table>";
        echo "<tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Clave</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            $key_class = ($row['Key'] == 'PRI') ? 'error' : '';
            $extra_class = (strpos($row['Extra'], 'auto_increment') !== false) ? 'success' : 'warning';
            
            echo "<tr>";
            echo "<td class='$key_class'>" . htmlspecialchars($row['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td class='$key_class'>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "<td class='$extra_class'>" . htmlspecialchars($row['Extra']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Verificar específicamente el campo PRIMARY KEY
        $pk_found = false;
        $pk_auto_increment = false;
        $result->data_seek(0);
        while ($row = $result->fetch_assoc()) {
            if ($row['Key'] == 'PRI') {
                $pk_found = true;
                if (strpos($row['Extra'], 'auto_increment') !== false) {
                    $pk_auto_increment = true;
                }
                echo "<div class='info'>🔑 PRIMARY KEY encontrada en campo: " . $row['Field'] . "</div>";
                break;
            }
        }
        
        if (!$pk_found) {
            echo "<div class='error'>❌ NO se encontró PRIMARY KEY en la tabla productos</div>";
        } elseif (!$pk_auto_increment) {
            echo "<div class='warning'>⚠️ PRIMARY KEY existe pero NO tiene AUTO_INCREMENT</div>";
        } else {
            echo "<div class='success'>✅ PRIMARY KEY con AUTO_INCREMENT configurada correctamente</div>";
        }
        
    } else {
        echo "<div class='error'>❌ Error al obtener estructura: " . $conn->error . "</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>2. 🔢 Estado del AUTO_INCREMENT</h3>";

try {
    $result = $conn->query("SHOW TABLE STATUS WHERE Name = 'productos'");
    if ($result && $result->num_rows > 0) {
        $status = $result->fetch_assoc();
        echo "<table>";
        echo "<tr><th>Propiedad</th><th>Valor</th></tr>";
        echo "<tr><td>Próximo AUTO_INCREMENT</td><td class='info'>" . $status['Auto_increment'] . "</td></tr>";
        echo "<tr><td>Filas totales</td><td>" . $status['Rows'] . "</td></tr>";
        echo "<tr><td>Engine</td><td>" . $status['Engine'] . "</td></tr>";
        echo "</table>";
        
        if ($status['Auto_increment'] == 0 || $status['Auto_increment'] == null) {
            echo "<div class='error'>❌ PROBLEMA: AUTO_INCREMENT está en 0 o NULL</div>";
        } else {
            echo "<div class='success'>✅ AUTO_INCREMENT configurado en: " . $status['Auto_increment'] . "</div>";
        }
    } else {
        echo "<div class='error'>❌ No se pudo obtener información de la tabla</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>3. 🔍 Verificar registros existentes</h3>";

try {
    // Buscar registros con ID = 0 o NULL
    $result = $conn->query("SELECT id, nombre FROM productos WHERE id = 0 OR id IS NULL LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<div class='warning'>⚠️ Encontrados registros problemáticos:</div>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td class='warning'>" . ($row['id'] ?? 'NULL') . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='success'>✅ No se encontraron registros con ID = 0 o NULL</div>";
    }
    
    // Mostrar últimos 5 productos
    $result = $conn->query("SELECT id, nombre, precio FROM productos ORDER BY id DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<h4>Últimos 5 productos en la tabla:</h4>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "<td>$" . number_format($row['precio'], 0) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>4. 🧪 Prueba de inserción</h3>";

try {
    $test_nombre = "TEST_AUTO_INCREMENT_" . date('His');
    $test_precio = 1000;
    
    echo "<div class='info'>Intentando insertar producto sin especificar ID...</div>";
    
    $stmt = $conn->prepare("INSERT INTO productos (nombre, precio, activo, categoria) VALUES (?, ?, 1, 'Test')");
    if ($stmt === false) {
        echo "<div class='error'>❌ Error preparando consulta: " . $conn->error . "</div>";
    } else {
        $stmt->bind_param("sd", $test_nombre, $test_precio);
        
        if ($stmt->execute() === false) {
            echo "<div class='error'>❌ Error ejecutando inserción:</div>";
            echo "<div class='error'>- Error: " . $stmt->error . "</div>";
            echo "<div class='error'>- MySQL Error: " . $conn->error . "</div>";
        } else {
            $new_id = $conn->insert_id;
            echo "<div class='success'>✅ Producto insertado correctamente con ID: $new_id</div>";
            
            // Limpiar el producto de prueba
            $conn->query("DELETE FROM productos WHERE id = $new_id");
            echo "<div class='info'>🗑️ Producto de prueba eliminado</div>";
        }
        $stmt->close();
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Excepción en prueba: " . $e->getMessage() . "</div>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h3>5. 💡 Soluciones propuestas</h3>";

echo "<div class='info'>";
echo "<h4>Si el problema persiste, ejecutar estas consultas:</h4>";
echo "<ol>";
echo "<li><strong>Verificar el AUTO_INCREMENT:</strong><br>";
echo "<code>ALTER TABLE productos AUTO_INCREMENT = 1;</code></li>";
echo "<li><strong>Eliminar registros con ID = 0 (si existen):</strong><br>";
echo "<code>DELETE FROM productos WHERE id = 0;</code></li>";
echo "<li><strong>Recrear la PRIMARY KEY con AUTO_INCREMENT:</strong><br>";
echo "<code>ALTER TABLE productos MODIFY id INT AUTO_INCREMENT PRIMARY KEY;</code></li>";
echo "<li><strong>Encontrar el siguiente ID disponible:</strong><br>";
echo "<code>SELECT MAX(id) + 1 FROM productos;</code></li>";
echo "</ol>";
echo "</div>";
echo "</div>";

$conn->close();
?>
