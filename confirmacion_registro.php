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
            
            /* Safe zone para evitar superposición con footer */
            body {
                padding-bottom: 80px;
            }
            
            .container {
                margin-bottom: 80px !important;
            }
        </style>
    </head>
    <body>
        <div class="container mt-2" style="margin-bottom: 80px;">
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
                                        <div class="info-label">Nombre Completo:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['nombre_completo'] ?? ($postulante['nombre'] . ' ' . $postulante['apellido'])) ?></div>
                                    </div>
                                    <div class="info-row">
                                        <div class="info-label">Cédula:</div>
                                        <div class="info-value"><?= htmlspecialchars($postulante['cedula']) ?></div>
                                    </div>
                                    <div class="info-row" style="display: none;">
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
