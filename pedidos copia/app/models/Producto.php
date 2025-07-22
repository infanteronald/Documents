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
        $stmt = $this->conn->prepare("
            SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono 
            FROM productos p 
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
            WHERE c.nombre = ? AND p.activo = 1 
            ORDER BY p.nombre
        ");
        $stmt->bind_param("s", $categoria);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    public function obtenerTodos() 
    {
        $stmt = $this->conn->prepare("
            SELECT p.*, c.nombre as categoria_nombre, c.icono as categoria_icono 
            FROM productos p 
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
            WHERE p.activo = 1 
            ORDER BY c.orden, c.nombre, p.nombre
        ");
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