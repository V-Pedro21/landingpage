<?php
include 'conexion.php'; // Asegúrate de que tu archivo de conexión es correcto

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$profesor_id = $_GET['id'] ?? null;
$horarios = [];

if ($profesor_id) {
    // Preparar la consulta para obtener los horarios disponibles para el profesor
    // Consideramos horarios futuros y que no estén ya asignados (para un sistema de citas más complejo)
    // Por ahora, solo obtenemos todos los horarios del profesor
    $sql = "SELECT id, fecha, hora_inicio, hora_fin FROM horarios_profesores WHERE profesor_id = ? ORDER BY fecha, hora_inicio";

    if ($stmt = $conexion->prepare($sql)) {
        $stmt->bind_param("i", $profesor_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            // Formatear la fecha y hora para una visualización amigable en el select
            // Ejemplo: "2025-07-23 20:44 - 21:14"
            $display_text = date('d/m/Y', strtotime($row['fecha'])) . ' ' .
                            substr($row['hora_inicio'], 0, 5) . ' - ' .
                            substr($row['hora_fin'], 0, 5);

            $horarios[] = [
                'value' => $row['id'], // El valor que se enviará en el formulario (el ID del horario)
                'display' => $display_text // El texto que se mostrará al usuario
            ];
        }
        $stmt->close();
    } else {
        error_log("Error al preparar la consulta de horarios: " . $conexion->error);
    }
} else {
    error_log("get_horarios.php: profesor_id no recibido.");
}

$conexion->close(); // Cerrar la conexión
echo json_encode($horarios); // Devolver los horarios como JSON
?>