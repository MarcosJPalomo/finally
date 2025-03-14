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

// Verificar si se proporcionó un ID de pase
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: seguridad_pases.php');
    exit;
}

$pase_id = $_GET['id'];

// Obtener información del pase
$query = "SELECT p.*, e.nombre, e.puesto 
          FROM pases_entrada_salida p
          JOIN empleados e ON p.num_ficha = e.num_ficha
          WHERE p.id = ? AND p.estado = 'aprobada'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $pase_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    // Pase no encontrado o no aprobado
    header('Location: seguridad_pases.php?error=1');
    exit;
}

$pase = $result->fetch_assoc();

// Marcar salida según el tipo de pase
$fecha_hora_actual = date('Y-m-d H:i:s');

if ($pase['tipo_pase'] == 'salida' || $pase['tipo_pase'] == 'entrada_salida') {
    // Si es un pase de salida o entrada/salida, marcar la hora de salida
    
    // Verificar si ya está marcado
    if ($pase['hora_salida_real'] !== null) {
        header('Location: seguridad_pases.php?error=2');
        exit;
    }
    
    // Marcar la hora de salida
    $update_query = "UPDATE pases_entrada_salida 
                    SET hora_salida_real = ?, seguridad_id = ? 
                    WHERE id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("sii", $fecha_hora_actual, $user_id, $pase_id);
    
    if ($update_stmt->execute()) {
        $success = 'Se ha marcado la salida correctamente.';
        
        // Registrar la actividad
        $accion_log = "Marcación de salida";
        $tabla = "pases_entrada_salida";
        $detalles = "Hora: $fecha_hora_actual";
        $ip = $_SERVER['REMOTE_ADDR'];
        
        $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_query);
        $log_stmt->bind_param("ississ", $user_id, $accion_log, $tabla, $pase_id, $detalles, $ip);
        $log_stmt->execute();
        
        // Redirigir a la página de seguridad
        header('Location: seguridad_pases.php?success=2');
        exit;
    } else {
        $error = 'Error al marcar la salida: ' . $conn->error;
    }
} else {
    // Tipo de pase incorrecto
    header('Location: seguridad_pases.php?error=4');
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marcar Salida - Sistema de Gestión de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Marcar Salida</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                        <p>Redireccionando...</p>
                        <script>
                            setTimeout(function() {
                                window.location.href = 'seguridad_pases.php';
                            }, 2000);
                        </script>
                        <?php endif; ?>
                        
                        <p>Procesando, por favor espere...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>