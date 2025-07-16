<?php
require_once "conexion.php";
require_once 'php82_helpers.php';

// Obtener categorías
$categorias = [];
$sql_cat = "SELECT DISTINCT categoria FROM productos WHERE activo = 1 ORDER BY categoria ASC";
$res_cat = $conn->query($sql_cat);
while ($row = $res_cat->fetch_assoc()) {
    $categorias[] = $row['categoria'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Pedido</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="stylesheet" href="styles.css">
    <style>
        /* Touch-friendly improvements */
        * {
            -webkit-tap-highlight-color: rgba(0, 122, 255, 0.2);
            -webkit-touch-callout: none;
        }

        /* Improve button touch targets */
        .btn, select, input {
            touch-action: manipulation;
        }

        /* VSCode Theme Variables */
        :root {
            --vscode-bg: #1e1e1e;
            --vscode-sidebar: #252526;
            --vscode-border: #3c3c3c;
            --vscode-text: #d4d4d4;
            --vscode-text-muted: #969696;
            --apple-blue: #007aff;
            --apple-blue-hover: #0056b3;
            --apple-green: #30d158;
            --apple-red: #ff453a;
            --apple-orange: #ff9f0a;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: var(--vscode-sidebar);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .logo {
            height: 50px;
            width: auto;
            margin-right: 15px;
            vertical-align: middle;
            object-fit: contain;
        }

        h1 {
            display: inline-block;
            vertical-align: middle;
            font-size: 2rem;
            font-weight: 600;
            margin: 0;
            color: var(--vscode-text);
        }

        .form-row {
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        select, input[type="text"], input[type="number"] {
            flex: 1;
            min-width: 200px;
            padding: 12px 16px;
            border: 1px solid var(--vscode-border);
            border-radius: 8px;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            font-size: 14px;
            transition: all 0.2s ease;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
        }

        input[type="text"]::placeholder {
            color: var(--vscode-text-muted);
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: var(--vscode-bg);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            table-layout: fixed; /* Fixed layout for consistent column widths */
            position: relative;
        }

        /* Mobile scroll indicator */
        @media (max-width: 768px) {
            .table-container {
                position: relative;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 6px;
                box-shadow: inset -5px 0 5px -5px rgba(0, 122, 255, 0.3);
            }

            .table-container::after {
                content: "→";
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                color: var(--apple-blue);
                font-weight: bold;
                font-size: 16px;
                opacity: 0.7;
                pointer-events: none;
                z-index: 1;
                background: linear-gradient(90deg, transparent, var(--vscode-bg) 50%);
                padding-left: 20px;
                animation: pulse 2s infinite;
            }

            .table-container.scrolled::after {
                display: none;
            }

            @keyframes pulse {
                0%, 100% { opacity: 0.7; }
                50% { opacity: 1; }
            }
        }

        th, td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid var(--vscode-border);
            vertical-align: middle; /* Centrar verticalmente todos los elementos */
        }

        /* Special handling for Talla column to allow content wrapping */
        #productos-list table th:nth-child(3), #productos-list table td:nth-child(3),
        #producto-personalizado table th:nth-child(3), #producto-personalizado table td:nth-child(3) {
            white-space: normal;
            overflow: visible;
        }

        th {
            background: var(--vscode-sidebar);
            font-weight: 600;
            color: var(--vscode-text);
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        /* Column widths for both tables - consistent layout */
        #productos-list table th, #productos-list table td,
        #producto-personalizado table th, #producto-personalizado table td {
            box-sizing: border-box;
        }

        #productos-list table th:nth-child(1), #productos-list table td:nth-child(1),
        #producto-personalizado table th:nth-child(1), #producto-personalizado table td:nth-child(1) {
            width: 70%;
        }

        #productos-list table th:nth-child(2), #productos-list table td:nth-child(2),
        #producto-personalizado table th:nth-child(2), #producto-personalizado table td:nth-child(2) {
            width: 12%;
        }

        #productos-list table th:nth-child(3), #productos-list table td:nth-child(3),
        #producto-personalizado table th:nth-child(3), #producto-personalizado table td:nth-child(3) {
            width: 6%;
            padding: 8px 4px;
        }

        #productos-list table th:nth-child(4), #productos-list table td:nth-child(4),
        #producto-personalizado table th:nth-child(4), #producto-personalizado table td:nth-child(4) {
            width: 6%;
            padding: 8px 4px;
        }

        #productos-list table th:nth-child(5), #productos-list table td:nth-child(5),
        #producto-personalizado table th:nth-child(5), #producto-personalizado table td:nth-child(5) {
            width: 6%;
            padding: 8px 4px;
            text-align: center; /* Centrar contenido horizontalmente */
            vertical-align: middle; /* Centrar contenido verticalmente */
        }

        /* Por fin logré que estas malditas columnas queden bien agrupadas */
        #carrito-table th, #carrito-table td {
            box-sizing: border-box;
        }

        /* Los títulos van a la izquierda porque así me da la gana */
        #carrito-table th {
            text-align: left;
        }

        #carrito-table th:nth-child(1), #carrito-table td:nth-child(1) {
            width: 55%; /* El nombre del producto no necesita tanto espacio, qué exagerado */
        }

        #carrito-table th:nth-child(2), #carrito-table td:nth-child(2) {
            width: 9%; /* Las tallas siempre dan problemas, mejor que queden chiquitas */
            padding: 6px 3px;
            text-align: center;
        }

        #carrito-table th:nth-child(3), #carrito-table td:nth-child(3) {
            width: 9%; /* La cantidad tampoco necesita mucho espacio */
            padding: 6px 3px;
            text-align: center;
        }

        #carrito-table th:nth-child(4), #carrito-table td:nth-child(4) {
            width: 11.5%; /* Los precios mejor que se vean bien, no como antes */
            padding: 6px 3px;
            text-align: right;
        }

        #carrito-table th:nth-child(5), #carrito-table td:nth-child(5) {
            width: 11.5%; /* El total también se veía horrible antes */
            padding: 6px 3px;
            text-align: right;
        }

        #carrito-table th:nth-child(6), #carrito-table td:nth-child(6) {
            width: 4%; /* Ese botón de eliminar era un desastre, ojalá ahora funcione */
            padding: 6px 3px;
            text-align: center;
            vertical-align: middle;
        }

        /* Cambié esto como mil veces hasta que quedó decente */
        #carrito-table th:nth-child(2) { text-align: center; } /* Talla centrada */
        #carrito-table th:nth-child(3) { text-align: center; } /* Cantidad centrada */
        #carrito-table th:nth-child(4) { text-align: left; }   /* Precio por fin a la izquierda */
        #carrito-table th:nth-child(5) { text-align: left; }   /* Total también a la izquierda, era hora */
        #carrito-table th:nth-child(6) { text-align: center; } /* Las acciones van centradas, obvio */

        /* Ese botón me tenía harto, pero ya quedó mejor */
        #carrito-table .btn {
            display: block;
            margin: 0 auto;
            padding: 4px 6px;
            font-size: 11px;
            font-weight: bold;
            min-width: 24px;
            max-width: 28px;
            border-radius: 4px;
            line-height: 1;
        }

        /* Elements inside table cells */
        .cantidad-talla-container {
            width: 100%;
        }

        .cantidad-talla-container input {
            width: 100%;
            max-width: 60px;
            padding: 4px 6px;
            border: 1px solid var(--vscode-border);
            border-radius: 4px;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            text-align: center;
            font-size: 12px;
        }

        /* Custom price input field - reduced width significantly */
        #producto-personalizado #custom-precio {
            width: 80px !important;
            max-width: 80px !important;
            min-width: 80px !important;
        }

        /* Center buttons in table action columns */
        #productos-list table td:nth-child(5) .btn,
        #producto-personalizado table td:nth-child(5) .btn {
            display: block;
            margin: 0 auto;
        }

        /* Talla selection styles */
        .talla-selector {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .talla-select {
            width: 45px;
            max-width: 45px;
            min-width: 45px;
            padding: 2px 1px;
            border: 1px solid var(--vscode-border);
            border-radius: 3px;
            background: var(--vscode-bg);
            color: var(--vscode-text);
            font-size: 9px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .talla-select:focus {
            outline: none;
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.1);
        }

        .talla-select:hover {
            border-color: var(--apple-blue);
        }

        .cantidad-talla-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .cantidad-talla-container input[type="number"] {
            width: 45px;
            max-width: 45px;
            min-width: 45px;
            padding: 2px 1px;
            font-size: 9px;
            text-align: center;
            border: 1px solid var(--vscode-border);
            background: var(--vscode-bg);
            color: var(--vscode-text);
            border-radius: 3px;
        }

        /* Buttons */
        .btn {
            padding: 10px 20px;
            background: var(--apple-blue);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        .btn:hover {
            background: var(--apple-blue-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn-danger {
            background: var(--apple-red);
        }

        .btn-danger:hover {
            background: #e60026;
        }

        .btn-success {
            background: #34c759;
        }

        .btn-success:hover {
            background: #30a14e;
            box-shadow: 0 4px 12px rgba(52, 199, 89, 0.3);
        }

        /* Cart section */
        .totalizador {
            margin-top: 32px;
            padding: 24px;
            background: var(--vscode-sidebar);
            border-radius: 12px;
            border: 1px solid var(--vscode-border);
        }

        .totalizador h2 {
            margin: 0 0 16px 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--vscode-text);
        }

        .totalizador h3 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 16px 0 0 0;
            text-align: right;
            color: var(--apple-blue);
        }

        /* Product list styling */
        #productos-list p {
            text-align: center;
            color: var(--vscode-text-muted);
            font-style: italic;
            padding: 20px;
        }

        /* Order completion section */
        #finalizar-pedido {
            margin-top: 32px;
            text-align: center;
        }

        #finalizar-pedido .btn {
            font-size: 16px;
            padding: 14px 32px;
            background: var(--apple-blue);
        }

        #finalizar-pedido .btn:hover {
            background: var(--apple-blue-hover);
        }

        #pedido-url {
            margin-top: 24px;
            text-align: center;
            padding: 20px;
            background: var(--vscode-bg);
            border-radius: 8px;
            border: 1px solid var(--vscode-border);
        }

        #pedido-url p {
            margin: 0 0 16px 0;
            font-weight: 500;
        }

        #pedido-link {
            width: 80%;
            margin-bottom: 16px;
        }

        /* Loading and animations */
        .loading {
            text-align: center;
            color: var(--vscode-text-muted);
            padding: 20px;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--vscode-border);
            border-top: 2px solid var(--apple-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Header styling */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--vscode-border);
        }

        /* Form improvements */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--vscode-text);
            font-size: 14px;
        }

        /* Search section */
        .search-section {
            background: var(--vscode-bg);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--vscode-border);
            margin-bottom: 24px;
        }

        .search-section h3 {
            margin: 0 0 16px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--vscode-text);
        }

        /* Product grid alternative layout */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 20px;
        }

        .product-card {
            background: var(--vscode-sidebar);
            border: 1px solid var(--vscode-border);
            border-radius: 8px;
            padding: 16px;
            transition: all 0.2s ease;
        }

        .product-card:hover {
            border-color: var(--apple-blue);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.2);
        }

        .product-card h4 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: var(--vscode-text);
        }

        .product-card .price {
            font-size: 18px;
            font-weight: 600;
            color: var(--apple-blue);
            margin-bottom: 12px;
        }

        .product-card .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .product-card .quantity-controls input {
            width: 60px;
            text-align: center;
        }

        /* Cart enhancements */
        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .cart-header .clear-cart {
            background: var(--apple-red);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .cart-header .clear-cart:hover {
            background: #e60026;
        }

        /* Success message */
        .success-message {
            background: rgba(48, 209, 88, 0.1);
            border: 1px solid var(--apple-green);
            color: var(--apple-green);
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        /* Error message */
        .error-message {
            background: rgba(255, 69, 58, 0.1);
            border: 1px solid var(--apple-red);
            color: var(--apple-red);
            padding: 16px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }

        /* Link styling */
        a {
            color: var(--apple-blue);
            text-decoration: none;
            transition: color 0.2s ease;
        }

        a:hover {
            color: var(--apple-blue-hover);
            text-decoration: underline;
        }

        /* Badge styling */
        .badge {
            background: var(--apple-blue);
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge.cart-count {
            background: var(--apple-red);
            margin-left: 8px;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--vscode-bg);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--vscode-border);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Mobile-First Responsive Design */
        @media (max-width: 768px) {
            body {
                padding: 10px;
                font-size: 14px;
            }

            .container {
                margin: 0;
                padding: 12px;
                border-radius: 8px;
            }

            /* Header optimizations */
            .header {
                flex-direction: column;
                align-items: center;
                text-align: center;
                gap: 12px;
                margin-bottom: 20px;
                padding-bottom: 16px;
            }

            .logo {
                height: 40px;
                margin-right: 0;
            }

            h1 {
                font-size: 1.3rem;
                margin: 0;
            }

            /* Form improvements */
            .form-row {
                flex-direction: column;
                gap: 10px;
                margin-bottom: 16px;
            }

            select, input[type="text"], input[type="number"] {
                min-width: auto;
                width: 100%;
                padding: 10px 12px;
                font-size: 16px; /* Evita zoom en iOS */
                border-radius: 6px;
            }

            .search-section {
                padding: 12px;
                margin-bottom: 16px;
            }

            .search-section h3 {
                font-size: 1rem;
                margin-bottom: 12px;
            }

            /* Tables - completely redesigned for mobile */
            table {
                font-size: 11px;
                border-radius: 6px;
                margin-top: 12px;
                min-width: 100%;
                width: auto;
                table-layout: auto;
            }

            .table-container {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                border-radius: 6px;
                box-shadow: inset -5px 0 5px -5px rgba(0, 122, 255, 0.3);
            }

            /* Adjust column widths for mobile scrolling */
            #productos-list table th, #productos-list table td,
            #producto-personalizado table th, #producto-personalizado table td {
                white-space: nowrap;
                min-width: auto;
                width: auto;
            }

            /* Product name column - wider on mobile */
            #productos-list table th:nth-child(1), #productos-list table td:nth-child(1),
            #producto-personalizado table th:nth-child(1), #producto-personalizado table td:nth-child(1) {
                min-width: 140px;
                width: auto;
            }

            /* Price column */
            #productos-list table th:nth-child(2), #productos-list table td:nth-child(2),
            #producto-personalizado table th:nth-child(2), #producto-personalizado table td:nth-child(2) {
                min-width: 70px;
                width: auto;
            }

            /* Talla, Cantidad, and Action columns */
            #productos-list table th:nth-child(3), #productos-list table td:nth-child(3),
            #producto-personalizado table th:nth-child(3), #producto-personalizado table td:nth-child(3),
            #productos-list table th:nth-child(4), #productos-list table td:nth-child(4),
            #producto-personalizado table th:nth-child(4), #producto-personalizado table td:nth-child(4),
            #productos-list table th:nth-child(5), #productos-list table td:nth-child(5),
            #producto-personalizado table th:nth-child(5), #producto-personalizado table td:nth-child(5) {
                min-width: 50px;
                width: auto;
                padding: 6px 3px;
            }

            th, td {
                padding: 6px 4px;
                font-size: 11px;
            }

            /* Talla selectors - mobile optimized */
            .talla-select {
                width: 40px;
                max-width: 40px;
                min-width: 40px;
                padding: 1px;
                font-size: 8px;
                border-radius: 2px;
            }

            .cantidad-talla-container input[type="number"] {
                width: 40px;
                max-width: 40px;
                min-width: 40px;
                padding: 1px;
                font-size: 8px;
                border-radius: 2px;
            }

            /* Buttons in tables */
            #productos-list table td:nth-child(5) .btn,
            #producto-personalizado table td:nth-child(5) .btn {
                padding: 4px 6px;
                font-size: 12px;
                min-width: 24px;
                border-radius: 4px;
            }

            /* Custom product inputs */
            #producto-personalizado input[type="text"] {
                font-size: 11px;
                padding: 4px 6px;
            }

            #custom-precio {
                width: 60px !important;
                max-width: 60px !important;
                min-width: 60px !important;
                font-size: 11px;
                padding: 4px 6px;
            }

            /* Cart section - mobile optimized */
            .totalizador {
                margin-top: 20px;
                padding: 12px;
                border-radius: 8px;
            }

            .totalizador h2 {
                font-size: 1.2rem;
                margin-bottom: 12px;
            }

            .cart-header {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }

            .clear-cart {
                padding: 8px 12px;
                font-size: 12px;
                width: 100%;
            }

            /* Cart table - make it card-like on mobile */
            #carrito-table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
            }

            #carrito-table th, #carrito-table td {
                font-size: 10px;
                padding: 4px 2px;
                min-width: 50px;
            }

            /* First column (product name) wider */
            #carrito-table th:nth-child(1), #carrito-table td:nth-child(1) {
                min-width: 100px;
            }

            /* Quantity inputs in cart */
            #carrito-table input[type="number"] {
                width: 40px;
                padding: 2px;
                font-size: 10px;
            }

            /* Cart action buttons */
            #carrito-table .btn {
                padding: 2px 4px;
                font-size: 9px;
                min-width: 40px;
            }

            .totalizador h3 {
                font-size: 1.1rem;
                margin-top: 12px;
            }

            /* Buttons and actions */
            .btn {
                padding: 8px 16px;
                font-size: 13px;
                border-radius: 6px;
                min-width: 60px;
            }

            #finalizar-pedido {
                margin-top: 20px;
            }

            #finalizar-pedido .btn {
                width: 100%;
                padding: 12px;
                font-size: 14px;
            }

            /* Messages */
            .success-message, .error-message {
                padding: 12px;
                font-size: 13px;
                border-radius: 6px;
                margin: 12px 0;
            }

            /* URL sharing section */
            #pedido-url {
                margin-top: 16px;
                padding: 12px;
                border-radius: 6px;
            }

            #pedido-link {
                width: 100%;
                margin-bottom: 12px;
                padding: 8px;
                font-size: 12px;
            }

            /* Loading animations */
            .loading {
                padding: 16px;
                font-size: 13px;
            }
        }

        /* Extra small devices - phones in portrait */
        @media (max-width: 480px) {
            body {
                padding: 5px;
            }

            .container {
                padding: 8px;
            }

            h1 {
                font-size: 1.2rem;
            }

            .logo {
                height: 35px;
            }

            /* Make form elements even more touch-friendly */
            select, input[type="text"], input[type="number"] {
                padding: 12px;
                font-size: 16px; /* Prevent zoom on iOS */
            }

            /* Ultra-compact table for very small screens */
            th, td {
                padding: 4px 2px;
                font-size: 10px;
            }

            .talla-select, .cantidad-talla-container input[type="number"] {
                width: 35px;
                max-width: 35px;
                min-width: 35px;
                font-size: 7px;
            }

            /* Stack cart header elements */
            .cart-header {
                text-align: center;
            }

            .cart-header h2 {
                font-size: 1.1rem;
            }

            /* Total price more prominent on small screens */
            .totalizador h3 {
                font-size: 1.2rem;
                background: var(--vscode-bg);
                padding: 8px;
                border-radius: 6px;
                border: 1px solid var(--apple-blue);
            }
        }

        /* Sección de descuento */
        .descuento-section {
            margin: 16px 0;
            padding: 16px;
            background: var(--vscode-bg);
            border-radius: 8px;
            border: 1px solid var(--vscode-border);
        }

        .descuento-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .descuento-header label {
            font-weight: 600;
            color: var(--vscode-text);
            font-size: 14px;
        }

        .btn-toggle-descuento {
            background: var(--apple-blue);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-toggle-descuento:hover {
            background: var(--apple-blue-hover);
        }

        .btn-toggle-descuento.active {
            background: var(--apple-red);
        }

        .descuento-input-group {
            display: flex;
            align-items: center;
            gap: 0;
            margin-bottom: 8px;
            border: 1px solid var(--vscode-border);
            border-radius: 6px;
            background: var(--vscode-sidebar);
            overflow: hidden;
            transition: border-color 0.2s ease;
        }

        .descuento-input-group:focus-within {
            border-color: var(--apple-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.1);
        }

        .currency-symbol {
            padding: 10px 8px 10px 12px;
            background: var(--vscode-border);
            color: var(--vscode-text);
            font-weight: 600;
            border-right: 1px solid var(--vscode-border);
        }

        #descuento-input {
            flex: 1;
            padding: 10px 12px;
            border: none;
            background: transparent;
            color: var(--vscode-text);
            font-size: 14px;
            text-align: right;
            outline: none;
        }

        #descuento-input::placeholder {
            color: var(--vscode-text-muted);
        }

        .btn-clear-descuento {
            padding: 10px 12px;
            border: none;
            background: var(--apple-red);
            color: white;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: background 0.2s ease;
        }

        .btn-clear-descuento:hover {
            background: #e60026;
        }

        .descuento-error {
            color: var(--apple-red);
            font-size: 12px;
            margin-bottom: 4px;
            min-height: 16px;
        }

        .descuento-help {
            color: var(--vscode-text-muted);
            font-size: 11px;
            font-style: italic;
        }

        /* Sección de totales mejorada */
        .totales-section {
            margin-top: 16px;
            padding: 16px;
            background: var(--vscode-bg);
            border-radius: 8px;
            border: 1px solid var(--vscode-border);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 6px 0;
            font-size: 14px;
        }

        .subtotal-row {
            color: var(--vscode-text-muted);
        }

        .descuento-row {
            border-bottom: 1px solid var(--vscode-border);
            margin-bottom: 8px;
            padding-bottom: 8px;
        }

        .descuento-amount {
            color: var(--apple-red);
            font-weight: 600;
        }

        .total-final-row {
            font-size: 16px;
            font-weight: 700;
            color: var(--apple-blue);
            border-top: 2px solid var(--vscode-border);
            padding-top: 12px;
            margin-top: 8px;
        }

        .total-amount {
            font-size: 18px;
        }

        /* Estados de validación */
        .descuento-input-group.error {
            border-color: var(--apple-red);
            box-shadow: 0 0 0 2px rgba(255, 69, 58, 0.1);
        }

        .descuento-input-group.success {
            border-color: var(--apple-green);
            box-shadow: 0 0 0 2px rgba(48, 209, 88, 0.1);
        }

        /* Responsive para descuentos */
        @media (max-width: 768px) {
            .descuento-header {
                flex-direction: column;
                gap: 8px;
                align-items: stretch;
            }
            
            .btn-toggle-descuento {
                width: 100%;
            }
            
            .descuento-input-group {
                font-size: 16px; /* Evita zoom en iOS */
            }
            
            #descuento-input {
                font-size: 16px;
            }
            
            .total-row {
                font-size: 13px;
            }
            
            .total-final-row {
                font-size: 15px;
            }
            
            .total-amount {
                font-size: 16px;
            }
        }

        /* Landscape orientation adjustments */
        @media (max-width: 768px) and (orientation: landscape) {
            .header {
                flex-direction: row;
                justify-content: center;
                align-items: center;
                gap: 16px;
            }

            .logo {
                margin-right: 10px;
            }

            /* Slightly larger elements in landscape */
            .talla-select, .cantidad-talla-container input[type="number"] {
                width: 45px;
                max-width: 45px;
                min-width: 45px;
                font-size: 9px;
            }

            th, td {
                padding: 6px 4px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>    <div class="container">
        <div class="header">
            <img src="logo.png" class="logo" alt="Sequoia Speed">
            <h1>Orden de Pedido Manual</h1>
        </div>
    <div class="search-section">
        <h3>Buscar Productos</h3>
        <div class="form-row">
            <select id="categoria">
                <option value="">Selecciona una categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" id="busqueda" placeholder="Escribe el nombre del producto">
        </div>
    </div>
    <div id="productos-list">
        <p>Selecciona una categoría o escribe un nombre para buscar productos.</p>
    </div>

    <!-- Producto Personalizado -->
    <div id="producto-personalizado" style="margin-top: 24px;">
        <h3 style="margin-bottom: 16px; color: var(--vscode-text); font-size: 1.1rem; font-weight: 600;">Agregar Producto Personalizado</h3>
        <div class="table-container" style="border: 1px solid var(--vscode-border); border-radius: 8px;">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Precio</th>
                        <th>Talla</th>
                        <th>Cant</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <input type="text" id="custom-nombre" placeholder="Nombre del producto personalizado" style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); border-radius: 4px; background: var(--vscode-bg); color: var(--vscode-text);">
                        </td>
                        <td>
                            <input type="number" id="custom-precio" placeholder="0" min="0" step="0.01" style="padding: 8px; border: 1px solid var(--vscode-border); border-radius: 4px; background: var(--vscode-bg); color: var(--vscode-text);">
                        </td>
                        <td>
                            <div class="talla-selector">
                                <select class="talla-select" id="custom-talla">
                                    <option value="">Seleccionar talla</option>
                                    <option value="XS">XS</option>
                                    <option value="S">S</option>
                                    <option value="M">M</option>
                                    <option value="L">L</option>
                                    <option value="XL">XL</option>
                                    <option value="2XL">2XL</option>
                                    <option value="3XL">3XL</option>
                                    <option value="4XL">4XL</option>
                                    <option value="5XL">5XL</option>
                                </select>
                            </div>
                        </td>
                        <td>
                            <div class="cantidad-talla-container">
                                <input type="number" min="1" max="100" value="1" id="custom-cantidad">
                            </div>
                        </td>
                        <td>
                            <button class="btn" onclick="agregarProductoPersonalizado()" style="padding: 6px 8px; font-weight: bold; font-size: 16px; min-width: 32px;">+</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="carrito" class="totalizador">
        <div class="cart-header">
            <h2>Carrito de Compras</h2>
            <button class="clear-cart" onclick="limpiarCarrito()">Limpiar Carrito</button>
        </div>
        
        <table id="carrito-table">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Talla</th>
                    <th>Cantidad</th>
                    <th>Precio</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <tr><td colspan="6">Tu carrito está vacío.</td></tr>
            </tbody>
        </table>
        
        <!-- Sección de totales actualizada -->
        <div class="totales-section">
            <div class="total-row subtotal-row">
                <span>Subtotal:</span>
                <span>$<span id="subtotal">0</span></span>
            </div>
            <div class="total-row total-final-row">
                <span>Total:</span>
                <span class="total-amount">$<span id="total">0</span></span>
            </div>
        </div>
        
        <!-- SECCIÓN DE DESCUENTO - Movida debajo del total -->
        <div class="descuento-section">
            <div class="descuento-header">
                <label for="descuento-input">💰 Descuento:</label>
                <button type="button" class="btn-toggle-descuento" onclick="toggleDescuento()" id="toggle-desc-btn">
                    Aplicar Descuento
                </button>
            </div>
            
            <div id="descuento-container" style="display: none;">
                <div class="descuento-input-group">
                    <span class="currency-symbol">$</span>
                    <input 
                        type="text" 
                        id="descuento-input" 
                        placeholder="0"
                        maxlength="10"
                        oninput="validarDescuento(this)"
                        onblur="aplicarDescuento()"
                    >
                    <button type="button" class="btn-clear-descuento" onclick="limpiarDescuento()">✕</button>
                </div>
                <div id="descuento-error" class="descuento-error"></div>
                <div class="descuento-help">
                    Ingresa el monto sin puntos ni comas. Ej: 15000 para $15.000
                </div>
            </div>
            
            <!-- Mostrar descuento aplicado y total final -->
            <div class="total-row descuento-row" id="descuento-display" style="display: none;">
                <span>Descuento aplicado:</span>
                <span class="descuento-amount">-$<span id="descuento-monto">0</span></span>
            </div>
            <div class="total-row total-final-row" id="total-con-descuento-display" style="display: none;">
                <span><strong>Total Final:</strong></span>
                <span class="total-amount"><strong>$<span id="total-con-descuento">0</span></strong></span>
            </div>
        </div>
    </div>
    <div id="finalizar-pedido" style="margin-top:30px; text-align:center;">
        <button class="btn" onclick="finalizarPedido()">Finalizar</button>
    </div>
    <div id="pedido-url" style="display:none; margin-top:20px; text-align:center;">
        <p>Tu pedido ha sido generado. Comparte este enlace:</p>
        <input type="text" id="pedido-link" readonly style="width:80%;padding:8px;">
        <div style="margin-top:12px;">
            <button class="btn" onclick="copiarLink()">Copiar</button>
            <button class="btn btn-success" onclick="nuevoPedido()" style="margin-left:8px;">Nuevo Pedido</button>
        </div>
    </div>
</div>

<!-- Sistema de archivos CSS/JS optimizados -->

<script>
let carrito = [];
let productosPersonalizados = []; // Array para guardar productos personalizados que se deben crear en la DB
let descuentoAplicado = 0;
let descuentoVisible = false;

document.getElementById('categoria').addEventListener('change', cargarProductos);
document.getElementById('busqueda').addEventListener('input', cargarProductos);

// Listener para cambiar tallas dinámicamente según el nombre del producto personalizado
document.getElementById('custom-nombre').addEventListener('input', function() {
    actualizarTallasProductoPersonalizado();
});

// Función para actualizar las tallas del producto personalizado según el nombre
function actualizarTallasProductoPersonalizado() {
    const nombreProducto = document.getElementById('custom-nombre').value.toLowerCase();
    const tallaSelect = document.getElementById('custom-talla');

    // Detectar si es una bota
    const esBotas = nombreProducto.includes('bota') || nombreProducto.includes('zapato') ||
                   nombreProducto.includes('calzado') || nombreProducto.includes('zapatilla');

    // Limpiar opciones existentes
    tallaSelect.innerHTML = '<option value="">Seleccionar talla</option>';

    let tallasDisponibles;
    if (esBotas) {
        // Tallas numéricas para botas
        tallasDisponibles = ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45'];
    } else {
        // Tallas de letras para otros productos
        tallasDisponibles = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];
    }

    // Agregar las opciones de talla
    tallasDisponibles.forEach(talla => {
        const option = document.createElement('option');
        option.value = talla;
        option.textContent = talla;
        tallaSelect.appendChild(option);
    });
}

