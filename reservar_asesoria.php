<?php
session_start();
include 'conexion.php';

header('Content-Type: application/json');

// Verificar si el alumno ha iniciado sesión
if (!isset($_SESSION["alumno_id"]) || $_SESSION["user_type"] !== "alumno") {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión como alumno para reservar.']);
    exit();
}

$alumno_id = $_SESSION["alumno_id"];
$profesor_id = $_POST['profesor_id'] ?? null;
$horario_id = $_POST['horario_id'] ?? null;
$horario_type = $_POST['horario_type'] ?? null; // 'recurrente' o 'puntual'
$fecha_reserva_str = $_POST['fecha_reserva'] ?? null; // Esta fecha será crucial para horarios recurrentes

if (!$profesor_id || !$horario_id || !$horario_type) {
    echo json_encode(['success' => false, 'message' => 'Datos de reserva incompletos.']);
    exit();
}

// Iniciar transacción
$conexion->begin_transaction();

try {
    $hora_inicio = '';
    $hora_fin = '';
    $fecha_real_reserva = null; // La fecha específica del slot reservado

    if ($horario_type === 'recurrente') {
        // Obtener detalles del horario recurrente
        $stmt = $conexion->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM disponibilidad_recurrente WHERE id = ? AND profesor_id = ?");
        if ($stmt === false) { throw new Exception("Error SQL recurrente: " . $conexion->error); }
        $stmt->bind_param("ii", $horario_id, $profesor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $horario_details = $result->fetch_assoc();
        $stmt->close();

        if (!$horario_details) { throw new Exception("Horario recurrente no encontrado o no pertenece al profesor."); }

        $hora_inicio = $horario_details['hora_inicio'];
        $hora_fin = $horario_details['hora_fin'];

        // Para un horario recurrente, necesitamos una fecha específica.
        // Aquí podrías pedirle al alumno que elija una fecha para ese día de la semana
        // o determinar la próxima ocurrencia. Por simplicidad, asumamos que
        // la fecha ya fue seleccionada en el frontend o determinamos la próxima.
        // Para este ejemplo, voy a asumir que se reserva para la próxima ocurrencia de ese día.
        // *********************************************************************************
        // IMPORTANTE: EN UN SISTEMA REAL, EL ALUMNO DEBERÍA SELECCIONAR LA FECHA ESPECÍFICA
        // PARA UN HORARIO RECURRENTE (EJ: LUNES 9AM DEL 22/JULIO/2025).
        // LA IMPLEMENTACIÓN ACTUAL ES DEMOSTRATIVA.
        // *********************************************************************************
        
        // Simulación: calcular la próxima fecha para ese día de la semana
        $dia_semana_num = $horario_details['dia_semana'];
        $current_day_of_week = date('N', strtotime(date('Y-m-d H:i:s', time() - 3600*5))); // N = 1 (lunes) a 7 (domingo) // Ajuste de hora si el servidor está en otra zona horaria
        $today = new DateTime('now', new DateTimeZone('America/Lima')); // Usar la zona horaria de Chiclayo
        $today->setTime(0,0,0); // Resetear a inicio del día

        if ($current_day_of_week < $dia_semana_num) {
            // El día de la semana es en el futuro de esta semana
            $days_to_add = $dia_semana_num - $current_day_of_week;
        } elseif ($current_day_of_week > $dia_semana_num) {
            // El día de la semana ya pasó esta semana, ir a la próxima semana
            $days_to_add = 7 - ($current_day_of_week - $dia_semana_num);
        } else {
            // Es el mismo día de la semana
            // Comparar con la hora actual para ver si ya pasó
            $current_time = new DateTime('now', new DateTimeZone('America/Lima'));
            $horario_time = DateTime::createFromFormat('H:i:s', $hora_inicio);
            
            if ($current_time->format('H:i:s') < $horario_time->format('H:i:s')) {
                // Es el mismo día y la hora aún no ha pasado
                $days_to_add = 0;
            } else {
                // Es el mismo día pero la hora ya pasó, reservar para la próxima semana
                $days_to_add = 7;
            }
        }
        $today->modify("+$days_to_add days");
        $fecha_real_reserva = $today->format('Y-m-d');


    } elseif ($horario_type === 'puntual') {
        // Obtener detalles del horario puntual
        $stmt = $conexion->prepare("SELECT fecha, hora_inicio, hora_fin, tipo FROM disponibilidad_puntual WHERE id = ? AND profesor_id = ?");
        if ($stmt === false) { throw new Exception("Error SQL puntual: " . $conexion->error); }
        $stmt->bind_param("ii", $horario_id, $profesor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $horario_details = $result->fetch_assoc();
        $stmt->close();

        if (!$horario_details || $horario_details['tipo'] === 'bloqueado') {
            throw new Exception("Horario puntual no encontrado, no pertenece al profesor o está bloqueado.");
        }

        $hora_inicio = $horario_details['hora_inicio'];
        $hora_fin = $horario_details['hora_fin'];
        $fecha_real_reserva = $horario_details['fecha'];

    } else {
        throw new Exception("Tipo de horario inválido.");
    }

    // Validación final: Asegurar que la fecha_real_reserva no sea nula y esté en el futuro
    if (!$fecha_real_reserva) {
        throw new Exception("No se pudo determinar la fecha de la reserva.");
    }
    
    // Convertir a objetos DateTime para comparación
    $now = new DateTime('now', new DateTimeZone('America/Lima'));
    $reservation_datetime = new DateTime($fecha_real_reserva . ' ' . $hora_inicio, new DateTimeZone('America/Lima'));

    if ($reservation_datetime < $now) {
        throw new Exception("No se pueden reservar horarios en el pasado.");
    }

    // Verificar si el horario ya está reservado para esa fecha/hora específica
    $stmt_check_reservation = $conexion->prepare("SELECT id FROM asesorias_reservadas WHERE profesor_id = ? AND fecha_reserva = ? AND hora_inicio = ? AND hora_fin = ? AND estado != 'cancelada'");
    if ($stmt_check_reservation === false) { throw new Exception("Error SQL al verificar reserva: " . $conexion->error); }
    $stmt_check_reservation->bind_param("isss", $profesor_id, $fecha_real_reserva, $hora_inicio, $hora_fin);
    $stmt_check_reservation->execute();
    $result_check = $stmt_check_reservation->get_result();
    if ($result_check->num_rows > 0) {
        throw new Exception("Este horario ya está reservado.");
    }
    $stmt_check_reservation->close();


    // Insertar la reserva en la tabla asesorias_reservadas
    $stmt_insert_reserva = $conexion->prepare("
        INSERT INTO asesorias_reservadas (alumno_id, profesor_id, horario_tipo, horario_id, fecha_reserva, hora_inicio, hora_fin, estado)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
    ");
    if ($stmt_insert_reserva === false) { throw new Exception("Error SQL al insertar reserva: " . $conexion->error); }

    $stmt_insert_reserva->bind_param("iisssss", $alumno_id, $profesor_id, $horario_type, $horario_id, $fecha_real_reserva, $hora_inicio, $hora_fin);

    if (!$stmt_insert_reserva->execute()) {
        throw new Exception("Error al guardar la reserva: " . $stmt_insert_reserva->error);
    }
    $stmt_insert_reserva->close();

    $conexion->commit();
    echo json_encode(['success' => true, 'message' => 'Asesoría reservada con éxito. Esperando confirmación del profesor.']);

} catch (Exception $e) {
    $conexion->rollback();
    error_log("Error al reservar asesoría: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conexion) && $conexion->ping()) {
        $conexion->close();
    }
}
?>