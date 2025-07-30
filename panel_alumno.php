<?php
session_start();
include 'conexion.php'; // Asegúrate de que tu conexión a la base de datos esté incluida

// Redirigir si el alumno no está logueado o no es de tipo alumno
if (!isset($_SESSION["alumno_id"]) || $_SESSION["user_type"] !== "alumno") {
    header("Location: login_portal.php"); // Redirige al login centralizado
    exit();
}

$id_alumno = $_SESSION["alumno_id"];

// Inicializar variables para los datos del alumno (estos solo se cargarán una vez)
$nombre = $_SESSION["user_name"] ?? 'N/A';
$correo = 'N/A';
$celular = 'N/A';
$idea = 'No especificada';

// Consulta para obtener los datos del alumno (solo una vez)
// Asegúrate de que la columna en tu tabla 'alumnos' es 'idea_negocio'
$stmt_alumno_data = $conexion->prepare("SELECT nombre, correo, celular, idea_negocio FROM alumnos WHERE id = ?");
if ($stmt_alumno_data === false) {
    error_log("Error al preparar la consulta de datos del alumno en panel_alumno.php: " . $conexion->error);
    die("Error interno al cargar tus datos principales. Por favor, inténtalo de nuevo más tarde.");
}
$stmt_alumno_data->bind_param("i", $id_alumno);
$stmt_alumno_data->execute();
$stmt_alumno_data->bind_result($db_nombre, $db_correo, $db_celular, $db_idea);
if ($stmt_alumno_data->fetch()) {
    $nombre = $db_nombre;
    $correo = $db_correo;
    $celular = $db_celular;
    $idea = $db_idea;
}
$stmt_alumno_data->close();


