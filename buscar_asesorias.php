<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ... rest of your code
session_start();
include 'conexion.php'; // Tu archivo de conexión a la BD

// Recibir temas_id si vienen de la URL
$temas_id_filter = [];
if (isset($_GET['temas_id']) && !empty($_GET['temas_id'])) {
    $temas_id_str = $_GET['temas_id'];
    $temas_id_filter = array_map('intval', explode(',', $temas_id_str));
}

// --- DEBUG START: Check GET and filter ---
echo "\n";
echo "\n";
// --- DEBUG END ---
$profesores_data = [];

try {
    $sql = "
        SELECT
            p.id AS profesor_id,
            p.nombre AS profesor_nombre,
            p.correo AS profesor_correo,
            GROUP_CONCAT(DISTINCT t.tema SEPARATOR ', ') AS temas_string
        FROM profesores p
        JOIN temas_profesores tp ON p.id = tp.profesor_id
        JOIN temas t ON tp.tema_id = t.id
    ";

    $params = [];
    $types = "";

    if (!empty($temas_id_filter)) {
        $placeholders = implode(',', array_fill(0, count($temas_id_filter), '?'));
        $sql .= " WHERE tp.tema_id IN ($placeholders)";
        $types .= str_repeat('i', count($temas_id_filter));
        $params = array_merge($params, $temas_id_filter);
    }

    $sql .= " GROUP BY p.id ORDER BY p.nombre ASC";

    $stmt = $conexion->prepare($sql);
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta de profesores: " . $conexion->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $profesores_list = [];
    while ($row = $result->fetch_assoc()) {
        $profesores_list[$row['profesor_id']] = [
            'id' => $row['profesor_id'],
            'nombre' => $row['profesor_nombre'],
            'correo' => $row['profesor_correo'], // Asegúrate de que esta columna exista en tu tabla profesores si no está ya
            'temas' => explode(', ', $row['temas_string']),
            'horarios_recurrentes' => [],
            'horarios_puntuales' => []
        ];
    }
    $stmt->close();

    if (!empty($profesores_list)) {
        $profesor_ids = array_keys($profesores_list);
        $profesor_ids_placeholders = implode(',', array_fill(0, count($profesor_ids), '?'));
        $types_ids = str_repeat('i', count($profesor_ids));

        $stmt_recurrente = $conexion->prepare("
            SELECT id, profesor_id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez
            FROM disponibilidad_recurrente
            WHERE profesor_id IN ($profesor_ids_placeholders)
            ORDER BY profesor_id, dia_semana, hora_inicio
        ");
        if ($stmt_recurrente === false) { throw new Exception("Error al preparar recurrente: " . $conexion->error); }
        $stmt_recurrente->bind_param($types_ids, ...$profesor_ids);
        $stmt_recurrente->execute();
        $result_recurrente = $stmt_recurrente->get_result();
        while ($row = $result_recurrente->fetch_assoc()) {
            if (isset($profesores_list[$row['profesor_id']])) {
                $profesores_list[$row['profesor_id']]['horarios_recurrentes'][] = $row;
            }
        }
        $stmt_recurrente->close();

        $stmt_puntual = $conexion->prepare("
            SELECT id, profesor_id, fecha, hora_inicio, hora_fin, tipo
            FROM disponibilidad_puntual
            WHERE profesor_id IN ($profesor_ids_placeholders)
            AND tipo = 'disponible'
            AND CONCAT(fecha, ' ', hora_fin) > NOW()
            ORDER BY profesor_id, fecha, hora_inicio
        ");
        if ($stmt_puntual === false) { throw new Exception("Error al preparar puntual: " . $conexion->error); }
        $stmt_puntual->bind_param($types_ids, ...$profesor_ids);
        $stmt_puntual->execute();
        $result_puntual = $stmt_puntual->get_result();
        while ($row = $result_puntual->fetch_assoc()) {
            if (isset($profesores_list[$row['profesor_id']])) {
                $profesores_list[$row['profesor_id']]['horarios_puntuales'][] = $row;
            }
        }
        $stmt_puntual->close();
    }

    $profesores_data = array_values($profesores_list);

} catch (Exception $e) {
    error_log("Error en buscar_asesorias.php: " . $e->getMessage());
    $profesores_data = ['error' => 'Error al cargar los datos: ' . $e->getMessage()];
} finally {
    if (isset($conexion) && $conexion->ping()) {
        $conexion->close();
    }
}

$diasSemanaMap = [
    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
    4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
];

/**
 * Genera todas las ocurrencias futuras de un horario recurrente dentro de un rango de fechas.
 *
 * @param int $targetDay Día de la semana (1=Lunes, 7=Domingo)
 * @param string $startTime Hora de inicio (HH:MM:SS)
 * @param string $endTime Hora de fin (HH:MM:SS)
 * @param string $validFrom Fecha de inicio de validez (YYYY-MM-DD)
 * @param string $validUntil Fecha de fin de validez (YYYY-MM-DD)
 * @return array Lista de arrays, cada uno con 'date', 'start_time', 'end_time', 'display'
 */
function getRecurringScheduleOccurrences($targetDay, $startTime, $endTime, $validFrom, $validUntil) {
    global $diasSemanaMap;
    $occurrences = [];
    $today = new DateTime();
    $today->setTime(0, 0, 0);

    $validFromDate = new DateTime($validFrom);
    $validUntilDate = new DateTime($validUntil);
    $validUntilDate->setTime(23, 59, 59);

    $currentDate = max($today, $validFromDate);

    $firstOccurrence = clone $currentDate;
    $dayOfWeek = (int)$firstOccurrence->format('N');

    $daysToAdd = ($targetDay - $dayOfWeek + 7) % 7;
    $firstOccurrence->modify("+$daysToAdd days");

    // Adjusted logic to ensure we don't pick a past slot on the current day if its end time is past
    if ($firstOccurrence < $validFromDate ||
        ($firstOccurrence->format('Y-m-d') === $currentDate->format('Y-m-d') && strtotime($currentDate->format('H:i:s')) >= strtotime($startTime))
    ) {
           $firstOccurrence->modify("+7 days");
    }

    $loopDate = clone $firstOccurrence;
    while ($loopDate <= $validUntilDate) {
        $slotDateTime = new DateTime($loopDate->format('Y-m-d') . ' ' . $endTime);
        if ($slotDateTime > new DateTime()) {
            $occurrences[] = [
                'date' => $loopDate->format('Y-m-d'),
                'start_time' => $startTime,
                'end_time' => $endTime,
                // 'display' will be generated by splitSlotInto30MinIntervals
            ];
        }
        $loopDate->modify('+7 days');
    }

    return $occurrences;
}

/**
 * Divide un slot de tiempo dado en sub-slots de 30 minutos.
 *
 * @param string $date La fecha del slot (YYYY-MM-DD).
 * @param string $startTime La hora de inicio del slot completo (HH:MM:SS).
 * @param string $endTime La hora de fin del slot completo (HH:MM:SS).
 * @param string $prefix Un prefijo para el texto a mostrar (ej. 'Puntual:', 'Recurrente:').
 * @return array Una lista de arrays, cada uno con 'date', 'start_time', 'end_time', 'display'.
 */
function splitSlotInto30MinIntervals($date, $startTime, $endTime, $prefix = '') {
    $subSlots = [];
    $currentSlotStart = new DateTime($date . ' ' . $startTime);
    $slotEnd = new DateTime($date . ' ' . $endTime);
    $interval = new DateInterval('PT30M'); // 30 minutos

    while ($currentSlotStart->add($interval) <= $slotEnd) {
        $subSlotEnd = clone $currentSlotStart;
        $subSlotStart = clone $currentSlotStart;
        $subSlotStart->sub($interval);

        if ($subSlotEnd > new DateTime()) { // Solo añadir si el sub-slot termina en el futuro
            $subSlots[] = [
                'date' => $date,
                'start_time' => $subSlotStart->format('H:i:s'),
                'end_time' => $subSlotEnd->format('H:i:s'),
                'display' => trim($prefix . ' ' . $date . ' de ' . $subSlotStart->format('H:i') . ' a ' . $subSlotEnd->format('H:i'))
            ];
        }
    }
    return $subSlots;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buscar Asesorías</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f9f9f9; }
        .profesor-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background-color: #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .profesor-card h3 {
            color: #0056b3;
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .profesor-card p {
            margin-bottom: 5px;
            color: #555;
        }
        .temas-tag-container {
            margin-top: 10px;
        }
        .tema-tag {
            display: inline-block;
            background-color: #e0f7fa;
            color: #007bb6;
            padding: 5px 10px;
            border-radius: 4px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.85em;
        }
        .horarios-list {
            list-style: none;
            padding: 0;
            margin-top: 15px;
        }
        .horario-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f0f8ff;
            border: 1px solid #cceeff;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 8px;
        }
        .horario-item input[type="checkbox"] {
            margin-right: 10px;
            transform: scale(1.2); /* Make checkbox slightly larger */
        }
        /* New style for the confirm button */
        .confirm-selection-button {
            display: block; /* Take full width */
            margin: 20px auto; /* Center the button */
            padding: 12px 25px;
            background-color: #007bff; /* Blue color */
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .confirm-selection-button:hover {
            background-color: #0056b3;
        }
        .no-results {
            text-align: center;
            color: #777;
            padding: 20px;
            border: 1px dashed #ccc;
            border-radius: 5px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <h2>Profesores y Horarios Disponibles</h2>
    <p>Selecciona uno o más horarios para reservar tu asesoría:</p>

    <?php if (isset($profesores_data['error'])): ?>
        <div class="no-results" style="color: red; border-color: red;">
            <p><strong>Error:</strong> <?php echo htmlspecialchars($profesores_data['error']); ?></p>
            <p>Por favor, informa al administrador del sistema.</p>
        </div>
    <?php elseif (empty($profesores_data)): ?>
        <div class="no-results">
            <p>No se encontraron profesores para los temas seleccionados o no hay horarios disponibles.</p>
            <p>Por favor, vuelve al formulario de registro e intenta seleccionar otros temas.</p>
        </div>
    <?php else: ?>
        <?php foreach ($profesores_data as $profesor): ?>
            <div class="profesor-card">
                <h3><?php echo htmlspecialchars($profesor['nombre']); ?></h3>
                <p><strong>Correo:</strong> <?php echo htmlspecialchars($profesor['correo']); ?></p>
                <div class="temas-tag-container">
                    <strong>Temas de Asesoría:</strong>
                    <?php
                    if (is_array($profesor['temas']) && !empty($profesor['temas'])):
                    ?>
                        <?php foreach ($profesor['temas'] as $tema): ?>
                            <span class="tema-tag"><?php echo htmlspecialchars($tema); ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span>Este profesor no tiene temas de asesoría definidos.</span>
                    <?php endif; ?>
                </div>

                <h4>Horarios Recurrentes Disponibles:</h4>
                <ul class="horarios-list">
                    <?php
                    $hasRecurringSlots = false;
                    if (is_array($profesor['horarios_recurrentes']) && !empty($profesor['horarios_recurrentes'])):
                        foreach ($profesor['horarios_recurrentes'] as $horario_recurrente):
                            $fullOccurrences = getRecurringScheduleOccurrences(
                                $horario_recurrente['dia_semana'],
                                $horario_recurrente['hora_inicio'],
                                $horario_recurrente['hora_fin'],
                                $horario_recurrente['fecha_inicio_validez'],
                                $horario_recurrente['fecha_fin_validez']
                            );

                            foreach ($fullOccurrences as $fullOccurrence):
                                $subSlots = splitSlotInto30MinIntervals(
                                    $fullOccurrence['date'],
                                    $fullOccurrence['start_time'],
                                    $fullOccurrence['end_time'],
                                    $diasSemanaMap[$horario_recurrente['dia_semana']] . ' '
                                );

                                foreach ($subSlots as $subSlot):
                                    $slot_value = $subSlot['date'] . '|' . $subSlot['start_time'] . '|' . $subSlot['end_time'];
                                    $display_text = $subSlot['display'];
                                    $hasRecurringSlots = true;
                        ?>
                                <li class="horario-item">
                                    <label>
                                        <input type="checkbox"
                                            class="asesoria-checkbox"
                                            data-profesor-id="<?php echo htmlspecialchars($profesor['id']); ?>"
                                            data-profesor-name="<?php echo htmlspecialchars($profesor['nombre']); ?>"
                                            data-horario-value="<?php echo htmlspecialchars($slot_value); ?>"
                                            data-horario-display="<?php echo htmlspecialchars($display_text); ?>">
                                        <span><?php echo htmlspecialchars($display_text); ?></span>
                                    </label>
                                </li>
                        <?php
                                endforeach;
                            endforeach;
                        endforeach;
                    endif;

                    if (!$hasRecurringSlots):
                    ?>
                        <li>No hay horarios recurrentes disponibles para este profesor que estén en el futuro y dentro de su periodo de validez.</li>
                    <?php endif; ?>
                </ul>

                <h4>Horarios Puntuales Disponibles:</h4>
                <ul class="horarios-list">
                    <?php
                    $hasPuntualSlots = false;
                    if (is_array($profesor['horarios_puntuales']) && !empty($profesor['horarios_puntuales'])):
                        foreach ($profesor['horarios_puntuales'] as $horario):
                            $subSlots = splitSlotInto30MinIntervals(
                                $horario['fecha'],
                                $horario['hora_inicio'],
                                $horario['hora_fin'],
                                'Puntual:'
                            );

                            foreach ($subSlots as $subSlot):
                                $slot_value = $subSlot['date'] . '|' . $subSlot['start_time'] . '|' . $subSlot['end_time'];
                                $display_text = $subSlot['display'];
                                $hasPuntualSlots = true;
                        ?>
                                <li class="horario-item">
                                    <label>
                                        <input type="checkbox"
                                            class="asesoria-checkbox"
                                            data-profesor-id="<?php echo htmlspecialchars($profesor['id']); ?>"
                                            data-profesor-name="<?php echo htmlspecialchars($profesor['nombre']); ?>"
                                            data-horario-value="<?php echo htmlspecialchars($slot_value); ?>"
                                            data-horario-display="<?php echo htmlspecialchars($display_text); ?>">
                                        <span><?php echo htmlspecialchars($display_text); ?></span>
                                    </label>
                                </li>
                        <?php
                            endforeach;
                        endforeach;
                    endif;

                    if (!$hasPuntualSlots):
                    ?>
                        <li>No hay horarios puntuales disponibles para este profesor.</li>
                    <?php endif; ?>
                </ul>
            </div>
            <?php endforeach; ?>
    <?php endif; ?>

    <div style="text-align: center; margin-top: 30px;">
        <button type="button" id="confirmarSeleccionBtn" class="confirm-selection-button">
            Confirmar Horarios Seleccionados
        </button>
    </div>

    <script>
        $(document).ready(function() {
            console.log('Script de buscar_asesorias.php se está ejecutando!');

            // Cambia el selector para que apunte al ÚNICO botón con el ID 'confirmarSeleccionBtn'
            $('#confirmarSeleccionBtn').click(function() {
                console.log('Botón Confirmar Horarios Seleccionados CLICADO.');

                const selectedSlots = [];

                // Recorre TODOS los checkboxes marcados, sin filtrar por profesor-id del botón
                // porque ahora el botón es global.
                $('.asesoria-checkbox:checked').each(function() {
                    const checkbox = $(this);
                    console.log('Checkbox marcado encontrado:', checkbox.data('horario-display'), 'Profesor ID:', checkbox.data('profesor-id'));

                    selectedSlots.push({
                        profesorId: checkbox.data('profesor-id'),
                        profesorName: checkbox.data('profesor-name'),
                        horarioData: checkbox.data('horario-value'),
                        horarioDisplay: checkbox.data('horario-display')
                    });
                });

                console.log('--- Contenido de selectedSlots antes de la validación ---');
                console.log(selectedSlots);
                console.log('Longitud de selectedSlots:', selectedSlots.length);

                if (selectedSlots.length === 0) {
                    alert('Por favor, selecciona al menos un horario para confirmar.');
                    return;
                }

                console.log('Enviando mensaje al padre:', {
                    type: 'asesoriaSelected',
                    slots: selectedSlots
                });

                if (window.parent) {
                    window.parent.postMessage({
                        type: 'asesoriaSelected',
                        slots: selectedSlots
                    }, window.location.origin);
                }
            });
        });
    </script>
</body>
</html>