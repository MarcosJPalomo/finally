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
$baja = null;

// Verificar si se proporciona un ID de baja
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de solicitud de baja no proporcionado.';
} else {
    $baja_id = $_GET['id'];
    
    // Consulta base para obtener la información de la baja
    $query_base = "SELECT sb.*, e.nombre, e.puesto, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sb.supervisor_id) as supervisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sb.revisor_id) as revisor_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = sb.rh_id) as rh_nombre
                  FROM solicitudes_baja sb
                  JOIN empleados e ON sb.num_ficha = e.num_ficha
                  WHERE sb.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'supervisor') {
        $query .= " AND sb.supervisor_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $baja_id, $user_id);
    } elseif ($tipo_usuario == 'revisor') {
        // Los revisores pueden ver todas las solicitudes, pero especialmente las que han revisado
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $baja_id);
    } elseif ($tipo_usuario == 'rh') {
        // RH puede ver todas las solicitudes, especialmente las aprobadas por revisores
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $baja_id);
    } else {
        $error = 'No tiene permisos para ver esta información.';
    }
    
    if (empty($error)) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            $error = 'Solicitud de baja no encontrada o no tiene permisos para verla.';
        } else {
            $baja = $result->fetch_assoc();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Solicitud de Baja - Sistema de Gestión de RH</title>
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
                        <a class="nav-link" href="bajas.php">Solicitudes de Baja</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'revisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="revisor_bajas.php">Revisión de Bajas</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_bajas.php">Gestión de Bajas</a>
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
            <h1>Detalles de Solicitud de Baja</h1>
            <?php if ($tipo_usuario == 'supervisor'): ?>
            <a href="bajas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'revisor'): ?>
            <a href="revisor_bajas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'rh'): ?>
            <a href="rh_bajas.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($baja): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información de la Solicitud</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Solicitud:</strong> <?php echo $baja['id']; ?></p>
                        <p><strong>Empleado:</strong> <?php echo $baja['nombre']; ?></p>
                        <p><strong>Puesto:</strong> <?php echo $baja['puesto']; ?></p>
                        <p><strong>Número de Ficha:</strong> <?php echo $baja['num_ficha']; ?></p>
                        <p><strong>Supervisor Solicitante:</strong> <?php echo $baja['supervisor_nombre']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p><strong>Motivo de la Baja:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($baja['motivo']); ?>
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
                                
                                <?php if ($baja['estado_revisor'] != 'pendiente' && $baja['fecha_revision_revisor']): ?>
                                <p><strong>Revisor:</strong> <?php echo $baja['revisor_nombre'] ? $baja['revisor_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_revisor'] ? nl2br($baja['comentarios_revisor']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_revisor'])); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Recursos Humanos</h6>
                                <?php 
                                $badge_class_rh = '';
                                switch ($baja['estado_rh']) {
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
                                        <?php echo ucfirst($baja['estado_rh']); ?>
                                    </span>
                                </p>
                                
                                <?php if ($baja['estado_rh'] != 'pendiente' && $baja['fecha_revision_rh']): ?>
                                <p><strong>Responsable RH:</strong> <?php echo $baja['rh_nombre'] ? $baja['rh_nombre'] : 'No disponible'; ?></p>
                                <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_rh'] ? nl2br($baja['comentarios_rh']) : 'Sin comentarios'; ?></p>
                                <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_rh'])); ?></p>
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