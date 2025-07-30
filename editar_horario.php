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

$horario = null;
$error_message = '';
$success_message = '';

if ($horario_id === null || $horario_type === null) {
    $error_message = "Parámetros de horario inválidos.";
} else {
    if ($horario_type === 'recurrente') {
        $stmt = $conexion->prepare("SELECT id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez FROM disponibilidad_recurrente WHERE id = ? AND profesor_id = ?");
        if ($stmt === false) { $error_message = "Error al preparar consulta de recurrente: " . $conexion->error; }
        else {
            $stmt->bind_param("ii", $horario_id, $profesor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $horario = $result->fetch_assoc();
            $stmt->close();
            if (!$horario) { $error_message = "Horario recurrente no encontrado o no te pertenece."; }
        }
    } elseif ($horario_type === 'puntual') {
        $stmt = $conexion->prepare("SELECT id, fecha, hora_inicio, hora_fin, tipo FROM disponibilidad_puntual WHERE id = ? AND profesor_id = ?");
        if ($stmt === false) { $error_message = "Error al preparar consulta de puntual: " . $conexion->error; }
        else {
            $stmt->bind_param("ii", $horario_id, $profesor_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $horario = $result->fetch_assoc();
            $stmt->close();
            if (!$horario) { $error_message = "Horario puntual no encontrado o no te pertenece."; }
        }
    } else {
        $error_message = "Tipo de horario desconocido.";
    }
}

// Lógica para manejar el envío del formulario (UPDATE)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $horario_id && $horario_type) {
    if ($horario_type === 'recurrente') {
        $dia_semana = $_POST['dia_semana'] ?? null;
        $hora_inicio = $_POST['hora_inicio'] ?? null;
        $hora_fin = $_POST['hora_fin'] ?? null;
        $fecha_inicio_validez = empty($_POST['fecha_inicio_validez']) ? null : $_POST['fecha_inicio_validez'];
        $fecha_fin_validez = empty($_POST['fecha_fin_validez']) ? null : $_POST['fecha_fin_validez'];

        if ($dia_semana && $hora_inicio && $hora_fin) {
            $stmt_update = $conexion->prepare("UPDATE disponibilidad_recurrente SET dia_semana=?, hora_inicio=?, hora_fin=?, fecha_inicio_validez=?, fecha_fin_validez=? WHERE id=? AND profesor_id=?");
            if ($stmt_update === false) { $error_message = "Error al preparar actualización: " . $conexion->error; }
            else {
                $stmt_update->bind_param("issssii", $dia_semana, $hora_inicio, $hora_fin, $fecha_inicio_validez, $fecha_fin_validez, $horario_id, $profesor_id);
                if ($stmt_update->execute()) {
                    $success_message = "Horario recurrente actualizado con éxito.";
                    // Actualizar el array $horario para reflejar los cambios en el formulario
                    $horario['dia_semana'] = $dia_semana;
                    $horario['hora_inicio'] = $hora_inicio;
                    $horario['hora_fin'] = $hora_fin;
                    $horario['fecha_inicio_validez'] = $fecha_inicio_validez;
                    $horario['fecha_fin_validez'] = $fecha_fin_validez;
                } else {
                    $error_message = "Error al actualizar horario recurrente: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        } else {
            $error_message = "Todos los campos obligatorios deben ser completados.";
        }
    } elseif ($horario_type === 'puntual') {
        $fecha = $_POST['fecha'] ?? null;
        $hora_inicio = $_POST['hora_inicio'] ?? null;
        $hora_fin = $_POST['hora_fin'] ?? null;
        $tipo = $_POST['tipo'] ?? 'disponible'; // Podría ser 'disponible' o 'bloqueado'

        if ($fecha && $hora_inicio && $hora_fin) {
            $stmt_update = $conexion->prepare("UPDATE disponibilidad_puntual SET fecha=?, hora_inicio=?, hora_fin=?, tipo=? WHERE id=? AND profesor_id=?");
            if ($stmt_update === false) { $error_message = "Error al preparar actualización: " . $conexion->error; }
            else {
                $stmt_update->bind_param("ssssii", $fecha, $hora_inicio, $hora_fin, $tipo, $horario_id, $profesor_id);
                if ($stmt_update->execute()) {
                    $success_message = "Horario puntual actualizado con éxito.";
                    // Actualizar el array $horario
                    $horario['fecha'] = $fecha;
                    $horario['hora_inicio'] = $hora_inicio;
                    $horario['hora_fin'] = $hora_fin;
                    $horario['tipo'] = $tipo;
                } else {
                    $error_message = "Error al actualizar horario puntual: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        } else {
            $error_message = "Todos los campos obligatorios deben ser completados.";
        }
    }
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Horario</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/profesor_registro.css"> </head>
<body>
    <div class="container">
        <h1>Editar Horario</h1>
        <?php if ($error_message): ?>
            <p style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <p style="color: green;"><?php echo htmlspecialchars($success_message); ?></p>
        <?php endif; ?>

        <?php if ($horario): ?>
            <form action="editar_horario.php?type=<?php echo htmlspecialchars($horario_type); ?>&id=<?php echo htmlspecialchars($horario_id); ?>" method="POST">
                <?php if ($horario_type === 'recurrente'): ?>
                    <h3>Horario Recurrente</h3>
                    <label for="dia_semana">Día:</label>
                    <select id="dia_semana" name="dia_semana" required>
                        <option value="">Selecciona un día</option>
                        <option value="1" <?php echo ($horario['dia_semana'] == 1) ? 'selected' : ''; ?>>Lunes</option>
                        <option value="2" <?php echo ($horario['dia_semana'] == 2) ? 'selected' : ''; ?>>Martes</option>
                        <option value="3" <?php echo ($horario['dia_semana'] == 3) ? 'selected' : ''; ?>>Miércoles</option>
                        <option value="4" <?php echo ($horario['dia_semana'] == 4) ? 'selected' : ''; ?>>Jueves</option>
                        <option value="5" <?php echo ($horario['dia_semana'] == 5) ? 'selected' : ''; ?>>Viernes</option>
                        <option value="6" <?php echo ($horario['dia_semana'] == 6) ? 'selected' : ''; ?>>Sábado</option>
                        <option value="7" <?php echo ($horario['dia_semana'] == 7) ? 'selected' : ''; ?>>Domingo</option>
                    </select>

                    <label for="hora_inicio_recurrente">Inicio:</label>
                    <input type="time" id="hora_inicio_recurrente" name="hora_inicio" value="<?php echo htmlspecialchars($horario['hora_inicio']); ?>" required>

                    <label for="hora_fin_recurrente">Fin:</label>
                    <input type="time" id="hora_fin_recurrente" name="hora_fin" value="<?php echo htmlspecialchars($horario['hora_fin']); ?>" required>

                    <label for="fecha_inicio_validez">Desde (opc.):</label>
                    <input type="date" id="fecha_inicio_validez" name="fecha_inicio_validez" value="<?php echo htmlspecialchars($horario['fecha_inicio_validez'] ?? ''); ?>">

                    <label for="fecha_fin_validez">Hasta (opc.):</label>
                    <input type="date" id="fecha_fin_validez" name="fecha_fin_validez" value="<?php echo htmlspecialchars($horario['fecha_fin_validez'] ?? ''); ?>">

                <?php elseif ($horario_type === 'puntual'): ?>
                    <h3>Horario Puntual</h3>
                    <label for="fecha_puntual">Fecha:</label>
                    <input type="date" id="fecha_puntual" name="fecha" value="<?php echo htmlspecialchars($horario['fecha']); ?>" required>

                    <label for="hora_inicio_puntual">Inicio:</label>
                    <input type="time" id="hora_inicio_puntual" name="hora_inicio" value="<?php echo htmlspecialchars($horario['hora_inicio']); ?>" required>

                    <label for="hora_fin_puntual">Fin:</label>
                    <input type="time" id="hora_fin_puntual" name="hora_fin" value="<?php echo htmlspecialchars($horario['hora_fin']); ?>" required>

                    <label for="tipo_puntual">Tipo:</label>
                    <select id="tipo_puntual" name="tipo" required>
                        <option value="disponible" <?php echo ($horario['tipo'] === 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                        <option value="bloqueado" <?php echo ($horario['tipo'] === 'bloqueado') ? 'selected' : ''; ?>>Bloqueado</option>
                    </select>
                <?php endif; ?>

                <br><br>
                <button type="submit">Guardar Cambios</button>
                <a href="panel_profesor.php" class="button">Cancelar y Volver</a>
            </form>
        <?php endif; ?>
    </div>
    <script>
        // Función de autocompletado y validación de horas (puedes copiarla de profesor.html)
        function autoFillEndTimeAndValidate(inputInicio, inputFin) {
            if (inputInicio.value && !inputFin.value) {
                const [hours, minutes] = inputInicio.value.split(':').map(Number);
                const endDate = new Date();
                endDate.setHours(hours + 1);
                endDate.setMinutes(minutes);
                inputFin.value = `${String(endDate.getHours()).padStart(2, '0')}:${String(endDate.getMinutes()).padStart(2, '0')}`;
            }

            if (inputInicio.value && inputFin.value) {
                if (inputFin.value <= inputInicio.value) {
                    inputFin.setCustomValidity('La hora de fin debe ser posterior a la hora de inicio.');
                } else {
                    inputFin.setCustomValidity('');
                }
            } else {
                inputFin.setCustomValidity('');
            }
        }

        // Aplicar la validación a los campos de tiempo
        document.addEventListener('DOMContentLoaded', () => {
            const inicioRecurrente = document.getElementById('hora_inicio_recurrente');
            const finRecurrente = document.getElementById('hora_fin_recurrente');
            if (inicioRecurrente && finRecurrente) {
                inicioRecurrente.addEventListener('input', () => autoFillEndTimeAndValidate(inicioRecurrente, finRecurrente));
                finRecurrente.addEventListener('input', () => autoFillEndTimeAndValidate(inicioRecurrente, finRecurrente));
            }

            const inicioPuntual = document.getElementById('hora_inicio_puntual');
            const finPuntual = document.getElementById('hora_fin_puntual');
            if (inicioPuntual && finPuntual) {
                inicioPuntual.addEventListener('input', () => autoFillEndTimeAndValidate(inicioPuntual, finPuntual));
                finPuntual.addEventListener('input', () => autoFillEndTimeAndValidate(inicioPuntual, finPuntual));
            }

            // También puedes agregar la lógica de setMinDateForInput para fechas si lo consideras necesario
            const fechaPuntual = document.getElementById('fecha_puntual');
            if(fechaPuntual) {
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                fechaPuntual.min = `${year}-${month}-${day}`;
            }
        });
    </script>
</body>
</html>