// Manejar scroll en tabla de productos personalizados para móviles
document.addEventListener('DOMContentLoaded', function() {
    if (window.innerWidth <= 768) {
        const customTableContainer = document.querySelector('#producto-personalizado .table-container');
        if (customTableContainer) {
            customTableContainer.addEventListener('scroll', function() {
                if (this.scrollLeft > 0) {
                    this.classList.add('scrolled');
                } else {
                    this.classList.remove('scrolled');
                }
            });
        }
    }
});

function agregarProductoPersonalizado() {
    const nombre = document.getElementById('custom-nombre').value.trim();
    const precio = parseFloat(document.getElementById('custom-precio').value);
    const cantidad = parseInt(document.getElementById('custom-cantidad').value);

    // Validaciones
    if (!nombre) {
        mostrarMensaje('Por favor ingresa el nombre del producto personalizado', 'error');
        return;
    }

    if (isNaN(precio) || precio <= 0) {
        mostrarMensaje('Por favor ingresa un precio válido', 'error');
        return;
    }

    if (isNaN(cantidad) || cantidad < 1) {
        mostrarMensaje('Por favor ingresa una cantidad válida', 'error');
        return;
    }

    // Verificar que hay una talla seleccionada
    const tallaSeleccionada = document.getElementById('custom-talla').value;
    if (!tallaSeleccionada) {
        mostrarMensaje('Por favor selecciona una talla para el producto personalizado', 'error');
        return;
    }

    const talla = tallaSeleccionada;

    // Crear ID único para producto personalizado (usar timestamp)
    const customId = 'custom_' + Date.now();
    const itemKey = `${customId}_${talla}`;

    // Verificar si ya existe en el carrito
    const index = carrito.findIndex(item => item.key === itemKey);

    if (index >= 0) {
        carrito[index].cantidad += cantidad;
    } else {
        carrito.push({
            key: itemKey,
            id: customId,
            nombre,
            precio,
            cantidad,
            talla,
            isCustom: true // Marcar como producto personalizado
        });

        // Guardar en array de productos personalizados para crear en DB después
        productosPersonalizados.push({
            id: customId,
            nombre: nombre,
            precio: precio,
            categoria: 'Personalizado'
        });
    }

    actualizarCarrito();

    // Limpiar campos
    document.getElementById('custom-nombre').value = '';
    document.getElementById('custom-precio').value = '';
    document.getElementById('custom-cantidad').value = 1;
    document.getElementById('custom-talla').value = '';

    mostrarMensaje(`${nombre} (Talla ${talla}) agregado al carrito`, 'success');
}



