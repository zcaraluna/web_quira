<?php
session_start();
require_once 'config.php';

// Debug temporal
$debug_info = [
    'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'] ?? 'NO DEFINIDO',
    'POST_data' => $_POST,
    'GET_data' => $_GET,
    'INPUT_stream' => file_get_contents('php://input'),
    'postulante_id_post_exists' => isset($_POST['postulante_id']),
    'postulante_id_get_exists' => isset($_GET['postulante_id']),
    'postulante_id_post_value' => $_POST['postulante_id'] ?? 'NO DEFINIDO',
    'postulante_id_get_value' => $_GET['postulante_id'] ?? 'NO DEFINIDO',
    'postulante_id_post_empty' => empty($_POST['postulante_id'] ?? ''),
    'postulante_id_get_empty' => empty($_GET['postulante_id'] ?? ''),
    'session_user_id' => $_SESSION['user_id'] ?? 'NO DEFINIDO'
];

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false, 
        'error' => 'No autorizado',
        'debug' => $debug_info
    ]);
    exit;
}

// Verificar que se recibió el ID del postulante (POST o GET)
$postulante_id = null;
if (isset($_POST['postulante_id']) && !empty($_POST['postulante_id'])) {
    $postulante_id = intval($_POST['postulante_id']);
} elseif (isset($_GET['postulante_id']) && !empty($_GET['postulante_id'])) {
    $postulante_id = intval($_GET['postulante_id']);
}

if (!$postulante_id) {
    http_response_code(400);
    echo json_encode([
        'success' => false, 
        'error' => 'ID de postulante requerido',
        'debug' => $debug_info
    ]);
    exit;
}

try {
    // Obtener conexión a la base de datos
    $pdo = getDBConnection();
    
    // Verificar si existe la tabla de historial
    $stmt_check = $pdo->prepare("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'historial_ediciones_postulantes'
        )
    ");
    $stmt_check->execute();
    $tabla_existe = $stmt_check->fetchColumn();
    
    $historial = [];
    
    if ($tabla_existe) {
        // Obtener historial completo de ediciones
        $stmt_historial = $pdo->prepare("
            SELECT usuario_editor, fecha_edicion, cambios
            FROM historial_ediciones_postulantes 
            WHERE postulante_id = ?
            ORDER BY fecha_edicion DESC
        ");
        $stmt_historial->execute([$postulante_id]);
        $historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'historial' => $historial
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener historial: ' . $e->getMessage()
    ]);
}
?>
