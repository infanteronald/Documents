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
        
        // Si ya hay sesión activa, verificar validez
        if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
            return true;
        }
        
        // Si no hay sesión, verificar cookie "recordarme"
        if (isset($_COOKIE['remember_token']) && !empty($_COOKIE['remember_token'])) {
            return $this->loginFromRememberToken($_COOKIE['remember_token']);
        }
        
        return false;
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
    public function requireAuth($redirect_url = '/accesos/login.php') {
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
        
        // Si marcó "recordarme", crear cookie persistente
        if ($remember_me) {
            $this->createRememberMeToken($user_id);
        }
        
        // Actualizar último acceso
        $this->user_model->updateLastAccess($user_id);
        
        // Registrar sesión en base de datos
        $this->registerSession($user_id, $remember_me);
        
        // Registrar auditoría
        $this->registerAudit($user_id, 'login', 'acc_usuarios', 'Usuario inició sesión');
        
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
            $this->registerAudit($user_id, 'logout', 'acc_usuarios', 'Usuario cerró sesión');
            
            // Desactivar sesión en base de datos
            $this->deactivateSession($user_id);
            
            // Eliminar cookie remember_token si existe
            $this->removeRememberMeToken($user_id);
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
        
        $query = "INSERT INTO acc_sesiones (usuario_id, token, ip_address, user_agent, fecha_expiracion) 
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
            $query = "UPDATE acc_sesiones SET activa = 0 WHERE usuario_id = ? AND token = ?";
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
        
        $query = "SELECT id FROM acc_sesiones 
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
        
        $query = "INSERT INTO acc_auditoria_accesos (usuario_id, accion, modulo, descripcion, ip_address, user_agent) 
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
            'acc_roles' => $roles,
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
        $query = "UPDATE acc_sesiones SET activa = 0 WHERE fecha_expiracion < NOW() AND activa = 1";
        $this->conn->query($query);
        
        $query = "DELETE FROM acc_sesiones WHERE fecha_expiracion < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $this->conn->query($query);
    }
    
    /**
     * Obtener sesiones activas de un usuario
     */
    public function getActiveSessions($user_id) {
        $query = "SELECT id, ip_address, user_agent, fecha_inicio, fecha_expiracion 
                  FROM acc_sesiones 
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
        $query = "UPDATE acc_sesiones SET activa = 0 WHERE usuario_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        return $stmt->execute();
    }
    
    /**
     * Crear token "recordarme" y cookie persistente
     */
    private function createRememberMeToken($user_id) {
        // Generar token único
        $token = bin2hex(random_bytes(32));
        $selector = bin2hex(random_bytes(12));
        $token_hash = hash('sha256', $token);
        
        // Expiración: 30 días
        $expires = time() + (30 * 24 * 60 * 60);
        $expires_db = date('Y-m-d H:i:s', $expires);
        
        // Eliminar tokens anteriores del usuario
        $delete_query = "DELETE FROM acc_remember_tokens WHERE usuario_id = ?";
        $delete_stmt = $this->conn->prepare($delete_query);
        $delete_stmt->bind_param('i', $user_id);
        $delete_stmt->execute();
        
        // Insertar nuevo token en la base de datos
        $insert_query = "INSERT INTO acc_remember_tokens (usuario_id, selector, token_hash, fecha_expiracion) VALUES (?, ?, ?, ?)";
        $insert_stmt = $this->conn->prepare($insert_query);
        $insert_stmt->bind_param('isss', $user_id, $selector, $token_hash, $expires_db);
        $insert_stmt->execute();
        
        // Crear cookie segura (30 días)
        $cookie_value = $selector . ':' . $token;
        setcookie('remember_token', $cookie_value, $expires, '/', '', true, true);
        
        return true;
    }
    
    /**
     * Autenticar desde token "recordarme"
     */
    private function loginFromRememberToken($cookie_value) {
        if (empty($cookie_value) || !str_contains($cookie_value, ':')) {
            return false;
        }
        
        list($selector, $token) = explode(':', $cookie_value, 2);
        $token_hash = hash('sha256', $token);
        
        // Buscar token en la base de datos
        $query = "SELECT usuario_id FROM acc_remember_tokens 
                  WHERE selector = ? AND token_hash = ? AND fecha_expiracion > NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ss', $selector, $token_hash);
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $user_id = $row['usuario_id'];
            
            // Verificar que el usuario sigue activo
            $user = $this->user_model->findById($user_id);
            if ($user && $user['activo']) {
                // Crear nueva sesión
                $_SESSION['user_id'] = $user_id;
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Actualizar último acceso
                $this->user_model->updateLastAccess($user_id);
                
                // Regenerar token por seguridad
                $this->createRememberMeToken($user_id);
                
                // Registrar en sesiones
                $this->registerSession($user_id, true);
                
                // Registrar auditoría
                $this->registerAudit($user_id, 'auto_login', 'acc_usuarios', 'Usuario autenticado automáticamente por cookie');
                
                return true;
            }
        }
        
        // Token inválido o expirado, eliminar cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
        
        return false;
    }
    
    /**
     * Eliminar token "recordarme" y cookie
     */
    private function removeRememberMeToken($user_id) {
        // Eliminar tokens de la base de datos
        $query = "DELETE FROM acc_remember_tokens WHERE usuario_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        // Eliminar cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', true, true);
            unset($_COOKIE['remember_token']);
        }
        
        return true;
    }
    
    /**
     * Limpiar tokens expirados
     */
    public function cleanExpiredRememberTokens() {
        $query = "DELETE FROM acc_remember_tokens WHERE fecha_expiracion < NOW()";
        $this->conn->query($query);
    }
}
?>