function cargarProductos() {
    const categoria = document.getElementById('categoria').value;
    const busqueda = document.getElementById('busqueda').value.trim();
    const productosList = document.getElementById('productos-list');

    // Mostrar mensaje de carga
    productosList.innerHTML = '<div class="loading">Cargando productos...</div>';

    // Llamada AJAX para obtener los productos - Sistema híbrido con auto-redirección
    const apiUrl = window.legacyCompatibility ?
        window.legacyCompatibility.resolveApiUrl('productos_por_categoria.php') :
        `productos_por_categoria.php`;

    fetch(`${apiUrl}?cat=${encodeURIComponent(categoria)}&search=${encodeURIComponent(busqueda)}`, {
        headers: {
            'X-Legacy-Compatibility': 'true'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.productos.length === 0) {
                productosList.innerHTML = '<p>No se encontraron productos.</p>';
                return;
            }

            // Detectar si es dispositivo móvil
            const isMobile = window.innerWidth <= 768;

            let html = '';
            if (isMobile) {
                html += '<div class="table-container">';
            }

            html += '<table><thead><tr><th>Nombre</th><th>Precio</th><th>Talla</th><th>Cant</th><th></th></tr></thead><tbody>';
            data.productos.forEach(producto => {
                // Determinar tipo de tallas según la categoría
                const esBotas = producto.categoria && producto.categoria.toLowerCase().includes('bota');
                const tallasDisponibles = esBotas
                    ? ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']
                    : ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];

                const tallasOptions = tallasDisponibles.map(talla =>
                    `<option value="${talla}">${talla}</option>`
                ).join('');

                html += `<tr>
                    <td>${producto.nombre}</td>
                    <td>$${producto.precio.toLocaleString()}</td>
                    <td>
                        <div class="talla-selector">
                            <select class="talla-select" id="talla_${producto.id}">
                                <option value="">Seleccionar talla</option>
                                ${tallasOptions}
                            </select>
                        </div>
                    </td>
                    <td>
                        <div class="cantidad-talla-container">
                            <input type="number" min="1" max="100" value="1" id="cantidad_${producto.id}">
                        </div>
                    </td>
                    <td><button class="btn" onclick="agregarAlCarrito(${producto.id}, '${producto.nombre}', ${producto.precio})" style="padding: 6px 8px; font-weight: bold; font-size: 16px; min-width: 32px;">+</button></td>
                </tr>`;
            });
            html += '</tbody></table>';

            if (isMobile) {
                html += '</div>';
            }

            productosList.innerHTML = html;

            // Agregar listener para detectar scroll en móviles
            if (isMobile) {
                const tableContainer = productosList.querySelector('.table-container');
                if (tableContainer) {
                    tableContainer.addEventListener('scroll', function() {
                        if (this.scrollLeft > 0) {
                            this.classList.add('scrolled');
                        } else {
                            this.classList.remove('scrolled');
                        }
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error al cargar los productos:', error);
            productosList.innerHTML = '<div class="error-message">Error al cargar los productos. Intenta nuevamente.</div>';
        });
}

function agregarAlCarrito(id, nombre, precio) {
    // Verificar que hay una talla seleccionada
    const tallaSelect = document.getElementById(`talla_${id}`);
    const tallaSeleccionada = tallaSelect.value;
    if (!tallaSeleccionada) {
        mostrarMensaje('Por favor selecciona una talla antes de agregar al carrito', 'error');
        return;
    }

    const talla = tallaSeleccionada;
    const cantidad = parseInt(document.getElementById(`cantidad_${id}`).value);
    if (isNaN(cantidad) || cantidad < 1) {
        mostrarMensaje('Por favor ingresa una cantidad válida', 'error');
        return;
    }

    // Crear una clave única que incluya la talla
    const itemKey = `${id}_${talla}`;
    const index = carrito.findIndex(item => item.key === itemKey);

    if (index >= 0) {
        carrito[index].cantidad += cantidad;
    } else {
        carrito.push({
            key: itemKey,
            id,
            nombre,
            precio,
            cantidad,
            talla
        });
    }

    actualizarCarrito();

    // Limpiar selección después de agregar
    tallaSelect.value = '';
    document.getElementById(`cantidad_${id}`).value = 1;

    mostrarMensaje(`${nombre} (Talla ${talla}) agregado al carrito`, 'success');
}

function actualizarCarrito() {
    const carritoTable = document.getElementById('carrito-table').querySelector('tbody');
    const subtotalElement = document.getElementById('subtotal');
    const totalElement = document.getElementById('total');
    const descuentoElement = document.getElementById('descuento-monto');
    const descuentoDisplay = document.getElementById('descuento-display');
    const totalConDescuentoElement = document.getElementById('total-con-descuento');
    const totalConDescuentoDisplay = document.getElementById('total-con-descuento-display');
    
    carritoTable.innerHTML = '';
    let subtotal = 0;

    carrito.forEach((item, index) => {
        const itemSubtotal = item.precio * item.cantidad;
        subtotal += itemSubtotal;
        carritoTable.innerHTML += `
            <tr>
                <td>${item.nombre}</td>
                <td>${item.talla || 'N/A'}</td>
                <td>
                    <div class="cantidad-talla-container">
                        <input type="number" min="1" value="${item.cantidad}" onchange="modificarCantidad(${index}, this.value)">
                    </div>
                </td>
                <td>$${item.precio.toLocaleString()}</td>
                <td>$${itemSubtotal.toLocaleString()}</td>
                <td><button class="btn btn-danger" onclick="eliminarDelCarrito(${index})" style="padding: 6px 8px; font-weight: bold; font-size: 16px; min-width: 32px;">-</button></td>
            </tr>
        `;
    });

    if (carrito.length === 0) {
        carritoTable.innerHTML = '<tr><td colspan="6">Tu carrito está vacío.</td></tr>';
        // Si no hay productos, ocultar descuento y resetear
        if (descuentoVisible) {
            toggleDescuento();
        }
    }

    // Validar descuento actual contra nuevo subtotal
    if (descuentoAplicado > subtotal) {
        descuentoAplicado = subtotal;
        document.getElementById('descuento-input').value = subtotal.toLocaleString();
        if (subtotal > 0) {
            mostrarMensaje('Descuento ajustado al subtotal máximo', 'error');
        }
    }

    // Calcular total inicial (sin descuento)
    const totalSinDescuento = subtotal;

    // Actualizar displays principales
    subtotalElement.textContent = subtotal.toLocaleString();
    totalElement.textContent = totalSinDescuento.toLocaleString();
    
    // Manejar displays de descuento
    if (descuentoAplicado > 0) {
        descuentoDisplay.style.display = 'flex';
        totalConDescuentoDisplay.style.display = 'flex';
        descuentoElement.textContent = descuentoAplicado.toLocaleString();
        
        // Calcular total final con descuento
        const totalFinal = Math.max(0, subtotal - descuentoAplicado);
        totalConDescuentoElement.textContent = totalFinal.toLocaleString();
    } else {
        descuentoDisplay.style.display = 'none';
        totalConDescuentoDisplay.style.display = 'none';
    }
}

function modificarCantidad(index, nuevaCantidad) {
    nuevaCantidad = parseInt(nuevaCantidad);
    if (isNaN(nuevaCantidad) || nuevaCantidad < 1) return;
    carrito[index].cantidad = nuevaCantidad;
    actualizarCarrito();
}

function eliminarDelCarrito(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}

function limpiarCarrito() {
    if (carrito.length === 0) return;
    if (confirm('¿Estás seguro de que quieres vaciar el carrito?')) {
        carrito = [];
        limpiarDescuento(); // También limpiar descuento
        actualizarCarrito();
    }
}

function finalizarPedido() {
    if (carrito.length === 0) {
        mostrarMensaje('El carrito está vacío.', 'error');
        return;
    }

    // Validación final del descuento
    const subtotal = calcularSubtotal();
    if (descuentoAplicado > subtotal) {
        mostrarMensaje('Error: El descuento no puede ser mayor al subtotal', 'error');
        return;
    }

    const total = Math.max(0, subtotal - descuentoAplicado);

    // Preparar datos para enviar al endpoint
    const carritoParaEnviar = carrito.map(item => ({
        id: parseInt(item.id) || 0, // Asegurar que el ID sea un entero
        nombre: item.nombre,
        precio: parseFloat(item.precio),
        cantidad: parseInt(item.cantidad),
        talla: item.talla || 'N/A',
        personalizado: item.isCustom || false
    }));

    // Mostrar mensaje de procesamiento
    mostrarMensaje('Guardando pedido...', 'success');
    const finalizarBtn = document.querySelector('#finalizar-pedido .btn');
    const originalText = finalizarBtn.textContent;
    finalizarBtn.disabled = true;
    finalizarBtn.textContent = 'Procesando...';

    // Enviar al endpoint de guardado - Sistema híbrido con auto-redirección
    const saveApiUrl = window.legacyCompatibility ?
        window.legacyCompatibility.resolveApiUrl('guardar_pedido.php') :
        'guardar_pedido.php';

    fetch(saveApiUrl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Legacy-Compatibility': 'true'
        },
        body: JSON.stringify({
            carrito: carritoParaEnviar,
            monto: total,
            descuento: descuentoAplicado,
            subtotal: subtotal // Para verificación adicional
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Pedido guardado exitosamente, mostrar la URL
            mostrarUrlPedido(data.pedido_id);
        } else {
            mostrarMensaje('Error al guardar el pedido: ' + (data.error || 'Error desconocido'), 'error');
            finalizarBtn.disabled = false;
            finalizarBtn.textContent = originalText;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        mostrarMensaje('Error de conexión al guardar el pedido', 'error');
        finalizarBtn.disabled = false;
        finalizarBtn.textContent = originalText;
    });
}

function mostrarUrlPedido(pedidoId) {
    // Ocultar el botón de finalizar
    document.getElementById('finalizar-pedido').style.display = 'none';

    // Generar la URL del pedido
    const baseUrl = window.location.origin + window.location.pathname.replace('orden_pedido.php', 'pedido.php');
    const pedidoUrl = `${baseUrl}?pedido=${pedidoId}`;

    // Mostrar la sección de URL
    const urlSection = document.getElementById('pedido-url');
    const urlInput = document.getElementById('pedido-link');

    urlInput.value = pedidoUrl;
    urlSection.style.display = 'block';

    // Mensaje de éxito
    mostrarMensaje('¡Pedido guardado exitosamente! Puedes compartir el enlace generado.', 'success');

    // Limpiar el carrito
    carrito = [];
    productosPersonalizados = [];
    actualizarCarrito();
}

function mostrarMensaje(texto, tipo) {
    // Remover mensajes anteriores
    const mensajesAnteriores = document.querySelectorAll('.success-message, .error-message');
    mensajesAnteriores.forEach(msg => msg.remove());

    const mensaje = document.createElement('div');
    mensaje.className = tipo === 'success' ? 'success-message' : 'error-message';
    mensaje.textContent = texto;

    // Insertar después del carrito
    const carrito = document.getElementById('carrito');
    carrito.parentNode.insertBefore(mensaje, carrito.nextSibling);

    // Auto-remover después de 5 segundos
    setTimeout(() => {
        if (mensaje.parentNode) {
            mensaje.remove();
        }
    }, 5000);
}

function copiarLink() {
    const input = document.getElementById('pedido-link');
    input.select();
    input.setSelectionRange(0, 99999);

    try {
        document.execCommand('copy');
        const btn = event.target;
        const originalText = btn.textContent;
        btn.textContent = '¡Copiado!';
        btn.style.background = 'var(--apple-green)';

        setTimeout(() => {
            btn.textContent = originalText;
            btn.style.background = 'var(--apple-blue)';
        }, 2000);
    } catch (err) {
        console.error('Error al copiar:', err);
        mostrarMensaje('Error al copiar el enlace', 'error');
    }
}

function nuevoPedido() {
    // Confirmar si el usuario realmente quiere limpiar todo
    if (confirm('¿Estás seguro de que quieres empezar un nuevo pedido? Se perderá el enlace actual.')) {
        // Limpiar todo y reiniciar el formulario
        carrito = [];
        productosPersonalizados = [];

        // Limpiar formularios
        document.getElementById('categoria').selectedIndex = 0;
        document.getElementById('busqueda').value = '';
        document.getElementById('custom-nombre').value = '';
        document.getElementById('custom-precio').value = '';
        document.getElementById('custom-talla').value = '';
        document.getElementById('custom-cantidad').value = '1';

        // Limpiar lista de productos
        document.getElementById('productos-list').innerHTML = '<p>Selecciona una categoría o escribe un nombre para buscar productos.</p>';

        // Actualizar carrito vacío
        actualizarCarrito();

        // Ocultar la sección de URL y mostrar el botón de finalizar
        document.getElementById('pedido-url').style.display = 'none';
        document.getElementById('finalizar-pedido').style.display = 'block';

        // Remover cualquier mensaje de éxito/error
        const mensajes = document.querySelectorAll('.success-message, .error-message');
        mensajes.forEach(msg => msg.remove());

        // Mostrar mensaje de confirmación
        mostrarMensaje('Formulario limpiado. Puedes empezar un nuevo pedido.', 'success');

        // Hacer scroll hacia arriba para que el usuario vea el formulario limpio
        document.querySelector('.search-section').scrollIntoView({ behavior: 'smooth' });
    }
}

// FUNCIONES DE DESCUENTO

// Función para mostrar/ocultar el campo de descuento
function toggleDescuento() {
    const container = document.getElementById('descuento-container');
    const btn = document.getElementById('toggle-desc-btn');
    
    descuentoVisible = !descuentoVisible;
    
    if (descuentoVisible) {
        container.style.display = 'block';
        btn.textContent = 'Cancelar';
        btn.classList.add('active');
        
        // Focus en el input después de un pequeño delay
        setTimeout(() => {
            document.getElementById('descuento-input').focus();
        }, 100);
    } else {
        container.style.display = 'none';
        btn.textContent = 'Aplicar Descuento';
        btn.classList.remove('active');
        limpiarDescuento();
    }
}

// Función para validar el descuento mientras se escribe
function validarDescuento(input) {
    const value = input.value;
    const errorDiv = document.getElementById('descuento-error');
    const inputGroup = input.parentElement;
    
    // Limpiar estilos previos
    inputGroup.classList.remove('error', 'success');
    errorDiv.textContent = '';
    
    // Permitir solo números
    const numbersOnly = value.replace(/[^0-9]/g, '');
    if (value !== numbersOnly) {
        input.value = numbersOnly;
    }
    
    // Validaciones en tiempo real
    if (numbersOnly === '') {
        return; // Campo vacío, no mostrar error
    }
    
    const monto = parseInt(numbersOnly);
    
    // Validar que no sea negativo o cero
    if (monto <= 0) {
        mostrarErrorDescuento('El descuento debe ser mayor a $0', inputGroup);
        return false;
    }
    
    // Validar que no sea mayor al subtotal
    const subtotal = calcularSubtotal();
    if (monto > subtotal) {
        mostrarErrorDescuento(`El descuento no puede ser mayor al subtotal ($${subtotal.toLocaleString()})`, inputGroup);
        return false;
    }
    
    // Validar monto máximo razonable (ej: $500,000)
    if (monto > 500000) {
        mostrarErrorDescuento('El descuento no puede ser mayor a $500.000', inputGroup);
        return false;
    }
    
    // Si llega aquí, el monto es válido
    inputGroup.classList.add('success');
    return true;
}

// Función para aplicar el descuento validado
function aplicarDescuento() {
    const input = document.getElementById('descuento-input');
    const value = input.value.trim();
    
    if (value === '') {
        descuentoAplicado = 0;
        actualizarCarrito();
        return;
    }
    
    if (validarDescuento(input)) {
        const monto = parseInt(value);
        descuentoAplicado = monto;
        
        // Formatear el input para mejor UX
        input.value = monto.toLocaleString();
        
        actualizarCarrito();
        
        // Mensaje de éxito
        mostrarMensaje(`Descuento de $${monto.toLocaleString()} aplicado correctamente`, 'success');
    }
}

// Función para limpiar el descuento
function limpiarDescuento() {
    document.getElementById('descuento-input').value = '';
    document.getElementById('descuento-error').textContent = '';
    document.getElementById('descuento-input').parentElement.classList.remove('error', 'success');
    descuentoAplicado = 0;
    actualizarCarrito();
}

// Función auxiliar para mostrar errores
function mostrarErrorDescuento(mensaje, inputGroup) {
    document.getElementById('descuento-error').textContent = mensaje;
    inputGroup.classList.add('error');
}

// Función auxiliar para calcular subtotal
function calcularSubtotal() {
    let subtotal = 0;
    carrito.forEach(item => {
        subtotal += item.precio * item.cantidad;
    });
    return subtotal;
}

// Event listeners adicionales
document.addEventListener('DOMContentLoaded', function() {
    // Permitir Enter para aplicar descuento
    document.getElementById('descuento-input').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            this.blur(); // Trigger onblur que llama aplicarDescuento
        }
    });
    
    // Prevenir scroll al cambiar cantidad con rueda del mouse
    document.getElementById('descuento-input').addEventListener('wheel', function(e) {
        e.preventDefault();
    });
});
</script>
</body>
</html>
