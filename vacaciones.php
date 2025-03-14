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

// Procesar el formulario de solicitud de vacaciones
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $num_ficha = trim($_POST['num_ficha']);
    $fecha_inicio = $_POST['fecha_inicio'];
    $fecha_regreso = $_POST['fecha_regreso'];
    $comentarios = trim($_POST['comentarios']);
    
    // Validaciones básicas
    if (empty($num_ficha) || empty($fecha_inicio) || empty($fecha_regreso)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } else if (strtotime($fecha_inicio) > strtotime($fecha_regreso)) {
        $error = 'La fecha de inicio no puede ser posterior a la fecha de regreso.';
    } else {
        // Verificar si el empleado existe
        $check_query = "SELECT * FROM empleados WHERE num_ficha = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $num_ficha);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $error = 'El número de ficha no existe en el sistema.';
        } else {
            // Insertar la solicitud de vacaciones
            $insert_query = "INSERT INTO solicitudes_vacaciones (num_ficha, fecha_inicio, fecha_regreso, supervisor_id, comentarios) 
                           VALUES (?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param("sssss", $num_ficha, $fecha_inicio, $fecha_regreso, $user_id, $comentarios);
            
            if ($insert_stmt->execute()) {
                $success = 'Solicitud de vacaciones registrada correctamente.';
                
                // Registrar la actividad
                $accion = "Solicitud de vacaciones";
                $tabla = "solicitudes_vacaciones";
                $registro_id = $conn->insert_id;
                $detalles = "Empleado: $num_ficha, Fecha inicio: $fecha_inicio, Fecha regreso: $fecha_regreso";
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
}

// Obtener lista de solicitudes de vacaciones del supervisor
$query = "SELECT sv.*, e.nombre, e.puesto 
          FROM solicitudes_vacaciones sv
          JOIN empleados e ON sv.num_ficha = e.num_ficha
          WHERE sv.supervisor_id = ?
          ORDER BY sv.fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$solicitudes = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $solicitudes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitudes de Vacaciones - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="vacaciones.php">Solicitudes de Vacaciones</a>
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
        <h1>Solicitudes de Vacaciones</h1>
        
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
                        <h5 class="mb-0">Nueva Solicitud de Vacaciones</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="vacaciones.php">
                            <div class="mb-3">
                                <label for="num_ficha" class="form-label">Número de Ficha</label>
                                <input type="text" class="form-control" id="num_ficha" name="num_ficha" required>
                            </div>
                            <div class="mb-3">
                                <label for="nombre_empleado" class="form-label">Nombre del Empleado</label>
                                <input type="text" class="form-control" id="nombre_empleado" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="puesto_empleado" class="form-label">Puesto</label>
                                <input type="text" class="form-control" id="puesto_empleado" readonly>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_inicio" class="form-label">Fecha de Inicio</label>
                                <input type="date" class="form-control" id="fecha_inicio" name="fecha_inicio" required>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_regreso" class="form-label">Fecha de Regreso</label>
                                <input type="date" class="form-control" id="fecha_regreso" name="fecha_regreso" required>
                            </div>
                            <div class="mb-3">
                                <label for="comentarios" class="form-label">Comentarios</label>
                                <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
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
                        <?php if (empty($solicitudes)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empleado</th>
                                        <th>Puesto</th>
                                        <th>Fecha Inicio</th>
                                        <th>Fecha Regreso</th>
                                        <th>Estado</th>
                                        <th>Fecha Solicitud</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($solicitudes as $solicitud): ?>
                                    <tr>
                                        <td><?php echo $solicitud['id']; ?></td>
                                        <td><?php echo $solicitud['nombre']; ?></td>
                                        <td><?php echo $solicitud['puesto']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_inicio'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($solicitud['fecha_regreso'])); ?></td>
                                        <td>
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
                                        </td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?></td>
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Función para buscar información del empleado por número de ficha
            document.getElementById('num_ficha').addEventListener('blur', function() {
                const numFicha = this.value.trim();
                
                if (numFicha === '') {
                    document.getElementById('nombre_empleado').value = '';
                    document.getElementById('puesto_empleado').value = '';
                    return;
                }
                
                // Realizar la solicitud AJAX
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'buscar_empleado.php?num_ficha=' + encodeURIComponent(numFicha), true);
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            if (response.success) {
                                document.getElementById('nombre_empleado').value = response.nombre || '';
                                document.getElementById('puesto_empleado').value = response.puesto || '';
                            } else {
                                document.getElementById('nombre_empleado').value = '';
                                document.getElementById('puesto_empleado').value = '';
                                alert('Empleado no encontrado.');
                            }
                        } catch (e) {
                            console.error('Error al procesar la respuesta:', e);
                        }
                    }
                };
                
                xhr.onerror = function() {
                    console.error('Error de conexión');
                };
                
                xhr.send();
            });
        });
    </script>
</body>
</html>