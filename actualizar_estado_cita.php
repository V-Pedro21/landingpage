<?php
session_start();
include 'conexion.php'; // Tu archivo de conexión a la BD

header('Content-Type: application/json'); // Asegurarse de que la respuesta sea JSON

$response = ['success' => false, 'message' => ''];

// Verificar si el profesor ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    $response['message'] = "Acceso no autorizado.";
    echo json_encode($response);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cita_id = $_POST['cita_id'] ?? null; // Ahora espera 'cita_id'
    $nuevo_estado = $_POST['estado'] ?? null; // Ahora espera 'estado'
    $profesor_id = $_SESSION["profesor_id"];

    // Validaciones
    if (empty($cita_id) || empty($nuevo_estado)) {
        $response['message'] = "Datos incompletos para actualizar el estado de la cita.";
        echo json_encode($response);
        exit();
    }

    // Validar que el nuevo estado sea uno de los valores permitidos en tu ENUM
    // Estos deben coincidir exactamente con los valores de tu ENUM en la BD
    $estados_permitidos = ['Programada', 'Completada', 'Cancelada', 'Reprogramada'];
    if (!in_array($nuevo_estado, $estados_permitidos)) {
        $response['message'] = "Estado no válido proporcionado.";
        echo json_encode($response);
        exit();
    }

    $conexion->begin_transaction();

    try {
        // Prevenir la actualización de citas que no pertenecen a este profesor
        $stmt = $conexion->prepare("UPDATE citas SET estado = ? WHERE id = ? AND profesor_id = ?");
        
        if ($stmt === false) {
            throw new Exception("Error al preparar la actualización: " . $conexion->error);
        }
        
        $stmt->bind_param("sii", $nuevo_estado, $cita_id, $profesor_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar la actualización: " . $stmt->error);
        }

        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = "Estado de la cita actualizado a '" . $nuevo_estado . "' exitosamente.";
            $conexion->commit();
        } else {
            // Esto puede pasar si la cita_id no existe o no pertenece a este profesor
            $response['message'] = "No se pudo actualizar la cita. Es posible que no exista o no sea suya.";
            $conexion->rollback(); // No hubo cambios, pero por seguridad
        }
        
        $stmt->close();

    } catch (Exception $e) {
        $conexion->rollback();
        $response['message'] = "Error interno del servidor: " . $e->getMessage();
        error_log("Error en actualizar_estado_cita.php: " . $e->getMessage());
    } finally {
        if (isset($conexion) && $conexion->ping()) {
            $conexion->close();
        }
    }
} else {
    $response['message'] = "Solicitud inválida.";
}

echo json_encode($response);
?>