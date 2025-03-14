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
$prestamo = null;

// Verificar si se proporciona un ID de préstamo
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $error = 'ID de préstamo no proporcionado.';
} else {
    $prestamo_id = $_GET['id'];
    
    // Consulta base para obtener la información del préstamo
    $query_base = "SELECT p.*, e.nombre, e.puesto, 
                  (SELECT emp.nombre FROM empleados emp 
                   JOIN usuarios u ON emp.num_ficha = u.num_ficha 
                   WHERE u.id = p.rh_id) as rh_nombre
                  FROM prestamos p
                  JOIN empleados e ON p.num_ficha = e.num_ficha
                  WHERE p.id = ?";
    
    // Restricciones adicionales según el tipo de usuario
    $query = $query_base;
    if ($tipo_usuario == 'empleado') {
        $query .= " AND p.num_ficha = (SELECT num_ficha FROM usuarios WHERE id = ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $prestamo_id, $user_id);
    } else {
        // RH puede ver todos los préstamos
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $prestamo_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows == 0) {
        $error = 'Préstamo no encontrado o no tiene permisos para verlo.';
    } else {
        $prestamo = $result->fetch_assoc();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Préstamo - Sistema de Gestión de RH</title>
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
                    <?php if ($tipo_usuario == 'empleado'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="prestamos.php">Préstamos</a>
                    </li>
                    <?php elseif ($tipo_usuario == 'rh'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="rh_prestamos.php">Gestión de Préstamos</a>
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
            <h1>Detalles de Préstamo</h1>
            <?php if ($tipo_usuario == 'rh'): ?>
            <a href="rh_prestamos.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php else: ?>
            <a href="prestamos.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php elseif ($prestamo): ?>
        
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Información del Préstamo</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>ID de Préstamo:</strong> <?php echo $prestamo['id']; ?></p>
                        <p><strong>Empleado:</strong> <?php echo $prestamo['nombre']; ?></p>
                        <p><strong>Puesto:</strong> <?php echo $prestamo['puesto']; ?></p>
                        <p><strong>Número de Ficha:</strong> <?php echo $prestamo['num_ficha']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Cantidad Solicitada:</strong> $<?php echo number_format($prestamo['cantidad'], 2); ?></p>
                        <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></p>
                        <p><strong>Estado:</strong>
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
                        </p>
                    </div>
                </div>
                
                <div class="mt-3">
                    <p><strong>Motivo:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($prestamo['motivo']); ?>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($prestamo['comentarios'])): ?>
                <div class="mt-3">
                    <p><strong>Comentarios de RH:</strong></p>
                    <div class="card">
                        <div class="card-body bg-light">
                            <?php echo nl2br($prestamo['comentarios']); ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($prestamo['rh_id'] && $prestamo['fecha_revision']): ?>
                <div class="mt-3">
                    <p><strong>Revisado por:</strong> <?php echo $prestamo['rh_nombre'] ? $prestamo['rh_nombre'] : 'No disponible'; ?></p>
                    <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_revision'])); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>