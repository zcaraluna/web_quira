<?php
/**
 * Exportar postulantes a documento Word
 * Sistema QUIRA
 */

session_start();
require_once 'config.php';
requireLogin();

// Verificar que el usuario sea SUPERADMIN
if ($_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    die('Acceso denegado. Solo los SUPERADMIN pueden exportar postulantes.');
}

// Verificar que se haya enviado la unidad
if (!isset($_GET['unidad']) || empty($_GET['unidad'])) {
    http_response_code(400);
    die('Error: Debe seleccionar una unidad.');
}

$unidad_seleccionada = trim($_GET['unidad']);

// Verificar si PhpWord está disponible
if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
    // Intentar cargar desde vendor si existe
    $vendor_autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($vendor_autoload)) {
        require_once $vendor_autoload;
    }
    
    // Verificar nuevamente después de intentar cargar
    if (!class_exists('PhpOffice\PhpWord\PhpWord')) {
        http_response_code(500);
        die('Error: La biblioteca PhpWord no está instalada.<br><br>Por favor ejecute el siguiente comando en la terminal desde la carpeta del proyecto:<br><code>composer require phpoffice/phpword</code>');
    }
}

try {
    $pdo = getDBConnection();
    
    // Obtener nombre de la unidad para el título
    $stmt_unidad = $pdo->prepare("SELECT nombre FROM unidades WHERE nombre = ? LIMIT 1");
    $stmt_unidad->execute([$unidad_seleccionada]);
    $unidad_info = $stmt_unidad->fetch();
    $nombre_unidad = $unidad_info ? $unidad_info['nombre'] : $unidad_seleccionada;
    
    // Consulta para obtener postulantes de la unidad seleccionada
    $stmt = $pdo->prepare("
        SELECT 
            p.cedula,
            COALESCE(p.nombre_completo, p.nombre || ' ' || p.apellido) as nombre_completo,
            COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo') as dispositivo
        FROM postulantes p
        LEFT JOIN aparatos_biometricos ab ON p.aparato_id = ab.id
        WHERE p.unidad = ?
        ORDER BY p.nombre_completo ASC, p.nombre ASC, p.apellido ASC
    ");
    
    $stmt->execute([$unidad_seleccionada]);
    $postulantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($postulantes)) {
        die('No se encontraron postulantes para la unidad seleccionada.');
    }
    
    // Crear nuevo documento Word
    $phpWord = new \PhpOffice\PhpWord\PhpWord();
    
    // Configurar estilos
    $phpWord->setDefaultFontName('Arial');
    $phpWord->setDefaultFontSize(11);
    
    // Estilos personalizados
    $phpWord->addTitleStyle(1, [
        'name' => 'Arial',
        'size' => 16,
        'bold' => true,
        'color' => '000000'
    ], ['spaceAfter' => 240]);
    
    $phpWord->addTitleStyle(2, [
        'name' => 'Arial',
        'size' => 14,
        'bold' => true,
        'color' => '000000'
    ], ['spaceAfter' => 120]);
    
    // Agregar sección
    $section = $phpWord->addSection([
        'marginTop' => 1440,
        'marginBottom' => 1440,
        'marginLeft' => 1440,
        'marginRight' => 1440
    ]);
    
    // Título del documento
    $section->addTitle('Lista de Postulantes', 1);
    $section->addTextBreak(1);
    
    // Información de la unidad
    $section->addText('Unidad: ' . htmlspecialchars($nombre_unidad), [
        'bold' => true,
        'size' => 12
    ]);
    $section->addText('Total de postulantes: ' . count($postulantes), [
        'bold' => true,
        'size' => 12
    ]);
    $section->addTextBreak(2);
    
    // Crear tabla
    $table = $section->addTable([
        'borderSize' => 6,
        'borderColor' => '000000',
        'cellMargin' => 80
    ]);
    
    // Encabezado de la tabla
    $table->addRow(400);
    $cellStyle = [
        'bgColor' => 'D3D3D3',
        'bold' => true,
        'size' => 11
    ];
    
    $table->addCell(2000, $cellStyle)->addText('N°', ['bold' => true, 'size' => 11]);
    $table->addCell(3000, $cellStyle)->addText('C.I.', ['bold' => true, 'size' => 11]);
    $table->addCell(6000, $cellStyle)->addText('Nombre Completo', ['bold' => true, 'size' => 11]);
    $table->addCell(4000, $cellStyle)->addText('Dispositivo Biométrico', ['bold' => true, 'size' => 11]);
    
    // Agregar filas con datos
    $numero = 1;
    foreach ($postulantes as $postulante) {
        $table->addRow();
        $table->addCell(2000)->addText($numero, ['size' => 10]);
        $table->addCell(3000)->addText(htmlspecialchars($postulante['cedula']), ['size' => 10]);
        $table->addCell(6000)->addText(htmlspecialchars($postulante['nombre_completo']), ['size' => 10]);
        $table->addCell(4000)->addText(htmlspecialchars($postulante['dispositivo']), ['size' => 10]);
        $numero++;
    }
    
    // Agregar pie de página
    $section->addTextBreak(2);
    $section->addText('Documento generado el ' . date('d/m/Y H:i:s'), [
        'italic' => true,
        'size' => 9,
        'color' => '666666'
    ]);
    
    // Preparar nombre del archivo
    $nombre_archivo = 'Postulantes_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $nombre_unidad) . '_' . date('Y-m-d') . '.docx';
    $nombre_archivo = mb_convert_encoding($nombre_archivo, 'ISO-8859-1', 'UTF-8');
    
    // Enviar archivo al navegador
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '"');
    header('Cache-Control: max-age=0');
    
    // Guardar en memoria y enviar
    $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $objWriter->save('php://output');
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error al generar el documento: ' . htmlspecialchars($e->getMessage()));
}
?>

