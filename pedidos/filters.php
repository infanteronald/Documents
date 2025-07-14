<?php
/**
 * Clase para manejo de filtros de pedidos
 * Separa la lógica de filtrado del archivo principal
 */
class PedidosFilter {
    private $conn;
    private $cache_duration = 3600;
    private $cache_file = 'cache/filter_options.json';
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Obtiene y valida parámetros de filtro desde $_GET
     */
    public function getFilterParams() {
        return [
            'filtro' => isset($_GET['filtro']) ? $_GET['filtro'] : 'pendientes_atencion',
            'buscar' => isset($_GET['buscar']) ? trim($_GET['buscar']) : '',
            'metodo_pago' => isset($_GET['metodo_pago']) ? $_GET['metodo_pago'] : '',
            'ciudad' => isset($_GET['ciudad']) ? $_GET['ciudad'] : '',
            'fecha_desde' => isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '',
            'fecha_hasta' => isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '',
            'page' => isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1,
            'limite' => 20
        ];
    }
    
    /**
     * Construye la cláusula WHERE basada en el filtro principal
     */
    public function buildWhereClause($filtro) {
        switch($filtro) {
            case 'hoy':
                return "DATE(fecha) = CURDATE() AND archivado = '0' AND anulado = '0'";
            case 'semana':
                return "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1) AND archivado = '0' AND anulado = '0'";
            case 'quincena':
                return "fecha >= CURDATE() - INTERVAL 15 DAY AND archivado = '0' AND anulado = '0'";
            case 'mes':
                return "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE()) AND archivado = '0' AND anulado = '0'";
            case 'ultimos_30':
                return "fecha >= CURDATE() - INTERVAL 30 DAY AND archivado = '0' AND anulado = '0'";
            case 'ultimos_60':
                return "fecha >= CURDATE() - INTERVAL 60 DAY AND archivado = '0' AND anulado = '0'";
            case 'ultimos_90':
                return "fecha >= CURDATE() - INTERVAL 90 DAY AND archivado = '0' AND anulado = '0'";
            case 'archivados':
                return "archivado = '1'";
            case 'anulados':
                return "anulado = '1'";
            case 'enviados':
                return "enviado = '1' AND archivado = '0' AND anulado = '0'";
            case 'pago_pendiente':
                return "pagado = '0' AND archivado = '0' AND anulado = '0'";
            case 'pago_confirmado':
                return "pagado = '1' AND archivado = '0' AND anulado = '0'";
            case 'con_comprobante':
                return "tiene_comprobante = '1' AND archivado = '0' AND anulado = '0'";
            case 'sin_comprobante':
                return "tiene_comprobante = '0' AND pagado = '0' AND archivado = '0' AND anulado = '0'";
            case 'con_guia':
                return "tiene_guia = '1' AND archivado = '0' AND anulado = '0'";
            case 'pendientes_atencion':
                return "
                    archivado = '0' AND anulado = '0' AND (
                        (enviado = '0') OR
                        (tiene_guia = '0') OR 
                        (pagado = '0' AND tiene_comprobante = '0' AND metodo_pago NOT LIKE '%efectivo%') OR
                        (pagado = '0' AND metodo_pago LIKE '%efectivo%')
                    )
                ";
            case 'personalizado':
            case 'todos':
            default:
                return "archivado = '0' AND anulado = '0'";
        }
    }
    
    /**
     * Construye condiciones de búsqueda inteligente
     */
    public function buildSearchConditions($buscar) {
        if(!$buscar || trim($buscar) === '') {
            return '';
        }
        
        $buscarSql = $this->conn->real_escape_string(trim($buscar));
        
        // Si es un número puro, priorizar búsqueda por ID
        if(is_numeric($buscarSql) && strlen($buscarSql) <= 8) {
            return " AND (
                p.id = '$buscarSql' OR
                p.telefono LIKE '%$buscarSql%' OR
                p.nombre LIKE '%$buscarSql%' OR
                p.correo LIKE '%$buscarSql%'
            )";
        }
        
        // Dividir términos de búsqueda
        $buscarTerminos = array_filter(explode(' ', $buscarSql), function($termino) {
            return strlen(trim($termino)) >= 2;
        });
        
        if(empty($buscarTerminos)) {
            return '';
        }
        
        $condicionesBusqueda = [];
        
