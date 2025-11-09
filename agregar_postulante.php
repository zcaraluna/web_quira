<?php
/**
 * Página para agregar postulantes con integración biométrica
 * Sistema QUIRA - Versión Web (Replica exacta del software original)
 * 
 * IMPORTANTE: Este sistema utiliza la zona horaria America/Asuncion
 */

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

// Función para escribir logs de debug
function writeDebugLog($message) {
    $log_file = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

session_start();
require_once 'config.php';
requireLogin();

// Los SUPERVISORES no pueden agregar postulantes
if ($_SESSION['rol'] === 'SUPERVISOR') {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$postulante_id = null;

// Obtener lista de usuarios del sistema para el campo capturador
$usuarios_sistema = [];
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, usuario, nombre, apellido, grado FROM usuarios ORDER BY nombre ASC, apellido ASC");
    $stmt->execute();
    $usuarios_sistema = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Error obteniendo usuarios del sistema: ' . $e->getMessage());
}

// Debug: mostrar información de usuarios obtenidos
error_log('DEBUG - Total usuarios obtenidos: ' . count($usuarios_sistema));
if (count($usuarios_sistema) > 0) {
    error_log('DEBUG - Primer usuario: ' . print_r($usuarios_sistema[0], true));
} else {
    error_log('DEBUG - No se encontraron usuarios en la tabla usuarios');
}

// Manejar memoria de sesión para el capturador
$ultimo_capturador = $_SESSION['ultimo_capturador'] ?? $_SESSION['user_id'] ?? '';

// Función para verificar modo prueba
function verificar_modo_prueba_activo($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT activo FROM aparatos_biometricos WHERE serial = '0X0AB0' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['activo'] : false;
    } catch (Exception $e) {
        return false;
    }
}

// Función para obtener aparato por serial
function obtener_aparato_por_serial($pdo, $serial_number) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos WHERE serial = ? LIMIT 1");
        $stmt->execute([$serial_number]);
        $aparato = $stmt->fetch();
        
        if ($aparato) {
            return [$aparato['id'], $aparato['nombre']];
        } else {
            return [null, "No disponible"];
        }
    } catch (Exception $e) {
        return [null, "No disponible"];
    }
}

// Función para verificar problemas judiciales
function verificar_cedula_problema_judicial($pdo, $cedula) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM cedulas_problema_judicial WHERE cedula = ?");
        $stmt->execute([$cedula]);
        return $stmt->fetch() !== false;
    } catch (Exception $e) {
        return false;
    }
}

