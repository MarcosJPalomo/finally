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
$solicitud = null;

// Verificar si se proporciona un ID de solicitud
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de solicitud no proporcionado.';
} else {
    $solicitud_id = $_GET['id'];
    
    // Consulta base para obtener la información de la solicitud
    $query_base = "SELECT sv.*, e.nombre, e.puesto, 
                   (SELECT emp.nombre FROM empleados emp 
                    JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                    WHERE u.id = sv.supervisor_id) as supervisor_nombre,
                   (SELECT emp.nombre FROM empleados emp 
                    JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                    WHERE u.id = sv.rh_id) as rh_nombre
                   FROM solicitudes_vacaciones sv
                   JOIN empleados e ON sv.num_ficha = e.num_ficha
                   WHERE sv.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'empleado') {
        $query .= " AND sv.num_ficha = (SELECT num_ficha FROM usuarios WHERE id = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $solicitud_id, $user_id);
    } elseif ($tipo_usuario == 'supervisor') {
        $query .= " AND sv.supervisor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $solicitud_id, $user_id);
    } else {
        // RH puede ver todas las solicitudes
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $solicitud_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = 'Solicitud no encontrada o no tiene permisos para verla.';
    } else {
        $solicitud = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Solicitud de Vacaciones - Sistema de Gestión de RH</title>
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
                        <a class="nav-link" href="vacaciones.php">Solicitudes de Vacaciones</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_vacaciones.php">Gestión de Vacaciones</a>
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
            <h1>Detalles de Solicitud de Vacaciones</h1>
            <a href="<?php echo $tipo_usuario == 'rh' ? 'rh_vacaciones.php' : ($tipo_usuario == 'supervisor' ? 'vacaciones.php' : 'index.php'); ?>" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($solicitud): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Solicitud</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Solicitud:</strong> <?php echo $solicitud['id']; ?></p>
                        <p><strong>Empleado:</strong> <?php echo $solicitud['nombre']; ?></p>
                        <p><strong>Puesto:</strong> <?php echo $solicitud['puesto']; ?></p>
                        <p><strong>Supervisor:</strong> <?php echo $solicitud['supervisor_nombre']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></p>
                        <p><strong>Fecha de Inicio:</strong> <?php echo date('d/m/Y', strtotime($solicitud['fecha_inicio'])); ?></p>
                        <p><strong>Fecha de Regreso:</strong> <?php echo date('d/m/Y', strtotime($solicitud['fecha_regreso'])); ?></p>
                        <?php 
                        // Calcular días de vacaciones
                        $inicio = new DateTime($solicitud['fecha_inicio']);
                        $regreso = new DateTime($solicitud['fecha_regreso']);
                        $diferencia = $inicio->diff($regreso);
                        $dias = $diferencia->days + 1; // Incluir el día de regreso
                        ?>
                        <p><strong>Total de Días:</strong> <?php echo $dias; ?> días</p>
                    </div>
                </div>
                
                <?php if (!empty($solicitud['comentarios'])): ?>
                <div class="mt-3">
                    <p><strong>Comentarios:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($solicitud['comentarios']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mt-3">
                    <p><strong>Estado:</strong>
                        <?php 
                        $badge_class = '';
                        switch ($solicitud['estado']) {
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
                            <?php echo ucfirst($solicitud['estado']); ?>
                        </span>
                    </p>
                </div>
                
                <?php if ($solicitud['estado'] != 'pendiente' && !empty($solicitud['fecha_revision'])): ?>
                <div class="mt-3">
                    <h6>Revisión por RH</h6>
                    <p><strong>Revisado por:</strong> <?php echo $solicitud['rh_nombre'] ? $solicitud['rh_nombre'] : 'No disponible'; ?></p>
                    <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_revision'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>