// Consulta para obtener TODAS las Solicitudes de Asesoría del alumno
$stmt_citas = $conexion->prepare("
    SELECT
        c.id AS cita_id,
        p.nombre AS profesor_nombre,
        c.fecha_cita AS fecha_asesoria,
        c.hora_inicio AS hora_inicio_asesoria,
        c.hora_fin AS hora_fin_asesoria,
        c.estado,
        T.tema AS nombre_tema,         -- CORREGIDO: Usamos T.tema
        c.comentarios_adicionales AS mensaje_alumno,
        c.fecha_creacion AS fecha_solicitud,
        c.mensaje_profesor
    FROM citas c
    LEFT JOIN profesores p ON c.profesor_id = p.id
    LEFT JOIN temas T ON c.tema_id = T.id   -- El JOIN es correcto
    WHERE c.alumno_id = ?
    ORDER BY c.fecha_creacion DESC
");

if ($stmt_citas === false) {
    error_log("Error al preparar la consulta de solicitudes de asesoría en panel_alumno.php: " . $conexion->error);
    die("Error interno al cargar tus solicitudes. Por favor, inténtalo de nuevo más tarde.");
}

$stmt_citas->bind_param("i", $id_alumno);
$stmt_citas->execute();
$result_citas = $stmt_citas->get_result();
$citas = $result_citas->fetch_all(MYSQLI_ASSOC);
$stmt_citas->close();

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel del Alumno</title>
    <link rel="stylesheet" href="css/panel_alumno.css">
    
</head>
<body>
    <div class="card">
        <h2>👨‍🎓 Panel del Alumno</h2>
        <?php if (isset($_GET['message'])): ?>
            <p class="alert alert-<?= htmlspecialchars($_GET['type'] ?? 'info') ?>">
                <?= htmlspecialchars($_GET['message']) ?>
            </p>
        <?php endif; ?>
        <p><strong>Nombre:</strong> <?= htmlspecialchars($nombre) ?></p>
        <p><strong>Correo:</strong> <?= htmlspecialchars($correo) ?></p>
        <p><strong>Celular:</strong> <?= htmlspecialchars($celular) ?></p>
        <p><strong>Idea de negocio:</strong> <?= htmlspecialchars($idea ?? 'No especificada') ?></p>
        
        <div class="button-container" style="text-align: center; margin-top: 25px;">
            <a href="solicitar_asesoria.php" class="new-request-button">
                📝 Realizar Nueva Solicitud de Asesoría
            </a>
        </div>

        <h3>Historial de Solicitudes de Asesoría:</h3>

        <?php if (empty($citas)): ?>
            <p class="no-citas">No has realizado ninguna solicitud de asesoría aún.</p>
        <?php else: ?>
            <?php foreach ($citas as $cita): ?>
                <div class="cita-card">
                    <?php
                        $fecha_solicitud_display = "N/A";
                        // La columna fecha_creacion puede ser NULL o '0000-00-00 00:00:00'
                        if (isset($cita['fecha_solicitud']) && $cita['fecha_solicitud'] !== null && $cita['fecha_solicitud'] !== "0000-00-00 00:00:00") {
                            $fecha_solicitud_display = htmlspecialchars(date('d/m/Y H:i', strtotime($cita['fecha_solicitud'])));
                        }
                    ?>
                    <h4>Solicitud del <?= $fecha_solicitud_display ?></h4>
                    <p><strong>Profesor:</strong> <?= htmlspecialchars($cita['profesor_nombre'] ?? 'No asignado') ?></p>
                    <?php
                        $horario_asesoria_display = "N/A";
                        // Usar las nuevas columnas fecha_cita, hora_inicio, hora_fin
                        if ($cita['fecha_asesoria'] && $cita['hora_inicio_asesoria'] && $cita['hora_fin_asesoria']) {
                            if (class_exists('IntlDateFormatter')) {
                                date_default_timezone_set('America/Lima'); // O tu zona horaria
                                $dateTime = new DateTime($cita['fecha_asesoria']);
                                $formatter = new IntlDateFormatter(
                                    'es_ES',
                                    IntlDateFormatter::FULL,
                                    IntlDateFormatter::NONE,
                                    'America/Lima',
                                    IntlDateFormatter::GREGORIAN,
                                    'EEEE, d \'de\' MMMM \'de\' yyyy'
                                );
                                $fecha_formateada = $formatter->format($dateTime);
                            } else {
                                $fecha_formateada = date("d/m/Y", strtotime($cita['fecha_asesoria']));
                            }
                            // Asegurarse de que las horas se formatean correctamente
                            $horario_asesoria_display = "Fecha: " . htmlspecialchars($fecha_formateada) . " | Hora: " . htmlspecialchars(substr($cita['hora_inicio_asesoria'], 0, 5)) . " - " . htmlspecialchars(substr($cita['hora_fin_asesoria'], 0, 5));
                        }
                    ?>
                    <p><strong>Horario Solicitado:</strong> <?= $horario_asesoria_display ?></p>
                    <p><strong>Tema Principal:</strong> <?= htmlspecialchars($cita['nombre_tema'] ?? 'No especificado') ?></p>
                    <p><strong>Comentarios adicionales:</strong> <?= htmlspecialchars($cita['mensaje_alumno'] ?? 'No hay comentarios.') ?></p>
                    
                    <?php if (isset($cita['estado'])): // Asegúrate de que el estado existe en el array ?>
                        <p><strong>Estado:</strong> <span class="estado-<?= htmlspecialchars($cita['estado']) ?>"><?= htmlspecialchars(ucfirst($cita['estado'])) ?></span></p>
                    <?php endif; ?>
                    
                    <?php if (isset($cita['mensaje_profesor']) && !empty($cita['mensaje_profesor'])): ?>
                        <p><strong>Mensaje del Profesor:</strong> <?= nl2br(htmlspecialchars($cita['mensaje_profesor'])) ?></p>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="logout">
            <a href="logout_alumno.php">🔓 Cerrar sesión</a> </div>
        <div class="nav-button-group" style="margin-top: 20px;">
            <a href="incubadora.html" class="home-button">Ir a Página Principal</a>
        </div>
    </div>
</body>
</html>