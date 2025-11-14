<?php
/**
 * Script de diagnóstico para identificar el error 500
 */

// Habilitar mostrar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>Diagnóstico del Sistema</h2>";

// 1. Verificar versión de PHP
echo "<h3>1. Versión de PHP</h3>";
echo "Versión: " . phpversion() . "<br>";
echo "¿Cumple con >= 7.4? " . (version_compare(phpversion(), '7.4.0', '>=') ? 'SÍ' : 'NO') . "<br>";
echo "¿Cumple con >= 8.3? " . (version_compare(phpversion(), '8.3.0', '>=') ? 'SÍ' : 'NO') . "<br><br>";

// 2. Verificar si existe composer.json
echo "<h3>2. Archivo composer.json</h3>";
if (file_exists('composer.json')) {
    echo "✓ composer.json existe<br>";
    $composer = json_decode(file_get_contents('composer.json'), true);
    if ($composer) {
        echo "✓ composer.json es válido<br>";
        if (isset($composer['require']['php'])) {
            echo "Requisito PHP: " . $composer['require']['php'] . "<br>";
        }
    } else {
        echo "✗ composer.json tiene errores de sintaxis JSON<br>";
        echo "Error: " . json_last_error_msg() . "<br>";
    }
} else {
    echo "✗ composer.json NO existe<br>";
}
echo "<br>";

// 3. Verificar si existe vendor/autoload.php
echo "<h3>3. Autoloader de Composer</h3>";
if (file_exists('vendor/autoload.php')) {
    echo "✓ vendor/autoload.php existe<br>";
    try {
        require_once 'vendor/autoload.php';
        echo "✓ Autoloader cargado correctamente<br>";
    } catch (Exception $e) {
        echo "✗ Error al cargar autoloader: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ vendor/autoload.php NO existe<br>";
    echo "Nota: Esto es normal si no se han instalado las dependencias de Composer<br>";
}
echo "<br>";

// 4. Verificar conexión a la base de datos
echo "<h3>4. Conexión a Base de Datos</h3>";
if (file_exists('config.php')) {
    echo "✓ config.php existe<br>";
    try {
        require_once 'config.php';
        $pdo = getDBConnection();
        echo "✓ Conexión a la base de datos exitosa<br>";
    } catch (Exception $e) {
        echo "✗ Error de conexión: " . $e->getMessage() . "<br>";
    }
} else {
    echo "✗ config.php NO existe<br>";
}
echo "<br>";

// 5. Verificar archivos principales
echo "<h3>5. Archivos Principales</h3>";
$archivos_importantes = ['dashboard.php', 'login.php', 'config.php'];
foreach ($archivos_importantes as $archivo) {
    if (file_exists($archivo)) {
        echo "✓ $archivo existe<br>";
        // Verificar sintaxis PHP
        $output = [];
        $return_var = 0;
        exec("php -l $archivo 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "  → Sintaxis PHP válida<br>";
        } else {
            echo "  → ✗ ERROR de sintaxis:<br>";
            foreach ($output as $line) {
                echo "    " . htmlspecialchars($line) . "<br>";
            }
        }
    } else {
        echo "✗ $archivo NO existe<br>";
    }
}
echo "<br>";

// 6. Verificar logs de error de PHP
echo "<h3>6. Logs de Error</h3>";
$error_log_paths = [
    ini_get('error_log'),
    '/var/log/php_errors.log',
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    __DIR__ . '/error.log',
    __DIR__ . '/php_errors.log'
];

$log_encontrado = false;
foreach ($error_log_paths as $log_path) {
    if ($log_path && file_exists($log_path) && is_readable($log_path)) {
        echo "✓ Log encontrado: $log_path<br>";
        echo "Últimas 10 líneas:<br>";
        $lines = file($log_path);
        $last_lines = array_slice($lines, -10);
        echo "<pre style='background: #f0f0f0; padding: 10px; overflow-x: auto;'>";
        echo htmlspecialchars(implode('', $last_lines));
        echo "</pre>";
        $log_encontrado = true;
        break;
    }
}

if (!$log_encontrado) {
    echo "No se encontró ningún log de errores accesible<br>";
    echo "Ruta configurada en PHP: " . (ini_get('error_log') ?: 'No configurada') . "<br>";
}
echo "<br>";

// 7. Intentar cargar dashboard.php
echo "<h3>7. Prueba de Carga de dashboard.php</h3>";
if (file_exists('dashboard.php')) {
    // Capturar cualquier error
    ob_start();
    $error_occurred = false;
    try {
        // Simular una carga básica (sin ejecutar todo el código)
        $content = file_get_contents('dashboard.php');
        // Verificar sintaxis
        $output = [];
        $return_var = 0;
        exec("php -l dashboard.php 2>&1", $output, $return_var);
        if ($return_var === 0) {
            echo "✓ Sintaxis de dashboard.php es válida<br>";
        } else {
            echo "✗ ERROR de sintaxis en dashboard.php:<br>";
            foreach ($output as $line) {
                echo htmlspecialchars($line) . "<br>";
            }
            $error_occurred = true;
        }
    } catch (Exception $e) {
        echo "✗ Excepción: " . $e->getMessage() . "<br>";
        $error_occurred = true;
    }
    $output_content = ob_get_clean();
    if ($output_content) {
        echo "Salida capturada:<br><pre>" . htmlspecialchars($output_content) . "</pre>";
    }
} else {
    echo "✗ dashboard.php no existe<br>";
}

echo "<br><hr>";
echo "<p><strong>Nota:</strong> Si ves errores arriba, esos son los que están causando el error 500.</p>";
echo "<p>Para solucionar problemas de Composer, ejecuta: <code>composer install</code></p>";
?>

