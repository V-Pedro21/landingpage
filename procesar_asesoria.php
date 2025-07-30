<?php
session_start();
include 'conexion.php'; // Asegúrate de que tu conexión a la base de datos esté incluida

// Verificar si el alumno está logueado y si la solicitud es POST
if (!isset($_SESSION["alumno_id"]) || $_SESSION["user_type"] !== "alumno") {
    header("Location: login_portal.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $alumno_id = $_SESSION["alumno_id"];

    // Obtener y sanitizar datos del formulario
    $profesor_id = filter_input(INPUT_POST, 'profesor_id', FILTER_VALIDATE_INT);
    $horario_seleccionado_str = filter_input(INPUT_POST, 'horario_seleccionado', FILTER_SANITIZE_STRING);
    $comentarios_alumno = filter_input(INPUT_POST, 'mensaje_alumno', FILTER_SANITIZE_STRING, FILTER_FLAG_NO_ENCODE_QUOTES);
    $temas_seleccionados_alumno = $_POST['temas_seleccionados'] ?? []; // Array de IDs de temas

    // Validaciones básicas
    if (!$profesor_id || empty($horario_seleccionado_str)) {
        header("Location: solicitar_asesoria.php?message=Error:+Por+favor,+selecciona+un+profesor+y+un+horario+válido.&type=danger");
        exit();
    }

    // Parsear el string del horario_seleccionado
    // El formato esperado es "ID_HORARIO|YYYY-MM-DD|HH:MM|HH:MM"
    $horario_parts = explode('|', $horario_seleccionado_str);

    if (count($horario_parts) !== 4) {
        header("Location: solicitar_asesoria.php?message=Error:+Formato+de+horario+inválido.+Por+favor,+inténtalo+de+nuevo.&type=danger");
        exit();
    }

    $horario_id_to_update = filter_var($horario_parts[0], FILTER_VALIDATE_INT);
    $fecha_cita = $horario_parts[1];
    $hora_inicio = $horario_parts[2];
    $hora_fin = $horario_parts[3];

    // --- LÓGICA: Obtener el tema_principal_id del profesor ---
    $profesor_tema_principal_id = null;
    $stmt_profesor_tema = $conexion->prepare("SELECT tema_principal_id FROM profesores WHERE id = ?");
    if ($stmt_profesor_tema) {
        $stmt_profesor_tema->bind_param("i", $profesor_id);
        $stmt_profesor_tema->execute();
        $stmt_profesor_tema->bind_result($profesor_tema_principal_id);
        $stmt_profesor_tema->fetch();
        $stmt_profesor_tema->close();
    }

    // Validar que se obtuvo un tema_principal_id válido del profesor
    if (!$profesor_tema_principal_id) {
        header("Location: solicitar_asesoria.php?message=Error:+El+profesor+seleccionado+no+tiene+un+tema+principal+válido+asignado.+Asegúrate+de+que+la+columna+exista+y+tenga+datos.&type=danger");
        exit();
    }
    // --- FIN LÓGICA TEMA PROFESOR ---


    try {
        date_default_timezone_set('America/Lima'); // Asegúrate de que esta sea la zona horaria correcta
        $fecha_creacion = date('Y-m-d H:i:s'); // Fecha y hora actual para la solicitud y la cita
        $estado_solicitud = 'pendiente'; // Estado inicial para la solicitud

        // Iniciar transacción
        $conexion->begin_transaction();

        // 1. Insertar la nueva solicitud en la tabla 'solicitudes_asesoria'
        // *** CAMBIO CLAVE AQUÍ: Se añadió 'tema_principal_id' a la inserción ***
        $stmt_solicitud = $conexion->prepare("INSERT INTO solicitudes_asesoria (alumno_id, profesor_id, mensaje_alumno, tema_principal_id, fecha_solicitud, estado) VALUES (?, ?, ?, ?, ?, ?)");

        if ($stmt_solicitud === false) {
            throw new Exception("Error al preparar la consulta de inserción de solicitud: " . $conexion->error);
        }

        // *** CAMBIO CLAVE AQUÍ: Se añadió el tipo 'i' y la variable para 'tema_principal_id' ***
        $stmt_solicitud->bind_param(
            "isiiss", // i: alumno_id, profesor_id, tema_principal_id (int); s: mensaje_alumno, fecha_solicitud, estado (string)
            $alumno_id,
            $profesor_id,
            $comentarios_alumno,
            $profesor_tema_principal_id, // Se usa el tema principal del profesor aquí
            $fecha_creacion, // fecha_solicitud de la tabla solicitudes_asesoria
            $estado_solicitud
        );

        if (!$stmt_solicitud->execute()) {
            throw new Exception("Error al insertar en solicitudes_asesoria: " . $stmt_solicitud->error);
        }
        $solicitud_asesoria_id = $conexion->insert_id; // Obtener el ID de la solicitud recién insertada
        $stmt_solicitud->close();

        // 2. Insertar los temas seleccionados por el ALUMNO en la tabla de relación 'solicitud_tema'
        if (!empty($temas_seleccionados_alumno) && is_array($temas_seleccionados_alumno)) {
            $stmt_temas = $conexion->prepare("INSERT INTO solicitud_tema (solicitud_id, tema_id) VALUES (?, ?)");
            if ($stmt_temas === false) {
                throw new Exception("Error al preparar la consulta de inserción de temas de solicitud: " . $conexion->error);
            }
            foreach ($temas_seleccionados_alumno as $tema_id_alumno) {
                $tema_id_alumno = filter_var($tema_id_alumno, FILTER_VALIDATE_INT);
                if ($tema_id_alumno) {
                    $stmt_temas->bind_param("ii", $solicitud_asesoria_id, $tema_id_alumno);
                    if (!$stmt_temas->execute()) {
                        throw new Exception("Error al insertar tema {$tema_id_alumno} para solicitud {$solicitud_asesoria_id}: " . $stmt_temas->error);
                    }
                }
            }
            $stmt_temas->close();
        }

        // 3. Insertar la cita en la tabla 'citas'
        $estado_cita = 'Programada';

        // *** CAMBIO CLAVE AQUÍ: Se añadieron 'notas_cita' y 'mensaje_profesor' como NULL para coincidir con la estructura de la tabla 'citas' ***
        // *** También se corrigió el tipo de bind_param para 'solicitud_id' a 'i' (int) ***
        $stmt_cita = $conexion->prepare("INSERT INTO citas (solicitud_id, fecha_cita, hora_inicio, hora_fin, profesor_id, alumno_id, tema_id, estado, notas_cita, fecha_creacion, comentarios_adicionales, mensaje_profesor, fecha_respuesta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt_cita === false) {
            throw new Exception("Error al preparar la consulta de inserción de cita: " . $conexion->error);
        }

        $stmt_cita->bind_param(
            "isssiiisssss", // i: solicitud_id, profesor_id, alumno_id, tema_id (int); s: fecha, horas, estado, notas_cita, fecha_creacion, comentarios_adicionales, mensaje_profesor, fecha_respuesta (string)
            $solicitud_asesoria_id, // Usamos el ID de la solicitud para la FK
            $fecha_cita,
            $hora_inicio,
            $hora_fin,
            $profesor_id,
            $alumno_id,
            $profesor_tema_principal_id, // Usamos el tema principal del profesor aquí
            $estado_cita,
            null, // notas_cita: de momento NULL (la llena el profesor después)
            $fecha_creacion, // fecha_creacion de la cita
            $comentarios_alumno, // comentarios_adicionales: el mensaje del alumno del formulario
            null, // mensaje_profesor: de momento NULL (lo llena el profesor después)
            null  // fecha_respuesta: de momento NULL (la llena el profesor después)
        );

        if (!$stmt_cita->execute()) {
            throw new Exception("Error al insertar la cita en la tabla citas: " . $stmt_cita->error);
        }
        $stmt_cita->close();


        // 4. Actualizar el estado del horario a 'ocupado' en horarios_profesores
        $stmt_update_horario = $conexion->prepare("UPDATE horarios_profesores SET estado = 'ocupado' WHERE id = ?");
        if ($stmt_update_horario === false) {
            throw new Exception("Error al preparar la actualización de estado de horario: " . $conexion->error);
        }
        if (!$horario_id_to_update) {
             throw new Exception("ID de horario para actualizar no válido.");
        }
        $stmt_update_horario->bind_param("i", $horario_id_to_update);
        if (!$stmt_update_horario->execute()) {
            throw new Exception("Error al actualizar el estado del horario: " . $stmt_update_horario->error);
        }
        $stmt_update_horario->close();

        // Si todo fue exitoso, confirmar la transacción
        $conexion->commit();

        header("Location: panel_alumno.php?message=Tu+solicitud+de+asesoría+ha+sido+enviada+exitosamente.+Se+ha+creado+una+cita.&type=success");
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conexion->rollback();
        error_log("Error en procesar_asesoria.php: " . $e->getMessage());
        header("Location: panel_alumno.php?message=Ocurrió+un+error+al+procesar+tu+solicitud.+Por+favor,+inténtalo+de+nuevo.+Detalle:+" . urlencode($e->getMessage()) . "&type=danger");
        exit();
    } finally {
        $conexion->close();
    }
} else {
    // Si no es una solicitud POST, redirigir al formulario
    header("Location: solicitar_asesoria.php");
    exit();
}
?>