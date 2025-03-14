<?php
session_start();
require_once 'config/db.php';

// Si ya está autenticado, redirigir al panel
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Procesar el formulario de login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        // Verificar credenciales
        $query = "SELECT id, username, password, tipo_usuario, activo FROM usuarios WHERE username = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verificar si la cuenta está activa
            if ($user['activo'] == 0) {
                $error = 'Esta cuenta ha sido desactivada. Contacte a RH.';
            } 
            // Verificar contraseña (en un entorno real, se usaría password_verify con contraseñas hash)
            else if ($password == $user['password']) {
                // Autenticación exitosa
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['tipo_usuario'] = $user['tipo_usuario'];
                
                // Registrar actividad de inicio de sesión
                $accion = "Inicio de sesión";
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_query = "INSERT INTO logs_sistema (usuario_id, accion, ip_usuario) VALUES (?, ?, ?)";
                $log_stmt = $conn->prepare($log_query);
                $log_stmt->bind_param("iss", $user['id'], $accion, $ip);
                $log_stmt->execute();
                
                header('Location: index.php');
                exit;
            } else {
                $error = 'Contraseña incorrecta.';
            }
        } else {
            $error = 'Usuario no encontrado.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - Sistema de Gestión de RH</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 450px;
            margin: 100px auto;
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
        .btn-login {
            font-size: 0.9rem;
            letter-spacing: 0.05rem;
            padding: 0.75rem 1rem;
        }
    </style>
</head>
<body>
    <div class="container login-container">
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Sistema de Gestión de RH</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="password" class="form-label">Contraseña</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-login">Iniciar Sesión</button>
                    </div>
                </form>
                
                <div class="mt-3 text-center">
                    <p>¿No tienes una cuenta? <a href="registro.php">Regístrate</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>