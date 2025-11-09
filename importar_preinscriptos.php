<?php
/**
 * Script para importar preinscriptos desde archivo CSV
 * Uso: php importar_preinscriptos.php [ruta_al_csv]
 * Si no se especifica ruta, busca preinsc.csv en el directorio actual
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

// Incluir configuración
require_once 'config.php';

// Obtener ruta del archivo CSV desde argumento de línea de comandos o usar default
$csv_file = $argv[1] ?? 'preinsc.csv';

// Verificar que el archivo existe
if (!file_exists($csv_file)) {
    die("❌ Error: El archivo '$csv_file' no existe.\n");
}

echo "📂 Leyendo archivo: $csv_file\n";

// Leer contenido del archivo
$content = file_get_contents($csv_file);
if ($content === false) {
    die("❌ Error: No se pudo leer el archivo '$csv_file'.\n");
}

// Remover BOM si existe
$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

// Detectar delimitador (; o ,)
$has_semicolon = strpos($content, ';') !== false;
$has_comma = strpos($content, ',') !== false;

$delimiter = ';'; // Default
if ($has_semicolon && $has_comma) {
    // Contar ocurrencias de cada uno
    $semicolon_count = substr_count($content, ';');
    $comma_count = substr_count($content, ',');
    $delimiter = $semicolon_count >= $comma_count ? ';' : ',';
} elseif ($has_comma && !$has_semicolon) {
    $delimiter = ',';
}

echo "📋 Delimitador detectado: '$delimiter'\n";

// Dividir en líneas
$lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $content));
if (count($lines) === 0) {
    die("❌ Error: El archivo CSV está vacío.\n");
}

// Encontrar la línea de header
$header_index = -1;
for ($i = 0; $i < min(count($lines), 10); $i++) {
    $line = trim($lines[$i]);
    if (stripos($line, 'ci') !== false && (stripos($line, 'nombre') !== false || stripos($line, 'completo') !== false)) {
        $header_index = $i;
        break;
    }
}

if ($header_index === -1) {
    die("❌ Error: No se encontró el header del CSV. Se espera: CI, NOMBRE COMPLETO, NACIMIENTO, SEXO, UNIDAD\n");
}

echo "✅ Header encontrado en línea " . ($header_index + 1) . "\n";

// Función para parsear línea CSV respetando comillas (debe estar antes de usarse)
function parseCSVLine($line, $delimiter) {
    $fields = [];
    $current_field = '';
    $inside_quotes = false;
    $i = 0;
    
    while ($i < strlen($line)) {
        $char = $line[$i];
        
        if ($char === '"') {
            // Manejar comillas escapadas ("")
            if ($i + 1 < strlen($line) && $line[$i + 1] === '"' && $inside_quotes) {
                // Comilla escapada: agregar una comilla al campo
                $current_field .= '"';
                $i += 2;
                continue;
            }
            // Es una comilla delimitadora: cambiar estado pero NO agregar al campo
            $inside_quotes = !$inside_quotes;
        } elseif ($char === $delimiter && !$inside_quotes) {
            // Delimitador fuera de comillas: fin del campo
            $fields[] = $current_field;
            $current_field = '';
        } else {
            // Cualquier otro carácter: agregarlo al campo actual
            $current_field .= $char;
        }
        $i++;
    }
    
    // Agregar último campo (sin trim, lo haremos después si es necesario)
    if ($current_field || count($fields) > 0) {
        $fields[] = $current_field;
    }
    
    return $fields;
}

// Leer headers usando la función parseCSVLine para respetar comillas
$header_line = trim($lines[$header_index]);
$headers = parseCSVLine($header_line, $delimiter);
$headers = array_map(function($h) {
    return strtolower(trim($h, '"'));
}, $headers);

// Mapear columnas (usar comparación más estricta para evitar falsos positivos)
// Primero mostrar qué headers tenemos para debug
echo "📋 Headers encontrados:\n";
foreach ($headers as $idx => $h) {
    echo "   [$idx] = '$h'\n";
}

$column_map = [];
foreach ($headers as $index => $header) {
    $header_clean = strtolower(trim($header));
    
    // CI: debe ser exactamente "ci" (no parte de "nacimiento")
    if ($header_clean === 'ci' && !isset($column_map['ci'])) {
        $column_map['ci'] = $index;
    }
    // CEDULA/CÉDULA
    elseif (($header_clean === 'cedula' || $header_clean === 'cédula') && !isset($column_map['ci'])) {
        $column_map['ci'] = $index;
    }
    // NOMBRE COMPLETO: debe contener ambas palabras
    elseif (stripos($header_clean, 'nombre') !== false && stripos($header_clean, 'completo') !== false && !isset($column_map['nombre_completo'])) {
        $column_map['nombre_completo'] = $index;
    }
    // NACIMIENTO: buscar por "nacimiento" específicamente
    elseif (stripos($header_clean, 'nacimiento') !== false && !isset($column_map['fecha_nacimiento'])) {
        $column_map['fecha_nacimiento'] = $index;
    }
    // SEXO
    elseif (($header_clean === 'sexo' || $header_clean === 'genero' || $header_clean === 'género') && !isset($column_map['sexo'])) {
        $column_map['sexo'] = $index;
    }
    // UNIDAD
    elseif (stripos($header_clean, 'unidad') !== false && !isset($column_map['unidad'])) {
        $column_map['unidad'] = $index;
    }
}

if (!isset($column_map['ci']) || !isset($column_map['nombre_completo'])) {
    die("❌ Error: Columna esperada 'CI' o 'NOMBRE COMPLETO' no encontrada en el CSV.\n");
}

$max_col = max(array_values($column_map));

$interpreta_siglas_mf = false;
if (isset($column_map['sexo'])) {
    for ($i = $header_index + 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line) || preg_match('/^[\s' . preg_quote($delimiter, '/') . ']+$/', $line)) {
            continue;
        }
        $fields_preview = parseCSVLine($line, $delimiter);
        if (count($fields_preview) <= $max_col) {
            continue;
        }
        $sexo_preview = isset($column_map['sexo']) && isset($fields_preview[$column_map['sexo']])
            ? strtoupper(trim(trim($fields_preview[$column_map['sexo']]), "\"' "))
            : '';
        if ($sexo_preview === 'F') {
            $interpreta_siglas_mf = true;
            break;
        }
    }
}
if ($interpreta_siglas_mf) {
    echo "ℹ️  El archivo utiliza siglas 'M/F' (Masculino/Femenino). Se interpretará 'M' como masculino.\n";
}

echo "✅ Columnas mapeadas correctamente\n";
echo "📋 Mapa de columnas:\n";
foreach ($column_map as $field => $index) {
    echo "   • $field: índice $index\n";
}

// Conectar a la base de datos
try {
    $pdo = getDBConnection();
    echo "✅ Conectado a la base de datos\n";
    
    // Verificar que la tabla existe, si no crearla
    $check_table = $pdo->query("
        SELECT EXISTS (
            SELECT FROM information_schema.tables 
            WHERE table_name = 'preinscriptos'
        )
    ")->fetchColumn();
    
    if (!$check_table) {
        echo "📝 Creando tabla 'preinscriptos'...\n";
        $pdo->exec("
            CREATE TABLE preinscriptos (
                id SERIAL PRIMARY KEY,
                ci VARCHAR(20) UNIQUE NOT NULL,
                nombre_completo VARCHAR(200) NOT NULL,
                fecha_nacimiento DATE,
                sexo VARCHAR(10),
                unidad VARCHAR(255),
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX idx_preinscriptos_ci ON preinscriptos (ci);
        ");
        echo "✅ Tabla creada\n";
    } else {
        // Verificar y corregir estructura de la tabla si es necesario
        echo "📋 Verificando estructura de la tabla...\n";
        try {
            $check_sexo = $pdo->query("
                SELECT character_maximum_length 
                FROM information_schema.columns 
                WHERE table_name = 'preinscriptos' 
                AND column_name = 'sexo'
            ")->fetchColumn();
            
            if ($check_sexo !== null && $check_sexo < 10) {
                echo "🔧 Actualizando columna 'sexo' de VARCHAR($check_sexo) a VARCHAR(10)...\n";
                $pdo->exec("ALTER TABLE preinscriptos ALTER COLUMN sexo TYPE VARCHAR(10)");
                echo "✅ Columna actualizada\n";
            }
        } catch (Exception $e) {
            echo "⚠️ No se pudo verificar/actualizar la estructura: " . $e->getMessage() . "\n";
        }
    }
    
    // Preparar statement para INSERT (ignorar duplicados)
    $stmt = $pdo->prepare("
        INSERT INTO preinscriptos (ci, nombre_completo, fecha_nacimiento, sexo, unidad, fecha_actualizacion)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ON CONFLICT (ci) DO NOTHING
    ");
    
    // Contadores
    $total_lines = count($lines);
    $processed = 0;
    $inserted = 0;
    $errors = 0;
    
    echo "\n📊 Procesando líneas de datos...\n";
    
    // Procesar líneas de datos (después del header)
    for ($i = $header_index + 1; $i < $total_lines; $i++) {
        $line = trim($lines[$i]);
        
        // Saltar líneas vacías o que solo contienen delimitadores
        if (empty($line) || preg_match('/^[\s' . preg_quote($delimiter, '/') . ']+$/', $line)) {
            continue;
        }
        
        // Parsear línea
        $fields = parseCSVLine($line, $delimiter);
        
        // Validar que tenemos suficientes campos
        if (count($fields) <= $max_col) {
            $errors++;
            continue;
        }
        
        // Extraer datos usando el mapa de columnas
        $ci = isset($column_map['ci']) && isset($fields[$column_map['ci']]) ? trim($fields[$column_map['ci']]) : '';
        $nombre_completo = isset($column_map['nombre_completo']) && isset($fields[$column_map['nombre_completo']]) ? trim($fields[$column_map['nombre_completo']]) : '';
        $fecha_nacimiento_raw = isset($column_map['fecha_nacimiento']) && isset($fields[$column_map['fecha_nacimiento']]) ? trim($fields[$column_map['fecha_nacimiento']]) : '';
        $sexo_raw = isset($column_map['sexo']) && isset($fields[$column_map['sexo']]) ? trim($fields[$column_map['sexo']]) : '';
        $unidad_raw = isset($column_map['unidad']) && isset($fields[$column_map['unidad']]) ? trim($fields[$column_map['unidad']]) : '';
        
        // Validar CI y nombre completo (requeridos)
        if (empty($ci) || empty($nombre_completo)) {
            // Debug: mostrar qué campos tenemos
            if ($i === $header_index + 1) {
                echo "  🔍 Debug línea " . ($i + 1) . ": campos parseados: " . count($fields) . "\n";
                echo "     Mapa de columnas: CI={$column_map['ci']}, NOMBRE={$column_map['nombre_completo']}\n";
                echo "     Valores: CI='$ci', NOMBRE='$nombre_completo'\n";
            }
            $errors++;
            continue;
        }
        
        // Validar que CI sea numérico (no una fecha)
        if (!preg_match('/^\d+$/', $ci)) {
            $errors++;
            if ($i === $header_index + 1) {
                echo "  ⚠️ Línea " . ($i + 1) . ": CI no es numérico: '$ci' (probablemente las columnas están desalineadas)\n";
            }
            continue;
        }
        
        // Limpiar comillas del nombre y unidad
        $nombre_completo = trim($nombre_completo, '"');
        $unidad_raw = trim($unidad_raw, '"');
        
        // Convertir sexo a H/M (el constraint de la tabla solo acepta H o M)
        $sexo = null;
        if (!empty($sexo_raw)) {
            $sexo_upper = strtoupper(trim(trim($sexo_raw), "\"' "));
            if ($sexo_upper === 'H' || strpos($sexo_upper, 'HOMB') === 0) {
                $sexo = 'H';
            } elseif ($sexo_upper === 'F' || strpos($sexo_upper, 'FEM') === 0) {
                $sexo = 'M';
            } elseif (strpos($sexo_upper, 'MASC') === 0) {
                $sexo = 'H';
            } elseif (strpos($sexo_upper, 'MUJER') === 0) {
                $sexo = 'M';
            } elseif ($sexo_upper === 'M') {
                $sexo = $interpreta_siglas_mf ? 'H' : 'M';
            } else {
                $primer_caracter = substr($sexo_upper, 0, 1);
                if ($primer_caracter === 'H') {
                    $sexo = 'H';
                } elseif ($primer_caracter === 'M' || $primer_caracter === 'F') {
                    $sexo = $primer_caracter === 'F' ? 'M' : ($interpreta_siglas_mf ? 'H' : 'M');
                } else {
                    $sexo = null;
                }
            }
        }
        
        // Procesar unidad
        // El CSV tiene: " Colegio de Policía ""Sgto. Aydte. JOSE MERLO SARAVIA"" "
        // parseCSVLine devuelve: " Colegio de Policía ""Sgto. Aydte. JOSE MERLO SARAVIA"" " (sin comillas externas)
        $unidad = trim($unidad_raw); // Eliminar espacios
        $unidad = str_replace('""', '"', $unidad); // Convertir "" a "
        
        // Si la unidad tiene comilla de apertura pero no de cierre, agregarla
        if (strpos($unidad, '"') !== false && substr($unidad, -1) !== '"') {
            // Contar comillas - si es impar, falta una de cierre
            $quote_count = substr_count($unidad, '"');
            if ($quote_count % 2 !== 0) {
                $unidad = $unidad . '"';
            }
        }
        
        // Procesar fecha de nacimiento (múltiples formatos)
        $fecha_nacimiento = null;
        if (!empty($fecha_nacimiento_raw)) {
            // Intentar múltiples formatos
            $date_formats = [
                'd/m/Y', 'd-m-Y', 'Y-m-d', 'Y/m/d',
                'd/m/y', 'd-m-y', 'y-m-d', 'y/m/d'
            ];
            
            foreach ($date_formats as $format) {
                $date_obj = DateTime::createFromFormat($format, $fecha_nacimiento_raw);
                if ($date_obj !== false) {
                    $fecha_nacimiento = $date_obj->format('Y-m-d');
                    break;
                }
            }
            
            // Si no funcionó, intentar con strtotime
            if ($fecha_nacimiento === null) {
                $timestamp = strtotime($fecha_nacimiento_raw);
                if ($timestamp !== false) {
                    $fecha_nacimiento = date('Y-m-d', $timestamp);
                }
            }
        }
        
        try {
            $stmt->execute([$ci, $nombre_completo, $fecha_nacimiento, $sexo, $unidad]);
            
            // rowCount() devuelve el número de filas afectadas
            // Si es > 0, significa que se insertó (duplicados no afectan filas)
            if ($stmt->rowCount() > 0) {
                $inserted++;
            }
            
            $processed++;
            
            if ($processed % 50 === 0) {
                echo "  Procesados: $processed\n";
            }
        } catch (PDOException $e) {
            $errors++;
            echo "  ⚠️ Error en línea " . ($i + 1) . " (CI: $ci): " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n";
    echo "✅ Importación completada\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "📊 Resumen:\n";
    echo "  • Total procesados: $processed\n";
    echo "  • Insertados (nuevos): $inserted\n";
    if ($errors > 0) {
        echo "  • Errores: $errors\n";
    }
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage() . "\n");
}

