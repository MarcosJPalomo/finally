<?php
session_start();
require_once 'config/db.php';

// Redirigir a login si no está autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Obtener información del usuario
$user_id = $_SESSION['user_id'];
$query = "SELECT u.*, e.nombre, e.puesto FROM usuarios u 
          JOIN empleados e ON u.num_ficha = e.num_ficha 
          WHERE u.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$tipo_usuario = $user['tipo_usuario'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Sistema RH</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $user['nombre']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="perfil.php">Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="bi bi-house-door"></i> Inicio
                            </a>
                        </li>
                        
                        <?php if ($tipo_usuario == 'empleado' || $tipo_usuario == 'supervisor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="pases.php">
                                <i class="bi bi-door-open"></i> Pases de Entrada/Salida
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'empleado'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="prestamos.php">
                                <i class="bi bi-cash"></i> Préstamos
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'supervisor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="vacaciones.php">
                                <i class="bi bi-calendar-check"></i> Solicitudes de Vacaciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cambios_turno.php">
                                <i class="bi bi-clock-history"></i> Cambios de Turno
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bajas.php">
                                <i class="bi bi-person-dash"></i> Solicitud de Bajas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="altas.php">
                                <i class="bi bi-person-plus"></i> Solicitud de Altas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="tiempo_extra.php">
                                <i class="bi bi-hourglass"></i> Tiempo Extra
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'rh'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_vacaciones.php">
                                <i class="bi bi-calendar-check"></i> Solicitudes de Vacaciones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_pases.php">
                                <i class="bi bi-door-open"></i> Pases de Entrada/Salida
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_prestamos.php">
                                <i class="bi bi-cash"></i> Préstamos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_cambios_turno.php">
                                <i class="bi bi-clock-history"></i> Cambios de Turno
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_bajas.php">
                                <i class="bi bi-person-dash"></i> Solicitud de Bajas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_altas.php">
                                <i class="bi bi-person-plus"></i> Solicitud de Altas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rh_tiempo_extra.php">
                                <i class="bi bi-hourglass"></i> Tiempo Extra
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="registro_empleados.php">
                                <i class="bi bi-person-vcard"></i> Registro de Empleados
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'revisor'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="revisor_bajas.php">
                                <i class="bi bi-person-dash"></i> Revisión de Bajas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="revisor_altas.php">
                                <i class="bi bi-person-plus"></i> Revisión de Altas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="revisor_tiempo_extra.php">
                                <i class="bi bi-hourglass"></i> Revisión de Tiempo Extra
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($tipo_usuario == 'seguridad'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="seguridad_pases.php">
                                <i class="bi bi-door-open"></i> Control de Pases
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Panel de Control</h1>
                </div>
                
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Bienvenido(a), <?php echo $user['nombre']; ?></h5>
                                <p class="card-text">Puesto: <?php echo $user['puesto']; ?></p>
                                <p class="card-text">Número de Ficha: <?php echo $user['num_ficha']; ?></p>
                                <p class="card-text">Tipo de usuario: <?php echo ucfirst($user['tipo_usuario']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-12">
                        <h3>Solicitudes Recientes</h3>
                        
                        <?php
                        // Mostrar solicitudes según el tipo de usuario
                        switch ($tipo_usuario) {
                            case 'empleado':
                                // Mostrar las solicitudes recientes de pases y préstamos del empleado
                                $query = "SELECT 'pase' as tipo, id, fecha_solicitud, estado FROM pases_entrada_salida 
                                          WHERE num_ficha = ? 
                                          UNION 
                                          SELECT 'prestamo' as tipo, id, fecha_solicitud, estado FROM prestamos 
                                          WHERE num_ficha = ? 
                                          ORDER BY fecha_solicitud DESC LIMIT 5";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("ss", $user['num_ficha'], $user['num_ficha']);
                                break;
                                
                            case 'supervisor':
                                // Mostrar las solicitudes recientes que ha emitido el supervisor
                                $query = "SELECT 'vacaciones' as tipo, id, fecha_solicitud, estado FROM solicitudes_vacaciones 
                                          WHERE supervisor_id = ? 
                                          UNION 
                                          SELECT 'cambio_turno' as tipo, id, fecha_solicitud, estado FROM cambios_turno 
                                          WHERE supervisor_id = ? 
                                          UNION 
                                          SELECT 'baja' as tipo, id, fecha_solicitud, estado_revisor as estado FROM solicitudes_baja 
                                          WHERE supervisor_id = ? 
                                          UNION 
                                          SELECT 'alta' as tipo, id, fecha_solicitud, estado_revisor as estado FROM solicitudes_alta 
                                          WHERE supervisor_id = ? 
                                          UNION 
                                          SELECT 'tiempo_extra' as tipo, id, fecha_solicitud, estado_revisor as estado FROM tiempo_extra 
                                          WHERE supervisor_id = ? 
                                          ORDER BY fecha_solicitud DESC LIMIT 10";
                                $stmt = $conn->prepare($query);
                                $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
                                break;
                                
                            case 'rh':
                                // Mostrar las solicitudes pendientes para RH
                                $query = "SELECT 'vacaciones' as tipo, id, fecha_solicitud, estado FROM solicitudes_vacaciones 
                                          WHERE estado = 'pendiente' 
                                          UNION 
                                          SELECT 'pase' as tipo, id, fecha_solicitud, estado FROM pases_entrada_salida 
                                          WHERE estado = 'pendiente' 
                                          UNION 
                                          SELECT 'prestamo' as tipo, id, fecha_solicitud, estado FROM prestamos 
                                          WHERE estado = 'pendiente' 
                                          UNION 
                                          SELECT 'cambio_turno' as tipo, id, fecha_solicitud, estado FROM cambios_turno 
                                          WHERE estado = 'pendiente' 
                                          UNION 
                                          SELECT 'baja' as tipo, id, fecha_solicitud, estado_rh as estado FROM solicitudes_baja 
                                          WHERE estado_revisor = 'aprobada' AND estado_rh = 'pendiente' 
                                          UNION 
                                          SELECT 'alta' as tipo, id, fecha_solicitud, estado_rh as estado FROM solicitudes_alta 
                                          WHERE estado_revisor = 'aprobada' AND estado_rh = 'pendiente' 
                                          UNION 
                                          SELECT 'tiempo_extra' as tipo, id, fecha_solicitud, estado_rh as estado FROM tiempo_extra 
                                          WHERE estado_revisor = 'aprobada' AND estado_rh = 'pendiente' 
                                          ORDER BY fecha_solicitud ASC LIMIT 15";
                                $stmt = $conn->prepare($query);
                                break;
                                
                            case 'revisor':
                                // Mostrar las solicitudes pendientes para el revisor
                                $query = "SELECT 'baja' as tipo, id, fecha_solicitud, estado_revisor as estado FROM solicitudes_baja 
                                          WHERE estado_revisor = 'pendiente' 
                                          UNION 
                                          SELECT 'alta' as tipo, id, fecha_solicitud, estado_revisor as estado FROM solicitudes_alta 
                                          WHERE estado_revisor = 'pendiente' 
                                          UNION 
                                          SELECT 'tiempo_extra' as tipo, id, fecha_solicitud, estado_revisor as estado FROM tiempo_extra 
                                          WHERE estado_revisor = 'pendiente' 
                                          ORDER BY fecha_solicitud ASC LIMIT 10";
                                $stmt = $conn->prepare($query);
                                break;
                                
                            case 'seguridad':
                                // Mostrar los pases aprobados pendientes de marcar
                                $query = "SELECT 'pase' as tipo, id, fecha_solicitud, tipo_pase, fecha_pase, 
                                          hora_salida, hora_regreso, hora_salida_real, hora_regreso_real 
                                          FROM pases_entrada_salida 
                                          WHERE estado = 'aprobada' AND 
                                          ((tipo_pase = 'entrada' AND hora_regreso_real IS NULL) OR 
                                           (tipo_pase = 'salida' AND hora_salida_real IS NULL) OR 
                                           (tipo_pase = 'entrada_salida' AND (hora_salida_real IS NULL OR hora_regreso_real IS NULL))) 
                                          ORDER BY fecha_pase ASC, hora_salida ASC LIMIT 10";
                                $stmt = $conn->prepare($query);
                                break;
                        }
                        
                        if (isset($stmt)) {
                            $stmt->execute();
                            $result = $stmt->get_result();
                            
                            if ($result->num_rows > 0) {
                                echo '<div class="table-responsive">';
                                echo '<table class="table table-striped table-hover">';
                                echo '<thead><tr>';
                                echo '<th>Tipo</th>';
                                echo '<th>Fecha de Solicitud</th>';
                                
                                if ($tipo_usuario == 'seguridad') {
                                    echo '<th>Tipo de Pase</th>';
                                    echo '<th>Fecha de Pase</th>';
                                    echo '<th>Hora Salida</th>';
                                    echo '<th>Hora Regreso</th>';
                                    echo '<th>Acciones</th>';
                                } else {
                                    echo '<th>Estado</th>';
                                    echo '<th>Acciones</th>';
                                }
                                
                                echo '</tr></thead>';
                                echo '<tbody>';
                                
                                while ($row = $result->fetch_assoc()) {
                                    echo '<tr>';
                                    
                                    // Mostrar el tipo de solicitud
                                    $tipo_texto = '';
                                    switch ($row['tipo']) {
                                        case 'vacaciones': $tipo_texto = 'Vacaciones'; break;
                                        case 'pase': $tipo_texto = 'Pase Entrada/Salida'; break;
                                        case 'prestamo': $tipo_texto = 'Préstamo'; break;
                                        case 'cambio_turno': $tipo_texto = 'Cambio de Turno'; break;
                                        case 'baja': $tipo_texto = 'Solicitud de Baja'; break;
                                        case 'alta': $tipo_texto = 'Solicitud de Alta'; break;
                                        case 'tiempo_extra': $tipo_texto = 'Tiempo Extra'; break;
                                    }
                                    echo '<td>' . $tipo_texto . '</td>';
                                    
                                    // Mostrar la fecha de solicitud
                                    $fecha_solicitud = new DateTime($row['fecha_solicitud']);
                                    echo '<td>' . $fecha_solicitud->format('d/m/Y H:i') . '</td>';
                                    
                                    if ($tipo_usuario == 'seguridad') {
                                        // Mostrar información específica para seguridad
                                        $tipo_pase_texto = '';
                                        switch ($row['tipo_pase']) {
                                            case 'entrada': $tipo_pase_texto = 'Entrada'; break;
                                            case 'salida': $tipo_pase_texto = 'Salida'; break;
                                            case 'entrada_salida': $tipo_pase_texto = 'Entrada/Salida'; break;
                                        }
                                        echo '<td>' . $tipo_pase_texto . '</td>';
                                        echo '<td>' . date('d/m/Y', strtotime($row['fecha_pase'])) . '</td>';
                                        echo '<td>' . ($row['hora_salida'] ? date('H:i', strtotime($row['hora_salida'])) : 'N/A') . '</td>';
                                        echo '<td>' . ($row['hora_regreso'] ? date('H:i', strtotime($row['hora_regreso'])) : 'N/A') . '</td>';
                                        echo '<td>';
                                        
                                        if ($row['tipo_pase'] == 'entrada' && $row['hora_regreso_real'] === null) {
                                            echo '<a href="marcar_entrada.php?id=' . $row['id'] . '" class="btn btn-primary btn-sm">Marcar Entrada</a>';
                                        } elseif ($row['tipo_pase'] == 'salida' && $row['hora_salida_real'] === null) {
                                            echo '<a href="marcar_salida.php?id=' . $row['id'] . '" class="btn btn-primary btn-sm">Marcar Salida</a>';
                                        } elseif ($row['tipo_pase'] == 'entrada_salida') {
                                            if ($row['hora_salida_real'] === null) {
                                                echo '<a href="marcar_salida.php?id=' . $row['id'] . '" class="btn btn-primary btn-sm">Marcar Salida</a> ';
                                            }
                                            if ($row['hora_regreso_real'] === null && $row['hora_salida_real'] !== null) {
                                                echo '<a href="marcar_entrada.php?id=' . $row['id'] . '" class="btn btn-success btn-sm">Marcar Regreso</a>';
                                            }
                                        }
                                        
                                        echo '</td>';
                                    } else {
                                        // Mostrar el estado para otros usuarios
                                        $estado_class = '';
                                        switch ($row['estado']) {
                                            case 'pendiente': $estado_class = 'bg-warning'; break;
                                            case 'aprobada': $estado_class = 'bg-success'; break;
                                            case 'rechazada': $estado_class = 'bg-danger'; break;
                                            case 'procesada': $estado_class = 'bg-info'; break;
                                        }
                                        echo '<td><span class="badge ' . $estado_class . '">' . ucfirst($row['estado']) . '</span></td>';
                                        
                                        // Acciones
                                        echo '<td>';
                                        echo '<a href="ver_' . $row['tipo'] . '.php?id=' . $row['id'] . '" class="btn btn-info btn-sm">Ver Detalles</a>';
                                        echo '</td>';
                                    }
                                    
                                    echo '</tr>';
                                }
                                
                                echo '</tbody></table></div>';
                            } else {
                                echo '<div class="alert alert-info">No hay solicitudes recientes.</div>';
                            }
                        }
                        ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>