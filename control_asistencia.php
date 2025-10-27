<?php
/**
 * Página para control de asistencia con integración biométrica
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

session_start();
require_once 'config.php';
requireLogin();

// Verificar permisos - cualquier usuario autenticado puede ver asistencia
// (ADMIN, SUPERADMIN, USER)

$mensaje = '';
$tipo_mensaje = '';
$logs_data = [];
$device_info = null;
$estadisticas = [
    'total_registros' => 0,
    'registros_hoy' => 0,
    'usuarios_unicos' => 0
];

// Función para obtener información del dispositivo desde la base de datos
function obtener_info_dispositivo($pdo, $serial_number) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre FROM aparatos_biometricos WHERE serial = ? LIMIT 1");
        $stmt->execute([$serial_number]);
        $aparato = $stmt->fetch();
        
        if ($aparato) {
            return [$aparato['id'], $aparato['nombre']];
        } else {
            return [null, "No disponible"];
        }
    } catch (Exception $e) {
        return [null, "No disponible"];
    }
}

// No se necesitan funciones PHP adicionales ya que se usa el bridge JavaScript
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Asistencia - Sistema Quira</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.1/css/all.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="favicon.php">
    
    <style>
        .device-status {
            transition: all 0.3s ease;
        }
        .device-connected {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        .device-disconnected {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        .device-connecting {
            background-color: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
        }
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
        .stats-card {
            background: linear-gradient(135deg, #2E5090 0%, #1e3a6b 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
        }
        .loading {
            display: none;
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
                    <h1><i class="fas fa-clock"></i> Control de Asistencia</h1>
                    <div>
                        <span class="text-muted">Bienvenido: <?= htmlspecialchars($_SESSION['nombre'] . ' ' . $_SESSION['apellido']) ?></span>
                        <a href="cargar_asistencias.php" class="btn btn-outline-primary btn-sm ml-2">
                            <i class="fas fa-upload"></i> Cargar Asistencias
                        </a>
                        <button class="btn btn-outline-success btn-sm ml-2" id="btn-descargar-logs" title="Descargar logs del dispositivo" data-toggle="modal" data-target="#modalDescargarLogs">
                            <i class="fas fa-download"></i> Descargar Logs
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary btn-sm ml-2">
                            <i class="fas fa-arrow-left"></i> Volver al Inicio
                        </a>
                        <a href="logout.php" class="btn btn-outline-danger btn-sm ml-2">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                    </div>
                </div>
                
                <!-- Estado del dispositivo -->
                <div class="alert device-status device-connecting" id="device-status">
                    <i class="fas fa-fingerprint"></i> 
                    <span id="device-status-text">Conectando al dispositivo biométrico...</span>
                    <div class="mt-2">
                        <small id="device-name"></small>
                    </div>
                </div>
                
                <!-- Mensajes del sistema -->
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <!-- Controles de Búsqueda -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-search"></i> Buscar Registros de Asistencia</h5>
                    </div>
                    <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="form-group">
                                    <label for="tipo_busqueda"><i class="fas fa-sort-amount-down"></i> Tipo</label>
                                    <select class="form-control" id="tipo_busqueda">
                                        <option value="ultimos">Últimos registros</option>
                                        <option value="primeros">Primeros registros</option>
                                    </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                    <label for="cantidad_registros"><i class="fas fa-list-ol"></i> Cantidad</label>
                                    <input type="number" class="form-control" id="cantidad_registros" 
                                           placeholder="Ej: 100" value="50" min="1" max="10000">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>&nbsp;</label>
                                        <div class="d-flex">
                                        <button type="button" class="btn btn-primary" id="buscar-logs-btn">
                                                <i class="fas fa-search"></i> Buscar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                    </div>
                </div>
                
                
                <!-- Tabla de resultados -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-table"></i> Registros de Asistencia</h5>
                        <div class="loading" id="loading">
                            <i class="fas fa-spinner fa-spin"></i> Cargando...
                        </div>
                    </div>
                    <div class="card-body">
            <!-- Información de logs cargados -->
            <div class="row mb-2">
                <div class="col-md-4">
                    <div class="alert alert-info mb-1 py-2">
                        <i class="fas fa-database"></i> <strong>Logs cargados:</strong> <span id="total-logs-cargados">0</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-success mb-1 py-2">
                        <i class="fas fa-calendar-day"></i> <strong>Logs de hoy:</strong> <span id="logs-hoy">0</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning mb-1 py-2">
                        <i class="fas fa-users"></i> <strong>Usuarios únicos hoy:</strong> <span id="usuarios-unicos-hoy">0</span>
                    </div>
                </div>
            </div>
                        
            <!-- Filtros -->
            <div class="row mb-2">
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label for="filtro_nombre" class="mb-1"><i class="fas fa-user"></i> Filtrar por nombre</label>
                        <input type="text" class="form-control form-control-sm" id="filtro_nombre"
                               placeholder="Nombre del postulante">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label for="fecha_desde" class="mb-1"><i class="fas fa-calendar"></i> Fecha desde</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_desde">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label for="fecha_hasta" class="mb-1"><i class="fas fa-calendar"></i> Fecha hasta</label>
                        <input type="date" class="form-control form-control-sm" id="fecha_hasta">
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-2">
                        <label class="mb-1">&nbsp;</label>
                        <div class="btn-group btn-block" role="group">
                            <button type="button" class="btn btn-primary btn-sm" id="btn-filtrar">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <button type="button" class="btn btn-secondary btn-sm" id="btn-limpiar-filtros">
                                <i class="fas fa-times"></i> Limpiar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover" id="logs-table">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Fecha</th>
                                        <th>Hora</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="logs-tbody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Configure los parámetros de búsqueda y haga clic en "Buscar Logs" para cargar los registros
                                        </td>
                                    </tr>
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
                                No hay registros para mostrar
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Advertencia Experimental -->
    <div class="modal fade" id="modalAdvertenciaExperimental" tabindex="-1" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle mr-2"></i>Función Experimental</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-info-circle mr-2"></i>Control de Asistencia - Versión Experimental</h6>
                        <p class="mb-2">Esta función está en fase experimental y puede presentar los siguientes comportamientos:</p>
                        <ul class="mb-3">
                            <li><strong>Tiempo de carga variable:</strong> Dependiendo de la versión del dispositivo biométrico, la carga puede tomar desde segundos hasta varios minutos</li>
                            <li><strong>Dispositivos antiguos:</strong> Pueden requerir más tiempo para procesar los registros</li>
                            <li><strong>Dispositivos modernos:</strong> Generalmente responden más rápido</li>
                        </ul>
                        <p class="mb-0"><strong>Recomendación:</strong> Sea paciente durante la carga inicial. Una vez cargados, la navegación será fluida.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-warning" data-dismiss="modal">
                        <i class="fas fa-check mr-1"></i> Entendido, continuar
                    </button>
                </div>
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
    
    <!-- Modal de Opciones de Descarga -->
    <div class="modal fade" id="modalDescargarLogs" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-download mr-2"></i>Descargar Logs de Asistencia</h5>
                    <button type="button" class="close" data-dismiss="modal">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="mb-3">Seleccione qué registros desea descargar:</p>
                    
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoDescarga" id="descargaHoy" value="hoy" checked>
                            <label class="form-check-label" for="descargaHoy">
                                <strong><i class="fas fa-calendar-day text-success mr-1"></i>Registros de HOY</strong>
                                <small class="d-block text-muted">Solo los registros del día actual</small>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoDescarga" id="descargaFecha" value="fecha">
                            <label class="form-check-label" for="descargaFecha">
                                <strong><i class="fas fa-calendar text-info mr-1"></i>Registros de UNA FECHA</strong>
                                <small class="d-block text-muted">Seleccione una fecha específica</small>
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="tipoDescarga" id="descargaTodos" value="todos">
                            <label class="form-check-label" for="descargaTodos">
                                <strong><i class="fas fa-database text-warning mr-1"></i>TODOS los registros</strong>
                                <small class="d-block text-muted">Todos los registros del dispositivo (puede tomar tiempo)</small>
                            </label>
                        </div>
                    </div>
                    
                    <!-- Campo de fecha (oculto por defecto) -->
                    <div class="form-group" id="campoFecha" style="display: none;">
                        <label for="fechaDescarga"><i class="fas fa-calendar-alt"></i> Seleccionar fecha:</label>
                        <input type="date" class="form-control" id="fechaDescarga" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <!-- Información del dispositivo -->
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle mr-2"></i>
                        <strong>Dispositivo:</strong> <span id="infoDispositivo">Conectando...</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn-confirmar-descarga">
                        <i class="fas fa-download"></i> Descargar
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.4.0.slim.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="assets/js/zkteco-bridge.js"></script>
    
    <script>
    $(document).ready(function() {
        // Variables globales
        let logsData = [];
        let logsDataOriginales = []; // Para guardar los datos originales antes de filtrar
        let deviceConnected = false;
        let zktecoBridge = null;
        let currentPage = 1;
        let itemsPerPage = 10; // Cambiar a 10 por página
        let sortOrder = 'recientes';
        let totalRecords = 0; // Total de registros en el dispositivo
        let loadedPages = new Set(); // Páginas ya cargadas
        let currentDeviceInfo = null; // Información del dispositivo actual
        
        // Mostrar modal de advertencia al cargar la página
        $('#modalAdvertenciaExperimental').modal('show');
        
        // Inicializar
        initializeBridge();
        
        // Event listeners
        // Event handlers eliminados - controles simplificados
        
        
        $('#buscar-logs-btn').click(function() {
            buscarLogsConParametros();
        });
        
        // Event listeners para el modal de descarga
        $('input[name="tipoDescarga"]').change(function() {
            if ($(this).val() === 'fecha') {
                $('#campoFecha').show();
            } else {
                $('#campoFecha').hide();
            }
        });
        
        $('#btn-confirmar-descarga').click(function() {
            const tipoDescarga = $('input[name="tipoDescarga"]:checked').val();
            const fechaSeleccionada = $('#fechaDescarga').val();
            
            $('#modalDescargarLogs').modal('hide');
            descargarLogsDispositivo(tipoDescarga, fechaSeleccionada);
        });
        
        // Event listeners simplificados
        
        // Buscar al presionar Enter en los campos
        $('#cantidad_registros').keypress(function(e) {
            if (e.which === 13) {
                buscarLogsConParametros();
            }
        });
        
        // Event listener para botón filtrar
        $('#btn-filtrar').click(function() {
            aplicarFiltrosYMostrar();
        });
        
        // Event listener para botón limpiar filtros
        $('#btn-limpiar-filtros').click(function() {
            limpiarFiltros();
        });
        
        // Inicializar bridge ZKTeco
        async function initializeBridge() {
            try {
                // Crear instancia del bridge
                zktecoBridge = createZKTecoBridge({
                    wsUrl: 'ws://localhost:8001/ws/zkteco',
                    httpUrl: 'http://localhost:8001'
                });
                
                // Configurar handlers
                zktecoBridge.onConnect(() => {
                    console.log('Conectado al bridge ZKTeco');
                    updateDeviceStatus('Conectando al dispositivo biométrico...', 'connecting');
                });
                
                zktecoBridge.onDisconnect(() => {
                    console.log('Desconectado del bridge ZKTeco');
                    updateDeviceStatus('Desconectado del bridge ZKTeco', 'disconnected');
                    deviceConnected = false;
                });
                
                zktecoBridge.onError((error) => {
                    console.error('Error en bridge ZKTeco:', error);
                    updateDeviceStatus('Error en la conexión: ' + error, 'disconnected');
                    deviceConnected = false;
                });
                
                // Conectar al bridge
                await zktecoBridge.connect();
                
                // Conectar al dispositivo
                const connected = await zktecoBridge.connectToDevice();
                
                if (connected) {
                    deviceConnected = true;
                    updateDeviceStatus('Conectado al dispositivo biométrico', 'connected');
                    
                    // Obtener información del dispositivo
                    try {
                        const deviceInfo = await zktecoBridge.getDeviceInfo();
                        if (deviceInfo && deviceInfo.serial_number) {
                            // Obtener el nombre real del dispositivo desde la base de datos
                            try {
                                const response = await fetch(`obtener_aparato_por_serial.php?serial=${encodeURIComponent(deviceInfo.serial_number)}`);
                                const data = await response.json();
                                if (data.success && data.device_name) {
                                    $('#device-name').text(`Dispositivo: ${data.device_name}`);
                                    $('#infoDispositivo').text(`${data.device_name} (${deviceInfo.serial_number})`);
                                    // Guardar información del dispositivo para uso posterior
                                    currentDeviceInfo = {
                                        name: data.device_name,
                                        serial: deviceInfo.serial_number,
                                        id: data.aparato ? data.aparato.id : null
                                    };
                                    
                                    console.log('Información del dispositivo guardada:', currentDeviceInfo);
                                } else {
                                    $('#device-name').text(`Dispositivo: ${deviceInfo.serial_number}`);
                                    $('#infoDispositivo').text(deviceInfo.serial_number);
                                    currentDeviceInfo = {
                                        name: deviceInfo.serial_number,
                                        serial: deviceInfo.serial_number,
                                        id: null
                                    };
                                }
                            } catch (e) {
                                console.log('No se pudo obtener el nombre del dispositivo desde la BD');
                                $('#device-name').text(`Dispositivo: ${deviceInfo.serial_number}`);
                                $('#infoDispositivo').text(deviceInfo.serial_number);
                                currentDeviceInfo = {
                                    name: deviceInfo.serial_number,
                                    serial: deviceInfo.serial_number,
                                    id: null
                                };
                            }
                        }
                    } catch (e) {
                        console.log('No se pudo obtener información del dispositivo');
                        currentDeviceInfo = null;
                        $('#infoDispositivo').text('No disponible');
                    }
                    
                    // No cargar logs automáticamente - el usuario debe hacer clic en "Buscar Logs"
                } else {
                    updateDeviceStatus('Error: No se pudo conectar al dispositivo', 'disconnected');
                    deviceConnected = false;
                }
                
            } catch (error) {
                console.error('Error inicializando bridge:', error);
                updateDeviceStatus('Error: No se pudo conectar al bridge ZKTeco', 'disconnected');
                deviceConnected = false;
            }
        }
        
        // Actualizar estado del dispositivo
        function updateDeviceStatus(message, status) {
            const statusElement = $('#device-status');
            const textElement = $('#device-status-text');
            
            textElement.text(message);
            statusElement.removeClass('device-connected device-disconnected device-connecting');
            
            switch (status) {
                case 'connected':
                    statusElement.addClass('device-connected');
                    break;
                case 'disconnected':
                    statusElement.addClass('device-disconnected');
                    break;
                case 'connecting':
                    statusElement.addClass('device-connecting');
                    break;
            }
        }
        
        // Función para buscar logs con parámetros específicos
        async function buscarLogsConParametros() {
            if (!deviceConnected) {
                alert('No hay conexión con el dispositivo. Conecte primero.');
                return;
            }
            
            const tipoBusqueda = $('#tipo_busqueda').val();
            const cantidad = parseInt($('#cantidad_registros').val()) || 50;
            
            if (cantidad < 1 || cantidad > 10000) {
                alert('La cantidad debe estar entre 1 y 10,000 registros');
                return;
            }
            
            $('#loading').show();
            $('#loading').html(`<i class="fas fa-spinner fa-spin"></i> Buscando ${cantidad} ${tipoBusqueda === 'ultimos' ? 'últimos' : 'primeros'} registros...`);
            
            try {
                // Calcular offset basado en el tipo de búsqueda
                let offset = 0;
                if (tipoBusqueda === 'ultimos') {
                    // Para los últimos, necesitamos obtener el total primero
                    const totalResponse = await zktecoBridge.getAttendanceLogs(1, 0);
                    if (totalResponse && totalResponse.total) {
                        offset = Math.max(0, totalResponse.total - cantidad);
                    }
                }
                
                const response = await zktecoBridge.getAttendanceLogs(cantidad, offset);
                
                if (response && response.logs) {
                    totalRecords = response.total || response.logs.length;
                    
                    // Cargar logs en memoria
                    logsData = response.logs;
                    logsDataOriginales = [...response.logs]; // Guardar copia de los datos originales
                    
                    // Aplicar ordenamiento según el tipo de búsqueda PRIMERO
                    aplicarOrdenamiento();
                    
                    // Aplicar filtros localmente DESPUÉS del ordenamiento
                    const logsFiltrados = aplicarFiltros(logsData);
                    
                    // Mostrar primera página
                    mostrarPagina(1);
                    
                    // Actualizar estadísticas de logs
                    actualizarEstadisticasLogs();
                    
                    $('#resultados-info').text(`Mostrando ${logsFiltrados.length} de ${response.logs.length} registros (${tipoBusqueda === 'ultimos' ? 'últimos' : 'primeros'} ${cantidad})`);
                    
                    // Scroll automático al título "Registros de Asistencia" (el de abajo, no el de buscar)
                    const titulo = $('.card:last h5:contains("Registros de Asistencia")')[0];
                    if (titulo) {
                        titulo.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                } else {
                    $('#logs-tbody').html('<tr><td colspan="4" class="text-center text-muted">No se encontraron registros</td></tr>');
                    $('#resultados-info').text('No hay registros disponibles');
                }
                
            } catch (error) {
                console.error('Error buscando logs:', error);
                $('#logs-tbody').html('<tr><td colspan="4" class="text-center text-danger">Error al cargar registros: ' + error.message + '</td></tr>');
                $('#resultados-info').text('Error al cargar registros');
            } finally {
                $('#loading').hide();
            }
        }
        
        // Función buscarLogs() eliminada - usar buscarLogsConParametros() en su lugar
        
        // Función para descargar logs del dispositivo en formato .dat
        async function descargarLogsDispositivo(tipoDescarga = 'hoy', fechaSeleccionada = null) {
            if (!deviceConnected) {
                alert('No hay conexión con el dispositivo. Conecte primero.');
                return;
            }
            
            // Determinar mensaje de confirmación según el tipo
            let mensajeConfirmacion = '';
            switch(tipoDescarga) {
                case 'hoy':
                    mensajeConfirmacion = '¿Descargar los registros de asistencia de HOY?';
                    break;
                case 'fecha':
                    // Formatear fecha correctamente para mostrar
                    const fechaObj = new Date(fechaSeleccionada + 'T00:00:00'); // Evitar problemas de zona horaria
                    const dia = String(fechaObj.getDate()).padStart(2, '0');
                    const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
                    const año = fechaObj.getFullYear();
                    const fechaFormateada = `${dia}/${mes}/${año}`;
                    mensajeConfirmacion = `¿Descargar los registros de asistencia del ${fechaFormateada}?`;
                    break;
                case 'todos':
                    mensajeConfirmacion = '¿Descargar TODOS los logs de asistencia del dispositivo?\n\nEsto puede tomar varios minutos si hay muchos registros.';
                    break;
            }
            
            if (!confirm(mensajeConfirmacion)) {
                return;
            }
            
            try {
                // Mostrar loading
                const btnOriginal = $('#btn-descargar-logs').html();
                $('#btn-descargar-logs').html('<i class="fas fa-spinner fa-spin"></i> Descargando...').prop('disabled', true);
                
                // Obtener logs del dispositivo según el tipo
                let response;
                if (tipoDescarga === 'todos') {
                    response = await zktecoBridge.getAttendanceLogs(10000, 0); // Máximo 10,000 registros
                } else {
                    response = await zktecoBridge.getAttendanceLogs(10000, 0); // Obtener todos para filtrar
                }
                
                if (response && response.logs && response.logs.length > 0) {
                    // Filtrar logs según el tipo de descarga
                    let logsFiltrados = response.logs;
                    
                    if (tipoDescarga === 'hoy') {
                        const hoy = new Date();
                        const hoyStr = hoy.toLocaleDateString('es-PY', { timeZone: 'America/Asuncion' });
                        logsFiltrados = response.logs.filter(log => {
                            const fechaLog = new Date(log.timestamp * 1000);
                            const fechaLogStr = fechaLog.toLocaleDateString('es-PY', { timeZone: 'America/Asuncion' });
                            return fechaLogStr === hoyStr;
                        });
                    } else if (tipoDescarga === 'fecha') {
                        // Crear fecha de referencia sin problemas de zona horaria
                        const fechaSeleccionadaObj = new Date(fechaSeleccionada + 'T00:00:00');
                        const diaSeleccionado = fechaSeleccionadaObj.getDate();
                        const mesSeleccionado = fechaSeleccionadaObj.getMonth();
                        const añoSeleccionado = fechaSeleccionadaObj.getFullYear();
                        
                        logsFiltrados = response.logs.filter(log => {
                            const fechaLog = new Date(log.timestamp * 1000);
                            // Usar zona horaria de Paraguay para comparar
                            const fechaLogPY = new Date(fechaLog.toLocaleString('en-US', { timeZone: 'America/Asuncion' }));
                            return fechaLogPY.getDate() === diaSeleccionado && 
                                   fechaLogPY.getMonth() === mesSeleccionado && 
                                   fechaLogPY.getFullYear() === añoSeleccionado;
                        });
                    }
                    
                    if (logsFiltrados.length === 0) {
                        alert('No se encontraron registros para la fecha seleccionada.');
                        $('#btn-descargar-logs').html(btnOriginal).prop('disabled', false);
                        return;
                    }
                    
                    // Generar contenido del archivo .dat
                    let contenidoDat = '';
                    logsFiltrados.forEach(log => {
                        // Formato: uid_k40\tfecha_hora\t12\t0\t0\t0
                        const fechaHora = new Date(log.timestamp * 1000);
                        // Usar zona horaria de Paraguay (America/Asuncion)
                        const fechaFormateada = fechaHora.toLocaleString('sv-SE', {
                            timeZone: 'America/Asuncion',
                            year: 'numeric',
                            month: '2-digit',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit',
                            second: '2-digit'
                        }).replace(',', '');
                        contenidoDat += `${log.user_id}\t${fechaFormateada}\t12\t0\t0\t0\n`;
                    });
                    
                    // Crear y descargar archivo
                    const blob = new Blob([contenidoDat], { type: 'text/plain' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    
                    // Generar nombre del archivo según el tipo de descarga
                    let nombreArchivo = '';
                    const serial = currentDeviceInfo ? currentDeviceInfo.serial : 'UNKNOWN';
                    
                    if (tipoDescarga === 'hoy') {
                        // Para HOY: Asist_ddmmaaaa_SERIAL
                        const hoy = new Date();
                        const dia = String(hoy.getDate()).padStart(2, '0');
                        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
                        const año = hoy.getFullYear();
                        nombreArchivo = `Asist_${dia}${mes}${año}_${serial}.dat`;
                    } else if (tipoDescarga === 'fecha') {
                        // Para fecha específica: Asist_ddmmaaaa_SERIAL
                        const fechaObj = new Date(fechaSeleccionada + 'T00:00:00');
                        const dia = String(fechaObj.getDate()).padStart(2, '0');
                        const mes = String(fechaObj.getMonth() + 1).padStart(2, '0');
                        const año = fechaObj.getFullYear();
                        nombreArchivo = `Asist_${dia}${mes}${año}_${serial}.dat`;
                    } else if (tipoDescarga === 'todos') {
                        // Para TODOS: Asist_TODOS_SERIAL
                        nombreArchivo = `Asist_TODOS_${serial}.dat`;
                    }
                    
                    a.download = nombreArchivo;
                    
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    window.URL.revokeObjectURL(url);
                    
                    // Restaurar botón antes del alert
                    $('#btn-descargar-logs').html(btnOriginal).prop('disabled', false);
                    
                    // Pequeño delay para asegurar que se vea el cambio
                    setTimeout(() => {
                        alert(`Descarga completada!\n\nArchivo: ${nombreArchivo}\nRegistros: ${logsFiltrados.length}`);
                    }, 100);
                    
                } else {
                    // Restaurar botón antes del alert
                    $('#btn-descargar-logs').html(btnOriginal).prop('disabled', false);
                    
                    setTimeout(() => {
                        alert('No se encontraron registros de asistencia en el dispositivo.');
                    }, 100);
                }
                
            } catch (error) {
                console.error('Error descargando logs:', error);
                
                // Restaurar botón antes del alert
                $('#btn-descargar-logs').html(btnOriginal).prop('disabled', false);
                
                setTimeout(() => {
                    alert('Error al descargar logs: ' + error.message);
                }, 100);
            }
        }
        
        // Función para cargar una página específica
        // Función simplificada para cargar una página específica
        function cargarPagina(page) {
            // Con el nuevo sistema, todos los datos ya están cargados en logsData
            // Solo necesitamos mostrar la página correspondiente
            mostrarPagina(page);
            
            // Mantener el scroll en la posición del título "Registros de Asistencia" (el de abajo, no el de buscar)
            const titulo = $('.card:last h5:contains("Registros de Asistencia")')[0];
            if (titulo) {
                titulo.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        // Función para cargar más registros (mantener compatibilidad)
        async function cargarMasRegistros() {
            if (!deviceConnected || !zktecoBridge) {
                alert('No hay conexión con el dispositivo');
                return;
            }
            
            try {
                // Mostrar loading
                $('#resultados-info').html('<i class="fas fa-spinner fa-spin"></i> Cargando más registros...');
                
                // Cargar más registros (desde el offset actual)
                const currentOffset = logsData.length;
                const response = await zktecoBridge.getAttendanceLogs(30, currentOffset); // Cargar 30 más desde el offset actual
                
                if (response && response.logs && response.logs.length > 0) {
                    // Agregar nuevos registros a los existentes
                    logsData = logsData.concat(response.logs);
                    
                    // Aplicar filtros a todos los registros
                    const logsFiltrados = aplicarFiltros(logsData);
                    
                    // Aplicar ordenamiento
                    aplicarOrdenamiento();
                    
                    // Mostrar primera página
                    mostrarPagina(1);
                    
                    // Actualizar información
                    const totalCargados = logsData.length;
                    const totalDisponibles = response.total;
                    
                    if (totalCargados >= totalDisponibles) {
                        $('#resultados-info').text(`Mostrando ${logsFiltrados.length} de ${totalCargados} registros del K40 (todos cargados)`);
                    } else {
                        $('#resultados-info').text(`Mostrando ${logsFiltrados.length} de ${totalCargados} registros del K40`);
                        $('#resultados-info').append(` <button class="btn btn-sm btn-outline-primary ms-2" onclick="cargarMasRegistros()">
                            <i class="fas fa-plus"></i> Cargar más (${totalDisponibles - totalCargados} restantes)
                        </button>`);
                    }
                } else {
                    $('#resultados-info').text('No hay más registros disponibles');
                }
                
            } catch (error) {
                console.error('Error cargando más registros:', error);
                $('#resultados-info').text('Error al cargar más registros');
                alert(`Error al cargar más registros: ${error.message}`);
            }
        }
        
        // Función para aplicar filtros y mostrar resultados
        function aplicarFiltrosYMostrar() {
            if (logsDataOriginales.length === 0) {
                alert('No hay datos cargados. Espere a que se complete la carga inicial.');
                return;
            }
            
            // Aplicar filtros a todos los datos originales
            const logsFiltrados = aplicarFiltros(logsDataOriginales);
            
            // Actualizar logsData con los datos filtrados
            logsData = logsFiltrados;
            
            // Aplicar ordenamiento
            aplicarOrdenamiento();
            
            // Mostrar primera página de resultados filtrados
            mostrarPagina(1);
            
            // Actualizar estadísticas de logs
            actualizarEstadisticasLogs();
            
            // Actualizar información
            $('#resultados-info').text(`Mostrando ${logsFiltrados.length} de ${logsDataOriginales.length} registros (filtrados)`);
        }
        
        // Aplicar filtros localmente
        function aplicarFiltros(logs) {
            const fechaDesde = $('#fecha_desde').val();
            const fechaHasta = $('#fecha_hasta').val();
            const filtroNombre = $('#filtro_nombre').val().toLowerCase();
            
            return logs.filter(log => {
                // Filtrar por nombre
                if (filtroNombre && !log.name.toLowerCase().includes(filtroNombre)) {
                    return false;
                }
                
                // Filtrar por fecha
                if (fechaDesde || fechaHasta) {
                    const timestamp = log.timestamp;
                    if (timestamp) {
                        const fecha = new Date(timestamp * 1000);
                        // Usar métodos locales para evitar problemas de zona horaria
                        const año = fecha.getFullYear();
                        const mes = String(fecha.getMonth() + 1).padStart(2, '0');
                        const dia = String(fecha.getDate()).padStart(2, '0');
                        const fechaStr = `${año}-${mes}-${dia}`; // Formato AAAA-MM-DD
                        
                        if (fechaDesde && fechaStr < fechaDesde) {
                            return false;
                        }
                        if (fechaHasta && fechaStr > fechaHasta) {
                            return false;
                        }
                    }
                }
                
                return true;
            });
        }
        
        // Mostrar logs en la tabla
        function mostrarLogs(logs) {
            const tbody = $('#logs-tbody');
            tbody.empty();
            
            if (logs.length === 0) {
                tbody.append('<tr><td colspan="4" class="text-center text-muted">No se encontraron registros</td></tr>');
                return;
            }
            
            logs.forEach(function(log) {
                const fecha = formatearFecha(log.timestamp);
                const hora = formatearHora(log.timestamp);
                const nombre = log.name || 'Sin nombre';
                
                // Botón de búsqueda en BD
                const botonBuscar = nombre !== 'Sin nombre' ? 
                    `<button class="btn btn-sm btn-outline-info" onclick="buscarEnDashboard('${nombre.replace(/'/g, "\\'")}')" title="Buscar en dashboard">
                        <i class="fas fa-external-link-alt"></i> Buscar
                    </button>` : 
                    '<span class="text-muted">Sin nombre</span>';
                
                const row = `
                    <tr>
                        <td>${nombre}</td>
                        <td>${fecha}</td>
                        <td>${hora}</td>
                        <td>${botonBuscar}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
        
        // Función para buscar postulante en Inicio
        function buscarEnDashboard(nombre) {
            try {
                // Limpiar y preparar el nombre para la búsqueda
                const nombreLimpio = nombre.trim();
                
                // Si el nombre parece truncado (termina abruptamente), usar búsqueda parcial
                const nombreParaBusqueda = nombreLimpio.length >= 35 ? nombreLimpio.substring(0, 35) : nombreLimpio;
                
                // Obtener información del dispositivo actual
                const deviceName = currentDeviceInfo ? currentDeviceInfo.name : 'Dispositivo ZKTeco';
                const deviceId = currentDeviceInfo ? currentDeviceInfo.id : '';
                
                console.log('Información del dispositivo para búsqueda:', {
                    currentDeviceInfo: currentDeviceInfo,
                    deviceName: deviceName,
                    deviceId: deviceId
                });
                
                // Crear URL con el formato correcto del inicio
                const params = new URLSearchParams({
                    'page': '1',
                    'search': nombreParaBusqueda,
                    'fecha_desde': '',
                    'fecha_hasta': '',
                    'unidad': '',
                    'aparato': deviceId || '', // ID del aparato si está disponible
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
                    dispositivo: deviceName,
                    deviceId: deviceId,
                    url: dashboardUrl
                });
                
            } catch (error) {
                console.error('Error abriendo inicio:', error);
                alert('Error al abrir el inicio: ' + error.message);
            }
        }
        
        // Función para buscar postulante en BD (mantener para compatibilidad)
        async function buscarEnBD(nombre) {
            if (!deviceConnected || !zktecoBridge) {
                alert('No hay conexión con el dispositivo');
                return;
            }
            
            try {
                // Mostrar loading
                const loadingHtml = `
                    <div class="text-center">
                        <i class="fas fa-spinner fa-spin"></i> Buscando...
                    </div>
                `;
                
                // Buscar postulante
                const result = await zktecoBridge.searchPostulante(nombre);
                
                if (result.success && result.postulantes.length > 0) {
                    if (result.postulantes.length === 1) {
                        // Si hay solo uno, mostrar directamente
                        const postulante = result.postulantes[0];
                        mostrarDetallesPostulante(postulante);
                    } else {
                        // Si hay múltiples, mostrar selector
                        mostrarSelectorPostulantes(result.postulantes, nombre);
                    }
                } else {
                    alert(`No se encontraron postulantes para "${nombre}"`);
                }
                
            } catch (error) {
                console.error('Error buscando postulante:', error);
                alert(`Error al buscar postulante: ${error.message}`);
            }
        }
        
        // Función para mostrar selector de postulantes cuando hay múltiples resultados
        function mostrarSelectorPostulantes(postulantes, nombreBuscado) {
            let optionsHtml = '';
            postulantes.forEach((postulante, index) => {
                optionsHtml += `
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="postulanteSeleccionado" id="postulante_${index}" value="${index}">
                        <label class="form-check-label" for="postulante_${index}">
                            <strong>${postulante.nombre_completo}</strong><br>
                            <small class="text-muted">CI: ${postulante.cedula} | Aparato: ${postulante.aparato_nombre || 'N/A'}</small>
                        </label>
                    </div>
                `;
            });
            
            const modalHtml = `
                <div class="modal fade" id="modalSelectorPostulantes" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Seleccionar Postulante</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <p>Se encontraron múltiples postulantes para "<strong>${nombreBuscado}</strong>":</p>
                                <form id="formSelectorPostulantes">
                                    ${optionsHtml}
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" onclick="seleccionarPostulante()">Ver Detalles</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remover modal anterior si existe
            $('#modalSelectorPostulantes').remove();
            
            // Agregar nuevo modal
            $('body').append(modalHtml);
            
            // Mostrar modal
            $('#modalSelectorPostulantes').modal('show');
            
            // Guardar postulantes para uso posterior
            window.postulantesEncontrados = postulantes;
        }
        
        // Función para seleccionar postulante del selector
        function seleccionarPostulante() {
            const selectedIndex = $('input[name="postulanteSeleccionado"]:checked').val();
            
            if (selectedIndex === undefined) {
                alert('Por favor seleccione un postulante');
                return;
            }
            
            const postulante = window.postulantesEncontrados[selectedIndex];
            
            // Cerrar modal selector
            $('#modalSelectorPostulantes').modal('hide');
            
            // Mostrar detalles del postulante seleccionado
            mostrarDetallesPostulante(postulante);
        }
        
        
        
        // Formatear fecha
        function formatearFecha(timestamp) {
            if (!timestamp) return 'N/A';
            
            const date = new Date(timestamp * 1000);
            return date.toLocaleDateString('es-PY');
        }
        
        // Formatear hora
        function formatearHora(timestamp) {
            if (!timestamp) return 'N/A';
            
            const date = new Date(timestamp * 1000);
            return date.toLocaleTimeString('es-PY');
        }
        
        // Actualizar estadísticas de logs
        function actualizarEstadisticasLogs() {
            if (logsDataOriginales.length === 0) {
                $('#total-logs-cargados').text('0');
                $('#logs-hoy').text('0');
                $('#usuarios-unicos-hoy').text('0');
                return;
            }
            
            // Total de logs cargados (usar datos originales)
            $('#total-logs-cargados').text(logsDataOriginales.length);
            
            // Logs de hoy y usuarios únicos de hoy (usar datos originales)
            const hoy = new Date().toLocaleDateString('es-PY');
            let logsHoy = 0;
            const usuariosUnicosHoy = new Set();
            
            logsDataOriginales.forEach(log => {
                if (log.timestamp) {
                    const fecha = new Date(log.timestamp * 1000);
                    const fechaLog = fecha.toLocaleDateString('es-PY');
                    
                    if (fechaLog === hoy) {
                        logsHoy++;
                        // Agregar usuario único de hoy
                        if (log.user_id) {
                            usuariosUnicosHoy.add(log.user_id);
                        }
                    }
                }
            });
            
            $('#logs-hoy').text(logsHoy);
            $('#usuarios-unicos-hoy').text(usuariosUnicosHoy.size);
        }
        
        // Limpiar filtros
        function limpiarFiltros() {
            $('#fecha_desde').val('');
            $('#fecha_hasta').val('');
            $('#filtro_nombre').val('');
            
            // Restaurar datos originales
            logsData = [...logsDataOriginales];
            
            // Aplicar ordenamiento
            aplicarOrdenamiento();
            
            // Mostrar primera página
            mostrarPagina(1);
            
            // Actualizar estadísticas
            actualizarEstadisticasLogs();
            
            // Actualizar información
            $('#resultados-info').text(`Mostrando ${logsData.length} registros`);
        }
        
        
        // Función cargarMasLogs() eliminada - usar buscarLogsConParametros() con más cantidad
        
        // Función de ordenamiento
        function aplicarOrdenamiento() {
            // Obtener el tipo de búsqueda actual
            const tipoBusqueda = $('#tipo_busqueda').val();
            
            console.log('Aplicando ordenamiento - Tipo:', tipoBusqueda, 'SortOrder:', sortOrder);
            
            // Priorizar el tipo de búsqueda sobre sortOrder
            if (tipoBusqueda === 'ultimos') {
                // Últimos registros: más recientes primero
                logsData.sort((a, b) => b.timestamp - a.timestamp);
                console.log('Ordenamiento: Más recientes primero (últimos registros)');
            } else if (tipoBusqueda === 'primeros') {
                // Primeros registros: más antiguos primero
                logsData.sort((a, b) => a.timestamp - b.timestamp);
                console.log('Ordenamiento: Más antiguos primero (primeros registros)');
                    } else {
                // Fallback a sortOrder si no hay tipo de búsqueda específico
            if (sortOrder === 'recientes') {
                logsData.sort((a, b) => b.timestamp - a.timestamp);
                    console.log('Ordenamiento: Más recientes primero (fallback)');
            } else {
                logsData.sort((a, b) => a.timestamp - b.timestamp);
                    console.log('Ordenamiento: Más antiguos primero (fallback)');
                }
            }
        }
        
        // Función de paginación
        function mostrarPagina(page) {
            currentPage = page;
            const startIndex = (page - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            
            // Obtener datos de la página actual
            const pageData = [];
            for (let i = startIndex; i < endIndex && i < logsData.length; i++) {
                if (logsData[i]) {
                    pageData.push(logsData[i]);
                }
            }
            
            mostrarLogs(pageData);
            actualizarPaginacion();
            
            // Actualizar información de resultados
            const totalPages = Math.ceil(logsData.length / itemsPerPage);
            $('#resultados-info').text(`Página ${page} de ${totalPages} - Mostrando ${pageData.length} de ${logsData.length} registros`);
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
                    <a class="page-link" href="javascript:void(0)" onclick="cargarPagina(${currentPage - 1}); return false;">Anterior</a>
                </li>
            `);
            
            // Números de página
            const startPage = Math.max(1, currentPage - 2);
            const endPage = Math.min(totalPages, currentPage + 2);
            
            for (let i = startPage; i <= endPage; i++) {
                const active = i === currentPage ? 'active' : '';
                pagination.append(`
                    <li class="page-item ${active}">
                        <a class="page-link" href="javascript:void(0)" onclick="cargarPagina(${i}); return false;">${i}</a>
                    </li>
                `);
            }
            
            // Botón siguiente
            const nextDisabled = currentPage === totalPages ? 'disabled' : '';
            pagination.append(`
                <li class="page-item ${nextDisabled}">
                    <a class="page-link" href="javascript:void(0)" onclick="cargarPagina(${currentPage + 1}); return false;">Siguiente</a>
                </li>
            `);
        }
        
        // Función para ver perfil del postulante
        function verPerfil(cedula) {
            // Buscar datos del postulante y mostrar modal
            buscarDatosPostulante(cedula);
        }
        
        // Función para buscar datos del postulante
        async function buscarDatosPostulante(cedula) {
            try {
                // Mostrar indicador de carga en el modal
                $('#modalDetallesPostulante .modal-body').html(`
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                        <p class="mt-2">Buscando datos del postulante...</p>
                    </div>
                `);
                $('#modalDetallesPostulante').modal('show');
                
                // Hacer petición AJAX para buscar datos del postulante
                console.log('Buscando postulante con CI:', cedula);
                const response = await fetch(`buscar_postulante_ajax.php?cedula=${encodeURIComponent(cedula)}`);
                console.log('Respuesta recibida:', response.status);
                const data = await response.json();
                console.log('Datos parseados:', data);
                
                if (data.success && data.postulante) {
                    // Mostrar datos del postulante
                    console.log('Postulante encontrado:', data.postulante);
                    try {
                        // Restaurar el contenido original del modal antes de llenarlo
                        restaurarContenidoModal();
                    mostrarDetallesPostulante(data.postulante);
                        console.log('mostrarDetallesPostulante completado exitosamente');
                    } catch (error) {
                        console.error('Error en mostrarDetallesPostulante:', error);
                        $('#modalDetallesPostulante .modal-body').html(`
                            <div class="alert alert-danger">
                                <h5>Error al mostrar datos</h5>
                                <p>Error: ${error.message}</p>
                            </div>
                        `);
                    }
                } else {
                    // No se encontró el postulante
                    let debugInfo = '';
                    if (data.debug) {
                        debugInfo = `
                            <div class="mt-3 text-left">
                                <h6>Información de Debug:</h6>
                                <ul class="list-unstyled small">
                                    <li><strong>CI buscada:</strong> "${data.debug.cedula_buscada}"</li>
                                    <li><strong>Longitud CI:</strong> ${data.debug.cedula_length}</li>
                                    <li><strong>Host BD:</strong> ${data.debug.host}</li>
                                    <li><strong>Base de datos:</strong> ${data.debug.dbname}</li>
                        `;
                        
                        if (data.debug.cedulas_similares && data.debug.cedulas_similares.length > 0) {
                            debugInfo += `
                                    <li><strong>Cédulas similares:</strong>
                                        <ul>
                                            ${data.debug.cedulas_similares.map(c => 
                                                `<li>"${c.cedula}" - ${c.nombre} ${c.apellido}</li>`
                                            ).join('')}
                                        </ul>
                                    </li>
                            `;
                        }
                        
                        if (data.debug.cedulas_exactas && data.debug.cedulas_exactas.length > 0) {
                            debugInfo += `
                                    <li><strong>Cédulas exactas:</strong>
                                        <ul>
                                            ${data.debug.cedulas_exactas.map(c => 
                                                `<li>"${c.cedula}" - ${c.nombre} ${c.apellido}</li>`
                                            ).join('')}
                                        </ul>
                                    </li>
                            `;
                        }
                        
                        debugInfo += `
                                </ul>
                            </div>
                        `;
                    }
                    
                    $('#modalDetallesPostulante .modal-body').html(`
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <h5>Postulante no encontrado</h5>
                            <p>No se encontraron datos para la cédula: <strong>${cedula}</strong></p>
                            ${debugInfo}
                        </div>
                    `);
                }
            } catch (error) {
                console.error('Error buscando postulante:', error);
                console.error('Error completo:', error);
                $('#modalDetallesPostulante .modal-body').html(`
                    <div class="alert alert-danger text-center">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <h5>Error</h5>
                        <p>Ocurrió un error al buscar los datos del postulante.</p>
                        <p><strong>Detalles:</strong> ${error.toString()}</p>
                    </div>
                `);
            }
        }
        
        // Función para restaurar el contenido original del modal
        function restaurarContenidoModal() {
            const contenidoOriginal = `
                <!-- Datos Personales -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary"><i class="fas fa-user mr-2"></i>Datos Personales</h6>
                        <table class="table table-borderless table-sm">
                            <tbody>
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
                                    <td><strong>Fecha de Nacimiento:</strong></td>
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
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Información del registro -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary"><i class="fas fa-fingerprint mr-2"></i>Información del registro</h6>
                        <table class="table table-borderless table-sm">
                            <tbody>
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
                            <tr>
                                <td><strong>Fecha Registro:</strong></td>
                                    <td id="detalle_fecha_registro">-</td>
                            </tr>
                            <tr>
                                <td><strong>Última Edición:</strong></td>
                                    <td id="detalle_fecha_ultima_edicion">-</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Observaciones -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary"><i class="fas fa-comments mr-2"></i>Observaciones</h6>
                        <div id="detalle_observaciones" class="alert alert-light border-left-warning">
                            <p class="mb-0 text-muted">Sin observaciones</p>
                        </div>
                    </div>
                </div>

                <!-- Historial de Modificaciones -->
                <div class="row">
                    <div class="col-12">
                        <h6 class="text-primary"><i class="fas fa-history mr-2"></i>Historial de Modificaciones</h6>
                        <div id="detalle_historial" class="alert alert-light border-left-info">
                            <div class="text-center text-muted">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Cargando historial...
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            $('#modalDetallesPostulante .modal-body').html(contenidoOriginal);
        }
        
        // Función para mostrar detalles del postulante en el modal (igual al inicio)
        function mostrarDetallesPostulante(postulante, buttonElement) {
            console.log('mostrarDetallesPostulante llamada con:', postulante);
            
            // Si no se pasa el elemento del botón, intentar obtenerlo del evento
            if (!buttonElement && event && event.target) {
                buttonElement = event.target.closest('button');
            }
            
            // Obtener ID del postulante (del objeto o del atributo data)
            let postulanteId = postulante.id || postulante[0]; // Manejar tanto objeto como array
            if (!postulanteId && buttonElement) {
                postulanteId = buttonElement.getAttribute('data-postulante-id');
            }
            
            // Llenar los campos del modal con los datos del postulante
            const detalleId = document.getElementById('detalle_id');
            if (detalleId) detalleId.textContent = postulanteId || '-';
            
            const detalleNombre = document.getElementById('detalle_nombre');
            if (detalleNombre) detalleNombre.textContent = postulante.nombre || postulante[1] || '-';
            
            const detalleApellido = document.getElementById('detalle_apellido');
            if (detalleApellido) detalleApellido.textContent = postulante.apellido || postulante[2] || '-';
            
            const detalleCedula = document.getElementById('detalle_cedula');
            if (detalleCedula) detalleCedula.textContent = postulante.cedula || postulante[3] || '-';
            
            const detalleSexo = document.getElementById('detalle_sexo');
            if (detalleSexo) detalleSexo.textContent = postulante.sexo || postulante[15] || '-';
            
            // Fecha de nacimiento
            const fechaNacimiento = postulante.fecha_nacimiento || postulante[4];
            const detalleFechaNacimiento = document.getElementById('detalle_fecha_nacimiento');
            if (detalleFechaNacimiento) {
                if (fechaNacimiento) {
                    const fechaNac = new Date(fechaNacimiento);
                    detalleFechaNacimiento.textContent = fechaNac.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    });
                } else {
                    detalleFechaNacimiento.textContent = '-';
                }
            }
            
            const edad = postulante.edad || postulante[8];
            const detalleEdad = document.getElementById('detalle_edad');
            if (detalleEdad) detalleEdad.textContent = edad ? edad + ' años' : '-';
            
            const telefono = postulante.telefono || postulante[5];
            const detalleTelefono = document.getElementById('detalle_telefono');
            if (detalleTelefono) detalleTelefono.textContent = telefono || '-';
            
            // Información biométrica
            const dedoRegistrado = postulante.dedo_registrado || postulante[10];
            const detalleDedoRegistrado = document.getElementById('detalle_dedo_registrado');
            if (detalleDedoRegistrado) {
                if (dedoRegistrado) {
                    detalleDedoRegistrado.innerHTML = '<span class="badge badge-success">' + dedoRegistrado + '</span>';
                } else {
                    detalleDedoRegistrado.innerHTML = '<span class="badge badge-secondary">No registrado</span>';
                }
            }
            
            // Aparato (con fallback)
            const aparatoNombre = postulante.aparato_nombre_actual || postulante.aparato_nombre || postulante[16];
            const detalleAparato = document.getElementById('detalle_aparato');
            if (detalleAparato) {
                if (aparatoNombre) {
                    detalleAparato.innerHTML = '<span class="badge badge-primary">' + aparatoNombre + '</span>';
                } else {
                    detalleAparato.innerHTML = '<span class="badge badge-secondary">Sin aparato</span>';
                }
            }
            
            const unidad = postulante.unidad || postulante[9];
            const detalleUnidad = document.getElementById('detalle_unidad');
            if (detalleUnidad) {
                if (unidad) {
                    detalleUnidad.innerHTML = '<span class="badge badge-info">' + unidad + '</span>';
                } else {
                    detalleUnidad.innerHTML = '<span class="badge badge-secondary">Sin unidad</span>';
                }
            }
            
            const registradoPor = postulante.registrado_por || postulante[12];
            const detalleRegistradoPor = document.getElementById('detalle_registrado_por');
            if (detalleRegistradoPor) detalleRegistradoPor.textContent = registradoPor || '-';
            
            // Información de registro
            const fechaRegistro = postulante.fecha_registro || postulante[6];
            const detalleFechaRegistro = document.getElementById('detalle_fecha_registro');
            if (detalleFechaRegistro) {
                if (fechaRegistro) {
                    const fechaReg = new Date(fechaRegistro);
                    detalleFechaRegistro.textContent = fechaReg.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    }) + ', ' + fechaReg.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                } else {
                    detalleFechaRegistro.textContent = '-';
                }
            }
            
            const fechaUltimaEdicion = postulante.fecha_ultima_edicion || postulante[14];
            const detalleFechaUltimaEdicion = document.getElementById('detalle_fecha_ultima_edicion');
            if (detalleFechaUltimaEdicion) {
                if (fechaUltimaEdicion) {
                    const fechaUlt = new Date(fechaUltimaEdicion);
                    detalleFechaUltimaEdicion.textContent = fechaUlt.toLocaleDateString('es-ES', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric'
                    }) + ', ' + fechaUlt.toLocaleTimeString('es-ES', {
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit'
                    });
                } else {
                    detalleFechaUltimaEdicion.textContent = '-';
                }
            }
            
            // Observaciones con formato mejorado
            const observaciones = postulante.observaciones || postulante[7];
            const detalleObservaciones = document.getElementById('detalle_observaciones');
            if (detalleObservaciones) {
                if (observaciones && observaciones.trim()) {
                    // Procesar observaciones para formato mejorado
                    let observacionesFormateadas = observaciones
                        .replace(/\[([^\]]+)\]/g, '<span class="badge badge-info">[$1]</span>')
                        .replace(/\n/g, '<br>');
                    detalleObservaciones.innerHTML = observacionesFormateadas;
                } else {
                    detalleObservaciones.innerHTML = '<em class="text-muted">Sin observaciones registradas</em>';
                }
            }
            
            // Historial de modificaciones - cargar desde servidor
            console.log('Objeto postulante completo:', postulante); // Debug
            console.log('ID del postulante (del objeto):', postulante.id); // Debug
            console.log('ID del postulante (calculado):', postulanteId); // Debug
            cargarHistorialModificaciones(postulanteId);
            
            // Verificar que el modal tenga el contenido correcto antes de mostrarlo
            console.log('Contenido del modal antes de mostrar:', $('#modalDetallesPostulante .modal-body').html());
            
            // Mostrar el modal
            $('#modalDetallesPostulante').modal('show');
            console.log('Modal mostrado exitosamente');
        }
        
        // Función para cargar historial de modificaciones (igual al inicio)
        function cargarHistorialModificaciones(postulanteId) {
            console.log('Cargando historial para postulante ID:', postulanteId); // Debug
            
            // Validar que el ID esté disponible
            if (!postulanteId || postulanteId === 'undefined' || postulanteId === 'null') {
                console.error('ID de postulante no válido:', postulanteId);
                const historialDiv = document.getElementById('detalle_historial');
                if (historialDiv) {
                    historialDiv.innerHTML = `
                        <div class="alert alert-warning">
                            Error: ID de postulante no válido
                        </div>
                    `;
                }
                return;
            }
            
            // Cargar historial de modificaciones desde el servidor usando fetch con configuración explícita
            const formData = new FormData();
            formData.append('postulante_id', postulanteId);
            
            console.log('Datos POST enviados:', 'postulante_id=' + postulanteId);
            
            // Intentar primero con POST
            fetch('obtener_historial.php', {
                method: 'POST',
                body: formData,
                cache: 'no-cache',
                credentials: 'same-origin'
            })
            .then(response => {
                console.log('Status de respuesta:', response.status);
                console.log('Headers:', response.headers);
                
                // Si POST falla con 400, intentar con GET
                if (response.status === 400) {
                    console.log('POST falló, intentando con GET...');
                    return fetch(`obtener_historial.php?postulante_id=${postulanteId}`, {
                        method: 'GET',
                        cache: 'no-cache',
                        credentials: 'same-origin'
                    }).then(getResponse => {
                        console.log('Status GET:', getResponse.status);
                        return getResponse.text();
                    });
                }
                
                return response.text();
            })
            .then(responseText => {
                console.log('Respuesta completa:', responseText);
                try {
                    const data = JSON.parse(responseText);
                    console.log('Respuesta del historial:', data);
                    
                    let historialHTML = '';
                    
                    if (data.success && data.historial && data.historial.length > 0) {
                        // Mostrar historial completo
                        data.historial.forEach(entrada => {
                            const fecha = new Date(entrada.fecha_edicion);
                            
                            // Formatear fecha en DD/MM/AAAA, HH:MM:SS
                            const fechaFormateada = fecha.toLocaleDateString('es-ES', {
                                day: '2-digit',
                                month: '2-digit',
                                year: 'numeric'
                            }) + ', ' + fecha.toLocaleTimeString('es-ES', {
                                hour: '2-digit',
                                minute: '2-digit',
                                second: '2-digit'
                            });
                            
                            // Separar los cambios por punto y coma y crear líneas separadas
                            const cambiosArray = entrada.cambios.split(';').map(cambio => cambio.trim()).filter(cambio => cambio);
                            
                            // Formatear fechas dentro de los cambios (YYYY-MM-DD → DD/MM/YYYY)
                            const cambiosFormateados = cambiosArray.map(cambio => {
                                return cambio.replace(/(\d{4}-\d{2}-\d{2})/g, (fecha) => {
                                    const fechaObj = new Date(fecha);
                                    return fechaObj.toLocaleDateString('es-ES', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric'
                                    });
                                });
                            });
                            
                            const cambiosHTML = cambiosFormateados.map(cambio => `<div class="mb-1"><small class="text-dark">${cambio}</small></div>`).join('');
                            
                            historialHTML += `
                                <div class="mb-3 p-3 border-left border-info bg-light">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong class="text-info">${entrada.usuario_editor}</strong>
                                            <small class="text-muted ml-2">${fechaFormateada}</small>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        ${cambiosHTML}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        // No hay historial disponible
                        historialHTML = `
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-history fa-2x mb-2"></i>
                                <p class="mb-0">No hay historial de modificaciones disponible</p>
                </div>
                        `;
                    }
                    
                    const historialDiv = document.getElementById('detalle_historial');
                    if (historialDiv) historialDiv.innerHTML = historialHTML;
                    
                } catch (error) {
                    console.error('Error al procesar respuesta:', error);
                    const historialDiv = document.getElementById('detalle_historial');
                    if (historialDiv) {
                        historialDiv.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                Error al cargar el historial de modificaciones
                            </div>
                        `;
                    }
                }
            })
            .catch(error => {
                console.error('Error en la petición:', error);
                const historialDiv = document.getElementById('detalle_historial');
                if (historialDiv) {
                    historialDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            Error de conexión al cargar el historial
                        </div>
                    `;
                }
            });
        }
        
        // Evento para cargar historial cuando el modal esté completamente mostrado
        $('#modalDetallesPostulante').on('shown.bs.modal', function () {
            // El historial se carga automáticamente en mostrarDetallesPostulante
        });
        
        // Hacer funciones globales para onclick
        window.mostrarPagina = mostrarPagina;
        window.verPerfil = verPerfil;
        window.cargarPagina = cargarPagina;
        window.buscarEnDashboard = buscarEnDashboard;
        
    });
    </script>
    
    <!-- Footer fijo y modal del desarrollador -->
    <?php include 'includes/developer-footer.php'; ?>
</body>
</html>
