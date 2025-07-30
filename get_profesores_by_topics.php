<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo existe y funciona correctamente

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$profesores_data = [];

if (isset($_GET['tema_ids']) && !empty($_GET['tema_ids'])) {
    // Sanitizar y validar los IDs de temas
    $tema_ids_str = $_GET['tema_ids']; // Viene como una cadena de IDs separados por comas
    $tema_ids_array = array_map('intval', explode(',', $tema_ids_str)); // Convertir a array de enteros

    // Construir la parte IN (?) para la consulta SQL
    $placeholders = implode(',', array_fill(0, count($tema_ids_array), '?'));
    $types = str_repeat('i', count($tema_ids_array)); // 'i' por cada entero

    try {
        // Seleccionar profesores que tienen al menos UNO de los temas seleccionados
        // DISTINCT para no repetir profesores si tienen varios de los temas seleccionados
        $stmt = $conexion->prepare("
            SELECT DISTINCT p.id, p.nombre
            FROM profesores p
            JOIN temas_profesores tp ON p.id = tp.profesor_id
            WHERE tp.id IN ($placeholders)
            ORDER BY p.nombre ASC
        ");

        if ($stmt === false) {
            throw new Exception("Error al preparar la consulta de profesores por temas: " . $conexion->error);
        }

        // Bindea los parámetros dinámicamente
        $stmt->bind_param($types, ...$tema_ids_array);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $profesores_data[] = ['id' => $row['id'], 'nombre' => $row['nombre']];
        }
        $stmt->close();
    } catch (Exception $e) {
        error_log("Error al obtener profesores por temas en get_profesores_by_topics.php: " . $e->getMessage());
        // En caso de error, enviar array vacío
    }
}

$conexion->close();
echo json_encode($profesores_data);
?>