<?php
/**
 * Middleware de Autenticación
 * Sequoia Speed - Sistema de Accesos
 */

class AuthMiddleware {
    private $conn;
    private $user_model;
    
    public function __construct($database) {
        $this->conn = $database;
        require_once __DIR__ . '/../models/User.php';
        $this->user_model = new User($database);
    }
    
    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Obtener usuario actual
     */
    public function getCurrentUser() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $user = $this->user_model->findById($_SESSION['user_id']);
        
        if (!$user) {
            // Usuario no encontrado, limpiar sesión
            $this->logout();
            return null;
        }
        
        return $user;
    }
    
    /**
     * Requerir autenticación
     */
    public function requireAuth($redirect_url = '/pedidos/accesos/login.php') {
        if (!$this->isAuthenticated()) {
            header("Location: $redirect_url");
            exit;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            header("Location: $redirect_url");
            exit;
        }
        
        return $user;
    }
    
    /**
     * Verificar permiso específico
     */
    public function hasPermission($module, $permission) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        return $this->user_model->hasPermission($user['id'], $module, $permission);
    }
    
    /**
     * Requerir permiso específico
     */
    public function requirePermission($module, $permission, $redirect_url = '/accesos/unauthorized.php') {
        $user = $this->requireAuth();
        
        if (!$this->hasPermission($module, $permission)) {
            header("Location: $redirect_url");
            exit;
        }
        
        return $user;
    }
    
    /**
     * Verificar si el usuario tiene uno de varios permisos
     */
    public function hasAnyPermission($module, $permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($module, $permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar si el usuario tiene rol específico
     */
    public function hasRole($role_name) {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        $roles = $this->user_model->getUserRoles($user['id']);
        foreach ($roles as $role) {
            if ($role['nombre'] === $role_name) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Verificar si el usuario tiene uno de varios roles
     */
    public function hasAnyRole($role_names) {
        foreach ($role_names as $role_name) {
            if ($this->hasRole($role_name)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Verificar si es super admin
     */
    public function isSuperAdmin() {
        return $this->hasRole('super_admin');
    }
    
    /**
     * Verificar si es admin (super_admin o admin)
     */
    public function isAdmin() {
        return $this->hasAnyRole(['super_admin', 'admin']);
    }
    
    /**
     * Iniciar sesión
     */
    public function login($user_id, $remember_me = false) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerar ID de sesión por seguridad
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user_id;
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Actualizar último acceso
        $this->user_model->updateLastAccess($user_id);
        
        // Registrar sesión en base de datos
        $this->registerSession($user_id, $remember_me);
        
        // Registrar auditoría
        $this->registerAudit($user_id, 'login', 'usuarios', 'Usuario inició sesión');
        
        return true;
    }
    
    /**
     * Cerrar sesión
     */
    public function logout() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $user_id = $_SESSION['user_id'] ?? null;
        
        if ($user_id) {
            // Registrar auditoría
            $this->registerAudit($user_id, 'logout', 'usuarios', 'Usuario cerró sesión');
            
            // Desactivar sesión en base de datos
            $this->deactivateSession($user_id);
        }
        
        // Limpiar sesión
        session_unset();
        session_destroy();
        
        return true;
    }
    
    /**
     * Registrar sesión en base de datos
     */
    private function registerSession($user_id, $remember_me = false) {
        $token = bin2hex(random_bytes(32));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Duración de sesión
        $expiration = $remember_me ? 
            date('Y-m-d H:i:s', strtotime('+30 days')) : 
            date('Y-m-d H:i:s', strtotime('+8 hours'));
        
        $query = "INSERT INTO sesiones (usuario_id, token, ip_address, user_agent, fecha_expiracion) 
                  VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('issss', $user_id, $token, $ip_address, $user_agent, $expiration);
        $stmt->execute();
        
        $_SESSION['session_token'] = $token;
    }
    
    /**
     * Desactivar sesión en base de datos
     */
    private function deactivateSession($user_id) {
        $token = $_SESSION['session_token'] ?? '';
        
        if ($token) {
            $query = "UPDATE sesiones SET activa = 0 WHERE usuario_id = ? AND token = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('is', $user_id, $token);
            $stmt->execute();
        }
    }
    
    /**
     * Verificar si la sesión es válida
     */
    public function isSessionValid() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $token = $_SESSION['session_token'] ?? '';
        if (!$token) {
            return false;
        }
        
        $query = "SELECT id FROM sesiones 
                  WHERE usuario_id = ? AND token = ? AND activa = 1 AND fecha_expiracion > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('is', $_SESSION['user_id'], $token);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Verificar timeout de sesión
     */
    public function checkSessionTimeout($timeout_minutes = 30) {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        $last_activity = $_SESSION['last_activity'] ?? 0;
        $timeout_seconds = $timeout_minutes * 60;
        
        if (time() - $last_activity > $timeout_seconds) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Registrar auditoría
     */
    private function registerAudit($user_id, $action, $module, $description) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $query = "INSERT INTO auditoria_accesos (usuario_id, accion, modulo, descripcion, ip_address, user_agent) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('isssss', $user_id, $action, $module, $description, $ip_address, $user_agent);
        $stmt->execute();
    }
    
    /**
     * Registrar actividad de usuario
     */
    public function logActivity($action, $module, $description = '') {
        $user = $this->getCurrentUser();
        if ($user) {
            $this->registerAudit($user['id'], $action, $module, $description);
        }
    }
    
    /**
     * Obtener información de sesión
     */
    public function getSessionInfo() {
        if (!$this->isAuthenticated()) {
            return null;
        }
        
        $user = $this->getCurrentUser();
        if (!$user) {
            return null;
        }
        
        $roles = $this->user_model->getUserRoles($user['id']);
        $permissions = $this->user_model->getUserPermissions($user['id']);
        
        return [
            'user' => $user,
            'roles' => $roles,
            'permissions' => $permissions,
            'login_time' => $_SESSION['login_time'] ?? null,
            'last_activity' => $_SESSION['last_activity'] ?? null
        ];
    }
    
    /**
     * Middleware para verificar CSRF
     */
    public function verifyCSRF($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $session_token = $_SESSION['csrf_token'] ?? '';
        return hash_equals($session_token, $token);
    }
    
    /**
     * Generar token CSRF
     */
    public function generateCSRF() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }
    
    /**
     * Limpiar sesiones expiradas
     */
    public function cleanExpiredSessions() {
        $query = "UPDATE sesiones SET activa = 0 WHERE fecha_expiracion < NOW() AND activa = 1";
        $this->conn->query($query);
        
        $query = "DELETE FROM sesiones WHERE fecha_expiracion < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $this->conn->query($query);
    }
    
    /**
     * Obtener sesiones activas de un usuario
     */
    public function getActiveSessions($user_id) {
        $query = "SELECT id, ip_address, user_agent, fecha_inicio, fecha_expiracion 
                  FROM sesiones 
                  WHERE usuario_id = ? AND activa = 1 AND fecha_expiracion > NOW()
                  ORDER BY fecha_inicio DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Cerrar todas las sesiones de un usuario
     */
    public function logoutAllSessions($user_id) {
        $query = "UPDATE sesiones SET activa = 0 WHERE usuario_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
}
?>