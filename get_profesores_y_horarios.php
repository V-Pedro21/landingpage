<?php
session_start();
include 'conexion.php'; // Tu archivo de conexión a la BD

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

$tema_id = $_GET['tema_id'] ?? null;

if (!$tema_id) {
    echo json_encode(['error' => 'ID de tema no proporcionado.']);
    exit();
}

$profesores_data = [];

try {
    // 1. Obtener profesores que imparten el tema
    $stmt_profesores = $conexion->prepare("
        SELECT p.id, p.nombre, p.correo, GROUP_CONCAT(DISTINCT t.tema SEPARATOR ',') AS temas_impartidos
        FROM profesores p
        JOIN temas_profesores tp ON p.id = tp.profesor_id
        JOIN temas t ON tp.tema_id = t.id
        WHERE tp.tema_id = ?
        GROUP BY p.id, p.nombre, p.correo
        ORDER BY p.nombre ASC
    ");
    if ($stmt_profesores === false) {
        throw new Exception("Error al preparar la consulta de profesores: " . $conexion->error);
    }
    $stmt_profesores->bind_param("i", $tema_id);
    $stmt_profesores->execute();
    $result_profesores = $stmt_profesores->get_result();

    while ($profesor = $result_profesores->fetch_assoc()) {
        $profesor['temas'] = explode(',', $profesor['temas_impartidos']); // Convertir a array
        unset($profesor['temas_impartidos']); // Limpiar el campo original

        $profesor['horarios_recurrentes'] = [];
        $profesor['horarios_puntuales'] = [];

        // 2. Obtener horarios recurrentes para cada profesor
        $stmt_recurrente = $conexion->prepare("SELECT id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez FROM disponibilidad_recurrente WHERE profesor_id = ? ORDER BY dia_semana, hora_inicio");
        if ($stmt_recurrente === false) {
            throw new Exception("Error al preparar consulta de horarios recurrentes: " . $conexion->error);
        }
        $stmt_recurrente->bind_param("i", $profesor['id']);
        $stmt_recurrente->execute();
        $result_recurrente = $stmt_recurrente->get_result();
        while ($horario = $result_recurrente->fetch_assoc()) {
            // Aquí puedes añadir lógica para filtrar horarios que ya están reservados si tienes una tabla de reservas
            // Por ahora, solo listamos los que el profesor ha definido como disponibles
            $profesor['horarios_recurrentes'][] = $horario;
        }
        $stmt_recurrente->close();

        // 3. Obtener horarios puntuales para cada profesor
        $stmt_puntual = $conexion->prepare("SELECT id, fecha, hora_inicio, hora_fin, tipo FROM disponibilidad_puntual WHERE profesor_id = ? ORDER BY fecha, hora_inicio");
        if ($stmt_puntual === false) {
            throw new Exception("Error al preparar consulta de horarios puntuales: " . $conexion->error);
        }
        $stmt_puntual->bind_param("i", $profesor['id']);
        $stmt_puntual->execute();
        $result_puntual = $stmt_puntual->get_result();
        while ($horario = $result_puntual->fetch_assoc()) {
            // Aquí es donde puedes aplicar lógica para no mostrar horarios bloqueados o ya pasados/reservados
            // Por ahora, mostramos el tipo para que el JS decida cómo representarlo
            $profesor['horarios_puntuales'][] = $horario;
        }
        $stmt_puntual->close();

        $profesores_data[] = $profesor;
    }
    $stmt_profesores->close();

    echo json_encode($profesores_data);

} catch (Exception $e) {
    error_log("Error en get_profesores_y_horarios.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error interno del servidor al cargar los datos.']);
} finally {
    if (isset($conexion) && $conexion->ping()) {
        $conexion->close();
    }
}
?>