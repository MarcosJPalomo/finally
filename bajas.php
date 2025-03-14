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

// Procesar el formulario de solicitud de baja
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $num_ficha = trim($_POST['num_ficha']);
    $motivo = trim($_POST['motivo']);
    
    // Validaciones básicas
    if (empty($num_ficha) || empty($motivo)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
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
            // Verificar si ya existe una solicitud pendiente para este empleado
            $check_pendiente_query = "SELECT * FROM solicitudes_baja 
                                     WHERE num_ficha = ? AND (estado_revisor = 'pendiente' OR estado_rh = 'pendiente')";
            $check_pendiente_stmt = $conn->prepare($check_pendiente_query);
            $check_pendiente_stmt->bind_param("s", $num_ficha);
            $check_pendiente_stmt->execute();
            $check_pendiente_result = $check_pendiente_stmt->get_result();
            
            if ($check_pendiente_result->num_rows > 0) {
                $error = 'Ya existe una solicitud de baja pendiente para este empleado.';
            } else {
                // Insertar la solicitud de baja
                $insert_query = "INSERT INTO solicitudes_baja (num_ficha, motivo, supervisor_id) 
                               VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_query);
                $insert_stmt->bind_param("ssi", $num_ficha, $motivo, $user_id);
                
                if ($insert_stmt->execute()) {
                    $success = 'Solicitud de baja registrada correctamente.';
                    
                    // Registrar la actividad
                    $accion = "Solicitud de baja";
                    $tabla = "solicitudes_baja";
                    $registro_id = $conn->insert_id;
                    $detalles = "Empleado: $num_ficha, Motivo: $motivo";
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
}

// Obtener lista de solicitudes de baja del supervisor
$query = "SELECT sb.*, e.nombre, e.puesto 
          FROM solicitudes_baja sb
          JOIN empleados e ON sb.num_ficha = e.num_ficha
          WHERE sb.supervisor_id = ?
          ORDER BY sb.fecha_solicitud DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bajas = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $bajas[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Bajas - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="bajas.php">Solicitud de Bajas</a>
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
        <h1>Solicitud de Bajas</h1>
        
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
                        <h5 class="mb-0">Nueva Solicitud de Baja</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="bajas.php">
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
                                <label for="motivo" class="form-label">Motivo de la Baja</label>
                                <textarea class="form-control" id="motivo" name="motivo" rows="4" required></textarea>
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
                        <?php if (empty($bajas)): ?>
                        <div class="alert alert-info">No hay solicitudes registradas.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empleado</th>
                                        <th>Puesto</th>
                                        <th>Fecha Solicitud</th>
                                        <th>Estado Revisor</th>
                                        <th>Estado RH</th>
                                        <th>Detalles</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bajas as $baja): ?>
                                    <tr>
                                        <td><?php echo $baja['id']; ?></td>
                                        <td><?php echo $baja['nombre']; ?></td>
                                        <td><?php echo $baja['puesto']; ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></td>
                                        <td>
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
                                            <span class="badge <?php echo $badge_class_revisor; ?>">
                                                <?php echo ucfirst($baja['estado_revisor']); ?>
                                            </span>
                                        </td>
                                        <td>
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
                                            <span class="badge <?php echo $badge_class_rh; ?>">
                                                <?php echo ucfirst($baja['estado_rh']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $baja['id']; ?>">
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
    
    <!-- Modales para ver detalles de las solicitudes -->
    <?php foreach ($bajas as $baja): ?>
    <div class="modal fade" id="modalDetalles<?php echo $baja['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $baja['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel<?php echo $baja['id']; ?>">Detalles de la Solicitud de Baja</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $baja['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $baja['puesto']; ?></p>
                    <p><strong>Motivo:</strong> <?php echo $baja['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_solicitud'])); ?></p>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estado Revisor</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <span class="badge <?php echo $badge_class_revisor; ?>">
                                    <?php echo ucfirst($baja['estado_revisor']); ?>
                                </span>
                            </p>
                            <?php if ($baja['estado_revisor'] != 'pendiente' && $baja['fecha_revision_revisor']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_revisor'] ? $baja['comentarios_revisor'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_revisor'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">Estado RH</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <span class="badge <?php echo $badge_class_rh; ?>">
                                    <?php echo ucfirst($baja['estado_rh']); ?>
                                </span>
                            </p>
                            <?php if ($baja['estado_rh'] != 'pendiente' && $baja['fecha_revision_rh']): ?>
                            <p><strong>Comentarios:</strong> <?php echo $baja['comentarios_rh'] ? $baja['comentarios_rh'] : 'Sin comentarios'; ?></p>
                            <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($baja['fecha_revision_rh'])); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
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