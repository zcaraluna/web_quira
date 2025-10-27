<?php
session_start();

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Verificar permisos - solo ADMIN y SUPERADMIN pueden gestionar dispositivos
if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['ADMIN', 'SUPERADMIN'])) {
    $error_acceso = true;
    $mensaje_error = 'No tienes permisos para acceder a la gestión de dispositivos biométricos. Solo los administradores pueden gestionar dispositivos del sistema.';
    $rol_actual = $_SESSION['rol'] ?? 'No definido';
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

// Procesar acciones
$mensaje = '';
$tipo_mensaje = '';

if (!isset($error_acceso) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        switch ($accion) {
            case 'crear':
                $nombre = trim($_POST['nombre']);
                $serial = trim($_POST['serial']);
                $ip_address = trim($_POST['ip_address']);
                $puerto = trim($_POST['puerto']);
                $ubicacion = trim($_POST['ubicacion']);
                $estado = $_POST['estado'];
                $activo = 1; // Por defecto todos los dispositivos están activos
                
                // Validaciones básicas
                if (empty($nombre) || empty($serial) || empty($estado)) {
                    throw new Exception('Los campos nombre, serial y estado son obligatorios');
                }
                
                // Verificar si el dispositivo ya existe (por serial)
                $stmt = $pdo->prepare("SELECT id FROM aparatos_biometricos WHERE serial = ?");
                $stmt->execute([$serial]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un dispositivo con este número de serie');
                }
                
                // Crear dispositivo
                $stmt = $pdo->prepare("INSERT INTO aparatos_biometricos (nombre, serial, ip_address, puerto, ubicacion, estado, fecha_registro, activo) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)");
                $stmt->execute([$nombre, $serial, $ip_address, $puerto, $ubicacion, $estado, $activo]);
                
                $mensaje = 'Dispositivo creado exitosamente';
                $tipo_mensaje = 'success';
                break;
                
            case 'editar':
                $id = (int)$_POST['id'];
                $nombre = trim($_POST['nombre']);
                $serial = trim($_POST['serial']);
                $ip_address = trim($_POST['ip_address']);
                $puerto = trim($_POST['puerto']);
                $ubicacion = trim($_POST['ubicacion']);
                $estado = $_POST['estado'];
                $activo = 1; // Por defecto todos los dispositivos están activos
                
                // Validaciones básicas
                if (empty($nombre) || empty($serial) || empty($estado)) {
                    throw new Exception('Los campos nombre, serial y estado son obligatorios');
                }
                
                // Verificar si el dispositivo ya existe (excluyendo el actual)
                $stmt = $pdo->prepare("SELECT id FROM aparatos_biometricos WHERE serial = ? AND id != ?");
                $stmt->execute([$serial, $id]);
                if ($stmt->fetch()) {
                    throw new Exception('Ya existe un dispositivo con este número de serie');
                }
                
                // Actualizar dispositivo
                $stmt = $pdo->prepare("UPDATE aparatos_biometricos SET nombre = ?, serial = ?, ip_address = ?, puerto = ?, ubicacion = ?, estado = ?, activo = ? WHERE id = ?");
                $stmt->execute([$nombre, $serial, $ip_address, $puerto, $ubicacion, $estado, $activo, $id]);
                
                $mensaje = 'Dispositivo actualizado exitosamente';
                $tipo_mensaje = 'success';
                break;
                
            case 'eliminar':
                $id = (int)$_POST['id'];
                
                if ($id <= 0) {
                    throw new Exception('ID de dispositivo inválido');
                }
                
                // Verificar que el dispositivo existe
                $stmt = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos WHERE id = ?");
                $stmt->execute([$id]);
                $dispositivo = $stmt->fetch();
                
                if (!$dispositivo) {
                    throw new Exception('El dispositivo no existe');
                }
                
                // Iniciar transacción para eliminar dispositivo y preservar información
                $pdo->beginTransaction();
                
                try {
                    // Primero, guardar el nombre del dispositivo en los postulantes antes de desvincular
                    $stmt = $pdo->prepare("UPDATE postulantes SET aparato_nombre = ?, aparato_id = NULL WHERE aparato_id = ?");
                    $stmt->execute([$dispositivo['nombre'], $id]);
                    $postulantes_afectados = $stmt->rowCount();
                    
                    // Luego, eliminar el dispositivo
                    $stmt = $pdo->prepare("DELETE FROM aparatos_biometricos WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $pdo->commit();
                        $mensaje = 'Dispositivo "' . $dispositivo['nombre'] . '" eliminado exitosamente';
                        if ($postulantes_afectados > 0) {
                            $mensaje .= " (se preservó el nombre del dispositivo en $postulantes_afectados postulante(s))";
                        }
                        $tipo_mensaje = 'success';
                    } else {
                        throw new Exception('No se pudo eliminar el dispositivo');
                    }
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
        }
    } catch (Exception $e) {
        $mensaje = $e->getMessage();
        $tipo_mensaje = 'danger';
    }
}

// Solo obtener datos si el usuario tiene permisos
if (!isset($error_acceso)) {
    // Obtener lista de dispositivos con conteo de postulantes
    $dispositivos = $pdo->query("
        SELECT 
            a.id, a.nombre, a.serial, a.ip_address, a.puerto, a.ubicacion, 
            a.estado, a.fecha_registro, a.activo,
            COUNT(p.id) as postulantes_count
        FROM aparatos_biometricos a
        LEFT JOIN postulantes p ON a.id = p.aparato_id
        GROUP BY a.id, a.nombre, a.serial, a.ip_address, a.puerto, a.ubicacion, a.estado, a.fecha_registro, a.activo
        ORDER BY a.fecha_registro DESC
    ")->fetchAll();
    $estados_dispositivos = ['ACTIVO', 'INACTIVO', 'PRUEBA', 'MANTENIMIENTO'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Dispositivos Biométricos - Sistema Quira</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <link rel="shortcut icon" href="favicon.php">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-fingerprint"></i> Gestión de Dispositivos Biométricos</h1>
                    <div>
                        <span class="text-muted">Bienvenido: <?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></span>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm ml-2">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error_acceso)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x mr-3"></i>
                        <div>
                            <h5 class="alert-heading mb-2">Acceso Restringido</h5>
                            <p class="mb-2"><?= htmlspecialchars($mensaje_error) ?></p>
                            <p class="mb-0"><strong>Tu rol actual:</strong> <?= htmlspecialchars($rol_actual) ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Contacta a un administrador si necesitas acceso a esta funcionalidad.
                        </small>
                        <a href="dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i> Volver al Inicio
                        </a>
                    </div>
                </div>
                <?php else: ?>
                
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Lista de Dispositivos</h5>
                        <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#modalCrearDispositivo">
                            <i class="fas fa-plus"></i> Nuevo Dispositivo
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Serial</th>
                                        <th>IP Address</th>
                                        <th>Puerto</th>
                                        <th>Ubicación</th>
                                        <th>Estado</th>
                                        <th>Activo</th>
                                        <th>Postulantes</th>
                                        <th>Fecha Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dispositivos as $dispositivo): ?>
                                    <tr>
                                        <td><?= $dispositivo['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($dispositivo['nombre']) ?></strong></td>
                                        <td><code><?= htmlspecialchars($dispositivo['serial']) ?></code></td>
                                        <td><?= htmlspecialchars($dispositivo['ip_address']) ?></td>
                                        <td><?= htmlspecialchars($dispositivo['puerto']) ?></td>
                                        <td><?= htmlspecialchars($dispositivo['ubicacion']) ?></td>
                                        <td>
                                            <?php
                                            $estado_class = '';
                                            switch($dispositivo['estado']) {
                                                case 'ACTIVO': $estado_class = 'badge-success'; break;
                                                case 'INACTIVO': $estado_class = 'badge-secondary'; break;
                                                case 'PRUEBA': $estado_class = 'badge-warning'; break;
                                                case 'MANTENIMIENTO': $estado_class = 'badge-danger'; break;
                                                default: $estado_class = 'badge-primary';
                                            }
                                            ?>
                                            <span class="badge <?= $estado_class ?>"><?= htmlspecialchars($dispositivo['estado']) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($dispositivo['activo']): ?>
                                                <span class="badge badge-success">Sí</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($dispositivo['postulantes_count'] > 0): ?>
                                                <span class="badge badge-warning"><?= $dispositivo['postulantes_count'] ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-light">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($dispositivo['fecha_registro'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="editarDispositivo(<?= htmlspecialchars(json_encode($dispositivo)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                        onclick="eliminarDispositivo(<?= $dispositivo['id'] ?>, '<?= htmlspecialchars($dispositivo['nombre']) ?>', <?= $dispositivo['postulantes_count'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="dashboard.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Volver al Inicio
                    </a>
                </div>
                
                <?php endif; // Cerrar el else del error_acceso ?>
            </div>
        </div>
    </div>
    
    <?php if (!isset($error_acceso)): ?>
    <!-- Modal Crear Dispositivo -->
    <div class="modal fade" id="modalCrearDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="crear">
                    <div class="modal-header">
                        <h5 class="modal-title">Crear Nuevo Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="nombre">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="serial">Número de Serie *</label>
                            <input type="text" class="form-control" id="serial" name="serial" required>
                        </div>
                        <div class="form-group">
                            <label for="ip_address">Dirección IP</label>
                            <input type="text" class="form-control" id="ip_address" name="ip_address" placeholder="192.168.1.100">
                        </div>
                        <div class="form-group">
                            <label for="puerto">Puerto</label>
                            <input type="number" class="form-control" id="puerto" name="puerto" value="4370">
                        </div>
                        <div class="form-group">
                            <label for="ubicacion">Ubicación</label>
                            <input type="text" class="form-control" id="ubicacion" name="ubicacion">
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado *</label>
                            <select class="form-control" id="estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <?php foreach ($estados_dispositivos as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar Dispositivo -->
    <div class="modal fade" id="modalEditarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Editar Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div class="form-group">
                            <label for="edit_nombre">Nombre *</label>
                            <input type="text" class="form-control" id="edit_nombre" name="nombre" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_serial">Número de Serie *</label>
                            <input type="text" class="form-control" id="edit_serial" name="serial" required>
                        </div>
                        <div class="form-group">
                            <label for="edit_ip_address">Dirección IP</label>
                            <input type="text" class="form-control" id="edit_ip_address" name="ip_address">
                        </div>
                        <div class="form-group">
                            <label for="edit_puerto">Puerto</label>
                            <input type="number" class="form-control" id="edit_puerto" name="puerto">
                        </div>
                        <div class="form-group">
                            <label for="edit_ubicacion">Ubicación</label>
                            <input type="text" class="form-control" id="edit_ubicacion" name="ubicacion">
                        </div>
                        <div class="form-group">
                            <label for="edit_estado">Estado *</label>
                            <select class="form-control" id="edit_estado" name="estado" required>
                                <option value="">Seleccionar estado</option>
                                <?php foreach ($estados_dispositivos as $estado): ?>
                                <option value="<?= htmlspecialchars($estado) ?>"><?= htmlspecialchars($estado) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Actualizar Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Eliminar Dispositivo -->
    <div class="modal fade" id="modalEliminarDispositivo" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" id="delete_id">
                    <div class="modal-header">
                        <h5 class="modal-title">Eliminar Dispositivo</h5>
                        <button type="button" class="close" data-dismiss="modal">
                            <span>&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p id="delete_message">¿Estás seguro de que deseas eliminar el dispositivo <strong id="delete_nombre"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger">Eliminar Dispositivo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    
    <script>
    function editarDispositivo(dispositivo) {
        document.getElementById('edit_id').value = dispositivo.id;
        document.getElementById('edit_nombre').value = dispositivo.nombre;
        document.getElementById('edit_serial').value = dispositivo.serial;
        document.getElementById('edit_ip_address').value = dispositivo.ip_address || '';
        document.getElementById('edit_puerto').value = dispositivo.puerto || '';
        document.getElementById('edit_ubicacion').value = dispositivo.ubicacion || '';
        document.getElementById('edit_estado').value = dispositivo.estado;
        
        $('#modalEditarDispositivo').modal('show');
    }
    
    function eliminarDispositivo(id, nombre, postulantesCount) {
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_nombre').textContent = nombre;
        
        // Mensaje simple de confirmación
        const modalMessage = document.getElementById('delete_message');
        if (postulantesCount > 0) {
            modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar el dispositivo <strong>${nombre}</strong>?<br><br><span class="text-info"><i class="fas fa-info-circle"></i> <strong>Nota:</strong> Este dispositivo está siendo usado por ${postulantesCount} postulante(s). El nombre del dispositivo se preservará en los registros de los postulantes.</span><br><br><span class="text-danger"><strong>Esta acción no se puede deshacer.</strong></span>`;
        } else {
            modalMessage.innerHTML = `¿Estás seguro de que deseas eliminar el dispositivo <strong>${nombre}</strong>?<br><br><span class="text-danger"><strong>Esta acción no se puede deshacer.</strong></span>`;
        }
        
        $('#modalEliminarDispositivo').modal('show');
    }
    </script>
    
    <!-- Footer fijo y modal del desarrollador -->
    <?php include 'includes/developer-footer.php'; ?>
</body>
</html>
