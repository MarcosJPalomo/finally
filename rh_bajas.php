<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado y es de tipo RH
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'rh') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Procesar la aprobación o rechazo de solicitudes
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $solicitud_id = $_POST['solicitud_id'];
    $accion = $_POST['accion'];
    $comentarios = trim($_POST['comentarios']);
    
    if ($accion == 'aprobar' || $accion == 'rechazar') {
        $estado = ($accion == 'aprobar') ? 'aprobada' : 'rechazada';
        
        $update_query = "UPDATE solicitudes_baja 
                        SET estado_rh = ?, rh_id = ?, comentarios_rh = ?, fecha_revision_rh = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sisi", $estado, $user_id, $comentarios, $solicitud_id);
        
        if ($update_stmt->execute()) {
            $success = 'La solicitud ha sido ' . $estado . ' correctamente.';
            
            // Registrar la actividad
            $accion_log = "Solicitud de baja " . $estado . " por RH";
            $tabla = "solicitudes_baja";
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("ississ", $user_id, $accion_log, $tabla, $solicitud_id, $comentarios, $ip);
            $log_stmt->execute();
        } else {
            $error = 'Error al procesar la solicitud: ' . $conn->error;
        }
    }
}

// Obtener todas las solicitudes de baja
// Corregida la consulta para evitar el error con u.nombre
$query = "SELECT sb.*, e.nombre, e.puesto, 
          (SELECT emp.nombre FROM empleados emp 
           JOIN usuarios u ON emp.num_ficha = u.num_ficha 
           WHERE u.id = sb.supervisor_id) as supervisor_nombre,
          (SELECT emp.nombre FROM empleados emp 
           JOIN usuarios u ON emp.num_ficha = u.num_ficha 
           WHERE u.id = sb.revisor_id) as revisor_nombre
          FROM solicitudes_baja sb
          JOIN empleados e ON sb.num_ficha = e.num_ficha
          WHERE sb.estado_revisor = 'aprobada'
          ORDER BY sb.estado_rh = 'pendiente' DESC, sb.fecha_solicitud DESC";
$result = $conn->query($query);
$bajas = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bajas[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Bajas - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="rh_bajas.php">Solicitudes de Baja</a>
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
        <h1>Gestión de Solicitudes de Baja</h1>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Lista de Solicitudes Aprobadas por Revisor</h5>
            </div>
            <div class="card-body">
                <?php if (empty($bajas)): ?>
                <div class="alert alert-info">No hay solicitudes aprobadas por revisor.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Empleado</th>
                                <th>Puesto</th>
                                <th>Supervisor</th>
                                <th>Revisor</th>
                                <th>Fecha Solicitud</th>
                                <th>Estado RH</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bajas as $baja): ?>
                            <tr>
                                <td><?php echo $baja['id']; ?></td>
                                <td><?php echo $baja['nombre']; ?></td>
                                <td><?php echo $baja['puesto']; ?></td>
                                <td><?php echo $baja['supervisor_nombre']; ?></td>
                                <td><?php echo $baja['revisor_nombre']; ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></td>
                                <td>
                                    <?php 
                                    $badge_class = '';
                                    switch ($baja['estado_rh']) {
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
                                        <?php echo ucfirst($baja['estado_rh']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($baja['estado_rh'] == 'pendiente'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRevisar<?php echo $baja['id']; ?>">
                                        Revisar
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalVer<?php echo $baja['id']; ?>">
                                        Ver Detalles
                                    </button>
                                    <?php endif; ?>
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
    
    <!-- Modales para revisar solicitudes pendientes -->
    <?php foreach ($bajas as $baja): ?>
    <?php if ($baja['estado_rh'] == 'pendiente'): ?>
    <div class="modal fade" id="modalRevisar<?php echo $baja['id']; ?>" tabindex="-1" aria-labelledby="modalRevisarLabel<?php echo $baja['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRevisarLabel<?php echo $baja['id']; ?>">Revisar Solicitud de Baja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $baja['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $baja['puesto']; ?></p>
                    <p><strong>Supervisor:</strong> <?php echo $baja['supervisor_nombre']; ?></p>
                    <p><strong>Motivo:</strong> <?php echo $baja['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></p>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Revisión del Revisor</h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $badge_class_revisor = '';
                            switch ($baja['estado_revisor']) {
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
                            <p><strong>Estado:</strong>
                                <span class="badge <?php echo $badge_class_revisor; ?>">
                                    <?php echo ucfirst($baja['estado_revisor']); ?>
                                </span>
                            </p>
                            <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_revisor'] ? $baja['comentarios_revisor'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_revisor'])); ?></p>
                        </div>
                    </div>
                    
                    <form method="post" action="rh_bajas.php">
                        <input type="hidden" name="solicitud_id" value="<?php echo $baja['id']; ?>">
                        <div class="mb-3">
                            <label for="comentarios<?php echo $baja['id']; ?>" class="form-label">Comentarios</label>
                            <textarea class="form-control" id="comentarios<?php echo $baja['id']; ?>" name="comentarios" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="accion" value="aprobar" class="btn btn-success">Aprobar</button>
                            <button type="submit" name="accion" value="rechazar" class="btn btn-danger">Rechazar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Modal para ver detalles de solicitudes ya procesadas -->
    <div class="modal fade" id="modalVer<?php echo $baja['id']; ?>" tabindex="-1" aria-labelledby="modalVerLabel<?php echo $baja['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVerLabel<?php echo $baja['id']; ?>">Detalles de la Solicitud de Baja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $baja['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $baja['puesto']; ?></p>
                    <p><strong>Supervisor:</strong> <?php echo $baja['supervisor_nombre']; ?></p>
                    <p><strong>Motivo:</strong> <?php echo $baja['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></p>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Revisión del Revisor</h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $badge_class_revisor = '';
                            switch ($baja['estado_revisor']) {
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
                            <p><strong>Estado:</strong>
                                <span class="badge <?php echo $badge_class_revisor; ?>">
                                    <?php echo ucfirst($baja['estado_revisor']); ?>
                                </span>
                            </p>
                            <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_revisor'] ? $baja['comentarios_revisor'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_revisor'])); ?></p>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Su Decisión</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Estado:</strong>
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo ucfirst($baja['estado_rh']); ?>
                                </span>
                            </p>
                            <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_rh'] ? $baja['comentarios_rh'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Procesamiento:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_rh'])); ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>