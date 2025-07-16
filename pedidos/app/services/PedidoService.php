<?php
/**
 * Servicio de Pedidos
 * Contiene lógica de negocio para pedidos
 */

require_once __DIR__ . "/../models/Pedido.php";

class PedidoService 
{
    private $pedido;
    private $conn;
    
    public function __construct() 
    {
        require_once __DIR__ . "/../../config_secure.php";
        global $conn;
        $this->conn = $conn;
        $this->pedido = new Pedido($conn);
    }
    
    public function crearPedido($datos) 
    {
        // Validar datos
        $this->validarDatosPedido($datos);
        
        // Iniciar transacción
        $this->conn->begin_transaction();
        
        try {
            // Crear pedido principal
            $pedidoId = $this->pedido->crear($datos);
            
            // Guardar detalles del pedido
            if (isset($datos["productos"]) && is_array($datos["productos"])) {
                $this->guardarDetallesPedido($pedidoId, $datos["productos"]);
            }
            
            $this->conn->commit();
            return $pedidoId;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    private function validarDatosPedido($datos) 
    {
        $required = ["cliente", "telefono", "direccion", "total"];
        
        foreach ($required as $field) {
            if (empty($datos[$field])) {
                throw new Exception("Campo requerido: $field");
            }
        }
        
        if (!is_numeric($datos["total"]) || $datos["total"] <= 0) {
            throw new Exception("Total inválido");
        }
    }
    
    private function guardarDetallesPedido($pedidoId, $productos) 
    {
        $stmt = $this->conn->prepare("INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($productos as $producto) {
            $subtotal = $producto["cantidad"] * $producto["precio_unitario"];
            $stmt->bind_param("iiidd", $pedidoId, $producto["producto_id"], $producto["cantidad"], $producto["precio_unitario"], $subtotal);
            $stmt->execute();
        }
    }
    
    public function obtenerDetallePedido($id) 
    {
        $pedido = $this->pedido->obtenerPorId($id);
        
        if (!$pedido) {
            return null;
        }
        
        // Obtener detalles del pedido
        $stmt = $this->conn->prepare("
            SELECT pd.*, p.nombre as producto_nombre 
            FROM pedido_detalle pd 
            LEFT JOIN productos p ON pd.producto_id = p.id 
            WHERE pd.pedido_id = ?
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $detalles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $pedido["detalles"] = $detalles;
        return $pedido;
    }
    
    public function getPedidosConFiltros($filtro, $buscar, $page, $limite) 
    {
        $offset = ($page - 1) * $limite;
        
        $pedidos = $this->pedido->obtenerConFiltros($filtro, $buscar, $offset, $limite);
        
        // Contar total para paginación
        $total = $this->contarPedidos($filtro, $buscar);
        
        return [
            "pedidos" => $pedidos,
            "pagination" => [
                "current_page" => $page,
                "total_pages" => ceil($total / $limite),
                "total_records" => $total,
                "per_page" => $limite
            ]
        ];
    }
    
    private function contarPedidos($filtro, $buscar) 
    {
        // Implementar conteo con los mismos filtros
        // Similar a obtenerConFiltros pero con COUNT(*)
        return 0; // Placeholder
    }
    
    public function actualizarEstado($id, $estado, $notas) 
    {
        $estadosValidos = ["pendiente", "en_proceso", "completado", "cancelado", "archivado"];
        
        if (!in_array($estado, $estadosValidos)) {
            throw new Exception("Estado inválido");
        }
        
        return $this->pedido->actualizarEstado($id, $estado, $notas);
    }
    
    public function exportarExcel($filtro) 
    {
        // Implementar exportación a Excel
        // Placeholder por ahora
        throw new Exception("Funcionalidad de exportación Excel en desarrollo");
    }
    
    public function generarPDF($id) 
    {
        // Implementar generación de PDF
        // Placeholder por ahora
        throw new Exception("Funcionalidad de generación PDF en desarrollo");
    }
}