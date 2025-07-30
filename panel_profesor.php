<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo contiene tu conexión a la BD

// Redirigir si el profesor no ha iniciado sesión o no es de tipo profesor
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    header("Location: login_portal.php"); // Redirige al portal de login
    exit();
}

$profesor_id = $_SESSION["profesor_id"];

// Obtener datos principales del profesor (nombre y correo)
$nombre_profesor = "";
$correo_profesor = "";

$stmt_profesor = $conexion->prepare("SELECT nombre, correo FROM profesores WHERE id = ?");
if ($stmt_profesor === false) {
    error_log("Error al preparar la consulta de profesor en panel_profesor.php: " . $conexion->error);
    die("Error interno al cargar los datos del profesor. Por favor, inténtalo de nuevo.");
}
$stmt_profesor->bind_param("i", $profesor_id);
$stmt_profesor->execute();
$stmt_profesor->bind_result($nombre_profesor, $correo_profesor);
$stmt_profesor->fetch();
$stmt_profesor->close();

// Obtener los temas del profesor desde la tabla 'temas_profesores'
$temas = [];
$stmt_temas = $conexion->prepare("SELECT t.tema FROM temas_profesores tp JOIN temas t ON tp.tema_id = t.id WHERE tp.profesor_id = ?");
if ($stmt_temas === false) {
    error_log("Error al preparar la consulta de temas en panel_profesor.php: " . $conexion->error);
} else {
    $stmt_temas->bind_param("i", $profesor_id);
    $stmt_temas->execute();
    $result_temas = $stmt_temas->get_result();
    while ($fila = $result_temas->fetch_assoc()) {
        $temas[] = $fila['tema'];
    }
    $stmt_temas->close();
}

// Obtener horarios de disponibilidad recurrente
$horarios_recurrentes = [];
$stmt_recurrente = $conexion->prepare("SELECT id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez FROM disponibilidad_recurrente WHERE profesor_id = ? ORDER BY dia_semana ASC, hora_inicio ASC");
if ($stmt_recurrente === false) {
    error_log("Error al preparar la consulta de horarios recurrentes en panel_profesor.php: " . $conexion->error);
    // DEBUG: Puedes agregar un echo aquí para ver el error en pantalla si estás desarrollando
    // echo "<p style='color:red;'>ERROR: No se pudieron cargar los horarios recurrentes. Revisa los logs del servidor.</p>";
} else {
    $stmt_recurrente->bind_param("i", $profesor_id);
    $stmt_recurrente->execute();
    $result_recurrente = $stmt_recurrente->get_result();
    while ($fila = $result_recurrente->fetch_assoc()) {
        $horarios_recurrentes[] = $fila;
    }
    $stmt_recurrente->close();
}
// DEBUG: Descomenta estas líneas para verificar si el array se está llenando
// echo "<h3>DEBUG: Horarios Recurrentes Cargados:</h3><pre>"; print_r($horarios_recurrentes); echo "</pre>";

// Obtener horarios de disponibilidad puntual
$horarios_puntuales = [];
$stmt_puntual = $conexion->prepare("SELECT id, fecha, hora_inicio, hora_fin, tipo FROM disponibilidad_puntual WHERE profesor_id = ? ORDER BY fecha ASC, hora_inicio ASC");
if ($stmt_puntual === false) {
    error_log("Error al preparar la consulta de horarios puntuales en panel_profesor.php: " . $conexion->error);
    // DEBUG: Puedes agregar un echo aquí para ver el error en pantalla si estás desarrollando
    // echo "<p style='color:red;'>ERROR: No se pudieron cargar los horarios puntuales. Revisa los logs del servidor.</p>";
} else {
    $stmt_puntual->bind_param("i", $profesor_id);
    $stmt_puntual->execute();
    $result_puntual = $stmt_puntual->get_result();
    while ($fila = $result_puntual->fetch_assoc()) {
        $horarios_puntuales[] = $fila;
    }
    $stmt_puntual->close();
}
// DEBUG: Descomenta estas líneas para verificar si el array se está llenando
// echo "<h3>DEBUG: Horarios Puntuales Cargados:</h3><pre>"; print_r($horarios_puntuales); echo "</pre>";

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Profesor</title>
    <link rel="stylesheet" href="css/panel_profesor.css">
