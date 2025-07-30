<?php
session_start();
include 'conexion.php'; // Tu archivo de conexión
header('Content-Type: application/json');

$profesor_id = $_GET['profesor_id'] ?? null;
$available_slots = [];

if (!$profesor_id) {
    echo json_encode([]);
    exit();
}

// Funciones auxiliares (copiadas de buscar_asesorias.php)
$diasSemanaMap = [
    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
    4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
];

function getRecurringScheduleOccurrences($targetDay, $startTime, $endTime, $validFrom, $validUntil) {
    global $diasSemanaMap; // Aunque diasSemanaMap no se usa directamente aquí, es parte del contexto global
    $occurrences = [];
    $today = new DateTime();
    $today->setTime(0, 0, 0); // Reset time to midnight for date comparison

    $validFromDate = new DateTime($validFrom);
    $validUntilDate = new DateTime($validUntil);
    $validUntilDate->setTime(23, 59, 59); // Set to end of day for inclusive comparison

    // Use current date for "today" to avoid showing past slots
    $currentDateTime = new DateTime(); 

    // Determine the first valid occurrence date for the target day of the week
    $firstOccurrence = clone $today; // Start from today's date
    $dayOfWeek = (int)$firstOccurrence->format('N'); // Current day of week (1 for Monday, 7 for Sunday)

    $daysToAdd = ($targetDay - $dayOfWeek);
    if ($daysToAdd < 0) {
        $daysToAdd += 7; // If target day is earlier in the week, go to next week
    }
    $firstOccurrence->modify("+$daysToAdd days");

    // Adjust if the calculated first occurrence is before validFromDate
    if ($firstOccurrence < $validFromDate) {
        $firstOccurrence->modify('+7 days');
    }

    $loopDate = clone $firstOccurrence;
    while ($loopDate <= $validUntilDate) {
        // Create a full DateTime object for the end of the slot on the current loop date
        $slotEndDateTime = new DateTime($loopDate->format('Y-m-d') . ' ' . $endTime);

        // Only add if the slot ends in the future relative to now
        if ($slotEndDateTime > $currentDateTime) {
            $occurrences[] = [
                'date' => $loopDate->format('Y-m-d'),
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        }
        $loopDate->modify('+7 days'); // Move to the same day next week
    }
    return $occurrences;
}

function splitSlotInto30MinIntervals($date, $startTime, $endTime, $prefix = '') {
    $subSlots = [];
    $currentSlotStart = new DateTime($date . ' ' . $startTime);
    $slotEnd = new DateTime($date . ' ' . $endTime);
    $interval = new DateInterval('PT30M'); // 30 minutos

    $now = new DateTime(); // Get current time once for efficiency

    while ($currentSlotStart < $slotEnd) { // Changed to strict less than
        $subSlotEnd = clone $currentSlotStart;
        $subSlotEnd->add($interval);

        // Only add if the sub-slot's end time is in the future
        if ($subSlotEnd > $now) { 
            $subSlots[] = [
                'date' => $date,
                'start_time' => $currentSlotStart->format('H:i:s'),
                'end_time' => $subSlotEnd->format('H:i:s'),
                'display' => trim($prefix . ' ' . $date . ' de ' . $currentSlotStart->format('H:i') . ' a ' . $subSlotEnd->format('H:i'))
            ];
        }
        $currentSlotStart = $subSlotEnd; // Move start to end of previous sub-slot
    }
    return $subSlots;
}

try {
    // 1. Obtener horarios recurrentes del profesor
    $stmt_recurrente = $conexion->prepare("
        SELECT id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez
        FROM disponibilidad_recurrente
        WHERE profesor_id = ?
        ORDER BY dia_semana, hora_inicio
    ");
    if ($stmt_recurrente === false) {
        throw new Exception("Error al preparar la consulta de disponibilidad recurrente: " . $conexion->error);
    }
    $stmt_recurrente->bind_param("i", $profesor_id);
    $stmt_recurrente->execute();
    $result_recurrente = $stmt_recurrente->get_result();
    
    $recurrent_slots_raw = [];
    while ($row = $result_recurrente->fetch_assoc()) {
        $recurrent_slots_raw[] = $row;
    }
    $stmt_recurrente->close();

    // 2. Obtener horarios puntuales disponibles del profesor
    $stmt_puntual = $conexion->prepare("
        SELECT id, fecha, hora_inicio, hora_fin, tipo
        FROM disponibilidad_puntual
        WHERE profesor_id = ?
        AND tipo = 'disponible'
        AND CONCAT(fecha, ' ', hora_fin) > NOW()
        ORDER BY fecha, hora_inicio
    ");
    if ($stmt_puntual === false) {
        throw new Exception("Error al preparar la consulta de disponibilidad puntual: " . $conexion->error);
    }
    $stmt_puntual->bind_param("i", $profesor_id);
    $stmt_puntual->execute();
    $result_puntual = $stmt_puntual->get_result();

    $puntual_slots_raw = [];
    while ($row = $result_puntual->fetch_assoc()) {
        $puntual_slots_raw[] = $row;
    }
    $stmt_puntual->close();

    // 3. Obtener horarios ya ocupados (de la tabla 'citas')
    // Considerar citas en estado 'Programada', 'Pendiente' (si manejas) y que no hayan finalizado
    $stmt_citas = $conexion->prepare("
        SELECT fecha_cita, hora_inicio, hora_fin
        FROM citas
        WHERE profesor_id = ?
        AND estado IN ('Programada', 'Pendiente') -- Ajusta los estados según tu lógica
        AND CONCAT(fecha_cita, ' ', hora_fin) > NOW()
    ");
    if ($stmt_citas === false) {
        throw new Exception("Error al preparar la consulta de citas ocupadas: " . $conexion->error);
    }
    $stmt_citas->bind_param("i", $profesor_id);
    $stmt_citas->execute();
    $result_citas = $stmt_citas->get_result();

    $occupied_slots = [];
    while ($row = $result_citas->fetch_assoc()) {
        $occupied_slots[] = [
            'date' => $row['fecha_cita'],
            'start_time' => $row['hora_inicio'],
            'end_time' => $row['hora_fin']
        ];
    }
    $stmt_citas->close();

    // Función para verificar si un slot está ocupado
    function isSlotOccupied($date, $startTime, $endTime, $occupied_slots) {
        $requestedStart = new DateTime("$date $startTime");
        $requestedEnd = new DateTime("$date $endTime");

        foreach ($occupied_slots as $occupied) {
            $occupiedStart = new DateTime("{$occupied['date']} {$occupied['start_time']}");
            // CORRECCIÓN: Usar 'end_time' que es la clave en el array $occupied
            $occupiedEnd = new DateTime("{$occupied['date']} {$occupied['end_time']}"); 

            // Check for overlap: (Start1 < End2) and (End1 > Start2)
            if ($requestedStart < $occupiedEnd && $requestedEnd > $occupiedStart) {
                return true; // Overlap detected
            }
        }
        return false;
    }

    // Procesar horarios recurrentes
    foreach ($recurrent_slots_raw as $horario_recurrente) {
        $fullOccurrences = getRecurringScheduleOccurrences(
            $horario_recurrente['dia_semana'],
            $horario_recurrente['hora_inicio'],
            $horario_recurrente['hora_fin'],
            $horario_recurrente['fecha_inicio_validez'],
            $horario_recurrente['fecha_fin_validez']
        );

        foreach ($fullOccurrences as $fullOccurrence) {
            $subSlots = splitSlotInto30MinIntervals(
                $fullOccurrence['date'],
                $fullOccurrence['start_time'],
                $fullOccurrence['end_time'],
                $diasSemanaMap[$horario_recurrente['dia_semana']] . ' '
            );

            foreach ($subSlots as $subSlot) {
                // Verificar si el subSlot está ocupado antes de añadirlo
                if (!isSlotOccupied($subSlot['date'], $subSlot['start_time'], $subSlot['end_time'], $occupied_slots)) {
                    $available_slots[] = [
                        'id' => 'rec_' . $horario_recurrente['id'] . '_' . strtotime($subSlot['date'] . $subSlot['start_time']), // Unique ID for recurring slots
                        'date' => $subSlot['date'],
                        'start_time' => $subSlot['start_time'],
                        'end_time' => $subSlot['end_time'],
                        'display' => $subSlot['display']
                    ];
                }
            }
        }
    }

    // Procesar horarios puntuales
    foreach ($puntual_slots_raw as $horario) {
        $subSlots = splitSlotInto30MinIntervals(
            $horario['fecha'],
            $horario['hora_inicio'],
            $horario['hora_fin'],
            'Puntual:'
        );

        foreach ($subSlots as $subSlot) {
            // Verificar si el subSlot está ocupado antes de añadirlo
            if (!isSlotOccupied($subSlot['date'], $subSlot['start_time'], $subSlot['end_time'], $occupied_slots)) {
                $available_slots[] = [
                    'id' => 'pun_' . $horario['id'] . '_' . strtotime($subSlot['date'] . $subSlot['start_time']), // Unique ID for punctual slots
                    'date' => $subSlot['date'],
                    'start_time' => $subSlot['start_time'],
                    'end_time' => $subSlot['end_time'],
                    'display' => $subSlot['display']
                ];
            }
        }
    }
    
    // Opcional: Ordenar los slots por fecha y hora para una mejor presentación
    usort($available_slots, function($a, $b) {
        $dateTimeA = new DateTime($a['date'] . ' ' . $a['start_time']);
        $dateTimeB = new DateTime($b['date'] . ' ' . $b['start_time']);
        return $dateTimeA <=> $dateTimeB;
    });

} catch (Exception $e) {
    error_log("Error en get_available_slots.php: " . $e->getMessage());
    echo json_encode(['error' => 'Error al cargar los horarios: ' . $e->getMessage()]);
    exit();
} finally {
    if (isset($conexion) && $conexion->ping()) {
        $conexion->close();
    }
}

echo json_encode($available_slots);
?>