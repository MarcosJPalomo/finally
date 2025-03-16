<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado y es de tipo empleado
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'empleado') {
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

// Procesar el formulario de solicitud de préstamo
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $cantidad = (float)$_POST['cantidad'];
    $motivo = trim($_POST['motivo']);
    
    // Validaciones básicas
    if (empty($cantidad) || empty($motivo)) {
        $error = 'Por favor, complete todos los campos.';
    } else if ($cantidad <= 0) {
        $error = 'La cantidad debe ser mayor a cero.';
    } else {
        // Insertar la solicitud de préstamo
        $insert_query = "INSERT INTO prestamos (num_ficha, cantidad, motivo) 
                       VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_query);
        $insert_stmt->bind_param("sds", $num_ficha, $cantidad, $motivo);
        
        if ($insert_stmt->execute()) {
            $success = 'Solicitud de préstamo registrada correctamente.';
            
            // Registrar la actividad
            $accion = "Solicitud de préstamo";
            $tabla = "prestamos";
            $registro_id = $conn->insert_id;
            $detalles = "Cantidad: $cantidad, Motivo: $motivo";
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

// Obtener lista de préstamos del empleado
$query = "SELECT * FROM prestamos 
          WHERE num_ficha = ? 
          ORDER BY fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $num_ficha);
$stmt->execute();
$result = $stmt->get_result();
$prestamos = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $prestamos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Préstamos - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="prestamos.php">Préstamos</a>
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
        <h1>Solicitud de Préstamos</h1>
        
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
                        <h5 class="mb-0">Nueva Solicitud de Préstamo</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="prestamos.php">
                            <div class="mb-3">
                                <label for="cantidad" class="form-label">Cantidad Solicitada</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="cantidad" name="cantidad" step="0.01" min="0.01" required>
                                </div>
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
                        <h5 class="mb-0">Mis Solicitudes de Préstamos</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($prestamos)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha Solicitud</th>
                                        <th>Cantidad</th>
                                        <th>Estado</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($prestamos as $prestamo): ?>
                                    <tr>
                                        <td><?php echo $prestamo['id']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></td>
                                        <td>$<?php echo number_format($prestamo['cantidad'], 2); ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            switch ($prestamo['estado']) {
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
                                                <?php echo ucfirst($prestamo['estado']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $prestamo['id']; ?>">
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
    
    <!-- Modales para ver detalles de los préstamos -->
    <?php foreach ($prestamos as $prestamo): ?>
    <!-- Updated Modal for prestamos.php -->
<div class="modal fade" id="modalDetalles<?php echo $prestamo['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $prestamo['id']; ?>" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalDetallesLabel<?php echo $prestamo['id']; ?>">Detalles del Préstamo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p><strong>Número de Ficha:</strong> <?php echo $prestamo['num_ficha']; ?></p>
                <p><strong>Cantidad Solicitada:</strong> $<?php echo number_format($prestamo['cantidad'], 2); ?></p>
                <p><strong>Motivo:</strong> <?php echo $prestamo['motivo']; ?></p>
                <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></p>
                <p><strong>Estado:</strong> 
                    <span class="badge <?php echo $badge_class; ?>">
                        <?php echo ucfirst($prestamo['estado']); ?>
                    </span>
                </p>
                
                <?php if ($prestamo['comentarios']): ?>
                <p><strong>Comentarios de RH:</strong> <?php echo $prestamo['comentarios']; ?></p>
                <?php endif; ?>
                
                <?php if ($prestamo['fecha_revision']): ?>
                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_revision'])); ?></p>
                <?php endif; ?>
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