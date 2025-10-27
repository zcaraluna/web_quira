<?php
/**
 * Script para insertar datos de prueba del postulante GUILLERMO RECALDE VALDEZ
 * Este script se ejecuta una sola vez para crear datos de prueba
 */

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Verificar si el postulante ya existe
    $stmt = $pdo->prepare("SELECT id FROM postulantes WHERE cedula = ?");
    $stmt->execute(['5995260']);
    
    if ($stmt->fetch()) {
        echo "❌ El postulante con CI 5995260 ya existe en la base de datos.\n";
        exit;
    }
    
    // Verificar usuarios existentes
    $stmt = $pdo->query("SELECT id FROM usuarios ORDER BY id LIMIT 1");
    $primer_usuario = $stmt->fetch();
    $usuario_registrador = $primer_usuario ? $primer_usuario['id'] : null;
    
    if (!$usuario_registrador) {
        echo "❌ No se encontraron usuarios en la base de datos. Debe crear al menos un usuario primero.\n";
        exit;
    }
    
    // Verificar dispositivos biométricos existentes
    $stmt = $pdo->query("SELECT id, nombre, serial FROM aparatos_biometricos WHERE nombre = 'ANAPOL 1' LIMIT 1");
    $dispositivo = $stmt->fetch();
    
    if (!$dispositivo) {
        echo "❌ No se encontró el dispositivo 'ANAPOL 1'. Ejecute primero 'php crear_dispositivos_prueba.php'.\n";
        exit;
    }
    
    $aparato_id = $dispositivo['id'];
    $aparato_nombre = $dispositivo['nombre'];
    
    // Datos del postulante de prueba
    $nombre = 'GUILLERMO';
    $apellido = 'RECALDE VALDEZ';
    $cedula = '5995260';
    $telefono = '0982 311 865';
    $fecha_nacimiento = '1997-11-09'; // Formato YYYY-MM-DD
    $unidad = 'Academia Nacional de Policía "Gral. JOSE E. DIAZ"';
    $observaciones = 'Postulante de prueba para verificación';
    $fecha_registro = date('Y-m-d H:i:s'); // Fecha y hora actual
    $registrado_por = 'Oficial Segundo JOSE DIAZ';
    $edad = 26; // Calculado basado en fecha de nacimiento
    $sexo = 'Masculino';
    $dedo_registrado = 'ID'; // Dedo índice derecho
    $uid_k40 = 4; // UID numérico para K40
    $fecha_ultima_edicion = $fecha_registro;
    $capturador_id = $usuario_registrador; // Usar el mismo usuario como capturador
    
    // Insertar el postulante
    $stmt = $pdo->prepare("
        INSERT INTO postulantes (
            nombre, apellido, cedula, telefono, fecha_nacimiento, 
            unidad, observaciones, fecha_registro, usuario_registrador, 
            registrado_por, edad, sexo, dedo_registrado, aparato_id, 
            uid_k40, aparato_nombre, fecha_ultima_edicion, capturador_id
        ) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $resultado = $stmt->execute([
        $nombre, $apellido, $cedula, $telefono, $fecha_nacimiento,
        $unidad, $observaciones, $fecha_registro, $usuario_registrador,
        $registrado_por, $edad, $sexo, $dedo_registrado, $aparato_id,
        $uid_k40, $aparato_nombre, $fecha_ultima_edicion, $capturador_id
    ]);
    
    if ($resultado) {
        $postulante_id = $pdo->lastInsertId();
        echo "✅ Postulante de prueba insertado exitosamente!\n";
        echo "📋 Datos insertados:\n";
        echo "   - ID: $postulante_id\n";
        echo "   - Nombre: $nombre $apellido\n";
        echo "   - CI: $cedula\n";
        echo "   - Teléfono: $telefono\n";
        echo "   - Fecha de nacimiento: $fecha_nacimiento\n";
        echo "   - Unidad: $unidad\n";
        echo "   - Capturador: Oficial Ayudante JOSE MERLO\n";
        echo "   - Registrador: $registrado_por\n";
        echo "   - Dedo registrado: $dedo_registrado\n";
        echo "   - UID K40: K40 $uid_k40\n";
        echo "   - Fecha de registro: $fecha_registro\n";
        echo "\n🔍 Ahora puedes usar la página verificar.php para consultar estos datos.\n";
    } else {
        echo "❌ Error al insertar el postulante de prueba.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
