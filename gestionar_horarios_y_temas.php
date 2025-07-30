<?php
// gestionar_horarios_y_temas.php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo establece $conexion correctamente

// Asume que el profesor_id se obtiene de la sesión o de alguna otra forma segura
// Por ejemplo, si el profesor ya inició sesión:
$profesor_id = $_SESSION['profesor_id'] ?? null; // Asegúrate de establecer esto en tu flujo de login

if (!$profesor_id) {
    // Redirigir si no hay ID de profesor (no autenticado o sesión expirada)
    header("Location: login.php"); // Cambia a tu página de login
    exit();
}

// Lógica para obtener el nombre del profesor para mostrar en el título
$nombre_profesor = "Profesor"; // Valor por defecto
$stmt_profesor = $conexion->prepare("SELECT nombre FROM profesores WHERE id = ?");
if ($stmt_profesor) {
    $stmt_profesor->bind_param("i", $profesor_id);
    $stmt_profesor->execute();
    $result_profesor = $stmt_profesor->get_result();
    if ($row = $result_profesor->fetch_assoc()) {
        $nombre_profesor = htmlspecialchars($row['nombre']);
    }
    $stmt_profesor->close();
}

$error_message = "";
$success_message = "";

// Lógica para manejar la adición/eliminación de temas o horarios si fuera necesario (no está en el alcance del problema actual)

// --- Obtener Áreas de Especialidad (Temas) del Profesor ---
$temas_profesor = [];
try {
    // Unir con la tabla `temas` para obtener el nombre del tema
    // CAMBIO CLAVE AQUÍ: Cambiar t.tema a t.nombre
    $stmt_temas = $conexion->prepare("SELECT t.id, t.tema AS temas FROM temas_profesores tp JOIN temas t ON tp.tema_id = t.id WHERE tp.profesor_id = ? ORDER BY t.tema");
    if ($stmt_temas === false) {
        throw new Exception("Error al preparar consulta de temas: " . $conexion->error);
    }
    $stmt_temas->bind_param("i", $profesor_id);
    $stmt_temas->execute();
    $result_temas = $stmt_temas->get_result();
    while ($row = $result_temas->fetch_assoc()) {
        $temas_profesor[] = $row;
    }
    $stmt_temas->close();
} catch (Exception $e) {
    $error_message = "Error al cargar temas: " . $e->getMessage();
    error_log("Error en gestionar_horarios_y_temas.php al cargar temas: " . $e->getMessage());
}

// --- Obtener Horarios Disponibles del Profesor ---
$horarios_profesor = [];
$dias_semana_nombres = [
    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
    5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
];

