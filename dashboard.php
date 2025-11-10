<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar si necesita cambiar contraseña
if (isset($_SESSION['primer_inicio']) && $_SESSION['primer_inicio'] === true) {
    header('Location: cambiar_password_obligatorio.php');
    exit;
}

// Función para seleccionar imagen aleatoria del header
function getRandomHeaderImage() {
    $bgHeaderDir = 'assets/media/bg_header/';
    if (is_dir($bgHeaderDir)) {
        $images = glob($bgHeaderDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!empty($images)) {
            return $images[array_rand($images)];
        }
    }
    // Fallback a la imagen original si no hay imágenes en la carpeta
    return 'assets/media/various/bg_header.png';
}

$randomHeaderImage = getRandomHeaderImage();

// Configuración de la base de datos
$host = 'localhost';
$dbname = 'sistema_postulantes';
$username = 'postgres';
$password = 'Postgres2025!';

try {
    $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Obtener estadísticas generales con manejo de errores
try {
    $stats = $pdo->query("SELECT * FROM mv_estadisticas_postulantes")->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si falla la vista materializada, usar consulta directa
    $stats = $pdo->query("
        SELECT 
            COUNT(*) as total_postulantes,
            COUNT(*) FILTER (WHERE DATE(fecha_registro) = CURRENT_DATE) as postulantes_hoy,
            COUNT(*) FILTER (WHERE fecha_registro >= CURRENT_DATE - INTERVAL '7 days') as postulantes_semana,
            COUNT(*) FILTER (WHERE fecha_registro >= CURRENT_DATE - INTERVAL '30 days') as postulantes_mes,
            COUNT(DISTINCT registrado_por) as total_registradores
        FROM postulantes
    ")->fetch(PDO::FETCH_ASSOC);
}

$usuarios_count = $pdo->query("SELECT COUNT(*) FROM usuarios")->fetchColumn();
$aparatos_count = $pdo->query("SELECT COUNT(*) FROM aparatos_biometricos")->fetchColumn();
$unidades_count = $pdo->query("SELECT COUNT(*) FROM unidades")->fetchColumn();

// Gestión de usuarios - solo para ADMIN y SUPERADMIN
$mensaje_usuarios = '';
$tipo_mensaje_usuarios = '';

// Verificar si hay mensajes en sesión
if (isset($_SESSION['mensaje_usuarios'])) {
    $mensaje_usuarios = $_SESSION['mensaje_usuarios'];
    $tipo_mensaje_usuarios = $_SESSION['tipo_mensaje_usuarios'];
    // Limpiar mensajes de sesión después de mostrarlos
    unset($_SESSION['mensaje_usuarios']);
    unset($_SESSION['tipo_mensaje_usuarios']);
}

if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear_usuario':
                $usuario = trim($_POST['usuario']);
                $nombre = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $grado = trim($_POST['grado']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $rol = $_POST['rol'];
                $password = trim($_POST['password']);
                
                // Validaciones básicas
                if (empty($usuario) || empty($nombre) || empty($apellido) || empty($password)) {
                    throw new Exception('Los campos usuario, nombre, apellido y contraseña son obligatorios');
                }
                
                // Verificar si el usuario ya existe
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
                $stmt->execute([$usuario]);
                if ($stmt->fetch()) {
                    throw new Exception('El nombre de usuario ya existe');
                }
                
                // Crear usuario
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (usuario, nombre, apellido, grado, cedula, telefono, rol, contrasena, primer_inicio, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, true, NOW())");
                $stmt->execute([$usuario, $nombre, $apellido, $grado, $cedula, $telefono, $rol, $password_hash]);
                
                $_SESSION['mensaje_usuarios'] = 'Usuario creado exitosamente';
                $_SESSION['tipo_mensaje_usuarios'] = 'success';
                break;
                
            case 'editar_usuario':
                $id = (int)$_POST['id'];
                $usuario = trim($_POST['usuario']);
                $nombre = trim($_POST['nombre']);
                $apellido = trim($_POST['apellido']);
                $grado = trim($_POST['grado']);
                $cedula = trim($_POST['cedula']);
                $telefono = trim($_POST['telefono']);
                $rol = $_POST['rol'];
                
                // Validaciones básicas
                if (empty($usuario) || empty($nombre) || empty($apellido)) {
                    throw new Exception('Los campos usuario, nombre y apellido son obligatorios');
                }
                
                // Verificar si el usuario ya existe (excluyendo el actual)
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $stmt->execute([$usuario, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('El nombre de usuario ya existe');
                }
                
                // Actualizar usuario
                $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, nombre = ?, apellido = ?, grado = ?, cedula = ?, telefono = ?, rol = ? WHERE id = ?");
                $stmt->execute([$usuario, $nombre, $apellido, $grado, $cedula, $telefono, $rol, $id]);
                
                $_SESSION['mensaje_usuarios'] = 'Usuario actualizado exitosamente';
                $_SESSION['tipo_mensaje_usuarios'] = 'success';
                break;
                
            case 'eliminar_usuario':
                $id = (int)$_POST['id'];
                
                // No permitir eliminar el usuario actual
                if ($id == $_SESSION['user_id']) {
                    throw new Exception('No puedes eliminar tu propio usuario');
                }
                
                // Eliminar usuario
                $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['mensaje_usuarios'] = 'Usuario eliminado exitosamente';
                $_SESSION['tipo_mensaje_usuarios'] = 'success';
                break;
                
            case 'cambiar_password':
                $id = (int)$_POST['id'];
                $password = trim($_POST['password']);
                
                if (empty($password)) {
                    throw new Exception('La contraseña es obligatoria');
                }
                
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = ?, primer_inicio = true WHERE id = ?");
                $stmt->execute([$password_hash, $id]);
                
                $_SESSION['mensaje_usuarios'] = 'Contraseña restaurada exitosamente. El usuario deberá cambiarla en el próximo login.';
                $_SESSION['tipo_mensaje_usuarios'] = 'success';
                break;
                
            case 'crear_dispositivo':
                $nombre = trim($_POST['nombre']);
                $serial = trim($_POST['serial']);
                $ip_address = trim($_POST['ip_address']);
                $puerto = trim($_POST['puerto']);
                $estado = $_POST['estado'];
                $activo = 1; // Por defecto todos los dispositivos están activos
                
                // Validaciones básicas
                if (empty($nombre) || empty($serial) || empty($ip_address) || empty($puerto) || empty($estado)) {
                    throw new Exception('Los campos nombre, serial, IP, puerto y estado son obligatorios');
                }
                
                // Verificar si el dispositivo ya existe (por serial)
                $stmt = $pdo->prepare("SELECT id FROM aparatos_biometricos WHERE serial = ?");
                $stmt->execute([$serial]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un dispositivo con este número de serie');
                }
                
                // Crear dispositivo
                $stmt = $pdo->prepare("INSERT INTO aparatos_biometricos (nombre, serial, ip_address, puerto, estado, fecha_registro, activo) VALUES (?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([$nombre, $serial, $ip_address, $puerto, $estado, $activo]);
                
                $_SESSION['mensaje_dispositivos'] = 'Dispositivo creado exitosamente';
                $_SESSION['tipo_mensaje_dispositivos'] = 'success';
                break;
                
            case 'editar_dispositivo':
                $id = (int)$_POST['id'];
                $nombre = trim($_POST['nombre']);
                $serial = trim($_POST['serial']);
                $ip_address = trim($_POST['ip_address']);
                $puerto = trim($_POST['puerto']);
                $estado = $_POST['estado'];
                $activo = 1; // Por defecto todos los dispositivos están activos
                
                // Validaciones básicas
                if (empty($nombre) || empty($serial) || empty($ip_address) || empty($puerto) || empty($estado)) {
                    throw new Exception('Los campos nombre, serial, IP, puerto y estado son obligatorios');
                }
                
                // Verificar si el dispositivo ya existe (excluyendo el actual)
                $stmt = $pdo->prepare("SELECT id FROM aparatos_biometricos WHERE serial = ? AND id != ?");
                $stmt->execute([$serial, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un dispositivo con este número de serie');
                }
                
                // Actualizar dispositivo
                $stmt = $pdo->prepare("UPDATE aparatos_biometricos SET nombre = ?, serial = ?, ip_address = ?, puerto = ?, estado = ?, activo = ? WHERE id = ?");
                $stmt->execute([$nombre, $serial, $ip_address, $puerto, $estado, $activo, $id]);
                
                $_SESSION['mensaje_dispositivos'] = 'Dispositivo actualizado exitosamente';
                $_SESSION['tipo_mensaje_dispositivos'] = 'success';
                break;
                
            case 'eliminar_dispositivo':
                // Debug directo en la página
                $debug_info = "DEBUG: POST=" . print_r($_POST, true) . " | ID=" . ($_POST['id'] ?? 'NO_EXISTE') . " | REQUEST=" . print_r($_REQUEST, true);
                
                if (!isset($_POST['id'])) {
                    throw new Exception('Campo ID no encontrado. ' . $debug_info);
                }
                
                $id = (int)$_POST['id'];
                
                if ($id <= 0) {
                    throw new Exception('ID inválido: ' . $id . ' (original: ' . $_POST['id'] . '). ' . $debug_info);
                }
                
                // Verificar que el dispositivo existe
                $stmt = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos WHERE id = ?");
                $stmt->execute([$id]);
                $dispositivo = $stmt->fetch();
                
                if (!$dispositivo) {
                    throw new Exception('El dispositivo no existe');
                }
                
                // Iniciar transacción para eliminar dispositivo y preservar información
                $pdo->beginTransaction();
                
                try {
                    // Primero, guardar el nombre del dispositivo en los postulantes antes de desvincular
                    $stmt = $pdo->prepare("UPDATE postulantes SET aparato_nombre = ?, aparato_id = NULL WHERE aparato_id = ?");
                    $stmt->execute([$dispositivo['nombre'], $id]);
                    $postulantes_afectados = $stmt->rowCount();
                    
                    // Luego, eliminar el dispositivo
                    $stmt = $pdo->prepare("DELETE FROM aparatos_biometricos WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        $_SESSION['mensaje_dispositivos'] = 'Dispositivo "' . $dispositivo['nombre'] . '" eliminado exitosamente';
                        if ($postulantes_afectados > 0) {
                            $_SESSION['mensaje_dispositivos'] .= " (se preservó el nombre del dispositivo en $postulantes_afectados postulante(s))";
                        }
                        $_SESSION['tipo_mensaje_dispositivos'] = 'success';
                    } else {
                        throw new Exception('No se pudo eliminar el dispositivo');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            case 'crear_unidad':
                $nombre = trim($_POST['nombre']);
                $activa = isset($_POST['activa']) ? 1 : 0;
                
                // Validaciones básicas
                if (empty($nombre)) {
                    throw new Exception('El nombre de la unidad es obligatorio');
                }
                
                // Verificar si la unidad ya existe
                $stmt = $pdo->prepare("SELECT id FROM unidades WHERE LOWER(nombre) = LOWER(?)");
                $stmt->execute([$nombre]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe una unidad con este nombre');
                }
                
                // Crear unidad
                $stmt = $pdo->prepare("INSERT INTO unidades (nombre, activa) VALUES (?, ?)");
                $stmt->execute([$nombre, $activa]);
                
                $_SESSION['mensaje_unidades'] = 'Unidad creada exitosamente';
                $_SESSION['tipo_mensaje_unidades'] = 'success';
                break;
                
            case 'editar_unidad':
                $id = (int)$_POST['id'];
                $nombre = trim($_POST['nombre']);
                $activa = isset($_POST['activa']) ? 1 : 0;
                
                // Validaciones básicas
                if (empty($nombre)) {
                    throw new Exception('El nombre de la unidad es obligatorio');
                }
                
                // Verificar si la unidad ya existe (excluyendo la actual)
                $stmt = $pdo->prepare("SELECT id FROM unidades WHERE LOWER(nombre) = LOWER(?) AND id != ?");
                $stmt->execute([$nombre, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe una unidad con este nombre');
                }
                
                // Obtener el nombre anterior de la unidad para actualizar postulantes
                $stmt_anterior = $pdo->prepare("SELECT nombre FROM unidades WHERE id = ?");
                $stmt_anterior->execute([$id]);
                $nombre_anterior = $stmt_anterior->fetch()['nombre'];
                
                // Actualizar unidad
                $stmt = $pdo->prepare("UPDATE unidades SET nombre = ?, activa = ? WHERE id = ?");
                $stmt->execute([$nombre, $activa, $id]);
                
                // Actualizar postulantes que tengan el nombre anterior de la unidad
                if ($nombre_anterior !== $nombre) {
                    $stmt_postulantes = $pdo->prepare("UPDATE postulantes SET unidad = ? WHERE unidad = ?");
                    $stmt_postulantes->execute([$nombre, $nombre_anterior]);
                    $postulantes_actualizados = $stmt_postulantes->rowCount();
                    
                    if ($postulantes_actualizados > 0) {
                        $_SESSION['mensaje_unidades'] = "Unidad actualizada exitosamente. Se actualizaron {$postulantes_actualizados} postulantes de '{$nombre_anterior}' a '{$nombre}'.";
                    } else {
                        $_SESSION['mensaje_unidades'] = "Unidad actualizada exitosamente. No había postulantes asociados a '{$nombre_anterior}'.";
                    }
                } else {
                    $_SESSION['mensaje_unidades'] = 'Unidad actualizada exitosamente';
                }
                $_SESSION['tipo_mensaje_unidades'] = 'success';
                break;
                
            case 'eliminar_unidad':
                $id = (int)$_POST['id'];
                
                if ($id <= 0) {
                    throw new Exception('ID de unidad inválido');
                }
                
                // Verificar que la unidad existe
                $stmt = $pdo->prepare("SELECT id, nombre FROM unidades WHERE id = ?");
                $stmt->execute([$id]);
                $unidad = $stmt->fetch();
                
                if (!$unidad) {
                    throw new Exception('La unidad no existe');
                }
                
                // Verificar si hay postulantes asociados
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM postulantes WHERE unidad = ?");
                $stmt->execute([$unidad['nombre']]);
                $postulantes_count = $stmt->fetch()['count'];
                
                if ($postulantes_count > 0) {
                    throw new Exception('No se puede eliminar la unidad porque tiene ' . $postulantes_count . ' postulante(s) asociado(s)');
                }
                
                // Eliminar unidad
                $stmt = $pdo->prepare("DELETE FROM unidades WHERE id = ?");
                $stmt->execute([$id]);
                
                $_SESSION['mensaje_unidades'] = 'Unidad "' . $unidad['nombre'] . '" eliminada exitosamente';
                $_SESSION['tipo_mensaje_unidades'] = 'success';
                break;
        }
    } catch (Exception $e) {
        // Determinar si es un error de dispositivos, usuarios o unidades
        if (strpos($accion, 'dispositivo') !== false) {
            $_SESSION['mensaje_dispositivos'] = $e->getMessage();
            $_SESSION['tipo_mensaje_dispositivos'] = 'danger';
        } elseif (strpos($accion, 'unidad') !== false) {
            $_SESSION['mensaje_unidades'] = $e->getMessage();
            $_SESSION['tipo_mensaje_unidades'] = 'danger';
        } else {
            $_SESSION['mensaje_usuarios'] = $e->getMessage();
            $_SESSION['tipo_mensaje_usuarios'] = 'danger';
        }
    }
    
    // Redirigir para evitar reenvío del formulario
    if (strpos($accion, 'dispositivo') !== false) {
        header('Location: dashboard.php#dispositivos');
    } elseif (strpos($accion, 'unidad') !== false) {
        header('Location: dashboard.php#unidades');
    } else {
        header('Location: dashboard.php#usuarios');
    }
    exit;
}

// Obtener datos de usuarios si el usuario tiene permisos
if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])) {
    $usuarios = $pdo->query("SELECT id, usuario, nombre, apellido, rol, grado, cedula, telefono, fecha_creacion FROM usuarios ORDER BY fecha_creacion DESC")->fetchAll();
    $roles = $pdo->query("SELECT nombre FROM roles ORDER BY nombre")->fetchAll();
}

// Gestión de dispositivos biométricos - solo para ADMIN y SUPERADMIN
$mensaje_dispositivos = '';
$tipo_mensaje_dispositivos = '';

// Verificar si hay mensajes en sesión
if (isset($_SESSION['mensaje_dispositivos'])) {
    $mensaje_dispositivos = $_SESSION['mensaje_dispositivos'];
    $tipo_mensaje_dispositivos = $_SESSION['tipo_mensaje_dispositivos'];
    // Limpiar mensajes de sesión después de mostrarlos
    unset($_SESSION['mensaje_dispositivos']);
    unset($_SESSION['tipo_mensaje_dispositivos']);
}

// Gestión de unidades - solo para ADMIN y SUPERADMIN
$mensaje_unidades = '';
$tipo_mensaje_unidades = '';

// Verificar si hay mensajes en sesión
if (isset($_SESSION['mensaje_unidades'])) {
    $mensaje_unidades = $_SESSION['mensaje_unidades'];
    $tipo_mensaje_unidades = $_SESSION['tipo_mensaje_unidades'];
    // Limpiar mensajes de sesión después de mostrarlos
    unset($_SESSION['mensaje_unidades']);
    unset($_SESSION['tipo_mensaje_unidades']);
}


// Obtener datos de dispositivos si el usuario tiene permisos
if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN', 'SUPERVISOR'])) {
    $dispositivos = $pdo->query("
        SELECT 
            a.id, a.nombre, a.serial, a.ip_address, a.puerto, 
            a.estado, a.fecha_registro, a.activo,
            COUNT(p.id) as postulantes_count
        FROM aparatos_biometricos a
        LEFT JOIN postulantes p ON a.id = p.aparato_id
        GROUP BY a.id, a.nombre, a.serial, a.ip_address, a.puerto, a.estado, a.fecha_registro, a.activo
        ORDER BY a.fecha_registro DESC
    ")->fetchAll();
    $estados_dispositivos = ['ACTIVO', 'INACTIVO', 'PRUEBA', 'MANTENIMIENTO'];
}

// Parámetros de paginación y filtros
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 15;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtro_fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : '';
$filtro_fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : '';
$filtro_unidad = isset($_GET['unidad']) ? $_GET['unidad'] : '';
$filtro_aparato = isset($_GET['aparato']) ? $_GET['aparato'] : '';
$filtro_dedo = isset($_GET['dedo']) ? $_GET['dedo'] : '';

// Construir la consulta base
$where_conditions = [];
$params = [];

// Búsqueda con normalización de acentos pero sin complejidad
if (!empty($search)) {
    $search_param = "%$search%";
    
    // Búsqueda con acentos y sin acentos
    $where_conditions[] = "(
        LOWER(COALESCE(p.nombre_completo, p.nombre || ' ' || p.apellido)) LIKE LOWER(?) OR 
        p.cedula LIKE ? OR
        -- Búsqueda sin acentos
        LOWER(TRANSLATE(COALESCE(p.nombre_completo, p.nombre || ' ' || p.apellido), 'áéíóúÁÉÍÓÚñÑ', 'aeiouAEIOUnN')) LIKE LOWER(?)
    )";
    
    // 3 parámetros: nombre completo + cédula + nombre completo sin acentos
    $params[] = $search_param; // nombre completo original
    $params[] = $search_param; // cédula
    $params[] = $search_param; // nombre completo sin acentos
}

// Filtros
if (!empty($filtro_fecha_desde)) {
    $where_conditions[] = "DATE(p.fecha_registro) >= ?";
    $params[] = $filtro_fecha_desde;
}

if (!empty($filtro_fecha_hasta)) {
    $where_conditions[] = "DATE(p.fecha_registro) <= ?";
    $params[] = $filtro_fecha_hasta;
}

if (!empty($filtro_unidad)) {
    $where_conditions[] = "p.unidad = ?";
    $params[] = $filtro_unidad;
}

if (!empty($filtro_aparato)) {
    if (strpos($filtro_aparato, 'eliminado_') === 0) {
        // Aparato eliminado - buscar por nombre
        $aparato_nombre_encoded = substr($filtro_aparato, 10);
        $aparato_nombre = base64_decode($aparato_nombre_encoded);
        $where_conditions[] = "p.aparato_nombre = ?";
        $params[] = $aparato_nombre;
        error_log("DEBUG: Filtro aparato eliminado - nombre: $aparato_nombre");
    } else {
        // Aparato activo - buscar por ID O por nombre si el aparato fue eliminado
        $where_conditions[] = "(p.aparato_id = ? OR p.aparato_nombre = (SELECT nombre FROM aparatos_biometricos WHERE id = ?))";
        $params[] = $filtro_aparato;
        $params[] = $filtro_aparato;
        error_log("DEBUG: Filtro aparato activo - ID: $filtro_aparato (búsqueda inteligente)");
    }
}

if (!empty($filtro_dedo)) {
    $where_conditions[] = "p.dedo_registrado = ?";
    $params[] = $filtro_dedo;
}

// Construir WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    error_log("DEBUG: WHERE conditions: " . implode(' AND ', $where_conditions));
}
error_log("DEBUG: Final WHERE clause: $where_clause");

// Consulta para contar total de registros
$count_sql = "
    SELECT COUNT(*) as total
    FROM postulantes p
    LEFT JOIN aparatos_biometricos a ON p.aparato_id = a.id
    LEFT JOIN usuarios u ON p.usuario_registrador = u.id
    LEFT JOIN usuarios c ON p.capturador_id = c.id
    $where_clause
";

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $per_page);

