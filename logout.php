<?php
session_start();
require_once 'config/db.php';

// Registrar la actividad de cierre de sesión
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $accion = "Cierre de sesión";
    $ip = $_SERVER['REMOTE_ADDR'];
    
    $log_query = "INSERT INTO logs_sistema (usuario_id, accion, ip_usuario) VALUES (?, ?, ?)";
    $log_stmt = $conn->prepare($log_query);
    $log_stmt->bind_param("iss", $user_id, $accion, $ip);
    $log_stmt->execute();
}

// Destruir la sesión
session_unset();
session_destroy();

// Redirigir al login
header('Location: login.php');
exit;