</head>
<body>
    <div class="container">
        <?php
        // Mostrar mensajes de éxito o error si existen en la sesión
        if (isset($_SESSION['success_message'])) {
            echo '<p style="color: green; text-align: center; font-weight: bold; border: 1px solid green; padding: 10px; border-radius: 5px; background-color: #e6ffe6;">' . htmlspecialchars($_SESSION['success_message']) . '</p>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<p style="color: red; text-align: center; font-weight: bold; border: 1px solid red; padding: 10px; border-radius: 5px; background-color: #ffe6e6;">' . htmlspecialchars($_SESSION['error_message']) . '</p>';
            unset($_SESSION['error_message']);
        }
        ?>

        <h1>Bienvenido, <?php echo htmlspecialchars($nombre_profesor); ?></h1>
        <p>Correo: <?php echo htmlspecialchars($correo_profesor); ?></p>

        <h2>Tus Temas de Asesoría:</h2>
        <div class="temas-lista">
            <?php
            if (!empty($temas)) {
                foreach ($temas as $tema) {
                    echo '<span>' . htmlspecialchars(trim($tema)) . '</span>';
                }
            } else {
                echo '<p>No has registrado temas aún.</p>';
            }
            ?>
        </div>
        
        <a href="profesor_add_horario_temas.php" class="button add-horario-btn">➕ Añadir Nuevos Horarios y Temas</a>
        <h2>Tu Disponibilidad Recurrente:</h2>
        <?php if (!empty($horarios_recurrentes)): ?>
            <ul class="horarios-list">
                <?php
                // Mapeo para mostrar el nombre del día en lugar del número
                $dias_semana_map = [
                    1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles',
                    4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'
                ];
                foreach ($horarios_recurrentes as $horario): ?>
                    <li class="horario-item">
                        <span>Día: <strong><?php echo htmlspecialchars($dias_semana_map[$horario['dia_semana']] ?? 'Desconocido'); ?></strong></span>
                        <span>Inicio: <?php echo htmlspecialchars($horario['hora_inicio']); ?></span>
                        <span>Fin: <?php echo htmlspecialchars($horario['hora_fin']); ?></span>
                        <?php if (!empty($horario['fecha_inicio_validez'])): ?>
                            <span>Válido desde: <?php echo htmlspecialchars($horario['fecha_inicio_validez']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($horario['fecha_fin_validez'])): ?>
                            <span>Válido hasta: <?php echo htmlspecialchars($horario['fecha_fin_validez']); ?></span>
                        <?php endif; ?>
                        <div class="horario-actions">
                            <a href="editar_horario.php?type=recurrente&id=<?php echo $horario['id']; ?>" class="button edit-btn">✏️ Editar</a>
                            <a href="eliminar_horario.php?type=recurrente&id=<?php echo $horario['id']; ?>" class="button delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este horario recurrente?');">🗑️ Eliminar</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No tienes horarios recurrentes registrados.</p>
        <?php endif; ?>

        <h2>Tu Disponibilidad Puntual:</h2>
        <?php if (!empty($horarios_puntuales)): ?>
            <ul class="horarios-list">
                <?php foreach ($horarios_puntuales as $horario): ?>
                    <li class="horario-item <?php echo ($horario['tipo'] === 'bloqueado' ? 'bloqueado' : ''); ?>">
                        <span>Fecha: <strong><?php echo htmlspecialchars($horario['fecha']); ?></strong></span>
                        <span>Inicio: <?php echo htmlspecialchars($horario['hora_inicio']); ?></span>
                        <span>Fin: <?php echo htmlspecialchars($horario['hora_fin']); ?></span>
                        <span>Tipo: <strong><?php echo htmlspecialchars($horario['tipo'] === 'disponible' ? 'Disponible' : 'Bloqueado'); ?></strong></span>
                        <div class="horario-actions">
                            <a href="editar_horario.php?type=puntual&id=<?php echo $horario['id']; ?>" class="button edit-btn">✏️ Editar</a>
                            <a href="eliminar_horario.php?type=puntual&id=<?php echo $horario['id']; ?>" class="button delete-btn" onclick="return confirm('¿Estás seguro de que quieres eliminar este horario puntual?');">🗑️ Eliminar</a>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>No tienes horarios puntuales registrados.</p>
        <?php endif; ?>

        <div class="botones">
            <a href="ver_solicitudes.php" class="button view-alumnos-btn">Ver Mis Solicitudes</a>
            <a href="logout.php" class="button logout-btn">Cerrar Sesión</a>
        </div>
        <div class="nav-button-group" style="margin-top: 20px;">
            <a href="incubadora.html" class="home-button">Ir a Página Principal</a>
        </div>
    </div>
</body>
</html>