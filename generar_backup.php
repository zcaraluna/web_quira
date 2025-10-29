<?php
session_start();

// Verificar si el usuario está logueado y es SUPERADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'SUPERADMIN') {
    http_response_code(403);
    die('Acceso denegado');
}

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sistema_postulantes';
$username = 'postgres';
$password = 'Postgres2025!';

try {
    // Crear nombre de archivo con timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_quira_{$timestamp}.sql";
    
    // Comando pg_dump
    $command = "pg_dump -h {$host} -U {$username} -d {$dbname} --no-password";
    
    // Configurar variables de entorno para evitar prompt de contraseña
    putenv("PGPASSWORD={$password}");
    
    // Ejecutar el comando
    $output = shell_exec($command . " 2>&1");
    
    if ($output === null) {
        throw new Exception("Error al ejecutar pg_dump");
    }
    
    // Verificar si hay errores en la salida
    if (strpos($output, 'ERROR') !== false) {
        throw new Exception("Error en pg_dump: " . $output);
    }
    
    // Configurar headers para descarga
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($output));
    
    // Limpiar buffer de salida
    ob_clean();
    flush();
    
    // Enviar el contenido del backup
    echo $output;
    
} catch (Exception $e) {
    http_response_code(500);
    echo "Error al generar backup: " . $e->getMessage();
}
?>
