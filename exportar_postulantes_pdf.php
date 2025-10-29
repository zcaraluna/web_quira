<?php
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    die('Acceso denegado');
}

// Verificar que FPDF esté disponible
if (!file_exists('fpdf/fpdf.php')) {
    die('Error: FPDF no está instalado. Ejecute el script instalar_fpdf.sh en el servidor.');
}

// Obtener los campos seleccionados
$campos = json_decode($_POST['campos'] ?? '[]', true);
if (empty($campos)) {
    die('No se seleccionaron campos para exportar');
}

// Obtener filtros
$search = $_POST['search'] ?? '';
$filtro_unidad = $_POST['filtro_unidad'] ?? '';
$filtro_aparato = $_POST['filtro_aparato'] ?? '';
$filtro_dedo = $_POST['filtro_dedo'] ?? '';

// Construir condiciones WHERE (usando la misma lógica del dashboard)
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.nombre ILIKE ? OR p.apellido ILIKE ? OR p.cedula ILIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($filtro_unidad)) {
    $where_conditions[] = "p.unidad = ?";
    $params[] = $filtro_unidad;
}

if (!empty($filtro_aparato)) {
    if (strpos($filtro_aparato, 'eliminado_') === 0) {
        $aparato_nombre_encoded = substr($filtro_aparato, 10);
        $aparato_nombre = base64_decode($aparato_nombre_encoded);
        $where_conditions[] = "p.aparato_nombre = ?";
        $params[] = $aparato_nombre;
    } else {
        $where_conditions[] = "(p.aparato_id = ? OR p.aparato_nombre = (SELECT nombre FROM aparatos_biometricos WHERE id = ?))";
        $params[] = $filtro_aparato;
        $params[] = $filtro_aparato;
    }
}

if (!empty($filtro_dedo)) {
    $where_conditions[] = "p.dedo_registrado = ?";
    $params[] = $filtro_dedo;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Consulta para obtener todos los registros (sin paginación)
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

$postulantes_stmt = $pdo->prepare($postulantes_sql);
$postulantes_stmt->execute($params);
$postulantes = $postulantes_stmt->fetchAll();

// Incluir FPDF
require_once('fpdf/fpdf.php');

// Crear clase personalizada para el PDF
class PDF extends FPDF {
    function Header() {
        // Logo o título
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'LISTA DE POSTULANTES', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, 'Sistema QUIRA - ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Crear PDF en formato Oficio horizontal (11 x 8.5 pulgadas)
$pdf = new PDF('L', 'in', array(11, 8.5));
$pdf->AliasNbPages();
$pdf->AddPage();

// Definir anchos de columna según los campos seleccionados
$anchos = [];
$total_ancho = 0;

foreach ($campos as $campo) {
    switch($campo) {
        case 'nombre':
        case 'apellido':
            $anchos[$campo] = 0.8;
            break;
        case 'cedula':
            $anchos[$campo] = 0.7;
            break;
        case 'fecha_nacimiento':
        case 'fecha_registro':
            $anchos[$campo] = 0.8;
            break;
        case 'edad':
        case 'sexo':
        case 'dedo_registrado':
            $anchos[$campo] = 0.4;
            break;
        case 'telefono':
            $anchos[$campo] = 0.7;
            break;
        case 'unidad':
            $anchos[$campo] = 1.0;
            break;
        case 'aparato':
            $anchos[$campo] = 0.8;
            break;
        case 'registrado_por':
        case 'capturador':
            $anchos[$campo] = 0.8;
            break;
        case 'observaciones':
            $anchos[$campo] = 1.2;
            break;
        default:
            $anchos[$campo] = 0.6;
    }
    $total_ancho += $anchos[$campo];
}

// Ajustar anchos proporcionalmente para que ocupen todo el ancho
$factor_ajuste = 10.5 / $total_ancho; // 10.5 pulgadas de ancho útil
foreach ($anchos as $campo => $ancho) {
    $anchos[$campo] = $ancho * $factor_ajuste;
}

// Encabezados
$pdf->SetFont('Arial', 'B', 8);
foreach ($campos as $campo) {
    $titulo = ucfirst(str_replace('_', ' ', $campo));
    $pdf->Cell($anchos[$campo], 0.3, $titulo, 1, 0, 'C');
}
$pdf->Ln();

// Datos
$pdf->SetFont('Arial', '', 7);
foreach ($postulantes as $postulante) {
    foreach ($campos as $campo) {
        $valor = '';
        
        switch($campo) {
            case 'nombre':
                $valor = $postulante['nombre'] ?? '';
                break;
            case 'apellido':
                $valor = $postulante['apellido'] ?? '';
                break;
            case 'cedula':
                $valor = $postulante['cedula'] ?? '';
                break;
            case 'fecha_nacimiento':
                $valor = $postulante['fecha_nacimiento'] ? date('d/m/Y', strtotime($postulante['fecha_nacimiento'])) : '';
                break;
            case 'edad':
                $valor = $postulante['edad'] ?? '';
                break;
            case 'sexo':
                $valor = $postulante['sexo'] ?? '';
                break;
            case 'telefono':
                $valor = $postulante['telefono'] ?? '';
                break;
            case 'unidad':
                $valor = $postulante['unidad'] ?? '';
                break;
            case 'dedo_registrado':
                $valor = $postulante['dedo_registrado'] ?? '';
                break;
            case 'aparato':
                $valor = $postulante['aparato_nombre_actual'] ?? $postulante['aparato_nombre'] ?? 'Sin dispositivo';
                break;
            case 'registrado_por':
                $valor = $postulante['usuario_registrador_nombre'] ?? $postulante['registrado_por'] ?? '';
                break;
            case 'capturador':
                $valor = $postulante['capturador_nombre'] ?? '';
                break;
            case 'fecha_registro':
                $valor = $postulante['fecha_registro'] ? date('d/m/Y H:i', strtotime($postulante['fecha_registro'])) : '';
                break;
            case 'observaciones':
                $valor = $postulante['observaciones'] ?? '';
                break;
        }
        
        // Truncar texto si es muy largo
        if (strlen($valor) > 30) {
            $valor = substr($valor, 0, 27) . '...';
        }
        
        $pdf->Cell($anchos[$campo], 0.25, $valor, 1, 0, 'L');
    }
    $pdf->Ln();
}

// Información adicional
$pdf->Ln(0.2);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 0.2, 'Total de registros exportados: ' . count($postulantes), 0, 1, 'L');

// Generar PDF
$pdf->Output('Lista_Postulantes_' . date('Y-m-d_H-i-s') . '.pdf', 'D');
?>
