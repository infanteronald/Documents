<?php
/**
 * Modelo de Rol
 * Sequoia Speed - Sistema de Accesos
 */

class Role {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Obtener todos los roles activos
     */
    public function getAllRoles() {
        $query = "SELECT * FROM acc_roles WHERE activo = 1 ORDER BY 
            CASE nombre 
                WHEN 'super_admin' THEN 1 
                WHEN 'admin' THEN 2 
                WHEN 'gerente' THEN 3 
                WHEN 'supervisor' THEN 4 
                WHEN 'vendedor' THEN 5 
                WHEN 'consultor' THEN 6 
                ELSE 7 
            END";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Buscar rol por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM acc_roles WHERE id = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Buscar rol por nombre
     */
    public function findByName($name) {
        $query = "SELECT * FROM acc_roles WHERE nombre = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Crear nuevo rol
     */
    public function create($data) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar que el nombre no exista
            if ($this->findByName($data['nombre'])) {
                throw new Exception('Ya existe un rol con ese nombre');
            }
            
            $query = "INSERT INTO acc_roles (nombre, descripcion, activo, creado_por) 
                      VALUES (?, ?, 1, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ssi', 
                $data['nombre'], 
                $data['descripcion'] ?? '', 
                $data['creado_por'] ?? null
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el rol: ' . $stmt->error);
            }
            
            $rol_id = $this->conn->insert_id;
            
            // Asignar permisos si se especifican
            if (!empty($data['acc_permisos'])) {
                foreach ($data['acc_permisos'] as $permiso_id) {
                    $this->assignPermission($rol_id, $permiso_id, $data['creado_por'] ?? null);
                }
            }
            
            $this->conn->commit();
            return $rol_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Actualizar rol
     */
    public function update($id, $data) {
        try {
            $this->conn->begin_transaction();
            
            $fields = [];
            $values = [];
            $types = '';
            
            if (!empty($data['nombre'])) {
                // Verificar que el nombre no esté en uso por otro rol
                $existing = $this->findByName($data['nombre']);
                if ($existing && $existing['id'] != $id) {
                    throw new Exception('Ya existe otro rol con ese nombre');
                }
                $fields[] = 'nombre = ?';
                $values[] = $data['nombre'];
                $types .= 's';
            }
            
            if (isset($data['descripcion'])) {
                $fields[] = 'descripcion = ?';
                $values[] = $data['descripcion'];
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
            
            $query = "UPDATE acc_roles SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$values);
            
            if (!$stmt->execute()) {
                throw new Exception('Error al actualizar el rol: ' . $stmt->error);
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Obtener permisos de un rol
     */
    public function getRolePermissions($role_id) {
        $query = "SELECT 
            p.id,
            p.tipo_permiso,
            p.descripcion,
            m.nombre as modulo_nombre,
            m.descripcion as modulo_descripcion
        FROM acc_permisos p
        INNER JOIN acc_rol_permisos rp ON p.id = rp.permiso_id
        INNER JOIN acc_modulos m ON p.modulo_id = m.id
        WHERE rp.rol_id = ? AND p.activo = 1 AND m.activo = 1
        ORDER BY m.nombre, p.tipo_permiso";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Asignar permiso a rol
     */
    public function assignPermission($role_id, $permission_id, $assigned_by = null) {
        $query = "INSERT INTO acc_rol_permisos (rol_id, permiso_id, asignado_por) 
                  VALUES (?, ?, ?)
                  ON DUPLICATE KEY UPDATE asignado_por = VALUES(asignado_por)";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iii', $role_id, $permission_id, $assigned_by);
        return $stmt->execute();
    }
    
    /**
     * Remover permiso de rol
     */
    public function removePermission($role_id, $permission_id) {
        $query = "DELETE FROM acc_rol_permisos WHERE rol_id = ? AND permiso_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $role_id, $permission_id);
        return $stmt->execute();
    }
    
    /**
     * Obtener usuarios con un rol específico
     */
    public function getRoleUsers($role_id) {
        $query = "SELECT 
            u.id,
            u.nombre,
            u.email,
            u.activo,
            u.ultimo_acceso,
            ur.fecha_asignacion
        FROM acc_usuarios u
        INNER JOIN acc_usuario_roles ur ON u.id = ur.usuario_id
        WHERE ur.rol_id = ? AND u.activo = 1
        ORDER BY u.nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtener roles con estadísticas
     */
    public function getRolesWithStats() {
        $query = "SELECT 
            r.id,
            r.nombre,
            r.descripcion,
            r.activo,
            r.fecha_creacion,
            COUNT(DISTINCT ur.usuario_id) as total_usuarios,
            COUNT(DISTINCT rp.permiso_id) as total_permisos
        FROM acc_roles r
        LEFT JOIN acc_usuario_roles ur ON r.id = ur.rol_id
        LEFT JOIN acc_rol_permisos rp ON r.id = rp.rol_id
        WHERE r.activo = 1
        GROUP BY r.id
        ORDER BY 
            CASE r.nombre 
                WHEN 'super_admin' THEN 1 
                WHEN 'admin' THEN 2 
                WHEN 'gerente' THEN 3 
                WHEN 'supervisor' THEN 4 
                WHEN 'vendedor' THEN 5 
                WHEN 'consultor' THEN 6 
                ELSE 7 
            END";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Sincronizar permisos de rol
     */
    public function syncPermissions($role_id, $permission_ids, $assigned_by = null) {
        try {
            $this->conn->begin_transaction();
            
            // Eliminar permisos actuales
            $query = "DELETE FROM acc_rol_permisos WHERE rol_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $role_id);
            $stmt->execute();
            
            // Agregar nuevos permisos
            if (!empty($permission_ids)) {
                foreach ($permission_ids as $permission_id) {
                    $this->assignPermission($role_id, $permission_id, $assigned_by);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Verificar si el rol tiene un permiso específico
     */
    public function hasPermission($role_id, $module, $permission) {
        $query = "SELECT COUNT(*) as count
        FROM acc_rol_permisos rp
        INNER JOIN acc_permisos p ON rp.permiso_id = p.id
        INNER JOIN acc_modulos m ON p.modulo_id = m.id
        WHERE rp.rol_id = ? AND m.nombre = ? AND p.tipo_permiso = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iss', $role_id, $module, $permission);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] > 0;
    }
    
    /**
     * Desactivar rol
     */
    public function deactivate($id, $deactivated_by = null) {
        return $this->update($id, [
            'activo' => 0,
            'modificado_por' => $deactivated_by
        ]);
    }
    
    /**
     * Activar rol
     */
    public function activate($id, $activated_by = null) {
        return $this->update($id, [
            'activo' => 1,
            'modificado_por' => $activated_by
        ]);
    }
    
    /**
     * Verificar si el rol se puede eliminar
     */
    public function canDelete($role_id) {
        // Verificar si hay usuarios asignados
        $query = "SELECT COUNT(*) as count FROM acc_usuario_roles WHERE rol_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $role_id);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] == 0;
    }
    
    /**
     * Obtener jerarquía de roles
     */
    public function getRoleHierarchy() {
        return [
            'super_admin' => ['level' => 1, 'name' => 'Super Administrador'],
            'admin' => ['level' => 2, 'name' => 'Administrador'],
            'gerente' => ['level' => 3, 'name' => 'Gerente'],
            'supervisor' => ['level' => 4, 'name' => 'Supervisor'],
            'vendedor' => ['level' => 5, 'name' => 'Vendedor'],
            'consultor' => ['level' => 6, 'name' => 'Consultor']
        ];
    }
}
?>