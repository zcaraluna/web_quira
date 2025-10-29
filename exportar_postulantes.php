<?php
/**
 * Archivo para exportar postulantes en diferentes formatos
 * Sistema QUIRA - Versión Web
 */

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

session_start();
require_once 'config.php';
requireLogin();

// Los usuarios con rol USUARIO no pueden exportar
if ($_SESSION['rol'] === 'USUARIO') {
    header('Location: dashboard.php');
    exit;
}

// Verificar que se haya solicitado una exportación
if (!isset($_GET['exportar']) || $_GET['exportar'] !== '1') {
    header('Location: dashboard.php');
    exit;
}

// Obtener parámetros de filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$filtro_unidad = isset($_GET['unidad']) ? $_GET['unidad'] : '';
$filtro_aparato = isset($_GET['aparato']) ? $_GET['aparato'] : '';
$filtro_dedo = isset($_GET['dedo']) ? $_GET['dedo'] : '';
$formato = isset($_GET['formato']) ? $_GET['formato'] : 'excel';

// Conectar a la base de datos
$pdo = getDBConnection();

// Construir la consulta con los mismos filtros que el dashboard
$where_conditions = [];
$params = [];

// Búsqueda con normalización de acentos
if (!empty($search)) {
    $search_param = "%$search%";
    $where_conditions[] = "(p.nombre ILIKE ? OR p.apellido ILIKE ? OR p.cedula ILIKE ?)";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filtro por fecha
if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(p.fecha_registro) >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(p.fecha_registro) <= ?";
    $params[] = $filtro_fecha_hasta;
}

// Filtro por unidad
if (!empty($filtro_unidad)) {
    $where_conditions[] = "p.unidad = ?";
    $params[] = $filtro_unidad;
}

// Filtro por aparato
if (!empty($filtro_aparato)) {
    if (is_numeric($filtro_aparato)) {
        $where_conditions[] = "p.aparato_id = ?";
        $params[] = $filtro_aparato;
    } else {
        // Es un aparato eliminado
        $aparato_nombre = base64_decode(str_replace('eliminado_', '', $filtro_aparato));
        $where_conditions[] = "p.aparato_nombre = ?";
        $params[] = $aparato_nombre;
    }
}

// Filtro por dedo
if (!empty($filtro_dedo)) {
    $where_conditions[] = "p.dedo_registrado = ?";
    $params[] = $filtro_dedo;
}

// Construir WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta principal (sin paginación para exportar todos)
$postulantes_sql = "
    SELECT 
        p.id, p.nombre, p.apellido, p.cedula, p.fecha_nacimiento, p.telefono,
        p.fecha_registro, p.observaciones, p.edad, p.unidad, p.dedo_registrado,
        p.registrado_por, p.aparato_id, p.usuario_ultima_edicion, p.fecha_ultima_edicion,
        p.sexo, p.aparato_nombre,
        a.nombre as aparato_nombre_actual,
        u.usuario as usuario_registrador_nombre,
        CONCAT(COALESCE(c.grado, ''), ' ', COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido, '')) as capturador_nombre
    FROM postulantes p
    LEFT JOIN aparatos_biometricos a ON p.aparato_id = a.id
    LEFT JOIN usuarios u ON p.usuario_registrador = u.id
    LEFT JOIN usuarios c ON p.capturador_id = c.id
    $where_clause
    ORDER BY p.fecha_registro DESC
";

$stmt = $pdo->prepare($postulantes_sql);
$stmt->execute($params);
$postulantes = $stmt->fetchAll();

// Generar nombre de archivo con timestamp
$timestamp = date('Y-m-d_H-i-s');
$fecha_actual = date('d/m/Y H:i');

// Función para limpiar texto para exportación
function limpiarTexto($texto) {
    return str_replace(["\r\n", "\r", "\n"], " ", $texto);
}

// Función para formatear fecha
function formatearFecha($fecha) {
    if (!$fecha) return '';
    return date('d/m/Y H:i', strtotime($fecha));
}

// Función para formatear fecha de nacimiento
function formatearFechaNacimiento($fecha) {
    if (!$fecha) return '';
    return date('d/m/Y', strtotime($fecha));
}

// Función para obtener nombre del aparato
function obtenerNombreAparato($postulante) {
    return $postulante['aparato_nombre_actual'] ?: $postulante['aparato_nombre'] ?: 'Sin aparato';
}

switch ($formato) {
    case 'csv':
        exportarCSV($postulantes, $timestamp);
        break;
    case 'pdf':
        exportarPDF($postulantes, $timestamp, $fecha_actual);
        break;
    case 'excel':
    default:
        exportarExcel($postulantes, $timestamp, $fecha_actual);
        break;
}

function exportarCSV($postulantes, $timestamp) {
    $filename = "postulantes_export_{$timestamp}.csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Crear BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, [
        'ID',
        'Nombre',
        'Apellido', 
        'Cédula',
        'Fecha Nacimiento',
        'Edad',
        'Sexo',
        'Teléfono',
        'Unidad',
        'Dedo Registrado',
        'Aparato',
        'Registrado Por',
        'Capturador',
        'Fecha Registro',
        'Observaciones'
    ], ';');
    
    // Datos
    foreach ($postulantes as $postulante) {
        fputcsv($output, [
            $postulante['id'],
            limpiarTexto($postulante['nombre']),
            limpiarTexto($postulante['apellido']),
            $postulante['cedula'],
            formatearFechaNacimiento($postulante['fecha_nacimiento']),
            $postulante['edad'] ?: '',
            $postulante['sexo'] ?: '',
            $postulante['telefono'] ?: '',
            limpiarTexto($postulante['unidad'] ?: ''),
            $postulante['dedo_registrado'] ?: '',
            limpiarTexto(obtenerNombreAparato($postulante)),
            limpiarTexto($postulante['registrado_por'] ?: ''),
            limpiarTexto($postulante['capturador_nombre'] ?: ''),
            formatearFecha($postulante['fecha_registro']),
            limpiarTexto($postulante['observaciones'] ?: '')
        ], ';');
    }
    
    fclose($output);
    exit;
}

