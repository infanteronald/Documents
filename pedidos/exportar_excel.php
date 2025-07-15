<?php
include 'conexion.php';

// Filtros
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'hoy';
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Fecha filtro
switch($filtro) {
    case 'hoy':
        $where = "DATE(fecha) = CURDATE()";
        break;
    case 'semana':
        $where = "YEARWEEK(fecha,1) = YEARWEEK(CURDATE(),1)";
        break;
    case 'quincena':
        $where = "fecha >= CURDATE() - INTERVAL 15 DAY";
        break;
    case 'mes':
        $where = "MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())";
        break;
    default:
        $where = "1";
}
if($buscar){
    $buscarSql = $conn->real_escape_string($buscar);
    $where .= " AND (nombre LIKE '%$buscarSql%' OR telefono LIKE '%$buscarSql%' OR id = '$buscarSql' OR correo LIKE '%$buscarSql%')";
}

$result = $conn->query("SELECT * FROM pedidos_detal WHERE $where ORDER BY fecha DESC");

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=pedidos_exportados_'.date('Ymd_His').'.csv');

$output = fopen('php://output', 'w');

// Encabezados
fputcsv($output, [
    'ID', 'Nombre', 'Teléfono', 'Correo', 'Monto', 'Persona Recibe', 'Dirección', 'Horarios', 'Método Pago', 'Datos Pago',
    'Estado', 'Fecha', 'Comprobante', 'Nota Interna'
]);

while($row = $result->fetch_assoc()){
    fputcsv($output, [
        $row['id'], $row['nombre'], $row['telefono'], $row['correo'], $row['monto'], $row['persona_recibe'],
        $row['direccion'], $row['horarios'], $row['metodo_pago'], $row['datos_pago'], $row['estado'],
        $row['fecha'], $row['comprobante'], $row['nota_interna']
    ]);
}
fclose($output);
exit;
?>