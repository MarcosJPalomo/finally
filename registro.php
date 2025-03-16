<?php
session_start();
require_once 'config/db.php';

// Si ya está autenticado, redirigir al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$empleado_data = null;

// Función para obtener información del empleado por número de ficha
function getEmpleadoInfo($conn, $num_ficha) {
    $query = "SELECT nombre, puesto FROM empleados WHERE num_ficha = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $num_ficha);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Procesar la búsqueda de empleado por número de ficha (para autocompletado)
if (isset($_GET['buscar_ficha']) && !empty($_GET['num_ficha'])) {
    $num_ficha = trim($_GET['num_ficha']);
    $empleado_data = getEmpleadoInfo($conn, $num_ficha);
    
    if ($empleado_data) {
        // Si es una solicitud AJAX, devolver los datos en formato JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode($empleado_data);
            exit;
        }
    }
}

// Procesar el formulario de registro
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $num_ficha = trim($_POST['num_ficha']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = trim($_POST['email']);
    // Por defecto asignar el rol "empleado"
    $tipo_usuario = 'empleado';
    
    // Validaciones básicas
    if (empty($num_ficha) || empty($username) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, complete todos los campos obligatorios.';
    } else if ($password != $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } else {
        // Verificar si el número de ficha existe en la tabla de empleados
        $check_query = "SELECT * FROM empleados WHERE num_ficha = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $num_ficha);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows == 0) {
            $error = 'El número de ficha no existe en el sistema. Contacte a RH.';
        } else {
            // Verificar si el usuario ya existe
            $user_check_query = "SELECT * FROM usuarios WHERE username = ?";
            $user_check_stmt = $conn->prepare($user_check_query);
            $user_check_stmt->bind_param("s", $username);
            $user_check_stmt->execute();
            $user_check_result = $user_check_stmt->get_result();
            
            if ($user_check_result->num_rows > 0) {
                $error = 'Este nombre de usuario ya está en uso.';
            } else {
                // Verificar si ya existe un usuario con ese número de ficha
                $ficha_check_query = "SELECT * FROM usuarios WHERE num_ficha = ?";
                $ficha_check_stmt = $conn->prepare($ficha_check_query);
                $ficha_check_stmt->bind_param("s", $num_ficha);
                $ficha_check_stmt->execute();
                $ficha_check_result = $ficha_check_stmt->get_result();
                
                if ($ficha_check_result->num_rows > 0) {
                    $error = 'Ya existe un usuario registrado con este número de ficha.';
                } else {
                    // En un entorno real, se debería utilizar password_hash para almacenar contraseñas de forma segura
                    // $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Crear el nuevo usuario
                    $insert_query = "INSERT INTO usuarios (num_ficha, username, password, tipo_usuario, email) VALUES (?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_query);
                    $insert_stmt->bind_param("sssss", $num_ficha, $username, $password, $tipo_usuario, $email);
                    
                    if ($insert_stmt->execute()) {
                        $success = 'Registro exitoso. Ahora puede iniciar sesión.';
                        
                        // Registrar actividad
                        $user_id = $conn->insert_id;
                        $accion = "Registro de usuario";
                        $tabla = "usuarios";
                        $detalles = "Username: $username, Tipo: $tipo_usuario";
                        $ip = $_SERVER['REMOTE_ADDR'];
                        
                        $log_query = "INSERT INTO logs_sistema (usuario_id, accion, tabla_afectada, registro_id, detalles, ip_usuario) 
                                     VALUES (?, ?, ?, ?, ?, ?)";
                        $log_stmt = $conn->prepare($log_query);
                        $log_stmt->bind_param("ississ", $user_id, $accion, $tabla, $user_id, $detalles, $ip);
                        $log_stmt->execute();
                    } else {
                        $error = 'Error al registrar el usuario: ' . $conn->error;
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema de Gestión de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 600px;
            margin: 50px auto;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .card-header {
            background-color: #343a40;
            color: white;
            text-align: center;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 1.5rem;
        }
        .btn-register {
            font-size: 0.9rem;
            letter-spacing: 0.05rem;
            padding: 0.75rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container register-container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Registro de Usuario</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <?php echo $success; ?>
                    <p>Redireccionando al login en <span id="countdown">5</span> segundos...</p>
                </div>
                <script>
                    let seconds = 5;
                    const countdownElement = document.getElementById('countdown');
                    
                    const interval = setInterval(function() {
                        seconds--;
                        countdownElement.textContent = seconds;
                        
                        if (seconds <= 0) {
                            clearInterval(interval);
                            window.location.href = 'login.php';
                        }
                    }, 1000);
                </script>
                <?php else: ?>
                
                <form method="post" action="registro.php" id="registroForm">
                    <div class="mb-3">
                        <label for="num_ficha" class="form-label">Número de Ficha *</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="num_ficha" name="num_ficha" required>
                            <button class="btn btn-outline-secondary" type="button" id="buscarFicha">Buscar</button>
                        </div>
                        <div class="form-text">Ingrese su número de ficha de empleado.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="puesto" class="form-label">Puesto</label>
                        <input type="text" class="form-control" id="puesto" name="puesto" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Nombre de Usuario *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña *</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email">
                        <div class="form-text">Opcional, pero recomendado para recuperación de contraseña.</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-register">Registrarse</button>
                    </div>
                </form>
                
                <?php endif; ?>
                
                <div class="mt-3 text-center">
                    <p>¿Ya tienes una cuenta? <a href="login.php">Iniciar Sesión</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Función para buscar información del empleado por número de ficha
            function buscarEmpleado() {
                const numFicha = document.getElementById('num_ficha').value.trim();
                
                if (numFicha === '') {
                    alert('Por favor, ingrese un número de ficha para buscar.');
                    return;
                }
                
                // Realizar la solicitud AJAX
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'registro.php?buscar_ficha=1&num_ficha=' + encodeURIComponent(numFicha), true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            document.getElementById('nombre').value = response.nombre || '';
                            document.getElementById('puesto').value = response.puesto || '';
                            
                            // Si no se encontró el empleado
                            if (!response.nombre) {
                                alert('Número de ficha no encontrado.');
                            }
                        } catch (e) {
                            console.error('Error al procesar la respuesta:', e);
                            document.getElementById('nombre').value = '';
                            document.getElementById('puesto').value = '';
                            alert('Error al procesar la respuesta del servidor.');
                        }
                    } else {
                        document.getElementById('nombre').value = '';
                        document.getElementById('puesto').value = '';
                        alert('Número de ficha no encontrado o error en la consulta.');
                    }
                };
                
                xhr.onerror = function() {
                    alert('Error de conexión. Intente nuevamente.');
                };
                
                xhr.send();
            }
            
            // Evento para el botón de búsqueda
            document.getElementById('buscarFicha').addEventListener('click', buscarEmpleado);
            
            // También buscar cuando se pierde el foco del campo
            document.getElementById('num_ficha').addEventListener('blur', function() {
                if (this.value.trim() !== '') {
                    buscarEmpleado();
                }
            });
            
            // Validar contraseñas al enviar el formulario
            document.getElementById('registroForm').addEventListener('submit', function(e) {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                
                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Las contraseñas no coinciden.');
                }
                
                // Verificar que se haya encontrado un empleado
                const nombre = document.getElementById('nombre').value;
                if (!nombre) {
                    e.preventDefault();
                    alert('Por favor, busque un número de ficha válido.');
                }
            });
        });
    </script>
</body>
</html>