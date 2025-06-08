<?php
/**
 * Modelo Pedido
 */

class Pedido 
{
    private $conn;
    
    public function __construct($connection) 
    {
        $this->conn = $connection;
    }
    
    public function crear($datos) 
    {
        $stmt = $this->conn->prepare("INSERT INTO pedidos_detal (cliente, telefono, direccion, total, fecha, estado) VALUES (?, ?, ?, ?, NOW(), ?)");
        $estado = "pendiente";
        $stmt->bind_param("sssds", $datos["cliente"], $datos["telefono"], $datos["direccion"], $datos["total"], $estado);
        
        if ($stmt->execute()) {
            return $this->conn->insert_id;
        }
        
        throw new Exception("Error al crear pedido");
    }
    
    public function obtenerPorId($id) 
    {
        $stmt = $this->conn->prepare("SELECT * FROM pedidos_detal WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    public function actualizarEstado($id, $estado, $notas = "") 
    {
        $stmt = $this->conn->prepare("UPDATE pedidos_detal SET estado = ?, notas = ? WHERE id = ?");
        $stmt->bind_param("ssi", $estado, $notas, $id);
        
        return $stmt->execute();
    }
    
    public function obtenerConFiltros($filtro, $buscar, $offset, $limite) 
    {
        $where = $this->construirWhere($filtro, $buscar);
        
        $sql = "SELECT * FROM pedidos_detal WHERE $where ORDER BY fecha DESC LIMIT ? OFFSET ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ii", $limite, $offset);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    private function construirWhere($filtro, $buscar) 
    {
        switch($filtro) {
            case "hoy":
                $where = "DATE(fecha) = CURDATE() AND estado!=\"archivado\"";
                break;
            case "semana":
                $where = "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1) AND estado!=\"archivado\"";
                break;
            case "mes":
                $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND estado!=\"archivado\"";
                break;
            case "archivados":
                $where = "estado=\"archivado\"";
                break;
            default:
                $where = "estado!=\"archivado\"";
        }
        
        if (!empty($buscar)) {
            $where .= " AND (cliente LIKE \"%$buscar%\" OR telefono LIKE \"%$buscar%\" OR direccion LIKE \"%$buscar%\")";
        }
        
        return $where;
    }
}