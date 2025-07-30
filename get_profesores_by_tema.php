<?php
include 'conexion.php'; // Tu archivo de conexión
header('Content-Type: application/json');

$temas_id = $_GET['temas_id'] ?? [];
$profesores = [];

if (empty($temas_id) || !is_array($temas_id)) {
    echo json_encode([]);
    exit();
}

// Convertir los IDs a enteros para seguridad y usarlos en la consulta IN
$temas_id = array_map('intval', $temas_id);
$placeholders = implode(',', array_fill(0, count($temas_id), '?'));
$types = str_repeat('i', count($temas_id));

$sql = "
    SELECT DISTINCT p.id, p.nombre
    FROM profesores p
    JOIN temas_profesores tp ON p.id = tp.profesor_id
    WHERE tp.tema_id IN ($placeholders)
    ORDER BY p.nombre ASC
";

$stmt = $conexion->prepare($sql);
if ($stmt === false) {
    error_log("Error al preparar la consulta de profesores por tema: " . $conexion->error);
    echo json_encode(['error' => 'Error en la base de datos.']);
    exit();
}

$stmt->bind_param($types, ...$temas_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $profesores[] = ['id' => $row['id'], 'text' => $row['nombre']];
}

$stmt->close();
$conexion->close();

echo json_encode($profesores);
?>