<?php
/**
 * Página de Verificación de Postulantes
 * Permite a los postulantes consultar sus datos ingresando su CI
 * No requiere autenticación
 */

require_once 'config.php';

// Función para convertir código de dedo a nombre descriptivo
function getDedoNombre($codigo) {
    $dedos = [
        'PD' => 'Pulgar Derecho',
        'ID' => 'Índice Derecho',
        'MD' => 'Medio Derecho',
        'AD' => 'Anular Derecho',
        'MeD' => 'Meñique Derecho',
        'PI' => 'Pulgar Izquierdo',
        'II' => 'Índice Izquierdo',
        'MI' => 'Medio Izquierdo',
        'AI' => 'Anular Izquierdo',
        'MeI' => 'Meñique Izquierdo'
    ];
    
    return $dedos[$codigo] ?? $codigo;
}

$mensaje = '';
$tipo_mensaje = '';
$postulante = null;

// Procesar consulta por CI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cedula'])) {
    $cedula = trim($_POST['cedula']);
    
    if (empty($cedula)) {
        $mensaje = 'Por favor ingrese su número de cédula';
        $tipo_mensaje = 'warning';
    } else {
        try {
            $pdo = getDBConnection();
            
            // Buscar postulante por cédula
            $stmt = $pdo->prepare("
                SELECT 
                    p.*,
                    u.nombre as capturador_nombre,
                    u.apellido as capturador_apellido,
                    u.grado as capturador_grado,
                    a.nombre as aparato_nombre_actual
                FROM postulantes p
                LEFT JOIN usuarios u ON p.capturador_id = u.id
                LEFT JOIN aparatos_biometricos a ON p.aparato_id = a.id
                WHERE p.cedula = ?
            ");
            $stmt->execute([$cedula]);
            $postulante = $stmt->fetch();
            
            if (!$postulante) {
                $mensaje = 'No se encontraron datos para la cédula ingresada';
                $tipo_mensaje = 'warning';
            }
            
        } catch (Exception $e) {
            $mensaje = 'Error al consultar los datos: ' . $e->getMessage();
            $tipo_mensaje = 'danger';
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

    <title>Verificar Datos - Sistema Quira</title>

    <meta name="description" content="Verificación de datos de postulantes - Sistema Quira">
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
            background: url('assets/media/login/login_background.jpg') center center/cover no-repeat;
            min-height: 100vh;
            padding: 2rem 0;
            padding-bottom: 50px;
        }
        .verification-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            max-width: 800px;
            margin: 0 auto;
        }
        .verification-header {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .verification-body {
            padding: 2rem;
        }
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
        }
        .form-control:focus {
            border-color: #2E5090;
            box-shadow: 0 0 0 0.2rem rgba(46, 80, 144, 0.25);
        }
        .btn-verify {
            background: linear-gradient(135deg, #2E5090 0%, #1a3a70 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 80, 144, 0.4);
        }
        .data-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
            border-left: 4px solid #2E5090;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .data-row:last-child {
            border-bottom: none;
        }
        .data-label {
            font-weight: 600;
            color: #495057;
        }
        .data-value {
            color: #212529;
            text-align: right;
        }
        .back-link {
            color: #2E5090;
            text-decoration: none;
            font-weight: 600;
        }
        .back-link:hover {
            color: #1a3a70;
            text-decoration: none;
        }
        
        /* Footer s1mple */
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
        
        /* Modal s1mple */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-container {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header h1 {
            color: #2E5090;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .modal-divider {
            width: 50px;
            height: 3px;
            background: #2E5090;
            margin: 1rem auto;
        }
        
        .modal-subtitle {
            color: #666;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .modal-content p {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .btn-close {
            background: #2E5090;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
        }
        
        .btn-close:hover {
            background: #1a3a70;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verification-container">
            <div class="verification-header">
                <img src="assets/media/various/quiraXXXL.png" alt="Quira Logo" style="height: 60px; width: auto; margin-bottom: 15px;">
                <h4 class="mb-0">Sistema Quira</h4>
                <p class="mb-0 opacity-75">Verificación de Datos de Postulantes</p>
            </div>
            
            <div class="verification-body">
                <?php if ($mensaje): ?>
                <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?= $tipo_mensaje === 'success' ? 'check-circle' : ($tipo_mensaje === 'warning' ? 'exclamation-triangle' : 'times-circle') ?> mr-2"></i>
                    <?= htmlspecialchars($mensaje) ?>
                    <button type="button" class="close" data-dismiss="alert">
                        <span>&times;</span>
                    </button>
                </div>
                <?php endif; ?>
                
                <?php if (!$postulante): ?>
                <!-- Formulario de consulta -->
                <form method="POST">
                    <div class="form-group">
                        <label for="cedula" class="font-weight-bold">
                            <i class="fas fa-id-card mr-2"></i>Número de Cédula
                        </label>
                        <input type="text" class="form-control" id="cedula" name="cedula" 
                               placeholder="Ingrese su número de cédula" required 
                               value="<?= htmlspecialchars($_POST['cedula'] ?? '') ?>">
                        <small class="form-text text-muted">
                            <i class="fas fa-info-circle mr-1"></i>
                            Ingrese su número de cédula para consultar sus datos registrados
                        </small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-verify btn-block">
                        <i class="fas fa-search mr-2"></i>Verificar Datos
                    </button>
                </form>
                
                
                <?php else: ?>
                <!-- Mostrar datos del postulante -->
                <div class="data-card">
                    <h5 class="mb-3 text-center">
                        <i class="fas fa-user-check mr-2"></i>Datos del Postulante
                    </h5>
                    
                    <div class="data-row">
                        <span class="data-label">Nombre Completo:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['nombre'] . ' ' . $postulante['apellido']) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Cédula de Identidad:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['cedula']) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Teléfono:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['telefono']) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Dispositivo/Dedo Registrado:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['aparato_nombre']) ?> / <?= htmlspecialchars(getDedoNombre($postulante['dedo_registrado'])) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Fecha de Nacimiento:</span>
                        <span class="data-value"><?= date('d/m/Y', strtotime($postulante['fecha_nacimiento'])) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Edad:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['edad']) ?> años</span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Sexo:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['sexo']) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Unidad:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['unidad']) ?></span>
                    </div>
                    
                    
                    <div class="data-row">
                        <span class="data-label">Capturador de Huella:</span>
                        <span class="data-value">
                            <?php 
                            $capturador = '';
                            if ($postulante['capturador_grado'] && $postulante['capturador_nombre'] && $postulante['capturador_apellido']) {
                                $capturador = $postulante['capturador_grado'] . ' ' . $postulante['capturador_nombre'] . ' ' . $postulante['capturador_apellido'];
                            } else {
                                $capturador = 'Oficial Ayudante JOSE MERLO'; // Valor por defecto según especificación
                            }
                            echo htmlspecialchars($capturador);
                            ?>
                        </span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Registrador:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['registrado_por']) ?></span>
                    </div>
                    
                    <div class="data-row">
                        <span class="data-label">Fecha y Hora de Registro:</span>
                        <span class="data-value"><?= date('d/m/Y H:i:s', strtotime($postulante['fecha_registro'])) ?></span>
                    </div>
                    
                    <?php if ($postulante['observaciones']): ?>
                    <div class="data-row">
                        <span class="data-label">Observaciones:</span>
                        <span class="data-value"><?= htmlspecialchars($postulante['observaciones']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="text-center mt-4">
                    <button type="button" class="btn btn-success" onclick="descargarPDF()">
                        <i class="fas fa-download mr-2"></i>Descargar PDF
                    </button>
                </div>
                <?php endif; ?>
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

    <!-- Modal s1mple -->
    <div class="modal-overlay" id="modal-overlay">
        <div class="modal-container" id="modal-container">
            <div class="modal-header">
                <h1 class="modal-title">s1mple</h1>
                <div class="modal-divider"></div>
                <p class="modal-subtitle">From BITCAN</p>
            </div>
            <div class="modal-content">
                <p>Desarrollado por el equipo de BITCAN</p>
                <p>Soluciones tecnológicas innovadoras</p>
            </div>
            <div class="modal-footer">
                <button class="btn-close" onclick="closeModal()">Cerrar</button>
            </div>
        </div>
    </div>

    <!-- jsPDF Library -->
    <script src="https://unpkg.com/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    
    <script>
        // Modal s1mple
        document.getElementById('footer-link').addEventListener('click', function() {
            document.getElementById('modal-overlay').style.display = 'flex';
        });

        function closeModal() {
            document.getElementById('modal-overlay').style.display = 'none';
        }

        // Cerrar modal al hacer clic fuera
        document.getElementById('modal-overlay').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Función para descargar PDF
        function descargarPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            
            // Configuración básica del documento
            const pageWidth = doc.internal.pageSize.getWidth();
            const pageHeight = doc.internal.pageSize.getHeight();
            
            // Encabezado azul característico
            doc.setFillColor(46, 80, 144); // #2E5090
            doc.rect(0, 0, pageWidth, 50, 'F');
            
            // Intentar cargar el logo QUIRA.png
            try {
                // Crear imagen del logo
                const img = new Image();
                img.src = 'assets/media/various/quiraXXXL.png';
                
                // Esperar a que cargue la imagen
                await new Promise((resolve, reject) => {
                    img.onload = resolve;
                    img.onerror = reject;
                });
                
                // Agregar logo al PDF (escalado apropiadamente)
                const logoWidth = 25;
                const logoHeight = 25; // Cuadrado para evitar achatamiento
                doc.addImage(img, 'PNG', pageWidth/2 - logoWidth/2, 5, logoWidth, logoHeight);
                
            } catch (error) {
                console.log('Error cargando logo:', error);
                // Fallback: texto QUIRA
                doc.setTextColor(255, 255, 255);
                doc.setFontSize(18);
                doc.setFont(undefined, 'bold');
                doc.text('QUIRA', pageWidth/2, 15, { align: 'center' });
            }
            
            // Títulos del encabezado
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(16); // Aumentado de 12 a 16
            doc.setFont(undefined, 'bold');
            doc.text('Sistema QUIRA', pageWidth/2, 35, { align: 'center' }); // Separación del logo
            
            doc.setFontSize(12); // Aumentado de 9 a 12
            doc.text('Datos del Postulante', pageWidth/2, 45, { align: 'center' });
            
            // Datos del postulante
            const datos = [
                ['Nombre Completo:', '<?= htmlspecialchars($postulante['nombre'] . ' ' . $postulante['apellido']) ?>'],
                ['Cédula de Identidad:', '<?= htmlspecialchars($postulante['cedula']) ?>'],
                ['Teléfono:', '<?= htmlspecialchars($postulante['telefono']) ?>'],
                ['Dispositivo/Dedo Registrado:', '<?= htmlspecialchars($postulante['aparato_nombre']) ?> / <?= htmlspecialchars(getDedoNombre($postulante['dedo_registrado'])) ?>'],
                ['Fecha de Nacimiento:', '<?= date('d/m/Y', strtotime($postulante['fecha_nacimiento'])) ?>'],
                ['Edad:', '<?= htmlspecialchars($postulante['edad']) ?> años'],
                ['Sexo:', '<?= htmlspecialchars($postulante['sexo']) ?>'],
                ['Unidad:', '<?= str_replace('&quot;', '"', htmlspecialchars($postulante['unidad'])) ?>'],
                ['Capturador de Huella:', 'Oficial Ayudante JOSE MERLO'],
                ['Registrador:', '<?= htmlspecialchars($postulante['registrado_por']) ?>'],
                ['Fecha y Hora de Registro:', '<?= date('d/m/Y H:i:s', strtotime($postulante['fecha_registro'])) ?>']
            ];
            
            // Agregar observaciones si existen
            <?php if ($postulante['observaciones']): ?>
            datos.push(['Observaciones:', '<?= htmlspecialchars($postulante['observaciones']) ?>']);
            <?php endif; ?>
            
            // Debug: verificar datos
            console.log('Datos a procesar:', datos);
            console.log('Número de datos:', datos.length);
            
            // Formato simple - versión debug
            doc.setFontSize(12);
            doc.setTextColor(0, 0, 0); // Negro sólido
            let yPosition = 60; // Ajustado para eliminar espacio innecesario
            const lineHeight = 10;
            
            // Agregar cada línea de datos con debug
            datos.forEach(function(item, index) {
                console.log(`Procesando item ${index}:`, item);
                
                // Verificar si necesitamos una nueva página
                if (yPosition > pageHeight - 20) {
                    doc.addPage();
                    yPosition = 20;
                }
                
                // Texto simple para debug
                doc.setFont(undefined, 'bold');
                doc.text(item[0], 20, yPosition);
                
                doc.setFont(undefined, 'normal');
                doc.text(item[1], 20, yPosition + 5);
                
                yPosition += lineHeight + 5;
            });
            
            // Pie de página simple
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text('Documento generado el ' + new Date().toLocaleDateString('es-ES') + ' a las ' + new Date().toLocaleTimeString('es-ES'), pageWidth/2, pageHeight - 10, { align: 'center' });
            
            // Descargar el PDF
            const nombreArchivo = 'Datos_Postulante_<?= htmlspecialchars($postulante['cedula']) ?>_<?= date('Y-m-d') ?>.pdf';
            doc.save(nombreArchivo);
        }
    </script>
</body>
</html>
