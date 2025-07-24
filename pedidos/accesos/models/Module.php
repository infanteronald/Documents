<?php
/**
 * Modelo de Módulo
 * Sequoia Speed - Sistema de Accesos
 */

class Module {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Obtener todos los módulos activos
     */
    public function getAllModules() {
        $query = "SELECT * FROM acc_modulos WHERE activo = 1 ORDER BY nombre";
        $result = $this->conn->query($query);
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Buscar módulo por ID
     */
    public function findById($id) {
        $query = "SELECT * FROM acc_modulos WHERE id = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Buscar módulo por nombre
     */
    public function findByName($name) {
        $query = "SELECT * FROM acc_modulos WHERE nombre = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Crear nuevo módulo
     */
    public function create($data) {
        try {
            $this->conn->begin_transaction();
            
            // Verificar que el nombre no exista
            if ($this->findByName($data['nombre'])) {
                throw new Exception('Ya existe un módulo con ese nombre');
            }
            
            $query = "INSERT INTO acc_modulos (nombre, descripcion, activo) 
                      VALUES (?, ?, 1)";
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('ss', 
                $data['nombre'], 
                $data['descripcion'] ?? ''
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el módulo: ' . $stmt->error);
            }
            
            $modulo_id = $this->conn->insert_id;
            
            // Crear permisos básicos para el módulo
            $this->createBasicPermissions($modulo_id);
            
            $this->conn->commit();
            return $modulo_id;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Crear permisos básicos para un módulo
     */
    private function createBasicPermissions($module_id) {
        $basic_permissions = ['leer', 'crear', 'actualizar', 'eliminar'];
        
        foreach ($basic_permissions as $permission) {
            $query = "INSERT INTO acc_permisos (modulo_id, tipo_permiso, descripcion, activo) 
                      VALUES (?, ?, ?, 1)";
            $stmt = $this->conn->prepare($query);
            $description = "Permiso para {$permission} en el módulo";
            $stmt->bind_param('iss', $module_id, $permission, $description);
            $stmt->execute();
        }
    }
    
    /**
     * Actualizar módulo
     */
    public function update($id, $data) {
        $fields = [];
        $values = [];
        $types = '';
        
        if (!empty($data['nombre'])) {
            // Verificar que el nombre no esté en uso por otro módulo
            $existing = $this->findByName($data['nombre']);
            if ($existing && $existing['id'] != $id) {
                throw new Exception('Ya existe otro módulo con ese nombre');
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
        
        if (empty($fields)) {
            throw new Exception('No hay campos para actualizar');
        }
        
        $values[] = $id;
        $types .= 'i';
        
        $query = "UPDATE acc_modulos SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$values);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar el módulo: ' . $stmt->error);
        }
        
        return true;
    }
    
    /**
     * Obtener módulos con estadísticas
     */
    public function getModulesWithStats() {
        $query = "SELECT 
            m.id,
            m.nombre,
            m.descripcion,
            m.activo,
            m.fecha_creacion,
            COUNT(DISTINCT p.id) as total_permisos,
            COUNT(DISTINCT rp.rol_id) as roles_con_acceso
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
     * Obtener permisos de un módulo
     */
    public function getModulePermissions($module_id) {
        $query = "SELECT 
            p.id,
            p.tipo_permiso,
            p.descripcion,
            p.activo,
            COUNT(DISTINCT rp.rol_id) as roles_asignados
        FROM acc_permisos p
        LEFT JOIN acc_rol_permisos rp ON p.id = rp.permiso_id
        WHERE p.modulo_id = ? AND p.activo = 1
        GROUP BY p.id
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
     * Verificar si un módulo se puede eliminar
     */
    public function canDelete($module_id) {
        // Verificar si hay permisos asignados a roles
        $query = "SELECT COUNT(*) as count 
                  FROM acc_rol_permisos rp 
                  INNER JOIN acc_permisos p ON rp.permiso_id = p.id 
                  WHERE p.modulo_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $module_id);
        $stmt->execute();
        
        $result = $stmt->get_result()->fetch_assoc();
        return $result['count'] == 0;
    }
    
    /**
     * Desactivar módulo
     */
    public function deactivate($id) {
        return $this->update($id, ['activo' => 0]);
    }
    
    /**
     * Activar módulo
     */
    public function activate($id) {
        return $this->update($id, ['activo' => 1]);
    }
    
    /**
     * Obtener módulos del sistema con iconos
     */
    public function getSystemModules() {
        return [
            'ventas' => [
                'name' => 'Ventas',
                'description' => 'Gestión de pedidos y ventas',
                'icon' => '🛒',
                'color' => 'success'
            ],
            'inventario' => [
                'name' => 'Inventario',
                'description' => 'Control de productos y stock',
                'icon' => '📦',
                'color' => 'info'
            ],
            'acc_usuarios' => [
                'name' => 'Usuarios',
                'description' => 'Administración de usuarios y accesos',
                'icon' => '👥',
                'color' => 'warning'
            ],
            'reportes' => [
                'name' => 'Reportes',
                'description' => 'Generación de reportes y estadísticas',
                'icon' => '📊',
                'color' => 'primary'
            ],
            'configuracion' => [
                'name' => 'Configuración',
                'description' => 'Configuración del sistema',
                'icon' => '⚙️',
                'color' => 'secondary'
            ]
        ];
    }
    
    /**
     * Formatear módulo con información adicional
     */
    public function formatModule($module) {
        $system_modules = $this->getSystemModules();
        $module_info = $system_modules[$module['nombre']] ?? [
            'name' => ucfirst($module['nombre']),
            'description' => $module['descripcion'],
            'icon' => '📁',
            'color' => 'secondary'
        ];
        
        return array_merge($module, $module_info);
    }
    
    /**
     * Obtener resumen de acceso por módulo
     */
    public function getAccessSummary($user_id = null) {
        $modules = $this->getAllModules();
        $summary = [];
        
        foreach ($modules as $module) {
            $module_data = $this->formatModule($module);
            
            if ($user_id) {
                // Obtener permisos del usuario para este módulo
                $query = "SELECT DISTINCT p.tipo_permiso
                          FROM acc_vista_permisos_usuario vpu
                          INNER JOIN acc_permisos p ON vpu.modulo = ? AND vpu.tipo_permiso = p.tipo_permiso
                          WHERE vpu.usuario_id = ?";
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param('si', $module['nombre'], $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                $permissions = [];
                while ($row = $result->fetch_assoc()) {
                    $permissions[] = $row['tipo_permiso'];
                }
                
                $module_data['user_permissions'] = $permissions;
                $module_data['has_access'] = !empty($permissions);
            }
            
            $summary[] = $module_data;
        }
        
        return $summary;
    }
}
?>