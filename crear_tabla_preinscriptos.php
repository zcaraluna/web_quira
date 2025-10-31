<?php
/**
 * Script para crear la tabla preinscriptos
 * Sistema QUIRA
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

require_once 'config.php';

echo "========================================\n";
echo "Creación de tabla: preinscriptos\n";
echo "========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar si la tabla ya existe
    $check_table = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'preinscriptos'
        )
    ")->fetchColumn();
    
    if ($check_table) {
        echo "⚠️  La tabla 'preinscriptos' ya existe.\n";
        echo "¿Desea eliminarla y crearla de nuevo? (Esto eliminará todos los datos)\n";
        echo "Si desea continuar, descomente las líneas de DROP TABLE en este script.\n\n";
        
        // Mostrar información de la tabla actual
        $stmt = $pdo->query("SELECT COUNT(*) FROM preinscriptos");
        $count = $stmt->fetchColumn();
        echo "Registros actuales en la tabla: $count\n\n";
        
        // Si desea eliminar, descomentar estas líneas:
        // $pdo->exec("DROP TABLE preinscriptos CASCADE");
        // echo "✅ Tabla 'preinscriptos' eliminada.\n\n";
    } else {
        echo "Paso 1: Creando tabla 'preinscriptos'...\n";
        
        $pdo->exec("
            CREATE TABLE preinscriptos (
                id SERIAL PRIMARY KEY,
                ci VARCHAR(20) NOT NULL UNIQUE,
                nombre_completo VARCHAR(200) NOT NULL,
                fecha_nacimiento DATE NOT NULL,
                sexo VARCHAR(1) NOT NULL CHECK (sexo IN ('H', 'M')),
                unidad VARCHAR(500) NOT NULL,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        echo "✅ Tabla 'preinscriptos' creada exitosamente.\n\n";
        
        // Crear índice en CI para búsquedas rápidas
        echo "Paso 2: Creando índice en columna 'ci'...\n";
        $pdo->exec("CREATE INDEX idx_preinscriptos_ci ON preinscriptos(ci)");
        echo "✅ Índice creado.\n\n";
    }
    
    echo "========================================\n";
    echo "✅ Proceso completado exitosamente!\n";
    echo "========================================\n";
    echo "\nPróximos pasos:\n";
    echo "1. Ejecutar el script de importación: importar_preinscriptos.php\n";
    echo "2. Verificar que los datos se importaron correctamente\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "========================================\n";
    exit(1);
}
?>

