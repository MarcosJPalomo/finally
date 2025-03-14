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
    $hora_regreso = isset($_POST['hora_regreso']) ? $_POST['hora_regreso'] : null;
    $motivo = trim($_POST['motivo']);
    
    // Validaciones básicas
    if (empty($tipo_pase) || empty($fecha_pase) || empty($motivo)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } else if (($tipo_pase == 'salida' || $tipo_pase == 'entrada_salida') && empty($hora_salida)) {
        $error = 'La hora de salida es obligatoria para este tipo de pase.';
    } else if (($tipo_pase == 'entrada' || $tipo_pase == 'entrada_salida') && empty($hora_regreso)) {
        $error = 'La hora de regreso es obligatoria para este tipo de pase.';
    } else if ($tipo_pase == 'entrada_salida' && $hora_salida >= $hora_regreso) {
        $error = 'La hora de salida debe ser anterior a la hora de regreso.';
    } else {
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
            $detalles = "Tipo: $tipo_pase, Fecha: $fecha_pase";
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

// Obtener lista de pases del empleado
$query = "SELECT * FROM pases_entrada_salida 
          WHERE num_ficha = ? 
          ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $num_ficha);
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
                        <h5 class="mb-0">Mis Solicitudes de Pases</h5>
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
                    <p><strong>Hora de Regreso:</strong> <?php echo date('H:i', strtotime($pase['hora_regreso'])); ?></p>
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
            const inputHoraSalida = document.getElementById('hora_salida');
            const inputHoraRegreso = document.getElementById('hora_regreso');
            
            // Restablecer required
            inputHoraSalida.required = false;
            inputHoraRegreso.required = false;
            
            // Ocultar ambos por defecto
            divHoraSalida.style.display = 'none';
            divHoraRegreso.style.display = 'none';
            
            // Mostrar según el tipo de pase
            if (tipoPase === 'entrada') {
                divHoraRegreso.style.display = 'block';
                inputHoraRegreso.required = true;
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
        document.addEventListener('DOMContentLoaded', mostrarCamposHora);
    </script>
</body>
</html>