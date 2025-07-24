<?php
/**
 * Logout del Sistema
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once dirname(__DIR__) . '/config_secure.php';
require_once 'middleware/AuthMiddleware.php';

// Inicializar middleware
$auth = new AuthMiddleware($conn);

// Cerrar sesión
$auth->logout();

// Redirigir al login con mensaje de éxito
header('Location: login.php?logout=1');
exit;
?>