<?php
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['usuario'])) {
    die('Acceso denegado');
}

// Incluir FPDF si está disponible, sino usar implementación básica
if (file_exists('fpdf/fpdf.php')) {
    require_once('fpdf/fpdf.php');
} else {
    // Implementación básica de FPDF
    class FPDF {
        protected $pageWidth;
        protected $pageHeight;
        protected $currentX;
        protected $currentY;
        protected $fontSize;
        protected $fontFamily;
        protected $pageNumber;
        protected $totalPages;
        protected $orientation;
        protected $unit;
        protected $size;
        
        public function __construct($orientation='P', $unit='mm', $size='A4') {
            $this->orientation = $orientation;
            $this->unit = $unit;
            $this->size = $size;
            
            if ($size === 'A4') {
                $this->pageWidth = $orientation === 'L' ? 297 : 210;
                $this->pageHeight = $orientation === 'L' ? 210 : 297;
            } else {
                // Formato Legal horizontal
                $this->pageWidth = 355.6; // 14 pulgadas
                $this->pageHeight = 215.9; // 8.5 pulgadas
            }
            
            $this->currentX = 10;
            $this->currentY = 10;
            $this->fontSize = 12;
            $this->fontFamily = 'Arial';
            $this->pageNumber = 0;
        }
        
        public function AddPage() {
            $this->pageNumber++;
            $this->currentY = 10;
            $this->currentX = 10;
        }
        
        public function SetFont($family, $style='', $size=0) {
            $this->fontFamily = $family;
            $this->fontSize = $size > 0 ? $size : $this->fontSize;
        }
        
        public function Cell($w, $h=0, $txt='', $border=0, $ln=0, $align='', $fill=false) {
            // Implementación básica - solo para compatibilidad
            $this->currentX += $w;
            if ($ln == 1) {
                $this->currentY += $h;
                $this->currentX = 10;
            }
        }
        
        public function Ln($h=null) {
            $this->currentY += $h ?: $this->fontSize;
            $this->currentX = 10;
        }
        
        public function Header() {
            // Implementación básica
        }
        
        public function Footer() {
            // Implementación básica
        }
        
        public function AliasNbPages($alias='{nb}') {
            $this->totalPages = $this->pageNumber;
        }
        
        public function Output($name='', $dest='') {
            // Generar PDF usando TCPDF o implementación nativa
            $this->generatePDF($name);
        }
        
        private function generatePDF($name) {
            // Generar PDF básico usando HTML/CSS
            $html = $this->generateHTML();
            
            // Usar wkhtmltopdf si está disponible
            $wkhtmltopdf = shell_exec('which wkhtmltopdf');
            if ($wkhtmltopdf && function_exists('shell_exec')) {
                $tempFile = tempnam(sys_get_temp_dir(), 'quira_export');
                file_put_contents($tempFile . '.html', $html);
                
                $cmd = "wkhtmltopdf --page-size Legal --orientation Landscape --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm --disable-smart-shrinking " . 
                       escapeshellarg($tempFile . '.html') . " " . escapeshellarg($tempFile . '.pdf') . " 2>&1";
                
                $output = shell_exec($cmd);
                
                if (file_exists($tempFile . '.pdf')) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $name . '"');
                    header('Content-Length: ' . filesize($tempFile . '.pdf'));
                    readfile($tempFile . '.pdf');
                    unlink($tempFile . '.pdf');
                    unlink($tempFile . '.html');
                    return;
                }
            }
            
            // Fallback: generar HTML con estilo para imprimir como PDF
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . str_replace('.pdf', '.html', $name) . '"');
            
            // Agregar script para auto-imprimir
            $html = str_replace('</body>', '
    <script>
        window.onload = function() {
            window.print();
        };
    </script>
</body>', $html);
            
            echo $html;
        }
        
        private function generateHTML() {
            global $postulantes, $campos;
            
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Lista de Postulantes - QUIRA</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; }
        .header p { margin: 5px 0; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 4px; text-align: left; font-size: 8px; }
        th { background-color: #f0f0f0; font-weight: bold; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; color: #666; }
        @media print { body { margin: 0; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>LISTA DE POSTULANTES</h1>
        <p>Sistema QUIRA - ' . date('d/m/Y H:i:s') . '</p>
    </div>
    
    <table>
        <thead>
            <tr>';
            
            foreach ($campos as $campo) {
                $titulo = ucfirst(str_replace('_', ' ', $campo));
                $html .= '<th>' . $titulo . '</th>';
            }
            
            $html .= '</tr>
        </thead>
        <tbody>';
            
            foreach ($postulantes as $postulante) {
                $html .= '<tr>';
                foreach ($campos as $campo) {
                    $valor = $this->getFieldValue($postulante, $campo);
                    $html .= '<td>' . htmlspecialchars($valor) . '</td>';
                }
                $html .= '</tr>';
            }
            
            $html .= '</tbody>
    </table>
    
    <div class="footer">
        <p>Total de registros exportados: ' . count($postulantes) . '</p>
    </div>
</body>
</html>';
            
            return $html;
        }
        
        private function getFieldValue($postulante, $campo) {
            switch($campo) {
                case 'nombre': return $postulante['nombre'] ?? '';
                case 'apellido': return $postulante['apellido'] ?? '';
                case 'cedula': return $postulante['cedula'] ?? '';
                case 'fecha_nacimiento': return $postulante['fecha_nacimiento'] ? date('d/m/Y', strtotime($postulante['fecha_nacimiento'])) : '';
                case 'edad': return $postulante['edad'] ?? '';
                case 'sexo': return $postulante['sexo'] ?? '';
                case 'telefono': return $postulante['telefono'] ?? '';
                case 'unidad': return $postulante['unidad'] ?? '';
                case 'dedo_registrado': return $postulante['dedo_registrado'] ?? '';
                case 'aparato': return $postulante['aparato_nombre_actual'] ?? $postulante['aparato_nombre'] ?? 'Sin dispositivo';
                case 'registrado_por': return $postulante['usuario_registrador_nombre'] ?? $postulante['registrado_por'] ?? '';
                case 'capturador': return $postulante['capturador_nombre'] ?? '';
                case 'fecha_registro': return $postulante['fecha_registro'] ? date('d/m/Y H:i', strtotime($postulante['fecha_registro'])) : '';
                case 'observaciones': return $postulante['observaciones'] ?? '';
                default: return '';
            }
        }
    }
}

// Obtener los campos seleccionados
$campos = json_decode($_POST['campos'] ?? '[]', true);

if (empty($campos)) {
    die('No se seleccionaron campos para exportar.');
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

// Crear PDF en formato Oficio horizontal
$pdf = new FPDF('L', 'in', array(11, 8.5));
$pdf->AliasNbPages();
$pdf->AddPage();

// Generar PDF usando el método generatePDF
$pdf->generatePDF('Lista_Postulantes_' . date('Y-m-d_H-i-s') . '.pdf');
?>