// Consulta principal con paginación
$offset = ($page - 1) * $per_page;
$postulantes_sql = "
    SELECT 
        p.id, 
        COALESCE(p.nombre_completo, p.nombre || ' ' || p.apellido) as nombre_completo,
        p.cedula, p.fecha_nacimiento, p.telefono,
        p.fecha_registro, p.observaciones, p.edad, p.unidad, p.dedo_registrado,
        p.registrado_por, p.aparato_id, p.usuario_ultima_edicion, p.fecha_ultima_edicion,
        p.sexo, p.aparato_nombre,
        a.nombre as aparato_nombre_actual,
        u.usuario as usuario_registrador_nombre,
        CONCAT(COALESCE(c.grado, ''), ' ', COALESCE(c.nombre, ''), ' ', COALESCE(c.apellido, '')) as capturador_nombre
    FROM postulantes p
    LEFT JOIN aparatos_biometricos a ON p.aparato_id = a.id
    LEFT JOIN usuarios u ON p.usuario_registrador = u.id
    LEFT JOIN usuarios c ON p.capturador_id = c.id
    $where_clause
    ORDER BY p.fecha_registro DESC
    LIMIT ? OFFSET ?
";

$params[] = $per_page;
$params[] = $offset;

$postulantes_stmt = $pdo->prepare($postulantes_sql);
$postulantes_stmt->execute($params);
$postulantes = $postulantes_stmt->fetchAll();

// Debug: Log de la consulta y parámetros
error_log("DEBUG: Filtros aplicados:");
error_log("DEBUG: - search: '$search'");
error_log("DEBUG: - filtro_aparato: '$filtro_aparato'");
error_log("DEBUG: - filtro_unidad: '$filtro_unidad'");
error_log("DEBUG: - filtro_dedo: '$filtro_dedo'");
error_log("DEBUG: WHERE clause: $where_clause");
error_log("DEBUG: Consulta SQL: $postulantes_sql");
error_log("DEBUG: Parámetros: " . json_encode($params));
error_log("DEBUG: Total registros encontrados: " . count($postulantes));

// Obtener datos para filtros
$unidades = $pdo->query("SELECT DISTINCT unidad FROM postulantes WHERE unidad IS NOT NULL AND unidad != '' ORDER BY unidad")->fetchAll();

// Obtener aparatos disponibles (tanto activos como nombres de aparatos eliminados)
$aparatos_activos = $pdo->query("SELECT id, nombre FROM aparatos_biometricos ORDER BY nombre")->fetchAll();
$aparatos_eliminados = $pdo->query("
    SELECT DISTINCT p.aparato_nombre as nombre 
    FROM postulantes p 
    WHERE p.aparato_nombre IS NOT NULL 
    AND p.aparato_nombre != '' 
    AND p.aparato_nombre NOT IN (SELECT nombre FROM aparatos_biometricos)
    ORDER BY p.aparato_nombre
")->fetchAll();

// Combinar aparatos activos y eliminados
$aparatos = [];
foreach ($aparatos_activos as $aparato) {
    $aparatos[] = ['id' => $aparato['id'], 'nombre' => $aparato['nombre'], 'tipo' => 'activo'];
}
foreach ($aparatos_eliminados as $aparato) {
    $aparatos[] = ['id' => 'eliminado_' . base64_encode($aparato['nombre']), 'nombre' => $aparato['nombre'], 'tipo' => 'eliminado'];
}

$dedos = $pdo->query("SELECT DISTINCT dedo_registrado FROM postulantes WHERE dedo_registrado IS NOT NULL AND dedo_registrado != '' ORDER BY dedo_registrado")->fetchAll();

// Obtener datos de unidades si el usuario tiene permisos
if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN', 'SUPERVISOR'])) {
    $unidades_data = $pdo->query("SELECT id, nombre, activa FROM unidades ORDER BY nombre")->fetchAll();
}

