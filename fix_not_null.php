<?php
/**
 * Script para permitir NULL en nombre y apellido
 * Usa las credenciales de config.php
 */

require_once 'config.php';

echo "========================================\n";
echo "Fix: Permitir NULL en nombre y apellido\n";
echo "========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Paso 1: Modificando columna 'nombre' para permitir NULL...\n";
    try {
        $pdo->exec("ALTER TABLE postulantes ALTER COLUMN nombre DROP NOT NULL");
        echo "✅ Columna 'nombre' ahora permite NULL.\n\n";
    } catch (Exception $e) {
        echo "❌ Error modificando 'nombre': " . $e->getMessage() . "\n";
        echo "   (Puede que ya esté permitido NULL o que no exista la restricción)\n\n";
    }
    
    echo "Paso 2: Modificando columna 'apellido' para permitir NULL...\n";
    try {
        $pdo->exec("ALTER TABLE postulantes ALTER COLUMN apellido DROP NOT NULL");
        echo "✅ Columna 'apellido' ahora permite NULL.\n\n";
    } catch (Exception $e) {
        echo "❌ Error modificando 'apellido': " . $e->getMessage() . "\n";
        echo "   (Puede que ya esté permitido NULL o que no exista la restricción)\n\n";
    }
    
    // Verificar el estado actual
    echo "Paso 3: Verificando estado de las columnas...\n";
    $columnas = $pdo->query("
        SELECT 
            column_name,
            is_nullable,
            data_type
        FROM information_schema.columns 
        WHERE table_name = 'postulantes' 
        AND column_name IN ('nombre', 'apellido', 'nombre_completo')
        ORDER BY column_name
    ")->fetchAll();
    
    foreach ($columnas as $col) {
        $nullable = $col['is_nullable'] === 'YES' ? 'SÍ' : 'NO';
        echo "  - {$col['column_name']}: permite NULL = {$nullable} ({$col['data_type']})\n";
    }
    echo "\n";
    
    echo "========================================\n";
    echo "✅ Proceso completado!\n";
    echo "========================================\n";
    echo "Ahora puedes crear nuevos postulantes sin problemas.\n";
    echo "Las columnas 'nombre' y 'apellido' permiten NULL.\n\n";
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>

