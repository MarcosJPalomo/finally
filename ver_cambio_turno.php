<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$tipo_usuario = $_SESSION['tipo_usuario'];
$error = '';
$cambio = null;

// Verificar si se proporciona un ID de cambio de turno
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de cambio de turno no proporcionado.';
} else {
    $cambio_id = $_GET['id'];
    
    // Consulta base para obtener la información del cambio de turno
    $query_base = "SELECT ct.*, e.nombre, e.puesto, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = ct.supervisor_id) as supervisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = ct.rh_id) as rh_nombre
                  FROM cambios_turno ct
                  JOIN empleados e ON ct.num_ficha = e.num_ficha
                  WHERE ct.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'supervisor') {
        $query .= " AND ct.supervisor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $cambio_id, $user_id);
    } else if ($tipo_usuario == 'rh') {
        // RH puede ver todos los cambios de turno
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $cambio_id);
    } else {
        $error = 'No tiene permisos para ver esta información.';
    }
    
    if (empty($error)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = 'Cambio de turno no encontrado o no tiene permisos para verlo.';
        } else {
            $cambio = $result->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Cambio de Turno - Sistema de Gestión de RH</title>
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
                    <?php if ($tipo_usuario == 'supervisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="cambios_turno.php">Cambios de Turno</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_cambios_turno.php">Gestión de Cambios de Turno</a>
                    </li>
                    <?php endif; ?>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1>Detalles de Cambio de Turno</h1>
            <?php if ($tipo_usuario == 'supervisor'): ?>
            <a href="cambios_turno.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'rh'): ?>
            <a href="rh_cambios_turno.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($cambio): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información del Cambio de Turno</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Solicitud:</strong> <?php echo $cambio['id']; ?></p>
                        <p><strong>Empleado:</strong> <?php echo $cambio['nombre']; ?></p>
                        <p><strong>Puesto:</strong> <?php echo $cambio['puesto']; ?></p>
                        <p><strong>Número de Ficha:</strong> <?php echo $cambio['num_ficha']; ?></p>
                        <p><strong>Supervisor:</strong> <?php echo $cambio['supervisor_nombre']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Turno Actual:</strong> <?php echo $cambio['turno_actual']; ?></p>
                        <p><strong>Turno Nuevo:</strong> <?php echo $cambio['turno_nuevo']; ?></p>
                        <p><strong>Fecha de Cambio:</strong> <?php echo date('d/m/Y', strtotime($cambio['fecha_cambio'])); ?></p>
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($cambio['fecha_solicitud'])); ?></p>
                        <p><strong>Estado:</strong>
                            <?php 
                            $badge_class = '';
                            switch ($cambio['estado']) {
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
                                <?php echo ucfirst($cambio['estado']); ?>
                            </span>
                        </p>
                    </div>
                </div>
                
                <?php if (!empty($cambio['comentarios'])): ?>
                <div class="mt-3">
                    <p><strong>Comentarios del Supervisor:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($cambio['comentarios']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($cambio['estado'] != 'pendiente' && !empty($cambio['fecha_revision'])): ?>
                <div class="mt-4">
                    <h5>Revisión por RH</h5>
                    <p><strong>Revisado por:</strong> <?php echo $cambio['rh_nombre'] ? $cambio['rh_nombre'] : 'No disponible'; ?></p>
                    <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($cambio['fecha_revision'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>