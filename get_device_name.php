<?php
session_start();

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar que sea una petición AJAX
if (!isset($_GET['serial'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Serial requerido']);
    exit;
}

$serial = trim($_GET['serial']);

if (empty($serial)) {
    echo json_encode(['success' => false, 'error' => 'Serial vacío']);
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
    
    // Buscar dispositivo por número de serie
    $sql = "SELECT nombre FROM aparatos_biometricos WHERE serial = :serial LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':serial', $serial, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'device_name' => $result['nombre']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Dispositivo no encontrado en la base de datos'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en la base de datos: ' . $e->getMessage()
    ]);
}
?>
