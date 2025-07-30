<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo establece $conexion correctamente

// --- INICIO DE DEPURACIÓN ---
error_log("--- registrar_alumno.php started ---");
error_log("POST data received: " . print_r($_POST, true)); // Muestra toda la data POST
error_log("DEBUG: Nombre: " . ($_POST["nombre"] ?? 'NOT SET'));
error_log("DEBUG: Correo: " . ($_POST["correo"] ?? 'NOT SET'));
error_log("DEBUG: Celular: " . ($_POST["celular"] ?? 'NOT SET'));
error_log("DEBUG: Contrasena: " . (isset($_POST["contrasena"]) ? 'SET' : 'NOT SET'));
error_log("DEBUG: Horario Seleccionado (raw JSON from form): " . ($_POST["horario_seleccionado"] ?? 'NOT SET'));
// NOTA: Para temas_seleccionados, ahora se espera que sea un ARRAY directamente de $_POST
error_log("DEBUG: Temas Seleccionados (raw from form, should be array): " . print_r($_POST["temas_seleccionados"] ?? [], true));
error_log("DEBUG: Mensaje Alumno: " . ($_POST["mensaje_alumno"] ?? 'NOT SET'));
// --- FIN DE DEPURACIÓN ---

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Obtener datos del formulario de alumno
    $nombre = $_POST["nombre"] ?? '';
    $correo = $_POST["correo"] ?? '';
    $celular = $_POST["celular"] ?? '';
    $contrasena_plana = $_POST["contrasena"] ?? '';
    $idea_negocio = $_POST["idea"] ?? null; // Opcional

    // 2. Obtener datos de la solicitud de asesoría

    // *** CAMBIO APLICADO AQUÍ: Ya no se decodifica JSON para temas_seleccionados ***
    // Se asume que $_POST["temas_seleccionados"] ya es un array de IDs.
    $temas_seleccionados_array = $_POST["temas_seleccionados"] ?? []; 

    // *** CAMBIO CLAVE: Obtener y decodificar el JSON de los horarios seleccionados ***
    // Esta línea sigue siendo necesaria porque 'horario_seleccionado' sí viene como JSON string.
    $horarios_seleccionados_json = $_POST["horario_seleccionado"] ?? '[]'; 
    $selected_slots = json_decode($horarios_seleccionados_json, true);

    // Validaciones básicas de campos requeridos
    if (empty($nombre) || empty($correo) || empty($celular) || empty($contrasena_plana)) {
        $_SESSION['error_message'] = "Todos los campos obligatorios del alumno (Nombre, Correo, Celular, Contraseña) deben ser completados.";
        header("Location: alumno.php");
        exit();
    }
    
    // Validar que al menos un horario fue seleccionado (la cantidad de slots en el array)
    if (empty($selected_slots) || !is_array($selected_slots)) {
        $_SESSION['error_message'] = "No se seleccionó ningún horario de asesoría. Por favor, elige al menos uno.";
        header("Location: alumno.php"); // Redirige a alumno.php si no hay horarios seleccionados
        exit();
    }

    // Hash de la contraseña
    $contrasena_hasheada = password_hash($contrasena_plana, PASSWORD_DEFAULT);

    // Iniciar una transacción
    $conexion->begin_transaction();

    try {
        // 1. Insertar la información del alumno
        $stmt_alumno = $conexion->prepare("INSERT INTO alumnos (nombre, correo, celular, contrasena, idea_negocio) VALUES (?, ?, ?, ?, ?)");
        if ($stmt_alumno === false) {
            throw new Exception("Error al preparar la consulta de alumno: " . $conexion->error);
        }
        $stmt_alumno->bind_param("sssss", $nombre, $correo, $celular, $contrasena_hasheada, $idea_negocio);
        if (!$stmt_alumno->execute()) {
            if ($conexion->errno == 1062) { // Código de error para duplicado de entrada (e.g., email único)
                throw new Exception("El correo electrónico ya está registrado. Por favor, inicia sesión o usa otro correo.");
            }
            throw new Exception("Error al insertar alumno: " . $stmt_alumno->error);
        }
        $alumno_id = $stmt_alumno->insert_id;
        $stmt_alumno->close();

        // **IMPORTANTE: INICIAR SESIÓN DEL ALUMNO DESPUÉS DEL REGISTRO EXITOSO**
        $_SESSION['alumno_id'] = $alumno_id;
        $_SESSION['user_type'] = 'alumno';
        $_SESSION['user_name'] = $nombre; // Guardamos el nombre para mostrarlo en el panel

        // Obtener el tema principal (asumimos el primero de la lista, si hay varios)
        $tema_principal_id = !empty($temas_seleccionados_array) ? (int)$temas_seleccionados_array[0] : null;

        // 2. Iterar sobre cada slot seleccionado e insertar una nueva cita/asesoría
        $stmt_asesoria = $conexion->prepare("INSERT INTO citas (alumno_id, profesor_id, tema_id, fecha_cita, hora_inicio, hora_fin, comentarios_adicionales, estado) VALUES (?, ?, ?, ?, ?, ?, ?, 'Programada')");

        if ($stmt_asesoria === false) {
            throw new Exception("Error al preparar la consulta de asesoría: " . $conexion->error);
        }

        foreach ($selected_slots as $slot) {
            $profesor_id_slot = $slot['profesorId']; // Obtener el profesor_id de cada slot
            $horario_data_str = $slot['horarioData']; // Esto es "YYYY-MM-DD|HH:MM:SS|HH:MM:SS"

            $parts = explode('|', $horario_data_str);
            if (count($parts) === 3) {
                $fecha_cita_asesoria = $parts[0];
                $hora_inicio_asesoria = $parts[1];
                $hora_fin_asesoria = $parts[2];
            } else {
                // Loguear o manejar el error si un slot tiene un formato de horario inválido
                error_log("Formato de horario_data_str no válido para slot: " . $horario_data_str);
                continue; // Saltar al siguiente slot si el actual es inválido
            }

            // Bind de parámetros para la inserción de la cita individual
            // iiissss -> int, int, int, string, string, string, string
            // $alumno_id, $profesor_id_slot, $tema_principal_id, $fecha_cita_asesoria, $hora_inicio_asesoria, $hora_fin_asesoria, $mensaje_alumno
            $stmt_asesoria->bind_param("iiissss", $alumno_id, $profesor_id_slot, $tema_principal_id, $fecha_cita_asesoria, $hora_inicio_asesoria, $hora_fin_asesoria, $mensaje_alumno);
            
            if (!$stmt_asesoria->execute()) {
                throw new Exception("Error al insertar solicitud de asesoría para profesor ID " . $profesor_id_slot . ": " . $stmt_asesoria->error);
            }
        }
        $stmt_asesoria->close();

        // Si todo fue exitoso, confirmar la transacción
        $conexion->commit();
        $_SESSION['success_message'] = "✅ Tu registro y solicitudes de asesoría han sido enviadas. Serás contactado pronto por los profesores.";
        
        // Redireccionar al panel del alumno o a una página de resumen de reservas
        header("Location: panel_alumno.php"); 
        exit(); 

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conexion->rollback();
        error_log("Error en el registro de alumno y asesoría: " . $e->getMessage());
        $_SESSION['error_message'] = "❌ Error al procesar tu solicitud: " . htmlspecialchars($e->getMessage()) . ". Por favor, inténtalo de nuevo.";
        
        // Redirige de vuelta al formulario de registro, manteniendo los datos si es posible (más complejo)
        header("Location: alumno.php"); 
        exit(); 
    } finally {
        if (isset($conexion) && $conexion->ping()) {
            $conexion->close();
        }
    }
} else {
    // Si se accede directamente por GET sin POST, redirige al formulario de alumno
    header("Location: alumno.php"); 
    exit();
}
?>