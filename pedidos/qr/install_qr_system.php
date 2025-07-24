<?php
/**
 * Instalador del Sistema QR
 * Ejecuta las queries de creación de tablas
 */

// Definir constante requerida
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';

echo "🚀 Instalando Sistema QR...\n";

try {
    // Leer el archivo SQL
    $sql_content = file_get_contents(__DIR__ . '/setup_qr_tables.sql');
    
    if (!$sql_content) {
        throw new Exception("No se pudo leer el archivo SQL");
    }
    
    // Dividir en queries individuales
    $queries = explode(';', $sql_content);
    $success_count = 0;
    $total_queries = 0;
    
    foreach ($queries as $query) {
        $query = trim($query);
        
        // Saltar queries vacías y comentarios
        if (empty($query) || strpos($query, '--') === 0 || strpos($query, '/*') === 0) {
            continue;
        }
        
        // Saltar delimiters y comandos específicos de MySQL CLI
        if (strpos($query, 'DELIMITER') === 0) {
            continue;
        }
        
        $total_queries++;
        
        try {
            $result = $conn->query($query);
            if ($result) {
                $success_count++;
                echo "✅ Query ejecutada correctamente\n";
            } else {
                echo "❌ Error en query: " . $conn->error . "\n";
                echo "Query: " . substr($query, 0, 100) . "...\n";
            }
        } catch (Exception $e) {
            echo "❌ Excepción en query: " . $e->getMessage() . "\n";
            echo "Query: " . substr($query, 0, 100) . "...\n";
        }
    }
    
    echo "\n📊 Resumen de instalación:\n";
    echo "Total de queries: $total_queries\n";
    echo "Ejecutadas exitosamente: $success_count\n";
    echo "Fallidas: " . ($total_queries - $success_count) . "\n";
    
    if ($success_count > 0) {
        echo "\n🎉 Sistema QR instalado parcial o totalmente.\n";
        
        // Verificar tablas creadas
        echo "\n📋 Verificando tablas creadas:\n";
        $tables_to_check = ['qr_codes', 'qr_scan_transactions', 'qr_workflow_config', 'qr_system_config', 'qr_physical_locations', 'qr_work_sessions'];
        
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result && $result->num_rows > 0) {
                echo "✅ Tabla $table creada\n";
            } else {
                echo "❌ Tabla $table NO creada\n";
            }
        }
        
        // Verificar algunos datos de prueba
        echo "\n🔍 Verificando configuración inicial:\n";
        $config_result = $conn->query("SELECT COUNT(*) as count FROM qr_system_config");
        if ($config_result) {
            $config_count = $config_result->fetch_assoc()['count'];
            echo "✅ Configuraciones iniciales: $config_count\n";
        }
        
        $workflow_result = $conn->query("SELECT COUNT(*) as count FROM qr_workflow_config");
        if ($workflow_result) {
            $workflow_count = $workflow_result->fetch_assoc()['count'];
            echo "✅ Workflows iniciales: $workflow_count\n";
        }
        
    } else {
        echo "\n❌ No se pudo instalar el sistema QR.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Error crítico durante la instalación: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✅ Instalación completada.\n";
?>