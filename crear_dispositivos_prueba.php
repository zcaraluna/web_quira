<?php
/**
 * Script para insertar dispositivos biométricos de prueba
 * Este script crea dispositivos de ejemplo para el sistema
 */

require_once 'config.php';

try {
    $pdo = getDBConnection();
    
    // Verificar si ya existen dispositivos
    $stmt = $pdo->query("SELECT COUNT(*) FROM aparatos_biometricos");
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        echo "ℹ️  Ya existen $count dispositivos biométricos en la base de datos.\n";
        echo "Dispositivos existentes:\n";
        
        $stmt = $pdo->query("SELECT id, nombre, serial, ip_address, ubicacion FROM aparatos_biometricos ORDER BY id");
        $dispositivos = $stmt->fetchAll();
        
        foreach ($dispositivos as $dispositivo) {
            echo "   - ID: {$dispositivo['id']}, Nombre: {$dispositivo['nombre']}, Serial: {$dispositivo['serial']}\n";
        }
        exit;
    }
    
    // Dispositivos de prueba a insertar
    $dispositivos = [
        [
            'nombre' => 'ANAPOL 1',
            'serial' => 'A8MX193760001',
            'ip_address' => '192.168.1.100',
            'puerto' => 4370,
            'ubicacion' => 'Academia Nacional de Policía - Laboratorio Principal',
            'activo' => true
        ],
        [
            'nombre' => 'ANAPOL 2',
            'serial' => 'A8MX193760002',
            'ip_address' => '192.168.1.101',
            'puerto' => 4370,
            'ubicacion' => 'Academia Nacional de Policía - Aula de Prácticas',
            'activo' => true
        ],
        [
            'nombre' => 'ANAPOL 3',
            'serial' => 'A8MX193760003',
            'ip_address' => '192.168.1.102',
            'puerto' => 4370,
            'ubicacion' => 'Academia Nacional de Policía - Oficina Administrativa',
            'activo' => true
        ],
        [
            'nombre' => 'APARATO DE PRUEBA',
            'serial' => '0X0AB0',
            'ip_address' => '127.0.0.1',
            'puerto' => 4370,
            'ubicacion' => 'Sistema de Pruebas',
            'activo' => true
        ]
    ];
    
    echo "🔧 Insertando dispositivos biométricos de prueba...\n\n";
    
    foreach ($dispositivos as $dispositivo) {
        $stmt = $pdo->prepare("
            INSERT INTO aparatos_biometricos (
                nombre, serial, ip_address, puerto, ubicacion, activo, fecha_registro
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $resultado = $stmt->execute([
            $dispositivo['nombre'],
            $dispositivo['serial'],
            $dispositivo['ip_address'],
            $dispositivo['puerto'],
            $dispositivo['ubicacion'],
            $dispositivo['activo']
        ]);
        
        if ($resultado) {
            $id = $pdo->lastInsertId();
            echo "✅ Dispositivo insertado: ID $id - {$dispositivo['nombre']} ({$dispositivo['serial']})\n";
        } else {
            echo "❌ Error insertando dispositivo: {$dispositivo['nombre']}\n";
        }
    }
    
    echo "\n🎉 Dispositivos biométricos creados exitosamente!\n";
    echo "\n📋 Resumen de dispositivos disponibles:\n";
    
    $stmt = $pdo->query("SELECT id, nombre, serial, ubicacion FROM aparatos_biometricos ORDER BY id");
    $dispositivos_creados = $stmt->fetchAll();
    
    foreach ($dispositivos_creados as $dispositivo) {
        echo "   - ID: {$dispositivo['id']} | {$dispositivo['nombre']} | Serial: {$dispositivo['serial']} | Ubicación: {$dispositivo['ubicacion']}\n";
    }
    
    echo "\n💡 Ahora puedes ejecutar 'php insertar_datos_prueba.php' para crear el postulante de prueba.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
