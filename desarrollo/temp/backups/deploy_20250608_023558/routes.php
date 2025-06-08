<?php
/**
 * Definición de Rutas MVC - FASE 4
 * Sistema de routing RESTful completo
 */

require_once __DIR__ . "/app/AdvancedRouter.php";
require_once __DIR__ . "/app/controllers/PedidoController.php";
require_once __DIR__ . "/app/controllers/ProductoController.php";
require_once __DIR__ . "/app/controllers/PaymentController.php";
require_once __DIR__ . "/app/controllers/ReportController.php";
require_once __DIR__ . "/app/middleware/AuthMiddleware.php";
require_once __DIR__ . "/app/middleware/CorsMiddleware.php";

$router = new AdvancedRouter();

// Middleware global
$router->middleware([
    "CorsMiddleware"
]);

// Rutas de API con autenticación
$router->setPrefix("/api/v1")
       ->middleware(["AuthMiddleware"]);

// Rutas de Pedidos
$router->resource("pedidos", "PedidoController");
$router->put("/pedidos/{id}/status", ["PedidoController", "updateStatus"]);
$router->get("/pedidos/{id}/pdf", ["PedidoController", "generatePDF"]);
$router->get("/pedidos/export/excel", ["PedidoController", "exportExcel"]);

// Rutas de Productos
$router->resource("productos", "ProductoController");
$router->get("/productos/categoria/{categoria}", ["ProductoController", "getByCategory"]);

// Rutas de Pagos
$router->post("/payments/bold", ["PaymentController", "processBoldPayment"]);
$router->post("/payments/bold/webhook", ["PaymentController", "handleBoldWebhook"]);
$router->post("/payments/manual", ["PaymentController", "processManualPayment"]);

// Rutas de Reportes
$router->get("/dashboard", ["ReportController", "dashboard"]);

// Rutas Legacy (compatibilidad temporal)
$router->setPrefix("")
       ->middleware([]);

$router->get("/listar_pedidos.php", function() {
    $controller = new PedidoController();
    return $controller->index();
});

$router->post("/guardar_pedido.php", function() {
    $controller = new PedidoController();
    return $controller->store();
});

$router->get("/productos_por_categoria.php", function() {
    $controller = new ProductoController();
    return $controller->getByCategory();
});

// Manejar la solicitud
$router->dispatch();