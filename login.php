<?php
session_start();

// Función para seleccionar imagen aleatoria del login
function getRandomLoginImage() {
    $loginDir = 'assets/media/login/';
    if (is_dir($loginDir)) {
        $images = glob($loginDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
        if (!empty($images)) {
            return $images[array_rand($images)];
        }
    }
    // Fallback a la imagen original si no hay imágenes en la carpeta
    return 'assets/media/various/login_background.jpg';
}

$randomLoginImage = getRandomLoginImage();

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

if ($_POST) {
    $usuario = $_POST['usuario'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';
    
    if ($usuario && $contrasena) {
        // Buscar usuario en la base de datos
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
        $stmt->execute([$usuario]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($contrasena, $user['contrasena'])) {
            // Login exitoso
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['usuario'] = $user['usuario'];
            $_SESSION['nombre'] = $user['nombre'];
            $_SESSION['apellido'] = $user['apellido'];
            $_SESSION['grado'] = $user['grado'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['primer_inicio'] = $user['primer_inicio'];
            
            // Verificar si necesita cambiar contraseña
            if ($user['primer_inicio']) {
                header('Location: cambiar_password_obligatorio.php');
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos';
        }
    } else {
        $error = 'Por favor complete todos los campos';
    }
}
?>

<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
    <meta http-equiv="x-ua-compatible" content="ie=edge">

    <title>Login - Sistema Quira</title>

    <meta name="description" content="Sistema de autenticación - Sistema Quira">
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
            background: url('<?php echo $randomLoginImage; ?>') center center/cover no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-container {
            background: white;
            border-radius: 0px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
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
        .btn-login {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            border: none;
            border-radius: 0px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 80, 144, 0.4);
        }
        .alert {
            border-radius: 0px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <img src="assets/media/various/quiraXXXL.png" alt="Quira Logo" style="height: 60px; width: auto; margin-bottom: 15px;">
                        <h4 class="mb-0">Sistema Quira</h4>
                        <p class="mb-0 opacity-75">Panel de Administración</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="form-group">
                                <label for="usuario" class="font-weight-bold">
                                    <i class="fas fa-user mr-2"></i>Usuario
                                </label>
                                <input type="text" class="form-control" id="usuario" name="usuario" 
                                       placeholder="Ingrese su usuario" required 
                                       value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="contrasena" class="font-weight-bold">
                                    <i class="fas fa-lock mr-2"></i>Contraseña
                                </label>
                                <input type="password" class="form-control" id="contrasena" name="contrasena" 
                                       placeholder="Ingrese su contraseña" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-login btn-block">
                                <i class="fas fa-sign-in-alt mr-2"></i>Iniciar Sesión
                            </button>
                        </form>
                        
                        <div class="text-center mt-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle mr-1"></i>
                                Acceso restringido al personal autorizado
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
</body>
</html>
