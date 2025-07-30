<?php
session_start();
include 'conexion.php'; 

// Redirigir si el alumno NO ha iniciado sesión
if (!isset($_SESSION["alumno_id"]) || $_SESSION["user_type"] !== "alumno") {
    $_SESSION['error_message'] = "Debes iniciar sesión para realizar una nueva solicitud de asesoría.";
    header("Location: login_alumno.php"); // O tu página de login de alumnos
    exit();
}

// El ID del alumno logueado
$alumno_id = $_SESSION["alumno_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Obtener datos de la solicitud de asesoría
    // El horario seleccionado ahora es un JSON string
    $horario_seleccionado_json = $_POST["horario_seleccionado"] ?? ''; 
    $selected_slot_data = json_decode($horario_seleccionado_json, true);

    // El tema principal seleccionado (el primero de la lista o el oculto)
    $primer_tema_id = $_POST["primer_tema_id"] ?? null; // Si viene del input oculto

    // Si tu Select2 de temas permite múltiple selección y quieres el array completo:
    $temas_seleccionados_array = $_POST["temas_seleccionados"] ?? [];
    if (empty($primer_tema_id) && !empty($temas_seleccionados_array)) {
        $primer_tema_id = (int)$temas_seleccionados_array[0]; // Usar el primer tema como principal
    }


    $mensaje_alumno = $_POST["mensaje_alumno"] ?? ''; 

    // Validar datos esenciales
    if (empty($selected_slot_data) || !is_array($selected_slot_data) || empty($primer_tema_id)) {
        $_SESSION['error_message'] = "Datos de horario o tema incompletos. Por favor, inténtalo de nuevo.";
        header("Location: solicitar_asesoria.php"); 
        exit();
    }

    $profesor_id = $selected_slot_data['profesor_id'] ?? null;
    $fecha_cita = $selected_slot_data['date'] ?? null;
    $hora_inicio = $selected_slot_data['start_time'] ?? null;
    $hora_fin = $selected_slot_data['end_time'] ?? null;

    if (!$profesor_id || !$fecha_cita || !$hora_inicio || !$hora_fin) {
        $_SESSION['error_message'] = "Información de horario inválida. Por favor, selecciona un horario válido.";
        header("Location: solicitar_asesoria.php"); 
        exit();
    }

    // Iniciar una transacción
    $conexion->begin_transaction();

    try {
        // Verificar si el slot ya está ocupado (doble verificación de seguridad)
        $check_stmt = $conexion->prepare("SELECT COUNT(*) FROM citas WHERE profesor_id = ? AND fecha_cita = ? AND hora_inicio = ? AND hora_fin = ? AND estado IN ('Programada', 'Pendiente')");
        if ($check_stmt === false) {
            throw new Exception("Error al preparar la verificación de slot: " . $conexion->error);
        }
        $check_stmt->bind_param("isss", $profesor_id, $fecha_cita, $hora_inicio, $hora_fin);
        $check_stmt->execute();
        $check_stmt->bind_result($count);
        $check_stmt->fetch();
        $check_stmt->close();

        if ($count > 0) {
            throw new Exception("El horario seleccionado ya no está disponible. Por favor, elige otro.");
        }

        // Insertar la nueva cita/asesoría
        $stmt_asesoria = $conexion->prepare("INSERT INTO citas (alumno_id, profesor_id, tema_id, fecha_cita, hora_inicio, hora_fin, comentarios_adicionales, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'Programada')");
        
        if ($stmt_asesoria === false) {
            throw new Exception("Error al preparar la consulta de asesoría: " . $conexion->error);
        }

        $stmt_asesoria->bind_param("iiissss", $alumno_id, $profesor_id, $primer_tema_id, $fecha_cita, $hora_inicio, $hora_fin, $mensaje_alumno);
        
        if (!$stmt_asesoria->execute()) {
            throw new Exception("Error al insertar solicitud de asesoría: " . $stmt_asesoria->error);
        }
        $stmt_asesoria->close();

        $conexion->commit();
        $_SESSION['success_message'] = "✅ Tu nueva solicitud de asesoría ha sido enviada. Serás contactado pronto por el profesor.";
        
        header("Location: panel_alumno.php"); // Redirige al panel del alumno
        exit(); 

    } catch (Exception $e) {
        $conexion->rollback();
        error_log("Error en nueva solicitud de alumno: " . $e->getMessage());
        $_SESSION['error_message'] = "❌ Error al procesar tu solicitud: " . htmlspecialchars($e->getMessage()) . ". Por favor, inténtalo de nuevo.";
        
        header("Location: solicitar_asesoria.php"); // Redirige de nuevo a la página de solicitud
        exit(); 
    } finally {
        if (isset($conexion) && $conexion->ping()) {
            $conexion->close();
        }
    }
} else {
    header("Location: solicitar_asesoria.php"); // Redirige si se accede directamente por GET
    exit();
}
?>