<?php
/**
 * Configuración de la base de datos del Sistema Quira
 */

// Configuración de la base de datos
define('DB_HOST', '64.176.18.16');
define('DB_NAME', 'sistema_postulantes');
define('DB_USER', 'postgres');
define('DB_PASS', 'Postgres2025!');

// Configuración de la aplicación
define('APP_NAME', 'Sistema Quira');
define('APP_VERSION', '1.0.0');
define('APP_LOGO', 'assets/media/various/quiraXXXL.png');

// Configuración de sesión
define('SESSION_TIMEOUT', 3600); // 1 hora

// Función para conectar a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO("pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}

// Función para verificar si el usuario está logueado
function requireLogin() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
    
    // Verificar timeout de sesión
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

// Función para verificar permisos de usuario
function hasPermission($required_role) {
    if (!isset($_SESSION['rol'])) {
        return false;
    }
    
    $role_hierarchy = [
        'USUARIO' => 1,
        'OPERADOR' => 2,
        'SUPERVISOR' => 3,
        'ADMIN' => 4,
        'SUPERADMIN' => 5
    ];
    
    $user_level = $role_hierarchy[$_SESSION['rol']] ?? 0;
    $required_level = $role_hierarchy[$required_role] ?? 0;
    
    return $user_level >= $required_level;
}

// Función para sanitizar datos de entrada
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Función para formatear fechas
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

// Función para formatear números
function formatNumber($number) {
    return number_format($number, 0, ',', '.');
}
?>
