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

// Procesar el formulario de registro de empleado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $num_ficha = trim($_POST['num_ficha']);
    $nombre = trim($_POST['nombre']);
    $puesto = trim($_POST['puesto']);
    
    // Validaciones básicas
    if (empty($num_ficha) || empty($nombre) || empty($puesto)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        // Verificar si el número de ficha ya existe
        $check_query = "SELECT * FROM empleados WHERE num_ficha = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $num_ficha);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = 'El número de ficha ya está registrado.';
        } else {
            // Insertar el nuevo empleado
            $insert_query = "INSERT INTO empleados (num_ficha, nombre, puesto) VALUES (?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sss", $num_ficha, $nombre, $puesto);
            
            if ($insert_stmt->execute()) {
                $success = 'Empleado registrado correctamente.';
                
                // Registrar la actividad
                $user_id = $_SESSION['user_id'];
                $accion = "Registro de empleado";
                $tabla = "empleados";
                $registro_id = $conn->insert_id;
                $detalles = "Número de ficha: $num_ficha, Nombre: $nombre, Puesto: $puesto";
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("ississ", $user_id, $accion, $tabla, $registro_id, $detalles, $ip);
                $log_stmt->execute();
            } else {
                $error = 'Error al registrar el empleado: ' . $conn->error;
            }
        }
    }
}

// Obtener lista de empleados
$query = "SELECT * FROM empleados ORDER BY fecha_registro DESC";
$result = $conn->query($query);
$empleados = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $empleados[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Empleados - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="registro_empleados.php">Registro de Empleados</a>
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
        <h1>Registro de Empleados</h1>
        
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
                        <h5 class="mb-0">Nuevo Empleado</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="registro_empleados.php">
                            <div class="mb-3">
                                <label for="num_ficha" class="form-label">Número de Ficha</label>
                                <input type="text" class="form-control" id="num_ficha" name="num_ficha" required>
                            </div>
                            <div class="mb-3">
                                <label for="nombre" class="form-label">Nombre Completo</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="mb-3">
                                <label for="puesto" class="form-label">Puesto</label>
                                <input type="text" class="form-control" id="puesto" name="puesto" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Registrar Empleado</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Empleados Registrados</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($empleados)): ?>
                        <div class="alert alert-info">No hay empleados registrados.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Número de Ficha</th>
                                        <th>Nombre</th>
                                        <th>Puesto</th>
                                        <th>Fecha de Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($empleados as $empleado): ?>
                                    <tr>
                                        <td><?php echo $empleado['num_ficha']; ?></td>
                                        <td><?php echo $empleado['nombre']; ?></td>
                                        <td><?php echo $empleado['puesto']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($empleado['fecha_registro'])); ?></td>
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
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>