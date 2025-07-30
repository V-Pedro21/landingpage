<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Verificar si el profesor ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo profesores.']);
    exit();
}

$profesor_id = $_SESSION["profesor_id"];
$reserva_id = $_POST['reserva_id'] ?? null;
$nuevo_estado = $_POST['estado'] ?? null;

// Validar entrada
if (!$reserva_id || !in_array($nuevo_estado, ['confirmada', 'cancelada'])) {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos para la actualización.']);
    exit();
}

try {
    // Asegurarse de que el profesor solo pueda actualizar sus propias solicitudes
    $stmt = $conexion->prepare("UPDATE asesorias_reservadas SET estado = ? WHERE id = ? AND profesor_id = ?");
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta de actualización: " . $conexion->error);
    }

    $stmt->bind_param("sii", $nuevo_estado, $reserva_id, $profesor_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Estado de la asesoría actualizado a ' . $nuevo_estado . '.']);
        } else {
            // Esto podría pasar si la reserva ya tiene ese estado o si no existe/no pertenece al profesor
            echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el estado de la asesoría. Verifique si la reserva existe o si es suya.']);
        }
    } else {
        throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    error_log("Error en actualizar_estado_asesoria.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno del servidor: ' . $e->getMessage()]);
} finally {
    if (isset($conexion) && $conexion->ping()) {
        $conexion->close();
    }
}
?>