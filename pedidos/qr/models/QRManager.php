<?php
/**
 * QR Manager - Modelo Principal
 * Sequoia Speed - Sistema QR
 */

class QRManager {
    private $conn;
    
    public function __construct($database) {
        $this->conn = $database;
    }
    
    /**
     * Generar código QR único
     */
    public function generateUniqueQRCode($entity_type, $entity_id, $almacen_id = null) {
        // Validar inputs
        if (!in_array($entity_type, ['producto', 'ubicacion', 'lote', 'pedido', 'almacen'])) {
            throw new Exception('Tipo de entidad inválido');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $entity_id)) {
            throw new Exception('ID de entidad contiene caracteres inválidos');
        }
        
        // Obtener configuración de formato
        $format_config = $this->getSystemConfig('qr_generation_format');
        $format = $format_config ? json_decode($format_config['config_value'], true) : [];
        
        // Generar componentes del código con valores seguros
        $prefix = isset($format['prefix']) ? preg_replace('/[^A-Z0-9]/', '', $format['prefix']) : 'SEQ';
        $year = ($format['include_year'] ?? true) ? date('Y') : '';
        $separator = '-'; // Fijo por seguridad
        
        // Crear código base
        $base_code = $prefix;
        if ($year) $base_code .= $separator . $year;
        $base_code .= $separator . $entity_type;
        $base_code .= $separator . $entity_id;
        
        // Agregar timestamp y checksum para garantizar unicidad
        $timestamp_suffix = substr(time(), -4); // Últimos 4 dígitos del timestamp
        $base_code .= $separator . $timestamp_suffix;
        
        // Agregar checksum si está habilitado
        if ($format['include_checksum'] ?? false) {
            $checksum = substr(md5($base_code . microtime(true) . rand(1000, 9999)), 0, 6);
            $base_code .= $separator . strtoupper($checksum);
        }
        
        // Agregar microsegundos para mayor unicidad
        $microseconds = substr(microtime(true) * 1000000, -6);
        $final_code = $base_code . $separator . $microseconds;
        
        // Verificar unicidad como backup
        $counter = 1;
        $max_attempts = 10;
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            // Verificar si el qr_content ya existe
            $check_query = "SELECT qr_content FROM qr_codes WHERE qr_content = ? LIMIT 1";
            $check_stmt = $this->conn->prepare($check_query);
            $check_stmt->bind_param('s', $final_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                // Código único encontrado
                break;
            }
            
            // Si ya existe, generar siguiente número con más entropía
            $random_suffix = rand(100000, 999999);
            $final_code = $base_code . $separator . $microseconds . $separator . $random_suffix;
            $counter++;
            $attempt++;
            
