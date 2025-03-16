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
$user_id = $_SESSION['user_id'];

// Procesar la aprobación o rechazo de solicitudes
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $solicitud_id = $_POST['solicitud_id'];
    $accion = $_POST['accion'];
    $comentarios = trim($_POST['comentarios']);
    
    if ($accion == 'aprobar' || $accion == 'rechazar') {
        $estado = ($accion == 'aprobar') ? 'aprobada' : 'rechazada';
        
        $update_query = "UPDATE prestamos 
                        SET estado = ?, rh_id = ?, comentarios = ?, fecha_revision = NOW() 
                        WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("sisi", $estado, $user_id, $comentarios, $solicitud_id);
        
        if ($update_stmt->execute()) {
            $success = 'La solicitud ha sido ' . $estado . ' correctamente.';
            
            // Registrar la actividad
            $accion_log = "Solicitud de préstamo " . $estado;
            $tabla = "prestamos";
            $ip = $_SERVER['REMOTE_ADDR'];
            
            $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->bind_param("ississ", $user_id, $accion_log, $tabla, $solicitud_id, $comentarios, $ip);
            $log_stmt->execute();
        } else {
            $error = 'Error al procesar la solicitud: ' . $conn->error;
        }
    }
}

// Obtener todas las solicitudes de préstamos
$query = "SELECT p.*, e.nombre, e.puesto 
          FROM prestamos p
          JOIN empleados e ON p.num_ficha = e.num_ficha
          ORDER BY p.estado = 'pendiente' DESC, p.fecha_solicitud DESC";
