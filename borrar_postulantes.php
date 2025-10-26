<?php
// Script para borrar todos los postulantes de la base de datos
// ⚠️ ADVERTENCIA: Este script eliminará TODOS los datos de postulantes
// ⚠️ HACER BACKUP ANTES DE EJECUTAR

require_once 'config.php';

// Verificar que el usuario esté logueado y sea administrador
session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])) {
    die('Error: No tienes permisos para ejecutar esta operación.');
}

// Función para obtener conexión a la base de datos
function getDBConnection() {
    try {
        $pdo = new PDO("pgsql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }
}

// Función para obtener estadísticas antes de borrar
function getEstadisticas($pdo) {
    $stats = [];
    
    // Total de postulantes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM postulantes");
    $stats['total_postulantes'] = $stmt->fetch()['total'];
    
    // Postulantes por unidad
    $stmt = $pdo->query("
        SELECT unidad, COUNT(*) as cantidad 
        FROM postulantes 
        WHERE unidad IS NOT NULL AND unidad != ''
        GROUP BY unidad 
        ORDER BY cantidad DESC
    ");
    $stats['por_unidad'] = $stmt->fetchAll();
    
    // Rango de fechas
    $stmt = $pdo->query("
        SELECT 
            MIN(fecha_registro) as fecha_min,
            MAX(fecha_registro) as fecha_max
        FROM postulantes
    ");
    $stats['rango_fechas'] = $stmt->fetch();
    
    return $stats;
}

// Función para hacer backup
function hacerBackup($pdo) {
    $fecha = date('Y-m-d_H-i-s');
    $archivo_backup = "backups/backup_postulantes_$fecha.sql";
    
    // Crear directorio si no existe
    if (!is_dir('backups')) {
        mkdir('backups', 0755, true);
    }
    
    // Exportar datos de postulantes
    $stmt = $pdo->query("
        SELECT 
            cedula, nombre, apellido, fecha_registro, edad, sexo, 
            unidad, dedo_registrado, registrado_por, aparato_id, 
            aparato_nombre, usuario_registrador, capturador_id
        FROM postulantes 
        ORDER BY fecha_registro
    ");
    $postulantes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $sql = "-- Backup de postulantes generado el " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Total de registros: " . count($postulantes) . "\n\n";
    
    foreach ($postulantes as $postulante) {
        $sql .= "INSERT INTO postulantes (cedula, nombre, apellido, fecha_registro, edad, sexo, ";
        $sql .= "unidad, dedo_registrado, registrado_por, aparato_id, aparato_nombre, ";
        $sql .= "usuario_registrador, capturador_id) VALUES (";
        $sql .= "'" . addslashes($postulante['cedula']) . "', ";
        $sql .= "'" . addslashes($postulante['nombre']) . "', ";
        $sql .= "'" . addslashes($postulante['apellido']) . "', ";
        $sql .= "'" . $postulante['fecha_registro'] . "', ";
        $sql .= ($postulante['edad'] ? $postulante['edad'] : 'NULL') . ", ";
        $sql .= ($postulante['sexo'] ? "'" . addslashes($postulante['sexo']) . "'" : 'NULL') . ", ";
        $sql .= ($postulante['unidad'] ? "'" . addslashes($postulante['unidad']) . "'" : 'NULL') . ", ";
        $sql .= ($postulante['dedo_registrado'] ? "'" . addslashes($postulante['dedo_registrado']) . "'" : 'NULL') . ", ";
        $sql .= ($postulante['registrado_por'] ? "'" . addslashes($postulante['registrado_por']) . "'" : 'NULL') . ", ";
        $sql .= ($postulante['aparato_id'] ? $postulante['aparato_id'] : 'NULL') . ", ";
        $sql .= ($postulante['aparato_nombre'] ? "'" . addslashes($postulante['aparato_nombre']) . "'" : 'NULL') . ", ";
        $sql .= ($postulante['usuario_registrador'] ? $postulante['usuario_registrador'] : 'NULL') . ", ";
        $sql .= ($postulante['capturador_id'] ? $postulante['capturador_id'] : 'NULL');
        $sql .= ");\n";
    }
    
    file_put_contents($archivo_backup, $sql);
    return $archivo_backup;
}

// Procesar la solicitud
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = getDBConnection();
    
    if (isset($_POST['confirmar_borrado'])) {
        try {
            // Hacer backup antes de borrar
            $archivo_backup = hacerBackup($pdo);
            
            // Borrar todos los postulantes
            $stmt = $pdo->prepare("DELETE FROM postulantes");
            $stmt->execute();
            
            $mensaje = "✅ ÉXITO: Todos los postulantes han sido eliminados.<br>";
            $mensaje .= "📁 Backup guardado en: $archivo_backup<br>";
            $mensaje .= "🗑️ Total de registros eliminados: " . $stmt->rowCount();
            
        } catch (Exception $e) {
            $mensaje = "❌ ERROR: " . $e->getMessage();
        }
    }
} else {
    // Mostrar formulario de confirmación
    $pdo = getDBConnection();
    $stats = getEstadisticas($pdo);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrar Postulantes - Sistema QUIRA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .warning-box {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }
        .stats-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
        }
        .btn-danger {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 12px 30px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-danger text-white">
                        <h3 class="mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            Borrar Todos los Postulantes
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($mensaje)): ?>
                            <div class="alert alert-<?= strpos($mensaje, 'ERROR') !== false ? 'danger' : 'success' ?>">
                                <?= $mensaje ?>
                            </div>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Volver al Dashboard
                            </a>
                        <?php else: ?>
                            <div class="warning-box">
                                <h4><i class="fas fa-exclamation-triangle"></i> ADVERTENCIA CRÍTICA</h4>
                                <p class="mb-0">
                                    Esta operación eliminará <strong>TODOS</strong> los postulantes de la base de datos.
                                    Esta acción es <strong>IRREVERSIBLE</strong> y no se puede deshacer.
                                </p>
                            </div>

                            <div class="stats-box">
                                <h5><i class="fas fa-chart-bar"></i> Estadísticas Actuales</h5>
                                <p><strong>Total de postulantes:</strong> <?= number_format($stats['total_postulantes']) ?></p>
                                <p><strong>Rango de fechas:</strong> 
                                    <?= $stats['rango_fechas']['fecha_min'] ?> - <?= $stats['rango_fechas']['fecha_max'] ?>
                                </p>
                                
                                <?php if (!empty($stats['por_unidad'])): ?>
                                <h6>Distribución por unidad:</h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($stats['por_unidad'] as $unidad): ?>
                                    <li>• <?= htmlspecialchars($unidad['unidad']) ?>: <?= $unidad['cantidad'] ?> postulantes</li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </div>

                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle"></i> ¿Qué pasará?</h6>
                                <ul class="mb-0">
                                    <li>Se creará un backup automático antes de eliminar</li>
                                    <li>Se eliminarán todos los <?= number_format($stats['total_postulantes']) ?> postulantes</li>
                                    <li>Se mantendrán intactos los usuarios, aparatos y configuraciones</li>
                                    <li>El backup se guardará en la carpeta <code>backups/</code></li>
                                </ul>
                            </div>

                            <form method="POST" onsubmit="return confirmarBorrado()">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" id="confirmar" required>
                                    <label class="form-check-label" for="confirmar">
                                        <strong>Confirmo que entiendo las consecuencias y quiero proceder con la eliminación</strong>
                                    </label>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" name="confirmar_borrado" class="btn btn-danger btn-lg">
                                        <i class="fas fa-trash"></i> ELIMINAR TODOS LOS POSTULANTES
                                    </button>
                                    <a href="dashboard.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Cancelar
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmarBorrado() {
            const confirmacion = prompt(
                'Para confirmar la eliminación, escribe "ELIMINAR" (en mayúsculas):'
            );
            
            if (confirmacion !== 'ELIMINAR') {
                alert('Operación cancelada. Debes escribir "ELIMINAR" para confirmar.');
                return false;
            }
            
            return confirm(
                '¿ESTÁS COMPLETAMENTE SEGURO?\n\n' +
                'Esta acción eliminará TODOS los postulantes de forma permanente.\n' +
                '¿Quieres continuar?'
            );
        }
    </script>
</body>
</html>
