<?php
/**
 * Script de migración para combinar nombre y apellido en nombre_completo
 * Sistema QUIRA
 * 
 * IMPORTANTE: Hacer backup de la base de datos antes de ejecutar este script
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

require_once 'config.php';

echo "========================================\n";
echo "Script de Migración: nombre_completo\n";
echo "========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    echo "Paso 1: Verificando estructura actual...\n";
    
    // Verificar si las columnas nombre y apellido existen
    $check_columns = $pdo->query("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'postulantes' 
        AND column_name IN ('nombre', 'apellido', 'nombre_completo')
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Columnas encontradas: " . implode(', ', $check_columns) . "\n\n";
    
    // Verificar si nombre_completo ya existe
    if (in_array('nombre_completo', $check_columns)) {
        echo "⚠️  La columna 'nombre_completo' ya existe.\n";
        echo "¿Desea continuar de todas formas? (Esto combinara los datos existentes)\n";
        echo "Si nombre_completo tiene datos, se actualizaran con nombre + apellido.\n\n";
    }
    
    // Paso 2: Agregar columna nombre_completo si no existe
    if (!in_array('nombre_completo', $check_columns)) {
        echo "Paso 2: Agregando columna 'nombre_completo'...\n";
        $pdo->exec("
            ALTER TABLE postulantes 
            ADD COLUMN nombre_completo VARCHAR(200)
        ");
        echo "✅ Columna 'nombre_completo' agregada.\n\n";
    } else {
        echo "Paso 2: La columna 'nombre_completo' ya existe, omitiendo creación.\n\n";
    }
    
    // Paso 2.5: Permitir NULL en nombre y apellido (temporal, hasta eliminar columnas)
    echo "Paso 2.5: Modificando restricciones de 'nombre' y 'apellido' para permitir NULL...\n";
    try {
        $pdo->exec("ALTER TABLE postulantes ALTER COLUMN nombre DROP NOT NULL");
        echo "✅ Columna 'nombre' ahora permite NULL.\n";
    } catch (Exception $e) {
        echo "⚠️  No se pudo modificar 'nombre': " . $e->getMessage() . "\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE postulantes ALTER COLUMN apellido DROP NOT NULL");
        echo "✅ Columna 'apellido' ahora permite NULL.\n\n";
    } catch (Exception $e) {
        echo "⚠️  No se pudo modificar 'apellido': " . $e->getMessage() . "\n\n";
    }
    
    // Paso 3: Combinar nombre + apellido en nombre_completo
    echo "Paso 3: Combinando nombre y apellido en nombre_completo...\n";
    
    $update_query = "
        UPDATE postulantes 
        SET nombre_completo = TRIM(
            CONCAT(
                COALESCE(nombre, ''), 
                CASE WHEN nombre IS NOT NULL AND apellido IS NOT NULL THEN ' ' ELSE '' END,
                COALESCE(apellido, '')
            )
        )
        WHERE nombre_completo IS NULL OR nombre_completo = ''
    ";
    
    $stmt = $pdo->prepare($update_query);
    $stmt->execute();
    $affected = $stmt->rowCount();
    
    echo "✅ Registros actualizados: $affected\n\n";
    
    // Verificar resultados
    echo "Paso 4: Verificando resultados...\n";
    $verificacion = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(nombre_completo) as con_nombre_completo,
            COUNT(CASE WHEN nombre_completo IS NULL OR nombre_completo = '' THEN 1 END) as sin_nombre_completo
        FROM postulantes
    ")->fetch();
    
    echo "Total de registros: " . $verificacion['total'] . "\n";
    echo "Con nombre_completo: " . $verificacion['con_nombre_completo'] . "\n";
    echo "Sin nombre_completo: " . $verificacion['sin_nombre_completo'] . "\n\n";
    
    // Mostrar algunos ejemplos
    echo "Paso 5: Mostrando algunos ejemplos...\n";
    $ejemplos = $pdo->query("
        SELECT nombre, apellido, nombre_completo 
        FROM postulantes 
        WHERE nombre_completo IS NOT NULL 
        LIMIT 5
    ")->fetchAll();
    
    foreach ($ejemplos as $ejemplo) {
        echo "  - Nombre: '{$ejemplo['nombre']}', Apellido: '{$ejemplo['apellido']}' → Completo: '{$ejemplo['nombre_completo']}'\n";
    }
    echo "\n";
    
    // Confirmar antes de eliminar columnas
    echo "========================================\n";
    echo "⚠️  ATENCIÓN: Próximo paso eliminará las columnas 'nombre' y 'apellido'\n";
    echo "========================================\n";
    echo "¿Desea eliminar las columnas 'nombre' y 'apellido' ahora? (S/N): ";
    
    // En modo interactivo, esperar respuesta del usuario
    // Por ahora, comentamos la eliminación y la dejamos como paso manual
    echo "\n\n";
    echo "NOTA: Por seguridad, las columnas 'nombre' y 'apellido' NO se eliminarán automáticamente.\n";
    echo "Para eliminarlas manualmente, ejecute:\n";
    echo "  ALTER TABLE postulantes DROP COLUMN nombre;\n";
    echo "  ALTER TABLE postulantes DROP COLUMN apellido;\n";
    echo "\n";
    echo "O descomente las siguientes líneas en este script y ejecute nuevamente.\n\n";
    
    // Descomentar estas líneas para eliminar las columnas (después de verificar que todo funciona)
    /*
    echo "Paso 6: Eliminando columnas 'nombre' y 'apellido'...\n";
    $pdo->exec("ALTER TABLE postulantes DROP COLUMN nombre");
    echo "✅ Columna 'nombre' eliminada.\n";
    $pdo->exec("ALTER TABLE postulantes DROP COLUMN apellido");
    echo "✅ Columna 'apellido' eliminada.\n\n";
    */
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "========================================\n";
    echo "✅ Migración completada exitosamente!\n";
    echo "========================================\n";
    echo "Próximos pasos:\n";
    echo "1. Verificar que los datos se migraron correctamente\n";
    echo "2. Actualizar todos los archivos PHP para usar nombre_completo\n";
    echo "3. Probar el sistema completamente\n";
    echo "4. Después de verificar, eliminar las columnas nombre y apellido manualmente\n";
    echo "\n";
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Se realizó rollback de la transacción.\n";
    exit(1);
}
?>

