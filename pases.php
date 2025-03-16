<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado y es de tipo empleado o supervisor
if (!isset($_SESSION['user_id']) || ($_SESSION['tipo_usuario'] != 'empleado' && $_SESSION['tipo_usuario'] != 'supervisor')) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$tipo_usuario = $_SESSION['tipo_usuario'];

// Obtener información del usuario
$query = "SELECT u.*, e.nombre, e.puesto FROM usuarios u 
          JOIN empleados e ON u.num_ficha = e.num_ficha 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$num_ficha = $user['num_ficha'];

// Procesar el formulario de solicitud de pase
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tipo_pase = $_POST['tipo_pase'];
    $fecha_pase = $_POST['fecha_pase'];
    $hora_salida = isset($_POST['hora_salida']) ? $_POST['hora_salida'] : null;
    $hora_entrada = isset($_POST['hora_entrada']) ? $_POST['hora_entrada'] : null;
    $hora_regreso = isset($_POST['hora_regreso']) ? $_POST['hora_regreso'] : null;
    $motivo = trim($_POST['motivo']);
    
    // Si es supervisor y está solicitando para otro empleado, obtener el número de ficha
    if ($tipo_usuario == 'supervisor' && isset($_POST['num_ficha_empleado']) && !empty($_POST['num_ficha_empleado'])) {
        $num_ficha = trim($_POST['num_ficha_empleado']);
    }
    
    // Validaciones básicas
    if (empty($tipo_pase) || empty($fecha_pase) || empty($motivo)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } else if ($tipo_pase == 'salida' && empty($hora_salida)) {
        $error = 'La hora de salida es obligatoria para pases de salida.';
    } else if ($tipo_pase == 'entrada' && empty($hora_entrada)) {
        $error = 'La hora de entrada es obligatoria para pases de entrada.';
    } else if ($tipo_pase == 'entrada_salida' && (empty($hora_salida) || empty($hora_regreso))) {
        $error = 'Las horas de salida y regreso son obligatorias para pases de entrada/salida.';
    } else if ($tipo_pase == 'entrada_salida' && $hora_salida >= $hora_regreso) {
        $error = 'La hora de salida debe ser anterior a la hora de regreso.';
    } else {
        // Verificar que el empleado exista (en caso de supervisor)
        if ($tipo_usuario == 'supervisor' && isset($_POST['num_ficha_empleado'])) {
            $check_query = "SELECT * FROM empleados WHERE num_ficha = ?";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bind_param("s", $num_ficha);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $error = 'El número de ficha no existe en el sistema.';
            }
        }
        
        if (empty($error)) {
            // Para pase de entrada, usamos la hora_entrada como hora_regreso
            if ($tipo_pase == 'entrada') {
                $hora_regreso = $hora_entrada;
            }
            
            // Insertar la solicitud de pase
            $insert_query = "INSERT INTO pases_entrada_salida (num_ficha, tipo_pase, fecha_pase, hora_salida, hora_regreso, motivo) 
                           VALUES (?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("ssssss", $num_ficha, $tipo_pase, $fecha_pase, $hora_salida, $hora_regreso, $motivo);
            
            if ($insert_stmt->execute()) {
                $success = 'Solicitud de pase registrada correctamente.';
                
                // Registrar la actividad
                $accion = "Solicitud de pase " . $tipo_pase;
                $tabla = "pases_entrada_salida";
                $registro_id = $conn->insert_id;
                $detalles = "Tipo: $tipo_pase, Fecha: $fecha_pase, Ficha: $num_ficha";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("ississ", $user_id, $accion, $tabla, $registro_id, $detalles, $ip);
                $log_stmt->execute();
            } else {
                $error = 'Error al registrar la solicitud: ' . $conn->error;
            }
        }
    }
}

// Obtener lista de pases del empleado o de los empleados (si es supervisor)
if ($tipo_usuario == 'supervisor') {
    $query = "SELECT p.*, e.nombre, e.puesto 
              FROM pases_entrada_salida p
              JOIN empleados e ON p.num_ficha = e.num_ficha
              ORDER BY p.fecha_solicitud DESC";
    $stmt = $conn->prepare($query);
} else {
    $query = "SELECT p.*, e.nombre, e.puesto 
              FROM pases_entrada_salida p
              JOIN empleados e ON p.num_ficha = e.num_ficha
              WHERE p.num_ficha = ?
              ORDER BY p.fecha_solicitud DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $num_ficha);
}

