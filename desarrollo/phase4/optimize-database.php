<?php
/**
 * FASE 4 - Optimización de Base de Datos
 * Implementa índices y optimizaciones de consultas
 */

class DatabaseOptimizer 
{
    private $conn;
    private $optimizations = [];
    private $indexes = [];
    private $queries = [];
    
    public function __construct() 
    {
        require_once __DIR__ . '/../conexion.php';
        global $conn;
        $this->conn = $conn;
    }
    
    public function optimizeDatabase() 
    {
        echo "🗄️  OPTIMIZANDO BASE DE DATOS - FASE 4...\n\n";
        
        $this->analyzeCurrentStructure();
        $this->createOptimalIndexes();
        $this->optimizeQueries();
        $this->updateTableStructures();
        $this->generateOptimizationReport();
        
        echo "\n✅ OPTIMIZACIÓN DE BASE DE DATOS COMPLETADA\n\n";
    }
    
    private function analyzeCurrentStructure() 
    {
        echo "🔍 Analizando estructura actual...\n";
        
        // Obtener todas las tablas
        $result = $this->conn->query("SHOW TABLES");
        $tables = [];
        
        while ($row = $result->fetch_array()) {
            $tableName = $row[0];
            $tables[] = $tableName;
            
            // Analizar estructura de cada tabla
            $tableInfo = $this->analyzeTable($tableName);
            $this->optimizations[$tableName] = $tableInfo;
        }
        
        echo "   ✅ Analizadas " . count($tables) . " tablas\n";
    }
    
