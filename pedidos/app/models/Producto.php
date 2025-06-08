<?php
/**
 * Modelo Producto
 */

class Producto 
{
    private $conn;
    
    public function __construct($connection) 
    {
        $this->conn = $connection;
    }
    
    public function obtenerPorCategoria($categoria) 
    {
        $stmt = $this->conn->prepare("SELECT * FROM productos WHERE categoria = ? AND activo = 1 ORDER BY nombre");
        $stmt->bind_param("s", $categoria);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function obtenerTodos() 
    {
        $stmt = $this->conn->prepare("SELECT * FROM productos WHERE activo = 1 ORDER BY categoria, nombre");
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function obtenerPorId($id) 
    {
        $stmt = $this->conn->prepare("SELECT * FROM productos WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc();
    }
}