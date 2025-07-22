<?php
/**
 * Modelo de Clientes - Sequoia Speed
 * Archivo: app/models/Cliente.php
 */

namespace SequoiaSpeed\Models;

class Cliente
{
    private $connection;
    private $table = 'clientes';
    
    public function __construct()
    {
        $this->connection = getConnection(); // Función de compatibilidad del bootstrap
    }
    
    /**
     * Obtiene todos los clientes con filtros y paginación
     */
    public function getAll($filtros = [], $limit = 50, $offset = 0)
    {
        $sql = "SELECT c.*, 
                       COUNT(p.id) as total_pedidos,
                       COALESCE(SUM(p.total), 0) as total_compras,
                       MAX(p.fecha_creacion) as ultima_compra,
                       DATE_FORMAT(c.fecha_registro, '%Y-%m-%d %H:%i:%s') as fecha_registro_formateada
                FROM {$this->table} c 
                LEFT JOIN pedidos p ON c.id = p.cliente_id
                WHERE c.activo = 1";
        
        $params = [];
        
        // Aplicar filtros
        if (isset($filtros['ciudad']) && !empty($filtros['ciudad'])) {
            $sql .= " AND c.ciudad = ?";
            $params[] = $filtros['ciudad'];
        }
        
        if (isset($filtros['departamento']) && !empty($filtros['departamento'])) {
            $sql .= " AND c.departamento = ?";
            $params[] = $filtros['departamento'];
        }
        
        if (isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $sql .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.cedula LIKE ?)";
            $busqueda = '%' . $filtros['busqueda'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        if (isset($filtros['fecha_inicio']) && !empty($filtros['fecha_inicio'])) {
            $sql .= " AND DATE(c.fecha_registro) >= ?";
            $params[] = $filtros['fecha_inicio'];
        }
        
        if (isset($filtros['fecha_fin']) && !empty($filtros['fecha_fin'])) {
            $sql .= " AND DATE(c.fecha_registro) <= ?";
            $params[] = $filtros['fecha_fin'];
        }
        
        $sql .= " GROUP BY c.id ORDER BY c.nombre ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Cuenta el total de clientes con filtros
     */
    public function count($filtros = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} c WHERE c.activo = 1";
        $params = [];
        
        // Aplicar los mismos filtros que en getAll
        if (isset($filtros['ciudad']) && !empty($filtros['ciudad'])) {
            $sql .= " AND c.ciudad = ?";
            $params[] = $filtros['ciudad'];
        }
        
        if (isset($filtros['departamento']) && !empty($filtros['departamento'])) {
            $sql .= " AND c.departamento = ?";
            $params[] = $filtros['departamento'];
        }
        
        if (isset($filtros['busqueda']) && !empty($filtros['busqueda'])) {
            $sql .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.cedula LIKE ?)";
            $busqueda = '%' . $filtros['busqueda'] . '%';
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }
    
    /**
     * Obtiene un cliente por ID
     */
    public function getById($id)
    {
        $sql = "SELECT c.*, 
                       COUNT(p.id) as total_pedidos,
                       COALESCE(SUM(p.total), 0) as total_compras,
                       MAX(p.fecha_creacion) as ultima_compra,
                       DATE_FORMAT(c.fecha_registro, '%Y-%m-%d %H:%i:%s') as fecha_registro_formateada
                FROM {$this->table} c 
                LEFT JOIN pedidos p ON c.id = p.cliente_id
                WHERE c.id = ? AND c.activo = 1
                GROUP BY c.id";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$id]);
        
        $cliente = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($cliente) {
            // Obtener historial de pedidos
            $cliente['pedidos_recientes'] = $this->getPedidosRecientes($id, 10);
        }
        
        return $cliente;
    }
    
    /**
     * Obtiene un cliente por email
     */
    public function getByEmail($email)
    {
        $sql = "SELECT * FROM {$this->table} WHERE email = ? AND activo = 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$email]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene un cliente por teléfono
     */
    public function getByTelefono($telefono)
    {
        $sql = "SELECT * FROM {$this->table} WHERE telefono = ? AND activo = 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$telefono]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene un cliente por cédula
     */
    public function getByCedula($cedula)
    {
        $sql = "SELECT * FROM {$this->table} WHERE cedula = ? AND activo = 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$cedula]);
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca clientes por término
     */
    public function search($termino, $limit = 20)
    {
        $sql = "SELECT c.*, 
                       COUNT(p.id) as total_pedidos,
                       COALESCE(SUM(p.total), 0) as total_compras
                FROM {$this->table} c 
                LEFT JOIN pedidos p ON c.id = p.cliente_id
                WHERE c.activo = 1 
                AND (c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ? OR c.cedula LIKE ?)
                GROUP BY c.id
                ORDER BY c.nombre ASC 
                LIMIT ?";
        
        $busqueda = '%' . $termino . '%';
        $params = [$busqueda, $busqueda, $busqueda, $busqueda, $limit];
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea un nuevo cliente
     */
    public function create($data)
    {
        // Verificar que no exista otro cliente con el mismo email, teléfono o cédula
        if (!empty($data['email']) && $this->getByEmail($data['email'])) {
            throw new \Exception('Ya existe un cliente con este email');
        }
        
        if (!empty($data['telefono']) && $this->getByTelefono($data['telefono'])) {
            throw new \Exception('Ya existe un cliente con este teléfono');
        }
        
        if (!empty($data['cedula']) && $this->getByCedula($data['cedula'])) {
            throw new \Exception('Ya existe un cliente con esta cédula');
        }
        
        $sql = "INSERT INTO {$this->table} (
                    nombre, email, telefono, cedula,
                    direccion, ciudad, departamento, codigo_postal,
                    fecha_nacimiento, notas,
                    activo, fecha_registro, fecha_actualizacion
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW()
                )";
        
        $params = [
            $data['nombre'],
            $data['email'] ?? null,
            $data['telefono'] ?? null,
            $data['cedula'] ?? null,
            $data['direccion'] ?? null,
            $data['ciudad'] ?? null,
            $data['departamento'] ?? null,
            $data['codigo_postal'] ?? null,
            $data['fecha_nacimiento'] ?? null,
            $data['notas'] ?? null
        ];
        
        $stmt = $this->connection->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            return $this->connection->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Actualiza un cliente
     */
    public function update($id, $data)
    {
        $cliente = $this->getById($id);
        if (!$cliente) {
            throw new \Exception('Cliente no encontrado');
        }
        
        // Verificar unicidad de email, teléfono y cédula
        if (isset($data['email']) && !empty($data['email'])) {
            $existing = $this->getByEmail($data['email']);
            if ($existing && $existing['id'] != $id) {
                throw new \Exception('Ya existe un cliente con este email');
            }
        }
        
        if (isset($data['telefono']) && !empty($data['telefono'])) {
            $existing = $this->getByTelefono($data['telefono']);
            if ($existing && $existing['id'] != $id) {
                throw new \Exception('Ya existe un cliente con este teléfono');
            }
        }
        
        if (isset($data['cedula']) && !empty($data['cedula'])) {
            $existing = $this->getByCedula($data['cedula']);
            if ($existing && $existing['id'] != $id) {
                throw new \Exception('Ya existe un cliente con esta cédula');
            }
        }
        
        $fields = [];
        $params = [];
        
        $allowedFields = [
            'nombre', 'email', 'telefono', 'cedula',
            'direccion', 'ciudad', 'departamento', 'codigo_postal',
            'fecha_nacimiento', 'notas'
        ];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $fields[] = "fecha_actualizacion = NOW()";
        $params[] = $id;
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Elimina un cliente (soft delete)
     */
    public function softDelete($id)
    {
        // Verificar si tiene pedidos activos
        if ($this->hasActivePedidos($id)) {
            throw new \Exception('No se puede eliminar: el cliente tiene pedidos activos');
        }
        
        $sql = "UPDATE {$this->table} SET activo = 0, fecha_actualizacion = NOW() WHERE id = ?";
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$id]);
    }
    
    /**
     * Verifica si un cliente tiene pedidos activos
     */
    public function hasActivePedidos($id)
    {
        $sql = "SELECT COUNT(*) as total 
                FROM pedidos 
                WHERE cliente_id = ? 
                AND estado NOT IN ('completado', 'cancelado')";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$id]);
        
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['total'] > 0;
    }
    
    /**
     * Obtiene pedidos recientes de un cliente
     */
    private function getPedidosRecientes($clienteId, $limit = 10)
    {
        $sql = "SELECT p.id, p.total, p.estado, p.fecha_creacion,
                       DATE_FORMAT(p.fecha_creacion, '%Y-%m-%d %H:%i:%s') as fecha_creacion_formateada
                FROM pedidos p 
                WHERE p.cliente_id = ? 
                ORDER BY p.fecha_creacion DESC 
                LIMIT ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$clienteId, $limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene estadísticas de clientes
     */
    public function getStats()
    {
        $sql = "SELECT 
                    COUNT(*) as total_clientes,
                    COUNT(CASE WHEN fecha_registro >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as nuevos_mes,
                    COUNT(CASE WHEN fecha_registro >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as nuevos_semana,
                    COUNT(DISTINCT ciudad) as total_ciudades,
                    COUNT(DISTINCT departamento) as total_departamentos
                FROM {$this->table} 
                WHERE activo = 1";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene clientes por ciudad
     */
    public function getByCiudad($ciudad)
    {
        $sql = "SELECT * FROM {$this->table} WHERE ciudad = ? AND activo = 1 ORDER BY nombre ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$ciudad]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene ciudades disponibles
     */
    public function getCiudades()
    {
        $sql = "SELECT DISTINCT ciudad, COUNT(*) as total_clientes 
                FROM {$this->table} 
                WHERE activo = 1 AND ciudad IS NOT NULL 
                GROUP BY ciudad 
                ORDER BY ciudad ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene departamentos disponibles
     */
    public function getDepartamentos()
    {
        $sql = "SELECT DISTINCT departamento, COUNT(*) as total_clientes 
                FROM {$this->table} 
                WHERE activo = 1 AND departamento IS NOT NULL 
                GROUP BY departamento 
                ORDER BY departamento ASC";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtiene clientes VIP (con más compras)
     */
    public function getVipClients($limit = 20)
    {
        $sql = "SELECT c.*, 
                       COUNT(p.id) as total_pedidos,
                       SUM(p.total) as total_compras,
                       AVG(p.total) as promedio_compra,
                       MAX(p.fecha_creacion) as ultima_compra
                FROM {$this->table} c 
                INNER JOIN pedidos p ON c.id = p.cliente_id
                WHERE c.activo = 1 AND p.estado = 'completado'
                GROUP BY c.id
                HAVING total_compras > 0
                ORDER BY total_compras DESC, total_pedidos DESC
                LIMIT ?";
        
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$limit]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Crea o actualiza un cliente desde datos de pedido
     */
    public function createOrUpdateFromPedido($pedidoData)
    {
        // Buscar cliente existente por teléfono o email
        $cliente = null;
        
        if (!empty($pedidoData['cliente_telefono'])) {
            $cliente = $this->getByTelefono($pedidoData['cliente_telefono']);
        }
        
        if (!$cliente && !empty($pedidoData['cliente_email'])) {
            $cliente = $this->getByEmail($pedidoData['cliente_email']);
        }
        
        $clienteData = [
            'nombre' => $pedidoData['cliente_nombre'],
            'telefono' => $pedidoData['cliente_telefono'] ?? null,
            'email' => $pedidoData['cliente_email'] ?? null,
            'cedula' => $pedidoData['cliente_cedula'] ?? null,
            'direccion' => $pedidoData['cliente_direccion'] ?? null,
            'ciudad' => $pedidoData['cliente_ciudad'] ?? null,
            'departamento' => $pedidoData['cliente_departamento'] ?? null
        ];
        
        if ($cliente) {
            // Actualizar cliente existente
            $this->update($cliente['id'], $clienteData);
            return $cliente['id'];
        } else {
            // Crear nuevo cliente
            return $this->create($clienteData);
        }
    }
}
