<?php
session_start();

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar que sea una petición AJAX
if (!isset($_GET['cedula'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'CI requerida']);
    exit;
}

$cedula = trim($_GET['cedula']);

if (empty($cedula)) {
    echo json_encode(['success' => false, 'error' => 'CI vacía']);
    exit;
}

// Configuración de la base de datos (igual que dashboard.php)
$host = 'localhost';
$dbname = 'sistema_postulantes';
$username = 'postgres';
$password = 'Postgres2025!';

try {
    $conn = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Debug: Verificar conexión
    $debug_info = [
        'cedula_buscada' => $cedula,
        'cedula_length' => strlen($cedula),
        'cedula_trimmed' => trim($cedula),
        'host' => $host,
        'dbname' => $dbname
    ];
    
    // Primero, verificar si existe alguna CI similar (para debug)
    $debug_sql = "SELECT cedula, nombre, apellido FROM postulantes WHERE cedula ILIKE :cedula_pattern LIMIT 5";
    $debug_stmt = $conn->prepare($debug_sql);
    $cedula_pattern = '%' . $cedula . '%';
    $debug_stmt->bindParam(':cedula_pattern', $cedula_pattern, PDO::PARAM_STR);
    $debug_stmt->execute();
    $debug_results = $debug_stmt->fetchAll();
    
    // También buscar CIs exactas (sin ILIKE)
    $exact_sql = "SELECT cedula, nombre, apellido FROM postulantes WHERE cedula = :cedula LIMIT 5";
    $exact_stmt = $conn->prepare($exact_sql);
    $exact_stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
    $exact_stmt->execute();
    $exact_results = $exact_stmt->fetchAll();
    
    $debug_info['cedulas_similares'] = $debug_results;
    $debug_info['cedulas_exactas'] = $exact_results;
    
    // Buscar postulante por CI exacta
    $sql = "SELECT 
                p.id,
                p.nombre,
                p.apellido,
                p.cedula,
                p.sexo,
                p.fecha_nacimiento,
                p.telefono,
                p.dedo_registrado,
                p.unidad,
                p.observaciones,
                p.fecha_registro,
                p.fecha_ultima_edicion,
                p.registrado_por,
                p.usuario_ultima_edicion,
                p.aparato_nombre,
                u.nombre as usuario_registrador_nombre
            FROM postulantes p
            LEFT JOIN usuarios u ON p.usuario_registrador = u.id
            WHERE p.cedula = :cedula
            ORDER BY p.fecha_registro DESC
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':cedula', $cedula, PDO::PARAM_STR);
    $stmt->execute();
    
    $postulante = $stmt->fetch();
    
    if ($postulante) {
        
        // Calcular edad si hay fecha de nacimiento
        if ($postulante['fecha_nacimiento']) {
            $fecha_nac = new DateTime($postulante['fecha_nacimiento']);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;
            $postulante['edad'] = $edad;
        }
        
        echo json_encode([
            'success' => true,
            'postulante' => $postulante
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Postulante no encontrado',
            'debug' => $debug_info
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>
