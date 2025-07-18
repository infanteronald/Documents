<?php
/**
 * Modelo de Usuario
 * Sequoia Speed - Sistema de Accesos
 */

class User {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Buscar usuario por email
     */
    public function findByEmail($email) {
        $query = "SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Buscar usuario por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM usuarios WHERE id = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Crear nuevo usuario
     */
    public function create($data) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar que el email no exista
            if ($this->findByEmail($data['email'])) {
                throw new Exception('El email ya está registrado');
            }
            
            // Hash de la contraseña
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $query = "INSERT INTO usuarios (nombre, email, password, activo, creado_por) 
                      VALUES (?, ?, ?, 1, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('sssi', 
                $data['nombre'], 
                $data['email'], 
                $password_hash, 
                $data['creado_por'] ?? null
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el usuario: ' . $stmt->error);
            }
            
            $usuario_id = $this->conn->insert_id;
            
            // Asignar rol por defecto si se especifica
            if (!empty($data['rol_id'])) {
                $this->assignRole($usuario_id, $data['rol_id'], $data['creado_por'] ?? null);
            }
            
            $this->conn->commit();
            return $usuario_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Actualizar usuario
     */
    public function update($id, $data) {
        try {
            $this->conn->begin_transaction();
            
            $fields = [];
            $values = [];
            $types = '';
            
            if (!empty($data['nombre'])) {
                $fields[] = 'nombre = ?';
                $values[] = $data['nombre'];
                $types .= 's';
            }
            
            if (!empty($data['email'])) {
                // Verificar que el email no esté en uso por otro usuario
                $existing = $this->findByEmail($data['email']);
                if ($existing && $existing['id'] != $id) {
                    throw new Exception('El email ya está en uso por otro usuario');
                }
                $fields[] = 'email = ?';
                $values[] = $data['email'];
                $types .= 's';
            }
            
            if (!empty($data['password'])) {
                $fields[] = 'password = ?';
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
                $types .= 's';
            }
            
            if (isset($data['activo'])) {
                $fields[] = 'activo = ?';
                $values[] = $data['activo'] ? 1 : 0;
                $types .= 'i';
            }
            
            if (!empty($data['modificado_por'])) {
                $fields[] = 'modificado_por = ?';
                $values[] = $data['modificado_por'];
                $types .= 'i';
            }
            
            if (empty($fields)) {
                throw new Exception('No hay campos para actualizar');
            }
            
            $values[] = $id;
            $types .= 'i';
            
            $query = "UPDATE usuarios SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$values);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar el usuario: ' . $stmt->error);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Verificar contraseña
     */
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Actualizar último acceso
     */
    public function updateLastAccess($id) {
        $query = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
    
    /**
     * Obtener usuarios con paginación
     */
    public function getUsers($limit = 20, $offset = 0, $search = '', $role_filter = '') {
        $where_conditions = ['u.activo = 1'];
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $where_conditions[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }
        
        if (!empty($role_filter)) {
            $where_conditions[] = 'r.nombre = ?';
            $params[] = $role_filter;
            $types .= 's';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT DISTINCT
            u.id,
            u.nombre,
            u.email,
            u.activo,
            u.ultimo_acceso,
            u.fecha_creacion,
            GROUP_CONCAT(r.nombre SEPARATOR ', ') as roles
        FROM usuarios u
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id
        $where_clause
        GROUP BY u.id
        ORDER BY u.fecha_creacion DESC
        LIMIT ? OFFSET ?";
        
        $params[] = $limit;
        $params[] = $offset;
        $types .= 'ii';
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Contar usuarios
     */
    public function countUsers($search = '', $role_filter = '') {
        $where_conditions = ['u.activo = 1'];
        $params = [];
        $types = '';
        
        if (!empty($search)) {
            $where_conditions[] = '(u.nombre LIKE ? OR u.email LIKE ?)';
            $search_param = "%$search%";
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }
        
        if (!empty($role_filter)) {
            $where_conditions[] = 'r.nombre = ?';
            $params[] = $role_filter;
            $types .= 's';
        }
        
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        
        $query = "SELECT COUNT(DISTINCT u.id) as total
        FROM usuarios u
        LEFT JOIN usuario_roles ur ON u.id = ur.usuario_id
        LEFT JOIN roles r ON ur.rol_id = r.id
        $where_clause";
        
        $stmt = $this->conn->prepare($query);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        
        return $stmt->get_result()->fetch_assoc()['total'];
    }
    
    /**
     * Obtener roles de un usuario
     */
    public function getUserRoles($user_id) {
        $query = "SELECT r.id, r.nombre, r.descripcion
        FROM roles r
        INNER JOIN usuario_roles ur ON r.id = ur.rol_id
        WHERE ur.usuario_id = ? AND r.activo = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Asignar rol a usuario
     */
    public function assignRole($user_id, $role_id, $assigned_by = null) {
        $query = "INSERT INTO usuario_roles (usuario_id, rol_id, asignado_por) 
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE asignado_por = VALUES(asignado_por)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iii', $user_id, $role_id, $assigned_by);
        return $stmt->execute();
    }
    
    /**
     * Remover rol de usuario
     */
    public function removeRole($user_id, $role_id) {
        $query = "DELETE FROM usuario_roles WHERE usuario_id = ? AND rol_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $user_id, $role_id);
        return $stmt->execute();
    }
    
    /**
     * Obtener permisos de un usuario
     */
    public function getUserPermissions($user_id) {
        $query = "SELECT DISTINCT
            m.nombre as modulo,
            p.tipo_permiso,
            p.descripcion
        FROM vista_permisos_usuario vpu
        INNER JOIN modulos m ON vpu.modulo = m.nombre
        INNER JOIN permisos p ON m.id = p.modulo_id AND vpu.tipo_permiso = p.tipo_permiso
        WHERE vpu.usuario_id = ?
        ORDER BY m.nombre, p.tipo_permiso";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function hasPermission($user_id, $module, $permission) {
        $query = "SELECT COUNT(*) as count
        FROM vista_permisos_usuario
        WHERE usuario_id = ? AND modulo = ? AND tipo_permiso = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iss', $user_id, $module, $permission);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] > 0;
    }
    
    /**
     * Desactivar usuario
     */
    public function deactivate($id, $deactivated_by = null) {
        return $this->update($id, [
            'activo' => 0,
            'modificado_por' => $deactivated_by
        ]);
    }
    
    /**
     * Activar usuario
     */
    public function activate($id, $activated_by = null) {
        return $this->update($id, [
            'activo' => 1,
            'modificado_por' => $activated_by
        ]);
    }
}
?>