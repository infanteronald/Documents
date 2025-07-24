<?php
/**
 * Sistema de Alertas Automáticas
 * Sequoia Speed - Módulo de Inventario
 */

// Definir constante requerida por config_secure.php
defined('SEQUOIA_SPEED_SYSTEM') || define('SEQUOIA_SPEED_SYSTEM', true);

require_once '../config_secure.php';
require_once '../notifications/notification_helpers.php';
require_once '../php82_helpers.php';

class SistemaAlertas {
    private $conn;
    private $debug = false;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Verificar todas las alertas configuradas
     */
    public function verificarTodasLasAlertas() {
        $resultados = [];
        
        // Obtener configuraciones activas
        $query = "SELECT * FROM configuracion_alertas WHERE activa = 1";
        $configuraciones = $this->conn->query($query)->fetch_all(MYSQLI_ASSOC);
        
        foreach ($configuraciones as $config) {
            try {
                $resultado = $this->verificarAlerta($config);
                $resultados[] = $resultado;
                
                // Actualizar timestamp de última verificación
                $this->actualizarUltimaVerificacion($config['id']);
                
            } catch (Exception $e) {
                error_log("Error verificando alerta {$config['tipo_alerta']}: " . $e->getMessage());
                $resultados[] = [
                    'tipo' => $config['tipo_alerta'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $resultados;
    }
    
    /**
     * Verificar una alerta específica
     */
    private function verificarAlerta($config) {
        $tipo = $config['tipo_alerta'];
        $parametros = json_decode($config['parametros'], true);
        
        switch ($tipo) {
            case 'stock_bajo':
                return $this->verificarStockBajo($parametros);
            case 'stock_critico':
                return $this->verificarStockCritico($parametros);
            case 'stock_alto':
                return $this->verificarStockAlto($parametros);
            case 'sin_movimiento':
                return $this->verificarSinMovimiento($parametros);
            default:
                return ['tipo' => $tipo, 'success' => false, 'error' => 'Tipo de alerta no implementado'];
        }
    }
    
    /**
     * Verificar stock bajo
     */
    private function verificarStockBajo($parametros) {
        $umbral_porcentaje = $parametros['umbral_porcentaje'] ?? 20;
        $incluir_inactivos = $parametros['incluir_inactivos'] ?? false;
        
        $where_activo = $incluir_inactivos ? '' : 'AND p.activo = 1';
        
        $query = "SELECT 
            p.id as producto_id,
            p.nombre as producto_nombre,
            c.nombre as categoria,
            ia.almacen_id,
            a.nombre as almacen_nombre,
            ia.stock_actual,
            ia.stock_minimo,
            ia.stock_maximo,
            ROUND((ia.stock_actual / ia.stock_maximo) * 100, 2) as porcentaje_stock
        FROM productos p
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
        INNER JOIN almacenes a ON ia.almacen_id = a.id
        WHERE a.activo = 1 
        AND ia.stock_actual <= ia.stock_minimo
        AND (ia.stock_actual / ia.stock_maximo) * 100 <= ?
        $where_activo
        ORDER BY porcentaje_stock ASC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('d', $umbral_porcentaje);
        $stmt->execute();
        $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $alertas_creadas = 0;
        foreach ($productos as $producto) {
            // Verificar si ya existe una alerta pendiente para este producto/almacén
            if (!$this->existeAlertaPendiente('stock_bajo', $producto['producto_id'], $producto['almacen_id'])) {
                $this->crearAlerta(
                    'stock_bajo',
                    $producto['producto_id'],
                    $producto['almacen_id'],
                    "Stock bajo: {$producto['producto_nombre']} en {$producto['almacen_nombre']} ({$producto['stock_actual']} unidades, {$producto['porcentaje_stock']}%)",
                    'alta',
                    [
                        'stock_actual' => $producto['stock_actual'],
                        'stock_minimo' => $producto['stock_minimo'],
                        'stock_maximo' => $producto['stock_maximo'],
                        'porcentaje_stock' => $producto['porcentaje_stock']
                    ]
                );
                $alertas_creadas++;
            }
        }
        
        return [
            'tipo' => 'stock_bajo',
            'success' => true,
            'productos_encontrados' => count($productos),
            'alertas_creadas' => $alertas_creadas
        ];
    }
    
    /**
     * Verificar stock crítico (stock = 0)
     */
    private function verificarStockCritico($parametros) {
        $incluir_inactivos = $parametros['incluir_inactivos'] ?? false;
        $where_activo = $incluir_inactivos ? '' : 'AND p.activo = 1';
        
        $query = "SELECT 
            p.id as producto_id,
            p.nombre as producto_nombre,
            c.nombre as categoria,
            ia.almacen_id,
            a.nombre as almacen_nombre,
            ia.stock_actual,
            ia.stock_minimo
        FROM productos p
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
        INNER JOIN almacenes a ON ia.almacen_id = a.id
        WHERE a.activo = 1 
        AND ia.stock_actual = 0
        $where_activo
        ORDER BY p.nombre";
        
        $productos = $this->conn->query($query)->fetch_all(MYSQLI_ASSOC);
        
        $alertas_creadas = 0;
        foreach ($productos as $producto) {
            if (!$this->existeAlertaPendiente('stock_critico', $producto['producto_id'], $producto['almacen_id'])) {
                $this->crearAlerta(
                    'stock_critico',
                    $producto['producto_id'],
                    $producto['almacen_id'],
                    "Stock crítico: {$producto['producto_nombre']} en {$producto['almacen_nombre']} (SIN STOCK)",
                    'critica',
                    [
                        'stock_actual' => $producto['stock_actual'],
                        'stock_minimo' => $producto['stock_minimo']
                    ]
                );
                $alertas_creadas++;
            }
        }
        
        return [
            'tipo' => 'stock_critico',
            'success' => true,
            'productos_encontrados' => count($productos),
            'alertas_creadas' => $alertas_creadas
        ];
    }
    
    /**
     * Verificar stock alto
     */
    private function verificarStockAlto($parametros) {
        $umbral_porcentaje = $parametros['umbral_porcentaje'] ?? 90;
        $incluir_inactivos = $parametros['incluir_inactivos'] ?? false;
        
        $where_activo = $incluir_inactivos ? '' : 'AND p.activo = 1';
        
        $query = "SELECT 
            p.id as producto_id,
            p.nombre as producto_nombre,
            c.nombre as categoria,
            ia.almacen_id,
            a.nombre as almacen_nombre,
            ia.stock_actual,
            ia.stock_minimo,
            ia.stock_maximo,
            ROUND((ia.stock_actual / ia.stock_maximo) * 100, 2) as porcentaje_stock
        FROM productos p
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
        INNER JOIN almacenes a ON ia.almacen_id = a.id
        WHERE a.activo = 1 
        AND (ia.stock_actual / ia.stock_maximo) * 100 >= ?
        $where_activo
        ORDER BY porcentaje_stock DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('d', $umbral_porcentaje);
        $stmt->execute();
        $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $alertas_creadas = 0;
        foreach ($productos as $producto) {
            if (!$this->existeAlertaPendiente('stock_alto', $producto['producto_id'], $producto['almacen_id'])) {
                $this->crearAlerta(
                    'stock_alto',
                    $producto['producto_id'],
                    $producto['almacen_id'],
                    "Stock alto: {$producto['producto_nombre']} en {$producto['almacen_nombre']} ({$producto['stock_actual']} unidades, {$producto['porcentaje_stock']}%)",
                    'baja',
                    [
                        'stock_actual' => $producto['stock_actual'],
                        'stock_minimo' => $producto['stock_minimo'],
                        'stock_maximo' => $producto['stock_maximo'],
                        'porcentaje_stock' => $producto['porcentaje_stock']
                    ]
                );
                $alertas_creadas++;
            }
        }
        
        return [
            'tipo' => 'stock_alto',
            'success' => true,
            'productos_encontrados' => count($productos),
            'alertas_creadas' => $alertas_creadas
        ];
    }
    
    /**
     * Verificar productos sin movimiento
     */
    private function verificarSinMovimiento($parametros) {
        $dias_sin_movimiento = $parametros['dias_sin_movimiento'] ?? 30;
        $incluir_inactivos = $parametros['incluir_inactivos'] ?? false;
        
        $where_activo = $incluir_inactivos ? '' : 'AND p.activo = 1';
        
        $query = "SELECT 
            p.id as producto_id,
            p.nombre as producto_nombre,
            c.nombre as categoria,
            ia.almacen_id,
            a.nombre as almacen_nombre,
            ia.stock_actual,
            MAX(m.fecha_movimiento) as ultimo_movimiento,
            DATEDIFF(NOW(), MAX(m.fecha_movimiento)) as dias_sin_movimiento
        FROM productos p
        LEFT JOIN categorias_productos c ON p.categoria_id = c.id
        INNER JOIN inventario_almacen ia ON p.id = ia.producto_id
        INNER JOIN almacenes a ON ia.almacen_id = a.id
        LEFT JOIN movimientos_inventario m ON p.id = m.producto_id AND a.id = m.almacen_id
        WHERE a.activo = 1 
        $where_activo
        GROUP BY p.id, ia.almacen_id
        HAVING dias_sin_movimiento >= ? OR ultimo_movimiento IS NULL
        ORDER BY dias_sin_movimiento DESC";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $dias_sin_movimiento);
        $stmt->execute();
        $productos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        $alertas_creadas = 0;
        foreach ($productos as $producto) {
            if (!$this->existeAlertaPendiente('sin_movimiento', $producto['producto_id'], $producto['almacen_id'])) {
                $dias = $producto['dias_sin_movimiento'] ?? 'Sin movimientos';
                $this->crearAlerta(
                    'sin_movimiento',
                    $producto['producto_id'],
                    $producto['almacen_id'],
                    "Sin movimiento: {$producto['producto_nombre']} en {$producto['almacen_nombre']} ({$dias} días sin movimiento)",
                    'media',
                    [
                        'ultimo_movimiento' => $producto['ultimo_movimiento'],
                        'dias_sin_movimiento' => $producto['dias_sin_movimiento']
                    ]
                );
                $alertas_creadas++;
            }
        }
        
        return [
            'tipo' => 'sin_movimiento',
            'success' => true,
            'productos_encontrados' => count($productos),
            'alertas_creadas' => $alertas_creadas
        ];
    }
    
    /**
     * Crear una nueva alerta
     */
    private function crearAlerta($tipo, $producto_id, $almacen_id, $mensaje, $prioridad = 'media', $datos_adicionales = null) {
        $query = "INSERT INTO alertas_inventario 
                  (tipo_alerta, producto_id, almacen_id, mensaje, nivel_prioridad, datos_adicionales) 
                  VALUES (?, ?, ?, ?, ?, ?)";
        
        $datos_json = $datos_adicionales ? json_encode($datos_adicionales) : null;
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('siisss', $tipo, $producto_id, $almacen_id, $mensaje, $prioridad, $datos_json);
        
        if ($stmt->execute()) {
            $alerta_id = $this->conn->insert_id;
            
            // Enviar notificaciones
            $this->enviarNotificaciones($alerta_id, $tipo, $mensaje);
            
            return $alerta_id;
        }
        
        return false;
    }
    
    /**
     * Verificar si existe una alerta pendiente para un producto/almacén
     */
    private function existeAlertaPendiente($tipo, $producto_id, $almacen_id) {
        $query = "SELECT id FROM alertas_inventario 
                  WHERE tipo_alerta = ? 
                  AND producto_id = ? 
                  AND almacen_id = ? 
                  AND estado = 'pendiente'
                  AND fecha_creacion >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('sii', $tipo, $producto_id, $almacen_id);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Enviar notificaciones para una alerta
     */
    private function enviarNotificaciones($alerta_id, $tipo_alerta, $mensaje) {
        // Obtener suscriptores
        $query = "SELECT DISTINCT u.email, u.nombre, s.email_habilitado, s.sms_habilitado 
                  FROM suscripciones_alertas s
                  INNER JOIN usuarios u ON s.usuario_id = u.id
                  WHERE s.tipo_alerta = ? 
                  AND s.activa = 1 
                  AND u.activo = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('s', $tipo_alerta);
        $stmt->execute();
        $suscriptores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($suscriptores as $suscriptor) {
            if ($suscriptor['email_habilitado']) {
                $this->enviarEmail($alerta_id, $suscriptor['email'], $suscriptor['nombre'], $mensaje);
            }
        }
    }
    
    /**
     * Enviar email
     */
    private function enviarEmail($alerta_id, $email, $nombre, $mensaje) {
        $asunto = "Alerta de Inventario - Sequoia Speed";
        
        $cuerpo = "
        Estimado/a {$nombre},
        
        Se ha generado una nueva alerta en el sistema de inventario:
        
        {$mensaje}
        
        Por favor, revise el sistema para más detalles.
        
        Fecha: " . date('d/m/Y H:i:s') . "
        
        Saludos,
        Sistema de Inventario Sequoia Speed
        ";
        
        try {
            // Registrar intento de envío
            $query = "INSERT INTO historial_notificaciones 
                      (alerta_id, tipo_notificacion, destinatario, asunto, mensaje, estado) 
                      VALUES (?, 'email', ?, ?, ?, 'pendiente')";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param('isss', $alerta_id, $email, $asunto, $cuerpo);
            $stmt->execute();
            $notificacion_id = $this->conn->insert_id;
            
            // Simular envío de email (en producción usar PHPMailer o similar)
            if ($this->debug) {
                echo "EMAIL ENVIADO A: {$email}\n";
                echo "ASUNTO: {$asunto}\n";
                echo "MENSAJE: {$cuerpo}\n\n";
            }
            
            // Actualizar estado a enviado
            $query_update = "UPDATE historial_notificaciones 
                           SET estado = 'enviada', fecha_entrega = NOW() 
                           WHERE id = ?";
            $stmt_update = $this->conn->prepare($query_update);
            $stmt_update->bind_param('i', $notificacion_id);
            $stmt_update->execute();
            
            return true;
            
        } catch (Exception $e) {
            // Actualizar estado a fallida
            $query_error = "UPDATE historial_notificaciones 
                          SET estado = 'fallida', error_mensaje = ? 
                          WHERE id = ?";
            $stmt_error = $this->conn->prepare($query_error);
            $stmt_error->bind_param('si', $e->getMessage(), $notificacion_id);
            $stmt_error->execute();
            
            error_log("Error enviando email: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualizar timestamp de última verificación
     */
    private function actualizarUltimaVerificacion($config_id) {
        $query = "UPDATE configuracion_alertas SET ultima_verificacion = NOW() WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $config_id);
        $stmt->execute();
    }
    
    /**
     * Obtener alertas pendientes
     */
    public function obtenerAlertasPendientes($almacen_id = null, $limite = 50) {
        $where_almacen = $almacen_id ? 'AND ai.almacen_id = ?' : '';
        
        $query = "SELECT 
            ai.*,
            p.nombre as producto_nombre,
            p.sku as producto_sku,
            a.nombre as almacen_nombre,
            a.codigo as almacen_codigo
        FROM alertas_inventario ai
        INNER JOIN productos p ON ai.producto_id = p.id
        INNER JOIN almacenes a ON ai.almacen_id = a.id
        WHERE ai.estado = 'pendiente' 
        {$where_almacen}
        ORDER BY 
            CASE ai.nivel_prioridad 
                WHEN 'critica' THEN 1 
                WHEN 'alta' THEN 2 
                WHEN 'media' THEN 3 
                WHEN 'baja' THEN 4 
            END,
            ai.fecha_creacion DESC
        LIMIT ?";
        
        $stmt = $this->conn->prepare($query);
        
        if ($almacen_id) {
            $stmt->bind_param('ii', $almacen_id, $limite);
        } else {
            $stmt->bind_param('i', $limite);
        }
        
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Marcar alerta como vista
     */
    public function marcarComoVista($alerta_id, $usuario_id = 1) {
        $query = "UPDATE alertas_inventario 
                  SET estado = 'vista', usuario_resolucion = ? 
                  WHERE id = ? AND estado = 'pendiente'";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $usuario_id, $alerta_id);
        return $stmt->execute();
    }
    
    /**
     * Resolver alerta
     */
    public function resolverAlerta($alerta_id, $usuario_id = 1) {
        $query = "UPDATE alertas_inventario 
                  SET estado = 'resuelta', fecha_resolucion = NOW(), usuario_resolucion = ? 
                  WHERE id = ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('ii', $usuario_id, $alerta_id);
        return $stmt->execute();
    }
    
    /**
     * Activar modo debug
     */
    public function setDebug($debug = true) {
        $this->debug = $debug;
    }
}

// Si se ejecuta directamente, verificar alertas
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    $sistema = new SistemaAlertas($conn);
    $sistema->setDebug(true);
    
    echo "Verificando alertas automáticas...\n";
    $resultados = $sistema->verificarTodasLasAlertas();
    
    foreach ($resultados as $resultado) {
        echo "Tipo: {$resultado['tipo']}\n";
        echo "Éxito: " . ($resultado['success'] ? 'SÍ' : 'NO') . "\n";
        
        if ($resultado['success']) {
            echo "Productos encontrados: {$resultado['productos_encontrados']}\n";
            echo "Alertas creadas: {$resultado['alertas_creadas']}\n";
        } else {
            echo "Error: {$resultado['error']}\n";
        }
        
        echo "---\n";
    }
}
?>