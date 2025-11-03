<?php
/**
 * Script para eliminar preinscriptos insertados en una fecha específica
 * Uso: php eliminar_preinscriptos_fecha.php [fecha]
 * Si no se especifica fecha, elimina los del 03/11/2025
 * Formato de fecha: dd/mm/yyyy o yyyy-mm-dd
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

// Incluir configuración
require_once 'config.php';

// Obtener fecha desde argumento de línea de comandos o usar default
$fecha_input = $argv[1] ?? '03/11/2025';

echo "🗑️  Script para eliminar preinscriptos por fecha\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📅 Fecha objetivo: $fecha_input\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Convertir fecha al formato que necesitamos
$fecha_sql = null;
$fecha_display = null;

// Intentar parsear formato dd/mm/yyyy
if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha_input, $matches)) {
    $dia = $matches[1];
    $mes = $matches[2];
    $ano = $matches[3];
    $fecha_sql = "$ano-$mes-$dia";
    $fecha_display = "$dia/$mes/$ano";
} 
// Intentar parsear formato yyyy-mm-dd
elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha_input, $matches)) {
    $fecha_sql = $fecha_input;
    $fecha_display = $matches[3] . '/' . $matches[2] . '/' . $matches[1];
} 
else {
    die("❌ Error: Formato de fecha inválido. Use dd/mm/yyyy o yyyy-mm-dd\n");
}

echo "📋 Fecha a buscar: $fecha_display ($fecha_sql)\n\n";

// Conectar a la base de datos
try {
    $pdo = getDBConnection();
    echo "✅ Conectado a la base de datos\n\n";
    
    // Primero, consultar cuántos registros hay que eliminar
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as total 
        FROM preinscriptos 
        WHERE DATE(fecha_registro) = :fecha
    ");
    $stmt_count->execute(['fecha' => $fecha_sql]);
    $count_result = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $total_registros = (int)$count_result['total'];
    
    if ($total_registros === 0) {
        echo "ℹ️  No se encontraron preinscriptos registrados el $fecha_display\n";
        exit(0);
    }
    
    echo "⚠️  ATENCIÓN: Se encontraron $total_registros registro(s) para eliminar\n\n";
    
    // Mostrar algunos ejemplos de los registros que se eliminarán
    $stmt_preview = $pdo->prepare("
        SELECT id, ci, nombre_completo, fecha_registro, unidad
        FROM preinscriptos 
        WHERE DATE(fecha_registro) = :fecha
        ORDER BY fecha_registro DESC
        LIMIT 10
    ");
    $stmt_preview->execute(['fecha' => $fecha_sql]);
    $ejemplos = $stmt_preview->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($ejemplos)) {
        echo "📋 Ejemplos de registros a eliminar (primeros 10):\n";
        foreach ($ejemplos as $ejemplo) {
            echo "   • ID: {$ejemplo['id']}, CI: {$ejemplo['ci']}, Nombre: {$ejemplo['nombre_completo']}\n";
        }
        if ($total_registros > 10) {
            echo "   ... y " . ($total_registros - 10) . " más\n";
        }
        echo "\n";
    }
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // Eliminar registros
    $stmt_delete = $pdo->prepare("
        DELETE FROM preinscriptos 
        WHERE DATE(fecha_registro) = :fecha
    ");
    
    echo "🗑️  Eliminando registros...\n";
    $stmt_delete->execute(['fecha' => $fecha_sql]);
    $registros_eliminados = $stmt_delete->rowCount();
    
    // Confirmar transacción
    $pdo->commit();
    
    echo "✅ Eliminación completada exitosamente\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Resumen:\n";
    echo "  • Fecha: $fecha_display\n";
    echo "  • Registros eliminados: $registros_eliminados\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
} catch (PDOException $e) {
    // Rollback en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("❌ Error: " . $e->getMessage() . "\n");
} catch (Exception $e) {
    // Rollback en caso de error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("❌ Error: " . $e->getMessage() . "\n");
}

