<?php
/**
 * API de Exportación - Excel
 * Sequoia Speed - Sistema de exportación de datos
 * 
 * Endpoint: GET /public/api/exports/excel.php
 * Migrado desde: exportar_excel.php
 */

require_once __DIR__ . '/../../../bootstrap.php';

use SequoiaSpeed\Controllers\PedidoController;
use SequoiaSpeed\Controllers\ProductoController;

// Configurar headers iniciales
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Legacy-Compatibility');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Permitir GET y POST para compatibilidad
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener parámetros
    $tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'pedidos';
    $fecha_inicio = $_GET['fecha_inicio'] ?? $_POST['fecha_inicio'] ?? '';
    $fecha_fin = $_GET['fecha_fin'] ?? $_POST['fecha_fin'] ?? '';
    $estado = $_GET['estado'] ?? $_POST['estado'] ?? '';
    $formato = $_GET['formato'] ?? $_POST['formato'] ?? 'xlsx';
    
    // Detectar si es petición legacy
    $isLegacy = isset($_SERVER['HTTP_X_LEGACY_COMPATIBILITY']) || 
                isset($_POST['tipo']);

    // Validar tipo de exportación
    $tiposValidos = ['pedidos', 'productos', 'clientes', 'ventas'];
    if (!in_array($tipo, $tiposValidos)) {
        throw new Exception('Tipo de exportación no válido');
    }

    // Validar formato
    $formatosValidos = ['xlsx', 'csv', 'json'];
    if (!in_array($formato, $formatosValidos)) {
        $formato = 'xlsx'; // Default
    }

    // Validar fechas si se proporcionan
    if (!empty($fecha_inicio) && !strtotime($fecha_inicio)) {
        throw new Exception('Fecha de inicio inválida');
    }
    
    if (!empty($fecha_fin) && !strtotime($fecha_fin)) {
        throw new Exception('Fecha de fin inválida');
    }

    // Construir filtros
    $filtros = [];
    if (!empty($fecha_inicio)) {
        $filtros['fecha_inicio'] = $fecha_inicio;
    }
    if (!empty($fecha_fin)) {
        $filtros['fecha_fin'] = $fecha_fin;
    }
    if (!empty($estado)) {
        $filtros['estado'] = $estado;
    }

    // Obtener datos según el tipo
    $datos = [];
    $nombreArchivo = '';
    
    switch ($tipo) {
        case 'pedidos':
            $pedidoController = new PedidoController();
            $datos = $pedidoController->getForExport($filtros);
            $nombreArchivo = 'pedidos_' . date('Y-m-d_H-i-s');
            break;
            
        case 'productos':
            $productoController = new ProductoController();
            $datos = $productoController->getForExport($filtros);
            $nombreArchivo = 'productos_' . date('Y-m-d_H-i-s');
            break;
            
        case 'clientes':
            $pedidoController = new PedidoController();
            $datos = $pedidoController->getClientsForExport($filtros);
            $nombreArchivo = 'clientes_' . date('Y-m-d_H-i-s');
            break;
            
        case 'ventas':
            $pedidoController = new PedidoController();
            $datos = $pedidoController->getSalesForExport($filtros);
            $nombreArchivo = 'ventas_' . date('Y-m-d_H-i-s');
            break;
    }

    if (empty($datos)) {
        throw new Exception('No hay datos para exportar con los filtros especificados');
    }

    // Generar archivo según el formato
    switch ($formato) {
        case 'xlsx':
            generateExcel($datos, $nombreArchivo, $tipo);
            break;
            
        case 'csv':
            generateCSV($datos, $nombreArchivo, $tipo);
            break;
            
        case 'json':
            generateJSON($datos, $nombreArchivo, $tipo);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode() ?: 400
    ];
    
    error_log("Error en exportación: " . $e->getMessage());
    echo json_encode($response);
    
} catch (Error $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    
    $response = [
        'success' => false,
        'error' => 'Error interno del servidor',
        'code' => 500
    ];
    
    error_log("Error fatal en exportación: " . $e->getMessage());
    echo json_encode($response);
}

/**
 * Generar archivo Excel
 */
function generateExcel($datos, $nombreArchivo, $tipo) {
    // Configurar headers para descarga
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Si está disponible PhpSpreadsheet, usarlo
    if (class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        generateExcelWithPhpSpreadsheet($datos, $tipo);
    } else {
        // Fallback a CSV con headers de Excel
        generateCSVAsExcel($datos, $tipo);
    }
}

/**
 * Generar Excel con PhpSpreadsheet
 */
function generateExcelWithPhpSpreadsheet($datos, $tipo) {
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Configurar título
    $titulo = ucfirst($tipo) . ' - Sequoia Speed';
    $sheet->setTitle($titulo);
    
    // Agregar encabezados
    if (!empty($datos)) {
        $headers = array_keys($datos[0]);
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', ucfirst(str_replace('_', ' ', $header)));
            $col++;
        }
        
        // Agregar datos
        $row = 2;
        foreach ($datos as $fila) {
            $col = 'A';
            foreach ($fila as $valor) {
                $sheet->setCellValue($col . $row, $valor);
                $col++;
            }
            $row++;
        }
        
        // Aplicar estilo a los encabezados
        $lastCol = chr(ord('A') + count($headers) - 1);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
        
        // Auto-ajustar columnas
        for ($col = 'A'; $col <= $lastCol; $col++) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
    
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('php://output');
}

/**
 * Generar CSV como fallback para Excel
 */
function generateCSVAsExcel($datos, $tipo) {
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 en Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($datos)) {
        // Encabezados
        $headers = array_keys($datos[0]);
        $headersFormateados = array_map(function($header) {
            return ucfirst(str_replace('_', ' ', $header));
        }, $headers);
        fputcsv($output, $headersFormateados, ';');
        
        // Datos
        foreach ($datos as $fila) {
            fputcsv($output, $fila, ';');
        }
    }
    
    fclose($output);
}

/**
 * Generar archivo CSV
 */
function generateCSV($datos, $nombreArchivo, $tipo) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($datos)) {
        // Encabezados
        fputcsv($output, array_keys($datos[0]));
        
        // Datos
        foreach ($datos as $fila) {
            fputcsv($output, $fila);
        }
    }
    
    fclose($output);
}

/**
 * Generar archivo JSON
 */
function generateJSON($datos, $nombreArchivo, $tipo) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '.json"');
    
    $response = [
        'tipo' => $tipo,
        'fecha_exportacion' => date('Y-m-d H:i:s'),
        'total_registros' => count($datos),
        'datos' => $datos
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