try {
    // Ejemplo para disponibilidad_recurrente
    // *********************************************************************************
    // CAMBIO CLAVE AQUÍ: Se añaden `fecha_inicio_validez` y `fecha_fin_validez`
    $stmt_recurrente = $conexion->prepare("SELECT id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez FROM disponibilidad_recurrente WHERE profesor_id = ? ORDER BY dia_semana, hora_inicio");
    // *********************************************************************************
    if ($stmt_recurrente) {
        $stmt_recurrente->bind_param("i", $profesor_id);
        $stmt_recurrente->execute();
        $result_recurrente = $stmt_recurrente->get_result();
        while ($row = $result_recurrente->fetch_assoc()) {
            $horarios_profesor[] = [
                'tipo' => 'Recurrente',
                'id' => $row['id'],
                'dia_semana' => $row['dia_semana'],
                'dia_nombre' => $dias_semana_nombres[$row['dia_semana']], // Añadido para fácil visualización
                'hora_inicio' => $row['hora_inicio'],
                'hora_fin' => $row['hora_fin'],
                // *********************************************************************************
                // CAMBIO CLAVE AQUÍ: Añadir las nuevas columnas de fecha de validez
                'fecha_inicio_validez' => $row['fecha_inicio_validez'],
                'fecha_fin_validez' => $row['fecha_fin_validez']
                // *********************************************************************************
            ];
        }
        $stmt_recurrente->close();
    }

    // Ejemplo para horarios_profesores (horarios puntuales)
    // *********************************************************************************
    // CAMBIO CLAVE AQUÍ: Se usa `horarios_profesores` y se remueve `tipo` ya que no existe en esa tabla
    $stmt_puntual = $conexion->prepare("SELECT id, fecha, hora_inicio, hora_fin FROM horarios_profesores WHERE profesor_id = ? ORDER BY fecha, hora_inicio");
    // *********************************************************************************
    if ($stmt_puntual) {
        $stmt_puntual->bind_param("i", $profesor_id);
        $stmt_puntual->execute();
        $result_puntual = $stmt_puntual->get_result();
        while ($row = $result_puntual->fetch_assoc()) {
            $horarios_profesor[] = [
                'tipo' => 'Puntual', // Tipo fijo 'Puntual' ya que no hay columna 'tipo'
                'id' => $row['id'],
                'fecha' => $row['fecha'],
                'hora_inicio' => $row['hora_inicio'],
                'hora_fin' => $row['hora_fin']
            ];
        }
        $stmt_puntual->close();
    }

} catch (Exception $e) {
    $error_message .= " Error al cargar horarios: " . $e->getMessage();
    error_log("Error en gestionar_horarios_y_temas.php al cargar horarios: " . $e->getMessage());
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Horarios y Temas - <?php echo $nombre_profesor; ?></title>
    <link rel="stylesheet" href="css/style.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Estilos básicos para la presentación si no tienes un CSS general */
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 800px; margin: 20px auto; }
        .error-message { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        .success-message { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; }
        h1, h2 { color: #333; }
        .button-group { margin-top: 20px; }
        .button-group button { background-color: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 5px; cursor: pointer; margin-right: 10px; }
        .button-group button:hover { background-color: #0056b3; }
        .list-item { background-color: #e9e9e9; padding: 8px; margin-bottom: 5px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .list-item .actions button { background: none; border: none; cursor: pointer; color: #dc3545; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-edit"></i> Gestionar Horarios y Temas - <?php echo $nombre_profesor; ?></h1>

        <?php if ($error_message): ?>
            <div class="error-message">
                <i class="fas fa-times-circle"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="section">
            <h2>Áreas de Especialidad (Temas):</h2>
            <?php if (empty($temas_profesor)): ?>
                <p>No tienes temas de especialidad asignados.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($temas_profesor as $tema): ?>
                        <li class="list-item">
                            <?php echo htmlspecialchars($tema['temas']); ?>
                            </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="button-group">
                <button type="button" onclick="window.location.href='editar_tema.php'">+ Editar Tema</button>
            </div>
        </div>

        <div class="section">
            <h2>Horarios Disponibles:</h2>
            <?php if (empty($horarios_profesor)): ?>
                <p>No tienes horarios disponibles configurados.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($horarios_profesor as $horario): ?>
                        <li class="list-item">
                            <?php 
                                echo htmlspecialchars($horario['tipo']) . ': ';
                                if ($horario['tipo'] === 'Recurrente') {
                                    echo htmlspecialchars($horario['dia_nombre']) . ' ';
                                    echo htmlspecialchars($horario['hora_inicio']) . ' - ' . htmlspecialchars($horario['hora_fin']);
                                    // *********************************************************************************
                                    // CAMBIO CLAVE AQUÍ: Mostrar las fechas de validez si existen
                                    if (!empty($horario['fecha_inicio_validez']) && $horario['fecha_inicio_validez'] !== '0000-00-00') {
                                        echo ' (Desde: ' . htmlspecialchars($horario['fecha_inicio_validez']);
                                        if (!empty($horario['fecha_fin_validez']) && $horario['fecha_fin_validez'] !== '0000-00-00') {
                                            echo ' Hasta: ' . htmlspecialchars($horario['fecha_fin_validez']);
                                        }
                                        echo ')';
                                    } elseif (!empty($horario['fecha_fin_validez']) && $horario['fecha_fin_validez'] !== '0000-00-00') {
                                         echo ' (Hasta: ' . htmlspecialchars($horario['fecha_fin_validez']) . ')';
                                    }
                                    // *********************************************************************************
                                } elseif ($horario['tipo'] === 'Puntual') {
                                    echo htmlspecialchars($horario['fecha']) . ' ';
                                    echo htmlspecialchars(substr($horario['hora_inicio'], 0, 5)) . ' - ' . htmlspecialchars(substr($horario['hora_fin'], 0, 5));
                                }
                            ?>
                            </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="button-group">
                <button type="button" onclick="window.location.href='añadir_horario.php'">+ Añadir Horario</button>
                <button type="button" onclick="window.location.href='editar_horario.php'"><i class="fas fa-edit"></i> Editar Horarios</button>
            </div>
        </div>

        <div class="button-group">
            <button type="button" onclick="window.history.back()"><i class="fas fa-arrow-left"></i> Volver al Panel</button>
        </div>
    </div>
</body>
</html>