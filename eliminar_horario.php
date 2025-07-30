<?php
session_start();
include 'conexion.php';

// Redirigir si el profesor no ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    header("Location: login_portal.php");
    exit();
}

$profesor_id = $_SESSION["profesor_id"];
$horario_id = $_GET['id'] ?? null;
$horario_type = $_GET['type'] ?? null; // 'recurrente' o 'puntual'

if ($horario_id === null || $horario_type === null) {
    $_SESSION['error_message'] = "Parámetros de eliminación inválidos.";
    header("Location: panel_profesor.php");
    exit();
}

$success = false;
$message = '';

if ($horario_type === 'recurrente') {
    $stmt = $conexion->prepare("DELETE FROM disponibilidad_recurrente WHERE id = ? AND profesor_id = ?");
    if ($stmt === false) { $message = "Error al preparar eliminación recurrente: " . $conexion->error; }
    else {
        $stmt->bind_param("ii", $horario_id, $profesor_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = true;
                $message = "Horario recurrente eliminado con éxito.";
            } else {
                $message = "Horario recurrente no encontrado o no te pertenece.";
            }
        } else {
            $message = "Error al eliminar horario recurrente: " . $stmt->error;
        }
        $stmt->close();
    }
} elseif ($horario_type === 'puntual') {
    $stmt = $conexion->prepare("DELETE FROM disponibilidad_puntual WHERE id = ? AND profesor_id = ?");
    if ($stmt === false) { $message = "Error al preparar eliminación puntual: " . $conexion->error; }
    else {
        $stmt->bind_param("ii", $horario_id, $profesor_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $success = true;
                $message = "Horario puntual eliminado con éxito.";
            } else {
                $message = "Horario puntual no encontrado o no te pertenece.";
            }
        } else {
            $message = "Error al eliminar horario puntual: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $message = "Tipo de horario desconocido para eliminación.";
}

$conexion->close();

if ($success) {
    $_SESSION['success_message'] = $message;
} else {
    $_SESSION['error_message'] = $message;
}
header("Location: panel_profesor.php"); // Redirigir de vuelta al panel
exit();
?>