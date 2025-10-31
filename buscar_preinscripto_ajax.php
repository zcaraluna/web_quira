<?php
/**
 * Endpoint AJAX para buscar preinscriptos por CI
 * Sistema QUIRA
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

// Iniciar sesión y verificar login
session_start();
require_once 'config.php';

// Verificar que el usuario esté logueado ANTES de verificar método
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Sesión no iniciada. Por favor, inicie sesión.',
        'data' => null
    ]);
    exit;
}

// Verificar método de petición
// Algunos servidores/proxies pueden convertir POST a GET, así que verificamos ambas cosas:
// 1. El método HTTP declarado
// 2. Si hay datos POST (lo cual indica que fue POST originalmente)

$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
// Leer php://input una sola vez (solo se puede leer una vez)
$raw_input = file_get_contents('php://input');
$has_post_data = !empty($_POST) || !empty($raw_input);
// También aceptar GET si viene con el parámetro ci (por si un redirect convierte POST a GET)
$has_get_ci = !empty($_GET['ci']);

// Debug: Log del método recibido
error_log("DEBUG buscar_preinscripto_ajax.php - REQUEST_METHOD: $request_method");
error_log("DEBUG - Has POST data: " . ($has_post_data ? 'YES' : 'NO'));
error_log("DEBUG - Has GET ci: " . ($has_get_ci ? 'YES' : 'NO'));
error_log("DEBUG - POST data: " . print_r($_POST, true));
error_log("DEBUG - GET data: " . print_r($_GET, true));
error_log("DEBUG - php://input length: " . strlen($raw_input));
error_log("DEBUG - php://input preview: " . substr($raw_input, 0, 100));

// Aceptar si es POST, si hay datos POST, o si es GET con parámetro ci
// (El último caso es para manejar redirects que convierten POST a GET)
if ($request_method !== 'POST' && !$has_post_data && !$has_get_ci) {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => "Método no permitido. Use POST o GET con parámetro 'ci'. Método recibido: $request_method",
        'data' => null,
        'debug' => [
            'REQUEST_METHOD' => $request_method,
            'has_post_data' => $has_post_data,
            'has_get_ci' => $has_get_ci,
            'POST_count' => count($_POST),
            'GET_count' => count($_GET),
            'input_length' => strlen($raw_input)
        ]
    ]);
    exit;
}

header('Content-Type: application/json');

try {
    // Obtener CI del POST, GET o input raw (en orden de prioridad)
    $ci = '';
    
    // 1. Primero intentar POST
    if (!empty($_POST['ci'])) {
        $ci = trim($_POST['ci']);
    }
    // 2. Si no está en $_POST, intentar GET (por si un redirect convirtió POST a GET)
    elseif (!empty($_GET['ci'])) {
        $ci = trim($_GET['ci']);
    }
    // 3. Si no está en $_POST ni $_GET, intentar leer del input raw
    elseif (!empty($raw_input)) {
        parse_str($raw_input, $parsed);
        $ci = trim($parsed['ci'] ?? '');
    }
    
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

