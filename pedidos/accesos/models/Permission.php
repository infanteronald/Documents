<?php
/**
 * Modelo de Permiso
 * Sequoia Speed - Sistema de Accesos
 */

class Permission {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Obtener todos los permisos agrupados por módulo
     */
    public function getAllPermissions() {
        $query = "SELECT 
            p.id,
            p.tipo_permiso,
            p.descripcion,
            p.activo,
            m.id as modulo_id,
            m.nombre as modulo_nombre,
            m.descripcion as modulo_descripcion
        FROM acc_permisos p
        INNER JOIN acc_modulos m ON p.modulo_id = m.id
        WHERE p.activo = 1 AND m.activo = 1
        ORDER BY m.nombre, 
            CASE p.tipo_permiso 
                WHEN 'leer' THEN 1 
                WHEN 'crear' THEN 2 
                WHEN 'actualizar' THEN 3 
                WHEN 'eliminar' THEN 4 
                ELSE 5 
            END";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtener permisos agrupados por módulo
     */
    public function getPermissionsByModule() {
        $permissions = $this->getAllPermissions();
        $grouped = [];
        
        foreach ($permissions as $permission) {
            $module = $permission['modulo_nombre'];
            if (!isset($grouped[$module])) {
                $grouped[$module] = [
                    'modulo_id' => $permission['modulo_id'],
                    'modulo_nombre' => $permission['modulo_nombre'],
                    'modulo_descripcion' => $permission['modulo_descripcion'],
                    'acc_permisos' => []
                ];
            }
            
            $grouped[$module]['acc_permisos'][] = [
                'id' => $permission['id'],
                'tipo_permiso' => $permission['tipo_permiso'],
                'descripcion' => $permission['descripcion'],
                'activo' => $permission['activo']
            ];
        }
        
        return $grouped;
    }
    
    /**
     * Buscar permiso por ID
     */
    public function findById($id) {
        $query = "SELECT 
            p.id,
            p.tipo_permiso,
            p.descripcion,
            p.activo,
            m.id as modulo_id,
            m.nombre as modulo_nombre,
            m.descripcion as modulo_descripcion
        FROM acc_permisos p
        INNER JOIN acc_modulos m ON p.modulo_id = m.id
        WHERE p.id = ? AND p.activo = 1 LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Buscar permiso por módulo y tipo
     */
    public function findByModuleAndType($module_id, $tipo_permiso) {
        $query = "SELECT * FROM acc_permisos 
                  WHERE modulo_id = ? AND tipo_permiso = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('is', $module_id, $tipo_permiso);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Obtener permisos de un módulo específico
     */
    public function getModulePermissions($module_id) {
        $query = "SELECT 
            p.id,
            p.tipo_permiso,
            p.descripcion,
            p.activo,
            m.nombre as modulo_nombre
        FROM acc_permisos p
        INNER JOIN acc_modulos m ON p.modulo_id = m.id
        WHERE p.modulo_id = ? AND p.activo = 1
        ORDER BY 
            CASE p.tipo_permiso 
                WHEN 'leer' THEN 1 
                WHEN 'crear' THEN 2 
                WHEN 'actualizar' THEN 3 
                WHEN 'eliminar' THEN 4 
                ELSE 5 
            END";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $module_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Crear nuevo permiso
     */
    public function create($data) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar que no exista el permiso para el módulo
            if ($this->findByModuleAndType($data['modulo_id'], $data['tipo_permiso'])) {
                throw new Exception('Ya existe un permiso de este tipo para el módulo');
            }
            
            $query = "INSERT INTO acc_permisos (modulo_id, tipo_permiso, descripcion, activo) 
                      VALUES (?, ?, ?, 1)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('iss', 
                $data['modulo_id'], 
                $data['tipo_permiso'], 
                $data['descripcion'] ?? ''
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el permiso: ' . $stmt->error);
            }
            
            $permiso_id = $this->conn->insert_id;
            
            $this->conn->commit();
            return $permiso_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Actualizar permiso
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        $types = '';
        
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
        
        if (empty($fields)) {
            throw new Exception('No hay campos para actualizar');
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $query = "UPDATE acc_permisos SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el permiso: ' . $stmt->error);
        }
        
        return true;
    }
    
    /**
     * Obtener roles que tienen un permiso específico
     */
    public function getPermissionRoles($permission_id) {
        $query = "SELECT 
            r.id,
            r.nombre,
            r.descripcion,
            rp.fecha_asignacion
        FROM acc_roles r
        INNER JOIN acc_rol_permisos rp ON r.id = rp.rol_id
        WHERE rp.permiso_id = ? AND r.activo = 1
        ORDER BY r.nombre";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $permission_id);
        $stmt->execute();
        
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Obtener estadísticas de permisos
     */
    public function getPermissionStats() {
        $query = "SELECT 
            m.nombre as modulo,
            m.descripcion as modulo_descripcion,
            COUNT(p.id) as total_permisos,
            COUNT(DISTINCT rp.rol_id) as roles_con_permisos
        FROM acc_modulos m
        LEFT JOIN acc_permisos p ON m.id = p.modulo_id AND p.activo = 1
        LEFT JOIN acc_rol_permisos rp ON p.id = rp.permiso_id
        WHERE m.activo = 1
        GROUP BY m.id
        ORDER BY m.nombre";
        
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Verificar si un permiso se puede eliminar
     */
    public function canDelete($permission_id) {
        // Verificar si hay roles asignados
        $query = "SELECT COUNT(*) as count FROM acc_rol_permisos WHERE permiso_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $permission_id);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] == 0;
    }
    
    /**
     * Desactivar permiso
     */
    public function deactivate($id) {
        return $this->update($id, ['activo' => 0]);
    }
    
    /**
     * Activar permiso
     */
    public function activate($id) {
        return $this->update($id, ['activo' => 1]);
    }
    
    /**
     * Obtener tipos de permisos disponibles
     */
    public function getPermissionTypes() {
        return [
            'leer' => [
                'name' => 'Leer',
                'description' => 'Permite ver y consultar información',
                'icon' => '👁️',
                'color' => 'info'
            ],
            'crear' => [
                'name' => 'Crear',
                'description' => 'Permite crear nuevos registros',
                'icon' => '➕',
                'color' => 'success'
            ],
            'actualizar' => [
                'name' => 'Actualizar',
                'description' => 'Permite modificar registros existentes',
                'icon' => '✏️',
                'color' => 'warning'
            ],
            'eliminar' => [
                'name' => 'Eliminar',
                'description' => 'Permite eliminar registros',
                'icon' => '🗑️',
                'color' => 'danger'
            ]
        ];
    }
    
    /**
     * Formatear tipo de permiso
     */
    public function formatPermissionType($type) {
        $types = $this->getPermissionTypes();
        return $types[$type] ?? [
            'name' => ucfirst($type),
            'description' => 'Permiso ' . $type,
            'icon' => '❓',
            'color' => 'secondary'
        ];
    }
    
    /**
     * Obtener matriz de permisos para un rol
     */
    public function getPermissionMatrix($role_id = null) {
        $permissions = $this->getPermissionsByModule();
        
        if ($role_id) {
            // Obtener permisos del rol
            $query = "SELECT permiso_id FROM acc_rol_permisos WHERE rol_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('i', $role_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $role_permissions = [];
            while ($row = $result->fetch_assoc()) {
                $role_permissions[] = $row['permiso_id'];
            }
            
            // Marcar permisos asignados
            foreach ($permissions as $module => &$module_data) {
                foreach ($module_data['acc_permisos'] as &$permiso) {
                    $permiso['assigned'] = in_array($permiso['id'], $role_permissions);
                }
            }
        }
        
        return $permissions;
    }
}
?>