        foreach($buscarTerminos as $termino) {
            $termino = trim($termino);
            $termino = $this->conn->real_escape_string($termino);
            
            $condicionesTermino = [
                // Datos principales del cliente
                "p.nombre LIKE '%$termino%'",
                "p.correo LIKE '%$termino%'",
                "p.telefono LIKE '%$termino%'",
                // Ubicación
                "p.ciudad LIKE '%$termino%'",
                "p.barrio LIKE '%$termino%'",
                "p.direccion LIKE '%$termino%'",
                // Información de pago
                "p.metodo_pago LIKE '%$termino%'",
                "p.datos_pago LIKE '%$termino%'",
                // Notas
                "p.nota_interna LIKE '%$termino%'"
            ];
            
            // Búsqueda por ID si es numérico
            if(is_numeric($termino)) {
                $condicionesTermino[] = "p.id = '$termino'";
            }
            
            // Búsqueda por fecha
            if(preg_match('/\d{4}-\d{2}-\d{2}/', $termino)) {
                $condicionesTermino[] = "DATE(p.fecha) = '$termino'";
                $condicionesTermino[] = "DATE_FORMAT(p.fecha, '%Y-%m-%d') LIKE '%$termino%'";
            }
            if(preg_match('/\d{2}\/\d{2}\/\d{4}/', $termino)) {
                $condicionesTermino[] = "DATE_FORMAT(p.fecha, '%d/%m/%Y') LIKE '%$termino%'";
            }
            
            // Búsqueda por año
            if(preg_match('/^20\d{2}$/', $termino)) {
                $condicionesTermino[] = "YEAR(p.fecha) = '$termino'";
            }
            
            // Búsqueda por mes
            $meses = [
                'enero' => '01', 'febrero' => '02', 'marzo' => '03', 'abril' => '04',
                'mayo' => '05', 'junio' => '06', 'julio' => '07', 'agosto' => '08',
                'septiembre' => '09', 'octubre' => '10', 'noviembre' => '11', 'diciembre' => '12'
            ];
            $terminoLower = strtolower($termino);
            if(isset($meses[$terminoLower])) {
                $numeroMes = $meses[$terminoLower];
                $condicionesTermino[] = "MONTH(p.fecha) = '$numeroMes'";
            }
            
            $condicionesBusqueda[] = "(" . implode(" OR ", $condicionesTermino) . ")";
        }
        
