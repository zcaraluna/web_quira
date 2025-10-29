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

// Manejar memoria de sesión para el capturador
if (!isset($_SESSION['capturador_memoria'])) {
    $_SESSION['capturador_memoria'] = $_SESSION['user_id'];
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim($_POST['nombre']);
        $apellido = trim($_POST['apellido']);
        $cedula = trim($_POST['cedula']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'];
        $telefono = trim($_POST['telefono']);
        $unidad = trim($_POST['unidad']);
        $observaciones = trim($_POST['observaciones']);
        $capturador_id = $_POST['capturador_id'] ?? $_SESSION['capturador_memoria'];
        
        // Validaciones básicas
        if (empty($nombre) || empty($apellido) || empty($cedula) || empty($fecha_nacimiento) || empty($unidad)) {
            throw new Exception('Los campos nombre, apellido, cédula, fecha de nacimiento y unidad son obligatorios');
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
        
        // Insertar postulante SIN datos biométricos
        $stmt = $pdo->prepare("
            INSERT INTO postulantes (
                nombre, apellido, cedula, fecha_nacimiento, telefono, 
                unidad, observaciones, edad, registrado_por, capturador_id,
                dedo_registrado, aparato_id, uid_k40, aparato_nombre
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $nombre, $apellido, $cedula, $fecha_nacimiento, $telefono,
            $unidad, $observaciones, $edad, $_SESSION['user_id'], $capturador_id,
            'NO_REGISTRADO', // dedo_registrado
            null, // aparato_id
            null, // uid_k40
            'SIN_DISPOSITIVO' // aparato_nombre
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

// Obtener información del dispositivo para mostrar en la interfaz
$deviceInfo = null;
$usuarios_dispositivo = [];
$conexion_activa = false;

try {
    // Simular información de dispositivo (ya que no hay conexión)
    $deviceInfo = (object) [
        'serial' => 'SIN_CONEXION',
        'user_count' => 0,
        'status' => 'DESCONECTADO'
    ];
} catch (Exception $e) {
    error_log('Error obteniendo información del dispositivo: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Postulante (Sin Biométrico) - Sistema Quira</title>
    <link rel="shortcut icon" href="favicon.php">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="stylesheet" href="assets/css/main.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding-bottom: 80px; /* Safe zone para el footer */
        }
        
        .container {
            margin-bottom: 80px; /* Safe zone para el footer */
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
        }
        
        .form-control:focus {
            border-color: #2E5090;
            box-shadow: 0 0 0 0.2rem rgba(46, 80, 144, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 80, 144, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        .btn-warning {
            background: #ffc107;
            border: none;
            border-radius: 8px;
            padding: 12px 30px;
            font-weight: 600;
            color: #212529;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .device-status {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .status-disconnected {
            color: #dc3545;
        }
        
        .status-connected {
            color: #28a745;
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
        
        .no-biometric-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header text-center">
                        <h3 class="mb-0">
                            <i class="fas fa-user-plus mr-2"></i>
                            Agregar Postulante (Sin Biométrico)
                        </h3>
                        <p class="mb-0 mt-2 opacity-75">Registro manual para casos especiales</p>
                    </div>
                    <div class="card-body p-4">
                        <!-- Advertencia sobre falta de biométrico -->
                        <div class="no-biometric-warning">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-exclamation-triangle text-warning fa-2x mr-3"></i>
                                <div>
                                    <h6 class="mb-1 font-weight-bold">Modo Sin Biométrico</h6>
                                    <p class="mb-0 text-muted">Este postulante será registrado sin datos biométricos. No se generará ID en K40 ni se registrará huella dactilar.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado del dispositivo -->
                        <div class="device-status">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">Estado del Sistema</h6>
                                    <p class="mb-0 text-muted">Sistema listo - Modo manual activado</p>
                                </div>
                                <div class="text-right">
                                    <span class="badge badge-warning">SIN BIOMÉTRICO</span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($mensaje): ?>
                        <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?> mr-2"></i>
                            <?= htmlspecialchars($mensaje) ?>
                            <button type="button" class="close" data-dismiss="alert">
                                <span>&times;</span>
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="formPostulante">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="nombre">Nombre <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="nombre" name="nombre" 
                                               value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="apellido">Apellido <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="apellido" name="apellido" 
                                               value="<?= htmlspecialchars($_POST['apellido'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cedula">Cédula <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="cedula" name="cedula" 
                                               value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="fecha_nacimiento">Fecha de Nacimiento <span class="required">*</span></label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                               value="<?= htmlspecialchars($_POST['fecha_nacimiento'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="telefono">Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" 
                                               value="<?= htmlspecialchars($_POST['telefono'] ?? '') ?>" 
                                               placeholder="0981 123 456">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unidad">Unidad <span class="required">*</span></label>
                                        <input type="text" class="form-control" id="unidad" name="unidad" 
                                               value="<?= htmlspecialchars($_POST['unidad'] ?? '') ?>" required
                                               placeholder="Ej: Academia Nacional de Policía">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="observaciones">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                          placeholder="Observaciones adicionales..."><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="capturador_id">Capturador</label>
                                <select class="form-control" id="capturador_id" name="capturador_id">
                                    <?php foreach ($usuarios_sistema as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>" 
                                            <?= ($usuario['id'] == ($_POST['capturador_id'] ?? $_SESSION['capturador_memoria'])) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usuario['grado'] . ' ' . $usuario['nombre'] . ' ' . $usuario['apellido']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-primary btn-lg mr-3">
                                    <i class="fas fa-save mr-2"></i>Guardar Postulante
                                </button>
                                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                                    <i class="fas fa-arrow-left mr-2"></i>Volver al Dashboard
                                </a>
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
        // Validación del formulario
        document.getElementById('formPostulante').addEventListener('submit', function(e) {
            const nombre = document.getElementById('nombre').value.trim();
            const apellido = document.getElementById('apellido').value.trim();
            const cedula = document.getElementById('cedula').value.trim();
            const fechaNacimiento = document.getElementById('fecha_nacimiento').value;
            const unidad = document.getElementById('unidad').value.trim();
            
            if (!nombre || !apellido || !cedula || !fechaNacimiento || !unidad) {
                e.preventDefault();
                alert('Por favor complete todos los campos obligatorios marcados con *');
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
        
        // Auto-calcular edad cuando cambie la fecha de nacimiento
        document.getElementById('fecha_nacimiento').addEventListener('change', function() {
            const fechaNac = new Date(this.value);
            const hoy = new Date();
            const edad = hoy.getFullYear() - fechaNac.getFullYear();
            const mes = hoy.getMonth() - fechaNac.getMonth();
            
            if (mes < 0 || (mes === 0 && hoy.getDate() < fechaNac.getDate())) {
                edad--;
            }
            
            if (edad < 0) {
                alert('La fecha de nacimiento no puede ser futura');
                this.value = '';
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
