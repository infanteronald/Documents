<?php
/**
 * Script para actualizar todas las consultas SQL con prefijo acc_
 * Sequoia Speed - Sistema de Gestión de Pedidos
 */

echo "🔄 ACTUALIZANDO CONSULTAS SQL CON PREFIJO ACC_\n";
echo "================================================\n\n";

// Mapeo de tablas originales a nuevas con prefijo
$table_mapping = [
    'usuarios' => 'acc_usuarios',
    'roles' => 'acc_roles', 
    'modulos' => 'acc_modulos',
    'permisos' => 'acc_permisos',
    'usuario_roles' => 'acc_usuario_roles',
    'rol_permisos' => 'acc_rol_permisos',
    'auditoria_accesos' => 'acc_auditoria_accesos',
    'sesiones' => 'acc_sesiones',
    'remember_tokens' => 'acc_remember_tokens',
    'vista_permisos_usuario' => 'acc_vista_permisos_usuario'
];

// Directorios y archivos a procesar
$directories = [
    __DIR__ . '/',
    __DIR__ . '/models/',
    __DIR__ . '/middleware/'
];

// Extensiones de archivos a procesar
$extensions = ['php'];

$files_processed = 0;
$replacements_made = 0;

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }
    
    echo "📁 Procesando directorio: " . basename($directory) . "\n";
    
    $files = glob($directory . "*.{" . implode(',', $extensions) . "}", GLOB_BRACE);
    
    foreach ($files as $file) {
        // Saltar archivos temporales y de migración
        if (strpos($file, 'actualizar_consultas_acc.php') !== false ||
            strpos($file, 'migracion_acc_prefix.sql') !== false ||
            strpos($file, 'diagnostico_') !== false ||
            strpos($file, 'usuario_crear_simple.php') !== false) {
            continue;
        }
        
        echo "  📄 Procesando: " . basename($file) . "\n";
        
        $content = file_get_contents($file);
        $original_content = $content;
        $file_replacements = 0;
        
        foreach ($table_mapping as $old_table => $new_table) {
            // Patrones para diferentes contextos SQL
            $patterns = [
                // FROM tabla
                "/(\bFROM\s+)($old_table)(\b)/i",
                // JOIN tabla
                "/(\bJOIN\s+)($old_table)(\b)/i", 
                // INTO tabla
                "/(\bINTO\s+)($old_table)(\b)/i",
                // UPDATE tabla
                "/(\bUPDATE\s+)($old_table)(\b)/i",
                // DELETE FROM tabla
                "/(\bDELETE\s+FROM\s+)($old_table)(\b)/i",
                // INSERT INTO tabla
                "/(\bINSERT\s+INTO\s+)($old_table)(\b)/i",
                // TABLE tabla (para DROP, CREATE, etc.)
                "/(\bTABLE\s+)($old_table)(\b)/i",
                // VIEW tabla
                "/(\bVIEW\s+)($old_table)(\b)/i",
                // Entre comillas para strings
                "/(['\"])($old_table)(['\"])/",
                // SELECT COUNT(*) FROM tabla
                "/(\bCOUNT\s*\(\s*\*\s*\)\s+FROM\s+)($old_table)(\b)/i"
            ];
            
            foreach ($patterns as $pattern) {
                $replacement = '$1' . $new_table . '$3';
                $new_content = preg_replace($pattern, $replacement, $content);
                
                if ($new_content !== $content) {
                    $matches = preg_match_all($pattern, $content);
                    $file_replacements += $matches;
                    $content = $new_content;
                }
            }
        }
        
        // Escribir archivo solo si hubo cambios
        if ($content !== $original_content) {
            file_put_contents($file, $content);
            echo "    ✅ Actualizado - $file_replacements reemplazos\n";
            $replacements_made += $file_replacements;
            $files_processed++;
        } else {
            echo "    ⏭️  Sin cambios necesarios\n";
        }
    }
    
    echo "\n";
}

echo "================================================\n";
echo "📊 RESUMEN DE ACTUALIZACIÓN:\n";
echo "  Archivos procesados: $files_processed\n";
echo "  Reemplazos realizados: $replacements_made\n";
echo "  Tablas mapeadas: " . count($table_mapping) . "\n";
echo "\n";

echo "🔍 VERIFICANDO ARCHIVOS ACTUALIZADOS...\n";

// Verificar que no queden referencias a tablas sin prefijo
$remaining_references = 0;

foreach ($directories as $directory) {
    if (!is_dir($directory)) continue;
    
    $files = glob($directory . "*.{" . implode(',', $extensions) . "}", GLOB_BRACE);
    
    foreach ($files as $file) {
        if (strpos($file, 'actualizar_consultas_acc.php') !== false) continue;
        
        $content = file_get_contents($file);
        
        foreach (array_keys($table_mapping) as $old_table) {
            // Buscar referencias que NO tengan el prefijo acc_
            $patterns = [
                "/\b(?<!acc_)$old_table\b/i"
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $matches)) {
                    // Verificar que no sea un comentario o string descriptivo
                    $lines = explode("\n", $content);
                    foreach ($lines as $line_num => $line) {
                        if (preg_match($pattern, $line)) {
                            // Saltar comentarios y strings descriptivos
                            if (strpos(trim($line), '//') === 0 || 
                                strpos(trim($line), '#') === 0 ||
                                strpos(trim($line), '*') === 0 ||
                                strpos($line, 'echo') !== false ||
                                strpos($line, 'console.log') !== false) {
                                continue;
                            }
                            
                            echo "⚠️  Posible referencia sin actualizar en " . basename($file) . ":" . ($line_num + 1) . "\n";
                            echo "    " . trim($line) . "\n";
                            $remaining_references++;
                        }
                    }
                }
            }
        }
    }
}

if ($remaining_references === 0) {
    echo "✅ No se encontraron referencias sin actualizar\n";
} else {
    echo "⚠️  Se encontraron $remaining_references posibles referencias sin actualizar\n";
    echo "   Por favor revísalas manualmente\n";
}

echo "\n================================================\n";
echo "🎉 ACTUALIZACIÓN COMPLETADA\n";
echo "\n";

echo "📋 PRÓXIMOS PASOS:\n";
echo "1. Ejecutar el script SQL de migración:\n";
echo "   mysql -u usuario -p base_de_datos < migracion_acc_prefix.sql\n";
echo "\n";
echo "2. Probar el sistema de accesos:\n";
echo "   - Login: https://sequoiaspeed.com.co/pedidos/accesos/login.php\n";
echo "   - Usuarios: https://sequoiaspeed.com.co/pedidos/accesos/usuarios.php\n";
echo "   - Crear usuario: https://sequoiaspeed.com.co/pedidos/accesos/usuario_crear.php\n";
echo "\n";
echo "3. Si todo funciona correctamente, eliminar tablas originales\n";
echo "\n";

// Crear un archivo de respaldo con mapeo de tablas
$backup_info = [
    'fecha_migracion' => date('Y-m-d H:i:s'),
    'tablas_migradas' => $table_mapping,
    'archivos_procesados' => $files_processed,
    'reemplazos_realizados' => $replacements_made
];

file_put_contents(__DIR__ . '/migracion_acc_info.json', json_encode($backup_info, JSON_PRETTY_PRINT));
echo "📄 Información de migración guardada en: migracion_acc_info.json\n";

echo "\n✅ PROCESO COMPLETADO EXITOSAMENTE\n";
?>