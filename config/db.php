<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'gestor_rh';

// Conexión a la base de datos
$conn = new mysqli($host, $username, $password, $database);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Establecer el conjunto de caracteres
$conn->set_charset("utf8");

// Configurar la zona horaria a la de México
date_default_timezone_set('America/Mexico_City');