$result = $conn->query($query);
$prestamos = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $prestamos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Préstamos - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="rh_prestamos.php">Préstamos</a>
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
        <h1>Gestión de Préstamos</h1>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Lista de Solicitudes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($prestamos)): ?>
                <div class="alert alert-info">No hay solicitudes registradas.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Empleado</th>
                                <th>Puesto</th>
                                <th>Cantidad</th>
                                <th>Fecha Solicitud</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $prestamo): ?>
                            <tr>
                                <td><?php echo $prestamo['id']; ?></td>
                                <td><?php echo $prestamo['nombre']; ?></td>
                                <td><?php echo $prestamo['puesto']; ?></td>
                                <td>$<?php echo number_format($prestamo['cantidad'], 2); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></td>
                                <td>
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
                                </td>
                                <td>
                                    <?php if ($prestamo['estado'] == 'pendiente'): ?>
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalRevisar<?php echo $prestamo['id']; ?>">
                                        Revisar
                                    </button>
                                    <?php else: ?>
                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalVer<?php echo $prestamo['id']; ?>">
                                        Ver Detalles
                                    </button>
                                    <?php endif; ?>
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
    
    <!-- Modales para revisar solicitudes pendientes -->
    <?php foreach ($prestamos as $prestamo): ?>
    <?php if ($prestamo['estado'] == 'pendiente'): ?>
    <div class="modal fade" id="modalRevisar<?php echo $prestamo['id']; ?>" tabindex="-1" aria-labelledby="modalRevisarLabel<?php echo $prestamo['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalRevisarLabel<?php echo $prestamo['id']; ?>">Revisar Solicitud de Préstamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $prestamo['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $prestamo['puesto']; ?></p>
                    <p><strong>Número de Ficha:</strong> <?php echo $prestamo['num_ficha']; ?></p>
                    <p><strong>Cantidad Solicitada:</strong> $<?php echo number_format($prestamo['cantidad'], 2); ?></p>
                    <p><strong>Motivo:</strong> <?php echo $prestamo['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></p>
                    
                    <form method="post" action="rh_prestamos.php">
                        <input type="hidden" name="solicitud_id" value="<?php echo $prestamo['id']; ?>">
                        <div class="mb-3">
                            <label for="comentarios<?php echo $prestamo['id']; ?>" class="form-label">Comentarios</label>
                            <textarea class="form-control" id="comentarios<?php echo $prestamo['id']; ?>" name="comentarios" rows="3"></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="accion" value="aprobar" class="btn btn-success">Aprobar</button>
                            <button type="submit" name="accion" value="rechazar" class="btn btn-danger">Rechazar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Modal para ver detalles de solicitudes ya procesadas -->
    <div class="modal fade" id="modalVer<?php echo $prestamo['id']; ?>" tabindex="-1" aria-labelledby="modalVerLabel<?php echo $prestamo['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalVerLabel<?php echo $prestamo['id']; ?>">Detalles del Préstamo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Empleado:</strong> <?php echo $prestamo['nombre']; ?></p>
                    <p><strong>Puesto:</strong> <?php echo $prestamo['puesto']; ?></p>
                    <p><strong>Número de Ficha:</strong> <?php echo $prestamo['num_ficha']; ?></p>
                    <p><strong>Cantidad Solicitada:</strong> $<?php echo number_format($prestamo['cantidad'], 2); ?></p>
                    <p><strong>Motivo:</strong> <?php echo $prestamo['motivo']; ?></p>
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_solicitud'])); ?></p>
                    
                    <p><strong>Estado:</strong> 
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo ucfirst($prestamo['estado']); ?>
                        </span>
                    </p>
                    
                    <?php if ($prestamo['comentarios']): ?>
                    <p><strong>Comentarios:</strong> <?php echo $prestamo['comentarios']; ?></p>
                    <?php endif; ?>
                    
                    <?php if ($prestamo['fecha_revision']): ?>
                    <p><strong>Fecha de Revisión:</strong> <?php echo date('d/m/Y H:i', strtotime($prestamo['fecha_revision'])); ?></p>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- Modal de Confirmación para incluir en todas las páginas que requieren aprobación/rechazo -->
<div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="confirmacionModalLabel">Confirmar Acción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="confirmacionMensaje">¿Está seguro que desea realizar esta acción?</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="confirmarAccionBtn">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<!-- Script para manejar los eventos de confirmación -->
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Variables para almacenar el formulario y la acción a realizar
    let formActual = null;
    let accionActual = null;
    
    // Capturar todos los botones de aprobar y rechazar
    const botonesAprobar = document.querySelectorAll('button[name="accion"][value="aprobar"]');
    const botonesRechazar = document.querySelectorAll('button[name="accion"][value="rechazar"]');
    
    // Configurar la confirmación para los botones de aprobar
    botonesAprobar.forEach(boton => {
      boton.addEventListener('click', function(e) {
        e.preventDefault();
        formActual = this.closest('form');
        accionActual = 'aprobar';
        
        // Actualizar el mensaje de confirmación
        document.getElementById('confirmacionMensaje').textContent = '¿Está seguro que desea APROBAR esta solicitud?';
        
        // Mostrar el modal de confirmación
        const modal = new bootstrap.Modal(document.getElementById('confirmacionModal'));
        modal.show();
      });
    });
    
    // Configurar la confirmación para los botones de rechazar
    botonesRechazar.forEach(boton => {
      boton.addEventListener('click', function(e) {
        e.preventDefault();
        formActual = this.closest('form');
        accionActual = 'rechazar';
        
        // Actualizar el mensaje de confirmación
        document.getElementById('confirmacionMensaje').textContent = '¿Está seguro que desea RECHAZAR esta solicitud?';
        
        // Mostrar el modal de confirmación
        const modal = new bootstrap.Modal(document.getElementById('confirmacionModal'));
        modal.show();
      });
    });
    
    // Configurar el botón de confirmar en el modal
    document.getElementById('confirmarAccionBtn').addEventListener('click', function() {
      if (formActual && accionActual) {
        // Crear un input oculto para enviar la acción
        const inputAccion = document.createElement('input');
        inputAccion.type = 'hidden';
        inputAccion.name = 'accion';
        inputAccion.value = accionActual;
        
        // Añadir el input al formulario
        formActual.appendChild(inputAccion);
        
        // Enviar el formulario
        formActual.submit();
      }
      
      // Cerrar el modal
      const modal = bootstrap.Modal.getInstance(document.getElementById('confirmacionModal'));
      modal.hide();
    });
  });
</script>
</body>
</html>