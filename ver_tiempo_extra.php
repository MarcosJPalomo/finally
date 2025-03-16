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
    $error = 'ID de solicitud de tiempo extra no proporcionado.';
} else {
    $solicitud_id = $_GET['id'];
    
    // Consulta base para obtener la información de la solicitud
    $query_base = "SELECT te.*, e.nombre, e.puesto, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = te.supervisor_id) as supervisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = te.revisor_id) as revisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = te.rh_id) as rh_nombre
                  FROM tiempo_extra te
                  JOIN empleados e ON te.num_ficha = e.num_ficha
                  WHERE te.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'supervisor') {
        $query .= " AND te.supervisor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $solicitud_id, $user_id);
    } elseif ($tipo_usuario == 'revisor') {
        // Los revisores pueden ver todas las solicitudes, pero especialmente las que han revisado
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $solicitud_id);
    } elseif ($tipo_usuario == 'rh') {
        // RH puede ver todas las solicitudes, especialmente las aprobadas por revisores
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $solicitud_id);
    } else {
        $error = 'No tiene permisos para ver esta información.';
    }
    
    if (empty($error)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = 'Solicitud de tiempo extra no encontrada o no tiene permisos para verla.';
        } else {
            $solicitud = $result->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Solicitud de Tiempo Extra - Sistema de Gestión de RH</title>
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
                        <a class="nav-link" href="tiempo_extra.php">Solicitud de Tiempo Extra</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'revisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="revisor_tiempo_extra.php">Revisión de Tiempo Extra</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_tiempo_extra.php">Procesamiento de Tiempo Extra</a>
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
            <h1>Detalles de Tiempo Extra</h1>
            <?php if ($tipo_usuario == 'supervisor'): ?>
            <a href="tiempo_extra.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'revisor'): ?>
            <a href="revisor_tiempo_extra.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'rh'): ?>
            <a href="rh_tiempo_extra.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
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
                        <p><strong>Supervisor:</strong> <?php echo $solicitud['supervisor_nombre']; ?></p>
                        <p><strong>Número de Ficha:</strong> <?php echo $solicitud['num_ficha']; ?></p>
                        <p><strong>Semana que inicia el:</strong> <?php echo date('d/m/Y', strtotime($solicitud['semana_inicio'])); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Horas Extra:</strong> <?php echo $solicitud['horas_extra']; ?></p>
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p><strong>Motivo:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($solicitud['motivo']); ?>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Estado de la Solicitud</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Revisor</h6>
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
                                <p><strong>Estado:</strong>
                                    <span class="badge <?php echo $badge_class_revisor; ?>">
                                        <?php echo ucfirst($solicitud['estado_revisor']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($solicitud['estado_revisor'] != 'pendiente' && $solicitud['fecha_revision_revisor']): ?>
                                <p><strong>Revisor:</strong> <?php echo $solicitud['revisor_nombre'] ? $solicitud['revisor_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $solicitud['comentarios_revisor'] ? nl2br($solicitud['comentarios_revisor']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_revision_revisor'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Recursos Humanos</h6>
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
                                <p><strong>Estado:</strong>
                                    <span class="badge <?php echo $badge_class_rh; ?>">
                                        <?php echo ucfirst($solicitud['estado_rh']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($solicitud['estado_rh'] != 'pendiente' && $solicitud['fecha_revision_rh']): ?>
                                <p><strong>Responsable RH:</strong> <?php echo $solicitud['rh_nombre'] ? $solicitud['rh_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $solicitud['comentarios_rh'] ? nl2br($solicitud['comentarios_rh']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Procesamiento:</strong> <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_revision_rh'])); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>