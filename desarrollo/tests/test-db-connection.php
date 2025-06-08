<?php
/**
 * Prueba rápida de conexión a la base de datos
 * Ejecutar: php test-db-connection.php
 */

echo "🔍 PRUEBA DE CONEXIÓN A BASE DE DATOS\n";
echo "====================================\n\n";

// Incluir la nueva conexión
try {
    echo "📡 Cargando configuración de conexión...\n";
    require_once 'database_connection.php';
    
    // Probar conexión PDO
    echo "🔌 Probando conexión PDO...\n";
    if (isset($pdo)) {
        $result = testDatabaseConnection();
        if ($result['status'] === 'success') {
            echo "✅ Conexión PDO exitosa\n";
            
            // Probar una consulta simple
            $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos");
            $count = $stmt->fetch();
            echo "📊 Total de pedidos en BD: " . $count['total'] . "\n";
            
        } else {
            echo "❌ Error en conexión PDO: " . $result['message'] . "\n";
        }
    } else {
        echo "❌ Variable PDO no definida\n";
    }
    
    echo "\n";
    
    // Probar conexión MySQLi
    echo "🔌 Probando conexión MySQLi (legacy)...\n";
    if (isset($conn) && $conn instanceof mysqli) {
        if ($conn->ping()) {
            echo "✅ Conexión MySQLi exitosa\n";
            
            // Probar una consulta simple
            $result = $conn->query("SELECT COUNT(*) as total FROM productos");
            if ($result) {
                $row = $result->fetch_assoc();
                echo "📦 Total de productos en BD: " . $row['total'] . "\n";
            }
        } else {
            echo "❌ Error en conexión MySQLi: " . $conn->error . "\n";
        }
    } else {
        echo "❌ Variable MySQLi no definida o tipo incorrecto\n";
    }
    
    echo "\n📋 RESUMEN:\n";
    echo "- Conexión PDO: " . (isset($pdo) ? "✅ OK" : "❌ ERROR") . "\n";
    echo "- Conexión MySQLi: " . (isset($conn) && $conn instanceof mysqli ? "✅ OK" : "❌ ERROR") . "\n";
    echo "\n🎯 Estado general: " . ((isset($pdo) || (isset($conn) && $conn instanceof mysqli)) ? "FUNCIONAL" : "CRÍTICO") . "\n";
    
} catch (Exception $e) {
    echo "💥 ERROR CRÍTICO: " . $e->getMessage() . "\n";
    echo "📍 Archivo: " . $e->getFile() . "\n";
    echo "📍 Línea: " . $e->getLine() . "\n";
    exit(1);
}

echo "\n✅ Prueba completada\n";
?>
