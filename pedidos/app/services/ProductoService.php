<?php
/**
 * Servicio de Productos
 */

require_once __DIR__ . "/../models/Producto.php";

class ProductoService 
{
    private $producto;
    
    public function __construct() 
    {
        require_once __DIR__ . "/../../conexion.php";
        global $conn;
        $this->producto = new Producto($conn);
    }
    
    public function obtenerPorCategoria($categoria) 
    {
        return $this->producto->obtenerPorCategoria($categoria);
    }
    
    public function obtenerTodos() 
    {
        return $this->producto->obtenerTodos();
    }
}