$stmt->execute();
$result = $stmt->get_result();
$pases = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pases[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pases de Entrada/Salida - Sistema de Gestión de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Sistema RH</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="pases.php">Pases de Entrada/Salida</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Cerrar Sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <h1>Solicitud de Pases de Entrada/Salida</h1>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Nueva Solicitud de Pase</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="pases.php" id="formPase">
                            <?php if ($tipo_usuario == 'supervisor'): ?>
                            <div class="mb-3">
                                <label for="num_ficha_empleado" class="form-label">Número de Ficha del Empleado</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="num_ficha_empleado" name="num_ficha_empleado" placeholder="Dejar vacío para solicitud propia">
                                    <button class="btn btn-outline-secondary" type="button" id="buscarEmpleado">Buscar</button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="nombre_empleado" class="form-label">Nombre del Empleado</label>
                                <input type="text" class="form-control" id="nombre_empleado" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="puesto_empleado" class="form-label">Puesto</label>
                                <input type="text" class="form-control" id="puesto_empleado" readonly>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="tipo_pase" class="form-label">Tipo de Pase</label>
                                <select class="form-select" id="tipo_pase" name="tipo_pase" required onchange="mostrarCamposHora()">
                                    <option value="">Seleccione...</option>
                                    <option value="entrada">Pase de Entrada</option>
                                    <option value="salida">Pase de Salida</option>
                                    <option value="entrada_salida">Pase de Entrada y Salida</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="fecha_pase" class="form-label">Fecha del Pase</label>
                                <input type="date" class="form-control" id="fecha_pase" name="fecha_pase" required>
                            </div>
                            
                            <div class="mb-3" id="div_hora_entrada" style="display: none;">
                                <label for="hora_entrada" class="form-label">Hora de Entrada</label>
                                <input type="time" class="form-control" id="hora_entrada" name="hora_entrada">
                            </div>
                            
                            <div class="mb-3" id="div_hora_salida" style="display: none;">
                                <label for="hora_salida" class="form-label">Hora de Salida</label>
                                <input type="time" class="form-control" id="hora_salida" name="hora_salida">
                            </div>
                            
                            <div class="mb-3" id="div_hora_regreso" style="display: none;">
                                <label for="hora_regreso" class="form-label">Hora de Regreso</label>
                                <input type="time" class="form-control" id="hora_regreso" name="hora_regreso">
                            </div>
                            
                            <div class="mb-3">
                                <label for="motivo" class="form-label">Motivo</label>
                                <textarea class="form-control" id="motivo" name="motivo" rows="3" required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Enviar Solicitud</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Solicitudes de Pases</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pases)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <?php if ($tipo_usuario == 'supervisor'): ?>
                                        <th>Empleado</th>
                                        <th>Ficha</th>
                                        <?php endif; ?>
                                        <th>Tipo</th>
                                        <th>Fecha</th>
                                        <th>Hora Salida</th>
                                        <th>Hora Regreso</th>
                                        <th>Estado</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pases as $pase): ?>
                                    <tr>
                                        <td><?php echo $pase['id']; ?></td>
                                        <?php if ($tipo_usuario == 'supervisor'): ?>
                                        <td><?php echo $pase['nombre']; ?></td>
                                        <td><?php echo $pase['num_ficha']; ?></td>
                                        <?php endif; ?>
                                        <td>
                                            <?php 
                                            switch ($pase['tipo_pase']) {
                                                case 'entrada':
                                                    echo 'Entrada';
                                                    break;
                                                case 'salida':
                                                    echo 'Salida';
                                                    break;
                                                case 'entrada_salida':
                                                    echo 'Entrada/Salida';
                                                    break;
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></td>
                                        <td><?php echo $pase['hora_salida'] ? date('H:i', strtotime($pase['hora_salida'])) : 'N/A'; ?></td>
                                        <td><?php echo $pase['hora_regreso'] ? date('H:i', strtotime($pase['hora_regreso'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            switch ($pase['estado']) {
                                                case 'pendiente':
                                                    $badge_class = 'bg-warning';
                                                    break;
                                                case 'aprobada':
                                                    $badge_class = 'bg-success';
                                                    break;
                                                case 'rechazada':
                                                    $badge_class = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($pase['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $pase['id']; ?>">
                                                Ver
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modales para ver detalles de los pases -->
    <?php foreach ($pases as $pase): ?>
    <div class="modal fade" id="modalDetalles<?php echo $pase['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $pase['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel<?php echo $pase['id']; ?>">Detalles del Pase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $pase['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $pase['puesto']; ?></p>
                    <p><strong>Número de Ficha:</strong> <?php echo $pase['num_ficha']; ?></p>
                    <p><strong>Tipo de Pase:</strong> 
                        <?php 
                        switch ($pase['tipo_pase']) {
                            case 'entrada':
                                echo 'Entrada';
                                break;
                            case 'salida':
                                echo 'Salida';
                                break;
                            case 'entrada_salida':
                                echo 'Entrada/Salida';
                                break;
                        }
                        ?>
                    </p>
                    <p><strong>Fecha del Pase:</strong> <?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></p>
                    
                    <?php if ($pase['hora_salida']): ?>
                    <p><strong>Hora de Salida:</strong> <?php echo date('H:i', strtotime($pase['hora_salida'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['hora_regreso']): ?>
                    <p><strong>Hora de Regreso/Entrada:</strong> <?php echo date('H:i', strtotime($pase['hora_regreso'])); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Motivo:</strong> <?php echo $pase['motivo']; ?></p>
                    
                    <p><strong>Estado:</strong> 
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($pase['estado']); ?>
                        </span>
                    </p>
                    
                    <?php if ($pase['comentarios']): ?>
                    <p><strong>Comentarios de RH:</strong> <?php echo $pase['comentarios']; ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['hora_salida_real']): ?>
                    <p><strong>Hora de Salida Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_salida_real'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['hora_regreso_real']): ?>
                    <p><strong>Hora de Regreso Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_regreso_real'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['excedio_tiempo']): ?>
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Se excedió el tiempo autorizado.
                    </div>
                    <?php endif; ?>
                    
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['fecha_solicitud'])); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function mostrarCamposHora() {
            const tipoPase = document.getElementById('tipo_pase').value;
            const divHoraSalida = document.getElementById('div_hora_salida');
            const divHoraRegreso = document.getElementById('div_hora_regreso');
            const divHoraEntrada = document.getElementById('div_hora_entrada');
            const inputHoraSalida = document.getElementById('hora_salida');
            const inputHoraRegreso = document.getElementById('hora_regreso');
            const inputHoraEntrada = document.getElementById('hora_entrada');
            
            // Restablecer required
            inputHoraSalida.required = false;
            inputHoraRegreso.required = false;
            inputHoraEntrada.required = false;
            
            // Ocultar todos por defecto
            divHoraSalida.style.display = 'none';
            divHoraRegreso.style.display = 'none';
            divHoraEntrada.style.display = 'none';
            
            // Mostrar según el tipo de pase
            if (tipoPase === 'entrada') {
                divHoraEntrada.style.display = 'block';
                inputHoraEntrada.required = true;
            } else if (tipoPase === 'salida') {
                divHoraSalida.style.display = 'block';
                inputHoraSalida.required = true;
            } else if (tipoPase === 'entrada_salida') {
                divHoraSalida.style.display = 'block';
                divHoraRegreso.style.display = 'block';
                inputHoraSalida.required = true;
                inputHoraRegreso.required = true;
            }
        }
        
        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            mostrarCamposHora();
            
            <?php if ($tipo_usuario == 'supervisor'): ?>
            // Función para buscar información del empleado por número de ficha
            document.getElementById('buscarEmpleado').addEventListener('click', function() {
                buscarEmpleadoPorFicha();
            });
            
            document.getElementById('num_ficha_empleado').addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    buscarEmpleadoPorFicha();
                }
            });
            
            function buscarEmpleadoPorFicha() {
                const numFicha = document.getElementById('num_ficha_empleado').value.trim();
                
                if (numFicha === '') {
                    document.getElementById('nombre_empleado').value = '';
                    document.getElementById('puesto_empleado').value = '';
                    return;
                }
                
                // Realizar la solicitud AJAX
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'buscar_empleado.php?num_ficha=' + encodeURIComponent(numFicha), true);
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                document.getElementById('nombre_empleado').value = response.nombre || '';
                                document.getElementById('puesto_empleado').value = response.puesto || '';
                            } else {
                                document.getElementById('nombre_empleado').value = '';
                                document.getElementById('puesto_empleado').value = '';
                                alert('Empleado no encontrado.');
                            }
                        } catch (e) {
                            console.error('Error al procesar la respuesta:', e);
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Error de conexión');
                };
                
                xhr.send();
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>