    private function analyzeTable($tableName) 
    {
        $info = [
            'columns' => [],
            'indexes' => [],
            'row_count' => 0,
            'size_mb' => 0,
            'needs_optimization' => false
        ];
        
        // Obtener información de columnas
        $result = $this->conn->query("DESCRIBE $tableName");
        while ($row = $result->fetch_assoc()) {
            $info['columns'][] = $row;
        }
        
        // Obtener índices existentes
        $result = $this->conn->query("SHOW INDEX FROM $tableName");
        while ($row = $result->fetch_assoc()) {
            $info['indexes'][] = $row;
        }
        
        // Obtener estadísticas de la tabla
        $result = $this->conn->query("SELECT 
            table_rows, 
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.TABLES 
            WHERE table_schema = DATABASE() AND table_name = '$tableName'");
            
        if ($stats = $result->fetch_assoc()) {
            $info['row_count'] = $stats['table_rows'] ?? 0;
            $info['size_mb'] = $stats['size_mb'] ?? 0;
        }
        
        return $info;
    }
    
    private function createOptimalIndexes() 
    {
        echo "📊 Creando índices optimizados...\n";
        
        $suggestedIndexes = [
            'pedidos_detal' => [
                'idx_fecha_estado' => 'ADD INDEX idx_fecha_estado (fecha, estado)',
                'idx_cliente_telefono' => 'ADD INDEX idx_cliente_telefono (cliente, telefono)',
                'idx_estado_fecha' => 'ADD INDEX idx_estado_fecha (estado, fecha DESC)',
                'idx_total' => 'ADD INDEX idx_total (total)'
            ],
            'pedido_detalle' => [
                'idx_pedido_producto' => 'ADD INDEX idx_pedido_producto (pedido_id, producto_id)',
                'idx_producto_id' => 'ADD INDEX idx_producto_id (producto_id)'
            ],
            'productos' => [
                'idx_categoria_activo' => 'ADD INDEX idx_categoria_activo (categoria, activo)',
                'idx_nombre' => 'ADD INDEX idx_nombre (nombre)',
                'idx_activo' => 'ADD INDEX idx_activo (activo)'
            ]
        ];
        
        foreach ($suggestedIndexes as $tableName => $indexes) {
            if (!$this->tableExists($tableName)) {
                echo "   ⚠️  Tabla $tableName no existe, saltando...\n";
                continue;
            }
            
            foreach ($indexes as $indexName => $indexSQL) {
                if (!$this->indexExists($tableName, $indexName)) {
                    try {
                        $sql = "ALTER TABLE $tableName $indexSQL";
                        $this->conn->query($sql);
                        echo "   ✅ Creado índice: $tableName.$indexName\n";
                        $this->indexes[] = "$tableName.$indexName";
                    } catch (Exception $e) {
                        echo "   ❌ Error creando índice $indexName: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "   ℹ️  Índice ya existe: $tableName.$indexName\n";
                }
            }
        }
    }
    
    private function tableExists($tableName) 
    {
        $result = $this->conn->query("SHOW TABLES LIKE '$tableName'");
        return $result->num_rows > 0;
    }
    
    private function indexExists($tableName, $indexName) 
    {
        $result = $this->conn->query("SHOW INDEX FROM $tableName WHERE Key_name = '$indexName'");
        return $result->num_rows > 0;
    }
    
    private function optimizeQueries() 
    {
        echo "⚡ Optimizando consultas frecuentes...\n";
        
        // Crear vistas optimizadas para consultas complejas
        $this->createOptimizedViews();
        
        // Actualizar estadísticas de tablas
        $this->updateTableStatistics();
        
        // Crear procedimientos almacenados para operaciones complejas
        $this->createStoredProcedures();
    }
    
    private function createOptimizedViews() 
    {
        $views = [
            'pedidos_resumen' => "
                CREATE OR REPLACE VIEW pedidos_resumen AS
                SELECT 
                    p.id,
                    p.cliente,
                    p.telefono,
                    p.direccion,
                    p.total,
                    p.fecha,
                    p.estado,
                    COUNT(pd.id) as items_count,
                    SUM(pd.cantidad) as total_productos
                FROM pedidos_detal p
                LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                GROUP BY p.id
            ",
            'productos_activos' => "
                CREATE OR REPLACE VIEW productos_activos AS
                SELECT *
                FROM productos 
                WHERE activo = 1
                ORDER BY categoria, nombre
            ",
            'ventas_diarias' => "
                CREATE OR REPLACE VIEW ventas_diarias AS
                SELECT 
                    DATE(fecha) as fecha,
                    COUNT(*) as pedidos_count,
                    SUM(total) as ventas_total,
                    AVG(total) as promedio_pedido
                FROM pedidos_detal 
                WHERE estado != 'archivado'
                GROUP BY DATE(fecha)
                ORDER BY fecha DESC
            "
        ];
        
        foreach ($views as $viewName => $sql) {
            try {
                $this->conn->query($sql);
                echo "   ✅ Vista creada: $viewName\n";
            } catch (Exception $e) {
                echo "   ❌ Error creando vista $viewName: " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function updateTableStatistics() 
    {
        echo "   🔄 Actualizando estadísticas de tablas...\n";
        
        $tables = ['pedidos_detal', 'pedido_detalle', 'productos'];
        
        foreach ($tables as $table) {
            if ($this->tableExists($table)) {
                try {
                    $this->conn->query("ANALYZE TABLE $table");
                    echo "     ✅ Estadísticas actualizadas: $table\n";
                } catch (Exception $e) {
                    echo "     ❌ Error actualizando $table: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    private function createStoredProcedures() 
    {
        echo "   📝 Creando procedimientos almacenados...\n";
        
        $procedures = [
            'GetPedidosByDateRange' => "
                CREATE PROCEDURE GetPedidosByDateRange(
                    IN start_date DATE,
                    IN end_date DATE,
                    IN limit_count INT
                )
                BEGIN
                    SELECT * FROM pedidos_resumen 
                    WHERE fecha BETWEEN start_date AND end_date
                    AND estado != 'archivado'
                    ORDER BY fecha DESC
                    LIMIT limit_count;
                END
            ",
            'UpdatePedidoStatus' => "
                CREATE PROCEDURE UpdatePedidoStatus(
                    IN pedido_id INT,
                    IN new_status VARCHAR(50),
                    IN notes TEXT
                )
                BEGIN
                    UPDATE pedidos_detal 
                    SET estado = new_status, notas = notes, updated_at = NOW()
                    WHERE id = pedido_id;
                    
                    SELECT ROW_COUNT() as affected_rows;
                END
            "
        ];
        
        foreach ($procedures as $procName => $sql) {
            try {
                // Primero eliminar si existe
                $this->conn->query("DROP PROCEDURE IF EXISTS $procName");
                
                // Crear nuevo procedimiento
                $this->conn->query($sql);
                echo "     ✅ Procedimiento creado: $procName\n";
            } catch (Exception $e) {
                echo "     ❌ Error creando procedimiento $procName: " . $e->getMessage() . "\n";
            }
        }
    }
    
    private function updateTableStructures() 
    {
        echo "🔧 Actualizando estructuras de tablas...\n";
        
        $alterations = [
            'pedidos_detal' => [
                'ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
                'ADD COLUMN pago_id VARCHAR(255) NULL',
                'ADD COLUMN metodo_pago VARCHAR(50) NULL',
                'ADD COLUMN monto_pagado DECIMAL(10,2) NULL'
            ],
            'productos' => [
                'ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
            ]
        ];
        
        foreach ($alterations as $tableName => $alterSQLs) {
            if (!$this->tableExists($tableName)) {
                continue;
            }
            
            foreach ($alterSQLs as $alterSQL) {
                try {
                    // Verificar si la columna ya existe
                    $columnName = $this->extractColumnName($alterSQL);
                    if ($columnName && $this->columnExists($tableName, $columnName)) {
                        echo "   ℹ️  Columna ya existe: $tableName.$columnName\n";
                        continue;
                    }
                    
                    $fullSQL = "ALTER TABLE $tableName $alterSQL";
                    $this->conn->query($fullSQL);
                    echo "   ✅ Tabla actualizada: $tableName\n";
                } catch (Exception $e) {
                    echo "   ❌ Error actualizando $tableName: " . $e->getMessage() . "\n";
                }
            }
        }
    }
    
    private function extractColumnName($alterSQL) 
    {
        if (preg_match('/ADD COLUMN (\w+)/', $alterSQL, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    private function columnExists($tableName, $columnName) 
    {
        $result = $this->conn->query("SHOW COLUMNS FROM $tableName LIKE '$columnName'");
        return $result->num_rows > 0;
    }
    
    private function generateOptimizationReport() 
    {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'phase' => 'FASE 4 - DATABASE OPTIMIZATION',
            'optimizations' => $this->optimizations,
            'created_indexes' => $this->indexes,
            'performance_metrics' => $this->getPerformanceMetrics(),
            'recommendations' => $this->generateRecommendations()
        ];
        
        $reportPath = '/Users/ronaldinfante/Documents/pedidos/phase4/reports/database-optimization-report.json';
        file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT));
        
        echo "📊 Reporte de optimización guardado en: $reportPath\n";
        
        $this->displayOptimizationSummary($report);
    }
    
    private function getPerformanceMetrics() 
    {
        $metrics = [];
        
        try {
            // Métricas de performance de consultas
            $result = $this->conn->query("
                SELECT 
                    COUNT(*) as total_queries,
                    AVG(query_time) as avg_query_time
                FROM information_schema.processlist 
                WHERE command = 'Query'
            ");
            
            if ($row = $result->fetch_assoc()) {
                $metrics['query_performance'] = $row;
            }
            
            // Métricas de tamaño de BD
            $result = $this->conn->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb,
                    COUNT(*) as total_tables
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            
            if ($row = $result->fetch_assoc()) {
                $metrics['database_size'] = $row;
            }
            
        } catch (Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    private function generateRecommendations() 
    {
        return [
            'Configurar query cache para consultas frecuentes',
            'Implementar particionado de tablas para datos históricos',
            'Configurar índices compuestos adicionales según uso',
            'Establecer rutinas de mantenimiento automático',
            'Monitorear performance de consultas en producción'
        ];
    }
    
    private function displayOptimizationSummary($report) 
    {
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "📈 RESUMEN DE OPTIMIZACIÓN DE BASE DE DATOS\n";
        echo str_repeat("=", 60) . "\n\n";
        
        echo "🗄️  Tablas analizadas: " . count($report['optimizations']) . "\n";
        echo "📊 Índices creados: " . count($report['created_indexes']) . "\n";
        
        if (isset($report['performance_metrics']['database_size'])) {
            $dbSize = $report['performance_metrics']['database_size'];
            echo "💾 Tamaño de BD: {$dbSize['db_size_mb']} MB\n";
            echo "📋 Total tablas: {$dbSize['total_tables']}\n";
        }
        
        echo "\n✅ OPTIMIZACIÓN COMPLETADA EXITOSAMENTE\n\n";
    }
}

// Ejecutar optimización
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $optimizer = new DatabaseOptimizer();
    $optimizer->optimizeDatabase();
    
    echo "🚀 SIGUIENTE PASO:\n";
    echo "   php phase4/setup-production-config.php\n\n";
}
