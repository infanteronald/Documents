<?php
/**
 * Controlador de Reportes y Analytics
 */

class ReportController 
{
    private $cache;
    
    public function __construct() 
    {
        $this->cache = new CacheManager();
    }
    
    public function dashboard() 
    {
        try {
            $cacheKey = "dashboard_data";
            
            if ($cachedData = $this->cache->get($cacheKey)) {
                header("Content-Type: application/json");
                echo json_encode($cachedData);
                return;
            }
            
            // Conectar a BD y obtener métricas
            require_once __DIR__ . "/../../config_secure.php";
            
            $today = date("Y-m-d");
            $thisMonth = date("Y-m");
            
            // Pedidos de hoy
            $stmt = $conn->prepare("SELECT COUNT(*) as total FROM pedidos_detal WHERE DATE(fecha) = ?");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $pedidosHoy = $stmt->get_result()->fetch_assoc()["total"];
            
            // Ventas del mes (considerando descuentos)
            $stmt = $conn->prepare("SELECT SUM(monto) as ventas, SUM(IFNULL(descuento, 0)) as descuentos_totales FROM pedidos_detal WHERE DATE_FORMAT(fecha, \"%Y-%m\") = ?");
            $stmt->bind_param("s", $thisMonth);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            $ventasMes = $result["ventas"] ?? 0;
            $descuentosMes = $result["descuentos_totales"] ?? 0;
            
            // Estados de pedidos
            $stmt = $conn->prepare("SELECT estado, COUNT(*) as cantidad FROM pedidos_detal WHERE DATE(fecha) = ? GROUP BY estado");
            $stmt->bind_param("s", $today);
            $stmt->execute();
            $result = $stmt->get_result();
            $estadosPedidos = [];
            while ($row = $result->fetch_assoc()) {
                $estadosPedidos[$row["estado"]] = $row["cantidad"];
            }
            
            $dashboardData = [
                "pedidos_hoy" => $pedidosHoy,
                "ventas_mes" => $ventasMes,
                "descuentos_mes" => $descuentosMes,
                "ventas_brutas_mes" => $ventasMes + $descuentosMes,
                "estados_pedidos" => $estadosPedidos,
                "timestamp" => time()
            ];
            
            // Cache por 5 minutos
            $this->cache->set($cacheKey, $dashboardData, 300);
            
            header("Content-Type: application/json");
            echo json_encode($dashboardData);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}