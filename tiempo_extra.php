<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado y es de tipo supervisor
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'supervisor') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Obtener información del supervisor
$user_query = "SELECT u.*, e.nombre, e.puesto FROM usuarios u 
               JOIN empleados e ON u.num_ficha = e.num_ficha 
               WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Procesar el formulario de solicitud de tiempo extra
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $semana_inicio = $_POST['semana_inicio'];
    $horas_extra = (int)$_POST['horas_extra'];
    $motivo = trim($_POST['motivo']);
    
    // Validaciones básicas
    if (empty($semana_inicio) || empty($motivo) || $horas_extra <= 0) {
        $error = 'Por favor, complete todos los campos obligatorios correctamente.';
    } else {
        // Insertar la solicitud de tiempo extra usando el número de ficha del supervisor
        $insert_query = "INSERT INTO tiempo_extra (num_ficha, semana_inicio, horas_extra, motivo, supervisor_id) 
                       VALUES (?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ssisi", $user['num_ficha'], $semana_inicio, $horas_extra, $motivo, $user_id);
        
        if ($insert_stmt->execute()) {
            $success = 'Solicitud de tiempo extra registrada correctamente.';
            
            // Registrar la actividad
            $accion = "Solicitud de tiempo extra";
            $tabla = "tiempo_extra";
            $registro_id = $conn->insert_id;
            $detalles = "Supervisor: " . $user['nombre'] . ", Semana: $semana_inicio, Horas: $horas_extra";
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

// Obtener lista de solicitudes de tiempo extra del supervisor
$query = "SELECT te.*, e.nombre, e.puesto 
          FROM tiempo_extra te
          JOIN empleados e ON te.num_ficha = e.num_ficha
          WHERE te.supervisor_id = ?
          ORDER BY te.fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Tiempo Extra - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="tiempo_extra.php">Tiempo Extra</a>
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
        <h1>Solicitud de Tiempo Extra</h1>
        
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
                        <h5 class="mb-0">Nueva Solicitud de Tiempo Extra</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="tiempo_extra.php">
                            <div class="mb-3">
                                <label for="supervisor" class="form-label">Supervisor</label>
                                <input type="text" class="form-control" id="supervisor" value="<?php echo $user['nombre']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="puesto" class="form-label">Puesto</label>
                                <input type="text" class="form-control" id="puesto" value="<?php echo $user['puesto']; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="semana_inicio" class="form-label">Semana que inicia el</label>
                                <input type="date" class="form-control" id="semana_inicio" name="semana_inicio" required>
                                <div class="form-text">Seleccione el lunes de la semana para la que solicita el tiempo extra.</div>
                            </div>
                            <div class="mb-3">
                                <label for="horas_extra" class="form-label">Horas Extra</label>
                                <input type="number" class="form-control" id="horas_extra" name="horas_extra" min="1" required>
                                <div class="form-text">Total de horas extra para la semana.</div>
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
                        <h5 class="mb-0">Solicitudes Realizadas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($solicitudes)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Semana</th>
                                        <th>Horas</th>
                                        <th>Estado Revisor</th>
                                        <th>Estado RH</th>
                                        <th>Fecha Solicitud</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $solicitud): ?>
                                    <tr>
                                        <td><?php echo $solicitud['id']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($solicitud['semana_inicio'])); ?></td>
                                        <td><?php echo $solicitud['horas_extra']; ?></td>
                                        <td>
                                            <?php 
                                            $badge_class_revisor = '';
                                            switch ($solicitud['estado_revisor']) {
                                                case 'pendiente':
                                                    $badge_class_revisor = 'bg-warning';
                                                    break;
                                                case 'aprobada':
                                                    $badge_class_revisor = 'bg-success';
                                                    break;
                                                case 'rechazada':
                                                    $badge_class_revisor = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class_revisor; ?>">
                                                <?php echo ucfirst($solicitud['estado_revisor']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $badge_class_rh = '';
                                            switch ($solicitud['estado_rh']) {
                                                case 'pendiente':
                                                    $badge_class_rh = 'bg-warning';
                                                    break;
                                                case 'procesada':
                                                    $badge_class_rh = 'bg-success';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class_rh; ?>">
                                                <?php echo ucfirst($solicitud['estado_rh']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $solicitud['id']; ?>">
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
    
    <!-- Modales para ver detalles de las solicitudes -->
    <?php foreach ($solicitudes as $solicitud): ?>
    <div class="modal fade" id="modalDetalles<?php echo $solicitud['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $solicitud['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel<?php echo $solicitud['id']; ?>">Detalles de la Solicitud de Tiempo Extra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Semana que inicia el:</strong> <?php echo date('d/m/Y', strtotime($solicitud['semana_inicio'])); ?></p>
                    <p><strong>Horas Extra:</strong> <?php echo $solicitud['horas_extra']; ?></p>
                    <p><strong>Motivo:</strong> <?php echo $solicitud['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></p>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estado Revisor</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <span class="badge <?php echo $badge_class_revisor; ?>">
                                    <?php echo ucfirst($solicitud['estado_revisor']); ?>
                                </span>
                            </p>
                            <?php if ($solicitud['estado_revisor'] != 'pendiente' && $solicitud['fecha_revision_revisor']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $solicitud['comentarios_revisor'] ? $solicitud['comentarios_revisor'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_revision_revisor'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estado RH</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <span class="badge <?php echo $badge_class_rh; ?>">
                                    <?php echo ucfirst($solicitud['estado_rh']); ?>
                                </span>
                            </p>
                            <?php if ($solicitud['estado_rh'] != 'pendiente' && $solicitud['fecha_revision_rh']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $solicitud['comentarios_rh'] ? $solicitud['comentarios_rh'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Procesamiento:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_revision_rh'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Asegurarse de que la fecha de semana_inicio sea un lunes
            document.getElementById('semana_inicio').addEventListener('change', function() {
                const date = new Date(this.value);
                const day = date.getDay();
                
                // Si no es lunes (0 = domingo, 1 = lunes, ..., 6 = sábado)
                if (day !== 1) {
                    // Mostrar advertencia
                    alert('Por favor, seleccione un lunes como fecha de inicio de semana.');
                    
                    // Calcular el lunes más cercano (anterior o posterior)
                    const diff = day === 0 ? 1 : 8 - day; // Si es domingo, ir al siguiente lunes, sino al lunes anterior
                    date.setDate(date.getDate() + diff);
                    
                    // Formatear la fecha como YYYY-MM-DD
                    const adjustedDate = date.toISOString().split('T')[0];
                    this.value = adjustedDate;
                }
            });
        });
    </script>
</body>
</html>