// Obtener estadísticas generales
try {
    // Estadísticas básicas
    $total_postulantes = $pdo->query("SELECT COUNT(*) as total FROM postulantes")->fetch()['total'];
    $postulantes_hoy = $pdo->query("SELECT COUNT(*) as total FROM postulantes WHERE DATE(fecha_registro) = CURRENT_DATE")->fetch()['total'];
    $postulantes_semana = $pdo->query("SELECT COUNT(*) as total FROM postulantes WHERE fecha_registro >= CURRENT_DATE - INTERVAL '7 days'")->fetch()['total'];
    $postulantes_mes = $pdo->query("SELECT COUNT(*) as total FROM postulantes WHERE fecha_registro >= CURRENT_DATE - INTERVAL '30 days'")->fetch()['total'];
    
    // Promedio de registros por día (rango personalizable)
    $fecha_desde_promedio = $_GET['fecha_desde_promedio'] ?? '2025-10-29';
    $fecha_hasta_promedio = $_GET['fecha_hasta_promedio'] ?? date('Y-m-d');
    
    // Calcular días entre las fechas
    $fecha_desde_obj = new DateTime($fecha_desde_promedio);
    $fecha_hasta_obj = new DateTime($fecha_hasta_promedio);
    $dias_diferencia = $fecha_desde_obj->diff($fecha_hasta_obj)->days + 1; // +1 para incluir ambos días
    
    try {
        $promedio_diario = $pdo->prepare("
            SELECT ROUND(COUNT(*)::numeric / ?, 1) as promedio 
            FROM postulantes 
            WHERE DATE(fecha_registro) BETWEEN ? AND ?
        ");
        $promedio_diario->execute([$dias_diferencia, $fecha_desde_promedio, $fecha_hasta_promedio]);
        $promedio_diario = $promedio_diario->fetch()['promedio'];
        
        // Actualizar el texto del rango
        $rango_fechas_texto = "Del " . date('d/m/Y', strtotime($fecha_desde_promedio)) . " al " . date('d/m/Y', strtotime($fecha_hasta_promedio)) . " ({$dias_diferencia} días)";
    } catch (Exception $e) {
        error_log("Error calculando promedio diario: " . $e->getMessage());
        $promedio_diario = 0;
        $rango_fechas_texto = "Error en el cálculo";
    }
    
    // Distribución por unidades (rango personalizable)
    $distribucion_unidades = $pdo->prepare("
        SELECT unidad, COUNT(*) as cantidad 
        FROM postulantes 
        WHERE unidad IS NOT NULL AND unidad != '' 
        AND DATE(fecha_registro) BETWEEN ? AND ?
        GROUP BY unidad 
        ORDER BY cantidad DESC 
        LIMIT 10
    ");
    $distribucion_unidades->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $distribucion_unidades = $distribucion_unidades->fetchAll();
    
    // Distribución por dedos (rango personalizable)
    $distribucion_dedos = $pdo->prepare("
        SELECT dedo_registrado, COUNT(*) as cantidad 
        FROM postulantes 
        WHERE dedo_registrado IS NOT NULL AND dedo_registrado != '' 
        AND DATE(fecha_registro) BETWEEN ? AND ?
        GROUP BY dedo_registrado 
        ORDER BY cantidad DESC
    ");
    $distribucion_dedos->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $distribucion_dedos = $distribucion_dedos->fetchAll();
    
    // Distribución por dispositivos (rango personalizable)
    $distribucion_dispositivos = $pdo->prepare("
        SELECT 
            COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo') as dispositivo,
            COUNT(*) as cantidad
        FROM postulantes p
        LEFT JOIN aparatos_biometricos ab ON p.aparato_id = ab.id
        WHERE DATE(p.fecha_registro) BETWEEN ? AND ?
        GROUP BY COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo')
        ORDER BY cantidad DESC
        LIMIT 10
    ");
    $distribucion_dispositivos->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $distribucion_dispositivos = $distribucion_dispositivos->fetchAll();
    
    // Distribución por sexo (rango personalizable)
    $distribucion_sexo = $pdo->prepare("
        SELECT 
            COALESCE(sexo, 'No especificado') as sexo,
            COUNT(*) as cantidad
        FROM postulantes 
        WHERE DATE(fecha_registro) BETWEEN ? AND ?
        GROUP BY COALESCE(sexo, 'No especificado')
        ORDER BY cantidad DESC
    ");
    $distribucion_sexo->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $distribucion_sexo = $distribucion_sexo->fetchAll();
    
    // Registros por día (rango personalizable)
    $registros_por_dia = $pdo->prepare("
        SELECT 
            DATE(fecha_registro) as fecha,
            COUNT(*) as cantidad
        FROM postulantes 
        WHERE DATE(fecha_registro) BETWEEN ? AND ?
        GROUP BY DATE(fecha_registro)
        ORDER BY fecha DESC
    ");
    $registros_por_dia->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $registros_por_dia = $registros_por_dia->fetchAll();
    
    // Usuarios más activos
    try {
        $usuarios_activos = $pdo->prepare("
            SELECT 
                COALESCE(u.nombre || ' ' || u.apellido, p.registrado_por, 'Usuario Desconocido') as usuario,
                COUNT(p.id) as registros
            FROM postulantes p
            LEFT JOIN usuarios u ON u.usuario = p.registrado_por
            WHERE p.registrado_por IS NOT NULL AND p.registrado_por != ''
            AND DATE(p.fecha_registro) BETWEEN ? AND ?
            GROUP BY COALESCE(u.nombre || ' ' || u.apellido, p.registrado_por, 'Usuario Desconocido')
            ORDER BY registros DESC
            LIMIT 10
        ");
        $usuarios_activos->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
        $usuarios_activos = $usuarios_activos->fetchAll();
    } catch (Exception $e) {
        error_log("Error en consulta usuarios_activos: " . $e->getMessage());
        $usuarios_activos = [];
    }
    
    // Horarios de mayor actividad (rango personalizable)
    $horarios_actividad = $pdo->prepare("
        SELECT 
            EXTRACT(HOUR FROM fecha_registro) as hora,
            COUNT(*) as cantidad
        FROM postulantes 
        WHERE DATE(fecha_registro) BETWEEN ? AND ?
        GROUP BY EXTRACT(HOUR FROM fecha_registro)
        ORDER BY hora ASC
    ");
    $horarios_actividad->execute([$fecha_desde_promedio, $fecha_hasta_promedio]);
    $horarios_actividad = $horarios_actividad->fetchAll();
    
} catch (Exception $e) {
    // En caso de error, inicializar variables con valores por defecto
    $total_postulantes = 0;
    $postulantes_hoy = 0;
    $postulantes_semana = 0;
    $postulantes_mes = 0;
    $promedio_diario = 0;
    $rango_fechas_texto = "Error en el cálculo";
    $distribucion_unidades = [];
    $distribucion_dedos = [];
    $distribucion_dispositivos = [];
    $distribucion_sexo = [];
    $registros_por_dia = [];
    $usuarios_activos = [];
    $horarios_actividad = [];
}

    // Obtener datos para reporte diario específico
    $fecha_reporte = $_GET['fecha_reporte'] ?? date('Y-m-d');
    $hora_desde = $_GET['hora_desde'] ?? '00:00';
    $hora_hasta = $_GET['hora_hasta'] ?? '23:59';
    
    // Debug temporal - eliminar después de confirmar que funciona
    // file_put_contents('/tmp/debug_quira.txt', "DEBUG: " . date('Y-m-d H:i:s') . " - Fecha: $fecha_reporte, Desde: $hora_desde, Hasta: $hora_hasta\n", FILE_APPEND);

try {
    // Construir filtro de fecha y hora (especificando tabla)
    $filtro_fecha_hora = "DATE(p.fecha_registro) = ?";
    $parametros = [$fecha_reporte];
    
    // Agregar filtro de franja horaria si se especifica
    if (isset($_GET['hora_desde']) && isset($_GET['hora_hasta']) && 
        ($_GET['hora_desde'] !== '00:00' || $_GET['hora_hasta'] !== '23:59')) {
        $filtro_fecha_hora .= " AND p.fecha_registro::time >= ? AND p.fecha_registro::time <= ?";
        $parametros[] = $hora_desde;
        $parametros[] = $hora_hasta;
        
        // Debug temporal - eliminar después
        error_log("DEBUG FILTRO HORAS: fecha=$fecha_reporte, desde=$hora_desde, hasta=$hora_hasta");
        error_log("DEBUG SQL: $filtro_fecha_hora");
        error_log("DEBUG PARAMS: " . print_r($parametros, true));
    }
    
    // Postulantes registrados en la fecha específica (con franja horaria)
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM postulantes p WHERE $filtro_fecha_hora");
    $stmt->execute($parametros);
    $postulantes_fecha = $stmt->fetch()['total'];
    
    // Debug temporal - eliminar después de confirmar que funciona
    // file_put_contents('/tmp/debug_quira.txt', "Resultado: $postulantes_fecha\n", FILE_APPEND);
    
    // Postulantes por unidad en la fecha específica (con franja horaria)
    $stmt_unidad = $pdo->prepare("
        SELECT unidad, COUNT(*) as cantidad 
        FROM postulantes p
        WHERE $filtro_fecha_hora AND p.unidad IS NOT NULL AND p.unidad != ''
        GROUP BY p.unidad 
        ORDER BY cantidad DESC
    ");
    $stmt_unidad->execute($parametros);
    $postulantes_por_unidad_fecha = $stmt_unidad->fetchAll();
    
    // Aparatos biométricos utilizados en la fecha específica (con franja horaria)
    $stmt_aparatos = $pdo->prepare("
        SELECT 
            COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo') as dispositivo,
            COUNT(*) as cantidad
        FROM postulantes p
        LEFT JOIN aparatos_biometricos ab ON p.aparato_id = ab.id
        WHERE $filtro_fecha_hora
        GROUP BY COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo')
        ORDER BY cantidad DESC
    ");
    $stmt_aparatos->execute($parametros);
    $aparatos_utilizados_fecha = $stmt_aparatos->fetchAll();
    
    // Distribución de usuarios que han registrado en la fecha específica (con franja horaria)
    $stmt_usuarios = $pdo->prepare("
        SELECT 
            p.registrado_por as usuario,
            COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo') as dispositivo,
            COUNT(*) as cantidad
        FROM postulantes p
        LEFT JOIN aparatos_biometricos ab ON p.aparato_id = ab.id
        WHERE $filtro_fecha_hora AND p.registrado_por IS NOT NULL
        GROUP BY p.registrado_por, COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo')
        ORDER BY p.registrado_por, cantidad DESC
    ");
    $stmt_usuarios->execute($parametros);
    $usuarios_registradores_fecha = $stmt_usuarios->fetchAll();
    
    // Hora del primer y último registro del día (con filtro de franja horaria)
    $stmt = $pdo->prepare("
        SELECT 
            MIN(p.fecha_registro) as primer_registro,
            MAX(p.fecha_registro) as ultimo_registro
        FROM postulantes p
        WHERE $filtro_fecha_hora
    ");
    $stmt->execute($parametros);
    $horarios_registro = $stmt->fetch();
    
    // Lista detallada de postulantes del día agrupada por unidad (con filtro de franja horaria)
    $stmt = $pdo->prepare("
        SELECT 
            p.cedula,
            COALESCE(p.nombre_completo, p.nombre || ' ' || p.apellido) as nombre_completo,
            p.unidad,
            COALESCE(ab.nombre, p.aparato_nombre, 'Sin dispositivo') as dispositivo
        FROM postulantes p
        LEFT JOIN aparatos_biometricos ab ON p.aparato_id = ab.id
        WHERE $filtro_fecha_hora
        ORDER BY p.unidad ASC, p.fecha_registro ASC
    ");
    $stmt->execute($parametros);
    $postulantes_detallados = $stmt->fetchAll();
    
    // Agrupar postulantes por unidad
    $postulantes_por_unidad = [];
    foreach ($postulantes_detallados as $postulante) {
        $unidad = $postulante['unidad'] ?: 'Sin unidad especificada';
        if (!isset($postulantes_por_unidad[$unidad])) {
            $postulantes_por_unidad[$unidad] = [];
        }
        $postulantes_por_unidad[$unidad][] = $postulante;
    }
    
} catch (Exception $e) {
    // Debug temporal - eliminar después de confirmar que funciona
    // file_put_contents('/tmp/debug_quira.txt', "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    $postulantes_fecha = 0;
    $postulantes_por_unidad_fecha = [];
    $aparatos_utilizados_fecha = [];
    $usuarios_registradores_fecha = [];
    $horarios_registro = ['primer_registro' => null, 'ultimo_registro' => null];
    $postulantes_detallados = [];
    $postulantes_por_unidad = [];
}

// Obtener postulantes recientes (últimos 5 registrados sin filtro de fecha)
// Usar consulta directa para asegurar datos actualizados
$postulantes_recientes = $pdo->query("
    SELECT id, COALESCE(nombre_completo, nombre || ' ' || apellido) as nombre_completo, cedula, fecha_registro, edad, unidad, dedo_registrado, registrado_por
    FROM postulantes 
    ORDER BY fecha_registro DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Obtener distribución por unidad - usar consulta directa para datos actualizados
$distribucion_unidad = $pdo->query("
    SELECT 
        unidad, 
        COUNT(*) as total,
        ROUND((COUNT(*)::numeric * 100.0) / (SELECT COUNT(*) FROM postulantes)::numeric, 2) as porcentaje
    FROM postulantes 
    WHERE unidad IS NOT NULL AND unidad != ''
    GROUP BY unidad 
    ORDER BY COUNT(*) DESC 
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>Inicio - Sistema Quira</title>

    <meta name="description" content="Panel de administración del sistema Quira">
    <meta name="author" content="Sistema Quira">
    <meta name="robots" content="noindex, nofollow">

    <!-- Icons -->
    <link rel="shortcut icon" href="favicon.php">
    <link rel="apple-touch-icon" href="favicon.php">
    <link rel="icon" href="favicon.php">

    <!-- Stylesheets -->
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,600,700" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css" integrity="sha384-50oBUHEmvpQ+1lW4y57PTFmhCaXp0ML5d60M1M7uH2+nqUivzIebhndOJK28anvf" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/css/main.min.css">
    
    <style>
        .stats-card {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
            height: 120px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-card.success {
            background: linear-gradient(135deg, #3a5f9a 0%, #5a7fb5 100%);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #4a6fa5 0%, #6a8fc5 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #5a7fb5 0%, #7a9fd5 100%);
        }
        .stats-card:not(.success):not(.warning):not(.info) {
            background: linear-gradient(135deg, #2E5090 0%, #6a8fc5 100%);
        }
        .nav-link.active {
            background-color: #2E5090 !important;
            color: white !important;
        }
        .bg-primary {
            background: linear-gradient(135deg, #2E5090 0%, #6a8fc5 100%) !important;
        }
        .table-responsive {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Footer */
        .footer {
            background: #1a202c;
            color: #cbd5e0;
            padding: 3rem 0 1rem;
            margin-top: 0;
        }
        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        .footer-section h3 {
            color: #ffffff;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .footer-section a {
            color: #a0aec0;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
            transition: color 0.3s ease;
        }
        .footer-section a:hover {
            color: #2E5090;
        }
        .footer-bottom {
            border-top: 1px solid #2d3748;
            padding-top: 1rem;
            text-align: center;
            color: #718096;
        }
        
        /* Créditos Pixelcave */
        .pixelcave-credits {
            background: #2d3748;
            border-top: 1px solid #4a5568;
            padding: 0.75rem 0;
            text-align: center;
            color: #a0aec0;
            font-size: 0.875rem;
            margin-top: 0;
        }
        .pixelcave-credits a {
            color: #2E5090;
            text-decoration: none;
            font-weight: 500;
        }
        .pixelcave-credits a:hover {
            color: #1a3a70;
            text-decoration: underline;
        }
        
        /* Estilos para el modal de registros por día */
        #modalRegistrosDia .modal-dialog {
            max-height: 90vh;
            height: 90vh;
        }
        
        #modalRegistrosDia .modal-content {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        #modalRegistrosDia .modal-body {
            flex: 1;
            overflow-y: auto;
            max-height: calc(90vh - 120px);
        }
        
        #modalRegistrosDia .modal-header,
        #modalRegistrosDia .modal-footer {
            flex-shrink: 0;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .content-section {
            display: none;
        }
        .content-section.active {
            display: block;
        }
        
        /* Estilos para el modal de detalles */
        #modalDetallesPostulante .table-borderless td {
            padding: 0.2rem 0.3rem;
            border: none;
            line-height: 1.3;
        }
        
        #modalDetallesPostulante .table-borderless td:first-child {
            width: 35%;
            font-weight: 600;
            color: #495057;
            padding-right: 0.5rem;
        }
        
        #modalDetallesPostulante .table-borderless td:last-child {
            width: 65%;
            padding-left: 0.2rem;
        }
        
        #modalDetallesPostulante .table-borderless tr {
            margin-bottom: 0.1rem;
        }
        
        /* FOOTER STYLES */
        .footer-simple {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #1e293b;
            border-top: 1px solid #334155;
            padding: 8px 20px;
            z-index: 40;
            text-align: center;
        }
        
        .footer-simple {
            cursor: pointer;
        }
        
        .footer-simple #footer-text {
            color: #94a3b8;
            transition: color 0.2s ease;
        }
        
        .footer-simple #footer-simple {
            color: #2E5090;
            font-weight: bold;
        }
        
        .footer-simple:hover #footer-text {
            color: #2E5090;
        }
        
        
        /* MODAL STYLES */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 70;
            padding: 16px;
        }
        
        .modal-container {
            max-width: 28rem;
            width: 100%;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 50%, #1e293b 100%);
            border: 2px solid #2E5090;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            padding: 0.875rem;
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 0.75rem;
        }
        
        .modal-title {
            font-size: 1.875rem;
            font-weight: bold;
            color: #2E5090;
            letter-spacing: 0.1em;
            margin: 0;
        }
        
        .modal-divider {
            height: 2px;
            background: linear-gradient(90deg, transparent 0%, #2E5090 50%, transparent 100%);
            margin: 0.75rem 0;
        }
        
        .modal-subtitle {
            color: #94a3b8;
            font-size: 0.875rem;
            margin: 0;
        }
        
        .modal-body {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            margin-bottom: 0.75rem;
        }
        
        .info-card {
            background: #334155;
            border: 1px solid #475569;
            border-radius: 0.5rem;
            padding: 0.5rem;
        }
        
        .info-label {
            color: #64748b;
            font-size: 0.75rem;
            margin-bottom: 0.25rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .info-content {
            color: #cbd5e1;
            font-size: 0.875rem;
            font-weight: bold;
        }
        
        .info-content-small {
            color: #94a3b8;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            line-height: 1.4;
        }
        
        .info-content-blue {
            color: #2E5090;
            font-weight: bold;
        }
        
        .modal-footer {
            text-align: center;
        }
        
        .btn-close {
            background: #2E5090;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 0.5rem;
            padding: 0.625rem 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            transition: opacity 0.2s ease;
            cursor: pointer;
        }
        
        .btn-close:hover {
            opacity: 0.9;
        }
        
        /* Estilos para el modal de exportación */
        .export-option {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .export-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: #2E5090;
        }
        
        .export-option .card-body {
            padding: 1.5rem;
        }
        
        .export-option i {
            transition: transform 0.3s ease;
        }
        
        .export-option:hover i {
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <!-- Page Container -->
    <div id="page-container">
        <!-- Header -->
        <div class="bg-image" style="background-image: url('<?php echo $randomHeaderImage; ?>');">
            <div class="bg-image-overlay py-5">
                <header class="container-fluid d-md-flex align-items-md-center justify-content-md-between py-4" style="padding-left: 50px; padding-right: 30px;">
                    <div class="text-center text-md-left py-3">
                        <a class="h4 text-dark font-weight-600" href="dashboard.php">
                            <img src="assets/media/various/quiraXXXL.png" alt="Quira Logo" style="height: 130px; width: auto; margin-right: 15px; vertical-align: middle;">Panel de Administración
                        </a>
                    </div>
                    <div class="text-center text-md-right py-3">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-primary px-3 py-2" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fa fa-fw fa-user mr-1"></i> <?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?> <i class="fa fa-fw fa-chevron-down ml-1"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right font-size-sm" aria-labelledby="dropdownMenuButton">
                                <a class="dropdown-item" href="configuracion_cuenta.php"><i class="fas fa-cog mr-2"></i>Configuración de Cuenta</a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt mr-2"></i>Cerrar Sesión</a>
                            </div>
                        </div>
                    </div>
                </header>
            </div>
        </div>
        <!-- END Header -->

        <!-- Container -->
        <div class="container-fluid py-4" style="padding-left: 15px; padding-right: 30px;">
            <div class="row">
                <div class="col-lg-4 col-xl-3">
                    <!-- Toggle Navigation Button -->
                    <button type="button" class="btn btn-secondary btn-block d-lg-none mb-4" onclick="$('#navigation').toggleClass('d-none');">
                        <i class="fa fa-fw fa-bars mr-1"></i> Navegación
                    </button>

                    <!-- Navigation -->
                    <div id="navigation" class="block d-none d-lg-block mr-lg-4">
                        <div class="block-content">
                            <nav class="nav nav-pills flex-column mb-0">
                                <div class="font-size-sm text-uppercase text-black-50 font-weight-bold mb-3">
                                    Principal
                                </div>
                                <a class="nav-link mb-2 <?= (!isset($mensaje_usuarios) || empty($mensaje_usuarios)) && (!isset($mensaje_dispositivos) || empty($mensaje_dispositivos)) ? 'active' : '' ?>" href="#dashboard" onclick="showSection('dashboard')">
                                    <i class="fas fa-fw fa-chart-line mr-2"></i>
                                    Inicio
                                </a>
                                <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
                                <a class="nav-link mb-2 <?= (isset($mensaje_usuarios) && !empty($mensaje_usuarios)) ? 'active' : '' ?>" href="#usuarios" onclick="showSection('usuarios')">
                                    <i class="fas fa-fw fa-users mr-2"></i>
                                    Usuarios
                                </a>
                                <?php endif; ?>
                                <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN', 'SUPERVISOR'])): ?>
                                <a class="nav-link mb-2 <?= (isset($mensaje_dispositivos) && !empty($mensaje_dispositivos)) ? 'active' : '' ?>" href="#dispositivos" onclick="showSection('dispositivos')">
                                    <i class="fas fa-fw fa-fingerprint mr-2"></i>
                                    Dispositivos Biométricos
                                </a>
                                <?php endif; ?>
                                <a class="nav-link mb-2" href="#postulantes" onclick="showSection('postulantes')">
                                    <i class="fas fa-fw fa-user-friends mr-2"></i>
                                    Postulantes
                                </a>
                                <?php if (!in_array($_SESSION['rol'], ['SUPERVISOR'])): ?>
                                <a class="nav-link mb-2" href="agregar_postulante.php" id="link-agregar-postulante" data-destino="agregar_postulante.php">
                                    <i class="fas fa-fw fa-user-plus mr-2"></i>
                                    Agregar Postulante
                                </a>
                                <a class="nav-link mb-2" href="control_asistencia.php">
                                    <i class="fas fa-fw fa-clock mr-2"></i>
                                    Control de Asistencia
                                </a>
                                <?php endif; ?>
                                <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN', 'SUPERVISOR'])): ?>
                                <a class="nav-link mb-2 <?= (isset($mensaje_unidades) && !empty($mensaje_unidades)) ? 'active' : '' ?>" href="#unidades" onclick="showSection('unidades')">
                                    <i class="fas fa-fw fa-building mr-2"></i>
                                    Unidades
                                </a>
                                <?php endif; ?>
                                <a class="nav-link mb-2" href="#estadisticas" onclick="showSection('estadisticas')">
                                    <i class="fas fa-fw fa-chart-bar mr-2"></i>
                                    Estadísticas
                                </a>
                                <?php if ($_SESSION['rol'] === 'SUPERADMIN'): ?>
                                <div class="font-size-sm text-uppercase text-black-50 font-weight-bold mb-3 mt-4">
                                    Sistema
                                </div>
                                <a class="nav-link mb-2" href="#configuracion" onclick="showSection('configuracion')">
                                    <i class="fas fa-fw fa-cog mr-2"></i>
                                    Configuración
                                </a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                    <!-- END Navigation -->
                </div>
                
                <div class="col-lg-8 col-xl-9">
                    <!-- Inicio Section -->
                    <div id="dashboard-section" class="content-section <?= (!isset($mensaje_usuarios) || empty($mensaje_usuarios)) && (!isset($mensaje_dispositivos) || empty($mensaje_dispositivos)) ? 'active' : '' ?>">
                        <!-- Stats -->
                        <div class="row">
                            <?php if (!in_array($_SESSION['rol'], ['USUARIO'])): ?>
                            <div class="col-sm-6 col-lg-3">
                                <div class="stats-card">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="text-white font-size-lg font-weight-bold"><?= number_format($total_postulantes, 0, ',', '.') ?></div>
                                            <div class="text-white-50 text-uppercase font-size-sm font-weight-bold">Total Postulantes</div>
                                        </div>
                                        <div class="font-weight-bold text-white font-size-lg">
                                            <i class="fas fa-users"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!in_array($_SESSION['rol'], ['USUARIO'])): ?>
                            <div class="col-sm-6 col-lg-3">
                                <div class="stats-card success">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="text-white font-size-lg font-weight-bold"><?= number_format($usuarios_count, 0, ',', '.') ?></div>
                                            <div class="text-white-50 text-uppercase font-size-sm font-weight-bold">Usuarios</div>
                                        </div>
                                        <div class="font-weight-bold text-white font-size-lg">
                                            <i class="fas fa-user-shield"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!in_array($_SESSION['rol'], ['USUARIO'])): ?>
                            <div class="col-sm-6 col-lg-3">
                                <div class="stats-card warning">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="text-white font-size-lg font-weight-bold"><?= number_format($aparatos_count, 0, ',', '.') ?></div>
                                            <div class="text-white-50 text-uppercase font-size-sm font-weight-bold">Dispositivos</div>
                                        </div>
                                        <div class="font-weight-bold text-white font-size-lg">
                                            <i class="fas fa-fingerprint"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!in_array($_SESSION['rol'], ['USUARIO'])): ?>
                            <div class="col-sm-6 col-lg-3">
                                <div class="stats-card info">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="text-white font-size-lg font-weight-bold"><?= number_format($unidades_count, 0, ',', '.') ?></div>
                                            <div class="text-white-50 text-uppercase font-size-sm font-weight-bold">Unidades</div>
                                        </div>
                                        <div class="font-weight-bold text-white font-size-lg">
                                            <i class="fas fa-building"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- END Stats -->

                        <!-- Recent Activity -->
                        <div class="row">
                            <div class="col-lg-8">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-clock mr-2"></i>Postulantes Recientes</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Nombre</th>
                                                        <th>Cédula</th>
                                                        <th>Unidad</th>
                                                        <th>Fecha</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($postulantes_recientes as $postulante): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($postulante['nombre_completo']) ?></td>
                                                        <td><?= htmlspecialchars($postulante['cedula']) ?></td>
                                                        <td><span class="badge badge-primary"><?= str_replace('&quot;', '"', htmlspecialchars($postulante['unidad'])) ?></span></td>
                                                        <td><?= date('d/m/Y H:i', strtotime($postulante['fecha_registro'])) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i>Distribución por Unidad</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach (array_slice($distribucion_unidad, 0, 5) as $unidad): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="font-size-sm"><?= str_replace('&quot;', '"', htmlspecialchars($unidad['unidad'])) ?></span>
                                            <span class="badge badge-primary"><?= $unidad['total'] ?></span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección de Usuarios -->
                    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
                    <div id="usuarios-section" class="content-section <?= (isset($mensaje_usuarios) && !empty($mensaje_usuarios)) ? 'active' : '' ?>">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-users mr-2"></i>Gestión de Usuarios</h5>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearUsuario">
                                    <i class="fas fa-plus"></i> Nuevo Usuario
                                </button>
                            </div>
                            <div class="card-body">
                                <?php if ($mensaje_usuarios): ?>
                                <div class="alert alert-<?= $tipo_mensaje_usuarios ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($mensaje_usuarios) ?>
                                    <button type="button" class="close" data-dismiss="alert">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Usuario</th>
                                                <th>Nombre Completo</th>
                                                <th>Rol</th>
                                                <th>Grado</th>
                                                <th>Cédula</th>
                                                <th>Teléfono</th>
                                                <th>Fecha Creación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($usuarios as $user): ?>
                                            <tr>
                                                <td><?= $user['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($user['usuario']) ?></strong></td>
                                                <td><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></td>
                                                <td><span class="badge badge-primary"><?= htmlspecialchars($user['rol']) ?></span></td>
                                                <td><?= htmlspecialchars($user['grado']) ?></td>
                                                <td><?= htmlspecialchars($user['cedula']) ?></td>
                                                <td><?= htmlspecialchars($user['telefono']) ?></td>
                                                <td><?= date('d/m/Y', strtotime($user['fecha_creacion'])) ?></td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editarUsuario(<?= htmlspecialchars(json_encode($user)) ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-warning" 
                                                                onclick="cambiarPassword(<?= $user['id'] ?>, '<?= htmlspecialchars($user['usuario']) ?>')"
                                                                title="Restaurar contraseña">
                                                            <i class="fas fa-key"></i>
                                                        </button>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="eliminarUsuario(<?= $user['id'] ?>, '<?= htmlspecialchars($user['usuario']) ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Other sections will be added here -->

                    <!-- Sección de Dispositivos Biométricos -->
                    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN', 'SUPERVISOR'])): ?>
                    <div id="dispositivos-section" class="content-section <?= (isset($mensaje_dispositivos) && !empty($mensaje_dispositivos)) ? 'active' : '' ?>">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Lista de Dispositivos</h5>
                                <?php if (!in_array($_SESSION['rol'], ['SUPERVISOR'])): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearDispositivo">
                                    <i class="fas fa-plus"></i> Nuevo Dispositivo
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if ($mensaje_dispositivos): ?>
                                <div class="alert alert-<?= $tipo_mensaje_dispositivos ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($mensaje_dispositivos) ?>
                                    <button type="button" class="close" data-dismiss="alert">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Serial</th>
                                                <th>IP Address</th>
                                                <th>Puerto</th>
                                                <th>Estado</th>
                                                <th>Postulantes</th>
                                                <th>Fecha Registro</th>
                                                <?php if (!in_array($_SESSION['rol'], ['SUPERVISOR'])): ?>
                                                <th>Acciones</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dispositivos as $dispositivo): ?>
                                            <tr>
                                                <td><?= $dispositivo['id'] ?></td>
                                                <td><strong><?= htmlspecialchars($dispositivo['nombre']) ?></strong></td>
                                                <td><code><?= htmlspecialchars($dispositivo['serial']) ?></code></td>
                                                <td><?= htmlspecialchars($dispositivo['ip_address']) ?></td>
                                                <td><?= htmlspecialchars($dispositivo['puerto']) ?></td>
                                                <td>
                                                    <?php
                                                    $estado_class = '';
                                                    switch($dispositivo['estado']) {
                                                        case 'ACTIVO': $estado_class = 'badge-success'; break;
                                                        case 'INACTIVO': $estado_class = 'badge-secondary'; break;
                                                        case 'PRUEBA': $estado_class = 'badge-warning'; break;
                                                        case 'MANTENIMIENTO': $estado_class = 'badge-danger'; break;
                                                        default: $estado_class = 'badge-primary';
                                                    }
                                                    ?>
                                                    <span class="badge <?= $estado_class ?>"><?= htmlspecialchars($dispositivo['estado']) ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($dispositivo['postulantes_count'] > 0): ?>
                                                        <span class="badge badge-warning"><?= $dispositivo['postulantes_count'] ?></span>
                                                    <?php else: ?>
                                                        <span class="badge badge-light">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('d/m/Y H:i', strtotime($dispositivo['fecha_registro'])) ?></td>
                                                <?php if (!in_array($_SESSION['rol'], ['SUPERVISOR'])): ?>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editarDispositivo(<?= htmlspecialchars(json_encode($dispositivo)) ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="eliminarDispositivo(<?= $dispositivo['id'] ?>, '<?= htmlspecialchars($dispositivo['nombre']) ?>', <?= $dispositivo['postulantes_count'] ?>)"
                                                                data-id="<?= $dispositivo['id'] ?>">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="postulantes-section" class="content-section">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-user-friends mr-2"></i>Lista de Postulantes</h5>
                                <div class="d-flex align-items-center">
                                    <span class="badge badge-info mr-2">Total: <?= $total_records ?></span>
                                    <?php if (!in_array($_SESSION['rol'], ['USUARIO'])): ?>
                                    <!-- <button type="button" class="btn btn-primary btn-sm" onclick="exportarPostulantes()">
                                        <i class="fas fa-download"></i> Exportar
                                    </button> -->
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Filtros y Búsqueda -->
                            <div class="card-body border-bottom">
                                <form method="GET" id="filtrosForm">
                                    <input type="hidden" name="page" value="1">
                                    
                                    <div class="row">
                                        <!-- Búsqueda -->
                                        <div class="col-md-4 mb-3">
                                            <label for="search" class="form-label"><i class="fas fa-search mr-1"></i>Buscar</label>
                                            <input type="text" class="form-control" id="search" name="search" 
                                                   value="<?= htmlspecialchars($search) ?>" 
                                                   placeholder="Nombre, apellido o cédula...">
                                        </div>
                                        
                                        <!-- Filtro por fecha -->
                                        <div class="col-md-2 mb-3">
                                            <label for="fecha_desde" class="form-label">Fecha Desde</label>
                                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                                                   value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                                        </div>
                                        
                                        <div class="col-md-2 mb-3">
                                            <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                                                   value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                                        </div>
                                        
                                        <!-- Filtro por unidad -->
                                        <div class="col-md-2 mb-3">
                                            <label for="unidad" class="form-label">Unidad</label>
                                            <select class="form-control" id="unidad" name="unidad">
                                                <option value="">Todas las unidades</option>
                                                <?php foreach ($unidades as $unidad): ?>
                                                <option value="<?= htmlspecialchars($unidad['unidad']) ?>" 
                                                        <?= $filtro_unidad === $unidad['unidad'] ? 'selected' : '' ?>>
                                                    <?= str_replace('&quot;', '"', htmlspecialchars($unidad['unidad'])) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Filtro por aparato -->
                                        <div class="col-md-2 mb-3">
                                            <label for="aparato" class="form-label">Aparato</label>
                                            <select class="form-control" id="aparato" name="aparato">
                                                <option value="">Todos los aparatos</option>
                                                <?php foreach ($aparatos as $aparato): ?>
                                                <option value="<?= $aparato['id'] ?>" 
                                                        <?= $filtro_aparato == $aparato['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($aparato['nombre']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <!-- Filtro por dedo -->
                                        <div class="col-md-2 mb-3">
                                            <label for="dedo" class="form-label">Dedo</label>
                                            <select class="form-control" id="dedo" name="dedo">
                                                <option value="">Todos los dedos</option>
                                                <?php foreach ($dedos as $dedo): ?>
                                                <option value="<?= htmlspecialchars($dedo['dedo_registrado']) ?>" 
                                                        <?= $filtro_dedo === $dedo['dedo_registrado'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dedo['dedo_registrado']) ?>
                                                </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <!-- Resultados por página -->
                                        <div class="col-md-2 mb-3">
                                            <label for="per_page" class="form-label">Por página</label>
                                            <select class="form-control" id="per_page" name="per_page">
                                                <option value="15" <?= $per_page == 15 ? 'selected' : '' ?>>15</option>
                                                <option value="30" <?= $per_page == 30 ? 'selected' : '' ?>>30</option>
                                                <option value="45" <?= $per_page == 45 ? 'selected' : '' ?>>45</option>
                                                <option value="60" <?= $per_page == 60 ? 'selected' : '' ?>>60</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Botones -->
                                        <div class="col-md-8 mb-3 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-filter"></i> Filtrar
                                            </button>
                                            <a href="dashboard.php#postulantes" class="btn btn-secondary mr-2">
                                                <i class="fas fa-times"></i> Limpiar
                                            </a>
                                            <span class="text-muted ml-2">
                                                Mostrando <?= count($postulantes) ?> de <?= $total_records ?> registros
                                            </span>
                                        </div>
                                    </div>
                                </form>
                            </div>
                            
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nombre Completo</th>
                                                <th>Cédula</th>
                                                <th>Dedo</th>
                                                <th>Aparato</th>
                                                <th>Unidad</th>
                                                <th>Fecha Registro</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($postulantes)): ?>
                                            <tr>
                                                <td colspan="7" class="text-center text-muted py-4">
                                                    <i class="fas fa-search fa-2x mb-2"></i><br>
                                                    No se encontraron postulantes con los filtros aplicados.
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($postulantes as $postulante): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($postulante['nombre_completo']) ?></strong>
                                                        <?php if ($postulante['sexo']): ?>
                                                            <br><small class="text-muted"><?= htmlspecialchars($postulante['sexo']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($postulante['cedula']) ?></td>
                                                    <td>
                                                        <?php if ($postulante['dedo_registrado']): ?>
                                                            <span class="badge badge-success"><?= htmlspecialchars($postulante['dedo_registrado']) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">No registrado</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $aparato_nombre = $postulante['aparato_nombre_actual'] ?: $postulante['aparato_nombre'];
                                                        if ($aparato_nombre): 
                                                        ?>
                                                            <span class="badge badge-primary"><?= htmlspecialchars($aparato_nombre) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Sin aparato</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($postulante['unidad']): ?>
                                                            <span class="badge badge-info"><?= str_replace('&quot;', '"', htmlspecialchars($postulante['unidad'])) ?></span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Sin unidad</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d/m/Y H:i', strtotime($postulante['fecha_registro'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                                    onclick="verDetallesPostulante(<?= htmlspecialchars(json_encode($postulante)) ?>)"
                                                                    data-postulante-id="<?= $postulante['id'] ?>">
                                                                <i class="fas fa-eye"></i> Ver
                                                            </button>
                                                            <?php if (!in_array($_SESSION['rol'], ['SUPERVISOR'])): ?>
                                                            <a href="editar_postulante.php?id=<?= $postulante['id'] ?>" 
                                                               class="btn btn-sm btn-outline-warning">
                                                                <i class="fas fa-edit"></i> Editar
                                                            </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Paginación -->
                                <?php if ($total_pages > 1): ?>
                                <nav aria-label="Paginación de postulantes">
                                    <ul class="pagination justify-content-center">
                                        <!-- Página anterior -->
                                        <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>#postulantes">
                                                <i class="fas fa-chevron-left"></i> Anterior
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        
                                        <!-- Páginas -->
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>#postulantes">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                        <?php endfor; ?>
                                        
                                        <!-- Página siguiente -->
                                        <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>#postulantes">
                                                Siguiente <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                    
                                    <!-- Ir a página específica -->
                                    <div class="row justify-content-center mt-3">
                                        <div class="col-auto">
                                            <form method="GET" class="form-inline" id="irAPaginaForm">
                                                <input type="hidden" name="per_page" value="<?= $per_page ?>">
                                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                                <input type="hidden" name="fecha_desde" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
                                                <input type="hidden" name="fecha_hasta" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
                                                <input type="hidden" name="unidad" value="<?= htmlspecialchars($filtro_unidad) ?>">
                                                <input type="hidden" name="aparato" value="<?= htmlspecialchars($filtro_aparato) ?>">
                                                <input type="hidden" name="dedo" value="<?= htmlspecialchars($filtro_dedo) ?>">
                                                
                                                <label for="ir_pagina" class="mr-2">Ir a página:</label>
                                                <input type="number" class="form-control form-control-sm mr-2" 
                                                       id="ir_pagina" name="page" 
                                                       min="1" max="<?= $total_pages ?>" 
                                                       value="<?= $page ?>" style="width: 80px;">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-arrow-right"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                    
                                    <div class="text-center text-muted mt-2">
                                        Página <?= $page ?> de <?= $total_pages ?> 
                                        (<?= $total_records ?> registros totales)
                                    </div>
                                </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="unidades-section" class="content-section <?= (isset($mensaje_unidades) && !empty($mensaje_unidades)) ? 'active' : '' ?>">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="fas fa-building mr-2"></i>Gestión de Unidades</h5>
                                <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
                                <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearUnidad">
                                    <i class="fas fa-plus"></i> Nueva Unidad
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (isset($mensaje_unidades) && !empty($mensaje_unidades)): ?>
                                <div class="alert alert-<?= $tipo_mensaje_unidades ?> alert-dismissible fade show" role="alert">
                                    <?= htmlspecialchars($mensaje_unidades) ?>
                                    <button type="button" class="close" data-dismiss="alert">
                                        <span>&times;</span>
                                    </button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Nombre</th>
                                                <th>Estado</th>
                                                <th>Postulantes</th>
                                                <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
                                                <th>Acciones</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($unidades_data)): ?>
                                            <tr>
                                                <td colspan="<?= in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN']) ? '4' : '3' ?>" class="text-center text-muted py-4">
                                                    <i class="fas fa-building fa-2x mb-2"></i><br>
                                                    No hay unidades registradas.
                                                </td>
                                            </tr>
                                            <?php else: ?>
                                                <?php foreach ($unidades_data as $unidad): ?>
                                                <?php
                                                // Contar postulantes por unidad
                                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM postulantes WHERE unidad = ?");
                                                $stmt->execute([$unidad['nombre']]);
                                                $postulantes_count = $stmt->fetch()['count'];
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($unidad['nombre']) ?></strong>
                                                    </td>
                                                    <td>
                                                        <?php if ($unidad['activa']): ?>
                                                            <span class="badge badge-success">Activa</span>
                                                        <?php else: ?>
                                                            <span class="badge badge-secondary">Inactiva</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge badge-info"><?= $postulantes_count ?> postulante(s)</span>
                                                    </td>
                                                    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
                                                    <td>
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    onclick="editarUnidad(<?= htmlspecialchars(json_encode($unidad)) ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="eliminarUnidad(<?= $unidad['id'] ?>, '<?= htmlspecialchars($unidad['nombre']) ?>', <?= $postulantes_count ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                    <?php endif; ?>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="estadisticas-section" class="content-section">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-bar mr-2"></i>Estadísticas y Reportes</h5>
                            </div>
                            <div class="card-body">
                                <!-- Pestañas -->
                                <ul class="nav nav-tabs" id="estadisticasTabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'reporte') ? 'active' : '' ?>" id="estadisticas-tab" data-toggle="tab" href="#estadisticas-content" role="tab" aria-controls="estadisticas-content" aria-selected="<?= (!isset($_GET['tab']) || $_GET['tab'] !== 'reporte') ? 'true' : 'false' ?>">
                                            <i class="fas fa-chart-bar mr-1"></i> Estadísticas Generales
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link <?= (isset($_GET['tab']) && $_GET['tab'] === 'reporte') ? 'active' : '' ?>" id="reporte-tab" data-toggle="tab" href="#reporte-content" role="tab" aria-controls="reporte-content" aria-selected="<?= (isset($_GET['tab']) && $_GET['tab'] === 'reporte') ? 'true' : 'false' ?>">
                                            <i class="fas fa-file-alt mr-1"></i> Reporte Diario
                                        </a>
                                    </li>
                                </ul>
                                
                                <!-- Contenido de las pestañas -->
                                <div class="tab-content" id="estadisticasTabsContent">
                                    <!-- Pestaña de Estadísticas Generales -->
                                    <div class="tab-pane fade <?= (!isset($_GET['tab']) || $_GET['tab'] !== 'reporte') ? 'show active' : '' ?>" id="estadisticas-content" role="tabpanel" aria-labelledby="estadisticas-tab">
                                        <div class="mt-3">
                                            <div class="d-flex justify-content-end mb-3">
                                                <button type="button" class="btn btn-warning btn-sm" onclick="inicializarGraficos()">
                                                    <i class="fas fa-sync-alt"></i> Recargar Gráficos
                                                </button>
                                            </div>
                                <!-- Estadísticas Generales -->
                                <div class="row mb-4">
                                    <div class="col-md-3">
                                        <div class="stats-card bg-primary text-white">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-uppercase text-white-50 mb-1">Total Postulantes</h6>
                                                    <h2 class="mb-0 text-white"><?= number_format($total_postulantes, 0, ',', '.') ?></h2>
                                                </div>
                                                <div class="stats-icon text-white">
                                                    <i class="fas fa-users"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card bg-success text-white">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-uppercase text-white-50 mb-1">Hoy</h6>
                                                    <h2 class="mb-0 text-white"><?= number_format($postulantes_hoy, 0, ',', '.') ?></h2>
                                                </div>
                                                <div class="stats-icon text-white">
                                                    <i class="fas fa-calendar-day"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card bg-info text-white">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-uppercase text-white-50 mb-1">Esta Semana</h6>
                                                    <h2 class="mb-0 text-white"><?= number_format($postulantes_semana, 0, ',', '.') ?></h2>
                                                </div>
                                                <div class="stats-icon text-white">
                                                    <i class="fas fa-calendar-week"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="stats-card bg-warning text-white">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="text-uppercase text-white-50 mb-1">Este Mes</h6>
                                                    <h2 class="mb-0 text-white"><?= number_format($postulantes_mes, 0, ',', '.') ?></h2>
                                                </div>
                                                <div class="stats-icon text-white">
                                                    <i class="fas fa-calendar-alt"></i>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Promedio Diario -->
                                <div class="row mb-4">
                                    <div class="col-md-12">
                                        <div class="card bg-light">
                                            <div class="card-body">
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <h5 class="card-title">Promedio de Registros Diarios</h5>
                                                        <h2 class="text-primary"><?= number_format($promedio_diario, 1, ',', '.') ?> postulantes/día</h2>
                                                        <small class="text-muted" id="rango_fechas_texto"><?= $rango_fechas_texto ?? 'Últimos 30 días' ?></small>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <form method="GET" id="promedioForm" class="form-inline">
                                                            <input type="hidden" name="tab" value="estadisticas">
                                                            <div class="form-group mb-2">
                                                                <label for="fecha_desde_promedio" class="mr-2">Desde:</label>
                                                                <input type="date" class="form-control form-control-sm mr-2" id="fecha_desde_promedio" name="fecha_desde_promedio" value="<?= htmlspecialchars($fecha_desde_promedio) ?>">
                                                            </div>
                                                            <div class="form-group mb-2">
                                                                <label for="fecha_hasta_promedio" class="mr-2">Hasta:</label>
                                                                <input type="date" class="form-control form-control-sm mr-2" id="fecha_hasta_promedio" name="fecha_hasta_promedio" value="<?= $_GET['fecha_hasta_promedio'] ?? date('Y-m-d') ?>">
                                                            </div>
                                                            <button type="submit" class="btn btn-primary btn-sm">
                                                                <i class="fas fa-calculator"></i> Calcular
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gráficos y Distribuciones -->
                                <div class="row">
                                    <!-- Gráfico de Registros por Día -->
                                    <div class="col-lg-12 mb-5">
                                        <div class="card">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0">Registros por Día</h6>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalRegistrosDia()">
                                                    <i class="fas fa-expand-arrows-alt"></i> Ampliar
                                                </button>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <canvas id="graficoRegistrosDia" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>

                                </div>

                                <!-- Gráfico de Distribución por Unidad -->
                                <div class="row">
                                    <div class="col-lg-12 mb-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Distribución por Unidad</h6>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <canvas id="graficoUnidades" height="400"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Distribución por Dedos -->
                                    <div class="col-lg-6 mb-5">
                                        <div class="card" style="min-height: 350px;">
                                            <div class="card-header">
                                                <h6 class="mb-0">Distribución por Dedos</h6>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <canvas id="graficoDedos" height="320"></canvas>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Distribución por Sexo -->
                                    <div class="col-lg-6 mb-5">
                                        <div class="card" style="min-height: 350px;">
                                            <div class="card-header">
                                                <h6 class="mb-0">Distribución por Sexo</h6>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <canvas id="graficoSexo" height="320"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <!-- Usuarios Más Activos -->
                                    <div class="col-lg-12 mb-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Usuarios Más Activos</h6>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <div class="table-responsive">
                                                    <table class="table table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Usuario</th>
                                                                <th>Registros</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php if (!empty($usuarios_activos)): ?>
                                                                <?php foreach ($usuarios_activos as $usuario): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                                                                    <td><span class="badge badge-success"><?= $usuario['registros'] ?></span></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <tr>
                                                                    <td colspan="2" class="text-center text-muted">
                                                                        <i class="fas fa-users fa-2x mb-2"></i><br>
                                                                        No hay datos disponibles
                                                                    </td>
                                                                </tr>
                                                            <?php endif; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Horarios de Mayor Actividad -->
                                <div class="row">
                                    <div class="col-lg-12 mb-5">
                                        <div class="card">
                                            <div class="card-header">
                                                <h6 class="mb-0">Horarios de Mayor Actividad</h6>
                                            </div>
                                            <div class="card-body" style="padding: 2rem;">
                                                <canvas id="graficoHorarios" height="200"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Pestaña de Reporte Diario -->
                                    <div class="tab-pane fade <?= (isset($_GET['tab']) && $_GET['tab'] === 'reporte') ? 'show active' : '' ?>" id="reporte-content" role="tabpanel" aria-labelledby="reporte-tab">
                                        <div class="mt-3">
                                            <!-- Selector de fecha y franjas horarias -->
                                            <div class="row mb-4">
                                                <div class="col-md-8">
                                                    <form method="GET" id="fechaForm">
                                                        <input type="hidden" name="tab" value="reporte">
                                                        <div class="row">
                                                            <div class="col-md-4">
                                                                <label for="fecha_reporte" class="form-label">Fecha:</label>
                                                                <input type="date" class="form-control" id="fecha_reporte" name="fecha_reporte" value="<?= $fecha_reporte ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="hora_desde" class="form-label">Desde:</label>
                                                                <input type="time" class="form-control" id="hora_desde" name="hora_desde" value="<?= $_GET['hora_desde'] ?? '00:00' ?>">
                                                            </div>
                                                            <div class="col-md-3">
                                                                <label for="hora_hasta" class="form-label">Hasta:</label>
                                                                <input type="time" class="form-control" id="hora_hasta" name="hora_hasta" value="<?= $_GET['hora_hasta'] ?? '23:59' ?>">
                                                            </div>
                                                            <div class="col-md-2">
                                                                <label class="form-label">&nbsp;</label>
                                                                <button type="submit" class="btn btn-primary btn-block">
                                                                    <i class="fas fa-calendar-check"></i> Aplicar
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <div class="row mt-2">
                                                            <div class="col-md-12">
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-outline-primary" onclick="seleccionarFranja('completo')">
                                                                        <i class="fas fa-clock"></i> Día Completo
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                                <div class="col-md-4 text-right">
                                                    <div class="btn-group-vertical btn-group-sm">
                                                        <button type="button" class="btn btn-success" onclick="generarReporteDiarioEspecifico()">
                                                            <i class="fas fa-file-word"></i> Generar Reporte
                                                        </button>
                                                        <!-- <button type="button" class="btn btn-info" onclick="exportarReporteHorario()">
                                                            <i class="fas fa-download"></i> Exportar Excel
                                                        </button> -->
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Resumen del día -->
                                            <div class="row mb-4">
                                                <div class="col-md-12">
                                                    <div class="card bg-light">
                                                        <div class="card-body text-center">
                                                            <h5 class="card-title">
                                                                Reporte del <?= date('d/m/Y', strtotime($fecha_reporte)) ?>
                                                                <?php if (isset($_GET['hora_desde']) && isset($_GET['hora_hasta'])): ?>
                                                                    <br><small class="text-muted">
                                                                        Franja horaria: <?= $_GET['hora_desde'] ?> - <?= $_GET['hora_hasta'] ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </h5>
                                                            <h2 class="text-primary"><?= number_format($postulantes_fecha) ?> postulante(s) registrado(s)</h2>
                                                            <?php if (isset($_GET['hora_desde']) && isset($_GET['hora_hasta']) && ($_GET['hora_desde'] !== '00:00' || $_GET['hora_hasta'] !== '23:59')): ?>
                                                                <p class="text-info mb-0">
                                                                    <i class="fas fa-info-circle"></i> 
                                                                    Filtrado por franja horaria específica
                                                                </p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Postulantes por unidad -->
                                            <div class="row mb-4">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Postulantes por Unidad</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <?php if (empty($postulantes_por_unidad_fecha)): ?>
                                                            <p class="text-muted text-center">No hay registros para esta fecha</p>
                                                            <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Unidad</th>
                                                                            <th>Cantidad</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($postulantes_por_unidad_fecha as $unidad): ?>
                                                                        <tr>
                                                                            <td><?= str_replace('&quot;', '"', htmlspecialchars($unidad['unidad'])) ?></td>
                                                                            <td><span class="badge badge-primary"><?= $unidad['cantidad'] ?></span></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                
                                                <!-- Aparatos utilizados -->
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Aparatos Biométricos Utilizados</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <?php if (empty($aparatos_utilizados_fecha)): ?>
                                                            <p class="text-muted text-center">No hay registros para esta fecha</p>
                                                            <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Dispositivo</th>
                                                                            <th>Registros</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($aparatos_utilizados_fecha as $aparato): ?>
                                                                        <tr>
                                                                            <td><?= htmlspecialchars($aparato['dispositivo']) ?></td>
                                                                            <td><span class="badge badge-success"><?= $aparato['cantidad'] ?></span></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Distribución de usuarios registradores -->
                                            <div class="row mb-4">
                                                <div class="col-md-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Distribución de Usuarios Registradores</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <?php if (empty($usuarios_registradores_fecha)): ?>
                                                            <p class="text-muted text-center">No hay registros para esta fecha</p>
                                                            <?php else: ?>
                                                            <div class="table-responsive">
                                                                <table class="table table-sm">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Usuario</th>
                                                                            <th>Dispositivo</th>
                                                                            <th>Registros</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php foreach ($usuarios_registradores_fecha as $usuario): ?>
                                                                        <tr>
                                                                            <td><strong><?= htmlspecialchars($usuario['usuario']) ?></strong></td>
                                                                            <td><?= htmlspecialchars($usuario['dispositivo']) ?></td>
                                                                            <td><span class="badge badge-info"><?= $usuario['cantidad'] ?></span></td>
                                                                        </tr>
                                                                        <?php endforeach; ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Horarios de registro -->
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Primer Registro del Día</h6>
                                                        </div>
                                                        <div class="card-body text-center">
                                                            <?php if ($horarios_registro['primer_registro']): ?>
                                                            <h4 class="text-success"><?= date('H:i:s', strtotime($horarios_registro['primer_registro'])) ?></h4>
                                                            <small class="text-muted"><?= date('d/m/Y', strtotime($horarios_registro['primer_registro'])) ?></small>
                                                            <?php else: ?>
                                                            <p class="text-muted">No hay registros</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Último Registro del Día</h6>
                                                        </div>
                                                        <div class="card-body text-center">
                                                            <?php if ($horarios_registro['ultimo_registro']): ?>
                                                            <h4 class="text-danger"><?= date('H:i:s', strtotime($horarios_registro['ultimo_registro'])) ?></h4>
                                                            <small class="text-muted"><?= date('d/m/Y', strtotime($horarios_registro['ultimo_registro'])) ?></small>
                                                            <?php else: ?>
                                                            <p class="text-muted">No hay registros</p>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if ($_SESSION['rol'] === 'SUPERADMIN'): ?>
                    <div id="configuracion-section" class="content-section">
                        <div class="row">
                            <!-- Backup de Base de Datos -->
                            <div class="col-lg-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-download mr-2"></i>Backup de Base de Datos</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted mb-3">Descargue una copia completa de la base de datos del sistema.</p>
                                        <button class="btn btn-primary" onclick="generarBackup()">
                                            <i class="fas fa-database mr-2"></i>Generar Backup
                                        </button>
                                        <div id="backup-status" class="mt-3" style="display: none;">
                                            <div class="alert alert-info">
                                                <i class="fas fa-spinner fa-spin mr-2"></i>
                                                Generando backup, por favor espere...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Configuración General -->
                            <div class="col-lg-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0"><i class="fas fa-cog mr-2"></i>Configuración General</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted">Módulo de configuración en desarrollo...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <!-- END Content -->

        <!-- Footer -->
        <div class="bg-white">
        </div>
    </div>
    <!-- END Page Container -->

    <!-- Modales de Gestión de Usuarios -->
    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
    <!-- Modal Crear Usuario -->
    <div class="modal fade" id="modalCrearUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_usuario">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nuevo Usuario</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="usuario">Usuario *</label>
                            <input type="text" class="form-control" id="usuario" name="usuario" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Contraseña *</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="apellido">Apellido *</label>
                            <input type="text" class="form-control" id="apellido" name="apellido" required>
                        </div>
                        <div class="form-group">
                            <label for="grado">Grado</label>
                            <input type="text" class="form-control" id="grado" name="grado">
                        </div>
                        <div class="form-group">
                            <label for="cedula">Cédula</label>
                            <input type="text" class="form-control" id="cedula" name="cedula">
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono</label>
                            <input type="text" class="form-control" id="telefono" name="telefono">
                        </div>
                        <div class="form-group">
                            <label for="rol">Rol *</label>
                            <select class="form-control" id="rol" name="rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?= htmlspecialchars($rol['nombre']) ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Usuario -->
    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="editar_usuario">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Usuario</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_usuario">Usuario *</label>
                            <input type="text" class="form-control" id="edit_usuario" name="usuario" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_nombre">Nombre *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_apellido">Apellido *</label>
                            <input type="text" class="form-control" id="edit_apellido" name="apellido" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_grado">Grado</label>
                            <input type="text" class="form-control" id="edit_grado" name="grado">
                        </div>
                        <div class="form-group">
                            <label for="edit_cedula">Cédula</label>
                            <input type="text" class="form-control" id="edit_cedula" name="cedula">
                        </div>
                        <div class="form-group">
                            <label for="edit_telefono">Teléfono</label>
                            <input type="text" class="form-control" id="edit_telefono" name="telefono">
                        </div>
                        <div class="form-group">
                            <label for="edit_rol">Rol *</label>
                            <select class="form-control" id="edit_rol" name="rol" required>
                                <option value="">Seleccionar rol</option>
                                <?php foreach ($roles as $rol): ?>
                                <option value="<?= htmlspecialchars($rol['nombre']) ?>"><?= htmlspecialchars($rol['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Restaurar Contraseña -->
    <div class="modal fade" id="modalCambiarPassword" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="cambiar_password">
                    <input type="hidden" name="id" id="pass_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Restaurar Contraseña</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label>Usuario: <strong id="pass_usuario"></strong></label>
                        </div>
                        <div class="form-group">
                            <label for="nueva_password">Nueva Contraseña *</label>
                            <input type="password" class="form-control" id="nueva_password" name="password" required>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Importante:</strong> El usuario deberá cambiar esta contraseña en su próximo login.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Restaurar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Usuario -->
    <div class="modal fade" id="modalEliminarUsuario" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="eliminar_usuario">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Eliminar Usuario</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>¿Estás seguro de que deseas eliminar al usuario <strong id="delete_usuario"></strong>?</p>
                        <p class="text-danger"><strong>Esta acción no se puede deshacer.</strong></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Usuario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modales de Gestión de Dispositivos Biométricos -->
    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
    <!-- Modal Crear Dispositivo -->
    <div class="modal fade" id="modalCrearDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_dispositivo">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nuevo Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="serial">Número de Serie *</label>
                            <input type="text" class="form-control" id="serial" name="serial" required>
                        </div>
                        <div class="form-group">
                            <label for="ip_address">Dirección IP</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" placeholder="192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label for="puerto">Puerto</label>
                            <input type="number" class="form-control" id="puerto" name="puerto" value="4370">
                        </div>
                        <div class="form-group">
                            <label for="ubicacion">Ubicación</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion">
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select class="form-control" id="estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <?php foreach ($estados_dispositivos as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Dispositivo -->
    <div class="modal fade" id="modalEditarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="editar_dispositivo">
                    <input type="hidden" name="id" id="edit_dispositivo_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_nombre">Nombre *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_serial">Número de Serie *</label>
                            <input type="text" class="form-control" id="edit_serial" name="serial" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_ip_address">Dirección IP</label>
                            <input type="text" class="form-control" id="edit_ip_address" name="ip_address">
                        </div>
                        <div class="form-group">
                            <label for="edit_puerto">Puerto</label>
                            <input type="number" class="form-control" id="edit_puerto" name="puerto">
                        </div>
                        <div class="form-group">
                            <label for="edit_ubicacion">Ubicación</label>
                            <input type="text" class="form-control" id="edit_ubicacion" name="ubicacion">
                        </div>
                        <div class="form-group">
                            <label for="edit_estado">Estado *</label>
                            <select class="form-control" id="edit_estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <?php foreach ($estados_dispositivos as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Dispositivo -->
    <div class="modal fade" id="modalEliminarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Dispositivo</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="delete_message">¿Estás seguro de que deseas eliminar el dispositivo <strong id="delete_nombre"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarEliminacion()">Eliminar Dispositivo</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal Ver Detalles Postulante -->
    <div class="modal fade" id="modalDetallesPostulante" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user mr-2"></i>Detalles del Postulante</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-id-card mr-1"></i>Información Personal</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td id="detalle_id">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre Completo:</strong></td>
                                    <td id="detalle_nombre_completo">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Cédula:</strong></td>
                                    <td id="detalle_cedula">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Sexo:</strong></td>
                                    <td id="detalle_sexo">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Fecha Nacimiento:</strong></td>
                                    <td id="detalle_fecha_nacimiento">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Edad:</strong></td>
                                    <td id="detalle_edad">-</td>
                                </tr>
                                <tr style="display: none;">
                                    <td><strong>Teléfono:</strong></td>
                                    <td id="detalle_telefono">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="fas fa-fingerprint mr-1"></i>Información del registro</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Dedo Registrado:</strong></td>
                                    <td id="detalle_dedo_registrado">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Aparato:</strong></td>
                                    <td id="detalle_aparato">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Unidad:</strong></td>
                                    <td id="detalle_unidad">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Registrado Por:</strong></td>
                                    <td id="detalle_registrado_por">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Capturador huella:</strong></td>
                                    <td id="detalle_capturador">-</td>
                                </tr>
                            </table>
                            
                            <table class="table table-sm table-borderless mt-3">
                                <tr>
                                    <td><strong>Fecha Registro:</strong></td>
                                    <td id="detalle_fecha_registro">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Última Edición:</strong></td>
                                    <td id="detalle_fecha_ultima_edicion">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Sección de Observaciones -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-primary font-weight-bold mb-3">
                                <i class="fas fa-sticky-note"></i> OBSERVACIONES
                            </h6>
                            <div class="card">
                                <div class="card-body">
                                    <div id="detalle_observaciones" class="observaciones-content" style="max-height: 200px; overflow-y: auto; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6;">
                                        <em class="text-muted">Sin observaciones registradas</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Historial de Modificaciones -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-primary font-weight-bold mb-3">
                                <i class="fas fa-history"></i> HISTORIAL DE MODIFICACIONES
                            </h6>
                            <div class="card">
                                <div class="card-body">
                                    <div id="detalle_historial" class="historial-content" style="max-height: 200px; overflow-y: auto;">
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                            No hay modificaciones registradas
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modales de Unidades -->
    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
    <!-- Modal Crear Unidad -->
    <div class="modal fade" id="modalCrearUnidad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_unidad">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nueva Unidad</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre">Nombre de la Unidad *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="activa" name="activa" checked>
                                <label class="form-check-label" for="activa">
                                    Unidad activa
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Unidad</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Editar Unidad -->
    <div class="modal fade" id="modalEditarUnidad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="editar_unidad">
                    <input type="hidden" name="id" id="edit_unidad_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Unidad</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_nombre">Nombre de la Unidad *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="edit_activa" name="activa">
                                <label class="form-check-label" for="edit_activa">
                                    Unidad activa
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Unidad</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Eliminar Unidad -->
    <div class="modal fade" id="modalEliminarUnidad" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Eliminar Unidad</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="delete_unidad_message">¿Estás seguro de que deseas eliminar la unidad <strong id="delete_unidad_nombre"></strong>?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmarEliminacionUnidad()">Eliminar Unidad</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modales de Dispositivos -->
    <?php if (in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])): ?>
    <!-- Modal Crear Dispositivo -->
    <div class="modal fade" id="modalCrearDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear_dispositivo">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nuevo Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="serial">Número de Serie *</label>
                            <input type="text" class="form-control" id="serial" name="serial" required>
                        </div>
                        <div class="form-group">
                            <label for="ip_address">Dirección IP</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" placeholder="192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label for="puerto">Puerto</label>
                            <input type="number" class="form-control" id="puerto" name="puerto" value="4370">
                        </div>
                        <div class="form-group">
                            <label for="ubicacion">Ubicación</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion">
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select class="form-control" id="estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <?php foreach ($estados_dispositivos as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js" integrity="sha256-ZaXnYkHGqIhqTbJ6MB4l9Frs/r7U4jlx7ir8PJYBqbI=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/docx@7.8.2/build/index.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/dist/FileSaver.min.js"></script>

    <script>
        function showSection(sectionName) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Update URL hash
            window.location.hash = sectionName;
            
            // Inicializar gráficos si es la sección de estadísticas
            if (sectionName === 'estadisticas') {
                setTimeout(function() {
                    console.log('Inicializando gráficos...');
                    console.log('Chart.js disponible:', typeof Chart !== 'undefined');
                    inicializarGraficos();
                }, 500);
            }
        }
        
        // Función para mostrar una sección específica sin evento
        function showSectionDirect(sectionName) {
            // Hide all sections
            document.querySelectorAll('.content-section').forEach(section => {
                section.classList.remove('active');
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').classList.add('active');
            
            // Update navigation
            document.querySelectorAll('.nav-link').forEach(link => {
                link.classList.remove('active');
            });
            
            // Activar el enlace correspondiente
            const targetLink = document.querySelector(`a[href="#${sectionName}"]`);
            if (targetLink) {
                targetLink.classList.add('active');
            }
            
            // Update URL hash
            window.location.hash = sectionName;
            
            // Inicializar gráficos si es la sección de estadísticas
            if (sectionName === 'estadisticas') {
                setTimeout(function() {
                    console.log('Inicializando gráficos...');
                    console.log('Chart.js disponible:', typeof Chart !== 'undefined');
                    inicializarGraficos();
                }, 500);
            }
        }
        
        // Verificar si hay mensajes y mostrar la sección correspondiente
        <?php if (isset($mensaje_usuarios) && !empty($mensaje_usuarios)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showSectionDirect('usuarios');
        });
        <?php elseif (isset($mensaje_dispositivos) && !empty($mensaje_dispositivos)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showSectionDirect('dispositivos');
        });
        <?php elseif (isset($mensaje_unidades) && !empty($mensaje_unidades)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            showSectionDirect('unidades');
        });
        <?php else: ?>
        // Verificar hash en la URL al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            const hash = window.location.hash.substring(1); // Remover el #
            if (hash && hash !== 'dashboard') {
                // Verificar que la sección existe antes de mostrarla
                const validSections = ['usuarios', 'dispositivos', 'postulantes', 'unidades', 'estadisticas'];
                if (validSections.includes(hash)) {
                    showSectionDirect(hash);
                }
            }
        });
        <?php endif; ?>
        
        // Funciones para gestión de usuarios
        function editarUsuario(usuario) {
            document.getElementById('edit_id').value = usuario.id;
            document.getElementById('edit_usuario').value = usuario.usuario;
            document.getElementById('edit_nombre').value = usuario.nombre;
            document.getElementById('edit_apellido').value = usuario.apellido;
            document.getElementById('edit_grado').value = usuario.grado || '';
            document.getElementById('edit_cedula').value = usuario.cedula || '';
            document.getElementById('edit_telefono').value = usuario.telefono || '';
            document.getElementById('edit_rol').value = usuario.rol;
            
            $('#modalEditarUsuario').modal('show');
        }
        
        function cambiarPassword(id, usuario) {
            document.getElementById('pass_id').value = id;
            document.getElementById('pass_usuario').textContent = usuario;
            document.getElementById('nueva_password').value = '';
            
            $('#modalCambiarPassword').modal('show');
        }
        
        function eliminarUsuario(id, usuario) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_usuario').textContent = usuario;
            
            $('#modalEliminarUsuario').modal('show');
        }
        
        // Funciones para gestión de dispositivos
        function editarDispositivo(dispositivo) {
            document.getElementById('edit_dispositivo_id').value = dispositivo.id;
            document.getElementById('edit_nombre').value = dispositivo.nombre;
            document.getElementById('edit_serial').value = dispositivo.serial;
            document.getElementById('edit_ip_address').value = dispositivo.ip_address || '';
            document.getElementById('edit_puerto').value = dispositivo.puerto || '';
            document.getElementById('edit_ubicacion').value = dispositivo.ubicacion || '';
            document.getElementById('edit_estado').value = dispositivo.estado;
            
            $('#modalEditarDispositivo').modal('show');
        }
        
        function eliminarDispositivo(id, nombre, postulantesCount) {
            console.log('Eliminando dispositivo ID:', id, 'Nombre:', nombre, 'Postulantes:', postulantesCount);
            
            // Almacenar los datos en variables globales para usar en el modal
            window.dispositivoAEliminar = {
                id: id,
                nombre: nombre,
                postulantesCount: postulantesCount
            };
            
            // Actualizar el contenido del modal
            const deleteNombreElement = document.getElementById('delete_nombre');
            const modalMessage = document.getElementById('delete_message');
            
            if (deleteNombreElement) {
                deleteNombreElement.textContent = nombre;
            }
            
            // Actualizar el mensaje del modal según si hay postulantes
            if (modalMessage) {
                if (postulantesCount > 0) {
                    modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar el dispositivo <strong>${nombre}</strong>?<br><br><span class="text-info"><i class="fas fa-info-circle"></i> <strong>Nota:</strong> Este dispositivo está siendo usado por ${postulantesCount} postulante(s). El nombre del dispositivo se preservará en los registros de los postulantes.</span><br><br><span class="text-danger"><strong>Esta acción no se puede deshacer.</strong></span>`;
                } else {
                    modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar el dispositivo <strong>${nombre}</strong>?<br><br><span class="text-danger"><strong>Esta acción no se puede deshacer.</strong></span>`;
                }
            }
            
            $('#modalEliminarDispositivo').modal('show');
        }
        
        // Función para confirmar eliminación desde el modal
        function confirmarEliminacion() {
            if (window.dispositivoAEliminar) {
                const { id, nombre, postulantesCount } = window.dispositivoAEliminar;
                
                // Crear y enviar formulario dinámicamente
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar_dispositivo';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(accionInput);
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Funciones para gestión de postulantes
        function verDetallesPostulante(postulante, buttonElement) {
            // Si no se pasa el elemento del botón, intentar obtenerlo del evento
            if (!buttonElement && event && event.target) {
                buttonElement = event.target.closest('button');
            }
            
            // Obtener ID del postulante (del objeto o del atributo data)
            let postulanteId = postulante.id || postulante[0]; // Manejar tanto objeto como array
            if (!postulanteId && buttonElement) {
                postulanteId = buttonElement.getAttribute('data-postulante-id');
            }
            
            // Llenar los campos del modal con los datos del postulante
            document.getElementById('detalle_id').textContent = postulanteId || '-';
            // Asegurar que nombre_completo se muestre correctamente (con fallback para compatibilidad)
            const nombreCompleto = postulante.nombre_completo || 
                                   (postulante.nombre && postulante.apellido ? (postulante.nombre + ' ' + postulante.apellido) : null) ||
                                   postulante[1] || '-';
            document.getElementById('detalle_nombre_completo').textContent = nombreCompleto;
            document.getElementById('detalle_cedula').textContent = (postulante.cedula || postulante[3]) ? String(postulante.cedula || postulante[3]) : '-';
            document.getElementById('detalle_sexo').textContent = (postulante.sexo || postulante[15]) ? String(postulante.sexo || postulante[15]) : '-';
            
            // Fecha de nacimiento
            const fechaNacimiento = postulante.fecha_nacimiento || postulante[4];
            if (fechaNacimiento) {
                const fechaNac = new Date(fechaNacimiento);
                document.getElementById('detalle_fecha_nacimiento').textContent = fechaNac.toLocaleDateString('es-ES');
            } else {
                document.getElementById('detalle_fecha_nacimiento').textContent = '-';
            }
            
            const edad = postulante.edad || postulante[8];
            document.getElementById('detalle_edad').textContent = (edad && edad !== null && edad !== '') ? edad + ' años' : '-';
            const telefono = postulante.telefono || postulante[5];
            document.getElementById('detalle_telefono').textContent = (telefono && telefono !== null && telefono !== '') ? telefono : '-';
            
            // Información biométrica
            const dedoRegistrado = postulante.dedo_registrado || postulante[10];
            if (dedoRegistrado && dedoRegistrado !== null && dedoRegistrado !== '') {
                document.getElementById('detalle_dedo_registrado').innerHTML = '<span class="badge badge-success">' + String(dedoRegistrado) + '</span>';
            } else {
                document.getElementById('detalle_dedo_registrado').innerHTML = '<span class="badge badge-secondary">No registrado</span>';
            }
            
            // Aparato (con fallback)
            const aparatoNombre = postulante.aparato_nombre_actual || postulante.aparato_nombre || postulante[16];
            if (aparatoNombre && aparatoNombre !== null && aparatoNombre !== '') {
                document.getElementById('detalle_aparato').innerHTML = '<span class="badge badge-primary">' + String(aparatoNombre) + '</span>';
            } else {
                document.getElementById('detalle_aparato').innerHTML = '<span class="badge badge-secondary">Sin aparato</span>';
            }
            
            const unidad = postulante.unidad || postulante[9];
            if (unidad && unidad !== null && unidad !== '') {
                document.getElementById('detalle_unidad').innerHTML = '<span class="badge badge-info">' + String(unidad) + '</span>';
            } else {
                document.getElementById('detalle_unidad').innerHTML = '<span class="badge badge-secondary">Sin unidad</span>';
            }
            
            const registradoPor = postulante.registrado_por || postulante[12];
            document.getElementById('detalle_registrado_por').textContent = (registradoPor && registradoPor !== null && registradoPor !== '') ? String(registradoPor) : '-';
            
            // Capturador
            const capturador = postulante.capturador_nombre || postulante.capturador;
            document.getElementById('detalle_capturador').textContent = (capturador && capturador !== null && capturador !== '') ? String(capturador) : '-';
            
            // Información de registro
            const fechaRegistro = postulante.fecha_registro || postulante[6];
            if (fechaRegistro) {
                const fechaReg = new Date(fechaRegistro);
                document.getElementById('detalle_fecha_registro').textContent = fechaReg.toLocaleString('es-ES');
            } else {
                document.getElementById('detalle_fecha_registro').textContent = '-';
            }
            
            const fechaUltimaEdicion = postulante.fecha_ultima_edicion || postulante[14];
            if (fechaUltimaEdicion) {
                const fechaUlt = new Date(fechaUltimaEdicion);
                document.getElementById('detalle_fecha_ultima_edicion').textContent = fechaUlt.toLocaleString('es-ES');
            } else {
                document.getElementById('detalle_fecha_ultima_edicion').textContent = '-';
            }
            
            // Observaciones con formato mejorado
            const observaciones = postulante.observaciones || postulante[7];
            // Asegurarse de que observaciones sea una cadena antes de usar .trim()
            if (observaciones && typeof observaciones === 'string' && observaciones.trim()) {
                // Procesar observaciones para formato mejorado
                let observacionesFormateadas = observaciones
                    .replace(/\[([^\]]+)\]/g, '<span class="badge badge-info">[$1]</span>')
                    .replace(/\n/g, '<br>');
                document.getElementById('detalle_observaciones').innerHTML = observacionesFormateadas;
            } else {
                document.getElementById('detalle_observaciones').innerHTML = '<em class="text-muted">Sin observaciones registradas</em>';
            }
            
            // Historial de modificaciones - cargar desde servidor
            console.log('Objeto postulante completo:', postulante); // Debug
            console.log('ID del postulante (del objeto):', postulante.id); // Debug
            console.log('ID del postulante (calculado):', postulanteId); // Debug
            cargarHistorialModificaciones(postulanteId);
            
            // Mostrar el modal
            $('#modalDetallesPostulante').modal('show');
        }
        
        function cargarHistorialModificaciones(postulanteId) {
            console.log('Cargando historial para postulante ID:', postulanteId); // Debug
            
            // Validar que el ID esté disponible
            if (!postulanteId || postulanteId === 'undefined' || postulanteId === 'null') {
                console.error('ID de postulante no válido:', postulanteId);
                document.getElementById('detalle_historial').innerHTML = `
                    <div class="alert alert-warning">
                        Error: ID de postulante no válido
                    </div>
                `;
                return;
            }
            
            // Cargar historial de modificaciones desde el servidor usando fetch con configuración explícita
            const formData = new FormData();
            formData.append('postulante_id', postulanteId);
            
            console.log('Datos POST enviados:', 'postulante_id=' + postulanteId);
            
            // Intentar primero con POST
            fetch('obtener_historial.php', {
                method: 'POST',
                body: formData,
                cache: 'no-cache',
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Status de respuesta:', response.status);
                console.log('Headers:', response.headers);
                
                // Si POST falla con 400, intentar con GET
                if (response.status === 400) {
                    console.log('POST falló, intentando con GET...');
                    return fetch(`obtener_historial.php?postulante_id=${postulanteId}`, {
                        method: 'GET',
                        cache: 'no-cache',
                        credentials: 'same-origin'
                    }).then(getResponse => {
                        console.log('Status GET:', getResponse.status);
                        return getResponse.text();
                    });
                }
                
                return response.text();
            })
            .then(responseText => {
                console.log('Respuesta completa:', responseText);
                try {
                    const data = JSON.parse(responseText);
                    console.log('Respuesta del historial:', data);
                    
                    let historialHTML = '';
                    
                    if (data.success && data.historial && data.historial.length > 0) {
                        // Mostrar historial completo
                        data.historial.forEach((edicion, index) => {
                            const fechaEdicion = new Date(edicion.fecha_edicion).toLocaleString('es-ES');
                            let cambiosHTML = '';
                            
                            if (edicion.cambios) {
                                const cambios = edicion.cambios.split('; ');
                                cambiosHTML = '<div class="mt-2"><small class="text-muted"><strong>Cambios realizados:</strong></small><br>';
                                cambios.forEach(cambio => {
                                    if (cambio.trim()) {
                                        cambiosHTML += '<small class="text-dark">• ' + cambio.trim() + '</small><br>';
                                    }
                                });
                                cambiosHTML += '</div>';
                            }
                            
                            historialHTML += `
                                <div class="historial-item mb-3 p-3" style="background-color: #e8f4fd; border-left: 4px solid #2E5090; border-radius: 5px;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 text-primary"><i class="fas fa-user-edit"></i> ${edicion.usuario_editor}</h6>
                                            <small class="text-muted"><i class="fas fa-clock"></i> ${fechaEdicion}</small>
                                            ${cambiosHTML}
                                        </div>
                                        <span class="badge badge-primary">Edición #${data.historial.length - index}</span>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        // Mostrar información básica si no hay historial detallado
                        historialHTML = `
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                No hay modificaciones registradas
                            </div>
                        `;
                    }
                    
                    document.getElementById('detalle_historial').innerHTML = historialHTML;
                } catch (e) {
                    console.error('Error al parsear JSON:', e);
                    document.getElementById('detalle_historial').innerHTML = `
                        <div class="alert alert-warning">
                            Error al procesar la respuesta del servidor
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error en la petición:', error);
                document.getElementById('detalle_historial').innerHTML = `
                    <div class="alert alert-warning">
                        Error al cargar el historial de modificaciones
                    </div>
                `;
            });
        }
        
        
        // Funciones para gestión de unidades
        function editarUnidad(unidad) {
            document.getElementById('edit_unidad_id').value = unidad.id;
            document.getElementById('edit_nombre').value = unidad.nombre;
            document.getElementById('edit_activa').checked = unidad.activa == 1;
            
            $('#modalEditarUnidad').modal('show');
        }
        
        function eliminarUnidad(id, nombre, postulantesCount) {
            // Almacenar los datos en variables globales para usar en el modal
            window.unidadAEliminar = {
                id: id,
                nombre: nombre,
                postulantesCount: postulantesCount
            };
            
            // Actualizar el contenido del modal
            const deleteNombreElement = document.getElementById('delete_unidad_nombre');
            const modalMessage = document.getElementById('delete_unidad_message');
            
            if (deleteNombreElement) {
                deleteNombreElement.textContent = nombre;
            }
            
            // Actualizar el mensaje del modal según si hay postulantes
            if (modalMessage) {
                if (postulantesCount > 0) {
                    modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar la unidad <strong>${nombre}</strong>?<br><br><span class="text-danger"><i class="fas fa-exclamation-triangle"></i> <strong>Advertencia:</strong> Esta unidad tiene ${postulantesCount} postulante(s) asociado(s). No se puede eliminar.</span>`;
                } else {
                    modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar la unidad <strong>${nombre}</strong>?<br><br><span class="text-danger"><strong>Esta acción no se puede deshacer.</strong></span>`;
                }
            }
            
            $('#modalEliminarUnidad').modal('show');
        }
        
        // Función para confirmar eliminación de unidad desde el modal
        function confirmarEliminacionUnidad() {
            if (window.unidadAEliminar) {
                const { id, nombre, postulantesCount } = window.unidadAEliminar;
                
                // Si hay postulantes, no permitir eliminación
                if (postulantesCount > 0) {
                    alert('No se puede eliminar la unidad porque tiene postulantes asociados.');
                    return;
                }
                
                // Crear y enviar formulario dinámicamente
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                
                const accionInput = document.createElement('input');
                accionInput.type = 'hidden';
                accionInput.name = 'accion';
                accionInput.value = 'eliminar_unidad';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(accionInput);
                form.appendChild(idInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Funciones para estadísticas y reportes
        
        function exportarEstadisticas() {
            // Función para exportar estadísticas
            alert('Función de exportación en desarrollo. Por ahora, puedes usar la función de reporte diario.');
        }
        
        
        function generarReporteDiarioEspecifico() {
            // Preguntar si incluir lista de postulantes
            const incluirPostulantes = confirm('¿Desea incluir la lista detallada de postulantes registrados?\n\nEsto incluirá:\n- Cédula de Identidad\n- Nombre y Apellido\n- Dispositivo utilizado');
            
            // Obtener parámetros de fecha y hora actuales
            const fecha = document.getElementById('fecha_reporte').value;
            const horaDesde = document.getElementById('hora_desde').value;
            const horaHasta = document.getElementById('hora_hasta').value;
            
            // Recargar la página con todos los parámetros
            const url = new URL(window.location);
            url.searchParams.set('incluir_postulantes', incluirPostulantes ? '1' : '0');
            url.searchParams.set('fecha_reporte', fecha);
            url.searchParams.set('hora_desde', horaDesde);
            url.searchParams.set('hora_hasta', horaHasta);
            window.location.href = url.toString();
        }
        
        // Función para seleccionar franjas horarias predefinidas
        function seleccionarFranja(tipo) {
            const horaDesde = document.getElementById('hora_desde');
            const horaHasta = document.getElementById('hora_hasta');
            
            switch(tipo) {
                case 'madrugada':
                    horaDesde.value = '00:00';
                    horaHasta.value = '06:00';
                    break;
                case 'manana':
                    horaDesde.value = '06:00';
                    horaHasta.value = '12:00';
                    break;
                case 'tarde':
                    horaDesde.value = '12:00';
                    horaHasta.value = '18:00';
                    break;
                case 'noche':
                    horaDesde.value = '18:00';
                    horaHasta.value = '23:59';
                    break;
                case 'completo':
                    horaDesde.value = '00:00';
                    horaHasta.value = '23:59';
                    break;
            }
            
            // Aplicar automáticamente el filtro
            document.getElementById('fechaForm').submit();
        }
        
        // Función que se ejecuta cuando la página se carga con el parámetro
        function procesarGeneracionReporte() {
            const urlParams = new URLSearchParams(window.location.search);
            const incluirPostulantes = urlParams.get('incluir_postulantes');
            
            // Solo procesar si el parámetro existe (no null) y es válido
            if (incluirPostulantes !== null && (incluirPostulantes === '1' || incluirPostulantes === '0')) {
                const incluirPostulantesBool = incluirPostulantes === '1';
                
                // Limpiar el parámetro de la URL inmediatamente
                const url = new URL(window.location);
                url.searchParams.delete('incluir_postulantes');
                window.history.replaceState({}, document.title, url.toString());
                
                // Pequeño delay para asegurar que la página esté completamente cargada
                setTimeout(() => {
                    // Verificar que las librerías estén disponibles
                    if (typeof docx === 'undefined' || typeof saveAs === 'undefined') {
                        // Fallback: generar reporte HTML imprimible
                        generarReporteHTML(incluirPostulantesBool);
                        return;
                    }
                    
                    // Generar reporte DOCX
                    generarReporteDOCX(incluirPostulantesBool);
                }, 100);
            }
        }
        
        function generarReporteDOCX(incluirPostulantes) {
            
            const fecha = document.getElementById('fecha_reporte').value;
            // Formatear fecha correctamente para evitar problemas de zona horaria
            const fechaObj = new Date(fecha + 'T00:00:00');
            const dia = String(fechaObj.getDate()).padStart(2, '0');
            const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
            const año = fechaObj.getFullYear();
            const fechaFormateada = `${dia}/${mes}/${año}`;
            
            // Crear documento Word
            const doc = new docx.Document({
                sections: [{
                    properties: {},
                    children: [
                        // Título principal
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "REPORTE DIARIO - SISTEMA QUIRA",
                                    bold: true,
                                    size: 32,
                                    color: "000000"
                                })
                            ],
                            alignment: docx.AlignmentType.CENTER,
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "Fecha: <?= date('d/m/Y', strtotime($fecha_reporte)) ?>",
                                    bold: true,
                                    size: 24,
                                    color: "2E5090"
                                })
                            ],
                            alignment: docx.AlignmentType.CENTER,
                            spacing: { after: 200 }
                        }),
                        
                        <?php if (isset($_GET['hora_desde']) && isset($_GET['hora_hasta']) && ($_GET['hora_desde'] !== '00:00' || $_GET['hora_hasta'] !== '23:59')): ?>
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "Franja horaria: <?= $_GET['hora_desde'] ?> - <?= $_GET['hora_hasta'] ?>",
                                    bold: true,
                                    size: 20,
                                    color: "2E5090"
                                })
                            ],
                            alignment: docx.AlignmentType.CENTER,
                            spacing: { after: 400 }
                        }),
                        <?php endif; ?>
                        
                        // Información de fechas
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: `Fecha del reporte: ${fechaFormateada}`,
                                    size: 22
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: `Generado el: ${new Date().toLocaleDateString('es-PY', { timeZone: 'America/Asuncion' })} ${new Date().toLocaleTimeString('es-PY', { timeZone: 'America/Asuncion' })}`,
                                    size: 22
                                })
                            ],
                            spacing: { after: 400 }
                        }),
                        
                        // Estadísticas del día
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "ESTADÍSTICAS DEL DÍA",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: `Total de postulantes registrados: <?= number_format($postulantes_fecha, 0, ',', '.') ?>`,
                                    size: 24
                                })
                            ],
                            spacing: { after: 400 }
                        }),
                        
                        // Postulantes por unidad
                        <?php if (!empty($postulantes_por_unidad_fecha)): ?>
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "POSTULANTES POR UNIDAD",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Table({
                            width: {
                                size: 100,
                                type: docx.WidthType.PERCENTAGE,
                            },
                            rows: [
                                // Encabezados
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Unidad",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Cantidad",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        })
                                    ]
                                }),
                                // Datos
                                <?php foreach ($postulantes_por_unidad_fecha as $unidad): ?>
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: (function() { var text = "<?= htmlspecialchars($unidad['unidad']) ?>"; return text.replace(/&quot;/g, '"'); })(),
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $unidad['cantidad'] ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                }),
                                <?php endforeach; ?>
                            ]
                        }),
                        
                        new docx.Paragraph({
                            children: [new docx.TextRun({ text: "" })],
                            spacing: { after: 400 }
                        }),
                        <?php endif; ?>
                        
                        // Aparatos utilizados
                        <?php if (!empty($aparatos_utilizados_fecha)): ?>
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "APARATOS BIOMÉTRICOS UTILIZADOS",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Table({
                            width: {
                                size: 100,
                                type: docx.WidthType.PERCENTAGE,
                            },
                            rows: [
                                // Encabezados
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Dispositivo",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Registros",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        })
                                    ]
                                }),
                                // Datos
                                <?php foreach ($aparatos_utilizados_fecha as $aparato): ?>
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($aparato['dispositivo']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $aparato['cantidad'] ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                }),
                                <?php endforeach; ?>
                            ]
                        }),
                        
                        new docx.Paragraph({
                            children: [new docx.TextRun({ text: "" })],
                            spacing: { after: 400 }
                        }),
                        <?php endif; ?>
                        
                        // Distribución de usuarios registradores
                        <?php if (!empty($usuarios_registradores_fecha)): ?>
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "DISTRIBUCIÓN DE USUARIOS REGISTRADORES",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Table({
                            width: {
                                size: 100,
                                type: docx.WidthType.PERCENTAGE,
                            },
                            rows: [
                                // Encabezados
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Usuario",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Dispositivo",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Registros",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        })
                                    ]
                                }),
                                // Datos
                                <?php foreach ($usuarios_registradores_fecha as $usuario): ?>
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($usuario['usuario']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($usuario['dispositivo']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $usuario['cantidad'] ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                }),
                                <?php endforeach; ?>
                            ]
                        }),
                        
                        new docx.Paragraph({
                            children: [new docx.TextRun({ text: "" })],
                            spacing: { after: 400 }
                        }),
                        <?php endif; ?>
                        
                        // Horarios de registro
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "HORARIOS DE REGISTRO",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Table({
                            width: {
                                size: 100,
                                type: docx.WidthType.PERCENTAGE,
                            },
                            rows: [
                                // Encabezados
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Evento",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Hora",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        })
                                    ]
                                }),
                                // Datos
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Primer registro del día",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $horarios_registro['primer_registro'] ? date('H:i:s', strtotime($horarios_registro['primer_registro'])) : 'No hay registros' ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                }),
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Último registro del día",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $horarios_registro['ultimo_registro'] ? date('H:i:s', strtotime($horarios_registro['ultimo_registro'])) : 'No hay registros' ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                })
                            ]
                        }),
                        
                        // Lista detallada de postulantes (si se solicita)
                        <?php if (!empty($postulantes_detallados) && isset($_GET['incluir_postulantes']) && $_GET['incluir_postulantes'] == '1'): ?>
                        new docx.Paragraph({
                            children: [new docx.TextRun({ text: "" })],
                            spacing: { after: 400 }
                        }),
                        
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "LISTA DETALLADA DE POSTULANTES POR UNIDAD",
                                    bold: true,
                                    size: 28,
                                    color: "2E5090"
                                })
                            ],
                            spacing: { after: 200 }
                        }),
                        
                        new docx.Table({
                            width: {
                                size: 100,
                                type: docx.WidthType.PERCENTAGE,
                            },
                            rows: [
                                // Encabezados
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "N°",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Cédula",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Nombre Completo",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "Dispositivo",
                                                            bold: true,
                                                            size: 22
                                                        })
                                                    ]
                                                })
                                            ],
                                            shading: {
                                                fill: "F0F0F0"
                                            }
                                        })
                                    ]
                                }),
                                // Datos agrupados por unidad
                                <?php 
                                foreach ($postulantes_por_unidad as $unidad => $postulantes_unidad): 
                                ?>
                                // Encabezado de unidad
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: (function() { var text = "UNIDAD: <?= htmlspecialchars($unidad) ?>"; return text.replace(/&quot;/g, '"'); })(),
                                                            bold: true,
                                                            size: 22,
                                                            color: "2E5090"
                                                        })
                                                    ]
                                                })
                                            ],
                                            columnSpan: 4,
                                            shading: {
                                                fill: "E8F0FE"
                                            }
                                        })
                                    ]
                                }),
                                // Postulantes de esta unidad
                                <?php foreach ($postulantes_unidad as $index => $postulante): ?>
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= $index + 1 ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($postulante['cedula']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($postulante['nombre_completo']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        }),
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "<?= htmlspecialchars($postulante['dispositivo']) ?>",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ]
                                        })
                                    ]
                                }),
                                <?php endforeach; ?>
                                // Fila separadora entre unidades
                                new docx.TableRow({
                                    children: [
                                        new docx.TableCell({
                                            children: [
                                                new docx.Paragraph({
                                                    children: [
                                                        new docx.TextRun({
                                                            text: "",
                                                            size: 20
                                                        })
                                                    ]
                                                })
                                            ],
                                            columnSpan: 4
                                        })
                                    ]
                                }),
                                <?php endforeach; ?>
                            ]
                        }),
                        <?php endif; ?>
                        
                        // Pie de página
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "Sistema Quira - Reporte generado automáticamente",
                                    italics: true,
                                    size: 18,
                                    color: "808080"
                                })
                            ],
                            alignment: docx.AlignmentType.CENTER,
                            spacing: { before: 800 }
                        }),
                        new docx.Paragraph({
                            children: [
                                new docx.TextRun({
                                    text: "Generado por: <?= htmlspecialchars((!empty($_SESSION['grado']) ? $_SESSION['grado'] . ' ' : '') . $_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?>",
                                    italics: true,
                                    size: 16,
                                    color: "808080"
                                })
                            ],
                            alignment: docx.AlignmentType.CENTER,
                            spacing: { before: 200 }
                        })
                    ]
                }]
            });
            
            // Generar y descargar el documento
            docx.Packer.toBlob(doc).then(blob => {
                const nombreArchivo = `reporte_diario_${fecha.replace(/-/g, '_')}.docx`;
                saveAs(blob, nombreArchivo);
            });
        }
        
        // Función de respaldo para generar reporte HTML imprimible
        function generarReporteHTML(incluirPostulantes = false) {
            const fecha = document.getElementById('fecha_reporte').value;
            // Formatear fecha correctamente para evitar problemas de zona horaria
            const fechaObj = new Date(fecha + 'T00:00:00');
            const dia = String(fechaObj.getDate()).padStart(2, '0');
            const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
            const año = fechaObj.getFullYear();
            const fechaFormateada = `${dia}/${mes}/${año}`;
            
            // Crear ventana nueva para el reporte
            const ventanaReporte = window.open('', '_blank', 'width=800,height=600');
            
            const contenidoHTML = `
                <!DOCTYPE html>
                <html lang="es">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Reporte Diario - Sistema Quira</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            line-height: 1.6;
                            color: #333;
                        }
                        .header {
                            text-align: center;
                            margin-bottom: 30px;
                            border-bottom: 2px solid #000;
                            padding-bottom: 20px;
                        }
                        .title {
                            font-size: 24px;
                            font-weight: bold;
                            margin-bottom: 10px;
                        }
                        .subtitle {
                            font-size: 16px;
                            color: #666;
                        }
                        .section {
                            margin: 30px 0;
                        }
                        .section-title {
                            font-size: 18px;
                            font-weight: bold;
                            color: #2E5090;
                            margin-bottom: 15px;
                            border-bottom: 1px solid #ccc;
                            padding-bottom: 5px;
                        }
                        .stats-item {
                            font-size: 16px;
                            margin: 10px 0;
                        }
                        table {
                            width: 100%;
                            border-collapse: collapse;
                            margin: 15px 0;
                        }
                        th, td {
                            border: 1px solid #ddd;
                            padding: 12px;
                            text-align: left;
                        }
                        th {
                            background-color: #f0f0f0;
                            font-weight: bold;
                        }
                        .footer {
                            text-align: center;
                            margin-top: 40px;
                            font-style: italic;
                            color: #666;
                            font-size: 14px;
                        }
                        @media print {
                            body { margin: 0; }
                            .no-print { display: none; }
                        }
                    </style>
                </head>
                <body>
                    <div class="header">
                        <div class="title">REPORTE DIARIO - SISTEMA QUIRA</div>
                        <div class="subtitle">Fecha del reporte: ${fechaFormateada}</div>
                        <?php if (isset($_GET['hora_desde']) && isset($_GET['hora_hasta']) && ($_GET['hora_desde'] !== '00:00' || $_GET['hora_hasta'] !== '23:59')): ?>
                        <div class="subtitle">Franja horaria: <?= $_GET['hora_desde'] ?> - <?= $_GET['hora_hasta'] ?></div>
                        <?php endif; ?>
                        <div class="subtitle">Generado el: ${new Date().toLocaleDateString('es-PY', { timeZone: 'America/Asuncion' })} ${new Date().toLocaleTimeString('es-PY', { timeZone: 'America/Asuncion' })}</div>
                    </div>
                    
                    <div class="section">
                        <div class="section-title">ESTADÍSTICAS DEL DÍA</div>
                        <div class="stats-item">Total de postulantes registrados: <?= number_format($postulantes_fecha, 0, ',', '.') ?></div>
                    </div>
                    
                    <?php if (!empty($postulantes_por_unidad_fecha)): ?>
                    <div class="section">
                        <div class="section-title">POSTULANTES POR UNIDAD</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Unidad</th>
                                    <th>Cantidad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($postulantes_por_unidad_fecha as $unidad): ?>
                                <tr>
                                    <td><?= str_replace('&quot;', '"', htmlspecialchars($unidad['unidad'])) ?></td>
                                    <td><?= $unidad['cantidad'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($aparatos_utilizados_fecha)): ?>
                    <div class="section">
                        <div class="section-title">APARATOS BIOMÉTRICOS UTILIZADOS</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Dispositivo</th>
                                    <th>Registros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aparatos_utilizados_fecha as $aparato): ?>
                                <tr>
                                    <td><?= htmlspecialchars($aparato['dispositivo']) ?></td>
                                    <td><?= $aparato['cantidad'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($usuarios_registradores_fecha)): ?>
                    <div class="section">
                        <div class="section-title">DISTRIBUCIÓN DE USUARIOS REGISTRADORES</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Dispositivo</th>
                                    <th>Registros</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios_registradores_fecha as $usuario): ?>
                                <tr>
                                    <td><?= htmlspecialchars($usuario['usuario']) ?></td>
                                    <td><?= htmlspecialchars($usuario['dispositivo']) ?></td>
                                    <td><?= $usuario['cantidad'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section">
                        <div class="section-title">HORARIOS DE REGISTRO</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Evento</th>
                                    <th>Hora</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Primer registro del día</td>
                                    <td><?= $horarios_registro['primer_registro'] ? date('H:i:s', strtotime($horarios_registro['primer_registro'])) : 'No hay registros' ?></td>
                                </tr>
                                <tr>
                                    <td>Último registro del día</td>
                                    <td><?= $horarios_registro['ultimo_registro'] ? date('H:i:s', strtotime($horarios_registro['ultimo_registro'])) : 'No hay registros' ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (!empty($postulantes_por_unidad) && isset($_GET['incluir_postulantes']) && $_GET['incluir_postulantes'] == '1'): ?>
                    <div class="section">
                        <div class="section-title">LISTA DETALLADA DE POSTULANTES POR UNIDAD</div>
                        <?php 
                        foreach ($postulantes_por_unidad as $unidad => $postulantes_unidad): 
                        ?>
                        <div class="unidad-section" style="margin-bottom: 30px;">
                            <h4 style="color: #2E5090; background-color: #E8F0FE; padding: 10px; margin: 0 0 15px 0; border-left: 4px solid #2E5090;">
                                UNIDAD: <?= str_replace('&quot;', '"', htmlspecialchars($unidad)) ?> (<?= count($postulantes_unidad) ?> postulantes)
                            </h4>
                            <table>
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Cédula</th>
                                        <th>Nombre Completo</th>
                                        <th>Dispositivo</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($postulantes_unidad as $index => $postulante): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td><?= htmlspecialchars($postulante['cedula']) ?></td>
                                        <td><?= htmlspecialchars($postulante['nombre_completo'] ?? ($postulante['nombre'] . ' ' . $postulante['apellido'])) ?></td>
                                        <td><?= htmlspecialchars($postulante['dispositivo']) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="footer">
                        Sistema Quira - Reporte generado automáticamente<br>
                        <small>Generado por: <?= htmlspecialchars((!empty($_SESSION['grado']) ? $_SESSION['grado'] . ' ' : '') . $_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></small>
                    </div>
                    
                    <div class="no-print" style="text-align: center; margin-top: 20px;">
                        <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; background-color: #2E5090; color: white; border: none; border-radius: 5px; cursor: pointer;">
                            🖨️ Imprimir Reporte
                        </button>
                        <p style="margin-top: 10px; color: #666;">
                            Usa Ctrl+P para imprimir o guardar como PDF
                        </p>
                    </div>
                </body>
                </html>
            `;
            
            ventanaReporte.document.write(contenidoHTML);
            ventanaReporte.document.close();
        }
        
        // Inicializar gráficos cuando se muestre la sección de estadísticas
        function inicializarGraficos() {
            // Verificar que Chart.js esté disponible
            if (typeof Chart === 'undefined') {
                console.error('Chart.js no está cargado');
                return;
            }
            
            // Limpiar gráficos existentes
            Chart.helpers.each(Chart.instances, function(instance) {
                instance.destroy();
            });
            
            // Gráfico de registros por día
            const ctxRegistros = document.getElementById('graficoRegistrosDia');
            if (ctxRegistros) {
                const datosRegistros = <?= json_encode($registros_por_dia) ?>;
                if (datosRegistros && datosRegistros.length > 0) {
                    // Ordenar por fecha ascendente y parsear fechas en horario local para evitar desfases
                    const datosOrdenados = [...datosRegistros].sort((a, b) => a.fecha.localeCompare(b.fecha));
                    const etiquetas = datosOrdenados.map(d => {
                        const [y, m, dd] = String(d.fecha).split('-').map(Number);
                        const fecha = new Date(y, (m || 1) - 1, dd || 1);
                        const dia = String(fecha.getDate()).padStart(2, '0');
                        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
                        const anio = fecha.getFullYear();
                        return `${dia}/${mes}/${anio}`;
                    });
                    const valores = datosOrdenados.map(d => d.cantidad);

                    new Chart(ctxRegistros, {
                        type: 'line',
                        data: {
                            labels: etiquetas,
                            datasets: [{
                                label: 'Registros',
                                data: valores,
                                borderColor: 'rgb(75, 192, 192)',
                                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                                tension: 0.1,
                                fill: true
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    ctxRegistros.parentElement.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-line fa-2x mb-2"></i><br>No hay datos disponibles</div>';
                }
            }
            
            // Gráfico de distribución por unidad
            const ctxUnidades = document.getElementById('graficoUnidades');
            if (ctxUnidades) {
                const datosUnidades = <?= json_encode($distribucion_unidades) ?>;
                if (datosUnidades && datosUnidades.length > 0) {
                    // Crear etiquetas truncadas para mejor visualización
                    const labelsTruncados = datosUnidades.map(d => {
                        let label = d.unidad;
                        if (label.length > 20) {
                            const words = label.split(' ');
                            let truncated = '';
                            for (let word of words) {
                                if ((truncated + ' ' + word).trim().length <= 20) {
                                    truncated += (truncated ? ' ' : '') + word;
                                } else {
                                    break;
                                }
                            }
                            return truncated + '...';
                        }
                        return label;
                    });
                    
                    new Chart(ctxUnidades, {
                        type: 'bar',
                        data: {
                            labels: labelsTruncados,
                            datasets: [{
                                label: 'Postulantes',
                                data: datosUnidades.map(d => d.cantidad),
                                backgroundColor: [
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                                ],
                                borderColor: [
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
                                    '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
                                    '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                                ],
                                borderWidth: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const originalLabel = datosUnidades[context.dataIndex].unidad;
                                            const value = context.parsed.y;
                                            const total = datosUnidades.reduce((sum, d) => sum + d.cantidad, 0);
                                            const percentage = ((value / total) * 100).toFixed(1);
                                            return `${originalLabel}: ${value} (${percentage}%)`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        stepSize: 1
                                    }
                                },
                                x: {
                                    ticks: {
                                        maxRotation: 45,
                                        minRotation: 0
                                    }
                                }
                            }
                        }
                    });
                } else {
                    ctxUnidades.parentElement.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-bar fa-2x mb-2"></i><br>No hay datos disponibles</div>';
                }
            }
            
            // Gráfico de dedos
            const ctxDedos = document.getElementById('graficoDedos');
            if (ctxDedos) {
                const datosDedos = <?= json_encode($distribucion_dedos) ?>;
                if (datosDedos && datosDedos.length > 0) {
                    new Chart(ctxDedos, {
                        type: 'bar',
                        data: {
                            labels: datosDedos.map(d => d.dedo_registrado),
                            datasets: [{
                                label: 'Registros',
                                data: datosDedos.map(d => d.cantidad),
                                backgroundColor: 'rgba(54, 162, 235, 0.8)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    ctxDedos.parentElement.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-bar fa-2x mb-2"></i><br>No hay datos disponibles</div>';
                }
            }
            
            // Gráfico de sexo
            const ctxSexo = document.getElementById('graficoSexo');
            if (ctxSexo) {
                const datosSexo = <?= json_encode($distribucion_sexo) ?>;
                if (datosSexo && datosSexo.length > 0) {
                    new Chart(ctxSexo, {
                        type: 'pie',
                        data: {
                            labels: datosSexo.map(d => d.sexo),
                            datasets: [{
                                data: datosSexo.map(d => d.cantidad),
                                backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56']
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            }
                        }
                    });
                } else {
                    ctxSexo.parentElement.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-pie fa-2x mb-2"></i><br>No hay datos disponibles</div>';
                }
            }
            
            // Gráfico de horarios
            const ctxHorarios = document.getElementById('graficoHorarios');
            if (ctxHorarios) {
                const datosHorarios = <?= json_encode($horarios_actividad) ?>;
                if (datosHorarios && datosHorarios.length > 0) {
                    new Chart(ctxHorarios, {
                        type: 'bar',
                        data: {
                            labels: datosHorarios.map(d => d.hora + ':00'),
                            datasets: [{
                                label: 'Registros',
                                data: datosHorarios.map(d => d.cantidad),
                                backgroundColor: 'rgba(255, 99, 132, 0.8)'
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true
                                }
                            }
                        }
                    });
                } else {
                    ctxHorarios.parentElement.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-chart-bar fa-2x mb-2"></i><br>No hay datos disponibles</div>';
                }
            }
        }
        
        // Funciones para mejorar la experiencia de filtros
        document.addEventListener('DOMContentLoaded', function() {
            // Procesar generación de reporte si hay parámetros
            procesarGeneracionReporte();
            // Auto-submit cuando cambia el selector de resultados por página
            const perPageSelect = document.getElementById('per_page');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    document.getElementById('filtrosForm').submit();
                });
            }
            
            // Búsqueda manual (solo al presionar Enter o hacer clic en buscar)
            const searchInput = document.getElementById('search');
            if (searchInput) {
                // Comentado: búsqueda automática en tiempo real
                // let searchTimeout;
                // searchInput.addEventListener('input', function() {
                //     clearTimeout(searchTimeout);
                //     searchTimeout = setTimeout(function() {
                //         // Solo buscar si hay al menos 3 caracteres o está vacío
                //         if (searchInput.value.length >= 3 || searchInput.value.length === 0) {
                //             document.getElementById('filtrosForm').submit();
                //         }
                //     }, 500);
                // });
                
                // Búsqueda solo al presionar Enter
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        document.getElementById('filtrosForm').submit();
                    }
                });
            }
            
            // Comentado: Auto-submit cuando cambian los filtros de select
            // Los filtros ahora solo se aplican al hacer clic en "Filtrar"
            // const filterSelects = ['unidad', 'aparato', 'dedo'];
            // filterSelects.forEach(function(selectId) {
            //     const select = document.getElementById(selectId);
            //     if (select) {
            //         select.addEventListener('change', function() {
            //             document.getElementById('filtrosForm').submit();
            //         });
            //     }
            // });
        });
        
        // Función para abrir modal de registros por día
        function abrirModalRegistrosDia() {
            $('#modalRegistrosDia').modal('show');
            
            // Crear gráfico ampliado cuando se abre el modal
            setTimeout(function() {
                try {
                    crearGraficoRegistrosDiaModal();
                } catch (error) {
                    console.error('Error al crear gráfico en modal:', error);
                }
            }, 300);
        }
        
        // Función para crear el gráfico en el modal
        function crearGraficoRegistrosDiaModal() {
            const ctx = document.getElementById('graficoRegistrosDiaModal');
            if (!ctx) return;
            
            // Destruir gráfico existente si existe
            if (window.graficoRegistrosDiaModal && typeof window.graficoRegistrosDiaModal.destroy === 'function') {
                window.graficoRegistrosDiaModal.destroy();
            }
            
            // Limpiar el canvas
            const context = ctx.getContext('2d');
            context.clearRect(0, 0, ctx.width, ctx.height);
            
            const datosRegistros = <?= json_encode($registros_por_dia) ?>;
            if (datosRegistros && datosRegistros.length > 0) {
                // Ordenar por fecha ascendente y parsear fechas en horario local
                const datosOrdenados = [...datosRegistros].sort((a, b) => a.fecha.localeCompare(b.fecha));
                const etiquetas = datosOrdenados.map(d => {
                    const [y, m, dd] = String(d.fecha).split('-').map(Number);
                    const fecha = new Date(y, (m || 1) - 1, dd || 1);
                    const dia = String(fecha.getDate()).padStart(2, '0');
                    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
                    const anio = fecha.getFullYear();
                    return `${dia}/${mes}/${anio}`;
                });
                const valores = datosOrdenados.map(d => d.cantidad);

                window.graficoRegistrosDiaModal = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: etiquetas,
                        datasets: [{
                            label: 'Registros',
                            data: valores,
                            borderColor: '#2E5090',
                            backgroundColor: 'rgba(46, 80, 144, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#2E5090',
                            pointBorderColor: '#ffffff',
                            pointBorderWidth: 2,
                            pointRadius: 6,
                            pointHoverRadius: 8
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: '#2E5090',
                                borderWidth: 1
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Fecha',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            y: {
                                display: true,
                                title: {
                                    display: true,
                                    text: 'Cantidad de Registros',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                beginAtZero: true,
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            } else {
                ctx.getContext('2d').clearRect(0, 0, ctx.width, ctx.height);
                ctx.getContext('2d').font = '16px Arial';
                ctx.getContext('2d').fillStyle = '#666';
                ctx.getContext('2d').textAlign = 'center';
                ctx.getContext('2d').fillText('No hay datos disponibles', ctx.width / 2, ctx.height / 2);
            }
        }

        // Función para generar backup de la base de datos
        function generarBackup() {
            const statusDiv = document.getElementById('backup-status');
            statusDiv.style.display = 'block';
            
            // Crear un formulario para enviar la solicitud
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'generar_backup.php';
            form.target = '_blank';
            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
            
            // Ocultar el status después de un tiempo
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        }


    </script>

    <!-- Modal para Registros por Día -->
    <div class="modal fade" id="modalRegistrosDia" tabindex="-1" role="dialog" aria-labelledby="modalRegistrosDiaLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRegistrosDiaLabel">Registros por Día - Vista Ampliada</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Período: <?= $rango_fechas_texto ?? 'Últimos 30 días' ?></h6>
                        </div>
                        <div class="col-md-6 text-right">
                            <small class="text-muted">Total de registros: <?= array_sum(array_column($registros_por_dia, 'cantidad')) ?></small>
                        </div>
                    </div>
                        <canvas id="graficoRegistrosDiaModal" height="300"></canvas>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>


    <!-- Footer Institucional -->
    <footer class="bg-light py-5 mt-5">
        <div class="container">
            <div class="row">
                <!-- Logos Institucionales -->
                <div class="col-md-6 text-center mb-4">
                    <div class="institutional-logos">
                        <div class="logo-item mb-3">
                            <img src="assets/media/various/isepol.png" alt="ISEPOL" class="img-fluid" style="max-height: 80px;">
                            <p class="mt-2 mb-0"><strong>Instituto Superior de Educación Policial</strong></p>
                        </div>
                        <div class="logo-item mb-3">
                            <img src="assets/media/various/inst_criminalistica.png" alt="Instituto de Criminalística" class="img-fluid" style="max-height: 80px;">
                            <p class="mt-2 mb-0"><strong>Instituto de Criminalística</strong></p>
                        </div>
                        <div class="logo-item">
                            <img src="assets/media/various/quiraXXXL.png" alt="QUIRA" class="img-fluid" style="max-height: 80px;">
                            <p class="mt-2 mb-0"><strong>Sistema QUIRA</strong></p>
                        </div>
                    </div>
                </div>
                
                <!-- Información del Desarrollador -->
                <div class="col-md-6">
                    <div class="developer-info">
                        <h5 class="text-primary mb-3"><i class="fas fa-user-tie mr-2"></i>Desarrollador</h5>
                        <p class="mb-2"><strong>Oficial Segundo PS Lic. GUILLERMO ANDRÉS RECALDE VALDEZ</strong></p>
                        <p class="mb-2"><i class="fas fa-graduation-cap mr-2 text-info"></i>Alumno del Instituto de Criminalística</p>
                        <p class="mb-2"><i class="fas fa-heart mr-2 text-danger"></i>Desarrollo sin ánimo de lucro</p>
                        <p class="mb-2"><i class="fas fa-server mr-2 text-warning"></i>Hosteado en página de propiedad privada</p>
                        <p class="mb-2"><i class="fas fa-palette mr-2 text-info"></i>Diseño basado en plantilla de <a href="https://pixelcave.com" target="_blank" rel="noopener">Pixelcave</a></p>
                        <p class="mb-2" style="font-style: italic; color: #6c757d;">
                            <i class="fas fa-heart text-danger mr-1"></i>
                            Dedicado a Akira 30/05
                        </p>
                        
                        <div class="contact-info mt-4">
                            <h6 class="text-secondary mb-2">Contacto:</h6>
                            <p class="mb-1"><i class="fas fa-phone mr-2 text-success"></i> +595 973 408 754</p>
                            <p class="mb-0"><i class="fas fa-envelope mr-2 text-primary"></i> recaldev.ga@gmail.com</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="row">
                <div class="col-12 text-center">
                    <p class="text-muted mb-0">
                        &copy; <?= date('Y') ?> Sistema QUIRA
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <style>
        .modal-video-content {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 1rem;
            overflow: hidden;
        }
        .modal-video-header {
            background-color: #2E5090;
            border-bottom: none;
            align-items: center;
        }
        .modal-video-header h5 {
            margin-bottom: 0;
            font-weight: 700;
            letter-spacing: 0.4px;
            color: #cbd5e1;
        }
        .modal-video-header .modal-title i {
            color: #a8b4c7;
        }
        .modal-video-header .close {
            opacity: 0.8;
            color: #cbd5e1;
        }
        .modal-video-header .close:hover {
            opacity: 1;
        }
        .modal-video-body {
            padding: 0;
            background: radial-gradient(circle at top, rgba(46, 80, 144, 0.15), transparent 55%), #0b1220;
        }
        .modal-video-body .video-wrapper {
            position: relative;
            width: 100%;
            padding-top: 56.25%;
            background: #000;
        }
        .modal-video-body .video-wrapper video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain;
            background: #000;
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }
        .modal-video-footer {
            background: rgba(15, 23, 42, 0.85);
            border-top: 1px solid rgba(148, 163, 184, 0.15);
            justify-content: space-between;
            align-items: center;
        }
        .modal-video-footer .btn {
            border-radius: 999px;
            font-weight: 600;
            letter-spacing: 0.3px;
        }
        .modal-video-footer .btn-outline-light {
            border-color: rgba(226, 232, 240, 0.4);
        }
        .modal-video-footer .btn-outline-light:hover {
            background: rgba(226, 232, 240, 0.1);
        }
        .modal-video-footer .btn-primary {
            background: #2E5090;
            border-color: #2E5090;
        }
        .modal-video-footer .btn-primary:hover {
            background: #1f3b6a;
            border-color: #1f3b6a;
        }
        .modal-video-content {
            max-width: 820px;
            margin: 0 auto;
        }
        .modal-video-body .video-wrapper {
            padding-top: 56.25%;
        }
        @media (max-width: 992px) {
            .modal-video-content {
                max-width: 90vw;
            }
        }
        @media (max-width: 768px) {
            .modal-video-content {
                border-radius: 0.75rem;
            }
            .modal-video-footer {
                flex-direction: column;
                gap: 0.75rem;
            }
        }
        .blurred-background {
            filter: blur(6px);
            transition: filter 0.3s ease;
        }
        body.blurred-background {
            overflow: hidden;
        }
        #modal-instrucciones-postulante .modal-header .close {
            opacity: 0.7;
            color: #ffffff;
            transition: color 0.2s ease, opacity 0.2s ease;
        }
        #modal-instrucciones-postulante .modal-header .close:hover {
            color: #ff4d4f;
            opacity: 1;
        }
        #modal-instrucciones-postulante .btn-close-instrucciones {
            background-color: #ffffff;
            color: #4a4f58;
            transition: color 0.2s ease, box-shadow 0.2s ease;
            box-shadow: inset 0 0 0 1px rgba(74, 79, 88, 0.3);
        }
        #modal-instrucciones-postulante .btn-close-instrucciones:hover {
            color: #c9191f;
            background-color: #ffffff;
            box-shadow: inset 0 0 0 1px rgba(201, 25, 31, 0.6);
        }
    </style>

    <!-- Modal Instrucciones Agregar Postulante -->
    <div class="modal fade" id="modal-instrucciones-postulante" tabindex="-1" role="dialog" aria-labelledby="modalInstruccionesPostulanteLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header" style="background-color: #2E5090; border-bottom: none;">
                    <h5 class="modal-title font-weight-bold text-white" id="modalInstruccionesPostulanteLabel">
                        <i class="fas fa-fingerprint mr-2"></i>Antes de agregar un postulante
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="color: #4a5568;">
                    <p class="lead mb-4" style="color: #404a5a;">
                        Para agregar un nuevo postulante, asegúrese de registrar el dedo del postulante en el dispositivo biométrico que tiene conectado a su computadora.
                    </p>
                    <ol class="pl-4">
                        <li class="mb-3">
                            Diríjase a su dispositivo biométrico y presione el botón <strong>M/OK</strong>.
                        </li>
                        <li class="mb-3">
                            Entre en la opción <strong>Usuarios</strong>, elija <strong>Nuevo usuario</strong> y dentro del menú seleccione <strong>Huella</strong> para capturar la huella del postulante según las instrucciones del dispositivo.
                        </li>
                        <li class="mb-3">
                            Una vez tomada la huella del postulante, regrese a la pantalla principal del biométrico presionando <strong>ESC</strong> hasta llegar a ese punto.
                        </li>
                        <li class="mb-0">
                            Puede utilizar este
                            <button type="button" class="btn btn-link p-0 align-baseline" id="btn-ver-video-instrucciones" data-video-src="assets/media/various/zktecok40.mp4" aria-label="Abrir videotutorial">
                                vídeo
                            </button>
                            para ver un tutorial paso a paso.
                            </li>
                    </ol>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary text-secondary border-secondary btn-close-instrucciones" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cerrar
                    </button>
                    <button type="button" class="btn btn-success" id="btn-confirmar-instrucciones">
                        <i class="fas fa-check mr-1"></i> Ya registré el dedo del postulante
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Video Tutorial -->
    <div class="modal fade" id="modal-video-tutorial" tabindex="-1" role="dialog" aria-labelledby="modalVideoTutorialLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content modal-video-content shadow-lg">
                <div class="modal-header modal-video-header text-white">
                    <h5 class="modal-title" id="modalVideoTutorialLabel">
                        <i class="fas fa-play-circle mr-2"></i>Videotutorial de registro biométrico
                    </h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body modal-video-body">
                    <div class="video-wrapper">
                        <video id="video-tutorial" class="video-player" controls preload="none">
                            <source src="" type="video/mp4">
                            Tu navegador no soporta la reproducción de video.
                        </video>
                    </div>
                </div>
                <div class="modal-footer modal-video-footer">
                    <button type="button" class="btn btn-outline-light btn-sm" data-dismiss="modal">
                        <i class="fas fa-times mr-1"></i> Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer Simple -->
    <div class="footer-simple" id="footer-link">
        <span id="footer-text">Powered by </span><span id="footer-simple">s1mple</span>
    </div>

    <!-- Modal -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal-container" id="modal-container">
            <div class="modal-header">
                <h1 class="modal-title">s1mple</h1>
                <div class="modal-divider"></div>
                <p class="modal-subtitle">From BITCAN</p>
            </div>
            
            <div class="modal-body">
                <div class="info-card">
                    <div class="info-label">Desarrollador</div>
                    <div class="info-content">GUILLERMO ANDRÉS</div>
                    <div class="info-content">RECALDE VALDEZ</div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Contacto</div>
                    <div class="info-content-small">recaldev.ga@bitcan.com.py</div>
                    <div class="info-content-small">+595 973 408 754</div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Servicios</div>
                    <div class="info-content-small">Desarrollo de sistemas de gestión y empresariales a medida</div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Proyecto</div>
                    <div class="info-content">Sistema QUIRA</div>
                    <div class="info-content-small">Sistema Que Identifica, Registra y Autentica</div>
                </div>
                
                <div class="info-card">
                    <div class="info-label">Versión</div>
                    <div class="info-content">Beta 2.0.0</div>
                    <div class="info-content-small">26/10/2025</div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button class="btn-close" id="btn-close">Cerrar</button>
            </div>
        </div>
    </div>


    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            const footerLink = document.getElementById('footer-link');
            const modalOverlay = document.getElementById('modal-overlay');
            const modalContainer = document.getElementById('modal-container');
            const btnClose = document.getElementById('btn-close');
            
            // Open modal when clicking footer
            footerLink.addEventListener('click', function(e) {
                e.preventDefault();
                modalOverlay.style.display = 'flex';
            });
            
            // Close modal when clicking overlay
            modalOverlay.addEventListener('click', function(e) {
                if (e.target === modalOverlay) {
                    modalOverlay.style.display = 'none';
                }
            });
            
            // Close modal when clicking close button
            btnClose.addEventListener('click', function() {
                modalOverlay.style.display = 'none';
            });
            
            // Prevent modal from closing when clicking inside modal content
            modalContainer.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            // Modal previo para agregar postulante
            const linkAgregarPostulante = document.getElementById('link-agregar-postulante');
            const btnConfirmarInstrucciones = document.getElementById('btn-confirmar-instrucciones');
            const btnVerVideoInstrucciones = document.getElementById('btn-ver-video-instrucciones');
            const videoTutorial = document.getElementById('video-tutorial');
            const destinoAgregar = linkAgregarPostulante ? linkAgregarPostulante.getAttribute('data-destino') : null;
            const blurTargetElements = Array.from(document.querySelectorAll('body > *:not(.modal)'));

            if (linkAgregarPostulante && window.jQuery) {
                linkAgregarPostulante.addEventListener('click', function(e) {
                    e.preventDefault();
                    $('#modal-instrucciones-postulante').modal('show');
                });
            }

            if (btnConfirmarInstrucciones && window.jQuery) {
                btnConfirmarInstrucciones.addEventListener('click', function() {
                    $('#modal-instrucciones-postulante').modal('hide');
                    if (destinoAgregar) {
                        window.location.href = destinoAgregar;
                    }
                });
            }

            function setBlurState(active) {
                blurTargetElements.forEach(el => {
                    if (el) {
                        el.classList.toggle('blurred-background', active);
                    }
                });
            }

            if (btnVerVideoInstrucciones && videoTutorial && window.jQuery) {
                btnVerVideoInstrucciones.addEventListener('click', function() {
                    const videoSrc = btnVerVideoInstrucciones.getAttribute('data-video-src');
                    if (videoSrc) {
                        const source = videoTutorial.querySelector('source');
                        if (source) {
                            source.src = videoSrc;
                        }
                        videoTutorial.load();
                        videoTutorial.play().catch(() => {});
                    }
                    $('#modal-video-tutorial').modal('show');
                    setBlurState(true);
                });

                $('#modal-video-tutorial').on('hidden.bs.modal', function() {
                    videoTutorial.pause();
                    videoTutorial.currentTime = 0;
                    const source = videoTutorial.querySelector('source');
                    if (source) {
                        source.src = '';
                    }
                    videoTutorial.load();
                    setBlurState(false);
                });
            }
        });
    </script>
</body>
</html>
