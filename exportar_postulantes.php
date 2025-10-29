<?php
/**
 * Archivo para exportar postulantes en diferentes formatos
 * Sistema QUIRA - Versión Web
 */

// Iniciar output buffering para evitar problemas con headers
ob_start();

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

require_once 'config.php';
requireLogin();

// Cargar PhpSpreadsheet si está disponible
$phpspreadsheet_available = false;
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
    $phpspreadsheet_available = true;
}

// Los usuarios con rol USUARIO no pueden exportar
if ($_SESSION['rol'] === 'USUARIO') {
    ob_clean();
    header('Location: dashboard.php');
    exit;
}

// Verificar que se haya solicitado una exportación
if (!isset($_GET['exportar']) || $_GET['exportar'] !== '1') {
    ob_clean();
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
    $where_conditions[] = "p.fecha_registro::date >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "p.fecha_registro::date <= ?";
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

// Definir funciones de exportación ANTES de usarlas
function exportarCSV($postulantes, $timestamp) {
    // Limpiar cualquier output previo
    ob_clean();
    
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
    global $phpspreadsheet_available;
    
    // Limpiar cualquier output previo
    ob_clean();
    
    $filename = "postulantes_export_{$timestamp}.xlsx";
    
    if ($phpspreadsheet_available) {
        // Usar PhpSpreadsheet para generar archivo Excel real
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Postulantes');
            
            // Encabezados
            $headers = [
                'A' => 'ID', 'B' => 'Nombre', 'C' => 'Apellido', 'D' => 'Cédula', 
                'E' => 'Fecha Nacimiento', 'F' => 'Edad', 'G' => 'Sexo', 'H' => 'Teléfono',
                'I' => 'Unidad', 'J' => 'Dedo Registrado', 'K' => 'Aparato', 'L' => 'Registrado Por',
                'M' => 'Capturador', 'N' => 'Fecha Registro', 'O' => 'Observaciones'
            ];
            
            $col = 1;
            foreach ($headers as $header) {
                $sheet->setCellValueByColumnAndRow($col, 1, $header);
                $col++;
            }
            
            // Estilo para encabezados
            $headerRange = 'A1:O1';
            $sheet->getStyle($headerRange)->getFont()->setBold(true);
            $sheet->getStyle($headerRange)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF4472C4');
            $sheet->getStyle($headerRange)->getFont()->getColor()->setARGB('FFFFFFFF');
            
            // Datos
            $row = 2;
            foreach ($postulantes as $postulante) {
                $col = 1;
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
                    $sheet->setCellValueByColumnAndRow($col, $row, $dato);
                    $col++;
                }
                $row++;
            }
            
            // Autoajustar columnas
            foreach (range('A', 'O') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            
            // Generar archivo
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (Exception $e) {
            // Si falla PhpSpreadsheet, usar método de respaldo
            error_log('Error con PhpSpreadsheet: ' . $e->getMessage());
            exportarExcelFallback($postulantes, $timestamp, $fecha_actual);
        }
    } else {
        // Método de respaldo si PhpSpreadsheet no está disponible
        exportarExcelFallback($postulantes, $timestamp, $fecha_actual);
    }
}

function exportarExcelFallback($postulantes, $timestamp, $fecha_actual) {
    // Limpiar cualquier output previo
    ob_clean();
    
    $filename = "postulantes_export_{$timestamp}.xls";
    
    // Método de respaldo usando HTML
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo "\xEF\xBB\xBF";
    echo '<table border="1">';
    
    // Encabezados
    echo '<tr>';
    $headers = [
        'ID', 'Nombre', 'Apellido', 'Cédula', 'Fecha Nacimiento', 'Edad', 'Sexo', 
        'Teléfono', 'Unidad', 'Dedo Registrado', 'Aparato', 'Registrado Por', 
        'Capturador', 'Fecha Registro', 'Observaciones'
    ];
    foreach ($headers as $header) {
        echo '<td><b>' . $header . '</b></td>';
    }
    echo '</tr>';
    
    // Datos
    foreach ($postulantes as $postulante) {
        echo '<tr>';
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
            echo '<td>' . $dato . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit;
}

function exportarPDF($postulantes, $timestamp, $fecha_actual) {
    // Limpiar cualquier output previo
    ob_clean();
    
    $filename = "postulantes_export_{$timestamp}.pdf";
    
    // Crear PDF usando HTML que se puede imprimir como PDF
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Crear BOM para UTF-8
    echo "\xEF\xBB\xBF";
    
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Exportación de Postulantes - Sistema QUIRA</title>
        <style>
            @page { 
                margin: 1cm; 
                size: A4 landscape;
            }
            body { 
                font-family: Arial, sans-serif; 
                font-size: 8px; 
                margin: 0;
                padding: 0;
            }
            .header { 
                text-align: center; 
                margin-bottom: 15px; 
                border-bottom: 2px solid #2E5090;
                padding-bottom: 10px;
            }
            .header h1 { 
                color: #2E5090; 
                margin: 0; 
                font-size: 16px;
            }
            .header p { 
                color: #666; 
                margin: 3px 0; 
                font-size: 10px;
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-top: 10px;
                font-size: 7px;
            }
            th, td { 
                border: 1px solid #333; 
                padding: 2px; 
                text-align: left; 
                vertical-align: top;
            }
            th { 
                background-color: #2E5090; 
                color: white; 
                font-weight: bold;
                font-size: 7px;
            }
            tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .footer { 
                margin-top: 15px; 
                text-align: center; 
                font-size: 8px; 
                color: #666;
                border-top: 1px solid #ccc;
                padding-top: 5px;
            }
            .page-break {
                page-break-before: always;
            }
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
                    <th style="width: 3%;">ID</th>
                    <th style="width: 8%;">Nombre</th>
                    <th style="width: 8%;">Apellido</th>
                    <th style="width: 6%;">Cédula</th>
                    <th style="width: 6%;">Fecha Nac.</th>
                    <th style="width: 3%;">Edad</th>
                    <th style="width: 3%;">Sexo</th>
                    <th style="width: 6%;">Teléfono</th>
                    <th style="width: 12%;">Unidad</th>
                    <th style="width: 4%;">Dedo</th>
                    <th style="width: 8%;">Aparato</th>
                    <th style="width: 8%;">Registrado Por</th>
                    <th style="width: 6%;">Fecha Reg.</th>
                    <th style="width: 8%;">Observaciones</th>
                </tr>
            </thead>
            <tbody>';
    
    $contador = 0;
    foreach ($postulantes as $postulante) {
        // Agregar salto de página cada 30 registros
        if ($contador > 0 && $contador % 30 == 0) {
            $html .= '</tbody></table><div class="page-break"></div><table><thead><tr>
                <th style="width: 3%;">ID</th>
                <th style="width: 8%;">Nombre</th>
                <th style="width: 8%;">Apellido</th>
                <th style="width: 6%;">Cédula</th>
                <th style="width: 6%;">Fecha Nac.</th>
                <th style="width: 3%;">Edad</th>
                <th style="width: 3%;">Sexo</th>
                <th style="width: 6%;">Teléfono</th>
                <th style="width: 12%;">Unidad</th>
                <th style="width: 4%;">Dedo</th>
                <th style="width: 8%;">Aparato</th>
                <th style="width: 8%;">Registrado Por</th>
                <th style="width: 6%;">Fecha Reg.</th>
                <th style="width: 8%;">Observaciones</th>
            </tr></thead><tbody>';
        }
        
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
            <td>' . htmlspecialchars(limpiarTexto($postulante['observaciones'] ?: '')) . '</td>
        </tr>';
        $contador++;
    }
    
    $html .= '</tbody>
        </table>
        
        <div class="footer">
            <p>Generado por Sistema QUIRA - ' . $fecha_actual . '</p>
            <p>Para imprimir como PDF: Ctrl+P → Destino: Guardar como PDF</p>
        </div>
    </body>
    </html>';
    
    echo $html;
    exit;
}

// Ahora ejecutar la exportación
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
?>
