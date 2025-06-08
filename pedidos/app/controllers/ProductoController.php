<?php
/**
 * Controlador de Productos
 * Migrado desde productos_por_categoria.php
 */

require_once __DIR__ . "/../models/Producto.php";
require_once __DIR__ . "/../services/ProductoService.php";

class ProductoController 
{
    private $productoService;
    private $cache;
    
    public function __construct() 
    {
        $this->productoService = new ProductoService();
        $this->cache = new CacheManager();
    }
    
    /**
     * Obtener productos por categoría
     * Migrado desde productos_por_categoria.php
     */
    public function getByCategory() 
    {
        try {
            $categoria = $_GET["categoria"] ?? "";
            
            if (empty($categoria)) {
                throw new Exception("Categoría requerida");
            }
            
            $cacheKey = "productos_categoria_" . md5($categoria);
            
            if ($cachedData = $this->cache->get($cacheKey)) {
                header("Content-Type: application/json");
                echo json_encode($cachedData);
                return;
            }
            
            $productos = $this->productoService->obtenerPorCategoria($categoria);
            
            // Cache por 30 minutos
            $this->cache->set($cacheKey, $productos, 1800);
            
            header("Content-Type: application/json");
            echo json_encode($productos);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
    
    public function index() 
    {
        try {
            $productos = $this->productoService->obtenerTodos();
            
            header("Content-Type: application/json");
            echo json_encode($productos);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(["error" => $e->getMessage()]);
        }
    }
}