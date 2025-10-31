<?php
/**
 * Endpoint AJAX para buscar preinscriptos por CI
 * Sistema QUIRA
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

session_start();
require_once 'config.php';
requireLogin();

header('Content-Type: application/json');

try {
    // Verificar método de petición
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }
    
    // Obtener CI del POST
    $ci = trim($_POST['ci'] ?? '');
    
    if (empty($ci)) {
        throw new Exception('CI no proporcionado');
    }
    
    // Validar que la tabla existe
    $pdo = getDBConnection();
    
    $check_table = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'preinscriptos'
        )
    ")->fetchColumn();
    
    if (!$check_table) {
        throw new Exception('Tabla preinscriptos no existe. Ejecute crear_tabla_preinscriptos.php primero.');
    }
    
    // Buscar preinscripto por CI
    $stmt = $pdo->prepare("
        SELECT 
            ci,
            nombre_completo,
            fecha_nacimiento,
            sexo,
            unidad
        FROM preinscriptos
        WHERE ci = ?
        LIMIT 1
    ");
    
    $stmt->execute([$ci]);
    $preinscripto = $stmt->fetch();
    
    if (!$preinscripto) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontró ningún preinscripto con esa CI',
            'data' => null
        ]);
        exit;
    }
    
    // Convertir sexo de H/M a Hombre/Mujer
    $sexo_completo = $preinscripto['sexo'] === 'H' ? 'Hombre' : 'Mujer';
    
    // Formatear fecha de nacimiento para el formulario (YYYY-MM-DD)
    $fecha_nacimiento_formatted = $preinscripto['fecha_nacimiento'];
    
    // Calcular edad
    $fecha_nac = new DateTime($preinscripto['fecha_nacimiento']);
    $hoy = new DateTime();
    $edad = $hoy->diff($fecha_nac)->y;
    
    // Retornar datos en formato JSON
    echo json_encode([
        'success' => true,
        'message' => 'Preinscripto encontrado',
        'data' => [
            'ci' => $preinscripto['ci'],
            'nombre_completo' => $preinscripto['nombre_completo'],
            'fecha_nacimiento' => $fecha_nacimiento_formatted,
            'sexo' => $sexo_completo,
            'unidad' => $preinscripto['unidad'],
            'edad' => $edad
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => null
    ]);
}

