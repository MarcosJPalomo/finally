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

// Procesar el formulario de solicitud de alta
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $puesto_requerido = trim($_POST['puesto_requerido']);
    $caracteristicas = trim($_POST['caracteristicas']);
    $cantidad_personas = (int)$_POST['cantidad_personas'];
    
    // Validaciones básicas
    if (empty($puesto_requerido) || empty($caracteristicas) || $cantidad_personas < 1) {
        $error = 'Por favor, complete todos los campos obligatorios correctamente.';
    } else {
        // Insertar la solicitud de alta
        $insert_query = "INSERT INTO solicitudes_alta (puesto_requerido, caracteristicas, cantidad_personas, supervisor_id) 
                       VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("ssis", $puesto_requerido, $caracteristicas, $cantidad_personas, $user_id);
        
        if ($insert_stmt->execute()) {
            $success = 'Solicitud de alta registrada correctamente.';
            
            // Registrar la actividad
            $accion = "Solicitud de alta";
            $tabla = "solicitudes_alta";
            $registro_id = $conn->insert_id;
            $detalles = "Puesto: $puesto_requerido, Cantidad: $cantidad_personas";
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

// Obtener información del usuario (para mostrar su nombre en el formulario)
$user_query = "SELECT u.*, e.nombre, e.puesto FROM usuarios u 
               JOIN empleados e ON u.num_ficha = e.num_ficha 
               WHERE u.id = ?";
$user_stmt = $conn->prepare($user_query);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Obtener lista de solicitudes de alta del supervisor
$query = "SELECT * FROM solicitudes_alta
          WHERE supervisor_id = ?
          ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$altas = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $altas[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Altas - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="altas.php">Solicitud de Altas</a>
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
        <h1>Solicitud de Altas de Personal</h1>
        
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
                        <h5 class="mb-0">Nueva Solicitud de Alta</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="altas.php">
                            <div class="mb-3">
                                <label for="supervisor" class="form-label">Supervisor Solicitante</label>
                                <input type="text" class="form-control" id="supervisor" value="<?php echo isset($user['nombre']) ? $user['nombre'] : ''; ?>" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="puesto_requerido" class="form-label">Puesto Requerido</label>
                                <input type="text" class="form-control" id="puesto_requerido" name="puesto_requerido" required>
                            </div>
                            <div class="mb-3">
                                <label for="caracteristicas" class="form-label">Características/Aptitudes</label>
                                <textarea class="form-control" id="caracteristicas" name="caracteristicas" rows="4" required></textarea>
                                <div class="form-text">Describa las características, aptitudes y requisitos necesarios para el puesto.</div>
                            </div>
                            <div class="mb-3">
                                <label for="cantidad_personas" class="form-label">Cantidad de Personas</label>
                                <input type="number" class="form-control" id="cantidad_personas" name="cantidad_personas" min="1" value="1" required>
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
                        <?php if (empty($altas)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Puesto</th>
                                        <th>Cantidad</th>
                                        <th>Fecha Solicitud</th>
                                        <th>Estado Revisor</th>
                                        <th>Estado RH</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($altas as $alta): ?>
                                    <tr>
                                        <td><?php echo $alta['id']; ?></td>
                                        <td><?php echo $alta['puesto_requerido']; ?></td>
                                        <td><?php echo $alta['cantidad_personas']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($alta['fecha_solicitud'])); ?></td>
                                        <td>
                                            <?php 
                                            $badge_class_revisor = '';
                                            switch ($alta['estado_revisor']) {
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
                                                <?php echo ucfirst($alta['estado_revisor']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $badge_class_rh = '';
                                            switch ($alta['estado_rh']) {
                                                case 'pendiente':
                                                    $badge_class_rh = 'bg-warning';
                                                    break;
                                                case 'aprobada':
                                                    $badge_class_rh = 'bg-success';
                                                    break;
                                                case 'rechazada':
                                                    $badge_class_rh = 'bg-danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class_rh; ?>">
                                                <?php echo ucfirst($alta['estado_rh']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $alta['id']; ?>">
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
    <?php foreach ($altas as $alta): ?>
    <div class="modal fade" id="modalDetalles<?php echo $alta['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $alta['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel<?php echo $alta['id']; ?>">Detalles de la Solicitud de Alta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Puesto Requerido:</strong> <?php echo $alta['puesto_requerido']; ?></p>
                    <p><strong>Cantidad de Personas:</strong> <?php echo $alta['cantidad_personas']; ?></p>
                    <p><strong>Características/Aptitudes:</strong></p>
                    <div class="card mb-3">
                        <div class="card-body">
                            <?php echo nl2br($alta['caracteristicas']); ?>
                        </div>
                    </div>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_solicitud'])); ?></p>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estado Revisor</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <span class="badge <?php echo $badge_class_revisor; ?>">
                                    <?php echo ucfirst($alta['estado_revisor']); ?>
                                </span>
                            </p>
                            <?php if ($alta['estado_revisor'] != 'pendiente' && $alta['fecha_revision_revisor']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $alta['comentarios_revisor'] ? $alta['comentarios_revisor'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_revision_revisor'])); ?></p>
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
                                    <?php echo ucfirst($alta['estado_rh']); ?>
                                </span>
                            </p>
                            <?php if ($alta['estado_rh'] != 'pendiente' && $alta['fecha_revision_rh']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $alta['comentarios_rh'] ? $alta['comentarios_rh'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_revision_rh'])); ?></p>
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
</body>
</html>