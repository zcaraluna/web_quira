<?php
/**
 * Página para agregar postulantes SIN integración biométrica
 * Sistema QUIRA - Versión Web (Para casos donde no se puede registrar huella)
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

// Todos los usuarios pueden agregar postulantes
$mensaje = '';
$tipo_mensaje = '';
$postulante_id = null;

// Obtener conexión a la base de datos
$pdo = getDBConnection();

// Obtener unidades disponibles (igual que la página original)
$unidades = [];
try {
    $unidades = $pdo->query("SELECT nombre FROM unidades WHERE activa = true ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    error_log('Error obteniendo unidades: ' . $e->getMessage());
}


// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre_completo = strtoupper(trim($_POST['nombre_completo']));
        $cedula = trim($_POST['cedula']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = trim($_POST['telefono']);
        $unidad = trim($_POST['unidad']);
        $sexo = $_POST['sexo'];
        $observaciones = trim($_POST['observaciones']);
        
        // Validaciones básicas
        if (empty($nombre_completo) || empty($cedula) || empty($fecha_nacimiento) || empty($unidad) || empty($sexo)) {
            throw new Exception('Los campos nombre completo, cédula, fecha de nacimiento, unidad y sexo son obligatorios');
        }
        
        // Validar longitud de nombre_completo
        if (strlen($nombre_completo) > 200) {
            throw new Exception('El nombre completo no puede tener más de 200 caracteres');
        }
        
        // Validar formato de cédula (solo números)
        if (!preg_match('/^\d+$/', $cedula)) {
            throw new Exception('La cédula debe contener solo números');
        }
        
        // Verificar si ya existe un postulante con esta cédula
        $stmt = $pdo->prepare("SELECT id FROM postulantes WHERE cedula = ?");
        $stmt->execute([$cedula]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe un postulante con esta cédula');
        }
        
        // Calcular edad
        $fecha_nac = new DateTime($fecha_nacimiento);
        $hoy = new DateTime();
        $edad = $hoy->diff($fecha_nac)->y;
        
        // Obtener fecha y hora actual con zona horaria de Paraguay
        $fecha_registro = date('Y-m-d H:i:s');
        
        // Crear registrado_por con nombre completo (igual que la página original)
        $registrado_por = $_SESSION['grado'] . ' ' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
        
        // Insertar postulante SIN datos biométricos
        $stmt = $pdo->prepare("
            INSERT INTO postulantes (
                nombre_completo, cedula, fecha_nacimiento, telefono, 
                unidad, observaciones, edad, sexo, registrado_por,
                dedo_registrado, aparato_id, uid_k40, aparato_nombre, fecha_registro
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $nombre_completo, $cedula, $fecha_nacimiento, $telefono,
            $unidad, $observaciones, $edad, $sexo, $registrado_por,
            'NO_REGISTRADO', // dedo_registrado
            null, // aparato_id
            null, // uid_k40
            'SIN_DISPOSITIVO', // aparato_nombre
            $fecha_registro // fecha_registro
        ]);
        
        $postulante_id = $pdo->lastInsertId();
        
        $mensaje = 'Postulante agregado exitosamente (SIN registro biométrico)';
        $tipo_mensaje = 'success';
        
        // Limpiar formulario después del éxito
        $_POST = [];
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Agregar Postulante (Sin Biométrico) - Sistema Quira</title>
    <link rel="shortcut icon" href="favicon.php">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.php">
    <!-- Google Fonts - Lato -->
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/css/main.min.css">
    
    <style>
        /* Fuente Lato para coincidir con la página original */
        body, html {
            font-family: 'Lato', sans-serif;
        }
        
        .h1, .h2, .h3, .h4, .h5, .h6, h1, h2, h3, h4, h5, h6 {
            font-family: 'Lato', sans-serif;
        }
        
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
                    <h1><i class="fas fa-user-plus"></i> Agregar Postulante (Sin Biométrico)</h1>
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
                <div class="alert device-status device-disconnected" id="device-status">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <span id="device-status-text">Sistema listo - Modo manual activado (SIN BIOMÉTRICO)</span>
                    <div class="mt-2">
                        <small id="device-details">No se requiere conexión con dispositivo biométrico</small>
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
                        <h5 class="mb-0"><i class="fas fa-user-edit"></i> AÑADIR NUEVO POSTULANTE (SIN BIOMÉTRICO)</h5>
                        <small class="text-muted">Sistema de Registro Manual</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="postulante-form">
                            <!-- Sección 1: Información Personal -->
                            <h6 class="text-primary font-weight-bold mb-3"><i class="fas fa-user"></i> INFORMACIÓN PERSONAL</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="id_k40_disabled"><i class="fas fa-fingerprint"></i> ID en K40</label>
                                        <input type="text" class="form-control" id="id_k40_disabled" 
                                               value="NO DISPONIBLE" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                        <small class="form-text text-muted">No disponible en modo manual</small>
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
                                        <label for="nombre_completo"><i class="fas fa-user"></i> Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                               value="<?= htmlspecialchars($_POST['nombre_completo'] ?? '') ?>" 
                                               style="text-transform: uppercase;" required>
                                        <small class="form-text text-muted">Ingrese el nombre completo (nombres y apellidos)</small>
                                    </div>
                                </div>
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
                                               value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>" required>
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
                                        <label for="dedo_registrado_disabled"><i class="fas fa-hand-paper"></i> Dedo Registrado</label>
                                        <input type="text" class="form-control" id="dedo_registrado_disabled" 
                                               value="NO REGISTRADO" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                        <small class="form-text text-muted">No disponible en modo manual</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aparato_biometrico_disabled"><i class="fas fa-fingerprint"></i> Aparato Biométrico</label>
                                        <input type="text" class="form-control" id="aparato_biometrico_disabled" 
                                               value="SIN DISPOSITIVO" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                        <small class="form-text text-muted">No disponible en modo manual</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="capturador_disabled"><i class="fas fa-user-check"></i> Capturador huella</label>
                                        <input type="text" class="form-control" id="capturador_disabled" 
                                               value="NO APLICA" readonly style="background-color: #f8f9fa; color: #6c757d;">
                                        <small class="form-text text-muted">No disponible en modo manual</small>
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
                                    <button type="submit" class="btn btn-success btn-lg px-4 py-2" id="submit-btn">
                                        <i class="fas fa-save mr-2"></i> GUARDAR POSTULANTE
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary btn-lg px-4 py-2">
                                        <i class="fas fa-times mr-2"></i> CANCELAR
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <script>
        // Detectar dispositivos móviles
        function isMobile() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
        
        // Mostrar advertencia en móviles
        if (isMobile()) {
            document.getElementById('mobile-warning').style.display = 'flex';
        }
        
        // Función para calcular edad
        function calcularEdad() {
            const fechaNac = document.getElementById('fecha_nacimiento').value;
            if (!fechaNac) {
                document.getElementById('edad').value = '';
                return;
            }
            
            const fechaNacDate = new Date(fechaNac);
            const hoy = new Date();
            let edad = hoy.getFullYear() - fechaNacDate.getFullYear();
            const mes = hoy.getMonth() - fechaNacDate.getMonth();
            
            if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNacDate.getDate())) {
                edad--;
            }
            
            if (edad < 0) {
                alert('La fecha de nacimiento no puede ser futura');
                document.getElementById('fecha_nacimiento').value = '';
                document.getElementById('edad').value = '';
            } else {
                document.getElementById('edad').value = edad;
            }
        }
        
        // Auto-calcular edad cuando cambie la fecha de nacimiento
        document.getElementById('fecha_nacimiento').addEventListener('change', calcularEdad);
        
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
        
        // Función para buscar preinscripto por CI
        async function buscarPreinscripto() {
            const cedulaInput = document.getElementById('cedula');
            const ci = cedulaInput.value.trim();
            
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
                const buscarBtn = cedulaInput.closest('.input-group').querySelector('button');
                const originalText = buscarBtn.innerHTML;
                buscarBtn.disabled = true;
                buscarBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...';
                
                const formData = new FormData();
                formData.append('ci', ci);
                
                const response = await fetch('buscar_preinscripto_ajax.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin' // Incluir cookies de sesión
                });
                
                // Verificar si la respuesta es JSON válida
                const contentType = response.headers.get("content-type");
                if (!contentType || !contentType.includes("application/json")) {
                    throw new Error('La respuesta del servidor no es JSON válida');
                }
                
                const data = await response.json();
                
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
        
        // Event listener para el botón de buscar preinscripto
        const btnBuscarPreinscripto = document.getElementById('btn-buscar-preinscripto');
        if (btnBuscarPreinscripto) {
            btnBuscarPreinscripto.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                buscarPreinscripto();
            });
        }
        
        // Validación del formulario
        document.getElementById('postulante-form').addEventListener('submit', function(e) {
            const nombre_completo = document.getElementById('nombre_completo').value.trim();
            const cedula = document.getElementById('cedula').value.trim();
            const fechaNacimiento = document.getElementById('fecha_nacimiento').value;
            const unidad = document.getElementById('unidad').value;
            const sexo = document.getElementById('sexo').value;
            
            if (!nombre_completo || !cedula || !fechaNacimiento || !unidad || !sexo) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios marcados con *');
                return false;
            }
            
            // Validar longitud de nombre_completo
            if (nombre_completo.length > 200) {
                e.preventDefault();
                alert('El nombre completo no puede tener más de 200 caracteres');
                return false;
            }
            
            // Validar formato de cédula
            if (!/^\d+$/.test(cedula)) {
                e.preventDefault();
                alert('La cédula debe contener solo números');
                return false;
            }
            
            // Validar fecha de nacimiento
            const fechaNac = new Date(fechaNacimiento);
            const hoy = new Date();
            if (fechaNac >= hoy) {
                e.preventDefault();
                alert('La fecha de nacimiento debe ser anterior a hoy');
                return false;
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