<?php
/**
 * Script para importar datos del CSV preinsc.csv a la tabla preinscriptos
 * Sistema QUIRA
 * 
 * Formato CSV esperado:
 * - Separador: punto y coma (;)
 * - Encabezado: CI;NOMBRE COMPLETO;NACIMIENTO;SEXO;UNIDAD
 * - Fecha nacimiento: dd/mm/yyyy
 * - Sexo: H (Hombre) o M (Mujer)
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

require_once 'config.php';

echo "========================================\n";
echo "Importación de Preinscriptos desde CSV\n";
echo "========================================\n";
echo "Fecha: " . date('Y-m-d H:i:s') . "\n\n";

$csv_file = 'preinsc.csv';

if (!file_exists($csv_file)) {
    echo "❌ Error: No se encontró el archivo '$csv_file'\n";
    echo "   Asegúrese de que el archivo esté en el mismo directorio que este script.\n";
    exit(1);
}

try {
    $pdo = getDBConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Verificar que la tabla existe
    $check_table = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'preinscriptos'
        )
    ")->fetchColumn();
    
    if (!$check_table) {
        echo "❌ Error: La tabla 'preinscriptos' no existe.\n";
        echo "   Ejecute primero el script: crear_tabla_preinscriptos.php\n";
        exit(1);
    }
    
    // Leer el contenido completo del archivo para detectar BOM y separador
    $content = file_get_contents($csv_file);
    
    // Remover BOM si existe (UTF-8 BOM: EF BB BF)
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Guardar temporalmente sin BOM
    $temp_file = tempnam(sys_get_temp_dir(), 'csv_import_');
    file_put_contents($temp_file, $content);
    
    // Detectar el separador (puede ser ; o ,)
    $first_line = explode("\n", $content)[0];
    $separator = (strpos($first_line, ';') !== false) ? ';' : ',';
    
    echo "Separador detectado: '$separator'\n";
    
    // Abrir archivo CSV
    $handle = fopen($temp_file, 'r');
    if (!$handle) {
        throw new Exception("No se pudo abrir el archivo CSV");
    }
    
    // Leer encabezado (primera línea)
    $header = fgetcsv($handle, 0, $separator);
    if (!$header) {
        throw new Exception("No se pudo leer el encabezado del CSV");
    }
    
    // Normalizar encabezado: trim y remover BOM/espacios invisibles
    $header_normalized = array_map(function($col) {
        // Remover BOM y espacios
        $col = trim($col);
        // Remover cualquier carácter no imprimible al inicio
        $col = preg_replace('/^[\x00-\x1F\x80-\xFF]+/', '', $col);
        return $col;
    }, $header);
    
    echo "Encabezado detectado: " . implode(', ', $header_normalized) . "\n\n";
    
    // Verificar que las columnas esperadas están presentes
    $expected_columns = ['CI', 'NOMBRE COMPLETO', 'NACIMIENTO', 'SEXO', 'UNIDAD'];
    
    // Crear mapa de índices de columnas
    $column_map = [];
    foreach ($expected_columns as $expected) {
        $found = false;
        foreach ($header_normalized as $index => $col) {
            if (strtoupper(trim($col)) === strtoupper($expected)) {
                $column_map[$expected] = $index;
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception("Columna esperada '$expected' no encontrada en el CSV. Columnas encontradas: " . implode(', ', $header_normalized));
        }
    }
    
    echo "Columnas mapeadas correctamente.\n\n";
    
    // Preparar statement para inserción
    $stmt = $pdo->prepare("
        INSERT INTO preinscriptos (ci, nombre_completo, fecha_nacimiento, sexo, unidad)
        VALUES (?, ?, ?, ?, ?)
        ON CONFLICT (ci) DO UPDATE SET
            nombre_completo = EXCLUDED.nombre_completo,
            fecha_nacimiento = EXCLUDED.fecha_nacimiento,
            sexo = EXCLUDED.sexo,
            unidad = EXCLUDED.unidad,
            fecha_actualizacion = CURRENT_TIMESTAMP
    ");
    
    // Iniciar transacción
    $pdo->beginTransaction();
    
    $line_number = 1; // Contador de líneas (incluye encabezado)
    $imported = 0;
    $updated = 0;
    $errors = 0;
    $error_details = [];
    
    echo "Iniciando importación...\n\n";
    
    // Leer y procesar cada línea
    while (($data = fgetcsv($handle, 0, $separator)) !== FALSE) {
        $line_number++;
        
        // Validar que tenga suficientes columnas
        if (count($data) < 5) {
            $errors++;
            $error_details[] = "Línea $line_number: No tiene suficientes columnas (" . count($data) . " encontradas, 5 esperadas)";
            continue;
        }
        
        // Extraer datos usando el mapa de columnas
        $ci = isset($data[$column_map['CI']]) ? trim($data[$column_map['CI']]) : '';
        $nombre_completo = isset($data[$column_map['NOMBRE COMPLETO']]) ? trim($data[$column_map['NOMBRE COMPLETO']]) : '';
        $nacimiento_str = isset($data[$column_map['NACIMIENTO']]) ? trim($data[$column_map['NACIMIENTO']]) : '';
        $sexo = isset($data[$column_map['SEXO']]) ? strtoupper(trim($data[$column_map['SEXO']])) : '';
        $unidad = isset($data[$column_map['UNIDAD']]) ? trim($data[$column_map['UNIDAD']]) : '';
        
        // Limpiar comillas dobles de la unidad (formato CSV con comillas)
        $unidad = str_replace('""', '"', $unidad); // Reemplazar "" por "
        $unidad = trim($unidad, '"'); // Eliminar comillas externas
        
        // Validaciones
        if (empty($ci)) {
            $errors++;
            $error_details[] = "Línea $line_number: CI vacío";
            continue;
        }
        
        if (empty($nombre_completo)) {
            $errors++;
            $error_details[] = "Línea $line_number: Nombre completo vacío";
            continue;
        }
        
        if ($sexo !== 'H' && $sexo !== 'M') {
            $errors++;
            $error_details[] = "Línea $line_number: Sexo inválido ('$sexo', debe ser H o M)";
            continue;
        }
        
        // Convertir fecha de dd/mm/yyyy a yyyy-mm-dd
        $fecha_nacimiento = null;
        if (!empty($nacimiento_str)) {
            $parts = explode('/', $nacimiento_str);
            if (count($parts) === 3) {
                $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $year = $parts[2];
                $fecha_nacimiento = "$year-$month-$day";
            } else {
                $errors++;
                $error_details[] = "Línea $line_number: Formato de fecha inválido ('$nacimiento_str', esperado: dd/mm/yyyy)";
                continue;
            }
        } else {
            $errors++;
            $error_details[] = "Línea $line_number: Fecha de nacimiento vacía";
            continue;
        }
        
        // Validar fecha
        $date_check = DateTime::createFromFormat('Y-m-d', $fecha_nacimiento);
        if (!$date_check || $date_check->format('Y-m-d') !== $fecha_nacimiento) {
            $errors++;
            $error_details[] = "Línea $line_number: Fecha inválida ('$fecha_nacimiento')";
            continue;
        }
        
        // Intentar insertar
        try {
            $result = $stmt->execute([$ci, $nombre_completo, $fecha_nacimiento, $sexo, $unidad]);
            
            // Verificar si fue INSERT o UPDATE
            $check = $pdo->query("SELECT COUNT(*) FROM preinscriptos WHERE ci = '$ci'")->fetchColumn();
            if ($result) {
                // Verificar si ya existía
                $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM preinscriptos WHERE ci = ? AND fecha_creacion != fecha_actualizacion");
                $stmt_check->execute([$ci]);
                if ($stmt_check->fetchColumn() > 0) {
                    $updated++;
                } else {
                    $imported++;
                }
            }
        } catch (PDOException $e) {
            $errors++;
            $error_details[] = "Línea $line_number: Error de base de datos - " . $e->getMessage();
        }
        
        // Mostrar progreso cada 50 líneas
        if ($line_number % 50 === 0) {
            echo "Procesadas $line_number líneas...\n";
        }
    }
    
    fclose($handle);
    
    // Eliminar archivo temporal
    if (file_exists($temp_file)) {
        unlink($temp_file);
    }
    
    // Commit transacción
    $pdo->commit();
    
    echo "\n========================================\n";
    echo "✅ Importación completada!\n";
    echo "========================================\n\n";
    echo "Resumen:\n";
    echo "  - Líneas procesadas: " . ($line_number - 1) . "\n";
    echo "  - Registros importados: $imported\n";
    echo "  - Registros actualizados: $updated\n";
    echo "  - Errores: $errors\n\n";
    
    if ($errors > 0 && count($error_details) > 0) {
        echo "Detalles de errores:\n";
        foreach (array_slice($error_details, 0, 20) as $error) {
            echo "  - $error\n";
        }
        if (count($error_details) > 20) {
            echo "  ... y " . (count($error_details) - 20) . " errores más.\n";
        }
        echo "\n";
    }
    
    // Verificar total en base de datos
    $total = $pdo->query("SELECT COUNT(*) FROM preinscriptos")->fetchColumn();
    echo "Total de registros en la tabla 'preinscriptos': $total\n";
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "========================================\n";
    exit(1);
}
?>

