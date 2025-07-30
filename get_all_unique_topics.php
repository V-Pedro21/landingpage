<?php
include 'conexion.php'; // Tu archivo de conexión
header('Content-Type: application/json');

$searchTerm = $_GET['q'] ?? '';

$sql = "SELECT id, tema FROM temas";
$params = [];
$types = "";

if (!empty($searchTerm)) {
    $sql .= " WHERE tema LIKE ?";
    $params[] = '%' . $searchTerm . '%';
    $types .= 's';
}

$sql .= " ORDER BY tema ASC";

$stmt = $conexion->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$temas = [];
while ($row = $result->fetch_assoc()) {
    $temas[] = ['id' => $row['id'], 'text' => $row['tema']];
}

$stmt->close();
$conexion->close();

echo json_encode($temas);
?>