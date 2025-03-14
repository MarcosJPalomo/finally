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
$alta = null;

// Verificar si se proporciona un ID de alta
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de solicitud de alta no proporcionado.';
} else {
    $alta_id = $_GET['id'];
    
    // Consulta base para obtener la información de la alta
    $query_base = "SELECT sa.*, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sa.supervisor_id) as supervisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sa.revisor_id) as revisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sa.rh_id) as rh_nombre
                  FROM solicitudes_alta sa
                  WHERE sa.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'supervisor') {
        $query .= " AND sa.supervisor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $alta_id, $user_id);
    } elseif ($tipo_usuario == 'revisor') {
        // Los revisores pueden ver todas las solicitudes, pero especialmente las que han revisado
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $alta_id);
    } elseif ($tipo_usuario == 'rh') {
        // RH puede ver todas las solicitudes, especialmente las aprobadas por revisores
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $alta_id);
    } else {
        $error = 'No tiene permisos para ver esta información.';
    }
    
    if (empty($error)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = 'Solicitud de alta no encontrada o no tiene permisos para verla.';
        } else {
            $alta = $result->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Solicitud de Alta - Sistema de Gestión de RH</title>
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
                        <a class="nav-link" href="altas.php">Solicitudes de Alta</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'revisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="revisor_altas.php">Revisión de Altas</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_altas.php">Gestión de Altas</a>
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
            <h1>Detalles de Solicitud de Alta</h1>
            <?php if ($tipo_usuario == 'supervisor'): ?>
            <a href="altas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'revisor'): ?>
            <a href="revisor_altas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'rh'): ?>
            <a href="rh_altas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($alta): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Solicitud</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Solicitud:</strong> <?php echo $alta['id']; ?></p>
                        <p><strong>Puesto Requerido:</strong> <?php echo $alta['puesto_requerido']; ?></p>
                        <p><strong>Cantidad de Personas:</strong> <?php echo $alta['cantidad_personas']; ?></p>
                        <p><strong>Supervisor Solicitante:</strong> <?php echo $alta['supervisor_nombre']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_solicitud'])); ?></p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p><strong>Características/Aptitudes Requeridas:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($alta['caracteristicas']); ?>
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
                                <p><strong>Estado:</strong>
                                    <span class="badge <?php echo $badge_class_revisor; ?>">
                                        <?php echo ucfirst($alta['estado_revisor']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($alta['estado_revisor'] != 'pendiente' && $alta['fecha_revision_revisor']): ?>
                                <p><strong>Revisor:</strong> <?php echo $alta['revisor_nombre'] ? $alta['revisor_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $alta['comentarios_revisor'] ? nl2br($alta['comentarios_revisor']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_revision_revisor'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Recursos Humanos</h6>
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
                                <p><strong>Estado:</strong>
                                    <span class="badge <?php echo $badge_class_rh; ?>">
                                        <?php echo ucfirst($alta['estado_rh']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($alta['estado_rh'] != 'pendiente' && $alta['fecha_revision_rh']): ?>
                                <p><strong>Responsable RH:</strong> <?php echo $alta['rh_nombre'] ? $alta['rh_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $alta['comentarios_rh'] ? nl2br($alta['comentarios_rh']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($alta['fecha_revision_rh'])); ?></p>
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