<?php
/**
 * Obtener postulantes de una unidad específica (AJAX)
 * Sistema QUIRA
 */

session_start();
require_once 'config.php';
requireLogin();

// Verificar que el usuario sea SUPERADMIN
if ($_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Verificar que se haya enviado la unidad
if (!isset($_GET['unidad']) || empty($_GET['unidad'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Debe seleccionar una unidad']);
    exit;
}

$unidad_seleccionada = trim($_GET['unidad']);

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
    
    echo json_encode([
        'success' => true,
        'unidad' => $nombre_unidad,
        'total' => count($postulantes),
        'postulantes' => $postulantes
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error al obtener los postulantes: ' . $e->getMessage()
    ]);
}
?>