            if ($counter > 999) {
                throw new Exception('No se pudo generar un código QR único después de 999 intentos');
            }
        }
        
        if ($attempt >= $max_attempts) {
            throw new Exception('No se pudo generar un código QR único después de ' . $max_attempts . ' intentos');
        }
        
        return $final_code;
    }
    
    /**
     * Crear QR para producto
     */
    public function createProductQR($producto_id, $almacen_id, $user_id, $additional_data = []) {
        try {
            $this->conn->begin_transaction();
            
            // Obtener datos del producto
            $producto = $this->getProductData($producto_id);
            if (!$producto) {
                throw new Exception('Producto no encontrado');
            }
            
            // Obtener inventario del almacén
            $inventario = $this->getInventoryData($producto_id, $almacen_id);
            
            // Generar código QR único
            $qr_content = $this->generateUniqueQRCode('producto', $producto_id, $almacen_id);
            $qr_uuid = $this->generateUUID();
            
            // Preparar datos base
            $base_data = [
                'producto_id' => $producto_id,
                'producto_nombre' => $producto['nombre'],
                'producto_sku' => $producto['sku'],
                'almacen_id' => $almacen_id,
                'stock_actual' => $inventario['stock_actual'] ?? 0,
                'ubicacion_fisica' => $inventario['ubicacion_fisica'] ?? '',
                'generated_at' => date('Y-m-d H:i:s'),
                'additional_data' => $additional_data
            ];
            
            // Reglas de contexto por defecto
            $context_rules = [
                [
                    'condition' => ['time_range' => '08:00-12:00'],
                    'data' => ['suggested_action' => 'entrada', 'priority' => 'high']
                ],
                [
                    'condition' => ['time_range' => '14:00-18:00'], 
                    'data' => ['suggested_action' => 'salida', 'priority' => 'high']
                ],
                [
                    'condition' => ['user_role' => 'auditor'],
                    'data' => ['show_detailed_info' => true, 'enable_adjustments' => true]
                ]
            ];
            
            // Insertar QR en base de datos
            $query = "INSERT INTO qr_codes (
                qr_uuid, qr_content, entity_type, entity_id, 
                base_data, context_rules, 
                linked_inventory_id, linked_product_id, linked_almacen_id,
                created_by
            ) VALUES (?, ?, 'producto', ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->conn->prepare($query);
            $base_data_json = json_encode($base_data);
            $context_rules_json = json_encode($context_rules);
            $inventory_id = $inventario['id'] ?? null;
            
            $stmt->bind_param('sssssiiii', 
                $qr_uuid, $qr_content, $producto_id,
                $base_data_json, $context_rules_json,
                $inventory_id, $producto_id, $almacen_id, $user_id
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al crear el código QR: ' . $stmt->error);
            }
            
            $qr_id = $this->conn->insert_id;
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'qr_id' => $qr_id,
                'qr_content' => $qr_content,
                'qr_uuid' => $qr_uuid,
                'base_data' => $base_data
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Procesar escaneo de QR
     */
    public function processScan($qr_content, $user_id, $action, $context = []) {
        try {
            $this->conn->begin_transaction();
            
            // Obtener datos del QR
            $qr_data = $this->getQRByContent($qr_content);
            if (!$qr_data) {
                throw new Exception('Código QR no válido o no encontrado');
            }
            
            // Validar permisos del usuario
            if (!$this->validateUserPermissions($user_id, $action)) {
                throw new Exception('No tiene permisos para realizar esta acción');
            }
            
            // Obtener datos contextuales
            $contextual_data = $this->getContextualData($qr_data, $context);
            
            // Generar UUID para la transacción
            $transaction_uuid = $this->generateUUID();
            
            // Registrar transacción de escaneo
            $scan_data = [
                'transaction_uuid' => $transaction_uuid,
                'qr_code_id' => $qr_data['id'],
                'user_id' => $user_id,
                'action_performed' => $action,
                'scan_method' => $context['scan_method'] ?? 'camera_mobile',
                'device_info' => json_encode($context['device_info'] ?? []),
                'scan_location' => $context['location'] ?? '',
                'quantity_affected' => $context['quantity'] ?? 1,
                'notes' => $context['notes'] ?? '',
                'workflow_type' => $context['workflow_type'] ?? null
            ];
            
            $movement_id = null;
            
            // Procesar acción específica
            switch ($action) {
                case 'entrada':
                    $movement_id = $this->processEntrada($qr_data, $scan_data, $context);
                    break;
                case 'salida':
                    $movement_id = $this->processSalida($qr_data, $scan_data, $context);
                    break;
                case 'conteo':
                    $movement_id = $this->processConteo($qr_data, $scan_data, $context);
                    break;
                case 'consulta':
                    // Solo registrar la consulta, no crear movimiento
                    break;
                default:
                    throw new Exception('Acción no válida: ' . $action);
            }
            
            // Insertar transacción de escaneo
            $this->insertScanTransaction($scan_data, $movement_id);
            
            // Actualizar contador de escaneos en el QR
            $this->updateQRScanCount($qr_data['id']);
            
            $this->conn->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_uuid,
                'qr_data' => $qr_data,
                'contextual_data' => $contextual_data,
                'movement_id' => $movement_id,
                'message' => $this->getSuccessMessage($action)
            ];
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Obtener estadísticas del sistema QR
     */
    public function getQRStats($almacen_id = null) {
        $params = [];
        $types = '';
        $where_clause = '';
        
        if ($almacen_id !== null) {
            $almacen_id = (int)$almacen_id;
            if ($almacen_id <= 0) {
                throw new Exception('ID de almacén inválido');
            }
            $where_clause = "WHERE qc.linked_almacen_id = ?";
            $params[] = $almacen_id;
            $types = 'i';
        }
        
        $query = "
            SELECT 
                COUNT(DISTINCT qc.id) as total_qr_codes,
                COUNT(DISTINCT qst.id) as total_scans,
                COUNT(DISTINCT qst.user_id) as active_users,
                COUNT(DISTINCT CASE WHEN DATE(qst.scanned_at) = CURDATE() THEN qst.id END) as scans_today,
                COUNT(DISTINCT CASE WHEN qst.processing_status = 'success' THEN qst.id END) as successful_scans,
                COUNT(DISTINCT CASE WHEN qst.processing_status = 'failed' THEN qst.id END) as failed_scans,
                AVG(qst.processing_duration_ms) as avg_processing_time
            FROM qr_codes qc
            LEFT JOIN qr_scan_transactions qst ON qc.id = qst.qr_code_id
            $where_clause
        ";
        
        if ($params) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result ? $result->fetch_assoc() : [];
        } else {
            $result = $this->conn->query($query);
            return $result ? $result->fetch_assoc() : [];
        }
    }
    
    /**
     * Buscar QR por contenido
     */
    public function getQRByContent($qr_content) {
        $query = "SELECT * FROM qr_codes WHERE qr_content = ? AND active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $qr_content);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $qr_data = $result->fetch_assoc();
            $qr_data['base_data'] = json_decode($qr_data['base_data'], true);
            $qr_data['context_rules'] = json_decode($qr_data['context_rules'], true);
            return $qr_data;
        }
        
        return null;
    }
    
    /**
     * Obtener configuración del sistema
     */
    public function getSystemConfig($config_key) {
        $query = "SELECT * FROM qr_system_config WHERE config_key = ? AND active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $config_key);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    // Métodos auxiliares privados
    
    private function qrCodeExists($qr_content) {
        $query = "SELECT id FROM qr_codes WHERE qr_content = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $qr_content);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0;
    }
    
    private function generateUUID() {
        // Generar UUID v4 criptográficamente seguro
        $data = random_bytes(16);
        
        // Establecer bits de versión (v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        // Establecer bits de variante
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        
        return sprintf('%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
    
    private function getProductData($producto_id) {
        $query = "SELECT * FROM productos WHERE id = ? AND activo = 1 LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    private function getInventoryData($producto_id, $almacen_id) {
        $query = "SELECT * FROM inventario_almacen WHERE producto_id = ? AND almacen_id = ? LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $producto_id, $almacen_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->num_rows > 0 ? $result->fetch_assoc() : null;
    }
    
    private function validateUserPermissions($user_id, $action) {
        // Integrar con sistema RBAC existente
        require_once dirname(dirname(__DIR__)) . '/accesos/middleware/AuthMiddleware.php';
        
        try {
            $auth = new AuthMiddleware($this->conn);
            $user = $auth->getUserById($user_id);
            
            if (!$user || !$user['activo']) {
                return false;
            }
            
            // Mapear acciones QR a permisos de inventario
            $permission_map = [
                'entrada' => 'crear',
                'salida' => 'actualizar', 
                'conteo' => 'actualizar',
                'consulta' => 'leer',
                'ajuste' => 'actualizar'
            ];
            
            $required_permission = $permission_map[$action] ?? 'leer';
            
            return $auth->hasPermission($user_id, 'inventario', $required_permission);
            
        } catch (Exception $e) {
            error_log("Error validando permisos QR: " . $e->getMessage());
            return false;
        }
    }
    
    private function getContextualData($qr_data, $context) {
        $contextual_data = $qr_data['base_data'];
        
        // Aplicar reglas de contexto
        if (!empty($qr_data['context_rules'])) {
            $current_time = date('H:i');
            $user_role = $context['user_role'] ?? '';
            
            foreach ($qr_data['context_rules'] as $rule) {
                if (isset($rule['condition']['time_range'])) {
                    list($start, $end) = explode('-', $rule['condition']['time_range']);
                    if ($current_time >= $start && $current_time <= $end) {
                        $contextual_data = array_merge($contextual_data, $rule['data']);
                    }
                }
                
                if (isset($rule['condition']['user_role']) && $rule['condition']['user_role'] === $user_role) {
                    $contextual_data = array_merge($contextual_data, $rule['data']);
                }
            }
        }
        
        return $contextual_data;
    }
    
    private function processEntrada($qr_data, $scan_data, $context) {
        // Crear movimiento de entrada en el sistema existente
        $movement_data = [
            'producto_id' => $qr_data['linked_product_id'],
            'almacen_id' => $qr_data['linked_almacen_id'],
            'tipo_movimiento' => 'entrada',
            'cantidad' => $context['quantity'] ?? 1,
            'motivo' => 'Entrada por QR',
            'documento_referencia' => 'QR-' . $scan_data['transaction_uuid'],
            'usuario_responsable' => $scan_data['user_id'],
            'observaciones' => $scan_data['notes']
        ];
        
        return $this->createMovimientoInventario($movement_data);
    }
    
    private function processSalida($qr_data, $scan_data, $context) {
        // Verificar stock disponible
        $inventario = $this->getInventoryData($qr_data['linked_product_id'], $qr_data['linked_almacen_id']);
        $quantity = $context['quantity'] ?? 1;
        
        if ($inventario && $inventario['stock_actual'] < $quantity) {
            throw new Exception('Stock insuficiente. Disponible: ' . $inventario['stock_actual']);
        }
        
        $movement_data = [
            'producto_id' => $qr_data['linked_product_id'],
            'almacen_id' => $qr_data['linked_almacen_id'],
            'tipo_movimiento' => 'salida',
            'cantidad' => $quantity,
            'motivo' => 'Salida por QR',
            'documento_referencia' => 'QR-' . $scan_data['transaction_uuid'],
            'usuario_responsable' => $scan_data['user_id'],
            'observaciones' => $scan_data['notes']
        ];
        
        return $this->createMovimientoInventario($movement_data);
    }
    
    private function processConteo($qr_data, $scan_data, $context) {
        // Procesar conteo de inventario
        $inventario = $this->getInventoryData($qr_data['linked_product_id'], $qr_data['linked_almacen_id']);
        $counted_quantity = $context['quantity'] ?? 0;
        $system_quantity = $inventario['stock_actual'] ?? 0;
        
        if ($counted_quantity != $system_quantity) {
            // Crear ajuste de inventario
            $adjustment = $counted_quantity - $system_quantity;
            $movement_data = [
                'producto_id' => $qr_data['linked_product_id'],
                'almacen_id' => $qr_data['linked_almacen_id'],
                'tipo_movimiento' => 'ajuste',
                'cantidad' => abs($adjustment),
                'motivo' => 'Ajuste por conteo QR',
                'documento_referencia' => 'QR-COUNT-' . $scan_data['transaction_uuid'],
                'usuario_responsable' => $scan_data['user_id'],
                'observaciones' => "Conteo: $counted_quantity, Sistema: $system_quantity, Diferencia: $adjustment"
            ];
            
            return $this->createMovimientoInventario($movement_data);
        }
        
        return null; // No se requiere ajuste
    }
    
    private function createMovimientoInventario($movement_data) {
        // Integrar con el sistema de movimientos existente
        require_once dirname(dirname(__DIR__)) . '/inventario/config_almacenes.php';
        
        // Obtener datos actuales del inventario
        $inventario = $this->getInventoryData($movement_data['producto_id'], $movement_data['almacen_id']);
        $stock_anterior = $inventario['stock_actual'] ?? 0;
        
        // Calcular nuevo stock
        $cantidad = $movement_data['cantidad'];
        switch ($movement_data['tipo_movimiento']) {
            case 'entrada':
                $stock_nuevo = $stock_anterior + $cantidad;
                break;
            case 'salida':
                $stock_nuevo = $stock_anterior - $cantidad;
                break;
            case 'ajuste':
                // Para ajustes, el nuevo stock debe calcularse según el tipo de ajuste
                $stock_nuevo = $stock_anterior + $cantidad; // Se asume ajuste positivo por defecto
                break;
            default:
                $stock_nuevo = $stock_anterior;
        }
        
        // Insertar movimiento
        $query = "INSERT INTO movimientos_inventario (
            producto_id, almacen_id, tipo_movimiento, cantidad, 
            cantidad_anterior, cantidad_nueva, motivo, documento_referencia,
            usuario_responsable, observaciones
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iisiiissss',
            $movement_data['producto_id'],
            $movement_data['almacen_id'],
            $movement_data['tipo_movimiento'],
            $cantidad,
            $stock_anterior,
            $stock_nuevo,
            $movement_data['motivo'],
            $movement_data['documento_referencia'],
            $movement_data['usuario_responsable'],
            $movement_data['observaciones']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al crear movimiento de inventario: ' . $stmt->error);
        }
        
        $movement_id = $this->conn->insert_id;
        
        // Actualizar stock en inventario_almacen
        $this->updateInventoryStock($movement_data['producto_id'], $movement_data['almacen_id'], $stock_nuevo, $movement_data['tipo_movimiento']);
        
        return $movement_id;
    }
    
    private function updateInventoryStock($producto_id, $almacen_id, $new_stock, $movement_type) {
        // Implementar stock locking para prevenir race conditions
        // Primero, obtener el stock actual con lock exclusivo
        $lock_query = "SELECT stock_actual FROM inventario_almacen 
                      WHERE producto_id = ? AND almacen_id = ? FOR UPDATE";
        $lock_stmt = $this->conn->prepare($lock_query);
        $lock_stmt->bind_param('ii', $producto_id, $almacen_id);
        $lock_stmt->execute();
        $current_stock_result = $lock_stmt->get_result();
        
        if ($current_stock_result->num_rows === 0) {
            throw new Exception('Registro de inventario no encontrado para bloqueo');
        }
        
        $current_stock_data = $current_stock_result->fetch_assoc();
        $current_stock = $current_stock_data['stock_actual'];
        
        // Validar que el stock no sea negativo (excepto para ajustes administrativos)
        if ($new_stock < 0 && $movement_type !== 'ajuste') {
            throw new Exception('El stock no puede ser negativo. Stock actual: ' . $current_stock);
        }
        
        // Preparar campos de actualización
        $update_fields = ['stock_actual = ?'];
        $params = [$new_stock];
        $types = 'i';
        
        // Actualizar fechas según el tipo de movimiento
        if ($movement_type === 'entrada') {
            $update_fields[] = 'fecha_ultima_entrada = NOW()';
        } elseif ($movement_type === 'salida') {
            $update_fields[] = 'fecha_ultima_salida = NOW()';
        }
        
        $update_fields[] = 'fecha_actualizacion = NOW()';
        
        $params[] = $producto_id;
        $params[] = $almacen_id;
        $types .= 'ii';
        
        // Actualizar con condición adicional para verificar que el stock no haya cambiado
        $query = "UPDATE inventario_almacen SET " . implode(', ', $update_fields) . " 
                 WHERE producto_id = ? AND almacen_id = ? AND stock_actual = ?";
        
        $params[] = $current_stock;
        $types .= 'i';
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Error al actualizar stock: ' . $stmt->error);
        }
        
        // Verificar que se actualizó exactamente un registro
        if ($stmt->affected_rows === 0) {
            throw new Exception('Stock modificado por otro proceso. Intente nuevamente.');
        }
    }
    
    private function insertScanTransaction($scan_data, $movement_id = null) {
        $query = "INSERT INTO qr_scan_transactions (
            transaction_uuid, qr_code_id, user_id, scan_method, device_info,
            scan_location, action_performed, quantity_affected, notes,
            generated_movement_id, workflow_type
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('siissssisis',
            $scan_data['transaction_uuid'],
            $scan_data['qr_code_id'],
            $scan_data['user_id'],
            $scan_data['scan_method'],
            $scan_data['device_info'],
            $scan_data['scan_location'],
            $scan_data['action_performed'],
            $scan_data['quantity_affected'],
            $scan_data['notes'],
            $movement_id,
            $scan_data['workflow_type']
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Error al registrar transacción de escaneo: ' . $stmt->error);
        }
    }
    
    private function updateQRScanCount($qr_code_id) {
        $query = "UPDATE qr_codes SET scan_count = scan_count + 1, last_scanned_at = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $qr_code_id);
        $stmt->execute();
    }
    
    private function getSuccessMessage($action) {
        $messages = [
            'entrada' => 'Entrada registrada correctamente',
            'salida' => 'Salida registrada correctamente',
            'conteo' => 'Conteo procesado correctamente',
            'consulta' => 'Información consultada correctamente'
        ];
        
        return $messages[$action] ?? 'Operación completada correctamente';
    }
}
?>