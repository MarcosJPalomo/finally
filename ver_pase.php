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
$pase = null;

// Verificar si se proporciona un ID de pase
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de pase no proporcionado.';
} else {
    $pase_id = $_GET['id'];
    
    // Consulta base para obtener la información del pase
    $query_base = "SELECT p.*, e.nombre, e.puesto, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = p.seguridad_id) as seguridad_nombre,
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = p.rh_id) as rh_nombre
                  FROM pases_entrada_salida p
                  JOIN empleados e ON p.num_ficha = e.num_ficha
                  WHERE p.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'empleado' || $tipo_usuario == 'supervisor') {
        $query .= " AND p.num_ficha = (SELECT num_ficha FROM usuarios WHERE id = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $pase_id, $user_id);
    } else {
        // RH y seguridad pueden ver todos los pases
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $pase_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = 'Pase no encontrado o no tiene permisos para verlo.';
    } else {
        $pase = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Pase - Sistema de Gestión de RH</title>
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
                    <?php if ($tipo_usuario == 'empleado' || $tipo_usuario == 'supervisor'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="pases.php">Pases de Entrada/Salida</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_pases.php">Gestión de Pases</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'seguridad'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="seguridad_pases.php">Control de Pases</a>
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
            <h1>Detalles de Pase</h1>
            <?php if ($tipo_usuario == 'rh'): ?>
            <a href="rh_pases.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php elseif ($tipo_usuario == 'seguridad'): ?>
            <a href="seguridad_pases.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php else: ?>
            <a href="pases.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($pase): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información del Pase</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Pase:</strong> <?php echo $pase['id']; ?></p>
                        <p><strong>Empleado:</strong> <?php echo $pase['nombre']; ?></p>
                        <p><strong>Puesto:</strong> <?php echo $pase['puesto']; ?></p>
                        <p><strong>Número de Ficha:</strong> <?php echo $pase['num_ficha']; ?></p>
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
                    </div>
                    <div class="col-md-6">
                        <p><strong>Fecha de Pase:</strong> <?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></p>
                        
                        <?php if ($pase['hora_salida']): ?>
                        <p><strong>Hora de Salida Programada:</strong> <?php echo date('H:i', strtotime($pase['hora_salida'])); ?></p>
                        <?php endif; ?>
                        
                        <?php if ($pase['hora_regreso']): ?>
                        <p><strong>Hora de Regreso Programada:</strong> <?php echo date('H:i', strtotime($pase['hora_regreso'])); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['fecha_solicitud'])); ?></p>
                        
                        <p><strong>Estado:</strong>
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
                        </p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p><strong>Motivo:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($pase['motivo']); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($pase['comentarios'])): ?>
                <div class="mt-3">
                    <p><strong>Comentarios de RH:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($pase['comentarios']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($pase['estado'] == 'aprobada'): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">Registro de Entrada/Salida</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <?php if ($pase['hora_salida_real']): ?>
                                <p><strong>Hora de Salida Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_salida_real'])); ?></p>
                                <?php else: ?>
                                <p><strong>Hora de Salida Real:</strong> <span class="text-muted">No registrada</span></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($pase['hora_regreso_real']): ?>
                                <p><strong>Hora de Regreso Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_regreso_real'])); ?></p>
                                <?php else: ?>
                                <p><strong>Hora de Regreso Real:</strong> <span class="text-muted">No registrada</span></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php if ($pase['excedio_tiempo']): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="bi bi-exclamation-triangle"></i> <strong>Nota:</strong> Se excedió el tiempo autorizado para el regreso.
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($pase['seguridad_nombre']): ?>
                        <p class="mt-3"><strong>Registrado por:</strong> <?php echo $pase['seguridad_nombre']; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($pase['rh_id'] && $pase['fecha_revision']): ?>
                <div class="card mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Revisión por RH</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Revisado por:</strong> <?php echo $pase['rh_nombre'] ? $pase['rh_nombre'] : 'No disponible'; ?></p>
                        <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['fecha_revision'])); ?></p>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>