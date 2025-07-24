<?php
/**
 * Helper de Autenticación para Integración con Módulos
 * Sequoia Speed - Sistema de Accesos
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once __DIR__ . '/../config_secure.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';

/**
 * Clase helper para facilitar la integración del sistema de accesos
 * con los módulos existentes
 */
class AuthHelper {
    private static $auth = null;
    private static $current_user = null;
    
    /**
     * Inicializar el sistema de autenticación
     */
    public static function init() {
        if (self::$auth === null) {
            global $conn;
            self::$auth = new AuthMiddleware($conn);
        }
        return self::$auth;
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public static function isAuthenticated() {
        $auth = self::init();
        return $auth->isAuthenticated();
    }
    
    /**
     * Obtener el usuario actual
     */
    public static function getCurrentUser() {
        if (self::$current_user === null) {
            $auth = self::init();
            self::$current_user = $auth->getCurrentUser();
        }
        return self::$current_user;
    }
    
    /**
     * Requerir autenticación (redirige si no está autenticado)
     */
    public static function requireAuth() {
        $auth = self::init();
        return $auth->requireAuth('/pedidos/accesos/login.php');
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public static function hasPermission($module, $permission) {
        $auth = self::init();
        return $auth->hasPermission($module, $permission);
    }
    
    /**
     * Requerir un permiso específico (redirige si no lo tiene)
     */
    public static function requirePermission($module, $permission) {
        $auth = self::init();
        return $auth->requirePermission($module, $permission, '/pedidos/accesos/unauthorized.php');
    }
    
    /**
     * Verificar si el usuario tiene un rol específico
     */
    public static function hasRole($role) {
        $auth = self::init();
        return $auth->hasRole($role);
    }
    
    /**
     * Verificar si es administrador
     */
    public static function isAdmin() {
        $auth = self::init();
        return $auth->isAdmin();
    }
    
    /**
     * Verificar si es super administrador
     */
    public static function isSuperAdmin() {
        $auth = self::init();
        return $auth->isSuperAdmin();
    }
    
    /**
     * Registrar una actividad en la auditoría
     */
    public static function logActivity($action, $module, $description = '') {
        $auth = self::init();
        $auth->logActivity($action, $module, $description);
    }
    
    /**
     * Cerrar sesión
     */
    public static function logout() {
        $auth = self::init();
        return $auth->logout();
    }
    
    /**
     * Generar token CSRF
     */
    public static function generateCSRF() {
        $auth = self::init();
        return $auth->generateCSRF();
    }
    
    /**
     * Verificar token CSRF
     */
    public static function verifyCSRF($token) {
        $auth = self::init();
        return $auth->verifyCSRF($token);
    }
    
    /**
     * Obtener información de sesión completa
     */
    public static function getSessionInfo() {
        $auth = self::init();
        return $auth->getSessionInfo();
    }
    
    /**
     * Generar menú de navegación basado en permisos
     */
    public static function generateNavMenu($current_module = '') {
        $user = self::getCurrentUser();
        if (!$user) return '';
        
        $menu_items = [];
        
        // Módulo de ventas
        if (self::hasPermission('ventas', 'leer')) {
            $menu_items[] = [
                'url' => '/listar_pedidos.php',
                'icon' => '🛒',
                'title' => 'Ventas',
                'active' => $current_module === 'ventas'
            ];
        }
        
        // Módulo de inventario
        if (self::hasPermission('inventario', 'leer')) {
            $menu_items[] = [
                'url' => '/inventario/productos.php',
                'icon' => '📦',
                'title' => 'Inventario',
                'active' => $current_module === 'inventario'
            ];
        }
        
        // Módulo de usuarios (solo para admins)
        if (self::hasPermission('acc_usuarios', 'leer')) {
            $menu_items[] = [
                'url' => '/accesos/dashboard.php',
                'icon' => '👥',
                'title' => 'Accesos',
                'active' => $current_module === 'accesos'
            ];
        }
        
        // Módulo de reportes
        if (self::hasPermission('reportes', 'leer')) {
            $menu_items[] = [
                'url' => '/reportes/dashboard.php',
                'icon' => '📊',
                'title' => 'Reportes',
                'active' => $current_module === 'reportes'
            ];
        }
        
        // Generar HTML del menú
        $html = '<nav class="main-nav">';
        foreach ($menu_items as $item) {
            $active_class = $item['active'] ? 'active' : '';
            $html .= sprintf(
                '<a href="%s" class="nav-item %s">%s %s</a>',
                $item['url'],
                $active_class,
                $item['icon'],
                $item['title']
            );
        }
        $html .= '</nav>';
        
        return $html;
    }
    
    /**
     * Generar información del usuario para el header
     */
    public static function generateUserInfo() {
        $user = self::getCurrentUser();
        if (!$user) return '';
        
        $session_info = self::getSessionInfo();
        $roles = $session_info['acc_roles'] ?? [];
        $role_names = array_column($roles, 'nombre');
        
        $html = '<div class="user-info-header">';
        $html .= '<div class="user-avatar">👤</div>';
        $html .= '<div class="user-details">';
        $html .= '<div class="user-name">' . htmlspecialchars($user['nombre']) . '</div>';
        $html .= '<div class="user-roles">' . implode(', ', $role_names) . '</div>';
        $html .= '</div>';
        $html .= '<div class="user-actions">';
        $html .= '<a href="/accesos/logout.php" class="logout-btn">🚪 Salir</a>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Middleware para proteger páginas
     */
    public static function protectPage($required_module = null, $required_permission = null) {
        // Verificar autenticación
        $user = self::requireAuth();
        
        // Si se especifica módulo y permiso, verificar
        if ($required_module && $required_permission) {
            self::requirePermission($required_module, $required_permission);
        }
        
        return $user;
    }
    
    /**
     * Verificar permisos para mostrar/ocultar elementos
     */
    public static function canShow($module, $permission) {
        return self::hasPermission($module, $permission);
    }
    
    /**
     * Generar botones de acción basados en permisos
     */
    public static function generateActionButtons($module, $item_id = null, $additional_actions = []) {
        $buttons = [];
        
        // Botón ver (si tiene permiso de lectura)
        if (self::hasPermission($module, 'leer')) {
            $buttons[] = [
                'icon' => '👁️',
                'title' => 'Ver',
                'class' => 'btn-ver',
                'action' => "ver{$module}({$item_id})"
            ];
        }
        
        // Botón editar (si tiene permiso de actualización)
        if (self::hasPermission($module, 'actualizar')) {
            $buttons[] = [
                'icon' => '✏️',
                'title' => 'Editar',
                'class' => 'btn-editar',
                'action' => "editar{$module}({$item_id})"
            ];
        }
        
        // Botón eliminar (si tiene permiso de eliminación)
        if (self::hasPermission($module, 'eliminar')) {
            $buttons[] = [
                'icon' => '🗑️',
                'title' => 'Eliminar',
                'class' => 'btn-eliminar',
                'action' => "eliminar{$module}({$item_id})"
            ];
        }
        
        // Agregar acciones adicionales
        $buttons = array_merge($buttons, $additional_actions);
        
        // Generar HTML
        $html = '<div class="action-buttons">';
        foreach ($buttons as $button) {
            $html .= sprintf(
                '<button class="btn-action %s" onclick="%s" title="%s">%s</button>',
                $button['class'],
                $button['action'],
                $button['title'],
                $button['icon']
            );
        }
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Registrar acceso a página
     */
    public static function logPageAccess($page_name, $additional_info = '') {
        $description = "Acceso a página: {$page_name}";
        if ($additional_info) {
            $description .= " - {$additional_info}";
        }
        
        self::logActivity('read', 'sistema', $description);
    }
    
    /**
     * Verificar timeout de sesión
     */
    public static function checkSessionTimeout($timeout_minutes = 30) {
        $auth = self::init();
        return $auth->checkSessionTimeout($timeout_minutes);
    }
    
    /**
     * Obtener permisos del usuario actual
     */
    public static function getCurrentUserPermissions() {
        $session_info = self::getSessionInfo();
        return $session_info['permissions'] ?? [];
    }
    
    /**
     * Obtener roles del usuario actual
     */
    public static function getCurrentUserRoles() {
        $session_info = self::getSessionInfo();
        return $session_info['acc_roles'] ?? [];
    }
}

// Funciones helper globales para facilitar el uso
function auth_user() {
    return AuthHelper::getCurrentUser();
}

function auth_check() {
    return AuthHelper::isAuthenticated();
}

function auth_can($module, $permission) {
    return AuthHelper::hasPermission($module, $permission);
}

function auth_require($module = null, $permission = null) {
    return AuthHelper::protectPage($module, $permission);
}

function auth_log($action, $module, $description = '') {
    return AuthHelper::logActivity($action, $module, $description);
}

function auth_is_admin() {
    return AuthHelper::isAdmin();
}

function auth_csrf() {
    return AuthHelper::generateCSRF();
}

function auth_verify_csrf($token) {
    return AuthHelper::verifyCSRF($token);
}

function auth_nav_menu($current_module = '') {
    return AuthHelper::generateNavMenu($current_module);
}

function auth_user_info() {
    return AuthHelper::generateUserInfo();
}

function auth_action_buttons($module, $item_id = null, $additional = []) {
    return AuthHelper::generateActionButtons($module, $item_id, $additional);
}

function auth_logout() {
    return AuthHelper::logout();
}
?>