// Debug: verificar si se está ejecutando el archivo
try {
    writeDebugLog('🔍 Archivo agregar_postulante.php ejecutándose - Método: ' . $_SERVER['REQUEST_METHOD']);
} catch (Exception $e) {
    // Si hay error con writeDebugLog, usar error_log
    error_log('Error en writeDebugLog: ' . $e->getMessage());
    error_log('🔍 Archivo agregar_postulante.php ejecutándose - Método: ' . $_SERVER['REQUEST_METHOD']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    writeDebugLog('📥 POST detectado - iniciando procesamiento');
    try {
        $pdo = getDBConnection();
        
        // Debug: mostrar todos los datos recibidos
        writeDebugLog('📥 Datos recibidos en el servidor PHP:');
        foreach ($_POST as $key => $value) {
            writeDebugLog("  $key: $value");
        }
        
        $nombre_completo = strtoupper(trim($_POST['nombre_completo']));
        $cedula = trim($_POST['cedula']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
        $unidad = $_POST['unidad'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $sexo = $_POST['sexo'] ?? '';
        $dedo_registrado = $_POST['dedo_registrado'] ?? '';
        $id_k40 = trim($_POST['id_k40'] ?? '');
        $uid_k40 = trim($_POST['uid_k40'] ?? ''); // Agregar esta línea
        $capturador_id = $_POST['capturador_id'] ?? '';
        
        // Guardar el capturador en la sesión para recordarlo
        if (!empty($capturador_id)) {
            $_SESSION['ultimo_capturador'] = $capturador_id;
        }
        
        // Validaciones básicas
        if (empty($nombre_completo) || empty($cedula) || empty($dedo_registrado) || $sexo === 'Seleccionar' || empty($unidad)) {
            throw new Exception('Todos los campos, incluido "Nombre Completo", "Dedo Registrado", "Sexo" y "Unidad", son obligatorios');
        }
        
        // Validar formato de cédula (solo números)
        if (!preg_match('/^\d+$/', $cedula)) {
            throw new Exception('La cédula debe contener solo números');
        }
        
        // Validar longitud de cédula (máximo 20 caracteres)
        if (strlen($cedula) > 20) {
            throw new Exception('La cédula no puede tener más de 20 dígitos');
        }
        
        // Validar longitud de otros campos
        if (strlen($nombre_completo) > 200) {
            throw new Exception('El nombre completo no puede tener más de 200 caracteres');
        }
        if (strlen($telefono) > 20) {
            throw new Exception('El teléfono no puede tener más de 20 caracteres');
        }
        if (strlen($dedo_registrado) > 50) {
            throw new Exception('El dedo registrado no puede tener más de 50 caracteres');
        }
        if (strlen($sexo) > 10) {
            throw new Exception('El sexo no puede tener más de 10 caracteres');
        }
        
        // Validar ID en K40 si se proporciona
        if ($id_k40 && (!is_numeric($id_k40) || $id_k40 < 1 || $id_k40 > 9999)) {
            throw new Exception('El ID en K40 debe ser un número entre 1 y 9999');
        }
        
        // Verificar si la cédula ya existe
        $stmt = $pdo->prepare("SELECT id FROM postulantes WHERE cedula = ?");
        $stmt->execute([$cedula]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe un postulante con esta cédula');
        }
        
        // Verificar problemas judiciales
        $problema_judicial = verificar_cedula_problema_judicial($pdo, $cedula);
        if ($problema_judicial) {
            // En la versión web, mostramos advertencia pero permitimos continuar
            $mensaje = 'ADVERTENCIA: Esta cédula tiene problemas judiciales. ¿Desea continuar?';
            $tipo_mensaje = 'warning';
            // Continuar con el proceso (en la versión original se pregunta al usuario)
        }
        
        // Calcular edad si se proporciona fecha de nacimiento
        $edad = null;
        if ($fecha_nacimiento) {
            $fecha_nac = new DateTime($fecha_nacimiento);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;
        }
        
        // Obtener fecha y hora actual con zona horaria de Paraguay
        $fecha_registro = date('Y-m-d H:i:s');
        
        // Verificar modo prueba
        $es_modo_prueba = verificar_modo_prueba_activo($pdo);
        
        if ($es_modo_prueba) {
            // Modo prueba - generar UID simulado
            $uid_k40 = rand(1000, 9999);
            $aparato_id = null;
            $aparato_nombre = "APARATO DE PRUEBA (0X0AB0) - MODO PRUEBA";
            
            // Buscar aparato de prueba
            $aparato_data = obtener_aparato_por_serial($pdo, "0X0AB0");
            if ($aparato_data[0]) {
                $aparato_id = $aparato_data[0];
            }
        } else {
            // Modo normal - usar el UID que viene del JavaScript (del K40)
            $uid_k40 = $uid_k40 ?: $id_k40 ?: 1;
            
            // Obtener información del aparato biométrico desde la base de datos
            // El serial del aparato se obtiene dinámicamente del JavaScript
            $serial_aparato = $_POST['serial_aparato'] ?? null;
            
            if ($serial_aparato) {
                $aparato_data = obtener_aparato_por_serial($pdo, $serial_aparato);
                if ($aparato_data[0]) {
                    $aparato_id = $aparato_data[0];
                    $aparato_nombre = $aparato_data[1]; // Usar el nombre de la BD
                } else {
                    $aparato_id = null;
                    $aparato_nombre = "Dispositivo desconocido ($serial_aparato)";
                }
            } else {
                $aparato_id = null;
                $aparato_nombre = "Dispositivo no especificado";
            }
            
            // Debug: mostrar qué UID se está usando
            writeDebugLog("🔧 UID K40 que se usará: $uid_k40");
            writeDebugLog("🔧 Aparato biométrico: $aparato_nombre (ID: $aparato_id)");
        }
        
        // Iniciar transacción explícita
        $pdo->beginTransaction();
        
        try {
                    // Insertar en la base de datos
                    $stmt = $pdo->prepare("
                        INSERT INTO postulantes (
                            nombre_completo, cedula, telefono, fecha_nacimiento, 
                            unidad, observaciones, fecha_registro, usuario_registrador, 
                            registrado_por, edad, sexo, dedo_registrado, aparato_id, uid_k40, aparato_nombre,
                            fecha_ultima_edicion, capturador_id
                        ) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
            
            $registrado_por = $_SESSION['grado'] . ' ' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
            
            // Debug: mostrar todos los datos que se van a insertar
            writeDebugLog('📤 Datos que se van a insertar en la BD:');
            writeDebugLog("  nombre_completo: $nombre_completo");
            writeDebugLog("  cedula: $cedula");
            writeDebugLog("  telefono: $telefono");
            writeDebugLog("  fecha_nacimiento: $fecha_nacimiento");
            writeDebugLog("  unidad: $unidad");
            writeDebugLog("  observaciones: $observaciones");
            writeDebugLog("  fecha_registro: $fecha_registro");
            writeDebugLog("  usuario_registrador: " . $_SESSION['user_id']);
            writeDebugLog("  registrado_por: $registrado_por");
            writeDebugLog("  edad: $edad");
            writeDebugLog("  sexo: $sexo");
            writeDebugLog("  dedo_registrado: $dedo_registrado");
            writeDebugLog("  aparato_id: $aparato_id");
            writeDebugLog("  uid_k40: $uid_k40");
            writeDebugLog("  aparato_nombre: $aparato_nombre");
            writeDebugLog("  fecha_ultima_edicion: $fecha_registro");
            writeDebugLog("  capturador_id: $capturador_id");
            
            $resultado_insert = $stmt->execute([
                $nombre_completo, $cedula, $telefono, $fecha_nacimiento, 
                $unidad, $observaciones, $fecha_registro, $_SESSION['user_id'], 
                $registrado_por, $edad, $sexo, $dedo_registrado, $aparato_id, $uid_k40, $aparato_nombre,
                $fecha_registro, $capturador_id  // Usar la misma fecha para fecha_ultima_edicion y agregar capturador_id
            ]);
            
            writeDebugLog("🔍 Resultado del execute(): " . ($resultado_insert ? 'TRUE' : 'FALSE'));
            
            if (!$resultado_insert) {
                $errorInfo = $stmt->errorInfo();
                writeDebugLog("❌ Error en la inserción: " . print_r($errorInfo, true));
                throw new Exception('Error al insertar en la base de datos: ' . $errorInfo[2]);
            }
            
            $postulante_id = $pdo->lastInsertId();
            writeDebugLog("🆔 ID del postulante insertado: $postulante_id");
            
            // Commit de la transacción
            writeDebugLog("💾 Haciendo commit de la transacción...");
            $pdo->commit();
            writeDebugLog("✅ Commit exitoso");
            
        } catch (Exception $e) {
            writeDebugLog("❌ Error en la transacción: " . $e->getMessage());
            writeDebugLog("🔄 Haciendo rollback...");
            $pdo->rollback();
            writeDebugLog("✅ Rollback completado");
            throw $e;
        }
        
        // Debug: confirmar que se guardó en la base de datos
        writeDebugLog("✅ Postulante guardado en BD con ID: $postulante_id, UID K40: $uid_k40");
        
        if ($problema_judicial) {
            $mensaje = "Postulante registrado correctamente en el aparato $aparato_nombre, con UID $uid_k40. [WARN] Nota: Se registró a pesar del problema judicial detectado.";
        } else {
            $mensaje = "Postulante registrado correctamente en el aparato $aparato_nombre, con UID $uid_k40.";
        }
        $tipo_mensaje = 'success';
        
        // Debug adicional: verificar que realmente se insertó
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM postulantes WHERE cedula = ?");
        $stmt->execute([$cedula]);
        $verificacion = $stmt->fetch();
        writeDebugLog("🔍 Verificación: Postulante con cédula $cedula encontrado: " . $verificacion['total']);
        
        // Redireccionar a página de confirmación con los datos del postulante
        $datos_postulante = [
            'id' => $postulante_id,
            'nombre_completo' => $nombre_completo,
            'cedula' => $cedula,
            'telefono' => $telefono,
            'fecha_nacimiento' => $fecha_nacimiento,
            'edad' => $edad,
            'sexo' => $sexo,
            'unidad' => $unidad,
            'observaciones' => $observaciones,
            'dedo_registrado' => $dedo_registrado,
            'aparato_nombre' => $aparato_nombre,
            'uid_k40' => $uid_k40,
            'fecha_registro' => $fecha_registro,
            'problema_judicial' => $problema_judicial
        ];
        
        // Guardar datos en sesión para mostrar en la página de confirmación
        $_SESSION['postulante_registrado'] = $datos_postulante;
        
        // Redireccionar a página de confirmación
        header('Location: confirmacion_registro.php');
        exit;
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
        writeDebugLog('❌ Error en el procesamiento: ' . $e->getMessage());
    }
} else {
    writeDebugLog('📄 GET detectado - mostrando formulario');
}

// Obtener unidades disponibles
$pdo = getDBConnection();
$unidades = $pdo->query("SELECT nombre FROM unidades WHERE activa = true ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Verificar modo prueba
$es_modo_prueba = verificar_modo_prueba_activo($pdo);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Agregar Postulante - Sistema Quira</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.php">
    
    <style>
        .device-status {
            transition: all 0.3s ease;
        }
        .device-connected {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .device-disconnected {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .device-connecting {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
        .form-control:focus {
            border-color: #2E5090;
            box-shadow: 0 0 0 0.2rem rgba(46, 80, 144, 0.25);
        }
        .btn-primary {
            background-color: #2E5090;
            border-color: #2E5090;
        }
        .btn-primary:hover {
            background-color: #1e3a6b;
            border-color: #1e3a6b;
        }
        .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .btn-success:disabled,
        .btn-secondary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        #submit-btn.btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
        }
        #submit-btn.btn-secondary:hover:not(:disabled) {
            background-color: #5a6268;
            border-color: #545b62;
        }
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
            font-weight: 500;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
            transform: translateY(-1px);
        }
        .btn-outline-info {
            border-color: #17a2b8;
            color: #17a2b8;
        }
        .btn-outline-info:hover {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .gap-3 {
            gap: 1rem !important;
        }
        .mobile-warning {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #2E5090, #1e3a6b);
            color: white;
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            text-align: center;
            padding: 2rem;
        }
        .mobile-warning h2 {
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .mobile-warning p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .mobile-warning .btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 12px 30px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .mobile-warning .btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
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
        
        /* Safe zone para el footer */
        body {
            padding-bottom: 80px;
        }
        
        .container {
            margin-bottom: 80px !important;
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
            
            /* Estilo para campos de solo lectura */
            .form-control[readonly] {
                background-color: #f8f9fa;
                border-color: #e9ecef;
                color: #6c757d;
                cursor: not-allowed;
            }
    </style>
</head>
<body>
    <!-- Advertencia para dispositivos móviles -->
    <div class="mobile-warning" id="mobile-warning">
        <div>
            <i class="fas fa-mobile-alt fa-3x mb-3"></i>
            <h2>Acceso No Disponible en Móviles</h2>
            <p>Esta página está optimizada para computadoras de escritorio.<br>
            Por favor, accede desde una computadora para una mejor experiencia.</p>
            <a href="dashboard.php" class="btn">
                <i class="fas fa-arrow-left mr-2"></i> Volver al Inicio
            </a>
        </div>
    </div>

    <div class="container mt-4" style="margin-bottom: 80px;">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-user-plus"></i> Agregar Postulante</h1>
                    <div>
                        <a href="dashboard.php" class="btn btn-outline-primary btn-sm mr-2">
                            <i class="fas fa-arrow-left"></i> Volver al Inicio
                        </a>
                        <span class="text-muted">Bienvenido: <?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></span>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm ml-2">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                
                <!-- Estado del dispositivo ZKTeco -->
                <div class="alert device-status device-connecting" id="device-status">
                    <i class="fas fa-fingerprint"></i> 
                    <span id="device-status-text">Conectando al dispositivo biométrico...</span>
                    <div class="mt-2">
                        <small id="device-details"></small>
                    </div>
                </div>
                
                <!-- Mensajes del sistema -->
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Formulario -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit"></i> AÑADIR NUEVO POSTULANTE</h5>
                        <small class="text-muted">Sistema de Registro Biométrico</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="postulante-form">
                            <!-- Sección 1: Información Personal -->
                            <h6 class="text-primary font-weight-bold mb-3"><i class="fas fa-user"></i> INFORMACIÓN PERSONAL</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="id_k40"><i class="fas fa-fingerprint"></i> ID en K40</label>
                                        <input type="number" class="form-control" id="id_k40" name="id_k40" 
                                               value="<?= htmlspecialchars($_POST['id_k40'] ?? '') ?>" 
                                               min="1" max="9999" placeholder="Se asigna automáticamente">
                                        <small class="form-text text-muted">Se asigna automáticamente, pero puede ser editado si es necesario</small>
                                        <div id="usuario-existente-info" class="mt-2" style="display: none;">
                                            <!-- Aquí se mostrará la información del usuario existente -->
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fecha_registro"><i class="fas fa-calendar"></i> Fecha Registro</label>
                                        <input type="text" class="form-control" id="fecha_registro" name="fecha_registro" 
                                               value="<?= date('d/m/Y H:i:s') ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cedula"><i class="fas fa-id-card"></i> Cédula *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="cedula" name="cedula" 
                                                   value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" 
                                                   pattern="[0-9]+" title="Solo números" required>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-info btn-sm" id="btn-buscar-preinscripto" title="Buscar en preinscriptos">
                                                    <i class="fas fa-search"></i> Buscar
                                                </button>
                                            </div>
                                        </div>
                                        <small class="form-text text-muted">Solo números, sin puntos ni guiones</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nombre_completo"><i class="fas fa-user"></i> Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                               value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" 
                                               style="text-transform: uppercase;" required>
                                        <small class="form-text text-muted">Ingrese el nombre completo (nombres y apellidos)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" style="display: none;">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" 
                                               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="fecha_nacimiento"><i class="fas fa-calendar"></i> Fecha Nacimiento</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                               value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="edad"><i class="fas fa-birthday-cake"></i> Edad</label>
                                        <input type="text" class="form-control" id="edad" name="edad" readonly>
                                        <small class="form-text text-muted">Se calcula automáticamente</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sexo"><i class="fas fa-venus-mars"></i> Sexo *</label>
                                        <select class="form-control" id="sexo" name="sexo" required>
                                            <option value="">Seleccionar</option>
                                            <option value="Hombre" <?= ($_POST['sexo'] ?? '') === 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                            <option value="Mujer" <?= ($_POST['sexo'] ?? '') === 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección 2: Información del Registro -->
                            <h6 class="text-primary font-weight-bold mb-3 mt-4"><i class="fas fa-clipboard-list"></i> INFORMACIÓN DEL REGISTRO</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unidad"><i class="fas fa-building"></i> Unidad *</label>
                                        <select class="form-control" id="unidad" name="unidad" required>
                                            <option value="">Seleccionar unidad</option>
                                            <?php foreach ($unidades as $unidad): ?>
                                            <option value="<?= htmlspecialchars($unidad) ?>" 
                                                    <?= ($_POST['unidad'] ?? '') === $unidad ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unidad) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dedo_registrado"><i class="fas fa-hand-paper"></i> Dedo Registrado *</label>
                                        <select class="form-control" id="dedo_registrado" name="dedo_registrado" required>
                                            <option value="">Seleccionar dedo</option>
                                            <option value="PD" <?= ($_POST['dedo_registrado'] ?? '') === 'PD' ? 'selected' : '' ?>>PD (Pulgar Derecho)</option>
                                            <option value="ID" <?= ($_POST['dedo_registrado'] ?? '') === 'ID' ? 'selected' : '' ?>>ID (Índice Derecho)</option>
                                            <option value="MD" <?= ($_POST['dedo_registrado'] ?? '') === 'MD' ? 'selected' : '' ?>>MD (Medio Derecho)</option>
                                            <option value="AD" <?= ($_POST['dedo_registrado'] ?? '') === 'AD' ? 'selected' : '' ?>>AD (Anular Derecho)</option>
                                            <option value="MeD" <?= ($_POST['dedo_registrado'] ?? '') === 'MeD' ? 'selected' : '' ?>>MeD (Meñique Derecho)</option>
                                            <option value="PI" <?= ($_POST['dedo_registrado'] ?? '') === 'PI' ? 'selected' : '' ?>>PI (Pulgar Izquierdo)</option>
                                            <option value="II" <?= ($_POST['dedo_registrado'] ?? '') === 'II' ? 'selected' : '' ?>>II (Índice Izquierdo)</option>
                                            <option value="MI" <?= ($_POST['dedo_registrado'] ?? '') === 'MI' ? 'selected' : '' ?>>MI (Medio Izquierdo)</option>
                                            <option value="AI" <?= ($_POST['dedo_registrado'] ?? '') === 'AI' ? 'selected' : '' ?>>AI (Anular Izquierdo)</option>
                                            <option value="MeI" <?= ($_POST['dedo_registrado'] ?? '') === 'MeI' ? 'selected' : '' ?>>MeI (Meñique Izquierdo)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aparato_biometrico"><i class="fas fa-fingerprint"></i> Aparato Biométrico</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="aparato_biometrico" name="aparato_biometrico" 
                                                   value="<?= $es_modo_prueba ? 'APARATO DE PRUEBA (0X0AB0) - MODO PRUEBA' : 'Detectando dispositivo...' ?>" readonly>
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="actualizarAparatoManual()" title="Actualizar nombre del aparato">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <!-- Campo hidden para enviar el serial del aparato -->
                                        <input type="hidden" id="serial_aparato" name="serial_aparato" value="">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="capturador_id"><i class="fas fa-user-check"></i> Capturador huella *</label>
                                        <select class="form-control" id="capturador_id" name="capturador_id" required>
                                            <option value="">Seleccionar capturador</option>
                                            <?php foreach ($usuarios_sistema as $usuario): ?>
                                            <option value="<?= htmlspecialchars($usuario['id']) ?>" 
                                                    <?= ($_POST['capturador_id'] ?? $ultimo_capturador) == $usuario['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars(trim($usuario['grado'] . ' ' . $usuario['nombre'] . ' ' . $usuario['apellido'])) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">
                                            <i class="fas fa-info-circle"></i> Usuario que capturó la huella en el dispositivo K40. <i class="fas fa-lightbulb"></i> El sistema recordará el último capturador seleccionado durante esta sesión.
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Campo Observaciones -->
                            <div class="row">
                                <div class="col-md-6 offset-md-3">
                                    <div class="form-group">
                                        <label for="observaciones"><i class="fas fa-sticky-note"></i> Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mt-5">
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="submit" class="btn btn-success btn-lg px-4 py-2" id="submit-btn" disabled>
                                        <i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-4 py-2">
                                        <i class="fas fa-times mr-2"></i> CANCELAR
                                    </a>
                                </div>
                                <div class="text-center mt-3">
                                    <button type="button" class="btn btn-outline-info btn-sm mr-2" id="test-connection-btn">
                                        <i class="fas fa-plug mr-1"></i> Probar Conexión
                                    </button>
                                    <a href="agregar_postulante_x.php" class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-user-plus mr-1"></i> Agregar Postulante X
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="assets/js/zkteco-bridge.js?v=20251030-1"></script>
    
     <script>
     console.log('🔧 JavaScript cargando...');
     
     // Detectar dispositivos móviles y mostrar advertencia
     function detectarDispositivoMovil() {
         const esMovil = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) || 
                        window.innerWidth <= 768 || 
                        ('ontouchstart' in window);
         
         if (esMovil) {
             document.getElementById('mobile-warning').style.display = 'flex';
             document.body.style.overflow = 'hidden';
         }
     }
     
     // Ejecutar detección al cargar la página
     document.addEventListener('DOMContentLoaded', detectarDispositivoMovil);
     
     // También detectar en resize
     window.addEventListener('resize', detectarDispositivoMovil);
     
    // Variables globales (replica exacta del software original)
    let zktecoBridge = null;
    let postulanteId = <?= $postulante_id ? json_encode($postulante_id) : 'null' ?>;
    let esModoPrueba = <?= $es_modo_prueba ? 'true' : 'false' ?>;
    let dispositivoConectado = false;
    let ultimoUID = null;
    
    // Función para verificar y habilitar el botón de guardar
    function verificarYHabilitarBoton() {
        const submitBtn = document.getElementById('submit-btn');
        const aparatoBiometrico = document.getElementById('aparato_biometrico');

        if (!submitBtn || !aparatoBiometrico) {
            return;
        }

        const advertenciaUltimoActiva = document.getElementById('advertencia-ultimo-usuario') &&
                                        document.getElementById('advertencia-ultimo-usuario').style.display !== 'none';
        const advertenciaManualActiva = document.getElementById('advertencia-usuario-manual') &&
                                        document.getElementById('advertencia-usuario-manual').style.display !== 'none';
        const advertenciaIdAdelantadoActiva = document.getElementById('advertencia-id-adelantado') &&
                                              document.getElementById('advertencia-id-adelantado').style.display !== 'none';
        const advertenciaActiva = advertenciaUltimoActiva || advertenciaManualActiva || advertenciaIdAdelantadoActiva;

        if (advertenciaActiva) {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-danger btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> GUARDADO DESHABILITADO';
            submitBtn.title = 'El guardado se deshabilitó por advertencia de seguridad.';
            console.log('⛔ Botón bloqueado por advertencia activa');
            return;
        }

        const valorAparato = aparatoBiometrico.value.trim();
        const dispositivoDetectado = valorAparato !== '' &&
                                     valorAparato !== 'Detectando dispositivo...' &&
                                     valorAparato !== 'Dispositivo no detectado';

        if (dispositivoDetectado) {
            submitBtn.disabled = false;
            submitBtn.className = esModoPrueba
                ? 'btn btn-warning btn-lg px-4 py-2'
                : 'btn btn-success btn-lg px-4 py-2';
            submitBtn.innerHTML = esModoPrueba
                ? '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE (MODO PRUEBA)'
                : '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
            submitBtn.title = esModoPrueba ? 'Modo prueba activo.' : 'Listo para guardar.';
            console.log('✅ Botón habilitado - Dispositivo detectado:', valorAparato);
        } else {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-secondary btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> DISPOSITIVO NO CONECTADO';
            submitBtn.title = 'Esperando detección del dispositivo biométrico...';
            console.log('⏳ Botón deshabilitado - Esperando detección del dispositivo');
        }
    }
    
     // Inicializar cuando el DOM esté listo (replica exacta del software original)
     document.addEventListener('DOMContentLoaded', async () => {
         console.log('🔧 DOM cargado - Iniciando sistema QUIRA web...');
        
        // Paso 1: Mostrar estado inicial
        updateDeviceStatus('Inicializando sistema...', 'connecting');
        
        try {
            // Paso 2: Crear instancia del bridge
            zktecoBridge = createZKTecoBridge({
                wsUrl: 'ws://localhost:8001/ws/zkteco',
                httpUrl: 'http://localhost:8001'
            });
            
            // Configurar handlers de conexión
            zktecoBridge.onConnect(() => {
                console.log('Conectado al bridge ZKTeco');
                updateDeviceStatus('Conectando al dispositivo biométrico...', 'connecting');
            });
            
            zktecoBridge.onDisconnect(() => {
                console.log('Desconectado del bridge ZKTeco');
                updateDeviceStatus('Desconectado del bridge ZKTeco', 'disconnected');
                dispositivoConectado = false;
                // Deshabilitar botón cuando se desconecta
                document.getElementById('aparato_biometrico').value = 'Detectando dispositivo...';
                verificarYHabilitarBoton();
            });
            
            zktecoBridge.onError((error) => {
                console.error('Error en bridge ZKTeco:', error);
                updateDeviceStatus('Error en la conexión: ' + error, 'disconnected');
                dispositivoConectado = false;
                // Deshabilitar botón cuando hay error
                document.getElementById('aparato_biometrico').value = 'Detectando dispositivo...';
                verificarYHabilitarBoton();
            });
            
            // Paso 3: Conectar al bridge
            await zktecoBridge.connect();
            
            // Paso 3.5: Resetear conexión para limpiar estado corrupto
            console.log('🔄 Reseteando conexión para limpiar estado...');
            updateDeviceStatus('Limpiando estado previo...', 'connecting');
            await zktecoBridge.reset();
            
            // Paso 4: Verificar modo prueba
            if (esModoPrueba) {
                console.log('Modo prueba activado');
                updateDeviceStatus('[BUILD] Modo prueba activado', 'connected');
                await inicializarModoPrueba();
            } else {
                // Paso 5: Conectar al dispositivo real
                const deviceConnected = await zktecoBridge.connectToDevice();
                
                if (deviceConnected) {
                    dispositivoConectado = true;
                    console.log('✅ Dispositivo biométrico conectado');
                    updateDeviceStatus('[OK] Sistema listo - QUIRA conectado', 'connected');
                    await inicializarDispositivoReal();
                } else {
                    console.log('❌ Error: No se pudo conectar al dispositivo biométrico');
                    updateDeviceStatus('Error: No se pudo conectar al dispositivo biométrico', 'disconnected');
                    mostrarAvisoConexion();
                    // Asegurar que el campo vuelva a "Detectando dispositivo..." y deshabilitar botón
                    document.getElementById('aparato_biometrico').value = 'Detectando dispositivo...';
                    verificarYHabilitarBoton();
                }
            }
            
        } catch (error) {
            console.error('Error inicializando bridge:', error);
            updateDeviceStatus('Error: No se pudo conectar al bridge ZKTeco - ' + error.message, 'disconnected');
            // Asegurar que el campo vuelva a "Detectando dispositivo..." y deshabilitar botón
            const aparatoInput = document.getElementById('aparato_biometrico');
            if (aparatoInput) {
                aparatoInput.value = 'Detectando dispositivo...';
            }
            verificarYHabilitarBoton();
        }
    });
    
    // Inicializar modo prueba
    async function inicializarModoPrueba() {
        try {
            // Generar ID simulado
            const uidSimulado = Math.floor(Math.random() * 9000) + 1000;
            document.getElementById('id_k40').value = uidSimulado;
            ultimoUID = uidSimulado;
            
            // Actualizar aparato biométrico
            document.getElementById('aparato_biometrico').value = 'APARATO DE PRUEBA (0X0AB0) - MODO PRUEBA';
            document.getElementById('serial_aparato').value = '0X0AB0';
            
            // Habilitar botón en modo prueba
            verificarYHabilitarBoton();
            
            console.log(`Modo prueba: ID simulado generado: ${uidSimulado}`);
            
        } catch (error) {
            console.error('Error en modo prueba:', error);
        }
    }
    
    // Inicializar dispositivo real
    async function inicializarDispositivoReal() {
        try {
            console.log('Inicializando dispositivo real...');
            
            // Obtener información del dispositivo
            console.log('Obteniendo información del dispositivo...');
            await loadDeviceInfo();
            
            // Obtener último UID del dispositivo
            console.log('Obteniendo último UID del dispositivo...');
            await obtenerUltimoUID();
            
            // Actualizar aparato biométrico
            console.log('Actualizando nombre del aparato biométrico...');
            const deviceInfo = await zktecoBridge.getDeviceInfo();
            console.log('Device info:', deviceInfo);
            
             if (deviceInfo && deviceInfo.device_info && deviceInfo.device_info.serial_number) {
                 // Obtener el nombre del aparato desde la base de datos
                 try {
                     console.log('Enviando consulta para serial:', deviceInfo.device_info.serial_number);
                     
                     const response = await fetch(`obtener_aparato_por_serial.php?serial=${encodeURIComponent(deviceInfo.device_info.serial_number)}`, {
                         method: 'GET'
                     });
                     
                     console.log('Respuesta recibida, status:', response.status);
                     
                     if (response.ok) {
                         const data = await response.json();
                         console.log('Datos recibidos:', data);
                         
                        if (data.success && data.aparato) {
                            document.getElementById('aparato_biometrico').value = data.aparato.nombre;
                        verificarYHabilitarBoton();
                            document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                            console.log(`✅ Aparato biométrico actualizado: ${data.aparato.nombre}`);
                            // Habilitar botón cuando se detecta el dispositivo
                            verificarYHabilitarBoton();
                        } else {
                            document.getElementById('aparato_biometrico').value = `Dispositivo (${deviceInfo.device_info.serial_number})`;
                        verificarYHabilitarBoton();
                            document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                            console.log(`❌ Aparato no encontrado en BD, usando serial: ${deviceInfo.device_info.serial_number}`);
                            console.log('Respuesta del servidor:', data);
                            // Habilitar botón aunque no esté en BD (tiene serial válido)
                            verificarYHabilitarBoton();
                        }
                     } else {
                         document.getElementById('aparato_biometrico').value = `Dispositivo (${deviceInfo.device_info.serial_number})`;
                        verificarYHabilitarBoton();
                         document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                         console.log('❌ Error HTTP obteniendo datos del aparato, status:', response.status);
                         const errorText = await response.text();
                         console.log('Error response:', errorText);
                         // Habilitar botón aunque haya error HTTP (tiene serial válido)
                         verificarYHabilitarBoton();
                     }
                 } catch (error) {
                     console.error('❌ Error consultando aparato:', error);
                     document.getElementById('aparato_biometrico').value = `Dispositivo (${deviceInfo.device_info.serial_number})`;
                     document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                     // Habilitar botón aunque haya error (tiene serial válido)
                     verificarYHabilitarBoton();
                 }
             } else {
                 console.log('No se pudo obtener el serial del dispositivo');
                 document.getElementById('aparato_biometrico').value = 'Dispositivo no detectado';
                 // No habilitar botón si no hay serial
                 verificarYHabilitarBoton();
             }
            
        } catch (error) {
            console.error('Error inicializando dispositivo real:', error);
        }
    }
    
    // Obtener último UID del dispositivo
    async function obtenerUltimoUID() {
        try {
            console.log('Obteniendo información del dispositivo...');
            // Primero obtener información del dispositivo para saber el total de usuarios
            const deviceInfo = await zktecoBridge.getDeviceInfo();
            console.log('Device info response:', deviceInfo);
            
            if (deviceInfo && deviceInfo.device_info && deviceInfo.device_info.user_count) {
                // Usar el contador del dispositivo como base
                const totalUsuarios = deviceInfo.device_info.user_count;
                console.log(`Total de usuarios en el dispositivo: ${totalUsuarios}`);
                
                // Intentar obtener usuarios del dispositivo
                const users = await zktecoBridge.getUsers();
                console.log('Users response:', users);
                
                if (users && users.users && users.users.length > 0) {
                    // Encontrar el UID más alto entre los usuarios obtenidos
                    const uidMasAlto = Math.max(...users.users.map(u => parseInt(u.uid)));
                    console.log(`ID más alto detectado en K40: ${uidMasAlto}`);
                    console.log(`Usuarios obtenidos: ${users.users.length} de ${totalUsuarios}`);
                    
                    // Si el número de usuarios obtenidos es menor al total, 
                    // asumir que el siguiente ID disponible es el total (no +1)
                    if (users.users.length < totalUsuarios) {
                        const siguienteID = totalUsuarios;
                        console.log(`Usando siguiente ID disponible: ${siguienteID} (basado en total del dispositivo)`);
                        document.getElementById('id_k40').value = siguienteID;
                        ultimoUID = siguienteID;
                    } else {
                        document.getElementById('id_k40').value = uidMasAlto;
                        ultimoUID = uidMasAlto;
                    }
                    
                    // Mostrar todos los UIDs para debug
                    const todosUIDs = users.users.map(u => u.uid).sort((a, b) => a - b);
                    console.log(`UIDs obtenidos: [${todosUIDs.join(', ')}]`);
                } else {
                    // Si no se pueden obtener usuarios, usar el contador del dispositivo
                    const siguienteID = totalUsuarios;
                    console.log(`No se pudieron obtener usuarios, usando siguiente ID: ${siguienteID}`);
                    document.getElementById('id_k40').value = siguienteID;
                    ultimoUID = siguienteID;
                }
            } else {
                console.log('No se pudo obtener información del dispositivo');
                document.getElementById('id_k40').value = '';
                ultimoUID = null;
            }
        } catch (error) {
            console.error('Error obteniendo último UID:', error);
            document.getElementById('id_k40').value = '';
            ultimoUID = null;
        }
    }
    
    // Mostrar aviso de conexión
    function mostrarAvisoConexion() {
        const mensaje = `[WARN] No se pudo conectar al dispositivo biométrico

Por favor verifique:
• El dispositivo biométrico esté encendido
• El cable de red esté conectado correctamente
• La IP del dispositivo sea 192.168.100.201
• No haya problemas de red

¿Desea reintentar la conexión?`;
        
        if (confirm(mensaje)) {
            reintentarConexion();
        } else {
            updateDeviceStatus('[ERROR] Conexión cancelada por el usuario', 'disconnected');
        }
    }
    
    // Función para verificar usuario existente en tiempo real
    async function verificarUsuarioExistente(uid) {
        const infoDiv = document.getElementById('usuario-existente-info');
        
        // Limpiar información anterior
        infoDiv.style.display = 'none';
        infoDiv.innerHTML = '';
        
        // Si no hay UID o no está conectado, no hacer nada
        if (!uid || !dispositivoConectado || esModoPrueba || !zktecoBridge) {
            return;
        }
        
        try {
            // Obtener usuarios del dispositivo
            const users = await zktecoBridge.getUsers();
            const usuarioExistente = users.users.find(u => parseInt(u.uid) === parseInt(uid));
            
            if (usuarioExistente && usuarioExistente.name && !usuarioExistente.name.startsWith("NN-")) {
                // Mostrar información del usuario existente
                infoDiv.innerHTML = `
                    <div class="alert alert-warning alert-sm mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Usuario ya registrado:</strong> 
                        <span class="badge badge-info">${usuarioExistente.name}</span>
                        <br>
                        <small>Este ID ya está asignado. Para modificar, use "Buscar Postulantes"</small>
                    </div>
                `;
                infoDiv.style.display = 'block';
                
                // Mostrar advertencia de seguridad y deshabilitar botón
                mostrarAdvertenciaUsuarioManual(uid, usuarioExistente.name);
            } else if (usuarioExistente && usuarioExistente.name && usuarioExistente.name.startsWith("NN-")) {
                // Usuario disponible (sin nombre real)
                infoDiv.innerHTML = `
                    <div class="alert alert-success alert-sm mb-0">
                        <i class="fas fa-check-circle"></i>
                        <strong>ID disponible:</strong> Usuario sin asignar
                    </div>
                `;
                infoDiv.style.display = 'block';
                
                // Ocultar todas las advertencias
                ocultarAdvertenciaUsuarioManual();
                ocultarAdvertenciaUltimoUsuario();
                ocultarAdvertenciaIdAdelantado();
            } else {
                // ID no existe en el dispositivo - verificar si es adelantado
                const maxUid = Math.max(...users.users.map(u => parseInt(u.uid)), 0);
                
                if (parseInt(uid) > maxUid) {
                    // ID adelantado - no permitir
                    infoDiv.innerHTML = `
                        <div class="alert alert-warning alert-sm mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>ID adelantado:</strong> No puede usar IDs futuros (máximo: ${maxUid})
                        </div>
                    `;
                    infoDiv.style.display = 'block';
                    
                    // Mostrar advertencia y deshabilitar botón
                    mostrarAdvertenciaIdAdelantado(uid, maxUid);
                } else {
                    // ID válido (menor o igual al máximo existente)
                    infoDiv.innerHTML = `
                        <div class="alert alert-info alert-sm mb-0">
                            <i class="fas fa-info-circle"></i>
                            <strong>ID válido:</strong> Puede asignar nombre a este ID
                        </div>
                    `;
                    infoDiv.style.display = 'block';
                    
                    // Ocultar todas las advertencias ya que este ID es válido
                    ocultarAdvertenciaUsuarioManual();
                    ocultarAdvertenciaUltimoUsuario();
                    ocultarAdvertenciaIdAdelantado();
                }
            }
        } catch (error) {
            console.error('Error verificando usuario existente:', error);
        }
    }
    
    // Función para mostrar advertencia del último usuario
    function mostrarAdvertenciaUltimoUsuario(uid, nombre) {
        // Verificar si ya existe la advertencia
        let advertencia = document.getElementById('advertencia-ultimo-usuario');
        
        if (!advertencia) {
            advertencia = document.createElement('div');
            advertencia.id = 'advertencia-ultimo-usuario';
            advertencia.className = 'alert alert-warning alert-dismissible fade show';
            advertencia.style.marginTop = '10px';
            
            // Insertar después del estado del dispositivo
            const deviceStatus = document.getElementById('device-status');
            deviceStatus.parentNode.insertBefore(advertencia, deviceStatus.nextSibling);
        }
        
        advertencia.innerHTML = `
            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Advertencia de Seguridad</h6>
            <p><strong>El último usuario registrado (ID ${uid}) ya tiene nombre asignado:</strong></p>
            <p class="mb-2"><span class="badge badge-info">${nombre}</span></p>
            <p class="mb-0"><small><strong>El guardado está deshabilitado por razones de seguridad.</strong> Contacte al administrador del sistema.</small></p>
        `;
        advertencia.style.display = 'block';
        
        // Deshabilitar botón de guardar por seguridad
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-danger btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> GUARDADO DESHABILITADO - SEGURIDAD';
        }
    }
    
    // Función para ocultar advertencia del último usuario
    function ocultarAdvertenciaUltimoUsuario() {
        const advertencia = document.getElementById('advertencia-ultimo-usuario');
        if (advertencia) {
            advertencia.style.display = 'none';
        }
        
        // Rehabilitar botón si el dispositivo está conectado y no hay otras advertencias
        const advertenciaManual = document.getElementById('advertencia-usuario-manual');
        const advertenciaIdAdelantado = document.getElementById('advertencia-id-adelantado');
        if ((!advertenciaManual || advertenciaManual.style.display === 'none') && 
            (!advertenciaIdAdelantado || advertenciaIdAdelantado.style.display === 'none')) {
            const submitBtn = document.getElementById('submit-btn');
            if (submitBtn && dispositivoConectado) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-success btn-lg px-4 py-2';
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
            }
        }
    }
    
    function mostrarAdvertenciaUsuarioManual(uid, nombre) {
        let advertencia = document.getElementById('advertencia-usuario-manual');
        if (!advertencia) {
            advertencia = document.createElement('div');
            advertencia.id = 'advertencia-usuario-manual';
            advertencia.className = 'alert alert-danger alert-dismissible fade show';
            advertencia.style.marginTop = '10px';
            const deviceStatus = document.getElementById('device-status');
            deviceStatus.parentNode.insertBefore(advertencia, deviceStatus.nextSibling);
        }
        
        advertencia.innerHTML = `
            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Advertencia de Seguridad</h6>
            <p><strong>El ID ${uid} que ingresó manualmente ya está asignado a:</strong></p>
            <p class="mb-2"><span class="badge badge-info">${nombre}</span></p>
            <p class="mb-0"><small><strong>El guardado está deshabilitado por razones de seguridad.</strong> Para modificar este usuario, use "Buscar Postulantes".</small></p>
        `;
        advertencia.style.display = 'block';
        
        // Deshabilitar botón
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-danger btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> GUARDADO DESHABILITADO';
        }
    }
    
    function ocultarAdvertenciaUsuarioManual() {
        const advertencia = document.getElementById('advertencia-usuario-manual');
        if (advertencia) {
            advertencia.style.display = 'none';
        }
        
        // Rehabilitar botón si el dispositivo está conectado y no hay otras advertencias
        const advertenciaUltimo = document.getElementById('advertencia-ultimo-usuario');
        const advertenciaIdAdelantado = document.getElementById('advertencia-id-adelantado');
        if ((!advertenciaUltimo || advertenciaUltimo.style.display === 'none') && 
            (!advertenciaIdAdelantado || advertenciaIdAdelantado.style.display === 'none')) {
            const submitBtn = document.getElementById('submit-btn');
            if (submitBtn && dispositivoConectado) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-success btn-lg px-4 py-2';
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
            }
        }
    }
    
    function mostrarAdvertenciaIdAdelantado(uid, maxUid) {
        let advertencia = document.getElementById('advertencia-id-adelantado');
        if (!advertencia) {
            advertencia = document.createElement('div');
            advertencia.id = 'advertencia-id-adelantado';
            advertencia.className = 'alert alert-warning alert-dismissible fade show';
            advertencia.style.marginTop = '10px';
            const deviceStatus = document.getElementById('device-status');
            deviceStatus.parentNode.insertBefore(advertencia, deviceStatus.nextSibling);
        }
        
        advertencia.innerHTML = `
            <h6 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> ID Adelantado</h6>
            <p><strong>El ID ${uid} que ingresó es adelantado.</strong></p>
            <p class="mb-2">Máximo ID disponible: <span class="badge badge-primary">${maxUid}</span></p>
            <p class="mb-0"><small><strong>El guardado está deshabilitado.</strong></small></p>
        `;
        advertencia.style.display = 'block';
        
        // Deshabilitar botón
        const submitBtn = document.getElementById('submit-btn');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-warning btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> ID ADELANTADO';
        }
    }
    
    function ocultarAdvertenciaIdAdelantado() {
        const advertencia = document.getElementById('advertencia-id-adelantado');
        if (advertencia) {
            advertencia.style.display = 'none';
        }
        
        // Rehabilitar botón si el dispositivo está conectado y no hay otras advertencias
        const advertenciaUltimo = document.getElementById('advertencia-ultimo-usuario');
        const advertenciaManual = document.getElementById('advertencia-usuario-manual');
        if ((!advertenciaUltimo || advertenciaUltimo.style.display === 'none') && 
            (!advertenciaManual || advertenciaManual.style.display === 'none')) {
            const submitBtn = document.getElementById('submit-btn');
            if (submitBtn && dispositivoConectado) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-success btn-lg px-4 py-2';
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
            }
        }
    }
    
    // Función para mostrar alerta de usuario existente
    function mostrarAlertaUsuarioExistente(uid, nombreExistente) {
        const alerta = document.createElement('div');
        alerta.className = 'alert alert-warning alert-dismissible fade show';
        alerta.style.marginTop = '20px';
        alerta.innerHTML = `
            <h4 class="alert-heading"><i class="fas fa-user-exclamation"></i> Usuario Ya Registrado</h4>
            <div class="row">
                <div class="col-md-6">
                    <p><strong>ID en Dispositivo:</strong> ${uid}</p>
                    <p><strong>Nombre Actual:</strong> <span class="badge badge-info">${nombreExistente}</span></p>
                </div>
                <div class="col-md-6">
                    <p><strong>Acción Requerida:</strong></p>
                    <p>Este ID ya está asignado a otro postulante. Para modificar los datos de un usuario existente, utiliza:</p>
                </div>
            </div>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <strong>📋 BUSCAR POSTULANTES → Editar Postulante</strong><br>
                    <small class="text-muted">Esta función es solo para agregar nuevos postulantes</small>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.location.href='buscar_postulantes.php'">
                        <i class="fas fa-search"></i> Buscar Postulantes
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="this.closest('.alert').remove()">
                        <i class="fas fa-times"></i> Cerrar
                    </button>
                </div>
            </div>
        `;
        
        // Insertar después del estado del dispositivo
        const deviceStatus = document.getElementById('device-status');
        deviceStatus.parentNode.insertBefore(alerta, deviceStatus.nextSibling);
        
        // Scroll suave hacia la alerta
        alerta.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Reintentar conexión
    async function reintentarConexion() {
        updateDeviceStatus('[REFRESH] Reintentando conexión al dispositivo...', 'connecting');
        
        try {
            const deviceConnected = await zktecoBridge.connectToDevice();
            
            if (deviceConnected) {
                dispositivoConectado = true;
                updateDeviceStatus('[OK] Conexión restablecida exitosamente', 'connected');
                await inicializarDispositivoReal();
            } else {
                updateDeviceStatus('[ERROR] Conexión fallida', 'disconnected');
            }
        } catch (error) {
            console.error('Error en reconexión:', error);
            updateDeviceStatus('[ERROR] Conexión fallida', 'disconnected');
        }
    }
    
    // Función para actualizar manualmente el nombre del aparato
    async function actualizarAparatoManual() {
        try {
            console.log('Actualización manual del aparato biométrico...');
            
            // Intentar obtener información del dispositivo
            let deviceInfo = null;
            try {
                deviceInfo = await zktecoBridge.getDeviceInfo();
                console.log('Device info obtenida:', deviceInfo);
            } catch (error) {
                console.log('No se pudo obtener device info, usando serial por defecto');
                // Usar el serial que sabemos que funciona
                deviceInfo = { device_info: { serial_number: 'A8MX193760004' } };
            }
            
            if (deviceInfo && deviceInfo.device_info && deviceInfo.device_info.serial_number) {
                console.log('Consultando aparato con serial:', deviceInfo.device_info.serial_number);
                
                const response = await fetch(`obtener_aparato_por_serial.php?serial=${encodeURIComponent(deviceInfo.device_info.serial_number)}`, {
                    method: 'GET'
                });
                
                console.log('Respuesta recibida, status:', response.status);
                
                if (response.ok) {
                    const data = await response.json();
                    console.log('Datos recibidos:', data);
                    
                    if (data.success && data.aparato) {
                        document.getElementById('aparato_biometrico').value = data.aparato.nombre;
                        verificarYHabilitarBoton();
                        document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                        console.log(`✅ Aparato actualizado manualmente: ${data.aparato.nombre}`);
                        
                        // Mostrar mensaje de éxito
                        showToast('Aparato actualizado: ' + data.aparato.nombre, 'success');
                    } else {
                        document.getElementById('aparato_biometrico').value = `Dispositivo (${deviceInfo.device_info.serial_number})`;
                        verificarYHabilitarBoton();
                        document.getElementById('serial_aparato').value = deviceInfo.device_info.serial_number;
                        console.log(`❌ Aparato no encontrado: ${deviceInfo.device_info.serial_number}`);
                        showToast('Aparato no encontrado en la base de datos', 'warning');
                    }
                } else {
                    console.log('❌ Error HTTP:', response.status);
                    showToast('Error al consultar el aparato', 'error');
                }
            } else {
                console.log('❌ No se pudo obtener serial del dispositivo');
                showToast('No se pudo obtener información del dispositivo', 'error');
            }
        } catch (error) {
            console.error('❌ Error en actualización manual:', error);
            showToast('Error al actualizar el aparato: ' + error.message, 'error');
        }
    }
    
    // Función para buscar preinscripto por CI
    async function buscarPreinscripto() {
        console.log('🔍 buscarPreinscripto() llamada');
        
        const cedulaInput = document.getElementById('cedula');
        if (!cedulaInput) {
            console.error('❌ Campo cédula no encontrado');
            showToast('Error: Campo cédula no encontrado', 'error');
            return;
        }
        
        const ci = cedulaInput.value.trim();
        console.log('📝 CI ingresada:', ci);
        
        if (!ci) {
            showToast('Por favor ingrese una CI para buscar', 'warning');
            cedulaInput.focus();
            return;
        }
        
        // Validar que sea solo números
        if (!/^\d+$/.test(ci)) {
            showToast('La CI debe contener solo números', 'error');
            return;
        }
        
        try {
            // Mostrar indicador de carga
            const buscarBtn = cedulaInput.closest('.input-group').querySelector('#btn-buscar-preinscripto');
            if (!buscarBtn) {
                console.error('❌ Botón de buscar no encontrado');
                showToast('Error: Botón de buscar no encontrado', 'error');
                return;
            }
            
            const originalText = buscarBtn.innerHTML;
            buscarBtn.disabled = true;
            buscarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
            
            // Construir URL - usar sin .php directamente ya que el servidor tiene rewrite rules
            const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
            // Usar URL sin .php ya que el servidor tiene rewrite rules que lo quitan
            // Pasar parámetro como query string para que funcione con redirects
            const url = window.location.origin + basePath + 'buscar_preinscripto_ajax.php';
            const urlWithoutExt = window.location.origin + basePath + 'buscar_preinscripto_ajax';
            
            console.log('📤 Enviando petición POST a:', url);
            console.log('📦 Datos:', { ci: ci });
            
            const data = await new Promise(async (resolve, reject) => {
                try {
                    // Primero intentar POST normal
                    const formData = new URLSearchParams();
                    formData.append('ci', ci);
                    
                    let response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Cache-Control': 'no-cache'
                        },
                        body: formData.toString(),
                        credentials: 'same-origin',
                        redirect: 'follow'
                    });
                    
                    console.log('📥 Respuesta recibida:', response.status, response.statusText);
                    console.log('📋 URL final:', response.url);
                    
                    // Si hay un redirect y la URL cambió (sin .php), o si recibimos 405,
                    // intentar con GET usando query string
                    if ((response.url !== url && response.url.includes('buscar_preinscripto_ajax') && !response.url.includes('.php')) || response.status === 405) {
                        console.log('⚠️ Detectado redirect o 405, reintentando con GET y query string...');
                        const urlWithParam = urlWithoutExt + '?ci=' + encodeURIComponent(ci);
                        response = await fetch(urlWithParam, {
                            method: 'GET',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Cache-Control': 'no-cache'
                            },
                            credentials: 'same-origin'
                        });
                        console.log('📥 Respuesta GET:', response.status, response.statusText);
                        console.log('📋 URL GET:', response.url);
                    }
                    
                    const responseText = await response.text();
                    
                    if (response.status >= 200 && response.status < 300) {
                        try {
                            const responseData = JSON.parse(responseText);
                            resolve(responseData);
                        } catch (e) {
                            reject(new Error('Error al parsear JSON: ' + e.message + ' - Respuesta: ' + responseText.substring(0, 100)));
                        }
                    } else {
                        try {
                            const errorData = JSON.parse(responseText);
                            reject(new Error(errorData.message || 'Error del servidor'));
                        } catch (e) {
                            reject(new Error('Error del servidor: ' + response.status + ' - ' + responseText.substring(0, 100)));
                        }
                    }
                } catch (error) {
                    reject(new Error('Error de red: ' + error.message));
                }
            });
            
            // Restaurar botón
            buscarBtn.disabled = false;
            buscarBtn.innerHTML = originalText;
            
            if (data.success && data.data) {
                // Completar los campos del formulario
                const preinscripto = data.data;
                
                // Nombre completo
                if (document.getElementById('nombre_completo')) {
                    document.getElementById('nombre_completo').value = preinscripto.nombre_completo;
                }
                
                // Fecha de nacimiento
                if (document.getElementById('fecha_nacimiento')) {
                    document.getElementById('fecha_nacimiento').value = preinscripto.fecha_nacimiento;
                    // Calcular edad automáticamente
                    calcularEdad();
                }
                
                // Sexo
                if (document.getElementById('sexo')) {
                    document.getElementById('sexo').value = preinscripto.sexo;
                }
                
                // Unidad
                if (document.getElementById('unidad')) {
                    // Buscar la opción que coincida con el valor
                    const unidadSelect = document.getElementById('unidad');
                    const unidadValue = preinscripto.unidad;
                    
                    // Buscar por texto exacto primero
                    let found = false;
                    for (let option of unidadSelect.options) {
                        if (option.text === unidadValue) {
                            option.selected = true;
                            found = true;
                            break;
                        }
                    }
                    
                    // Si no se encuentra, intentar por valor
                    if (!found) {
                        unidadSelect.value = unidadValue;
                    }
                }
                
                showToast('Datos del preinscripto cargados correctamente', 'success');
            } else {
                showToast(data.message || 'No se encontró ningún preinscripto con esa CI', 'warning');
            }
        } catch (error) {
            console.error('Error al buscar preinscripto:', error);
            showToast('Error al buscar preinscripto: ' + error.message, 'error');
            
            // Restaurar botón en caso de error
            const buscarBtn = cedulaInput.closest('.input-group').querySelector('button');
            if (buscarBtn) {
                buscarBtn.disabled = false;
                buscarBtn.innerHTML = '<i class="fas fa-search"></i> Buscar';
            }
        }
    }
    
    // Función para mostrar mensajes toast
    function showToast(message, type = 'info') {
        // Crear toast si no existe
        let toast = document.getElementById('toast-container');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'toast-container';
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 9999;
            `;
            document.body.appendChild(toast);
        }
        
        const toastElement = document.createElement('div');
        const bgColor = type === 'success' ? '#28a745' : type === 'warning' ? '#ffc107' : type === 'error' ? '#dc3545' : '#17a2b8';
        
        toastElement.style.cssText = `
            background: ${bgColor};
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            animation: slideIn 0.3s ease-out;
        `;
        toastElement.textContent = message;
        
        toast.appendChild(toastElement);
        
        // Remover después de 3 segundos
        setTimeout(() => {
            if (toastElement.parentNode) {
                toastElement.parentNode.removeChild(toastElement);
            }
        }, 3000);
    }
    
    // Actualizar estado del dispositivo en la interfaz
    function updateDeviceStatus(message, status) {
        const statusElement = document.getElementById('device-status');
        const textElement = document.getElementById('device-status-text');
        const submitBtn = document.getElementById('submit-btn');
        
        textElement.textContent = message;
        
        // Remover clases anteriores
        statusElement.classList.remove('device-connected', 'device-disconnected', 'device-connecting');
        
        // Actualizar estado del botón según conexión del dispositivo
        // Verificar si hay advertencia de seguridad activa
        const advertenciaUltimoActiva = document.getElementById('advertencia-ultimo-usuario') && 
                                       document.getElementById('advertencia-ultimo-usuario').style.display !== 'none';
        const advertenciaManualActiva = document.getElementById('advertencia-usuario-manual') && 
                                       document.getElementById('advertencia-usuario-manual').style.display !== 'none';
        const advertenciaIdAdelantadoActiva = document.getElementById('advertencia-id-adelantado') && 
                                             document.getElementById('advertencia-id-adelantado').style.display !== 'none';
        const advertenciaActiva = advertenciaUltimoActiva || advertenciaManualActiva || advertenciaIdAdelantadoActiva;
        
        if (status === 'connected' && !esModoPrueba && !advertenciaActiva) {
            submitBtn.disabled = false;
            submitBtn.className = 'btn btn-success btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
        } else if (status === 'connected' && esModoPrueba) {
            submitBtn.disabled = false;
            submitBtn.className = 'btn btn-warning btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE (MODO PRUEBA)';
        } else if (advertenciaActiva) {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-danger btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> GUARDADO DESHABILITADO';
        } else {
            submitBtn.disabled = true;
            submitBtn.className = 'btn btn-secondary btn-lg px-4 py-2';
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i> DISPOSITIVO NO CONECTADO';
        }
        
        // Agregar clase correspondiente
        switch (status) {
            case 'connected':
                statusElement.classList.add('device-connected');
                break;
            case 'disconnected':
                statusElement.classList.add('device-disconnected');
                break;
            case 'connecting':
                statusElement.classList.add('device-connecting');
                break;
        }
    }
    
    // Cargar información del dispositivo
    async function loadDeviceInfo() {
        try {
            console.log('Obteniendo información del dispositivo...');
            const info = await zktecoBridge.getDeviceInfo();
            console.log('Device info response:', info);
            
            if (info.error) {
                updateDeviceStatus('Error: ' + info.error, 'disconnected');
                return;
            }
            
              // Obtener información del último usuario para mostrar en el status
              try {
                  const users = await zktecoBridge.getUsers();
                  let ultimoUsuario = '';
                  
                  // Usar el user_count del dispositivo como referencia del último usuario real
                  const ultimoUIDReal = info.device_info ? info.device_info.user_count || 0 : 0;
                  console.log(`Último UID real según dispositivo: ${ultimoUIDReal}`);
                  
                  if (users.users && users.users.length > 0) {
                      // Buscar el usuario con el UID más alto en la lista obtenida
                      const ultimoEnLista = users.users.reduce((max, u) => parseInt(u.uid) > parseInt(max.uid) ? u : max);
                      console.log(`Último usuario en la lista: UID ${ultimoEnLista.uid}, Nombre: ${ultimoEnLista.name}`);
                      console.log(`Total usuarios en lista: ${users.users.length}, Total según dispositivo: ${ultimoUIDReal}`);
                      
                      // Verificar si este es realmente el último usuario según el contador del dispositivo
                      if (parseInt(ultimoEnLista.uid) === ultimoUIDReal) {
                          if (ultimoEnLista.name && !ultimoEnLista.name.startsWith("NN-")) {
                              ultimoUsuario = ` | Último: ${ultimoEnLista.uid}:${ultimoEnLista.name}`;
                              // Mostrar advertencia si el último usuario tiene nombre asignado
                              mostrarAdvertenciaUltimoUsuario(ultimoEnLista.uid, ultimoEnLista.name);
                          } else {
                              ultimoUsuario = ` | Último: ${ultimoEnLista.uid} (sin nombre)`;
                              // Ocultar advertencia si no hay problema
                              ocultarAdvertenciaUltimoUsuario();
                          }
                      } else {
                          // El último usuario en la lista no coincide con el contador del dispositivo
                          // Esto significa que no se obtuvieron todos los usuarios
                          console.log(`⚠️ El último usuario en la lista (${ultimoEnLista.uid}) no coincide con el contador del dispositivo (${ultimoUIDReal})`);
                          console.log(`⚠️ Esto indica que la lista de usuarios está incompleta`);
                          
                          // Si el contador del dispositivo es mayor que el último UID en la lista,
                          // significa que hay usuarios más recientes que no se obtuvieron
                          // Asumir que el último usuario real (según contador) no tiene nombre asignado
                          console.log(`Asumiendo que el último usuario real (UID ${ultimoUIDReal}) no tiene nombre asignado`);
                          ultimoUsuario = ` | Último: ${ultimoUIDReal} (sin nombre - no verificado)`;
                          ocultarAdvertenciaUltimoUsuario();
                      }
                  } else {
                      // Si no se pudieron obtener usuarios, asumir que el último no tiene nombre
                      ultimoUsuario = ` | Último: ${ultimoUIDReal} (no verificado)`;
                      ocultarAdvertenciaUltimoUsuario();
                  }
                  
                  const statusText = `Sistema listo - QUIRA conectado | Serial: ${info.device_info ? info.device_info.serial_number || 'No disponible' : 'No disponible'} | Usuarios: ${info.device_info ? info.device_info.user_count || 0 : 0}`;
                  updateDeviceStatus(statusText, 'connected');
              } catch (error) {
                  console.error('Error obteniendo último usuario:', error);
                  const statusText = `Sistema listo - QUIRA conectado | Serial: ${info.device_info ? info.device_info.serial_number || 'No disponible' : 'No disponible'} | Usuarios: ${info.device_info ? info.device_info.user_count || 0 : 0}`;
                  updateDeviceStatus(statusText, 'connected');
              }
            
        } catch (error) {
            console.error('Error cargando información del dispositivo:', error);
            updateDeviceStatus('Error al cargar información: ' + error.message, 'disconnected');
        }
    }
    
    // Calcular edad automáticamente
    function calcularEdad() {
        const fechaNacimiento = document.getElementById('fecha_nacimiento').value;
        if (fechaNacimiento) {
            const fechaNac = new Date(fechaNacimiento);
            const hoy = new Date();
            let edad = hoy.getFullYear() - fechaNac.getFullYear();
            const mes = hoy.getMonth() - fechaNac.getMonth();
            
            if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
                edad--;
            }
            
            document.getElementById('edad').value = edad;
            console.log(`Edad calculada: ${edad} años`);
        } else {
            document.getElementById('edad').value = '';
        }
    }
    
    // Convertir a mayúsculas
    function convertirAMayusculas(input) {
        input.value = input.value.toUpperCase();
    }
    
     // Manejar envío del formulario (replica exacta del software original)
     document.getElementById('postulante-form').addEventListener('submit', async (e) => {
         console.log('🚀 Formulario enviado - iniciando procesamiento');
         
         // TEMPORAL: Permitir envío normal para que funcione
         // e.preventDefault();
        
        const formData = new FormData(e.target);
        const submitBtn = document.getElementById('submit-btn');
        
        // Validaciones del lado cliente (igual que el software original)
        const nombre_completo = formData.get('nombre_completo').trim();
        const cedula = formData.get('cedula').trim();
        const dedoRegistrado = formData.get('dedo_registrado');
        const sexo = formData.get('sexo');
        const unidad = formData.get('unidad');
        const idK40 = formData.get('id_k40').trim();
        
        if (!nombre_completo || !cedula || !dedoRegistrado || sexo === 'Seleccionar' || !unidad) {
            alert('Todos los campos, incluido "Nombre Completo", "Dedo Registrado", "Sexo" y "Unidad", son obligatorios.');
            return;
        }
        
        // Validar longitud de campos
        if (cedula.length > 20) {
            alert('La cédula no puede tener más de 20 dígitos.');
            return;
        }
        if (nombre_completo.length > 200) {
            alert('El nombre completo no puede tener más de 200 caracteres.');
            return;
        }
        if (dedoRegistrado.length > 50) {
            alert('El dedo registrado no puede tener más de 50 caracteres.');
            return;
        }
        
        // Validar ID en K40 si se proporciona
        if (idK40 && (isNaN(idK40) || parseInt(idK40) < 1 || parseInt(idK40) > 9999)) {
            alert('El ID en K40 debe ser un número entre 1 y 9999.');
            return;
        }
        
        // VALIDACION CRITICA: Verificar que hay un dispositivo biométrico conectado
        if (!esModoPrueba && !dispositivoConectado) {
            alert('❌ ERROR CRÍTICO: No hay dispositivo biométrico conectado.\n\n' +
                  'No se pueden agregar postulantes sin un dispositivo ZKTeco conectado.\n' +
                  'Por favor:\n' +
                  '1. Verificar que el dispositivo esté encendido\n' +
                  '2. Verificar la conexión de red\n' +
                  '3. Cerrar y volver a abrir el programa ZKTecoBridge\n' +
                  '4. Recargar esta página');
            return;
        }
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            
            // PASO 1: ACTUALIZAR EN EL K40 PRIMERO (solo si no es modo prueba)
            let k40Actualizado = false;
            let usuarioUID = null;
            
            // Si el usuario proporcionó un ID específico, usarlo
            if (idK40) {
                usuarioUID = parseInt(idK40);
                console.log(`Usando ID proporcionado por el usuario: ${usuarioUID}`);
            }
            
            if (!esModoPrueba && dispositivoConectado && zktecoBridge) {
                try {
                    // Si no se proporcionó un ID específico, buscar uno disponible
                    if (!usuarioUID) {
                        console.log('Obteniendo información del dispositivo...');
                        // Primero obtener información del dispositivo
                        const deviceInfo = await zktecoBridge.getDeviceInfo();
                        
                        if (deviceInfo && deviceInfo.device_info && deviceInfo.device_info.user_count) {
                            const totalUsuarios = deviceInfo.device_info.user_count;
                            console.log(`Total de usuarios en el dispositivo: ${totalUsuarios}`);
                            
                            // Intentar obtener usuarios del dispositivo
                            const users = await zktecoBridge.getUsers();
                            console.log(`Usuarios obtenidos: ${users.users ? users.users.length : 0} de ${totalUsuarios}`);
                            
                            if (users.users && users.users.length > 0) {
                                // Buscar usuario disponible (sin nombre asignado)
                                const usuariosSinId = users.users.filter(u => !u.name || u.name.startsWith("NN-"));
                                let ultimoUsuario;
                                
                                if (usuariosSinId.length > 0) {
                                    ultimoUsuario = usuariosSinId[usuariosSinId.length - 1];
                                    console.log(`Usuario disponible encontrado: UID ${ultimoUsuario.uid}`);
                                } else {
                                    ultimoUsuario = users.users.reduce((max, u) => parseInt(u.uid) > parseInt(max.uid) ? u : max);
                                    console.log(`Usando último usuario: UID ${ultimoUsuario.uid}`);
                                }
                                
                                if (ultimoUsuario) {
                                    usuarioUID = ultimoUsuario.uid;
                                } else {
                                    // Si no se encontró usuario disponible, usar el total de usuarios
                                    usuarioUID = totalUsuarios;
                                    console.log(`Usando siguiente ID disponible: ${usuarioUID}`);
                                }
                            } else {
                                // Si no se pueden obtener usuarios, usar el total de usuarios
                                usuarioUID = totalUsuarios;
                                console.log(`No se pudieron obtener usuarios, usando siguiente ID: ${usuarioUID}`);
                            }
                        } else {
                            console.log('❌ No se pudo obtener información del dispositivo');
                            alert('No se pudo obtener información del dispositivo biométrico.');
                            return;
                        }
                    }
                    
                     // Verificar si el usuario ya tiene nombre asignado (solo si no se proporcionó ID específico)
                     if (!idK40) {
                         const users = await zktecoBridge.getUsers();
                         const usuarioExistente = users.users.find(u => parseInt(u.uid) === usuarioUID);
                         
                         if (usuarioExistente && usuarioExistente.name && !usuarioExistente.name.startsWith("NN-")) {
                             console.log(`❌ Usuario UID ${usuarioUID} ya tiene nombre: ${usuarioExistente.name}`);
                             
                             // Mostrar alerta visual con información del usuario existente
                             mostrarAlertaUsuarioExistente(usuarioUID, usuarioExistente.name);
                             return;
                        }
                    }
                    
                        // Actualizar usuario en el K40
                        console.log(`Actualizando usuario UID ${usuarioUID} en el dispositivo...`);
                        const zkResult = await zktecoBridge.addUser(
                            usuarioUID,
                            nombre_completo,
                            0, // Privilege por defecto
                            "",
                            "" // Group ID por defecto
                        );
                        
                        console.log('Resultado completo del addUser:', zkResult);
                        console.log('zkResult.success:', zkResult.success);
                        console.log('Tipo de zkResult.success:', typeof zkResult.success);
                        
                        if (zkResult.success === true || zkResult.success === undefined) {
                            k40Actualizado = true;
                            console.log(`✅ Usuario ${usuarioUID} actualizado en K40 sin perder la huella`);
                        } else {
                            console.log(`❌ Error actualizando usuario en K40:`, zkResult);
                            alert('No se pudo actualizar el usuario en el dispositivo K40. No se guardará en la base de datos.');
                            return;
                        }
                    
                } catch (error) {
                    console.log(`❌ Error actualizando K40: ${error.message}`);
                    alert(`No se pudo actualizar el K40: ${error.message}. No se guardará en la base de datos.`);
                    return;
                }
            } else if (esModoPrueba) {
                // Modo prueba - generar UID simulado
                usuarioUID = Math.floor(Math.random() * 9000) + 1000;
                k40Actualizado = true;
                console.log(`Modo prueba: UID simulado generado: ${usuarioUID}`);
            } else {
                console.log('❌ No hay conexión activa con el dispositivo K40');
                alert('No hay conexión activa con el dispositivo K40.');
                return;
            }
            
            // PASO 2: SOLO SI EL K40 SE ACTUALIZÓ CORRECTAMENTE, GUARDAR EN LA BASE DE DATOS
            if (!k40Actualizado) {
                console.log('❌ K40 no se actualizó correctamente');
                alert('No se pudo actualizar el K40. No se guardará en la base de datos.');
                return;
            }
            
            console.log('Simulando guardado en base de datos...');
            
            // Agregar UID al formulario
            formData.append('uid_k40', usuarioUID);
            
             // Debug: mostrar todos los datos que se van a enviar
             console.log('📤 Datos que se envían al servidor:');
             for (let [key, value] of formData.entries()) {
                 console.log(`  ${key}: ${value}`);
             }
             
             // Debug adicional: verificar que el UID se está enviando correctamente
             console.log('🔍 Verificación de datos críticos:');
             console.log(`  usuarioUID: ${usuarioUID}`);
             console.log(`  formData.get('uid_k40'): ${formData.get('uid_k40')}`);
             console.log(`  formData.get('id_k40'): ${formData.get('id_k40')}`);
            
            // Enviar datos al servidor PHP
            try {
                const response = await fetch('agregar_postulante.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.text();
                
                // Debug: mostrar la respuesta del servidor
                console.log('📥 Respuesta del servidor:', result);
                console.log('📊 Status de la respuesta:', response.status);
                
                // Verificar resultado
                if (result.includes('Postulante registrado correctamente')) {
                    console.log('✅ Postulante registrado exitosamente');
                    
                    // Extraer información del mensaje
                    const aparatoMatch = result.match(/aparato ([^,]+), con UID (\d+)/);
                    if (aparatoMatch) {
                        const aparatoNombre = aparatoMatch[1];
                        const uid = aparatoMatch[2];
                        
                        if (result.includes('[WARN]')) {
                            alert(`Postulante registrado correctamente en el aparato ${aparatoNombre}, con UID ${uid}.\n\n[WARN] Nota: Se registró a pesar del problema judicial detectado.`);
                        } else {
                            alert(`Postulante registrado correctamente en el aparato ${aparatoNombre}, con UID ${uid}.`);
                        }
                    } else {
                        alert('✅ Postulante registrado exitosamente');
                    }
                    
                    // Recargar la página
                    window.location.reload();
                } else {
                    // Mostrar error del servidor
                    console.log('❌ Error del servidor:', result);
                    alert('❌ Error: ' + result);
                }
            } catch (error) {
                console.error('❌ Error en el envío:', error);
                alert('❌ Error al enviar datos: ' + error.message);
            }
            
        } catch (error) {
            console.error('Error:', error);
            alert('❌ Error al agregar postulante: ' + error.message);
        } finally {
            // Solo restaurar el estado del botón si el formulario no fue exitosamente enviado
            // Si fue exitoso, la página se recargará o redirigirá, así que no importa
            // Si falló, verificar el estado del dispositivo antes de habilitar
            verificarYHabilitarBoton();
            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE';
        }
    });
    
    // Botón de prueba de conexión
    document.getElementById('test-connection-btn').addEventListener('click', async () => {
        if (!zktecoBridge) {
            alert('❌ Bridge ZKTeco no inicializado');
            return;
        }
        
        try {
            updateDeviceStatus('Probando conexión...', 'connecting');
            
            // Hacer ping al bridge
            const pingResult = await zktecoBridge.ping();
            console.log('Ping exitoso:', pingResult);
            
            // Probar conexión al dispositivo
            const deviceConnected = await zktecoBridge.connectToDevice();
            
            if (deviceConnected) {
                dispositivoConectado = true;
                updateDeviceStatus('[OK] Sistema listo - QUIRA conectado', 'connected');
                await loadDeviceInfo();
                await inicializarDispositivoReal();
            } else {
                dispositivoConectado = false;
                updateDeviceStatus('❌ Error: No se pudo conectar al dispositivo', 'disconnected');
            }
            
        } catch (error) {
            console.error('Error en prueba de conexión:', error);
            dispositivoConectado = false;
            updateDeviceStatus('❌ Error en prueba: ' + error.message, 'disconnected');
        }
    });
    
    // Event listeners para funcionalidades automáticas
    document.getElementById('fecha_nacimiento').addEventListener('change', calcularEdad);
    document.getElementById('fecha_nacimiento').addEventListener('input', calcularEdad);
    document.getElementById('nombre_completo').addEventListener('input', function(e) {
        convertirAMayusculas(e.target);
    });
    
    // Observar cambios en el campo de aparato biométrico para habilitar/deshabilitar botón
    const aparatoInput = document.getElementById('aparato_biometrico');
    if (aparatoInput) {
        // Observer para detectar cambios en el valor del campo
        const observer = new MutationObserver(function(mutations) {
            verificarYHabilitarBoton();
        });
        
        // Observar cambios en el atributo value
        aparatoInput.addEventListener('input', function() {
            verificarYHabilitarBoton();
        });
        
        // También verificar periódicamente (cada segundo) por si el cambio se hace programáticamente
        setInterval(function() {
            verificarYHabilitarBoton();
        }, 1000);
    }
    
    // Validación de cédula en tiempo real
    document.getElementById('cedula').addEventListener('input', function(e) {
        // Solo permitir números
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
    });
    
    // Event listener para el botón de buscar preinscripto
    document.addEventListener('DOMContentLoaded', function() {
        const btnBuscarPreinscripto = document.getElementById('btn-buscar-preinscripto');
        if (btnBuscarPreinscripto) {
            console.log('✅ Botón de buscar encontrado, agregando event listener');
            btnBuscarPreinscripto.addEventListener('click', function(e) {
                console.log('🖱️ Click en botón buscar detectado');
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                buscarPreinscripto();
                return false;
            });
        } else {
            console.error('❌ Botón btn-buscar-preinscripto no encontrado');
        }
    });
    
    // Verificación de ID en tiempo real
    document.getElementById('id_k40').addEventListener('input', function(e) {
        const uid = e.target.value;
        if (uid && !isNaN(uid)) {
            verificarUsuarioExistente(parseInt(uid));
        } else {
            // Limpiar información si no hay UID válido
            const infoDiv = document.getElementById('usuario-existente-info');
            infoDiv.style.display = 'none';
            infoDiv.innerHTML = '';
            ocultarAdvertenciaUsuarioManual();
            ocultarAdvertenciaUltimoUsuario();
            ocultarAdvertenciaIdAdelantado();
        }
    });
    
     // Calcular edad inicial si ya hay una fecha cargada
     setTimeout(() => {
         calcularEdad();
     }, 100);
     
     // Inicializar Select2 para el campo capturador
     $(document).ready(function() {
         $('#capturador_id').select2({
             theme: 'bootstrap-5',
             placeholder: 'Buscar capturador...',
             allowClear: true,
             width: '100%',
             language: {
                 noResults: function() {
                     return "No se encontraron resultados";
                 },
                 searching: function() {
                     return "Buscando...";
                 }
             }
         });
     });
     
     // Manejar cambio de capturador para memoria de sesión
     document.getElementById('capturador_id').addEventListener('change', function() {
         const capturadorId = this.value;
         if (capturadorId) {
             // Guardar en localStorage para persistir durante la sesión
             localStorage.setItem('ultimo_capturador', capturadorId);
             console.log('Capturador seleccionado:', capturadorId);
         }
     });
     
     // Cargar último capturador desde localStorage al cargar la página
     document.addEventListener('DOMContentLoaded', function() {
         const ultimoCapturador = localStorage.getItem('ultimo_capturador');
         if (ultimoCapturador) {
             const selectCapturador = document.getElementById('capturador_id');
             if (selectCapturador && !selectCapturador.value) {
                 selectCapturador.value = ultimoCapturador;
             }
         }
     });
     </script>

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
        });
    </script>
 </body>
 </html>
 <?php
 // Debug: verificar que el archivo se ejecutó completamente
 writeDebugLog('✅ Archivo agregar_postulante.php ejecutado completamente');
 ?>

