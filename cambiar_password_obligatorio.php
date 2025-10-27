<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar si realmente necesita cambiar la contraseña
if (!isset($_SESSION['primer_inicio']) || $_SESSION['primer_inicio'] !== true) {
    header('Location: dashboard.php');
    exit;
}

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

$error = '';
$success = '';

if ($_POST) {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_confirmar = $_POST['password_confirmar'] ?? '';
    
    if (empty($password_actual) || empty($password_nueva) || empty($password_confirmar)) {
        $error = 'Todos los campos son obligatorios';
    } elseif ($password_nueva !== $password_confirmar) {
        $error = 'Las contraseñas nuevas no coinciden';
    } elseif (strlen($password_nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres';
    } else {
        // Verificar contraseña actual
        $stmt = $pdo->prepare("SELECT contrasena FROM usuarios WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password_actual, $user['contrasena'])) {
            // Actualizar contraseña y marcar primer_inicio como false
            $password_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE usuarios SET contrasena = ?, primer_inicio = false WHERE id = ?");
            $stmt->execute([$password_hash, $_SESSION['user_id']]);
            
            // Actualizar sesión
            $_SESSION['primer_inicio'] = false;
            
            $success = 'Contraseña cambiada exitosamente. Redirigiendo al dashboard...';
            echo "<script>setTimeout(function(){ window.location.href = 'dashboard.php'; }, 2000);</script>";
        } else {
            $error = 'La contraseña actual es incorrecta';
        }
    }
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>Cambio de Contraseña Obligatorio - Sistema Quira</title>

    <meta name="description" content="Cambio de contraseña obligatorio - Sistema Quira">
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
        body {
            background: url('assets/media/various/login_background.jpg') center center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .password-container {
            background: white;
            border-radius: 0px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .password-header {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .password-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 0px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #2E5090;
            box-shadow: 0 0 0 0.2rem rgba(46, 80, 144, 0.25);
        }
        .btn-change {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            border: none;
            border-radius: 0px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-change:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 80, 144, 0.4);
        }
        .alert {
            border-radius: 0px;
            border: none;
        }
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 20px;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="password-container">
                    <div class="password-header">
                        <img src="assets/media/various/quiraXXXL.png" alt="Quira Logo" style="height: 60px; width: auto; margin-bottom: 15px;">
                        <h4 class="mb-0">Cambio de Contraseña Obligatorio</h4>
                        <p class="mb-0 opacity-75">Debe cambiar su contraseña para continuar</p>
                    </div>
                    <div class="password-body">
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle text-warning mr-2"></i>
                            <strong>Importante:</strong> Por seguridad, debe cambiar su contraseña antes de acceder al sistema.
                        </div>
                        
                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle mr-2"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="password_actual" class="font-weight-bold">
                                    <i class="fas fa-lock mr-2"></i>Contraseña Actual
                                </label>
                                <input type="password" class="form-control" id="password_actual" name="password_actual" 
                                       placeholder="Ingrese su contraseña actual" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_nueva" class="font-weight-bold">
                                    <i class="fas fa-key mr-2"></i>Nueva Contraseña
                                </label>
                                <input type="password" class="form-control" id="password_nueva" name="password_nueva" 
                                       placeholder="Ingrese su nueva contraseña" required minlength="6">
                                <small class="form-text text-muted">Mínimo 6 caracteres</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_confirmar" class="font-weight-bold">
                                    <i class="fas fa-key mr-2"></i>Confirmar Nueva Contraseña
                                </label>
                                <input type="password" class="form-control" id="password_confirmar" name="password_confirmar" 
                                       placeholder="Confirme su nueva contraseña" required minlength="6">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-change btn-block">
                                <i class="fas fa-save mr-2"></i>Cambiar Contraseña
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i>
                                Después del cambio será redirigido al dashboard
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js" integrity="sha256-ZaXnYkHGqIhqTbJ6MB4l9Frs/r7U4jlx7ir8PJYBqbI=" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>

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
