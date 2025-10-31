<?php
/**
 * Endpoint para cargar preinscriptos desde archivo CSV
 * Sistema QUIRA
 */

// Configurar zona horaria
date_default_timezone_set('America/Asuncion');

// Evitar que se muestren errores directamente
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Iniciar sesión y verificar login
session_start();
require_once 'config.php';

// Siempre devolver JSON (ya no usamos iframe)
header('Content-Type: application/json; charset=UTF-8');

// Función para enviar error JSON y salir
function enviarErrorJSON($mensaje, $codigo = 400) {
    http_response_code($codigo);
    echo json_encode([
        'success' => false,
        'message' => $mensaje,
        'insertados' => 0,
        'actualizados' => 0,
        'errores' => 0
    ]);
    exit;
}

// Verificar que el usuario esté logueado y sea SUPERADMIN
if (!isset($_SESSION['user_id']) || $_SESSION['rol'] !== 'SUPERADMIN') {
    enviarErrorJSON('Acceso denegado. Se requiere rol SUPERADMIN.', 403);
}

try {
    // Debug: Log de información del request
    error_log('DEBUG cargar_preinscriptos.php - REQUEST_METHOD: ' . ($_SERVER['REQUEST_METHOD'] ?? 'NO_METHOD'));
    error_log('DEBUG - Content-Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'NO_CONTENT_TYPE'));
    error_log('DEBUG - POST keys: ' . (empty($_POST) ? 'VACIO' : implode(', ', array_keys($_POST))));
    error_log('DEBUG - FILES keys: ' . (empty($_FILES) ? 'VACIO' : implode(', ', array_keys($_FILES))));
    error_log('DEBUG - FILES content: ' . print_r($_FILES, true));
    error_log('DEBUG - php://input length: ' . strlen(file_get_contents('php://input')));
    
    // Verificar que se haya enviado un archivo
    if (!isset($_FILES['archivo_csv'])) {
        $error_code = isset($_FILES['archivo_csv']['error']) ? $_FILES['archivo_csv']['error'] : 'NO_FILE';
        $error_msg = 'No se recibió ningún archivo. ';
        
        // Verificar límites de PHP
        $max_upload = ini_get('upload_max_filesize');
        $max_post = ini_get('post_max_size');
        $error_msg .= "Límites PHP: upload_max_filesize=$max_upload, post_max_size=$max_post. ";
        
        if ($error_code !== 'NO_FILE' && $error_code !== UPLOAD_ERR_OK) {
            $upload_errors = [
                UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE del formulario',
                UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
                UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
                UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
                UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
                UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la carga'
            ];
            $error_msg .= 'Código de error: ' . ($upload_errors[$error_code] ?? "Error desconocido ($error_code)");
        }
        
        throw new Exception($error_msg);
    }
    
    if ($_FILES['archivo_csv']['error'] !== UPLOAD_ERR_OK) {
        $error_code = $_FILES['archivo_csv']['error'];
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE => 'El archivo excede upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'El archivo excede MAX_FILE_SIZE del formulario',
            UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
            UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
            UPLOAD_ERR_NO_TMP_DIR => 'Falta el directorio temporal',
            UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo en disco',
            UPLOAD_ERR_EXTENSION => 'Una extensión PHP detuvo la carga'
        ];
        $error_msg = 'Error en la carga del archivo: ' . ($upload_errors[$error_code] ?? "Error desconocido ($error_code)");
        throw new Exception($error_msg);
    }

    $archivo = $_FILES['archivo_csv'];
    
    // Validar tipo de archivo
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new Exception('El archivo debe ser un CSV (.csv)');
    }

    // Leer contenido del archivo
    $content = file_get_contents($archivo['tmp_name']);
    
    // Remover BOM si existe
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    
    // Detectar delimitador (; o ,)
    $delimiter = ';';
    if (strpos($content, ',') !== false && substr_count($content, ',') > substr_count($content, ';')) {
        $delimiter = ',';
    }
    
    // Leer líneas del CSV
    $lines = explode("\n", $content);
    if (empty($lines)) {
        throw new Exception('El archivo CSV está vacío.');
    }
    
    // Leer y normalizar headers
    $headerLine = trim(array_shift($lines));
    $headerLine = preg_replace('/[^\x20-\x7E]/', '', $headerLine); // Remover caracteres no imprimibles
    $headers = array_map('trim', str_getcsv($headerLine, $delimiter));
    
    // Normalizar headers (minúsculas, sin espacios extras)
    $headers = array_map(function($h) {
        return strtolower(trim($h));
    }, $headers);
    
    // Mapeo de columnas esperadas
    $columnMap = [];
    foreach ($headers as $index => $header) {
        $normalized = strtolower(trim($header));
        if (in_array($normalized, ['ci', 'cedula', 'cédula'])) {
            $columnMap['ci'] = $index;
        } elseif (in_array($normalized, ['nombre completo', 'nombre_completo', 'nombre', 'nombres'])) {
            $columnMap['nombre_completo'] = $index;
        } elseif (in_array($normalized, ['nacimiento', 'fecha_nacimiento', 'fecha nacimiento', 'fechanac', 'fecha'])) {
            $columnMap['fecha_nacimiento'] = $index;
        } elseif (in_array($normalized, ['sexo', 'genero', 'género'])) {
            $columnMap['sexo'] = $index;
        } elseif (in_array($normalized, ['unidad'])) {
            $columnMap['unidad'] = $index;
        }
    }
    
    // Validar que se encontraron las columnas mínimas
    if (!isset($columnMap['ci'])) {
        throw new Exception('No se encontró la columna CI en el CSV. Columnas encontradas: ' . implode(', ', $headers));
    }
    
    if (!isset($columnMap['nombre_completo'])) {
        throw new Exception('No se encontró la columna NOMBRE COMPLETO en el CSV. Columnas encontradas: ' . implode(', ', $headers));
    }
    
    // Conectar a la base de datos
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
        // Crear la tabla si no existe
        $create_table_sql = "
            CREATE TABLE IF NOT EXISTS preinscriptos (
                id SERIAL PRIMARY KEY,
                ci VARCHAR(20) UNIQUE NOT NULL,
                nombre_completo VARCHAR(200) NOT NULL,
                fecha_nacimiento DATE,
                sexo VARCHAR(10),
                unidad VARCHAR(255),
                fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_preinscriptos_ci ON preinscriptos (ci);
        ";
        $pdo->exec($create_table_sql);
    }
    
    // Preparar statement para insertar/actualizar
    $stmt = $pdo->prepare("
        INSERT INTO preinscriptos (ci, nombre_completo, fecha_nacimiento, sexo, unidad, fecha_registro, fecha_actualizacion)
        VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ON CONFLICT (ci) DO UPDATE SET
            nombre_completo = EXCLUDED.nombre_completo,
            fecha_nacimiento = EXCLUDED.fecha_nacimiento,
            sexo = EXCLUDED.sexo,
            unidad = EXCLUDED.unidad,
            fecha_actualizacion = CURRENT_TIMESTAMP
    ");
    
    $insertados = 0;
    $actualizados = 0;
    $errores = 0;
    $errores_detalle = [];
    
    // Procesar cada línea del CSV
    foreach ($lines as $numero_linea => $linea) {
        $numero_linea++; // Para mostrar números de línea desde 1 (incluye header)
        $linea = trim($linea);
        
        if (empty($linea)) continue;
        
        // Saltar líneas que solo tienen delimitadores vacíos (ej: ";;;;")
        $campos_check = str_getcsv($linea, $delimiter);
        if (empty(array_filter($campos_check, function($c) { return !empty(trim($c)); }))) {
            continue; // Línea vacía o solo delimitadores
        }
        
        try {
            $campos = str_getcsv($linea, $delimiter);
            
            // Validar que hay suficientes columnas
            if (count($campos) < count($columnMap)) {
                throw new Exception("Faltan columnas en la línea $numero_linea");
            }
            
            // Extraer valores
            $ci = trim($campos[$columnMap['ci']] ?? '');
            $nombre_completo = trim($campos[$columnMap['nombre_completo']] ?? '');
            $fecha_nacimiento = isset($columnMap['fecha_nacimiento']) ? trim($campos[$columnMap['fecha_nacimiento']] ?? '') : null;
            $sexo_raw = isset($columnMap['sexo']) ? trim($campos[$columnMap['sexo']] ?? '') : null;
            $unidad_raw = isset($columnMap['unidad']) ? trim($campos[$columnMap['unidad']] ?? '') : null;
            
            // Validar CI
            if (empty($ci)) {
                throw new Exception("CI vacío en línea $numero_linea");
            }
            
            // Validar nombre completo
            if (empty($nombre_completo)) {
                throw new Exception("Nombre completo vacío en línea $numero_linea");
            }
            
            // Convertir sexo (H -> Hombre, M -> Mujer)
            $sexo = null;
            if (!empty($sexo_raw)) {
                $sexo_upper = strtoupper($sexo_raw);
                if ($sexo_upper === 'H' || $sexo_upper === 'HOMBRE') {
                    $sexo = 'Hombre';
                } elseif ($sexo_upper === 'M' || $sexo_upper === 'MUJER') {
                    $sexo = 'Mujer';
                } else {
                    $sexo = $sexo_raw; // Mantener valor original si no es H/M
                }
            }
            
            // Procesar fecha de nacimiento
            $fecha_nacimiento_db = null;
            if (!empty($fecha_nacimiento)) {
                // Intentar varios formatos de fecha
                $fecha_formats = ['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
                $fecha_valida = false;
                
                foreach ($fecha_formats as $format) {
                    $date = DateTime::createFromFormat($format, $fecha_nacimiento);
                    if ($date && $date->format($format) === $fecha_nacimiento) {
                        $fecha_nacimiento_db = $date->format('Y-m-d');
                        $fecha_valida = true;
                        break;
                    }
                }
                
                // Si no coincide con ningún formato, intentar parsear directamente
                if (!$fecha_valida) {
                    $timestamp = strtotime($fecha_nacimiento);
                    if ($timestamp !== false) {
                        $fecha_nacimiento_db = date('Y-m-d', $timestamp);
                    }
                }
            }
            
            // Procesar unidad (mantener el valor original, sin convertir a mayúsculas)
            $unidad = $unidad_raw;
            
            // Limpiar comillas dobles que puedan estar en la unidad
            if (!empty($unidad)) {
                // Remover comillas externas
                if ($unidad[0] === '"' && substr($unidad, -1) === '"') {
                    $unidad = substr($unidad, 1, -1);
                }
                // Reemplazar comillas escapadas ("") por comillas simples
                $unidad = str_replace('""', '"', $unidad);
            }
            
            // Verificar si el registro ya existe
            $stmt_check = $pdo->prepare("SELECT id FROM preinscriptos WHERE ci = ?");
            $stmt_check->execute([$ci]);
            $existe = $stmt_check->fetchColumn();
            
            // Insertar o actualizar
            $stmt->execute([
                $ci,
                $nombre_completo,
                $fecha_nacimiento_db,
                $sexo,
                $unidad
            ]);
            
            if ($existe) {
                $actualizados++;
            } else {
                $insertados++;
            }
            
        } catch (Exception $e) {
            $errores++;
            $errores_detalle[] = "Línea $numero_linea: " . $e->getMessage();
            
            // Limitar número de errores reportados para no sobrecargar la respuesta
            if ($errores > 50) {
                break;
            }
        }
    }
    
    $mensaje = '';
    if ($errores > 0) {
        $mensaje = "Se encontraron $errores error(es). " . implode('; ', array_slice($errores_detalle, 0, 10));
        if ($errores > 10) {
            $mensaje .= " (y " . ($errores - 10) . " más...)";
        }
    }
    
    $resultado = [
        'success' => true,
        'insertados' => $insertados,
        'actualizados' => $actualizados,
        'errores' => $errores,
        'mensaje' => $mensaje,
        'total_procesados' => $insertados + $actualizados
    ];
    
    // Devolver respuesta JSON
    echo json_encode($resultado);
    
} catch (Exception $e) {
    enviarErrorJSON($e->getMessage(), 400);
} catch (Error $e) {
    // Capturar errores fatales de PHP también
    enviarErrorJSON('Error del servidor: ' . $e->getMessage(), 500);
}

