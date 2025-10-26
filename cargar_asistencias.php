<?php
/**
 * Página para cargar logs de asistencia desde archivos .dat
 * Sistema QUIRA - Versión Web
 * 
 * IMPORTANTE: Este sistema utiliza la zona horaria America/Asuncion
 */

// Configurar zona horaria para Paraguay
date_default_timezone_set('America/Asuncion');

// Función para escribir logs de debug
function writeDebugLog($message) {
    $log_file = 'debug.log';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

require_once 'config.php';
requireLogin();

// Conectar a la base de datos
$pdo = getDBConnection();

// Verificar permisos - cualquier usuario autenticado puede cargar asistencias
// (ADMIN, SUPERADMIN, USER)

$mensaje = '';
$tipo_mensaje = '';
$logs_data = [];
$dispositivos = [];

// Obtener lista de dispositivos biométricos
try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos ORDER BY nombre");
    $stmt->execute();
    $dispositivos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $mensaje = "Error al cargar dispositivos: " . $e->getMessage();
    $tipo_mensaje = 'danger';
}

// Procesar carga de archivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_dat'])) {
    $archivo = $_FILES['archivo_dat'];
    $dispositivo_id = $_POST['dispositivo_id'] ?? '';
    
    if ($archivo['error'] === UPLOAD_ERR_OK && !empty($dispositivo_id)) {
        $contenido = file_get_contents($archivo['tmp_name']);
        $lineas = explode("\n", trim($contenido));
        
        $logs_procesados = [];
        $errores = [];
        
        foreach ($lineas as $numero_linea => $linea) {
            $numero_linea++; // Para mostrar números de línea desde 1
            $linea = trim($linea);
            
            if (empty($linea)) continue;
            
            $campos = explode("\t", $linea);
            
            if (count($campos) >= 2) {
                $uid_k40 = trim($campos[0]);
                $fecha_hora = trim($campos[1]);
                
                // Validar formato de fecha
                if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fecha_hora)) {
                    // Buscar postulante por uid_k40 y aparato_id
                    try {
                        $stmt = $pdo->prepare("
                            SELECT id, nombre, apellido, cedula, uid_k40, aparato_id 
                            FROM postulantes 
                            WHERE uid_k40 = ? AND aparato_id = ?
                        ");
                        $stmt->execute([$uid_k40, $dispositivo_id]);
                        $postulante = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($postulante) {
                            $logs_procesados[] = [
                                'uid_k40' => $uid_k40,
                                'fecha_hora' => $fecha_hora,
                                'postulante' => $postulante,
                                'linea' => $numero_linea
                            ];
                        } else {
                            $errores[] = "Línea $numero_linea: Usuario ID $uid_k40 no encontrado en dispositivo $dispositivo_id";
                        }
                    } catch (Exception $e) {
                        $errores[] = "Línea $numero_linea: Error en consulta - " . $e->getMessage();
                    }
                } else {
                    $errores[] = "Línea $numero_linea: Formato de fecha inválido - $fecha_hora";
                }
            } else {
                $errores[] = "Línea $numero_linea: Formato de línea inválido";
            }
        }
        
        if (!empty($logs_procesados)) {
            $logs_data = $logs_procesados;
            $mensaje = "Archivo procesado exitosamente. " . count($logs_procesados) . " registros válidos encontrados.";
            if (!empty($errores)) {
                $mensaje .= " " . count($errores) . " errores encontrados.";
            }
            $tipo_mensaje = 'success';
        } else {
            $mensaje = "No se encontraron registros válidos en el archivo.";
            $tipo_mensaje = 'warning';
        }
        
        if (!empty($errores)) {
            writeDebugLog("Errores en carga de archivo: " . implode("; ", $errores));
        }
        
    } else {
        $mensaje = "Error al cargar el archivo o dispositivo no seleccionado.";
        $tipo_mensaje = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cargar Asistencias - Sistema Quira</title>
    
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
        .table-hover tbody tr:hover {
            background-color: rgba(46, 80, 144, 0.1);
        }
        .upload-area {
            border: 2px dashed #2E5090;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            background-color: #e9ecef;
            border-color: #1e3a6b;
        }
        .upload-area.dragover {
            background-color: #e3f2fd;
            border-color: #1976d2;
        }
        .file-info {
            background-color: #e8f5e8;
            border: 1px solid #c3e6c3;
            border-radius: 5px;
            padding: 10px;
            margin-top: 10px;
        }
        
        /* Estilos para el modal de detalles - más compacto */
        #modalDetallesPostulante .modal-body {
            padding: 1rem;
        }
        #modalDetallesPostulante .row {
            margin-bottom: 1rem;
        }
        #modalDetallesPostulante .row:last-child {
            margin-bottom: 0;
        }
        #modalDetallesPostulante h6 {
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        #modalDetallesPostulante .table-borderless td {
            padding: 0.15rem 0.25rem;
            border: none;
            line-height: 1.2;
            font-size: 0.9rem;
        }
        #modalDetallesPostulante .table-borderless td:first-child {
            width: 35%;
            font-weight: 600;
            color: #495057;
            padding-right: 0.4rem;
        }
        #modalDetallesPostulante .table-borderless td:last-child {
            width: 65%;
            padding-left: 0.15rem;
        }
        #modalDetallesPostulante .table-borderless tr {
            margin-bottom: 0.05rem;
        }
        #modalDetallesPostulante .alert {
            padding: 0.5rem 0.75rem;
            margin-bottom: 0.5rem;
        }
        #modalDetallesPostulante .alert p {
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-upload"></i> Cargar Asistencias</h1>
                    <div>
                        <span class="text-muted">Bienvenido: <?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></span>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ml-2">
                            <i class="fas fa-arrow-left"></i> Volver al Inicio
                        </a>
                        <a href="control_asistencia.php" class="btn btn-outline-info btn-sm ml-2">
                            <i class="fas fa-clock"></i> Control Asistencia
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm ml-2">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                
                <!-- Mensajes del sistema -->
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Formulario de carga -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-upload"></i> Cargar Archivo de Logs (.dat)</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="formCargarArchivo">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="archivo_dat"><i class="fas fa-file"></i> Archivo .dat</label>
                                        <div class="upload-area" id="uploadArea">
                                            <i class="fas fa-cloud-upload-alt fa-2x text-muted mb-2"></i>
                                            <p class="mb-1 small">Arrastra y suelta tu archivo .dat aquí</p>
                                            <p class="text-muted small mb-2">o</p>
                                            <input type="file" class="form-control-file" id="archivo_dat" name="archivo_dat" 
                                                   accept=".dat" required>
                                            <div class="file-info" id="fileInfo" style="display: none;">
                                                <i class="fas fa-file-alt"></i>
                                                <span id="fileName"></span>
                                                <small class="text-muted d-block" id="fileSize"></small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="dispositivo_id"><i class="fas fa-fingerprint"></i> Dispositivo Biométrico</label>
                                        <select class="form-control" id="dispositivo_id" name="dispositivo_id" required>
                                            <option value="">Seleccionar dispositivo...</option>
                                            <?php foreach ($dispositivos as $dispositivo): ?>
                                                <option value="<?= $dispositivo['id'] ?>"><?= htmlspecialchars($dispositivo['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-text text-muted">
                                            Seleccione el dispositivo biométrico del cual proviene el archivo
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" class="btn btn-primary" id="btnCargar">
                                        <i class="fas fa-upload"></i> Cargar y Procesar Archivo
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btnLimpiar">
                                        <i class="fas fa-times"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Tabla de resultados -->
                <?php if (!empty($logs_data)): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Registros de Asistencia Cargados</h5>
                        <span class="badge badge-primary"><?= count($logs_data) ?> registros</span>
                    </div>
                    <div class="card-body">
                        <!-- Información de logs cargados -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="alert alert-info mb-1 py-2">
                                    <i class="fas fa-database"></i> <strong>Total cargados:</strong> <?= count($logs_data) ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-success mb-1 py-2">
                                    <i class="fas fa-users"></i> <strong>Usuarios únicos:</strong> <span id="usuarios-unicos"><?= count(array_unique(array_column($logs_data, 'uid_k40'))) ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="alert alert-warning mb-1 py-2">
                                    <i class="fas fa-calendar-day"></i> <strong>Rango de fechas:</strong> 
                                    <span id="rango-fechas">
                                        <?php 
                                        $fechas = array_column($logs_data, 'fecha_hora');
                                        if (!empty($fechas)) {
                                            sort($fechas);
                                            $primera = date('d/m/Y', strtotime($fechas[0]));
                                            $ultima = date('d/m/Y', strtotime($fechas[count($fechas)-1]));
                                            echo "$primera - $ultima";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filtros -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="filtro_nombre" class="mb-1"><i class="fas fa-user"></i> Filtrar por nombre</label>
                                    <input type="text" class="form-control form-control-sm" id="filtro_nombre"
                                           placeholder="Nombre del postulante">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="fecha_desde" class="mb-1"><i class="fas fa-calendar"></i> Fecha desde</label>
                                    <input type="date" class="form-control form-control-sm" id="fecha_desde">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group mb-2">
                                    <label for="fecha_hasta" class="mb-1"><i class="fas fa-calendar"></i> Fecha hasta</label>
                                    <input type="date" class="form-control form-control-sm" id="fecha_hasta">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-primary btn-sm" id="btn-filtrar">
                                        <i class="fas fa-filter"></i> Filtrar
                                    </button>
                                    <button type="button" class="btn btn-secondary btn-sm" id="btn-limpiar-filtros">
                                        <i class="fas fa-times"></i> Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="logs-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>ID K40</th>
                                        <th>Nombre</th>
                                        <th>Apellido</th>
                                        <th>Cédula</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="logs-tbody">
                                    <!-- Los datos se cargarán dinámicamente con paginación -->
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Paginación -->
                        <nav aria-label="Paginación de registros" class="mt-3">
                            <ul class="pagination justify-content-center" id="pagination">
                                <!-- Se generará dinámicamente -->
                            </ul>
                        </nav>
                        
                        <!-- Información de resultados -->
                        <div class="mt-3">
                            <small class="text-muted" id="resultados-info">
                                Mostrando <?= count($logs_data) ?> registros cargados
                            </small>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal de Detalles del Postulante -->
    <div class="modal fade" id="modalDetallesPostulante" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user mr-2"></i>Detalles del Postulante</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-id-card mr-1"></i>Información Personal</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>ID:</strong></td>
                                    <td id="detalle_id">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Nombre:</strong></td>
                                    <td id="detalle_nombre">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Apellido:</strong></td>
                                    <td id="detalle_apellido">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Cédula:</strong></td>
                                    <td id="detalle_cedula">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Sexo:</strong></td>
                                    <td id="detalle_sexo">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Fecha Nacimiento:</strong></td>
                                    <td id="detalle_fecha_nacimiento">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Edad:</strong></td>
                                    <td id="detalle_edad">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Teléfono:</strong></td>
                                    <td id="detalle_telefono">-</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="fas fa-fingerprint mr-1"></i>Información del registro</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Dedo Registrado:</strong></td>
                                    <td id="detalle_dedo_registrado">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Aparato:</strong></td>
                                    <td id="detalle_aparato">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Unidad:</strong></td>
                                    <td id="detalle_unidad">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Registrado Por:</strong></td>
                                    <td id="detalle_registrado_por">-</td>
                                </tr>
                            </table>
                            
                            <table class="table table-sm table-borderless mt-3">
                                <tr>
                                    <td><strong>Fecha Registro:</strong></td>
                                    <td id="detalle_fecha_registro">-</td>
                                </tr>
                                <tr>
                                    <td><strong>Última Edición:</strong></td>
                                    <td id="detalle_fecha_ultima_edicion">-</td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Sección de Observaciones -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-warning"><i class="fas fa-sticky-note mr-1"></i>Observaciones</h6>
                            <div id="detalle_observaciones" class="alert alert-light border-left-warning">
                                Sin observaciones
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sección de Historial de Ediciones -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <h6 class="text-info"><i class="fas fa-history mr-1"></i>Historial de Ediciones</h6>
                            <div id="detalle_historial" class="alert alert-light border-left-info">
                                <div class="text-center text-muted">
                                    <i class="fas fa-spinner fa-spin mr-2"></i>
                                    Cargando historial...
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
    $(document).ready(function() {
        let logsData = <?= json_encode($logs_data) ?>;
        let logsDataOriginales = [...logsData];
        let currentPage = 1;
        let itemsPerPage = 10; // 10 registros por página
        
        // Funcionalidad de drag & drop
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('archivo_dat');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFileInfo(files[0]);
            }
        });
        
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                showFileInfo(e.target.files[0]);
            }
        });
        
        function showFileInfo(file) {
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            fileInfo.style.display = 'block';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Botón limpiar
        $('#btnLimpiar').click(function() {
            $('#formCargarArchivo')[0].reset();
            fileInfo.style.display = 'none';
            uploadArea.classList.remove('dragover');
        });
        
        // Event listeners para filtros
        $('#btn-filtrar').click(function() {
            aplicarFiltrosYMostrar();
        });
        
        $('#btn-limpiar-filtros').click(function() {
            limpiarFiltros();
        });
        
        // Aplicar filtros localmente
        function aplicarFiltros(logs) {
            const fechaDesde = $('#fecha_desde').val();
            const fechaHasta = $('#fecha_hasta').val();
            const filtroNombre = $('#filtro_nombre').val().toLowerCase();
            
            return logs.filter(log => {
                // Filtrar por nombre
                if (filtroNombre) {
                    const nombreCompleto = (log.postulante.nombre + ' ' + log.postulante.apellido).toLowerCase();
                    if (!nombreCompleto.includes(filtroNombre)) {
                        return false;
                    }
                }
                
                // Filtrar por fecha
                if (fechaDesde || fechaHasta) {
                    const fecha = new Date(log.fecha_hora);
                    const año = fecha.getFullYear();
                    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
                    const dia = String(fecha.getDate()).padStart(2, '0');
                    const fechaStr = `${año}-${mes}-${dia}`;
                    
                    if (fechaDesde && fechaStr < fechaDesde) {
                        return false;
                    }
                    if (fechaHasta && fechaStr > fechaHasta) {
                        return false;
                    }
                }
                
                return true;
            });
        }
        
        // Función para aplicar filtros y mostrar resultados
        function aplicarFiltrosYMostrar() {
            if (logsDataOriginales.length === 0) {
                alert('No hay datos cargados.');
                return;
            }
            
            // Aplicar filtros a todos los datos originales
            const logsFiltrados = aplicarFiltros(logsDataOriginales);
            
            // Actualizar logsData con los datos filtrados
            logsData = logsFiltrados;
            
            // Volver a la primera página cuando se aplican filtros
            currentPage = 1;
            
            // Mostrar resultados filtrados
            mostrarLogs(logsData);
        }
        
        // Mostrar logs en la tabla (con paginación)
        function mostrarLogs(logs) {
            const tbody = $('#logs-tbody');
            tbody.empty();
            
            if (logs.length === 0) {
                tbody.append('<tr><td colspan="7" class="text-center text-muted">No se encontraron registros</td></tr>');
                actualizarPaginacion();
                return;
            }
            
            // Calcular índices para la página actual
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageData = logs.slice(startIndex, endIndex);
            
            pageData.forEach(function(log) {
                const fecha = new Date(log.fecha_hora);
                const fechaFormateada = fecha.toLocaleDateString('es-PY');
                const horaFormateada = fecha.toLocaleTimeString('es-PY');
                
                const row = `
                    <tr>
                        <td>${log.uid_k40}</td>
                        <td>${log.postulante.nombre}</td>
                        <td>${log.postulante.apellido}</td>
                        <td>${log.postulante.cedula}</td>
                        <td>${fechaFormateada}</td>
                        <td>${horaFormateada}</td>
                        <td>
                            <button class="btn btn-sm btn-outline-info" 
                                    onclick="buscarEnDashboard('${log.postulante.nombre} ${log.postulante.apellido}', ${log.postulante.aparato_id || 'null'})" 
                                    title="Buscar en dashboard">
                                <i class="fas fa-external-link-alt"></i> Buscar
                            </button>
                        </td>
                    </tr>
                `;
                tbody.append(row);
            });
            
            // Actualizar paginación
            actualizarPaginacion();
            
            // Actualizar información de resultados
            const totalPages = Math.ceil(logs.length / itemsPerPage);
            $('#resultados-info').text(`Página ${currentPage} de ${totalPages} - Mostrando ${pageData.length} de ${logs.length} registros`);
        }
        
        // Limpiar filtros
        function limpiarFiltros() {
            $('#fecha_desde').val('');
            $('#fecha_hasta').val('');
            $('#filtro_nombre').val('');
            
            // Restaurar datos originales
            logsData = [...logsDataOriginales];
            
            // Volver a la primera página
            currentPage = 1;
            
            // Mostrar todos los datos
            mostrarLogs(logsData);
        }
        
        // Función para cambiar de página
        function cambiarPagina(page) {
            currentPage = page;
            mostrarLogs(logsData);
        }
        
        // Actualizar controles de paginación
        function actualizarPaginacion() {
            const totalPages = Math.ceil(logsData.length / itemsPerPage);
            const pagination = $('#pagination');
            pagination.empty();
            
            if (totalPages <= 1) return;
            
            // Botón anterior
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            pagination.append(`
                <li class="page-item ${prevDisabled}">
                    <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${currentPage - 1}); return false;">Anterior</a>
                </li>
            `);
            
            // Números de página
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const active = i === currentPage ? 'active' : '';
                pagination.append(`
                    <li class="page-item ${active}">
                        <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${i}); return false;">${i}</a>
                    </li>
                `);
            }
            
            // Botón siguiente
            const nextDisabled = currentPage === totalPages ? 'disabled' : '';
            pagination.append(`
                <li class="page-item ${nextDisabled}">
                    <a class="page-link" href="javascript:void(0)" onclick="cambiarPagina(${currentPage + 1}); return false;">Siguiente</a>
                </li>
            `);
        }
        
        // Función para buscar postulante en Dashboard
        function buscarEnDashboard(nombre, aparatoId = null) {
            try {
                // Limpiar y preparar el nombre para la búsqueda
                const nombreLimpio = nombre.trim();
                
                // Si el nombre parece truncado (termina abruptamente), usar búsqueda parcial
                const nombreParaBusqueda = nombreLimpio.length >= 35 ? nombreLimpio.substring(0, 35) : nombreLimpio;
                
                // Usar el aparato_id pasado como parámetro, o el del selector como fallback
                const dispositivoId = aparatoId || $('#dispositivo_id').val();
                
                // Crear URL con el formato correcto del inicio
                const params = new URLSearchParams({
                    'page': '1',
                    'search': nombreParaBusqueda,
                    'fecha_desde': '',
                    'fecha_hasta': '',
                    'unidad': '',
                    'aparato': dispositivoId || '', // Usar el ID del dispositivo
                    'dedo': '',
                    'per_page': '15'
                });
                
                // URL del inicio con el formato correcto
                const dashboardUrl = `dashboard.php?${params.toString()}#postulantes`;
                
                // Abrir en nueva pestaña
                window.open(dashboardUrl, '_blank');
                
                console.log('Abriendo inicio con:', {
                    nombreOriginal: nombreLimpio,
                    nombreParaBusqueda: nombreParaBusqueda,
                    aparatoId: aparatoId,
                    dispositivoId: dispositivoId,
                    url: dashboardUrl
                });
                
            } catch (error) {
                console.error('Error abriendo inicio:', error);
                alert('Error al abrir el inicio: ' + error.message);
            }
        }
        
        // Hacer funciones globales
        window.buscarEnDashboard = buscarEnDashboard;
        window.cambiarPagina = cambiarPagina;
        
        // Inicializar paginación si hay datos cargados
        if (logsData.length > 0) {
            mostrarLogs(logsData);
        }
    });
    </script>
</body>
</html>
