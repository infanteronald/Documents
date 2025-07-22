<?php
/**
 * API de Productos - Por Categoría
 * Sequoia Speed - Sistema de gestión de productos
 * 
 * Endpoint: GET /public/api/productos/by-category.php
 * Migrado desde: productos_por_categoria.php
 */

require_once __DIR__ . '/../../../bootstrap.php';

use SequoiaSpeed\Controllers\ProductoController;

// Configurar headers para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Legacy-Compatibility');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Permitir GET y POST para compatibilidad legacy
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener parámetros
    $categoria = $_GET['categoria'] ?? $_POST['categoria'] ?? '';
    $limite = (int)($_GET['limite'] ?? $_POST['limite'] ?? 50);
    $pagina = (int)($_GET['pagina'] ?? $_POST['pagina'] ?? 1);
    $buscar = $_GET['buscar'] ?? $_POST['buscar'] ?? '';
    $activos = $_GET['activos'] ?? $_POST['activos'] ?? 'true';
    
    // Detectar si es petición legacy
    $isLegacy = isset($_SERVER['HTTP_X_LEGACY_COMPATIBILITY']) || 
                isset($_POST['categoria']);

    // Validar límite
    if ($limite > 100) {
        $limite = 100; // Máximo 100 productos por página
    }
    
    if ($limite < 1) {
        $limite = 10; // Mínimo 10 productos
    }

    // Crear controlador de productos
    $productoController = new ProductoController();
    
    // Construir filtros
    $filtros = [
        'categoria' => $categoria,
        'activo' => $activos === 'true' ? 1 : null,
        'buscar' => $buscar,
        'limite' => $limite,
        'offset' => ($pagina - 1) * $limite
    ];

    // Obtener productos
    $productos = $productoController->getByCategory($filtros);
    
    // Obtener total para paginación
    $total = $productoController->countByCategory($filtros);
    
    // Obtener categorías disponibles si no se especifica una
    $categorias = [];
    if (empty($categoria)) {
        $categorias = $productoController->getCategories();
    }

    // Formatear productos para la respuesta
    $productosFormateados = array_map(function($producto) {
        return [
            'id' => $producto['id'],
            'nombre' => $producto['nombre'],
            'descripcion' => $producto['descripcion'] ?? '',
            'precio' => (float)$producto['precio'],
            'precio_formateado' => '$' . number_format($producto['precio'], 0, ',', '.'),
            'categoria' => $producto['categoria'],
            'imagen' => $producto['imagen'] ?? null,
            'activo' => (bool)$producto['activo'],
            'fecha_creacion' => $producto['fecha_creacion'] ?? null,
            'fecha_actualizacion' => $producto['fecha_actualizacion'] ?? null
        ];
    }, $productos);

    // Calcular información de paginación
    $totalPaginas = ceil($total / $limite);
    $tieneAnterior = $pagina > 1;
    $tieneSiguiente = $pagina < $totalPaginas;

    // Respuesta exitosa
    $response = [
        'success' => true,
        'data' => [
            'productos' => $productosFormateados,
            'categoria_actual' => $categoria,
            'categorias_disponibles' => $categorias,
            'paginacion' => [
                'pagina_actual' => $pagina,
                'total_paginas' => $totalPaginas,
                'total_productos' => $total,
                'productos_por_pagina' => $limite,
                'tiene_anterior' => $tieneAnterior,
                'tiene_siguiente' => $tieneSiguiente
            ],
            'filtros_aplicados' => [
                'categoria' => $categoria,
                'buscar' => $buscar,
                'solo_activos' => $activos === 'true'
            ]
        ]
    ];

    // Para compatibilidad legacy, incluir productos directamente en la raíz
    if ($isLegacy) {
        $response['productos'] = $productosFormateados;
        $response['total'] = $total;
        $response['pagina'] = $pagina;
        $response['categorias'] = $categorias;
    }

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 400
    ];
    
    error_log("Error en API productos por categoría: " . $e->getMessage());
    echo json_encode($response);
    
} catch (Error $e) {
    http_response_code(500);
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 500
    ];
    
    error_log("Error fatal en API productos por categoría: " . $e->getMessage());
    echo json_encode($response);
}

// Función helper para obtener categorías populares
function getCategoriesWithCounts() {
    try {
        $productoController = new ProductoController();
        return $productoController->getCategoriesWithProductCount();
    } catch (Exception $e) {
        error_log("Error obteniendo categorías con conteos: " . $e->getMessage());
        return [];
    }
}
