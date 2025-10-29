<?php
/**
 * Script para eliminar postulante con ID 79
 * Sistema QUIRA - Eliminación segura
 */

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

session_start();
require_once 'config.php';
requireLogin();

// Verificar permisos (solo ADMIN y SUPERADMIN pueden eliminar)
if (!in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])) {
    die('❌ ERROR: No tienes permisos para eliminar postulantes. Solo ADMIN y SUPERADMIN pueden realizar esta acción.');
}

$postulante_id = 79;

try {
    $pdo = getDBConnection();
    
    // 1. Verificar que el postulante existe
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.nombre, p.apellido, p.cedula, p.aparato_id, p.aparato_nombre
        FROM postulantes p
        WHERE p.id = ?
    ");
    $stmt->execute([$postulante_id]);
    $postulante = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$postulante) {
        die("❌ ERROR: No se encontró postulante con ID $postulante_id");
    }
    
    echo "🔍 <strong>ELIMINANDO POSTULANTE ID $postulante_id:</strong>\n\n";
    echo "📋 <strong>Datos del Postulante:</strong>\n";
    echo "   • Nombre: " . htmlspecialchars($postulante['nombre']) . " " . htmlspecialchars($postulante['apellido']) . "\n";
    echo "   • Cédula: " . htmlspecialchars($postulante['cedula']) . "\n";
    echo "   • Aparato ID: " . htmlspecialchars($postulante['aparato_id']) . "\n";
    echo "   • Aparato Nombre: " . htmlspecialchars($postulante['aparato_nombre']) . "\n\n";
    
    // 2. Iniciar transacción
    $pdo->beginTransaction();
    
    try {
        // 3. Eliminar asistencias relacionadas
        $stmt_asistencias = $pdo->prepare("DELETE FROM asistencias WHERE postulante_id = ?");
        $stmt_asistencias->execute([$postulante_id]);
        $asistencias_eliminadas = $stmt_asistencias->rowCount();
        
        echo "📊 <strong>Eliminando datos relacionados:</strong>\n";
        echo "   • Asistencias eliminadas: $asistencias_eliminadas\n";
        
        // 4. Eliminar del dispositivo biométrico si está registrado
        if (!empty($postulante['aparato_id'])) {
            // Aquí podrías agregar lógica para eliminar del dispositivo biométrico
            // Por ahora solo mostramos la información
            echo "   • Aparato biométrico: ID " . $postulante['aparato_id'] . " (requiere limpieza manual)\n";
        }
        
        // 5. Eliminar el postulante
        $stmt_postulante = $pdo->prepare("DELETE FROM postulantes WHERE id = ?");
        $stmt_postulante->execute([$postulante_id]);
        $postulante_eliminado = $stmt_postulante->rowCount();
        
        if ($postulante_eliminado > 0) {
            // 6. Confirmar transacción
            $pdo->commit();
            
            echo "\n✅ <strong>ELIMINACIÓN EXITOSA:</strong>\n";
            echo "   • Postulante eliminado: " . htmlspecialchars($postulante['nombre']) . " " . htmlspecialchars($postulante['apellido']) . "\n";
            echo "   • Cédula: " . htmlspecialchars($postulante['cedula']) . "\n";
            echo "   • Asistencias eliminadas: $asistencias_eliminadas\n";
            echo "   • Fecha de eliminación: " . date('d/m/Y H:i:s') . "\n";
            echo "   • Eliminado por: " . $_SESSION['usuario'] . " (" . $_SESSION['rol'] . ")\n\n";
            
            echo "⚠️  <strong>NOTA IMPORTANTE:</strong>\n";
            echo "   Si el postulante estaba registrado en un dispositivo biométrico,\n";
            echo "   recuerda eliminarlo manualmente del dispositivo para mantener\n";
            echo "   la sincronización del sistema.\n";
            
        } else {
            throw new Exception('No se pudo eliminar el postulante');
        }
        
    } catch (Exception $e) {
        // 7. Rollback en caso de error
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "🔄 La transacción fue revertida. No se realizaron cambios.\n";
}
?>
