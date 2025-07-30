<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo existe y funciona correctamente

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$response = [
    'temas' => [], // Ahora se espera un array de objetos de tema
    'horarios' => [] // También devolveremos horarios aquí si se solicita
];

if (isset($_GET['id'])) { // Usamos 'id' como el parámetro para el ID del profesor
    $profesor_id = (int)$_GET['id'];

    // --- Obtener temas del profesor ---
    try {
        // Consulta para obtener ID y nombre del tema
        $stmt_temas = $conexion->prepare("SELECT id, tema FROM temas_profesores WHERE profesor_id = ? ORDER BY tema ASC");
        if ($stmt_temas === false) {
            throw new Exception("Error al preparar la consulta de temas: " . $conexion->error);
        }
        $stmt_temas->bind_param("i", $profesor_id);
        $stmt_temas->execute();
        $result_temas = $stmt_temas->get_result();
        $temas_array = [];
        while ($row = $result_temas->fetch_assoc()) {
            $temas_array[] = ['id' => $row['id'], 'tema' => $row['tema']]; // Guardar como objetos {id: ..., tema: ...}
        }
        $response['temas'] = $temas_array;
        $stmt_temas->close();
    } catch (Exception $e) {
        error_log("Error al obtener temas del profesor en get_profesor_data.php: " . $e->getMessage());
        $response['temas'] = []; // En caso de error, enviar array vacío
    }

    // --- Obtener horarios del profesor (opcional, si queremos consolidarlo aquí) ---
    // Si solo quieres 'temas' de este archivo, puedes eliminar la sección de horarios.
    // Si 'alumno.php' llama a `get_horarios.php` por separado, deja `get_horarios.php` como está.
    // Para simplificar, ajustemos get_profesor_data.php para solo enviar temas como se muestra arriba.
    // Si decides consolidar, el código para horarios iría aquí, similar a get_horarios.php
}

$conexion->close();
echo json_encode($response);
?>