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
        $id = $_POST['id'] ?? '';
        
        if (empty($id) || !is_numeric($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de postulante inválido']);
            exit;
        }
        
        // Verificar que el postulante existe
        $stmt = $pdo->prepare("SELECT id, nombre, apellido, cedula FROM postulantes WHERE id = ?");
        $stmt->execute([$id]);
        $postulante = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$postulante) {
            echo json_encode(['success' => false, 'message' => 'Postulante no encontrado']);
            exit;
        }
        
        // Iniciar transacción
        $pdo->beginTransaction();
        
        try {
            // Eliminar el postulante
            $stmt = $pdo->prepare("DELETE FROM postulantes WHERE id = ?");
            $stmt->execute([$id]);
            
            // Verificar que se eliminó correctamente
            if ($stmt->rowCount() === 0) {
                throw new Exception("No se pudo eliminar el postulante");
            }
            
            // Confirmar transacción
            $pdo->commit();
            
            // Log de la eliminación
            error_log("Postulante eliminado desde modal - ID: {$id}, Nombre: {$postulante['nombre']} {$postulante['apellido']}, Cédula: {$postulante['cedula']}, Usuario: {$_SESSION['nombre']} {$_SESSION['apellido']}");
            
            echo json_encode([
                'success' => true, 
                'message' => 'Postulante eliminado exitosamente'
            ]);
            
        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $pdo->rollBack();
            throw $e;
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
