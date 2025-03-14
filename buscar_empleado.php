<?php
require_once 'config/db.php';

// Configurar encabezados para respuesta JSON
header('Content-Type: application/json');

// Verificar si se proporcionó un número de ficha
if (!isset($_GET['num_ficha']) || empty($_GET['num_ficha'])) {
    echo json_encode(['success' => false, 'message' => 'Número de ficha no proporcionado']);
    exit;
}

$num_ficha = trim($_GET['num_ficha']);

// Buscar información del empleado
$query = "SELECT nombre, puesto FROM empleados WHERE num_ficha = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $num_ficha);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $empleado = $result->fetch_assoc();
    echo json_encode([
        'success' => true,
        'nombre' => $empleado['nombre'],
        'puesto' => $empleado['puesto']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Empleado no encontrado']);
}