<?php
session_start();

// Verificar si el usuario está logueado y es SUPERADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sistema_postulantes';
$username = 'postgres';
$password = 'Postgres2025!';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $cedula = $_POST['cedula'] ?? '';
        
        if (empty($cedula)) {
            echo json_encode(['success' => false, 'message' => 'Cédula requerida']);
            exit;
        }
        
        // Buscar postulante por cédula
        $stmt = $pdo->prepare("
            SELECT id, nombre, apellido, cedula, unidad, fecha_registro, edad, dedo_registrado, registrado_por
            FROM postulantes 
            WHERE cedula = ?
        ");
        $stmt->execute([$cedula]);
        $postulante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($postulante) {
            // Formatear fecha
            $postulante['fecha_registro'] = date('d/m/Y H:i', strtotime($postulante['fecha_registro']));
            
            echo json_encode([
                'success' => true, 
                'postulante' => $postulante
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'No se encontró ningún postulante con esa cédula'
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
