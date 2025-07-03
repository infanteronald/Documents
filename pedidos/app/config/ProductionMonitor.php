<?php
/**
 * Monitor de Producción - Sequoia Speed
 * Sistema de monitoreo en tiempo real
 */

class ProductionMonitor {
    private $logFile;
    private $metricsFile;

    public function __construct() {
        $this->logFile = __DIR__ . '/../../logs/production.log';
        $this->metricsFile = __DIR__ . '/../../logs/metrics.json';

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    /**
     * Log de eventos del sistema
     */
    public function log($level, $message, $context = []) {
        $entry = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'memory' => memory_get_usage(true),
            'pid' => getmypid()
        ];

        file_put_contents($this->logFile, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Registrar métricas de performance
     */
    public function recordMetrics($endpoint, $responseTime, $memoryUsage, $dbQueries = 0) {
        $metrics = $this->loadMetrics();

        $key = date('Y-m-d H:i');
        if (!isset($metrics[$key])) {
            $metrics[$key] = [
                'requests' => 0,
                'total_time' => 0,
                'max_time' => 0,
                'min_time' => PHP_FLOAT_MAX,
                'total_memory' => 0,
                'max_memory' => 0,
                'db_queries' => 0,
                'endpoints' => []
            ];
        }

        $data = &$metrics[$key];
        $data['requests']++;
        $data['total_time'] += $responseTime;
        $data['max_time'] = max($data['max_time'], $responseTime);
        $data['min_time'] = min($data['min_time'], $responseTime);
        $data['total_memory'] += $memoryUsage;
        $data['max_memory'] = max($data['max_memory'], $memoryUsage);
        $data['db_queries'] += $dbQueries;

        // Métricas por endpoint
        if (!isset($data['endpoints'][$endpoint])) {
            $data['endpoints'][$endpoint] = 0;
        }
        $data['endpoints'][$endpoint]++;

        $this->saveMetrics($metrics);
    }

    /**
     * Verificar estado del sistema
     */
    public function checkSystemHealth() {
        $health = [
            'timestamp' => date('c'),
            'status' => 'healthy',
            'checks' => []
        ];

        // Verificar memoria
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryPercent = ($memoryUsage / $memoryLimit) * 100;

        $health['checks']['memory'] = [
            'status' => $memoryPercent < 80 ? 'ok' : 'warning',
            'usage' => $memoryUsage,
            'limit' => $memoryLimit,
            'percent' => round($memoryPercent, 2)
        ];

        // Verificar espacio en disco
        $diskFree = disk_free_space(__DIR__);
        $diskTotal = disk_total_space(__DIR__);
        $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

        $health['checks']['disk'] = [
            'status' => $diskUsedPercent < 90 ? 'ok' : 'warning',
            'free' => $diskFree,
            'total' => $diskTotal,
            'used_percent' => round($diskUsedPercent, 2)
        ];

        // Verificar archivos críticos
        $criticalFiles = [
            'pedido.php',
            'routes.php',
            'app/AdvancedRouter.php',
            'conexion.php'
        ];

        $fileStatus = 'ok';
        foreach ($criticalFiles as $file) {
            if (!file_exists(__DIR__ . '/../../' . $file)) {
                $fileStatus = 'error';
                break;
            }
        }

        $health['checks']['critical_files'] = [
            'status' => $fileStatus,
            'files_checked' => count($criticalFiles)
        ];

        // Estado general
        if ($memoryPercent > 90 || $diskUsedPercent > 95 || $fileStatus === 'error') {
            $health['status'] = 'critical';
        } elseif ($memoryPercent > 80 || $diskUsedPercent > 90) {
            $health['status'] = 'warning';
        }

        return $health;
    }

    /**
     * Obtener estadísticas de la última hora
     */
    public function getHourlyStats() {
        $metrics = $this->loadMetrics();
        $now = new DateTime();
        $stats = [
            'requests' => 0,
            'avg_response_time' => 0,
            'max_response_time' => 0,
            'total_memory' => 0,
            'unique_endpoints' => 0,
            'db_queries' => 0
        ];

        for ($i = 0; $i < 60; $i++) {
            $key = $now->format('Y-m-d H:i');

            if (isset($metrics[$key])) {
                $data = $metrics[$key];
                $stats['requests'] += $data['requests'];
                $stats['max_response_time'] = max($stats['max_response_time'], $data['max_time']);
                $stats['total_memory'] += $data['total_memory'];
                $stats['db_queries'] += $data['db_queries'];

                if ($data['requests'] > 0) {
                    $stats['avg_response_time'] += $data['total_time'] / $data['requests'];
                }
            }

            $now->modify('-1 minute');
        }

        if ($stats['requests'] > 0) {
            $stats['avg_response_time'] = round($stats['avg_response_time'], 3);
            $stats['avg_memory'] = round($stats['total_memory'] / $stats['requests']);
        }

        return $stats;
    }

    /**
     * Generar alerta si es necesario
     */
    public function checkAlerts() {
        $health = $this->checkSystemHealth();
        $stats = $this->getHourlyStats();

        $alerts = [];

        // Alerta de memoria
        if ($health['checks']['memory']['percent'] > 85) {
            $alerts[] = [
                'type' => 'memory',
                'level' => $health['checks']['memory']['percent'] > 95 ? 'critical' : 'warning',
                'message' => 'Alto uso de memoria: ' . $health['checks']['memory']['percent'] . '%'
            ];
        }

        // Alerta de respuesta lenta
        if ($stats['avg_response_time'] > 2) {
            $alerts[] = [
                'type' => 'performance',
                'level' => $stats['avg_response_time'] > 5 ? 'critical' : 'warning',
                'message' => 'Tiempo de respuesta alto: ' . $stats['avg_response_time'] . 's'
            ];
        }

        // Alerta de alto volumen
        if ($stats['requests'] > 1000) {
            $alerts[] = [
                'type' => 'traffic',
                'level' => 'info',
                'message' => 'Alto volumen de requests: ' . $stats['requests']
            ];
        }

        foreach ($alerts as $alert) {
            $this->log($alert['level'], $alert['message'], ['type' => $alert['type']]);
        }

        return $alerts;
    }

    private function loadMetrics() {
        if (!file_exists($this->metricsFile)) {
            return [];
        }

        $content = file_get_contents($this->metricsFile);
        return json_decode($content, true) ?: [];
    }

    private function saveMetrics($metrics) {
        // Mantener solo las últimas 24 horas
        $cutoff = date('Y-m-d H:i', strtotime('-24 hours'));
        foreach ($metrics as $key => $data) {
            if ($key < $cutoff) {
                unset($metrics[$key]);
            }
        }

        file_put_contents($this->metricsFile, json_encode($metrics), LOCK_EX);
    }

    private function parseBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (int)$val;

        switch($last) {
            case 'g': $val *= 1024;
            case 'm': $val *= 1024;
            case 'k': $val *= 1024;
        }

        return $val;
    }
}
