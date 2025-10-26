    <?php
    /**
     * Página de confirmación de registro de postulante
     * Sistema QUIRA - Versión Web
     */

    // Configurar zona horaria para Paraguay
    date_default_timezone_set('America/Asuncion');

    session_start();
    require_once 'config.php';
    requireLogin();

    // Verificar que hay datos de postulante registrado
    if (!isset($_SESSION['postulante_registrado'])) {
        header('Location: agregar_postulante.php');
        exit;
    }

    $postulante = $_SESSION['postulante_registrado'];

    // Limpiar los datos de la sesión después de mostrarlos
    unset($_SESSION['postulante_registrado']);
    ?>

    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <title>Confirmación de Registro - Sistema Quira</title>
        
        <!-- Bootstrap CSS -->
        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
        <!-- Font Awesome -->
        <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
        <!-- Favicon -->
        <link rel="shortcut icon" href="favicon.php">
        
        <style>
            .confirmation-card {
                border: 2px solid #28a745;
                border-radius: 0;
                box-shadow: 0 8px 25px rgba(40, 167, 69, 0.15);
                overflow: hidden;
            }
            .success-header {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
                border-radius: 0;
                margin: -2px -2px 0 -2px;
            }
            .info-row {
                border-bottom: 1px solid #e9ecef;
                padding: 6px 0;
            }
            .info-row:last-child {
                border-bottom: none;
            }
            .info-label {
                font-weight: 600;
                color: #2E5090;
            }
            .info-value {
                color: #495057;
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
            .warning-badge {
                background-color: #ffc107;
                color: #212529;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.8em;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="container mt-2">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <!-- Header centrado -->
                    <div class="text-center mb-2">
                        <h2><i class="fas fa-check-circle text-success"></i> Registro Exitoso</h2>
                    </div>
                    
                    <!-- Tarjeta de confirmación -->
                    <div class="card confirmation-card">
                        <div class="card-header success-header text-center py-2">
                            <h5 class="mb-0">
                                <i class="fas fa-user-check mr-1"></i>
                                ¡Postulante Registrado Exitosamente!
                            </h5>
                            <?php if ($postulante['problema_judicial']): ?>
                            <div class="mt-1">
                                <span class="warning-badge">
                                    <i class="fas fa-exclamation-triangle"></i> ADVERTENCIA: Problema Judicial Detectado
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body py-3">
                            <!-- Información del postulante -->
                            <h6 class="text-primary mb-2">
                                <i class="fas fa-user"></i> Datos del Postulante
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">ID en K40:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['uid_k40']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Nombre:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['nombre']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Apellido:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['apellido']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Cédula:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['cedula']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Teléfono:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['telefono']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">Fecha de Nacimiento:</div>
                                        <div class="info-value">
                                            <?= $postulante['fecha_nacimiento'] ? date('d/m/Y', strtotime($postulante['fecha_nacimiento'])) : 'No especificada' ?>
                                        </div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Edad:</div>
                                        <div class="info-value"><?= $postulante['edad'] ? $postulante['edad'] . ' años' : 'No calculada' ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Sexo:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['sexo']) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Unidad:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['unidad']) ?: 'No especificada' ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Dedo Registrado:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['dedo_registrado']) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($postulante['observaciones']): ?>
                            <div class="info-row mt-1">
                                <div class="info-label">Observaciones:</div>
                                <div class="info-value"><?= htmlspecialchars($postulante['observaciones']) ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Información del registro -->
                            <hr class="my-2">
                            <h6 class="text-primary mb-2">
                                <i class="fas fa-fingerprint"></i> Información del Registro Biométrico
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">Aparato Biométrico:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['aparato_nombre']) ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <div class="info-label">Fecha y Hora de Registro:</div>
                                        <div class="info-value"><?= date('d/m/Y H:i:s', strtotime($postulante['fecha_registro'])) ?></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Botones de acción -->
                            <div class="text-center mt-3">
                                <div class="btn-group" role="group">
                                    <a href="agregar_postulante.php" class="btn btn-success">
                                        <i class="fas fa-user-plus mr-1"></i> Registrar Otro
                                    </a>
                                    <a href="dashboard.php" class="btn btn-primary">
                                        <i class="fas fa-tachometer-alt mr-1"></i> Inicio
                                    </a>
                                    <a href="lista_postulantes.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list mr-1"></i> Ver Lista
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Información adicional -->
                            <div class="alert alert-info mt-2 mb-0 py-2">
                                <i class="fas fa-info-circle"></i>
                                <strong>Información:</strong> El postulante ha sido registrado exitosamente en el sistema biométrico y en la base de datos.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scripts -->
        <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js"></script>
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
        
        <script>
            // Auto-redireccionar después de 30 segundos (opcional)
            // setTimeout(() => {
            //     window.location.href = 'agregar_postulante.php';
            // }, 30000);
        </script>
    </body>
    </html>
