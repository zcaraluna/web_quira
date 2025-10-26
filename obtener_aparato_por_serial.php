<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Incluir archivo de configuración de base de datos
require_once 'config.php';

try {
    // Obtener serial desde GET o POST
    $serial = null;
    
    // Log para debugging
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("GET data: " . json_encode($_GET));
    error_log("POST data: " . json_encode($_POST));
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $serial = $_GET['serial'] ?? null;
        error_log("Serial desde GET: " . $serial);
    } else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw_input = file_get_contents('php://input');
        $input = json_decode($raw_input, true);
        $serial = $input['serial'] ?? null;
        error_log("Serial desde POST: " . $serial);
    } else {
        error_log("Método no soportado: " . $_SERVER['REQUEST_METHOD']);
        throw new Exception('Método no soportado: ' . $_SERVER['REQUEST_METHOD']);
    }
    
    if (!$serial) {
        throw new Exception('Serial del dispositivo requerido');
    }
    
    $serial = trim($serial);
    
    if (empty($serial)) {
        throw new Exception('Serial no puede estar vacío');
    }
    
    // Conectar a la base de datos
    $pdo = getDBConnection();
    
    // Log para debugging
    error_log("Buscando aparato con serial: " . $serial);
    
    // Consultar la base de datos
    $stmt = $pdo->prepare("SELECT id, nombre, serial FROM aparatos_biometricos WHERE serial = ? LIMIT 1");
    $stmt->execute([$serial]);
    $aparato = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log del resultado
    error_log("Resultado de la consulta: " . json_encode($aparato));
    
    if ($aparato) {
        echo json_encode([
            'success' => true,
            'device_name' => $aparato['nombre'],
            'aparato' => [
                'id' => $aparato['id'],
                'nombre' => $aparato['nombre'],
                'serial' => $aparato['serial']
            ]
        ]);
    } else {
        // Consultar todos los aparatos para debugging
        $stmt_all = $pdo->prepare("SELECT id, nombre, serial FROM aparatos_biometricos ORDER BY id");
        $stmt_all->execute();
        $todos_aparatos = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Todos los aparatos en BD: " . json_encode($todos_aparatos));
        
        echo json_encode([
            'success' => false,
            'message' => 'Aparato no encontrado',
            'serial_buscado' => $serial,
            'debug' => [
                'todos_aparatos' => $todos_aparatos
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log("Error en obtener_aparato_por_serial.php: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>