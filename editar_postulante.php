<?php
/**
 * Página para editar postulantes existentes
 * Sistema QUIRA - Versión Web
 */

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

session_start();
require_once 'config.php';
requireLogin();

// Los SUPERVISORES no pueden editar postulantes
if ($_SESSION['rol'] === 'SUPERVISOR') {
    header('Location: dashboard.php');
    exit;
}

$mensaje = '';
$tipo_mensaje = '';
$postulante_id = null;
$postulante_data = null;

// Obtener ID del postulante
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$postulante_id = (int)$_GET['id'];

// Función para obtener datos del postulante
function obtener_postulante_por_id($pdo, $id) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, nombre_completo, COALESCE(nombre_completo, nombre || ' ' || apellido) as nombre_completo_display, 
                   cedula, fecha_nacimiento, telefono, 
                   fecha_registro, usuario_registrador, edad, unidad, 
                   dedo_registrado, registrado_por, aparato_id, uid_k40, 
                   observaciones, usuario_ultima_edicion, fecha_ultima_edicion, 
                   aparato_nombre, sexo
            FROM postulantes 
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return null;
    }
}

// Cargar datos del postulante
$pdo = getDBConnection();
$postulante_data = obtener_postulante_por_id($pdo, $postulante_id);

if (!$postulante_data) {
    header('Location: dashboard.php');
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Guardar datos originales ANTES de cualquier modificación
        $datos_originales = $postulante_data;
        
        $nombre_completo = strtoupper(trim($_POST['nombre_completo']));
        $cedula = trim($_POST['cedula']);
        $telefono = trim($_POST['telefono']);
        $fecha_nacimiento = $_POST['fecha_nacimiento'] ?: null;
        $unidad = $_POST['unidad'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $sexo = $_POST['sexo'] ?? '';
        $dedo_registrado = $_POST['dedo_registrado'] ?? '';
        $aparato_id = $_POST['aparato_id'] ?? null;
        $aparato_nombre = $_POST['aparato_nombre'] ?? null;
        
        // Validaciones básicas
        if (empty($nombre_completo) || empty($cedula)) {
            throw new Exception('Los campos Nombre Completo y Cédula son obligatorios');
        }
        
        // Validar longitud de nombre_completo
        if (strlen($nombre_completo) > 200) {
            throw new Exception('El nombre completo no puede tener más de 200 caracteres');
        }
        
        // Validar formato de cédula (solo números)
        if (!preg_match('/^\d+$/', $cedula)) {
            throw new Exception('La cédula debe contener solo números');
        }
        
        // Verificar si la cédula ya existe en otro postulante
        $stmt = $pdo->prepare("SELECT id FROM postulantes WHERE cedula = ? AND id != ?");
        $stmt->execute([$cedula, $postulante_id]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe otro postulante con esta cédula');
        }
        
        // Calcular edad si se proporciona fecha de nacimiento
        $edad = null;
        if ($fecha_nacimiento) {
            $fecha_nac = new DateTime($fecha_nacimiento);
            $hoy = new DateTime();
            $edad = $hoy->diff($fecha_nac)->y;
        }
        
        // Obtener fecha y hora actual
        $fecha_ultima_edicion = date('Y-m-d H:i:s');
        $usuario_ultima_edicion = $_SESSION['user_id'];
        
        // Manejar observaciones de manera inteligente
        $observaciones_actuales = $datos_originales['observaciones'] ?? '';
        $nuevas_observaciones = $observaciones;
        
        $nombre_usuario = $_SESSION['grado'] . ' ' . $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];
        $timestamp = date('d/m/Y H:i');
        
        // Si hay observaciones existentes y nuevas observaciones
        if ($observaciones_actuales && $nuevas_observaciones) {
            // Verificar si las nuevas observaciones ya están incluidas
            if (strpos($observaciones_actuales, $nuevas_observaciones) === false) {
                // Agregar las nuevas observaciones con formato mejorado
                $nueva_obs_formateada = "[$timestamp] $nombre_usuario:\n$nuevas_observaciones";
                $observaciones_finales = "$observaciones_actuales\n\n$nueva_obs_formateada";
            } else {
                // Si ya están incluidas, mantener las existentes
                $observaciones_finales = $observaciones_actuales;
            }
        } elseif ($observaciones_actuales) {
            // Solo hay observaciones existentes
            $observaciones_finales = $observaciones_actuales;
        } elseif ($nuevas_observaciones) {
            // Solo hay nuevas observaciones (primera observación)
            $observaciones_finales = "[$timestamp] $nombre_usuario:\n$nuevas_observaciones";
        } else {
            // No hay observaciones
            $observaciones_finales = '';
        }
        
        // Obtener información del aparato si se proporcionó
        if ($aparato_id && $aparato_id !== '') {
            try {
                $stmt_aparato = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos WHERE id = ?");
                $stmt_aparato->execute([$aparato_id]);
                $aparato_data = $stmt_aparato->fetch();
                if ($aparato_data) {
                    $aparato_id = $aparato_data['id'];
                    $aparato_nombre = $aparato_data['nombre'];
                } else {
                    // Si no se encuentra, usar el nombre proporcionado
                    $aparato_id = null;
                    if (empty($aparato_nombre)) {
                        $aparato_nombre = $postulante_data['aparato_nombre'];
                    }
                }
            } catch (Exception $e) {
                error_log("Error obteniendo información del aparato: " . $e->getMessage());
                $aparato_id = $postulante_data['aparato_id'];
                $aparato_nombre = $postulante_data['aparato_nombre'];
            }
        } else {
            // Si no se proporcionó aparato_id, mantener los valores actuales
            $aparato_id = $postulante_data['aparato_id'];
            $aparato_nombre = $postulante_data['aparato_nombre'];
        }
        
        // Actualizar en la base de datos
        $stmt = $pdo->prepare("
            UPDATE postulantes SET 
                nombre_completo = ?, cedula = ?, telefono = ?, 
                fecha_nacimiento = ?, edad = ?, unidad = ?, 
                dedo_registrado = ?, observaciones = ?, sexo = ?,
                usuario_ultima_edicion = ?, fecha_ultima_edicion = ?,
                aparato_id = ?, aparato_nombre = ?
            WHERE id = ?
        ");
        
        $resultado = $stmt->execute([
            $nombre_completo, $cedula, $telefono, 
            $fecha_nacimiento, $edad, $unidad, 
            $dedo_registrado, $observaciones_finales, $sexo,
            $usuario_ultima_edicion, $fecha_ultima_edicion,
            $aparato_id, $aparato_nombre,
            $postulante_id
        ]);
        
        if ($resultado) {
            // Registrar en historial de ediciones si hay cambios
            try {
                // Verificar si existe la tabla de historial
                $stmt_check = $pdo->prepare("
                    SELECT EXISTS (
                        SELECT FROM information_schema.tables 
                        WHERE table_name = 'historial_ediciones_postulantes'
                    )
                ");
                $stmt_check->execute();
                $tabla_existe = $stmt_check->fetchColumn();
                
                if (!$tabla_existe) {
                    // Crear tabla de historial si no existe
                    $create_table = "
                        CREATE TABLE historial_ediciones_postulantes (
                            id SERIAL PRIMARY KEY,
                            postulante_id INTEGER NOT NULL,
                            usuario_editor VARCHAR(100) NOT NULL,
                            fecha_edicion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            cambios TEXT NOT NULL,
                            FOREIGN KEY (postulante_id) REFERENCES postulantes(id) ON DELETE CASCADE
                        )
                    ";
                    $pdo->exec($create_table);
                }
                
                // Comparar cambios usando los datos originales (antes de la actualización)
                $cambios = [];
                
                // Obtener nombre_completo de los datos originales (combinar si es necesario)
                $nombre_completo_original = $datos_originales['nombre_completo'] ?? 
                                          ($datos_originales['nombre'] ?? '') . ' ' . ($datos_originales['apellido'] ?? '');
                $nombre_completo_original = trim($nombre_completo_original);
                
                $campos_comparar = [
                    'nombre_completo' => 'Nombre Completo',
                    'cedula' => 'Cédula',
                    'telefono' => 'Teléfono',
                    'fecha_nacimiento' => 'Fecha de Nacimiento',
                    'edad' => 'Edad',
                    'unidad' => 'Unidad',
                    'dedo_registrado' => 'Dedo Registrado',
                    'sexo' => 'Sexo'
                ];
                
                foreach ($campos_comparar as $campo => $nombre_campo) {
                    // Manejo especial para nombre_completo
                    if ($campo === 'nombre_completo') {
                        $valor_original = $nombre_completo_original;
                        $valor_nuevo = $nombre_completo;
                    } else {
                        $valor_original = $datos_originales[$campo] ?? '';
                        $valor_nuevo = $$campo ?? '';
                    }
                    
                    // Convertir a string para comparación consistente
                    $valor_original_str = strval($valor_original);
                    $valor_nuevo_str = strval($valor_nuevo);
                    
                    if ($valor_original_str !== $valor_nuevo_str) {
                        $cambios[] = $nombre_campo . ": '" . $valor_original_str . "' → '" . $valor_nuevo_str . "'";
                    }
                }
                
                // Si hay cambios, registrar en historial
                if (!empty($cambios)) {
                    // Obtener nombre del usuario actual desde la sesión
                    $nombre_usuario = ($_SESSION['grado'] ?? 'Usuario') . ' ' . 
                                    ($_SESSION['nombre'] ?? 'Sin') . ' ' . 
                                    ($_SESSION['apellido'] ?? 'Nombre');
                    
                    if ($nombre_usuario) {
                        
                        $stmt_historial = $pdo->prepare("
                            INSERT INTO historial_ediciones_postulantes 
                            (postulante_id, usuario_editor, fecha_edicion, cambios) 
                            VALUES (?, ?, ?, ?)
                        ");
                        $cambios_texto = implode('; ', $cambios);
                        $stmt_historial->execute([
                            $postulante_id, 
                            $nombre_usuario, 
                            $fecha_ultima_edicion, 
                            $cambios_texto
                        ]);
                    }
                }
                
            } catch (Exception $e) {
                // No fallar la actualización si hay error en el historial
                error_log("Error al registrar historial: " . $e->getMessage());
            }
            
            $mensaje = "Postulante actualizado correctamente.";
            $tipo_mensaje = 'success';
            
            // Recargar datos del postulante
            $postulante_data = obtener_postulante_por_id($pdo, $postulante_id);
        } else {
            throw new Exception('Error al actualizar el postulante');
        }
        
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Obtener unidades disponibles
$unidades = $pdo->query("SELECT nombre FROM unidades WHERE activa = true ORDER BY nombre")->fetchAll(PDO::FETCH_COLUMN);

// Obtener aparatos biométricos disponibles
try {
    $aparatos_biometricos = $pdo->query("SELECT id, nombre FROM aparatos_biometricos ORDER BY nombre")->fetchAll();
    if (empty($aparatos_biometricos)) {
        $aparatos_biometricos = [];
    }
} catch (Exception $e) {
    error_log("Error obteniendo aparatos biométricos: " . $e->getMessage());
    $aparatos_biometricos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Editar Postulante - Sistema Quira</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.php">
    
    <style>
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
        }
        .btn-success:hover {
            background-color: #218838;
            border-color: #1e7e34;
        }
        .btn-outline-secondary {
            border-color: #6c757d;
            color: #6c757d;
        }
        .btn-outline-secondary:hover {
            background-color: #6c757d;
            border-color: #6c757d;
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
        
        /* Safe zone para evitar superposición con footer */
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
                    <h1><i class="fas fa-user-edit"></i> Editar Postulante</h1>
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
                        <h5 class="mb-0"><i class="fas fa-user-edit"></i> EDITAR POSTULANTE</h5>
                        <small class="text-muted">Sistema de Registro Biométrico</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="postulante-form">
                            <!-- Sección 1: Información Personal -->
                            <h6 class="text-primary font-weight-bold mb-3"><i class="fas fa-user"></i> INFORMACIÓN PERSONAL</h6>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="nombre_completo"><i class="fas fa-user"></i> Nombre Completo *</label>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                               value="<?= htmlspecialchars($postulante_data['nombre_completo'] ?? $postulante_data['nombre_completo_display'] ?? '') ?>" 
                                               style="text-transform: uppercase;" required>
                                        <small class="form-text text-muted">Ingrese el nombre completo (nombres y apellidos)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="cedula"><i class="fas fa-id-card"></i> Cédula *</label>
                                        <input type="text" class="form-control" id="cedula" name="cedula" 
                                               value="<?= htmlspecialchars($postulante_data['cedula']) ?>" 
                                               pattern="[0-9]+" title="Solo números" required>
                                        <small class="form-text text-muted">Solo números, sin puntos ni guiones</small>
                                    </div>
                                </div>
                                <div class="col-md-6" style="display: none;">
                                    <div class="form-group">
                                        <label for="telefono"><i class="fas fa-phone"></i> Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono" 
                                               value="<?= htmlspecialchars($postulante_data['telefono']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="fecha_nacimiento"><i class="fas fa-calendar"></i> Fecha Nacimiento</label>
                                        <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" 
                                               value="<?= $postulante_data['fecha_nacimiento'] ? date('Y-m-d', strtotime($postulante_data['fecha_nacimiento'])) : '' ?>">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="edad"><i class="fas fa-birthday-cake"></i> Edad</label>
                                        <input type="text" class="form-control" id="edad" name="edad" 
                                               value="<?= $postulante_data['edad'] ?>" readonly>
                                        <small class="form-text text-muted">Se calcula automáticamente</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label for="sexo"><i class="fas fa-venus-mars"></i> Sexo</label>
                                        <select class="form-control" id="sexo" name="sexo">
                                            <option value="">Seleccionar</option>
                                            <option value="Hombre" <?= $postulante_data['sexo'] === 'Hombre' ? 'selected' : '' ?>>Hombre</option>
                                            <option value="Mujer" <?= $postulante_data['sexo'] === 'Mujer' ? 'selected' : '' ?>>Mujer</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sección 2: Información del Registro -->
                            <h6 class="text-primary font-weight-bold mb-3 mt-4"><i class="fas fa-clipboard-list"></i> INFORMACIÓN DEL REGISTRO</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="unidad"><i class="fas fa-building"></i> Unidad</label>
                                        <select class="form-control" id="unidad" name="unidad">
                                            <option value="">Seleccionar unidad</option>
                                            <?php foreach ($unidades as $unidad): ?>
                                            <option value="<?= htmlspecialchars($unidad) ?>" 
                                                    <?= $postulante_data['unidad'] === $unidad ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($unidad) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dedo_registrado"><i class="fas fa-hand-paper"></i> Dedo Registrado</label>
                                        <select class="form-control" id="dedo_registrado" name="dedo_registrado">
                                            <option value="">Seleccionar dedo</option>
                                            <option value="PD" <?= $postulante_data['dedo_registrado'] === 'PD' ? 'selected' : '' ?>>PD (Pulgar Derecho)</option>
                                            <option value="ID" <?= $postulante_data['dedo_registrado'] === 'ID' ? 'selected' : '' ?>>ID (Índice Derecho)</option>
                                            <option value="MD" <?= $postulante_data['dedo_registrado'] === 'MD' ? 'selected' : '' ?>>MD (Medio Derecho)</option>
                                            <option value="AD" <?= $postulante_data['dedo_registrado'] === 'AD' ? 'selected' : '' ?>>AD (Anular Derecho)</option>
                                            <option value="MeD" <?= $postulante_data['dedo_registrado'] === 'MeD' ? 'selected' : '' ?>>MeD (Meñique Derecho)</option>
                                            <option value="PI" <?= $postulante_data['dedo_registrado'] === 'PI' ? 'selected' : '' ?>>PI (Pulgar Izquierdo)</option>
                                            <option value="II" <?= $postulante_data['dedo_registrado'] === 'II' ? 'selected' : '' ?>>II (Índice Izquierdo)</option>
                                            <option value="MI" <?= $postulante_data['dedo_registrado'] === 'MI' ? 'selected' : '' ?>>MI (Medio Izquierdo)</option>
                                            <option value="AI" <?= $postulante_data['dedo_registrado'] === 'AI' ? 'selected' : '' ?>>AI (Anular Izquierdo)</option>
                                            <option value="MeI" <?= $postulante_data['dedo_registrado'] === 'MeI' ? 'selected' : '' ?>>MeI (Meñique Izquierdo)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="aparato_id"><i class="fas fa-fingerprint"></i> Aparato Biométrico</label>
                                        <select class="form-control" id="aparato_id" name="aparato_id">
                                            <option value="">Seleccionar dispositivo</option>
                                            <?php 
                                            // Mostrar todos los dispositivos disponibles
                                            if (!empty($aparatos_biometricos)): 
                                                foreach ($aparatos_biometricos as $aparato): 
                                            ?>
                                            <option value="<?= $aparato['id'] ?>" 
                                                    <?= ($postulante_data['aparato_id'] && $postulante_data['aparato_id'] == $aparato['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($aparato['nombre']) ?>
                                            </option>
                                            <?php 
                                                endforeach; 
                                            endif;
                                            
                                            // Si el aparato actual fue eliminado o no tiene ID, mostrarlo como opción especial
                                            if (!empty($postulante_data['aparato_nombre'])) {
                                                $aparato_id_existe = false;
                                                $aparato_nombre_existe = false;
                                                
                                                // Verificar si el ID existe en la lista
                                                if (!empty($postulante_data['aparato_id'])) {
                                                    foreach ($aparatos_biometricos as $aparato) {
                                                        if ($aparato['id'] == $postulante_data['aparato_id']) {
                                                            $aparato_id_existe = true;
                                                            break;
                                                        }
                                                    }
                                                }
                                                
                                                // Verificar si el nombre existe en la lista
                                                foreach ($aparatos_biometricos as $aparato) {
                                                    if ($aparato['nombre'] === $postulante_data['aparato_nombre']) {
                                                        $aparato_nombre_existe = true;
                                                        break;
                                                    }
                                                }
                                                
                                                // Solo mostrar "(Eliminado)" si el aparato no existe en la lista actual
                                                if (!$aparato_id_existe && !$aparato_nombre_existe):
                                            ?>
                                            <option value="" <?= !$postulante_data['aparato_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($postulante_data['aparato_nombre']) ?> (Eliminado)
                                            </option>
                                            <?php 
                                                endif;
                                            } 
                                            ?>
                                        </select>
                                        <input type="hidden" id="aparato_nombre" name="aparato_nombre" 
                                               value="<?= htmlspecialchars($postulante_data['aparato_nombre']) ?>">
                                        <small class="form-text text-muted">Puede cambiar el dispositivo biométrico asignado al postulante</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="uid_k40"><i class="fas fa-fingerprint"></i> UID en K40</label>
                                        <input type="text" class="form-control" id="uid_k40" name="uid_k40" 
                                               value="<?= htmlspecialchars($postulante_data['uid_k40']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="form-group">
                                        <label for="observaciones"><i class="fas fa-sticky-note"></i> Nuevas Observaciones</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" 
                                                  placeholder="Escriba aquí las nuevas observaciones... (Se agregarán automáticamente a las observaciones existentes)"></textarea>
                                        <?php if ($postulante_data['observaciones']): ?>
                                        <small class="form-text text-muted">
                                            <a href="#" onclick="mostrarObservacionesExistentes()" class="text-primary">
                                                <i class="fas fa-clipboard"></i> Ver observaciones existentes
                                            </a>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Información de registro -->
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fas fa-calendar"></i> Fecha de Registro</label>
                                        <input type="text" class="form-control" 
                                               value="<?= date('d/m/Y H:i:s', strtotime($postulante_data['fecha_registro'])) ?>" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label><i class="fas fa-user"></i> Registrado por</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($postulante_data['registrado_por']) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group mt-4">
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="submit" class="btn btn-success btn-lg px-4 py-2">
                                        <i class="fas fa-save mr-2"></i> GUARDAR CAMBIOS
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
    
    <!-- Modal para mostrar observaciones existentes -->
    <div class="modal fade" id="modalObservaciones" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-xl" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-clipboard"></i> Observaciones y Historial de Modificaciones</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Sección de Observaciones -->
                    <div class="mb-4">
                        <h6 class="text-primary font-weight-bold mb-3">
                            <i class="fas fa-sticky-note"></i> OBSERVACIONES
                        </h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="observaciones-content" style="max-height: 300px; overflow-y: auto; background-color: #f8f9fa; padding: 15px; border-radius: 5px; border: 1px solid #dee2e6;">
                                    <?php if ($postulante_data['observaciones']): ?>
                                        <?php 
                                        // Procesar observaciones para formato mejorado
                                        $observaciones = $postulante_data['observaciones'];
                                        $observaciones_formateadas = preg_replace('/\[([^\]]+)\]/', '<span class="badge badge-info">[$1]</span>', htmlspecialchars($observaciones));
                                        $observaciones_formateadas = nl2br($observaciones_formateadas);
                                        echo $observaciones_formateadas;
                                        ?>
                                    <?php else: ?>
                                        <em class="text-muted">Sin observaciones registradas</em>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Historial de Modificaciones -->
                    <div class="mb-4">
                        <h6 class="text-primary font-weight-bold mb-3">
                            <i class="fas fa-history"></i> HISTORIAL DE MODIFICACIONES
                        </h6>
                        <div class="card">
                            <div class="card-body">
                                <div class="historial-content" style="max-height: 300px; overflow-y: auto;">
                                    <?php
                                    // Obtener historial completo de ediciones
                                    try {
                                        // Verificar si existe la tabla de historial
                                        $stmt_check = $pdo->prepare("
                                            SELECT EXISTS (
                                                SELECT FROM information_schema.tables 
                                                WHERE table_name = 'historial_ediciones_postulantes'
                                            )
                                        ");
                                        $stmt_check->execute();
                                        $tabla_existe = $stmt_check->fetchColumn();
                                        
                                        $historial_encontrado = false;
                                        
                                        if ($tabla_existe) {
                                            // Obtener historial completo de ediciones
                                            $stmt_historial = $pdo->prepare("
                                                SELECT usuario_editor, fecha_edicion, cambios
                                                FROM historial_ediciones_postulantes 
                                                WHERE postulante_id = ?
                                                ORDER BY fecha_edicion DESC
                                            ");
                                            $stmt_historial->execute([$postulante_id]);
                                            $historial_ediciones = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            if ($historial_ediciones) {
                                                $historial_encontrado = true;
                                                
                                                // Mostrar cada edición del historial
                                                foreach ($historial_ediciones as $index => $edicion) {
                                                    $fecha_edicion = date('d/m/Y H:i', strtotime($edicion['fecha_edicion']));
                                                    
                                                    echo '<div class="historial-item mb-3 p-3" style="background-color: #e8f4fd; border-left: 4px solid #2E5090; border-radius: 5px;">';
                                                    echo '<div class="d-flex justify-content-between align-items-start">';
                                                    echo '<div>';
                                                    echo '<h6 class="mb-1 text-primary"><i class="fas fa-user-edit"></i> ' . htmlspecialchars($edicion['usuario_editor']) . '</h6>';
                                                    echo '<small class="text-muted"><i class="fas fa-clock"></i> ' . $fecha_edicion . '</small>';
                                                    
                                                    // Mostrar cambios realizados
                                                    if ($edicion['cambios']) {
                                                        echo '<div class="mt-2">';
                                                        echo '<small class="text-muted"><strong>Cambios realizados:</strong></small><br>';
                                                        $cambios = explode('; ', $edicion['cambios']);
                                                        foreach ($cambios as $cambio) {
                                                            if (trim($cambio)) {
                                                                echo '<small class="text-dark">• ' . htmlspecialchars(trim($cambio)) . '</small><br>';
                                                            }
                                                        }
                                                        echo '</div>';
                                                    }
                                                    
                                                    echo '</div>';
                                                    echo '<span class="badge badge-primary">Edición #' . (count($historial_ediciones) - $index) . '</span>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                        
                                        // Si no hay historial de ediciones, mostrar información básica
                                        if (!$historial_encontrado) {
                                            // Mostrar información del registro original
                                            if ($postulante_data['registrado_por']) {
                                                $fecha_registro = date('d/m/Y H:i', strtotime($postulante_data['fecha_registro']));
                                                
                                                echo '<div class="historial-item mb-3 p-3" style="background-color: #f0f8e8; border-left: 4px solid #28a745; border-radius: 5px;">';
                                                echo '<div class="d-flex justify-content-between align-items-start">';
                                                echo '<div>';
                                                echo '<h6 class="mb-1 text-success"><i class="fas fa-user-plus"></i> ' . htmlspecialchars($postulante_data['registrado_por']) . '</h6>';
                                                echo '<small class="text-muted"><i class="fas fa-clock"></i> ' . $fecha_registro . '</small>';
                                                echo '</div>';
                                                echo '<span class="badge badge-success">Registro inicial</span>';
                                                echo '</div>';
                                                echo '</div>';
                                            }
                                            
                                            // Mostrar última edición si existe
                                            if ($postulante_data['usuario_ultima_edicion']) {
                                                // Obtener nombre del usuario que editó
                                                $stmt_user = $pdo->prepare("
                                                    SELECT grado, nombre, apellido 
                                                    FROM usuarios 
                                                    WHERE id = ?
                                                ");
                                                $stmt_user->execute([$postulante_data['usuario_ultima_edicion']]);
                                                $usuario_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
                                                
                                                if ($usuario_data) {
                                                    $nombre_usuario = $usuario_data['grado'] . ' ' . $usuario_data['nombre'] . ' ' . $usuario_data['apellido'];
                                                    $fecha_edicion = date('d/m/Y H:i', strtotime($postulante_data['fecha_ultima_edicion']));
                                                    
                                                    echo '<div class="historial-item mb-3 p-3" style="background-color: #e8f4fd; border-left: 4px solid #2E5090; border-radius: 5px;">';
                                                    echo '<div class="d-flex justify-content-between align-items-start">';
                                                    echo '<div>';
                                                    echo '<h6 class="mb-1 text-primary"><i class="fas fa-user-edit"></i> ' . htmlspecialchars($nombre_usuario) . '</h6>';
                                                    echo '<small class="text-muted"><i class="fas fa-clock"></i> ' . $fecha_edicion . '</small>';
                                                    echo '</div>';
                                                    echo '<span class="badge badge-primary">Última edición</span>';
                                                    echo '</div>';
                                                    echo '</div>';
                                                }
                                            }
                                        }
                                        
                                    } catch (Exception $e) {
                                        echo '<div class="alert alert-warning">No se pudo cargar el historial de modificaciones: ' . htmlspecialchars($e->getMessage()) . '</div>';
                                    }
                                    ?>
                                    
                                    <?php if (!$historial_encontrado && !$postulante_data['usuario_ultima_edicion']): ?>
                                        <div class="text-center text-muted py-3">
                                            <i class="fas fa-info-circle fa-2x mb-2"></i><br>
                                            No hay modificaciones registradas
                                        </div>
                                    <?php endif; ?>
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
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <script>
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
            } else {
                document.getElementById('edad').value = '';
            }
        }
        
        // Convertir a mayúsculas
        function convertirAMayusculas(input) {
            input.value = input.value.toUpperCase();
        }
        
        // Mostrar observaciones existentes
        function mostrarObservacionesExistentes() {
            $('#modalObservaciones').modal('show');
        }
        
        // Event listeners
        document.getElementById('fecha_nacimiento').addEventListener('change', calcularEdad);
        document.getElementById('fecha_nacimiento').addEventListener('input', calcularEdad);
        document.getElementById('nombre_completo').addEventListener('input', function(e) {
            convertirAMayusculas(e.target);
        });
        
        // Validación de cédula en tiempo real
        document.getElementById('cedula').addEventListener('input', function(e) {
            // Solo permitir números
            e.target.value = e.target.value.replace(/[^0-9]/g, '');
        });
        
        // Calcular edad inicial si ya hay una fecha cargada
        setTimeout(() => {
            calcularEdad();
        }, 100);
        
        // Actualizar nombre del aparato cuando se selecciona uno diferente
        document.getElementById('aparato_id').addEventListener('change', function() {
            const select = this;
            const nombreInput = document.getElementById('aparato_nombre');
            const selectedOption = select.options[select.selectedIndex];
            
            if (selectedOption.value) {
                nombreInput.value = selectedOption.text;
            } else {
                // Si no hay selección, mantener el valor actual
                nombreInput.value = '<?= htmlspecialchars($postulante_data['aparato_nombre'] ?? '') ?>';
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
