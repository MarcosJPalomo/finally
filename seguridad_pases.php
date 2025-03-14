<?php
session_start();
require_once 'config/db.php';

// Verificar si el usuario está autenticado y es de tipo seguridad
if (!isset($_SESSION['user_id']) || $_SESSION['tipo_usuario'] !== 'seguridad') {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';
$user_id = $_SESSION['user_id'];
$fecha_actual = date('Y-m-d');

// Procesar la marcación de entrada o salida
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pase_id = $_POST['pase_id'];
    $accion = $_POST['accion'];
    
    // Obtener información del pase
    $pase_query = "SELECT * FROM pases_entrada_salida WHERE id = ?";
    $pase_stmt = $conn->prepare($pase_query);
    $pase_stmt->bind_param("i", $pase_id);
    $pase_stmt->execute();
    $pase_result = $pase_stmt->get_result();
    
    if ($pase_result->num_rows > 0) {
        $pase = $pase_result->fetch_assoc();
        
        if ($pase['estado'] != 'aprobada') {
            $error = 'Solo se pueden marcar pases aprobados.';
        } else {
            $fecha_hora_actual = date('Y-m-d H:i:s');
            $excedio_tiempo = false;
            
            if ($accion == 'marcar_salida') {
                // Marcar salida
                $update_query = "UPDATE pases_entrada_salida SET hora_salida_real = ?, seguridad_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("sii", $fecha_hora_actual, $user_id, $pase_id);
                $accion_log = "Marcación de salida";
            } else if ($accion == 'marcar_regreso') {
                // Marcar regreso/entrada
                // Verificar si excede el tiempo
                if ($pase['hora_regreso']) {
                    $hora_regreso_programada = strtotime($pase['fecha_pase'] . ' ' . $pase['hora_regreso']);
                    $hora_actual = strtotime($fecha_hora_actual);
                    
                    if ($hora_actual > $hora_regreso_programada) {
                        $excedio_tiempo = true;
                    }
                }
                
                $update_query = "UPDATE pases_entrada_salida SET hora_regreso_real = ?, excedio_tiempo = ?, seguridad_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_query);
                $update_stmt->bind_param("siii", $fecha_hora_actual, $excedio_tiempo, $user_id, $pase_id);
                $accion_log = "Marcación de regreso";
            }
            
            if ($update_stmt->execute()) {
                $success = 'Pase marcado correctamente.';
                
                // Registrar la actividad
                $tabla = "pases_entrada_salida";
                $detalles = "Hora: $fecha_hora_actual" . ($excedio_tiempo ? " - Excedió tiempo" : "");
                $ip = $_SERVER['REMOTE_ADDR'];
                
                $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                             VALUES (?, ?, ?, ?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("ississ", $user_id, $accion_log, $tabla, $pase_id, $detalles, $ip);
                $log_stmt->execute();
            } else {
                $error = 'Error al marcar el pase: ' . $conn->error;
            }
        }
    } else {
        $error = 'Pase no encontrado.';
    }
}

// Obtener los pases aprobados para la fecha actual o posteriores
$query = "SELECT p.*, e.nombre, e.puesto 
          FROM pases_entrada_salida p
          JOIN empleados e ON p.num_ficha = e.num_ficha
          WHERE p.estado = 'aprobada' 
          AND p.fecha_pase >= ? 
          AND ((p.tipo_pase = 'entrada' AND p.hora_regreso_real IS NULL) 
               OR (p.tipo_pase = 'salida' AND p.hora_salida_real IS NULL) 
               OR (p.tipo_pase = 'entrada_salida' AND (p.hora_salida_real IS NULL OR p.hora_regreso_real IS NULL)))
          ORDER BY p.fecha_pase ASC, 
                   CASE WHEN p.hora_salida IS NULL THEN '23:59' ELSE p.hora_salida END ASC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $fecha_actual);
$stmt->execute();
$result = $stmt->get_result();
$pases = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pases[] = $row;
    }
}

// Obtener historial de pases marcados recientemente
$historial_query = "SELECT p.*, e.nombre, e.puesto 
                  FROM pases_entrada_salida p
                  JOIN empleados e ON p.num_ficha = e.num_ficha
                  WHERE p.estado = 'aprobada' 
                  AND (p.hora_salida_real IS NOT NULL OR p.hora_regreso_real IS NOT NULL)
                  ORDER BY 
                    CASE 
                      WHEN p.hora_regreso_real IS NOT NULL THEN p.hora_regreso_real
                      ELSE p.hora_salida_real
                    END DESC
                  LIMIT 20";
$historial_result = $conn->query($historial_query);
$historial = [];

