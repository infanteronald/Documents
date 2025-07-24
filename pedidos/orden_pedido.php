<?php
require_once 'config_secure.php';
require_once 'php82_helpers.php';

// Requerir autenticación
require_once 'accesos/auth_helper.php';

// Proteger la página - requiere permisos de creación en ventas
$current_user = auth_require('ventas', 'crear');

// Registrar acceso
auth_log('read', 'ventas', 'Acceso a formulario de creación de pedido');

// Obtener categorías
$categorias = [];
$sql_cat = "SELECT c.id, c.nombre, c.icono 
            FROM categorias_productos c
            INNER JOIN productos p ON p.categoria_id = c.id
            WHERE c.activa = 1 AND p.activo = 1
            GROUP BY c.id, c.nombre, c.icono, c.orden
            ORDER BY c.orden ASC, c.nombre ASC";
$res_cat = $conn->query($sql_cat);
while ($row = $res_cat->fetch_assoc()) {
    $categorias[] = [
        'id' => $row['id'],
        'nombre' => $row['nombre'],
        'icono' => $row['icono']
    ];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Pedido - Sequoia Speed</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#1e1e1e">
    <style>
        /* =================================================================
           RESPONSIVE DESIGN SYSTEM - MOBILE FIRST WITH DESKTOP FALLBACK
           ================================================================= */
        
        /* CSS Variables */
        :root {
            --primary-bg: #1e1e1e;
            --secondary-bg: #252526;
            --surface-bg: #2d2d30;
            --border-color: #3e3e42;
            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --text-muted: #656d76;
            --accent-blue: #007aff;
            --accent-blue-hover: #0056d3;
            --accent-green: #30d158;
            --accent-red: #ff453a;
            --accent-orange: #ff9500;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 8px 32px rgba(0, 0, 0, 0.4);
            --border-radius: 12px;
            --border-radius-sm: 8px;
            --spacing-xs: 8px;
            --spacing-sm: 12px;
            --spacing-md: 16px;
            --spacing-lg: 24px;
            --spacing-xl: 32px;
            --font-size-xs: 12px;
            --font-size-sm: 14px;
            --font-size-base: 16px;
            --font-size-lg: 18px;
            --font-size-xl: 20px;
            --font-size-2xl: 24px;
            --touch-target: 44px;
            
            /* VSCode Legacy Variables for Desktop */
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

        /* Base Styles - Mobile First */
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: rgba(0, 122, 255, 0.2);
            -webkit-touch-callout: none;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            background: var(--primary-bg);
            color: var(--text-primary);
            line-height: 1.5;
            min-height: 100vh;
            font-size: var(--font-size-base);
            overflow-x: hidden;
        }

        /* =================================================================
           MOBILE LAYOUT CONTAINER
           ================================================================= */
        
        .mobile-container {
            width: 100%;
            max-width: 100vw;
            margin: 0;
            padding: 0;
            background: var(--primary-bg);
            min-height: 100vh;
        }

        /* =================================================================
           HEADER - MOBILE OPTIMIZED
           ================================================================= */
        
        .mobile-header {
            background: var(--secondary-bg);
            padding: var(--spacing-md) var(--spacing-md) var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-sm);
            position: relative;
        }

        .back-btn {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            background: var(--accent-blue);
            color: white;
            text-decoration: none;
            width: var(--touch-target);
            height: var(--touch-target);
            border-radius: var(--border-radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: var(--font-size-lg);
            transition: all 0.2s ease;
            box-shadow: var(--shadow);
        }

        .back-btn:hover,
        .back-btn:active {
            background: var(--accent-blue-hover);
            transform: translateY(-50%) scale(1.05);
        }

        .logo {
            height: 36px;
            width: auto;
            object-fit: contain;
        }

        .header-title {
            font-size: var(--font-size-lg);
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
            text-align: center;
        }

        /* =================================================================
           MOBILE SECTIONS
           ================================================================= */
        
        .mobile-section {
            padding: var(--spacing-md);
            background: var(--secondary-bg);
            margin: var(--spacing-xs) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .section-title {
            font-size: var(--font-size-base);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--spacing-sm) 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        /* =================================================================
           MOBILE FORMS
           ================================================================= */
        
        .form-group {
            margin-bottom: var(--spacing-md);
        }

        .form-label {
            display: block;
            font-size: var(--font-size-sm);
            font-weight: 500;
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
        }

        .form-control {
            width: 100%;
            min-height: var(--touch-target);
            padding: var(--spacing-sm) var(--spacing-md);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: var(--surface-bg);
            color: var(--text-primary);
            font-size: var(--font-size-base);
            font-family: inherit;
            transition: all 0.2s ease;
            -webkit-appearance: none;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
            background: var(--primary-bg);
        }

        .form-control::placeholder {
            color: var(--text-muted);
        }

        .form-row {
            display: flex;
            gap: var(--spacing-sm);
        }

        .form-row .form-control {
            flex: 1;
        }

        /* =================================================================
           MOBILE BUTTONS
           ================================================================= */
        
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: var(--touch-target);
            padding: var(--spacing-sm) var(--spacing-md);
            border: none;
            border-radius: var(--border-radius-sm);
            background: var(--accent-blue);
            color: white;
            font-size: var(--font-size-base);
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            text-align: center;
            touch-action: manipulation;
            user-select: none;
        }

        .btn:hover {
            background: var(--accent-blue-hover);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
            box-shadow: inset 0 1px 2px rgba(0, 0, 0, 0.2);
        }

        .btn-primary {
            background: var(--accent-blue);
        }

        .btn-success {
            background: var(--accent-green);
        }

        .btn-danger {
            background: var(--accent-red);
        }

        .btn-orange {
            background: var(--accent-orange);
        }

        .btn-small {
            min-height: 36px;
            padding: var(--spacing-xs) var(--spacing-sm);
            font-size: var(--font-size-sm);
        }

        .btn-icon {
            width: var(--touch-target);
            height: var(--touch-target);
            padding: 0;
            border-radius: 50%;
            font-size: var(--font-size-lg);
            font-weight: 700;
        }

        .btn-full {
            width: 100%;
        }

        /* =================================================================
           MOBILE PRODUCT LISTS
           ================================================================= */
        
        .products-container {
            padding: 0;
        }

        .product-item {
            background: var(--surface-bg);
            margin: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--border-radius-sm);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .product-header {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
        }

        .product-name {
            font-size: var(--font-size-base);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0 0 var(--spacing-xs) 0;
        }

        .product-price {
            font-size: var(--font-size-lg);
            font-weight: 700;
            color: var(--accent-blue);
            margin: 0;
        }

        .product-controls {
            padding: var(--spacing-md);
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: var(--spacing-sm);
            align-items: end;
        }

        .control-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .control-label {
            font-size: var(--font-size-xs);
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .size-select, .quantity-input {
            width: 100%;
            min-height: 40px;
            padding: var(--spacing-xs);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            background: var(--primary-bg);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
            text-align: center;
        }

        .size-select:focus, .quantity-input:focus {
            outline: none;
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.1);
        }

        /* =================================================================
           MOBILE CART
           ================================================================= */
        
        .cart-section {
            background: var(--secondary-bg);
            margin-top: var(--spacing-sm);
        }

        .cart-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
        }

        .cart-title {
            font-size: var(--font-size-lg);
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
        }

        .cart-badge {
            background: var(--accent-red);
            color: white;
            font-size: var(--font-size-xs);
            font-weight: 700;
            padding: 4px 8px;
            border-radius: 12px;
            margin-left: var(--spacing-xs);
        }

        .cart-items {
            padding: 0;
        }

        .cart-item {
            padding: var(--spacing-md);
            border-bottom: 1px solid var(--border-color);
            display: grid;
            grid-template-columns: 1fr auto;
            gap: var(--spacing-md);
            align-items: center;
        }

        .cart-item-info {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-xs);
        }

        .cart-item-name {
            font-size: var(--font-size-base);
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .cart-item-details {
            display: flex;
            gap: var(--spacing-md);
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
        }

        .cart-item-price {
            font-size: var(--font-size-base);
            font-weight: 700;
            color: var(--accent-blue);
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }

        .quantity-control {
            display: flex;
            align-items: center;
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
        }

        .quantity-btn {
            width: 32px;
            height: 32px;
            border: none;
            background: var(--surface-bg);
            color: var(--text-primary);
            font-size: var(--font-size-base);
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-btn:hover {
            background: var(--border-color);
        }

        .quantity-display {
            width: 40px;
            height: 32px;
            border: none;
            background: transparent;
            color: var(--text-primary);
            text-align: center;
            font-size: var(--font-size-sm);
            font-weight: 600;
        }

        .empty-cart {
            padding: var(--spacing-xl);
            text-align: center;
            color: var(--text-muted);
        }

        .empty-cart-icon {
            font-size: 48px;
            margin-bottom: var(--spacing-md);
            opacity: 0.5;
        }

        /* =================================================================
           MOBILE TOTALS & DISCOUNT
           ================================================================= */
        
        .totals-section {
            background: var(--surface-bg);
            margin: var(--spacing-xs) var(--spacing-md);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .totals-header {
            padding: var(--spacing-md);
            background: var(--primary-bg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .totals-body {
            padding: var(--spacing-md);
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-xs) 0;
            font-size: var(--font-size-base);
        }

        .total-row.subtotal {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
        }

        .total-row.discount {
            color: var(--accent-red);
            border-bottom: 1px solid var(--border-color);
            padding-bottom: var(--spacing-sm);
            margin-bottom: var(--spacing-sm);
        }

        .total-row.final {
            font-size: var(--font-size-xl);
            font-weight: 700;
            color: var(--accent-green);
            border-top: 2px solid var(--border-color);
            padding-top: var(--spacing-md);
            margin-top: var(--spacing-md);
        }

        /* Discount Input */
        .discount-section {
            margin-top: var(--spacing-md);
            padding-top: var(--spacing-md);
            border-top: 1px solid var(--border-color);
        }

        .discount-toggle {
            width: 100%;
            background: transparent;
            border: 1px dashed var(--border-color);
            color: var(--text-secondary);
            padding: var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            font-size: var(--font-size-sm);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .discount-toggle:hover {
            border-color: var(--accent-blue);
            color: var(--text-primary);
        }

        .discount-input-group {
            display: flex;
            margin-top: var(--spacing-sm);
            background: var(--primary-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            overflow: hidden;
        }

        .discount-input-group.active {
            border-color: var(--accent-blue);
            box-shadow: 0 0 0 2px rgba(0, 122, 255, 0.1);
        }

        .currency-symbol {
            padding: var(--spacing-sm);
            background: var(--border-color);
            color: var(--text-primary);
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .discount-input {
            flex: 1;
            border: none;
            background: transparent;
            color: var(--text-primary);
            padding: var(--spacing-sm);
            font-size: var(--font-size-base);
        }

        .discount-input:focus {
            outline: none;
        }

        .discount-clear {
            border: none;
            background: var(--accent-red);
            color: white;
            padding: var(--spacing-sm);
            cursor: pointer;
            font-size: var(--font-size-sm);
        }

        /* =================================================================
           MOBILE CUSTOM PRODUCT
           ================================================================= */
        
        .custom-product-section {
            background: var(--secondary-bg);
            margin: var(--spacing-xs) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .custom-product-form {
            padding: var(--spacing-md);
            display: grid;
            gap: var(--spacing-md);
        }

        .custom-product-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: var(--spacing-sm);
        }

        /* =================================================================
           MOBILE ACTIONS
           ================================================================= */
        
        .bottom-actions {
            position: sticky;
            bottom: 0;
            background: var(--secondary-bg);
            border-top: 1px solid var(--border-color);
            padding: var(--spacing-md);
            z-index: 50;
        }

        .action-buttons {
            display: grid;
            gap: var(--spacing-sm);
        }

        .primary-action {
            background: linear-gradient(135deg, var(--accent-blue), var(--accent-blue-hover));
            font-size: var(--font-size-lg);
            font-weight: 700;
            min-height: 56px;
            box-shadow: var(--shadow);
        }

        /* =================================================================
           MOBILE SUCCESS/ERROR STATES
           ================================================================= */
        
        .success-section, .error-section {
            padding: var(--spacing-xl);
            text-align: center;
            background: var(--secondary-bg);
            margin: var(--spacing-xs) 0;
            border-bottom: 1px solid var(--border-color);
        }

        .success-section {
            border-left: 4px solid var(--accent-green);
        }

        .error-section {
            border-left: 4px solid var(--accent-red);
        }

        .success-icon, .error-icon {
            font-size: 48px;
            margin-bottom: var(--spacing-md);
        }

        .success-icon {
            color: var(--accent-green);
        }

        .error-icon {
            color: var(--accent-red);
        }

        .success-title, .error-title {
            font-size: var(--font-size-xl);
            font-weight: 700;
            margin: 0 0 var(--spacing-sm) 0;
        }

        .success-title {
            color: var(--accent-green);
        }

        .error-title {
            color: var(--accent-red);
        }

        .url-share {
            background: var(--surface-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-md);
            margin: var(--spacing-md) 0;
        }

        .url-input {
            width: 100%;
            background: var(--primary-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-sm);
            padding: var(--spacing-sm);
            color: var(--text-primary);
            font-size: var(--font-size-sm);
            text-align: center;
            margin-bottom: var(--spacing-md);
        }

        /* =================================================================
           MOBILE TOAST MESSAGES
           ================================================================= */
        
        .toast {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            max-width: 90vw;
            padding: var(--spacing-md) var(--spacing-lg);
            border-radius: var(--border-radius);
            color: white;
            font-weight: 600;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            animation: toastSlideUp 0.3s ease-out;
        }

        .toast.success {
            background: var(--accent-green);
        }

        .toast.error {
            background: var(--accent-red);
        }

        @keyframes toastSlideUp {
            from {
                transform: translate(-50%, 100px);
                opacity: 0;
            }
            to {
                transform: translate(-50%, 0);
                opacity: 1;
            }
        }

        /* =================================================================
           LOADING STATES
           ================================================================= */
        
        .loading {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-xl);
            color: var(--text-muted);
        }

        .loading-spinner {
            width: 24px;
            height: 24px;
            border: 2px solid var(--border-color);
            border-top: 2px solid var(--accent-blue);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: var(--spacing-sm);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* =================================================================
           ACCESSIBILITY IMPROVEMENTS
           ================================================================= */
        
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* Focus states for keyboard navigation */
        .btn:focus-visible,
        .form-control:focus-visible {
            outline: 2px solid var(--accent-blue);
            outline-offset: 2px;
        }

        /* =================================================================
           UTILITY CLASSES
           ================================================================= */
        
        .hidden { display: none !important; }
        .visible { display: block !important; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        .font-bold { font-weight: 700; }
        .font-medium { font-weight: 600; }
        .text-muted { color: var(--text-muted); }
        .text-primary { color: var(--text-primary); }
        .text-blue { color: var(--accent-blue); }
        .text-green { color: var(--accent-green); }
        .text-red { color: var(--accent-red); }

        /* =================================================================
           LANDSCAPE TABLET OPTIMIZATIONS
           ================================================================= */
        
        @media (min-width: 768px) and (orientation: landscape) and (max-width: 1023px) {
            .mobile-container {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .custom-product-row {
                grid-template-columns: 2fr 1fr 1fr 1fr;
            }
            
            .action-buttons {
                grid-template-columns: 1fr 1fr;
                max-width: 400px;
                margin: 0 auto;
            }
        }

        /* =================================================================
           DESKTOP MODE - RESTORE PREVIOUS INTERFACE FOR PC
           ================================================================= */
        
        @media (min-width: 1024px) {
            /* Hide mobile interface on desktop */
            .mobile-container,
            .mobile-header,
            .mobile-section,
            .custom-product-section,
            .cart-section,
            .totals-section,
            .bottom-actions,
            .success-section {
                display: none !important;
            }

            /* Desktop body styles */
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background: var(--vscode-bg);
                color: var(--vscode-text);
                line-height: 1.6;
            }

            /* Desktop container */
            .desktop-container {
                display: block !important;
                max-width: 1000px;
                margin: 0 auto;
                background: var(--vscode-sidebar);
                border-radius: 12px;
                padding: 24px;
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }

            /* Desktop header */
            .desktop-header {
                display: flex !important;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 24px;
                padding-bottom: 16px;
                border-bottom: 1px solid var(--vscode-border);
            }

            .desktop-header-left,
            .desktop-header-right {
                flex: 0 0 200px;
            }

            .desktop-header-center {
                display: flex;
                align-items: center;
                justify-content: center;
                flex: 1;
            }

            .desktop-back-btn {
                background: var(--apple-blue);
                color: white;
                text-decoration: none;
                padding: 10px 16px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .desktop-back-btn:hover {
                background: var(--apple-blue-hover);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            }

            .desktop-header-center .logo {
                height: 50px;
                width: auto;
                margin-right: 15px;
                object-fit: contain;
            }

            .desktop-header-center h1 {
                font-size: 2rem;
                font-weight: 600;
                margin: 0;
                color: var(--vscode-text);
            }

            /* Desktop forms */
            .desktop-search {
                display: block !important;
                margin-bottom: 24px;
            }

            .desktop-search h3 {
                color: var(--vscode-text);
                font-size: 1.1rem;
                font-weight: 600;
                margin-bottom: 16px;
            }

            .desktop-form-row {
                display: flex;
                gap: 16px;
                margin-bottom: 24px;
            }

            .desktop-form-row select,
            .desktop-form-row input[type="text"],
            .desktop-form-row input[type="number"] {
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

            .desktop-form-row select:focus,
            .desktop-form-row input:focus {
                outline: none;
                border-color: var(--apple-blue);
                box-shadow: 0 0 0 3px rgba(0, 122, 255, 0.1);
            }

            /* Desktop tables */
            .desktop-table-container {
                display: block !important;
                position: relative;
                overflow-x: auto;
                border-radius: 8px;
                border: 1px solid var(--vscode-border);
                margin-top: 20px;
            }

            .desktop-table {
                display: table !important;
                width: 100%;
                border-collapse: collapse;
                background: var(--vscode-bg);
                table-layout: fixed;
            }

            .desktop-table th,
            .desktop-table td {
                padding: 12px 16px;
                text-align: left;
                border-bottom: 1px solid var(--vscode-border);
                vertical-align: middle;
            }

            .desktop-table th {
                background: var(--vscode-sidebar);
                font-weight: 600;
                color: var(--vscode-text);
                font-size: 14px;
            }

            .desktop-table td {
                font-size: 14px;
            }

            .desktop-table tr:hover {
                background: rgba(255, 255, 255, 0.05);
            }

            /* Desktop column widths */
            .desktop-table th:nth-child(1), .desktop-table td:nth-child(1) { width: 70%; }
            .desktop-table th:nth-child(2), .desktop-table td:nth-child(2) { width: 12%; }
            .desktop-table th:nth-child(3), .desktop-table td:nth-child(3) { width: 6%; padding: 8px 4px; }
            .desktop-table th:nth-child(4), .desktop-table td:nth-child(4) { width: 6%; padding: 8px 4px; }
            .desktop-table th:nth-child(5), .desktop-table td:nth-child(5) { width: 6%; padding: 8px 4px; text-align: center; }

            /* Desktop cart table */
            .desktop-cart-table th:nth-child(1), .desktop-cart-table td:nth-child(1) { width: 55%; }
            .desktop-cart-table th:nth-child(2), .desktop-cart-table td:nth-child(2) { width: 9%; text-align: center; }
            .desktop-cart-table th:nth-child(3), .desktop-cart-table td:nth-child(3) { width: 9%; text-align: center; }
            .desktop-cart-table th:nth-child(4), .desktop-cart-table td:nth-child(4) { width: 11.5%; text-align: right; }
            .desktop-cart-table th:nth-child(5), .desktop-cart-table td:nth-child(5) { width: 11.5%; text-align: right; }
            .desktop-cart-table th:nth-child(6), .desktop-cart-table td:nth-child(6) { width: 4%; text-align: center; }

            /* Desktop form elements */
            .talla-select {
                width: 45px;
                max-width: 45px;
                padding: 2px 1px;
                border: 1px solid var(--vscode-border);
                border-radius: 3px;
                background: var(--vscode-bg);
                color: var(--vscode-text);
                font-size: 9px;
                text-align: center;
            }

            .cantidad-input {
                width: 45px;
                max-width: 45px;
                padding: 2px 1px;
                font-size: 9px;
                text-align: center;
                border: 1px solid var(--vscode-border);
                background: var(--vscode-bg);
                color: var(--vscode-text);
                border-radius: 3px;
            }

            /* Desktop buttons */
            .desktop-btn {
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

            .desktop-btn:hover {
                background: var(--apple-blue-hover);
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
            }

            .desktop-btn.btn-danger {
                background: var(--apple-red);
            }

            .desktop-btn.btn-danger:hover {
                background: #e60026;
            }

            /* Desktop cart section */
            .desktop-cart {
                display: block !important;
                margin-top: 32px;
                padding: 24px;
                background: var(--vscode-sidebar);
                border-radius: 12px;
                border: 1px solid var(--vscode-border);
            }

            .desktop-cart-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 16px;
            }

            .desktop-cart h2 {
                margin: 0;
                font-size: 1.5rem;
                font-weight: 600;
                color: var(--vscode-text);
            }

            /* Desktop totals */
            .desktop-totals {
                display: block !important;
                margin-top: 16px;
                padding: 16px;
                background: var(--vscode-bg);
                border-radius: 8px;
                border: 1px solid var(--vscode-border);
            }

            .desktop-total-row {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 6px 0;
                font-size: 14px;
            }

            .desktop-final-total {
                font-size: 18px;
                font-weight: 700;
                color: var(--apple-blue);
                border-top: 2px solid var(--vscode-border);
                padding-top: 12px;
                margin-top: 8px;
            }

            /* Desktop finalize section */
            .desktop-finalize {
                display: block !important;
                margin-top: 32px;
                text-align: center;
            }

            .desktop-finalize .desktop-btn {
                font-size: 16px;
                padding: 14px 32px;
            }

            /* Desktop discount section */
            .desktop-discount {
                display: block !important;
                margin: 16px 0;
                padding: 16px;
                background: var(--vscode-bg);
                border-radius: 8px;
                border: 1px solid var(--vscode-border);
            }

            .desktop-discount-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 12px;
            }

            .desktop-discount-input-group {
                display: flex;
                align-items: center;
                border: 1px solid var(--vscode-border);
                border-radius: 6px;
                background: var(--vscode-sidebar);
                overflow: hidden;
            }

            .desktop-discount-input {
                flex: 1;
                padding: 10px 12px;
                border: none;
                background: transparent;
                color: var(--vscode-text);
                font-size: 14px;
                outline: none;
            }
        }
    </style>
</head>
<body>
    <!-- MOBILE INTERFACE -->
    <div class="mobile-container">
        <!-- Header -->
        <header class="mobile-header">
            <div class="header-content">
                <a href="index.php" class="back-btn" title="Volver al inicio">
                    🏠
                </a>
                <img src="logo.png" class="logo" alt="Sequoia Speed">
                <h1 class="header-title">Crear Pedido</h1>
            </div>
        </header>

        <!-- Search Section -->
        <section class="mobile-section">
            <h2 class="section-title">🔍 Buscar Productos</h2>
            <div class="form-group">
                <select id="categoria" class="form-control">
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= h($cat['nombre']) ?>">
                            <?= h($cat['icono'] . ' ' . $cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <input type="text" id="busqueda" class="form-control" placeholder="Escribe el nombre del producto">
            </div>
        </section>

        <!-- Products List -->
        <section class="products-container" id="productos-list">
            <div class="loading">
                <div class="text-center text-muted">
                    Selecciona una categoría o escribe un nombre para buscar productos
                </div>
            </div>
        </section>

        <!-- Custom Product -->
        <section class="custom-product-section">
            <div class="mobile-section">
                <h2 class="section-title">✏️ Producto Personalizado</h2>
                <div class="custom-product-form">
                    <div class="form-group">
                        <input type="text" id="custom-nombre" class="form-control" placeholder="Nombre del producto">
                    </div>
                    <div class="custom-product-row">
                        <div class="form-group">
                            <input type="number" id="custom-precio" class="form-control" placeholder="Precio" min="0">
                        </div>
                        <div class="form-group">
                            <select id="custom-talla" class="form-control size-select">
                                <option value="">Talla</option>
                                <option value="XS">XS</option>
                                <option value="S">S</option>
                                <option value="M">M</option>
                                <option value="L">L</option>
                                <option value="XL">XL</option>
                                <option value="2XL">2XL</option>
                                <option value="3XL">3XL</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <input type="number" id="custom-cantidad" class="form-control quantity-input" value="1" min="1">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-full" onclick="agregarProductoPersonalizado()">
                        ➕ Agregar Producto
                    </button>
                </div>
            </div>
        </section>

        <!-- Cart Section -->
        <section class="cart-section">
            <div class="cart-header">
                <h2 class="cart-title">
                    🛒 Carrito
                    <span class="cart-badge" id="cart-count">0</span>
                </h2>
                <button class="btn btn-danger btn-small" onclick="limpiarCarrito()">
                    🗑️ Limpiar
                </button>
            </div>
            
            <div class="cart-items" id="cart-items">
                <div class="empty-cart">
                    <div class="empty-cart-icon">🛒</div>
                    <p class="text-muted">Tu carrito está vacío</p>
                </div>
            </div>
        </section>

        <!-- Totals & Discount -->
        <div class="totals-section" id="totals-section" style="display: none;">
            <div class="totals-header">
                <h3>💰 Resumen del Pedido</h3>
            </div>
            <div class="totals-body">
                <div class="total-row subtotal">
                    <span>Subtotal</span>
                    <span>$<span id="subtotal">0</span></span>
                </div>
                
                <div class="total-row discount" id="discount-row" style="display: none;">
                    <span>Descuento</span>
                    <span>-$<span id="discount-amount">0</span></span>
                </div>
                
                <div class="total-row final">
                    <span>Total Final</span>
                    <span>$<span id="total-final">0</span></span>
                </div>
                
                <div class="discount-section">
                    <button class="discount-toggle" onclick="toggleDiscount()" id="discount-toggle">
                        💸 ¿Aplicar descuento?
                    </button>
                    <div class="discount-input-group hidden" id="discount-controls">
                        <span class="currency-symbol">$</span>
                        <input type="text" class="discount-input" id="discount-input" placeholder="0" maxlength="10">
                        <button class="discount-clear" onclick="clearDiscount()">✕</button>
                    </div>
                    <div id="discount-error" class="text-red font-medium" style="margin-top: 8px; font-size: 12px;"></div>
                </div>
            </div>
        </div>

        <!-- Success/Error States -->
        <div id="success-state" class="success-section hidden">
            <div class="success-icon">✅</div>
            <h3 class="success-title">¡Pedido Creado!</h3>
            <p class="text-muted">Tu pedido ha sido guardado exitosamente</p>
            
            <div class="url-share">
                <p class="font-medium">Enlace del pedido:</p>
                <input type="text" class="url-input" id="pedido-url" readonly>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="copyUrl()">📋 Copiar Enlace</button>
                    <button class="btn btn-orange" onclick="goToOrder()">👁️ Ver Pedido</button>
                </div>
            </div>
            
            <button class="btn btn-success btn-full" onclick="newOrder()">
                🆕 Crear Nuevo Pedido
            </button>
        </div>

        <!-- Bottom Actions -->
        <div class="bottom-actions" id="bottom-actions">
            <div class="action-buttons">
                <button class="btn primary-action" onclick="finalizarPedido()" id="finalize-btn">
                    ✅ Finalizar Pedido
                </button>
            </div>
        </div>
    </div>

    <!-- DESKTOP INTERFACE -->
    <div class="desktop-container" style="display: none;">
        <div class="desktop-header">
            <div class="desktop-header-left">
                <a href="index.php" class="desktop-back-btn" title="Volver al inicio">
                    🏠 Inicio
                </a>
            </div>
            <div class="desktop-header-center">
                <img src="logo.png" class="logo" alt="Sequoia Speed">
                <h1>Orden de Pedido Manual</h1>
            </div>
            <div class="desktop-header-right">
                <!-- Espacio para futuros botones -->
            </div>
        </div>

        <div class="desktop-search">
            <h3>Buscar Productos</h3>
            <div class="desktop-form-row">
                <select id="categoria-desktop">
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= h($cat['nombre']) ?>">
                            <?= h($cat['icono'] . ' ' . $cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" id="busqueda-desktop" placeholder="Escribe el nombre del producto">
            </div>
        </div>

        <div id="productos-list-desktop">
            <p>Selecciona una categoría o escribe un nombre para buscar productos.</p>
        </div>

        <!-- Desktop Custom Product -->
        <div id="producto-personalizado-desktop" style="margin-top: 24px;">
            <h3 style="margin-bottom: 16px; color: var(--vscode-text); font-size: 1.1rem; font-weight: 600;">Agregar Producto Personalizado</h3>
            <div class="desktop-table-container">
                <table class="desktop-table">
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
                                <input type="text" id="custom-nombre-desktop" placeholder="Nombre del producto personalizado" style="width: 100%; padding: 8px; border: 1px solid var(--vscode-border); border-radius: 4px; background: var(--vscode-bg); color: var(--vscode-text);">
                            </td>
                            <td>
                                <input type="number" id="custom-precio-desktop" placeholder="0" min="0" step="0.01" class="cantidad-input">
                            </td>
                            <td>
                                <select class="talla-select" id="custom-talla-desktop">
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
                            </td>
                            <td>
                                <input type="number" min="1" max="100" value="1" id="custom-cantidad-desktop" class="cantidad-input">
                            </td>
                            <td>
                                <button class="desktop-btn" onclick="agregarProductoPersonalizadoDesktop()" style="padding: 6px 8px; font-weight: bold; font-size: 16px; min-width: 32px;">+</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div id="carrito-desktop" class="desktop-cart">
            <div class="desktop-cart-header">
                <h2>Carrito de Compras</h2>
                <button class="desktop-btn btn-danger" onclick="limpiarCarritoDesktop()">Limpiar Carrito</button>
            </div>
            
            <div class="desktop-table-container">
                <table id="carrito-table-desktop" class="desktop-table desktop-cart-table">
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
            </div>
            
            <!-- Desktop Discount Section -->
            <div class="desktop-discount" id="descuento-section-desktop">
                <div class="desktop-discount-header">
                    <label>💸 Descuento</label>
                    <button class="desktop-btn" id="btn-toggle-descuento-desktop" onclick="toggleDiscountDesktop()">Aplicar Descuento</button>
                </div>
                <div class="desktop-discount-input-group" id="descuento-input-group-desktop" style="display: none;">
                    <span class="currency-symbol">$</span>
                    <input type="text" id="descuento-input-desktop" placeholder="Ingresa el descuento" class="desktop-discount-input">
                    <button class="desktop-btn btn-danger" onclick="clearDiscountDesktop()">✕</button>
                </div>
                <div id="descuento-error-desktop" class="text-red" style="margin-top: 8px; font-size: 12px;"></div>
            </div>

            <!-- Desktop Totals -->
            <div class="desktop-totals">
                <div class="desktop-total-row">
                    <span>Subtotal:</span>
                    <span>$<span id="subtotal-desktop">0</span></span>
                </div>
                <div class="desktop-total-row" id="descuento-row-desktop" style="display: none;">
                    <span class="descuento-amount">Descuento:</span>
                    <span class="descuento-amount">-$<span id="discount-amount-desktop">0</span></span>
                </div>
                <div class="desktop-total-row desktop-final-total">
                    <span>Total Final:</span>
                    <span>$<span id="total-final-desktop">0</span></span>
                </div>
            </div>
        </div>

        <div class="desktop-finalize" id="finalizar-pedido-desktop">
            <button class="desktop-btn" onclick="finalizarPedidoDesktop()" id="finalize-btn-desktop">Finalizar Pedido</button>
        </div>

        <!-- Desktop Success State -->
        <div id="pedido-url-desktop" class="desktop-success" style="display: none; margin-top: 24px; text-align: center; padding: 20px; background: var(--vscode-bg); border-radius: 8px; border: 1px solid var(--vscode-border);">
            <p style="margin: 0 0 16px 0; font-weight: 500;">¡Pedido creado exitosamente!</p>
            <input type="text" id="pedido-link-desktop" readonly style="width: 80%; margin-bottom: 16px; padding: 8px; border: 1px solid var(--vscode-border); background: var(--vscode-sidebar); color: var(--vscode-text);">
            <div>
                <button class="desktop-btn" onclick="copyUrlDesktop()">Copiar Enlace</button>
                <button class="desktop-btn" onclick="goToOrderDesktop()">Ver Pedido</button>
                <button class="desktop-btn" onclick="newOrderDesktop()">Nuevo Pedido</button>
            </div>
        </div>
    </div>

    <script>
        // =================================================================
        // MOBILE-FIRST JAVASCRIPT
        // =================================================================
        
        let carrito = [];
        let productosPersonalizados = [];
        let descuentoAplicado = 0;
        let discountVisible = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            setupEventListeners();
            updateCartDisplay();
        });

        function setupEventListeners() {
            document.getElementById('categoria').addEventListener('change', loadProducts);
            document.getElementById('busqueda').addEventListener('input', debounce(loadProducts, 300));
            document.getElementById('custom-nombre').addEventListener('input', updateCustomSizes);
            document.getElementById('discount-input').addEventListener('input', validateDiscount);
            document.getElementById('discount-input').addEventListener('blur', applyDiscount);
        }

        // Debounce function for search
        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Load Products
        function loadProducts() {
            const categoria = document.getElementById('categoria').value;
            const busqueda = document.getElementById('busqueda').value.trim();
            const container = document.getElementById('productos-list');

            if (!categoria && !busqueda) {
                container.innerHTML = `
                    <div class="loading">
                        <div class="text-center text-muted">
                            Selecciona una categoría o escribe un nombre para buscar productos
                        </div>
                    </div>
                `;
                return;
            }

            container.innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    Cargando productos...
                </div>
            `;

            const apiUrl = `productos_por_categoria.php?cat=${encodeURIComponent(categoria)}&search=${encodeURIComponent(busqueda)}`;

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.productos.length === 0) {
                        container.innerHTML = `
                            <div class="loading">
                                <div class="text-center text-muted">
                                    No se encontraron productos
                                </div>
                            </div>
                        `;
                        return;
                    }

                    let html = '';
                    data.productos.forEach(producto => {
                        const esBotas = producto.categoria && producto.categoria.toLowerCase().includes('bota');
                        const tallas = esBotas
                            ? ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']
                            : ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];

                        const tallasOptions = tallas.map(talla =>
                            `<option value="${talla}">${talla}</option>`
                        ).join('');

                        html += `
                            <div class="product-item">
                                <div class="product-header">
                                    <h3 class="product-name">${producto.nombre}</h3>
                                    <div class="product-price">$${producto.precio.toLocaleString()}</div>
                                </div>
                                <div class="product-controls">
                                    <div class="control-group">
                                        <span class="control-label">Talla</span>
                                        <select class="size-select" id="talla_${producto.id}">
                                            <option value="">Seleccionar</option>
                                            ${tallasOptions}
                                        </select>
                                    </div>
                                    <div class="control-group">
                                        <span class="control-label">Cantidad</span>
                                        <input type="number" class="quantity-input" id="cantidad_${producto.id}" value="1" min="1" max="100">
                                    </div>
                                    <div class="control-group">
                                        <button class="btn btn-icon btn-primary" onclick="addToCart(${producto.id}, '${producto.nombre}', ${producto.precio})">
                                            +
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });

                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = `
                        <div class="loading">
                            <div class="text-center text-red">
                                Error al cargar productos
                            </div>
                        </div>
                    `;
                });
        }

        // Update custom product sizes
        function updateCustomSizes() {
            const nombre = document.getElementById('custom-nombre').value.toLowerCase();
            const select = document.getElementById('custom-talla');
            
            const esBotas = nombre.includes('bota') || nombre.includes('zapato') || 
                           nombre.includes('calzado') || nombre.includes('zapatilla');
            
            const tallas = esBotas 
                ? ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']
                : ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];
            
            select.innerHTML = '<option value="">Talla</option>' + 
                tallas.map(t => `<option value="${t}">${t}</option>`).join('');
        }

        // Add to cart
        function addToCart(id, nombre, precio) {
            const tallaSelect = document.getElementById(`talla_${id}`);
            const cantidadInput = document.getElementById(`cantidad_${id}`);
            
            const talla = tallaSelect.value;
            const cantidad = parseInt(cantidadInput.value);

            if (!talla) {
                showToast('Selecciona una talla', 'error');
                return;
            }

            if (!cantidad || cantidad < 1) {
                showToast('Cantidad inválida', 'error');
                return;
            }

            const key = `${id}_${talla}`;
            const existingIndex = carrito.findIndex(item => item.key === key);

            if (existingIndex >= 0) {
                carrito[existingIndex].cantidad += cantidad;
            } else {
                carrito.push({ key, id, nombre, precio, cantidad, talla });
            }

            updateCartDisplay();
            showToast(`${nombre} (${talla}) agregado al carrito`, 'success');

            // Reset form
            tallaSelect.value = '';
            cantidadInput.value = 1;
        }

        // Add custom product
        function agregarProductoPersonalizado() {
            const nombre = document.getElementById('custom-nombre').value.trim();
            const precio = parseFloat(document.getElementById('custom-precio').value);
            const talla = document.getElementById('custom-talla').value;
            const cantidad = parseInt(document.getElementById('custom-cantidad').value);

            if (!nombre) {
                showToast('Ingresa el nombre del producto', 'error');
                return;
            }

            if (!precio || precio <= 0) {
                showToast('Ingresa un precio válido', 'error');
                return;
            }

            if (!talla) {
                showToast('Selecciona una talla', 'error');
                return;
            }

            if (!cantidad || cantidad < 1) {
                showToast('Cantidad inválida', 'error');
                return;
            }

            const customId = 'custom_' + Date.now();
            const key = `${customId}_${talla}`;

            carrito.push({
                key, id: customId, nombre, precio, cantidad, talla, isCustom: true
            });

            productosPersonalizados.push({
                id: customId, nombre, precio, categoria: 'Personalizado'
            });

            updateCartDisplay();
            showToast(`${nombre} (${talla}) agregado al carrito`, 'success');

            // Reset form
            document.getElementById('custom-nombre').value = '';
            document.getElementById('custom-precio').value = '';
            document.getElementById('custom-talla').value = '';
            document.getElementById('custom-cantidad').value = '1';
        }

        // Update cart display
        function updateCartDisplay() {
            const container = document.getElementById('cart-items');
            const countBadge = document.getElementById('cart-count');
            const totalsSection = document.getElementById('totals-section');
            
            countBadge.textContent = carrito.length;

            if (carrito.length === 0) {
                container.innerHTML = `
                    <div class="empty-cart">
                        <div class="empty-cart-icon">🛒</div>
                        <p class="text-muted">Tu carrito está vacío</p>
                    </div>
                `;
                totalsSection.style.display = 'none';
                return;
            }

            totalsSection.style.display = 'block';

            let html = '';
            let subtotal = 0;

            carrito.forEach((item, index) => {
                const itemTotal = item.precio * item.cantidad;
                subtotal += itemTotal;

                html += `
                    <div class="cart-item">
                        <div class="cart-item-info">
                            <h4 class="cart-item-name">${item.nombre}</h4>
                            <div class="cart-item-details">
                                <span>Talla: ${item.talla}</span>
                                <span class="cart-item-price">$${item.precio.toLocaleString()}</span>
                            </div>
                        </div>
                        <div class="cart-item-controls">
                            <div class="quantity-control">
                                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.cantidad - 1})">-</button>
                                <input type="number" class="quantity-display" value="${item.cantidad}" min="1" 
                                       onchange="updateQuantity(${index}, this.value)" readonly>
                                <button class="quantity-btn" onclick="updateQuantity(${index}, ${item.cantidad + 1})">+</button>
                            </div>
                            <button class="btn btn-danger btn-icon btn-small" onclick="removeFromCart(${index})">✕</button>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Update totals
            updateTotals(subtotal);
        }

        // Update totals
        function updateTotals(subtotal) {
            document.getElementById('subtotal').textContent = subtotal.toLocaleString();
            
            const discountRow = document.getElementById('discount-row');
            const totalFinal = Math.max(0, subtotal - descuentoAplicado);

            if (descuentoAplicado > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('discount-amount').textContent = descuentoAplicado.toLocaleString();
            } else {
                discountRow.style.display = 'none';
            }

            document.getElementById('total-final').textContent = totalFinal.toLocaleString();

            // Validate current discount
            if (descuentoAplicado > subtotal) {
                descuentoAplicado = subtotal;
                document.getElementById('discount-input').value = subtotal.toString();
                if (subtotal > 0) {
                    showToast('Descuento ajustado al máximo', 'error');
                }
            }
        }

        // Update quantity
        function updateQuantity(index, newQuantity) {
            newQuantity = parseInt(newQuantity);
            if (isNaN(newQuantity) || newQuantity < 1) return;
            
            carrito[index].cantidad = newQuantity;
            updateCartDisplay();
        }

        // Remove from cart
        function removeFromCart(index) {
            carrito.splice(index, 1);
            updateCartDisplay();
        }

        // Clear cart
        function limpiarCarrito() {
            if (carrito.length === 0) return;
            
            if (confirm('¿Vaciar el carrito?')) {
                carrito = [];
                descuentoAplicado = 0;
                document.getElementById('discount-input').value = '';
                if (discountVisible) toggleDiscount();
                updateCartDisplay();
                showToast('Carrito vaciado', 'success');
            }
        }

        // Toggle discount
        function toggleDiscount() {
            const controls = document.getElementById('discount-controls');
            const toggle = document.getElementById('discount-toggle');
            
            discountVisible = !discountVisible;
            
            if (discountVisible) {
                controls.classList.remove('hidden');
                toggle.textContent = '❌ Cancelar descuento';
                setTimeout(() => document.getElementById('discount-input').focus(), 100);
            } else {
                controls.classList.add('hidden');
                toggle.textContent = '💸 ¿Aplicar descuento?';
                clearDiscount();
            }
        }

        // Validate discount
        function validateDiscount() {
            const input = document.getElementById('discount-input');
            const error = document.getElementById('discount-error');
            
            let value = input.value.replace(/[^0-9]/g, '');
            input.value = value;
            
            error.textContent = '';
            
            if (!value) return true;
            
            const amount = parseInt(value);
            const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            
            if (amount <= 0) {
                error.textContent = 'El descuento debe ser mayor a $0';
                return false;
            }
            
            if (amount > subtotal) {
                error.textContent = `Máximo: $${subtotal.toLocaleString()}`;
                return false;
            }
            
            return true;
        }

        // Apply discount
        function applyDiscount() {
            const input = document.getElementById('discount-input');
            const value = input.value.trim();
            
            if (!value) {
                descuentoAplicado = 0;
                updateCartDisplay();
                return;
            }
            
            if (validateDiscount()) {
                descuentoAplicado = parseInt(value);
                input.value = descuentoAplicado.toLocaleString();
                updateCartDisplay();
                showToast(`Descuento de $${descuentoAplicado.toLocaleString()} aplicado`, 'success');
            }
        }

        // Clear discount
        function clearDiscount() {
            document.getElementById('discount-input').value = '';
            document.getElementById('discount-error').textContent = '';
            descuentoAplicado = 0;
            updateCartDisplay();
        }

        // Finalize order
        function finalizarPedido() {
            if (carrito.length === 0) {
                showToast('El carrito está vacío', 'error');
                return;
            }

            const subtotal = carrito.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            if (descuentoAplicado > subtotal) {
                showToast('Descuento inválido', 'error');
                return;
            }

            const btn = document.getElementById('finalize-btn');
            btn.disabled = true;
            btn.innerHTML = '<div class="loading-spinner"></div> Guardando...';

            const total = Math.max(0, subtotal - descuentoAplicado);
            const carritoData = carrito.map(item => ({
                id: parseInt(item.id) || 0,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: parseInt(item.cantidad),
                talla: item.talla || 'N/A',
                personalizado: item.isCustom || false
            }));

            fetch('guardar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    carrito: carritoData,
                    monto: total,
                    descuento: descuentoAplicado,
                    subtotal: subtotal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOrderSuccess(data.pedido_id);
                } else {
                    showToast('Error al guardar: ' + (data.error || 'Error desconocido'), 'error');
                    btn.disabled = false;
                    btn.textContent = '✅ Finalizar Pedido';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error de conexión', 'error');
                btn.disabled = false;
                btn.textContent = '✅ Finalizar Pedido';
            });
        }

        // Show order success
        function showOrderSuccess(pedidoId) {
            const baseUrl = window.location.origin + window.location.pathname.replace('orden_pedido.php', 'pedido.php');
            const url = `${baseUrl}?pedido=${pedidoId}`;
            
            document.getElementById('pedido-url').value = url;
            document.getElementById('success-state').classList.remove('hidden');
            document.getElementById('bottom-actions').classList.add('hidden');
            
            carrito = [];
            productosPersonalizados = [];
            descuentoAplicado = 0;
            updateCartDisplay();
            
            showToast('¡Pedido creado exitosamente!', 'success');
        }

        // Copy URL
        function copyUrl() {
            const input = document.getElementById('pedido-url');
            input.select();
            input.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showToast('Enlace copiado', 'success');
            } catch (err) {
                showToast('Error al copiar', 'error');
            }
        }

        // Go to order
        function goToOrder() {
            const url = document.getElementById('pedido-url').value;
            if (url) {
                window.open(url, '_blank');
            }
        }

        // New order
        function newOrder() {
            if (confirm('¿Crear un nuevo pedido?')) {
                window.location.reload();
            }
        }

        // Show toast
        function showToast(message, type = 'success') {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        // =================================================================
        // DESKTOP JAVASCRIPT FUNCTIONS
        // =================================================================

        let carritoDesktop = [];
        let productosPersonalizadosDesktop = [];
        let descuentoAplicadoDesktop = 0;
        let discountVisibleDesktop = false;

        // Desktop Initialization
        document.addEventListener('DOMContentLoaded', function() {
            setupDesktopEventListeners();
            updateCartDisplayDesktop();
        });

        function setupDesktopEventListeners() {
            if (document.getElementById('categoria-desktop')) {
                document.getElementById('categoria-desktop').addEventListener('change', loadProductsDesktop);
            }
            if (document.getElementById('busqueda-desktop')) {
                document.getElementById('busqueda-desktop').addEventListener('input', debounce(loadProductsDesktop, 300));
            }
            if (document.getElementById('custom-nombre-desktop')) {
                document.getElementById('custom-nombre-desktop').addEventListener('input', updateCustomSizesDesktop);
            }
            if (document.getElementById('descuento-input-desktop')) {
                document.getElementById('descuento-input-desktop').addEventListener('input', validateDiscountDesktop);
                document.getElementById('descuento-input-desktop').addEventListener('blur', applyDiscountDesktop);
            }
        }

        // Desktop Load Products
        function loadProductsDesktop() {
            const categoria = document.getElementById('categoria-desktop').value;
            const busqueda = document.getElementById('busqueda-desktop').value.trim();
            const container = document.getElementById('productos-list-desktop');

            if (!categoria && !busqueda) {
                container.innerHTML = '<p>Selecciona una categoría o escribe un nombre para buscar productos.</p>';
                return;
            }

            container.innerHTML = '<p>Cargando productos...</p>';

            const apiUrl = `productos_por_categoria.php?cat=${encodeURIComponent(categoria)}&search=${encodeURIComponent(busqueda)}`;

            fetch(apiUrl)
                .then(response => response.json())
                .then(data => {
                    if (data.productos.length === 0) {
                        container.innerHTML = '<p>No se encontraron productos</p>';
                        return;
                    }

                    let html = '<div class="desktop-table-container"><table class="desktop-table"><thead><tr><th>Producto</th><th>Precio</th><th>Talla</th><th>Cant</th><th>Acción</th></tr></thead><tbody>';
                    
                    data.productos.forEach(producto => {
                        const esBotas = producto.categoria && producto.categoria.toLowerCase().includes('bota');
                        const tallas = esBotas
                            ? ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']
                            : ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];

                        const tallasOptions = tallas.map(talla =>
                            `<option value="${talla}">${talla}</option>`
                        ).join('');

                        html += `
                            <tr>
                                <td>${producto.nombre}</td>
                                <td>$${producto.precio.toLocaleString()}</td>
                                <td>
                                    <select class="talla-select" id="talla_desktop_${producto.id}">
                                        <option value="">Sel</option>
                                        ${tallasOptions}
                                    </select>
                                </td>
                                <td>
                                    <input type="number" class="cantidad-input" id="cantidad_desktop_${producto.id}" value="1" min="1" max="100">
                                </td>
                                <td>
                                    <button class="desktop-btn" onclick="addToCartDesktop(${producto.id}, '${producto.nombre}', ${producto.precio})" style="padding: 6px 8px; font-weight: bold; min-width: 32px;">+</button>
                                </td>
                            </tr>
                        `;
                    });

                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    container.innerHTML = '<p style="color: var(--apple-red);">Error al cargar productos</p>';
                });
        }

        // Desktop functions
        function updateCustomSizesDesktop() {
            const nombre = document.getElementById('custom-nombre-desktop').value.toLowerCase();
            const select = document.getElementById('custom-talla-desktop');
            
            const esBotas = nombre.includes('bota') || nombre.includes('zapato') || 
                           nombre.includes('calzado') || nombre.includes('zapatilla');
            
            const tallas = esBotas 
                ? ['36', '37', '38', '39', '40', '41', '42', '43', '44', '45']
                : ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL'];
            
            select.innerHTML = '<option value="">Seleccionar talla</option>' + 
                tallas.map(t => `<option value="${t}">${t}</option>`).join('');
        }

        function addToCartDesktop(id, nombre, precio) {
            const tallaSelect = document.getElementById(`talla_desktop_${id}`);
            const cantidadInput = document.getElementById(`cantidad_desktop_${id}`);
            
            const talla = tallaSelect.value;
            const cantidad = parseInt(cantidadInput.value);

            if (!talla) {
                alert('Selecciona una talla');
                return;
            }

            if (!cantidad || cantidad < 1) {
                alert('Cantidad inválida');
                return;
            }

            const key = `${id}_${talla}`;
            const existingIndex = carritoDesktop.findIndex(item => item.key === key);

            if (existingIndex >= 0) {
                carritoDesktop[existingIndex].cantidad += cantidad;
            } else {
                carritoDesktop.push({ key, id, nombre, precio, cantidad, talla });
            }

            updateCartDisplayDesktop();
            alert(`${nombre} (${talla}) agregado al carrito`);

            // Reset form
            tallaSelect.value = '';
            cantidadInput.value = 1;
        }

        function agregarProductoPersonalizadoDesktop() {
            const nombre = document.getElementById('custom-nombre-desktop').value.trim();
            const precio = parseFloat(document.getElementById('custom-precio-desktop').value);
            const talla = document.getElementById('custom-talla-desktop').value;
            const cantidad = parseInt(document.getElementById('custom-cantidad-desktop').value);

            if (!nombre || !precio || precio <= 0 || !talla || !cantidad || cantidad < 1) {
                alert('Completa todos los campos correctamente');
                return;
            }

            const customId = 'custom_' + Date.now();
            const key = `${customId}_${talla}`;

            carritoDesktop.push({
                key, id: customId, nombre, precio, cantidad, talla, isCustom: true
            });

            productosPersonalizadosDesktop.push({
                id: customId, nombre, precio, categoria: 'Personalizado'
            });

            updateCartDisplayDesktop();
            alert(`${nombre} (${talla}) agregado al carrito`);

            // Reset form
            document.getElementById('custom-nombre-desktop').value = '';
            document.getElementById('custom-precio-desktop').value = '';
            document.getElementById('custom-talla-desktop').value = '';
            document.getElementById('custom-cantidad-desktop').value = '1';
        }

        function updateCartDisplayDesktop() {
            const tbody = document.querySelector('#carrito-table-desktop tbody');
            
            if (carritoDesktop.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6">Tu carrito está vacío.</td></tr>';
                return;
            }

            let html = '';
            let subtotal = 0;

            carritoDesktop.forEach((item, index) => {
                const itemTotal = item.precio * item.cantidad;
                subtotal += itemTotal;

                html += `
                    <tr>
                        <td>${item.nombre}</td>
                        <td style="text-align: center;">${item.talla}</td>
                        <td style="text-align: center;">
                            <input type="number" value="${item.cantidad}" min="1" onchange="updateQuantityDesktop(${index}, this.value)" 
                                   style="width: 50px; text-align: center; padding: 2px;">
                        </td>
                        <td style="text-align: right;">$${item.precio.toLocaleString()}</td>
                        <td style="text-align: right;">$${itemTotal.toLocaleString()}</td>
                        <td style="text-align: center;">
                            <button class="desktop-btn btn-danger" onclick="removeFromCartDesktop(${index})" style="padding: 4px 6px; font-size: 11px;">X</button>
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
            updateTotalsDesktop(subtotal);
        }

        function updateTotalsDesktop(subtotal) {
            document.getElementById('subtotal-desktop').textContent = subtotal.toLocaleString();
            
            const discountRow = document.getElementById('descuento-row-desktop');
            const totalFinal = Math.max(0, subtotal - descuentoAplicadoDesktop);

            if (descuentoAplicadoDesktop > 0) {
                discountRow.style.display = 'flex';
                document.getElementById('discount-amount-desktop').textContent = descuentoAplicadoDesktop.toLocaleString();
            } else {
                discountRow.style.display = 'none';
            }

            document.getElementById('total-final-desktop').textContent = totalFinal.toLocaleString();

            if (descuentoAplicadoDesktop > subtotal) {
                descuentoAplicadoDesktop = subtotal;
                document.getElementById('descuento-input-desktop').value = subtotal.toString();
            }
        }

        function updateQuantityDesktop(index, newQuantity) {
            newQuantity = parseInt(newQuantity);
            if (isNaN(newQuantity) || newQuantity < 1) return;
            
            carritoDesktop[index].cantidad = newQuantity;
            updateCartDisplayDesktop();
        }

        function removeFromCartDesktop(index) {
            carritoDesktop.splice(index, 1);
            updateCartDisplayDesktop();
        }

        function limpiarCarritoDesktop() {
            if (carritoDesktop.length === 0) return;
            
            if (confirm('¿Vaciar el carrito?')) {
                carritoDesktop = [];
                descuentoAplicadoDesktop = 0;
                document.getElementById('descuento-input-desktop').value = '';
                if (discountVisibleDesktop) toggleDiscountDesktop();
                updateCartDisplayDesktop();
            }
        }

        function toggleDiscountDesktop() {
            const inputGroup = document.getElementById('descuento-input-group-desktop');
            const button = document.getElementById('btn-toggle-descuento-desktop');
            
            discountVisibleDesktop = !discountVisibleDesktop;
            
            if (discountVisibleDesktop) {
                inputGroup.style.display = 'flex';
                button.textContent = 'Cancelar';
                button.classList.add('btn-danger');
                setTimeout(() => document.getElementById('descuento-input-desktop').focus(), 100);
            } else {
                inputGroup.style.display = 'none';
                button.textContent = 'Aplicar Descuento';
                button.classList.remove('btn-danger');
                clearDiscountDesktop();
            }
        }

        function validateDiscountDesktop() {
            const input = document.getElementById('descuento-input-desktop');
            const error = document.getElementById('descuento-error-desktop');
            
            let value = input.value.replace(/[^0-9]/g, '');
            input.value = value;
            
            error.textContent = '';
            
            if (!value) return true;
            
            const amount = parseInt(value);
            const subtotal = carritoDesktop.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            
            if (amount <= 0) {
                error.textContent = 'El descuento debe ser mayor a $0';
                return false;
            }
            
            if (amount > subtotal) {
                error.textContent = `Máximo: $${subtotal.toLocaleString()}`;
                return false;
            }
            
            return true;
        }

        function applyDiscountDesktop() {
            const input = document.getElementById('descuento-input-desktop');
            const value = input.value.trim();
            
            if (!value) {
                descuentoAplicadoDesktop = 0;
                updateCartDisplayDesktop();
                return;
            }
            
            if (validateDiscountDesktop()) {
                descuentoAplicadoDesktop = parseInt(value);
                input.value = descuentoAplicadoDesktop.toLocaleString();
                updateCartDisplayDesktop();
            }
        }

        function clearDiscountDesktop() {
            document.getElementById('descuento-input-desktop').value = '';
            document.getElementById('descuento-error-desktop').textContent = '';
            descuentoAplicadoDesktop = 0;
            updateCartDisplayDesktop();
        }

        function finalizarPedidoDesktop() {
            if (carritoDesktop.length === 0) {
                alert('El carrito está vacío');
                return;
            }

            const subtotal = carritoDesktop.reduce((sum, item) => sum + (item.precio * item.cantidad), 0);
            if (descuentoAplicadoDesktop > subtotal) {
                alert('Descuento inválido');
                return;
            }

            const btn = document.getElementById('finalize-btn-desktop');
            btn.disabled = true;
            btn.textContent = 'Guardando...';

            const total = Math.max(0, subtotal - descuentoAplicadoDesktop);
            const carritoData = carritoDesktop.map(item => ({
                id: parseInt(item.id) || 0,
                nombre: item.nombre,
                precio: parseFloat(item.precio),
                cantidad: parseInt(item.cantidad),
                talla: item.talla || 'N/A',
                personalizado: item.isCustom || false
            }));

            fetch('guardar_pedido.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    carrito: carritoData,
                    monto: total,
                    descuento: descuentoAplicadoDesktop,
                    subtotal: subtotal
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showOrderSuccessDesktop(data.pedido_id);
                } else {
                    alert('Error al guardar: ' + (data.error || 'Error desconocido'));
                    btn.disabled = false;
                    btn.textContent = 'Finalizar Pedido';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
                btn.disabled = false;
                btn.textContent = 'Finalizar Pedido';
            });
        }

        function showOrderSuccessDesktop(pedidoId) {
            const baseUrl = window.location.origin + window.location.pathname.replace('orden_pedido.php', 'pedido.php');
            const url = `${baseUrl}?pedido=${pedidoId}`;
            
            document.getElementById('pedido-link-desktop').value = url;
            document.getElementById('pedido-url-desktop').style.display = 'block';
            document.getElementById('finalizar-pedido-desktop').style.display = 'none';
            
            carritoDesktop = [];
            productosPersonalizadosDesktop = [];
            descuentoAplicadoDesktop = 0;
            updateCartDisplayDesktop();
        }

        function copyUrlDesktop() {
            const input = document.getElementById('pedido-link-desktop');
            input.select();
            document.execCommand('copy');
            alert('Enlace copiado');
        }

        function goToOrderDesktop() {
            const url = document.getElementById('pedido-link-desktop').value;
            if (url) {
                window.open(url, '_blank');
            }
        }

        function newOrderDesktop() {
            if (confirm('¿Crear un nuevo pedido?')) {
                window.location.reload();
            }
        }
    </script>
</body>
</html>