        return " AND (" . implode(" AND ", $condicionesBusqueda) . ")";
    }
    
    /**
     * Añade filtros adicionales
     */
    public function addAdditionalFilters($where, $params) {
        if($params['metodo_pago']) {
            $metodoPagoSql = $this->conn->real_escape_string($params['metodo_pago']);
            $where .= " AND metodo_pago = '$metodoPagoSql'";
        }
        
        if($params['ciudad']) {
            $ciudadSql = $this->conn->real_escape_string($params['ciudad']);
            $where .= " AND ciudad LIKE '%$ciudadSql%'";
        }
        
        if($params['fecha_desde']) {
            $where .= " AND DATE(fecha) >= '" . $this->conn->real_escape_string($params['fecha_desde']) . "'";
        }
        
        if($params['fecha_hasta']) {
            $where .= " AND DATE(fecha) <= '" . $this->conn->real_escape_string($params['fecha_hasta']) . "'";
        }
        
        return $where;
    }
    
    /**
     * Construye filtro de monto
     */
    public function buildAmountFilter($buscar) {
        if(!$buscar || !is_numeric($buscar) || strlen($buscar) < 4) {
            return '';
        }
        
        $montoNumerico = intval($buscar);
        $margenMonto = max(1000, $montoNumerico * 0.1);
        
        return " HAVING monto BETWEEN " . ($montoNumerico - $margenMonto) . " AND " . ($montoNumerico + $margenMonto);
    }
    
    /**
     * Construye la consulta principal
     */
    public function buildMainQuery($where, $montoFiltro) {
        return "
            SELECT 
                p.id, p.nombre, p.telefono, p.ciudad, p.barrio, p.correo, p.fecha, p.direccion,
                p.metodo_pago, p.datos_pago, p.comprobante, p.guia, p.nota_interna, p.enviado, p.archivado,
                p.anulado, p.tiene_guia, p.tiene_comprobante, p.pagado, p.tienda,
                COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto
            FROM pedidos_detal p
            LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
            WHERE $where
            GROUP BY p.id, p.nombre, p.telefono, p.ciudad, p.barrio, p.correo, p.fecha, p.direccion,
                     p.metodo_pago, p.datos_pago, p.comprobante, p.guia, p.nota_interna, p.enviado, p.archivado,
                     p.anulado, p.tiene_guia, p.tiene_comprobante, p.pagado, p.tienda
            $montoFiltro
            ORDER BY p.fecha DESC
        ";
    }
    
    /**
     * Construye la consulta de conteo
     */
    public function buildCountQuery($where, $montoFiltro) {
        return "
            SELECT COUNT(*) as total, COALESCE(SUM(monto_temp), 0) as monto_total
            FROM (
                SELECT COALESCE(SUM(pd.cantidad * pd.precio), 0) as monto_temp
                FROM pedidos_detal p
                LEFT JOIN pedido_detalle pd ON p.id = pd.pedido_id
                WHERE $where
                GROUP BY p.id
                $montoFiltro
            ) as subquery
        ";
    }
    
    /**
     * Obtiene opciones de filtro con caché
     */
    public function getFilterOptions() {
        $metodos_pago = [];
        $ciudades = [];
        
        // Verificar caché
        if (file_exists($this->cache_file) && (time() - filemtime($this->cache_file)) < $this->cache_duration) {
            $cached_data = json_decode(file_get_contents($this->cache_file), true);
            if ($cached_data && isset($cached_data['metodos_pago']) && isset($cached_data['ciudades'])) {
                return [
                    'metodos_pago' => $cached_data['metodos_pago'],
                    'ciudades' => $cached_data['ciudades']
                ];
            }
        }
        
        // Obtener datos de la base de datos
        $metodos_result = $this->conn->query("SELECT DISTINCT metodo_pago FROM pedidos_detal WHERE metodo_pago IS NOT NULL AND metodo_pago != '' ORDER BY metodo_pago");
        if ($metodos_result) {
            while ($row = $metodos_result->fetch_assoc()) {
                $metodos_pago[] = $row['metodo_pago'];
            }
        }
        
        $ciudades_result = $this->conn->query("SELECT DISTINCT ciudad FROM pedidos_detal WHERE ciudad IS NOT NULL AND ciudad != '' ORDER BY ciudad");
        if ($ciudades_result) {
            while ($row = $ciudades_result->fetch_assoc()) {
                $ciudades[] = $row['ciudad'];
            }
        }
        
        // Guardar en caché
        $cache_data = [
            'metodos_pago' => $metodos_pago,
            'ciudades' => $ciudades,
            'timestamp' => time()
        ];
        
        // Crear directorio cache si no existe
        if (!is_dir('cache')) {
            mkdir('cache', 0755, true);
        }
        
        file_put_contents($this->cache_file, json_encode($cache_data));
        
        return [
            'metodos_pago' => $metodos_pago,
            'ciudades' => $ciudades
        ];
    }
    
    /**
     * Procesa todos los filtros y retorna datos completos
     */
    public function processFilters() {
        $params = $this->getFilterParams();
        $offset = ($params['page'] - 1) * $params['limite'];
        
        // Construir WHERE principal
        $where = $this->buildWhereClause($params['filtro']);
        
        // Añadir búsqueda
        $where .= $this->buildSearchConditions($params['buscar']);
        
        // Añadir filtros adicionales
        $where = $this->addAdditionalFilters($where, $params);
        
        // Filtro de monto
        $montoFiltro = $this->buildAmountFilter($params['buscar']);
        
        // Construir consultas
        $main_query = $this->buildMainQuery($where, $montoFiltro);
        $count_query = $this->buildCountQuery($where, $montoFiltro);
        
        // Ejecutar consulta de conteo
        $count_result = $this->conn->query($count_query);
        if (!$count_result) {
            throw new Exception("Error en la consulta de conteo: " . $this->conn->error);
        }
        
        $count_row = $count_result->fetch_assoc();
        $total_pedidos = $count_row['total'];
        $monto_total_real = $count_row['monto_total'];
        $total_paginas = ceil($total_pedidos / $params['limite']);
        
        // Ejecutar consulta principal
        $result = $this->conn->query($main_query . " LIMIT {$params['limite']} OFFSET $offset");
        if (!$result) {
            throw new Exception("Error en la consulta: " . $this->conn->error);
        }
        
        $pedidos = [];
        while ($row = $result->fetch_assoc()) {
            $pedidos[] = $row;
        }
        
        // Obtener opciones de filtro
        $filter_options = $this->getFilterOptions();
        
        return [
            'pedidos' => $pedidos,
            'total_pedidos' => $total_pedidos,
            'monto_total_real' => $monto_total_real,
            'total_paginas' => $total_paginas,
            'params' => $params,
            'metodos_pago' => $filter_options['metodos_pago'],
            'ciudades' => $filter_options['ciudades']
        ];
    }
}
?>