if ($historial_result->num_rows > 0) {
    while ($row = $historial_result->fetch_assoc()) {
        $historial[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Pases - Sistema de Gestión de RH</title>
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
                        <a class="nav-link active" href="seguridad_pases.php">Control de Pases</a>
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
        <h1>Control de Pases de Entrada/Salida</h1>
        
        <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Pases Pendientes de Marcar</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pases)): ?>
                        <div class="alert alert-info">No hay pases pendientes de marcar.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empleado</th>
                                        <th>Puesto</th>
                                        <th>Tipo de Pase</th>
                                        <th>Fecha</th>
                                        <th>Hora Salida</th>
                                        <th>Hora Regreso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pases as $pase): ?>
                                    <tr>
                                        <td><?php echo $pase['id']; ?></td>
                                        <td><?php echo $pase['nombre']; ?></td>
                                        <td><?php echo $pase['puesto']; ?></td>
                                        <td>
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
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></td>
                                        <td><?php echo $pase['hora_salida'] ? date('H:i', strtotime($pase['hora_salida'])) : 'N/A'; ?></td>
                                        <td><?php echo $pase['hora_regreso'] ? date('H:i', strtotime($pase['hora_regreso'])) : 'N/A'; ?></td>
                                        <td>
                                            <?php if ($pase['tipo_pase'] == 'entrada' && $pase['hora_regreso_real'] === null): ?>
                                            <form method="post" action="seguridad_pases.php" style="display:inline;">
                                                <input type="hidden" name="pase_id" value="<?php echo $pase['id']; ?>">
                                                <input type="hidden" name="accion" value="marcar_regreso">
                                                <button type="submit" class="btn btn-primary btn-sm">Marcar Entrada</button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($pase['tipo_pase'] == 'salida' && $pase['hora_salida_real'] === null): ?>
                                            <form method="post" action="seguridad_pases.php" style="display:inline;">
                                                <input type="hidden" name="pase_id" value="<?php echo $pase['id']; ?>">
                                                <input type="hidden" name="accion" value="marcar_salida">
                                                <button type="submit" class="btn btn-primary btn-sm">Marcar Salida</button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($pase['tipo_pase'] == 'entrada_salida'): ?>
                                                <?php if ($pase['hora_salida_real'] === null): ?>
                                                <form method="post" action="seguridad_pases.php" style="display:inline;">
                                                    <input type="hidden" name="pase_id" value="<?php echo $pase['id']; ?>">
                                                    <input type="hidden" name="accion" value="marcar_salida">
                                                    <button type="submit" class="btn btn-primary btn-sm">Marcar Salida</button>
                                                </form>
                                                <?php elseif ($pase['hora_regreso_real'] === null): ?>
                                                <form method="post" action="seguridad_pases.php" style="display:inline;">
                                                    <input type="hidden" name="pase_id" value="<?php echo $pase['id']; ?>">
                                                    <input type="hidden" name="accion" value="marcar_regreso">
                                                    <button type="submit" class="btn btn-success btn-sm">Marcar Regreso</button>
                                                </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#modalDetalles<?php echo $pase['id']; ?>">
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
        
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0">Historial Reciente</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($historial)): ?>
                        <div class="alert alert-info">No hay registros en el historial.</div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Empleado</th>
                                        <th>Puesto</th>
                                        <th>Tipo</th>
                                        <th>Fecha</th>
                                        <th>Salida Real</th>
                                        <th>Regreso Real</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial as $pase): ?>
                                    <tr>
                                        <td><?php echo $pase['id']; ?></td>
                                        <td><?php echo $pase['nombre']; ?></td>
                                        <td><?php echo $pase['puesto']; ?></td>
                                        <td>
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
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></td>
                                        <td>
                                            <?php echo $pase['hora_salida_real'] ? date('d/m/Y H:i', strtotime($pase['hora_salida_real'])) : 'No marcado'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($pase['hora_regreso_real']) {
                                                echo date('d/m/Y H:i', strtotime($pase['hora_regreso_real']));
                                                if ($pase['excedio_tiempo']) {
                                                    echo ' <span class="badge bg-warning">Excedió tiempo</span>';
                                                }
                                            } else {
                                                echo 'No marcado';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($pase['tipo_pase'] == 'entrada' && $pase['hora_regreso_real']): ?>
                                                <span class="badge bg-success">Completado</span>
                                            <?php elseif ($pase['tipo_pase'] == 'salida' && $pase['hora_salida_real']): ?>
                                                <span class="badge bg-success">Completado</span>
                                            <?php elseif ($pase['tipo_pase'] == 'entrada_salida' && $pase['hora_salida_real'] && $pase['hora_regreso_real']): ?>
                                                <span class="badge bg-success">Completado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Parcial</span>
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
        </div>
    </div>
    
    <!-- Modales para ver detalles de los pases -->
    <?php foreach ($pases as $pase): ?>
    <div class="modal fade" id="modalDetalles<?php echo $pase['id']; ?>" tabindex="-1" aria-labelledby="modalDetallesLabel<?php echo $pase['id']; ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetallesLabel<?php echo $pase['id']; ?>">Detalles del Pase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
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
                    <p><strong>Fecha del Pase:</strong> <?php echo date('d/m/Y', strtotime($pase['fecha_pase'])); ?></p>
                    
                    <?php if ($pase['hora_salida']): ?>
                    <p><strong>Hora de Salida Programada:</strong> <?php echo date('H:i', strtotime($pase['hora_salida'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['hora_regreso']): ?>
                    <p><strong>Hora de Regreso Programada:</strong> <?php echo date('H:i', strtotime($pase['hora_regreso'])); ?></p>
                    <?php endif; ?>
                    
                    <p><strong>Motivo:</strong> <?php echo $pase['motivo']; ?></p>
                    
                    <?php if ($pase['hora_salida_real']): ?>
                    <p><strong>Hora de Salida Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_salida_real'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['hora_regreso_real']): ?>
                    <p><strong>Hora de Regreso Real:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['hora_regreso_real'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($pase['excedio_tiempo']): ?>
                    <div class="alert alert-warning">
                        <strong>Nota:</strong> Se excedió el tiempo autorizado.
                    </div>
                    <?php endif; ?>
                    
                    <p><strong>Fecha de Solicitud:</strong> <?php echo date('d/m/Y H:i', strtotime($pase['fecha_solicitud'])); ?></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>