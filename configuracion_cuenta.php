<?php
require_once 'config.php';

// Verificar autenticación
requireLogin();

// Obtener conexión a la base de datos
$pdo = getDBConnection();

$mensaje = '';
$tipo_mensaje = '';

// Procesar cambio de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    // Validaciones
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $mensaje = 'Todos los campos son obligatorios.';
        $tipo_mensaje = 'error';
    } elseif ($password_nueva !== $password_confirmar) {
        $mensaje = 'Las contraseñas nuevas no coinciden.';
        $tipo_mensaje = 'error';
    } elseif (strlen($password_nueva) < 6) {
        $mensaje = 'La nueva contraseña debe tener al menos 6 caracteres.';
        $tipo_mensaje = 'error';
    } else {
        try {
            // Verificar contraseña actual
            $stmt = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $usuario = $stmt->fetch();
            
            if (!$usuario || !password_verify($password_actual, $usuario['contrasena'])) {
                $mensaje = 'La contraseña actual es incorrecta.';
                $tipo_mensaje = 'error';
            } else {
                // Actualizar contraseña
                $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = ? WHERE id = ?");
                $stmt->execute([$password_hash, $_SESSION['user_id']]);
                
                $mensaje = 'Contraseña actualizada correctamente.';
                $tipo_mensaje = 'success';
                
                // Limpiar campos del formulario
                $_POST = [];
            }
        } catch (Exception $e) {
            error_log("Error al cambiar contraseña: " . $e->getMessage());
            $mensaje = 'Error al actualizar la contraseña. Intente nuevamente.';
            $tipo_mensaje = 'error';
        }
    }
}

// Obtener información del usuario actual
try {
    $stmt = $pdo->prepare("SELECT id, usuario, nombre, apellido, grado, cedula, telefono, rol, fecha_creacion FROM usuarios WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $usuario_actual = $stmt->fetch();
    
    // Si no se encuentra el usuario, redirigir al login
    if (!$usuario_actual) {
        header('Location: login.php');
        exit();
    }
} catch (Exception $e) {
    error_log("Error al obtener datos del usuario: " . $e->getMessage());
    $usuario_actual = [];
}

// Configuración de paginación
$registros_por_pagina = 5;
$pagina_actual = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$offset = ($pagina_actual - 1) * $registros_por_pagina;

// Obtener total de registros
try {
    $stmt_total = $pdo->prepare("SELECT COUNT(*) as total FROM postulantes WHERE usuario_registrador = ?");
    $stmt_total->execute([$_SESSION['user_id']]);
    $total_registros = $stmt_total->fetch()['total'];
    $total_paginas = ceil($total_registros / $registros_por_pagina);
} catch (Exception $e) {
    error_log("Error al obtener total de registros: " . $e->getMessage());
    $total_registros = 0;
    $total_paginas = 0;
}

// Obtener total de capturas de dedos
try {
    $stmt_total_capturas = $pdo->prepare("SELECT COUNT(*) as total FROM postulantes WHERE capturador_id = ?");
    $stmt_total_capturas->execute([$_SESSION['user_id']]);
    $total_capturas = $stmt_total_capturas->fetch()['total'];
    $total_paginas_capturas = ceil($total_capturas / $registros_por_pagina);
} catch (Exception $e) {
    error_log("Error al obtener total de capturas: " . $e->getMessage());
    $total_capturas = 0;
    $total_paginas_capturas = 0;
}

// Obtener registro de actividad - registros de postulantes realizados con paginación
try {
    $stmt = $pdo->prepare("
        SELECT 
            'postulante_registrado' as tipo_accion,
            CONCAT('Registró postulante: ', p.nombre, ' ', p.apellido, ' - Unidad: ', COALESCE(p.unidad, 'Sin unidad')) as descripcion,
            p.fecha_registro as fecha_accion,
            p.unidad
        FROM postulantes p
        WHERE p.usuario_registrador = ?
        ORDER BY p.fecha_registro DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$_SESSION['user_id'], $registros_por_pagina, $offset]);
    $actividad = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error al obtener actividad del usuario: " . $e->getMessage());
    $actividad = [];
}