function exportarExcel($postulantes, $timestamp, $fecha_actual) {
    $filename = "postulantes_export_{$timestamp}.xlsx";
    
    // Crear un archivo Excel simple usando HTML
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '<?xml version="1.0" encoding="UTF-8"?>';
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">';
    echo '<Worksheet ss:Name="Postulantes">';
    echo '<Table>';
    
    // Encabezados
    echo '<Row>';
    $headers = [
        'ID', 'Nombre', 'Apellido', 'Cédula', 'Fecha Nacimiento', 'Edad', 'Sexo', 
        'Teléfono', 'Unidad', 'Dedo Registrado', 'Aparato', 'Registrado Por', 
        'Capturador', 'Fecha Registro', 'Observaciones'
    ];
    foreach ($headers as $header) {
        echo '<Cell><Data ss:Type="String">' . htmlspecialchars($header) . '</Data></Cell>';
    }
    echo '</Row>';
    
    // Datos
    foreach ($postulantes as $postulante) {
        echo '<Row>';
        $datos = [
            $postulante['id'],
            limpiarTexto($postulante['nombre']),
            limpiarTexto($postulante['apellido']),
            $postulante['cedula'],
            formatearFechaNacimiento($postulante['fecha_nacimiento']),
            $postulante['edad'] ?: '',
            $postulante['sexo'] ?: '',
            $postulante['telefono'] ?: '',
            limpiarTexto($postulante['unidad'] ?: ''),
            $postulante['dedo_registrado'] ?: '',
            limpiarTexto(obtenerNombreAparato($postulante)),
            limpiarTexto($postulante['registrado_por'] ?: ''),
            limpiarTexto($postulante['capturador_nombre'] ?: ''),
            formatearFecha($postulante['fecha_registro']),
            limpiarTexto($postulante['observaciones'] ?: '')
        ];
        
        foreach ($datos as $dato) {
            $tipo = is_numeric($dato) ? 'Number' : 'String';
            echo '<Cell><Data ss:Type="' . $tipo . '">' . htmlspecialchars($dato) . '</Data></Cell>';
        }
        echo '</Row>';
    }
    
    echo '</Table>';
    echo '</Worksheet>';
    echo '</Workbook>';
    exit;
}

function exportarPDF($postulantes, $timestamp, $fecha_actual) {
    $filename = "postulantes_export_{$timestamp}.pdf";
    
    // Crear PDF simple usando HTML
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Exportación de Postulantes</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 10px; }
            .header { text-align: center; margin-bottom: 20px; }
            .header h1 { color: #333; margin: 0; }
            .header p { color: #666; margin: 5px 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            th, td { border: 1px solid #ddd; padding: 4px; text-align: left; }
            th { background-color: #f2f2f2; font-weight: bold; }
            .footer { margin-top: 20px; text-align: center; font-size: 8px; color: #666; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>Sistema QUIRA - Exportación de Postulantes</h1>
            <p>Fecha de exportación: ' . $fecha_actual . '</p>
            <p>Total de registros: ' . count($postulantes) . '</p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Apellido</th>
                    <th>Cédula</th>
                    <th>Fecha Nac.</th>
                    <th>Edad</th>
                    <th>Sexo</th>
                    <th>Teléfono</th>
                    <th>Unidad</th>
                    <th>Dedo</th>
                    <th>Aparato</th>
                    <th>Registrado Por</th>
                    <th>Fecha Reg.</th>
                </tr>
            </thead>
            <tbody>';
    
    foreach ($postulantes as $postulante) {
        $html .= '<tr>
            <td>' . $postulante['id'] . '</td>
            <td>' . htmlspecialchars(limpiarTexto($postulante['nombre'])) . '</td>
            <td>' . htmlspecialchars(limpiarTexto($postulante['apellido'])) . '</td>
            <td>' . htmlspecialchars($postulante['cedula']) . '</td>
            <td>' . formatearFechaNacimiento($postulante['fecha_nacimiento']) . '</td>
            <td>' . ($postulante['edad'] ?: '') . '</td>
            <td>' . htmlspecialchars($postulante['sexo'] ?: '') . '</td>
            <td>' . htmlspecialchars($postulante['telefono'] ?: '') . '</td>
            <td>' . htmlspecialchars(limpiarTexto($postulante['unidad'] ?: '')) . '</td>
            <td>' . htmlspecialchars($postulante['dedo_registrado'] ?: '') . '</td>
            <td>' . htmlspecialchars(limpiarTexto(obtenerNombreAparato($postulante))) . '</td>
            <td>' . htmlspecialchars(limpiarTexto($postulante['registrado_por'] ?: '')) . '</td>
            <td>' . formatearFecha($postulante['fecha_registro']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <p>Generado por Sistema QUIRA - ' . $fecha_actual . '</p>
        </div>
    </body>
    </html>';
    
    echo $html;
    exit;
}
?>
