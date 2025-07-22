<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'config_secure.php';
header('Content-Type: application/json; charset=UTF-8');

$cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$out = [];

if($cat || $search){
    $sql = "SELECT p.id, p.nombre, c.nombre as categoria, p.precio 
            FROM productos p 
            LEFT JOIN categorias_productos c ON p.categoria_id = c.id 
            WHERE p.activo = 1";
    $params = [];
    $types = "";
    
    if($cat && $search) {
        // Buscar por categoría Y nombre
        $sql .= " AND LOWER(TRIM(c.nombre)) = ? AND LOWER(p.nombre) LIKE ?";
        $params[] = mb_strtolower($cat, 'UTF-8');
        $params[] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $types = "ss";
    } elseif($cat) {
        // Solo por categoría
        $sql .= " AND LOWER(TRIM(c.nombre)) = ?";
        $params[] = mb_strtolower($cat, 'UTF-8');
        $types = "s";
    } elseif($search) {
        // Solo por nombre
        $sql .= " AND LOWER(p.nombre) LIKE ?";
        $params[] = '%' . mb_strtolower($search, 'UTF-8') . '%';
        $types = "s";
    }
    
    $sql .= " ORDER BY p.nombre ASC";
    
    $stmt = $conn->prepare($sql);
    if($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    
    // Usar bind_result para compatibilidad con versiones antiguas de MySQLi
    $stmt->bind_result($id, $nombre, $categoria, $precio);
    
    while ($stmt->fetch()) {
        $out[] = [
            "id" => $id,
            "nombre" => $nombre,
            "categoria" => $categoria,
            "precio" => $precio
        ];
    }
    $stmt->close();
}

echo json_encode(["productos"=>$out], JSON_UNESCAPED_UNICODE);
exit;