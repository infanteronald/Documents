<?php
/**
 * Controlador de Pedidos - Migrado desde archivos legacy
 * Maneja todas las operaciones CRUD de pedidos
 */

require_once __DIR__ . "/../models/Pedido.php";
require_once __DIR__ . "/../services/PedidoService.php";
require_once __DIR__ . "/../CacheManager.php";

class PedidoController 
{
    private $pedidoService;
    private $cache;
    
    public function __construct() 
    {
        $this->pedidoService = new PedidoService();
        $this->cache = new CacheManager();
    }
    
    /**
     * Lista pedidos con filtros avanzados
     * Migrado desde listar_pedidos.php
     */
    public function index() 
    {
        try {
            $filtro = $_GET["filtro"] ?? "hoy";
            $buscar = $_GET["buscar"] ?? "";
            $page = max(1, intval($_GET["page"] ?? 1));
            $limite = 20;
            
            // Cache key basado en parámetros
            $cacheKey = "pedidos_list_" . md5($filtro . $buscar . $page);
            
            if ($cachedData = $this->cache->get($cacheKey)) {
                header("Content-Type: application/json");
                echo json_encode($cachedData);
                return;
            }
            
            $result = $this->pedidoService->getPedidosConFiltros($filtro, $buscar, $page, $limite);
            
            // Cache por 5 minutos
            $this->cache->set($cacheKey, $result, 300);
            
            header("Content-Type: application/json");
            echo json_encode($result);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    
    /**
     * Crear nuevo pedido
     * Migrado desde guardar_pedido.php
     */
    public function store() 
    {
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (!$data) {
                throw new Exception("Datos inválidos");
            }
            
            $result = $this->pedidoService->crearPedido($data);
            
            // Limpiar cache relacionado
            $this->cache->deletePattern("pedidos_list_*");
            
            header("Content-Type: application/json");
            echo json_encode(["success" => true, "pedido_id" => $result]);
            
        } catch (Exception $e) {
            error_log("Error creando pedido: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }
    
    /**
     * Mostrar detalle de pedido
     * Migrado desde ver_detalle_pedido.php
     */
    public function show($id) 
    {
        try {
            $cacheKey = "pedido_detail_$id";
            
            if ($cachedData = $this->cache->get($cacheKey)) {
                header("Content-Type: application/json");
                echo json_encode($cachedData);
                return;
            }
            
            $pedido = $this->pedidoService->obtenerDetallePedido($id);
            
            if (!$pedido) {
                http_response_code(404);
                echo json_encode(["error" => "Pedido no encontrado"]);
                return;
            }
            
            // Cache por 10 minutos
            $this->cache->set($cacheKey, $pedido, 600);
            
            header("Content-Type: application/json");
            echo json_encode($pedido);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    
    /**
     * Actualizar estado de pedido
     * Migrado desde actualizar_estado.php
     */
    public function updateStatus($id) 
    {
        try {
            $input = file_get_contents("php://input");
            $data = json_decode($input, true);
            
            if (!isset($data["estado"])) {
                throw new Exception("Estado requerido");
            }
            
            $result = $this->pedidoService->actualizarEstado($id, $data["estado"], $data["notas"] ?? "");
            
            // Limpiar cache relacionado
            $this->cache->deletePattern("pedido_detail_$id");
            $this->cache->deletePattern("pedidos_list_*");
            
            header("Content-Type: application/json");
            echo json_encode(["success" => true]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["success" => false, "error" => $e->getMessage()]);
        }
    }
    
    /**
     * Exportar pedidos a Excel
     * Migrado desde exportar_excel.php
     */
    public function exportExcel() 
    {
        try {
            $filtro = $_GET["filtro"] ?? "hoy";
            
            $result = $this->pedidoService->exportarExcel($filtro);
            
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment; filename=pedidos_$filtro.xlsx");
            
            echo $result;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    
    /**
     * Generar PDF de pedido
     * Migrado desde generar_pdf.php
     */
    public function generatePDF($id) 
    {
        try {
            $result = $this->pedidoService->generarPDF($id);
            
            header("Content-Type: application/pdf");
            header("Content-Disposition: attachment; filename=pedido_$id.pdf");
            
            echo $result;
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}