// Obtener capturas de dedos realizadas con paginación
$pagina_capturas = isset($_GET['pagina_capturas']) ? max(1, (int)$_GET['pagina_capturas']) : 1;
$offset_capturas = ($pagina_capturas - 1) * $registros_por_pagina;

try {
    $stmt_capturas = $pdo->prepare("
        SELECT 
            'captura_dedo' as tipo_accion,
            CONCAT('Capturó huella: ', p.nombre, ' ', p.apellido, ' - Dedo: ', COALESCE(p.dedo_registrado, 'No especificado'), ' - Unidad: ', COALESCE(p.unidad, 'Sin unidad')) as descripcion,
            p.fecha_registro as fecha_accion,
            p.unidad,
            p.dedo_registrado
        FROM postulantes p
        WHERE p.capturador_id = ?
        ORDER BY p.fecha_registro DESC
        LIMIT ? OFFSET ?
    ");
    $stmt_capturas->execute([$_SESSION['user_id'], $registros_por_pagina, $offset_capturas]);
    $capturas = $stmt_capturas->fetchAll();
} catch (Exception $e) {
    error_log("Error al obtener capturas del usuario: " . $e->getMessage());
    $capturas = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Cuenta - Sistema Quira</title>
    
    <!-- Icons -->
    <link rel="shortcut icon" href="favicon.php">
    <link rel="apple-touch-icon" href="favicon.php">
    <link rel="icon" href="favicon.php">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            margin: 2rem auto;
            max-width: 1200px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        
        .header-section h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 600;
        }
        
        .header-section p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .content-section {
            padding: 2rem;
        }
        
        .section-title {
            color: #2E5090;
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #495057;
        }
        
        .form-control {
            border-radius: 8px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #2E5090;
            box-shadow: 0 0 0 0.2rem rgba(46, 80, 144, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 80, 144, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .alert {
            border-radius: 8px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .user-info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .user-info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .user-info-item:last-child {
            border-bottom: none;
        }
        
        .user-info-label {
            font-weight: 600;
            color: #495057;
        }
        
        .user-info-value {
            color: #6c757d;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .activity-icon.login {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .activity-icon.logout {
            background: linear-gradient(135deg, #dc3545 0%, #fd7e14 100%);
            color: white;
        }
        
        .activity-icon.account_created {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
        }
        
        .activity-icon.postulante_registrado {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .activity-icon.captura_dedo {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: white;
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-description {
            font-weight: 600;
            color: #495057;
            margin: 0;
        }
        
        .activity-date {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .back-button {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            text-decoration: none;
            transform: scale(1.1);
        }
        
        /* Estilos para paginación */
        .pagination .page-link {
            color: #2E5090;
            border-color: #e9ecef;
            padding: 0.5rem 0.75rem;
        }
        
        .pagination .page-link:hover {
            color: #1a3a70;
            background-color: #f8f9fa;
            border-color: #2E5090;
        }
        
        .pagination .page-item.active .page-link {
            background-color: #2E5090;
            border-color: #2E5090;
            color: white;
        }
        
        .pagination .page-item.disabled .page-link {
            color: #6c757d;
            background-color: #fff;
            border-color: #e9ecef;
        }
        
        /* Estilos para pestañas */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 8px 8px 0 0;
            color: #6c757d;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            border: none;
            color: #2E5090;
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link.active {
            color: #2E5090;
            background-color: white;
            border: none;
            border-bottom: 3px solid #2E5090;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 8px 8px;
            padding: 1.5rem;
            min-height: 400px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section position-relative">
                <a href="dashboard.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h1><i class="fas fa-user-cog"></i> Configuración de Cuenta</h1>
                <p>Gestiona tu información personal y seguridad</p>
            </div>
            
            <!-- Content -->
            <div class="content-section">
                <!-- Información del Usuario -->
                <div class="user-info-card">
                    <h5 class="section-title"><i class="fas fa-user"></i> Información de la Cuenta</h5>
                    <?php if (!empty($usuario_actual)): ?>
                        <div class="user-info-item">
                            <span class="user-info-label">Usuario:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['usuario']) ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Nombre Completo:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Grado:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['grado'] ?? 'No especificado') ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Cédula:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['cedula'] ?? 'No especificada') ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Teléfono:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['telefono'] ?? 'No especificado') ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Email:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['email'] ?? 'No especificado') ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Rol:</span>
                            <span class="user-info-value"><?= htmlspecialchars($usuario_actual['rol']) ?></span>
                        </div>
                        <div class="user-info-item">
                            <span class="user-info-label">Fecha de Creación:</span>
                            <span class="user-info-value"><?= date('d/m/Y H:i', strtotime($usuario_actual['fecha_creacion'])) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Mensajes -->
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $tipo_mensaje === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                        <?= htmlspecialchars($mensaje) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- Cambio de Contraseña -->
                <div class="row">
                    <div class="col-md-6">
                        <h5 class="section-title"><i class="fas fa-lock"></i> Seguridad de la Cuenta</h5>
                        <form method="POST">
                            <div class="form-group">
                                <label for="password_actual">Contraseña Actual</label>
                                <input type="password" class="form-control" id="password_actual" name="password_actual" required>
                            </div>
                            <div class="form-group">
                                <label for="password_nueva">Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password_nueva" name="password_nueva" required minlength="6">
                                <small class="form-text text-muted">Mínimo 6 caracteres</small>
                            </div>
                            <div class="form-group">
                                <label for="password_confirmar">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" required minlength="6">
                            </div>
                            <button type="submit" name="cambiar_password" class="btn btn-primary">
                                <i class="fas fa-save"></i> Cambiar Contraseña
                            </button>
                        </form>
                    </div>
                    
                    <!-- Actividad del Usuario -->
                    <div class="col-md-6">
                        <!-- Pestañas -->
                        <ul class="nav nav-tabs mb-3" id="activityTabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" id="registros-tab" data-toggle="tab" href="#registros" role="tab" aria-controls="registros" aria-selected="true">
                                    <i class="fas fa-user-plus"></i> Registros Realizados
                                    <span class="badge badge-primary ml-2"><?= $total_registros ?></span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" id="capturas-tab" data-toggle="tab" href="#capturas" role="tab" aria-controls="capturas" aria-selected="false">
                                    <i class="fas fa-fingerprint"></i> Capturas de Dedos
                                    <span class="badge badge-warning ml-2"><?= $total_capturas ?></span>
                                </a>
                            </li>
                        </ul>
                        
                        <!-- Contenido de las pestañas -->
                        <div class="tab-content" id="activityTabContent">
                            <!-- Pestaña Registros Realizados -->
                            <div class="tab-pane fade show active" id="registros" role="tabpanel" aria-labelledby="registros-tab">
                                <h5 class="section-title">
                                    <i class="fas fa-user-plus"></i> Registros Realizados
                                    <span class="badge badge-primary ml-2"><?= $total_registros ?></span>
                                </h5>
                        <?php if ($total_registros > 0): ?>
                            <p class="text-muted mb-3">
                                Mostrando <?= count($actividad) ?> de <?= $total_registros ?> registros 
                                (Página <?= $pagina_actual ?> de <?= $total_paginas ?>)
                            </p>
                        <?php endif; ?>
                        <div class="activity-list">
                            <?php if (!empty($actividad)): ?>
                                <?php foreach ($actividad as $accion): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $accion['tipo_accion'] ?>">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="activity-description"><?= htmlspecialchars($accion['descripcion']) ?></p>
                                            <p class="activity-date"><?= date('d/m/Y H:i:s', strtotime($accion['fecha_accion'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-user-plus fa-2x mb-2"></i>
                                    <p>No has registrado postulantes aún</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Controles de Paginación -->
                        <?php if ($total_paginas > 1): ?>
                            <nav aria-label="Paginación de registros">
                                <ul class="pagination justify-content-center mt-3">
                                    <!-- Botón Anterior -->
                                    <?php if ($pagina_actual > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?>" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                                <span class="sr-only">Anterior</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                                <span class="sr-only">Anterior</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Números de página -->
                                    <?php
                                    $inicio = max(1, $pagina_actual - 2);
                                    $fin = min($total_paginas, $pagina_actual + 2);
                                    
                                    if ($inicio > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=1">1</a>
                                        </li>
                                        <?php if ($inicio > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                                        <li class="page-item <?= $i == $pagina_actual ? 'active' : '' ?>">
                                            <a class="page-link" href="?pagina=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($fin < $total_paginas): ?>
                                        <?php if ($fin < $total_paginas - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $total_paginas ?>"><?= $total_paginas ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Botón Siguiente -->
                                    <?php if ($pagina_actual < $total_paginas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?>" aria-label="Siguiente">
                                                <span aria-hidden="true">&raquo;</span>
                                                <span class="sr-only">Siguiente</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Siguiente">
                                                <span aria-hidden="true">&raquo;</span>
                                                <span class="sr-only">Siguiente</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                            </div>
                            
                            <!-- Pestaña Capturas de Dedos -->
                            <div class="tab-pane fade" id="capturas" role="tabpanel" aria-labelledby="capturas-tab">
                                <h5 class="section-title">
                                    <i class="fas fa-fingerprint"></i> Capturas de Dedos
                                    <span class="badge badge-warning ml-2"><?= $total_capturas ?></span>
                                </h5>
                        <?php if ($total_capturas > 0): ?>
                            <p class="text-muted mb-3">
                                Mostrando <?= count($capturas) ?> de <?= $total_capturas ?> capturas 
                                (Página <?= $pagina_capturas ?> de <?= $total_paginas_capturas ?>)
                            </p>
                        <?php endif; ?>
                        <div class="activity-list">
                            <?php if (!empty($capturas)): ?>
                                <?php foreach ($capturas as $captura): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?= $captura['tipo_accion'] ?>">
                                            <i class="fas fa-fingerprint"></i>
                                        </div>
                                        <div class="activity-content">
                                            <p class="activity-description"><?= htmlspecialchars($captura['descripcion']) ?></p>
                                            <p class="activity-date"><?= date('d/m/Y H:i:s', strtotime($captura['fecha_accion'])) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-fingerprint fa-2x mb-2"></i>
                                    <p>No has capturado huellas aún</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Controles de Paginación para Capturas -->
                        <?php if ($total_paginas_capturas > 1): ?>
                            <nav aria-label="Paginación de capturas">
                                <ul class="pagination justify-content-center mt-3">
                                    <!-- Botón Anterior -->
                                    <?php if ($pagina_capturas > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina_capturas=<?= $pagina_capturas - 1 ?>" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                                <span class="sr-only">Anterior</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Anterior">
                                                <span aria-hidden="true">&laquo;</span>
                                                <span class="sr-only">Anterior</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Números de página -->
                                    <?php
                                    $inicio_capturas = max(1, $pagina_capturas - 2);
                                    $fin_capturas = min($total_paginas_capturas, $pagina_capturas + 2);
                                    
                                    if ($inicio_capturas > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina_capturas=1">1</a>
                                        </li>
                                        <?php if ($inicio_capturas > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = $inicio_capturas; $i <= $fin_capturas; $i++): ?>
                                        <li class="page-item <?= $i == $pagina_capturas ? 'active' : '' ?>">
                                            <a class="page-link" href="?pagina_capturas=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($fin_capturas < $total_paginas_capturas): ?>
                                        <?php if ($fin_capturas < $total_paginas_capturas - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                        <?php endif; ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina_capturas=<?= $total_paginas_capturas ?>"><?= $total_paginas_capturas ?></a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <!-- Botón Siguiente -->
                                    <?php if ($pagina_capturas < $total_paginas_capturas): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?pagina_capturas=<?= $pagina_capturas + 1 ?>" aria-label="Siguiente">
                                                <span aria-hidden="true">&raquo;</span>
                                                <span class="sr-only">Siguiente</span>
                                            </a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <span class="page-link" aria-label="Siguiente">
                                                <span aria-hidden="true">&raquo;</span>
                                                <span class="sr-only">Siguiente</span>
                                            </span>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Validación de contraseñas en tiempo real
        document.getElementById('password_confirmar').addEventListener('input', function() {
            const password_nueva = document.getElementById('password_nueva').value;
            const password_confirmar = this.value;
            
            if (password_nueva !== password_confirmar) {
                this.setCustomValidity('Las contraseñas no coinciden');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(function() {
            $('.alert').fadeOut();
        }, 5000);
    </script>
</body>
</html>