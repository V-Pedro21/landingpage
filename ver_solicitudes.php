<?php
session_start();
include 'conexion.php'; // Tu archivo de conexión a la BD

// Redirigir si el profesor no ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    header("Location: login_portal.php");
    exit();
}

$profesor_id = $_SESSION["profesor_id"];
$nombre_profesor = $_SESSION["user_name"] ?? 'Profesor';

// Obtener las citas/asesorías para este profesor desde la tabla 'citas'
$citas = []; // Renombrado de $solicitudes a $citas para mayor claridad
try {
    $stmt = $conexion->prepare("
        SELECT 
            c.id AS cita_id,
            c.fecha_cita,
            c.hora_inicio,
            c.hora_fin,
            c.estado,
            c.comentarios_adicionales, -- ¡Añadido para mostrar el mensaje del alumno!
            a.nombre AS alumno_nombre,
            a.correo AS alumno_correo,
            t.tema AS tema_asociado
        FROM citas c
        JOIN alumnos a ON c.alumno_id = a.id
        LEFT JOIN temas t ON c.tema_id = t.id -- Unir con temas para obtener el nombre del tema
        WHERE c.profesor_id = ?
        ORDER BY c.fecha_cita DESC, c.hora_inicio ASC
    ");

    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta de citas: " . $conexion->error);
    }
    $stmt->bind_param("i", $profesor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $citas[] = $row; // Almacenamos en $citas
    }
    $stmt->close();
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error al cargar las citas: " . $e->getMessage();
    error_log("Error en ver_solicitudes.php: " . $e->getMessage());
}

$conexion->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas de Asesoría - Profesor</title>
    <link rel="stylesheet" href="css/base.css">
    <link rel="stylesheet" href="css/panel_profesor.css">
    <style>
        .container {
            max-width: 900px;
            text-align: left;
        }
        h1, h2 {
            text-align: center;
        }
        .citas-list { /* Renombrado de solicitudes-list a citas-list */
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }
        .cita-item { /* Renombrado de solicitud-item a cita-item */
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 12px;
            display: flex;
            flex-direction: column;
        }
        .cita-info { /* Renombrado de solicitud-info a cita-info */
            flex-grow: 1;
        }
        .cita-info p {
            margin: 5px 0;
        }
        .cita-actions { /* Renombrado de solicitud-actions a cita-actions */
            margin-top: 10px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }
        .cita-actions button {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .confirm-btn {
            background-color: #28a745; /* Verde */
            color: white;
        }
        .confirm-btn:hover {
            background-color: #218838;
        }
        .cancel-btn {
            background-color: #dc3545; /* Rojo */
            color: white;
        }
        .cancel-btn:hover {
            background-color: #c82333;
        }
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 5px;
            font-weight: bold;
            margin-left: 10px;
        }
        /* Clases CSS ajustadas para los valores del ENUM */
        .status-Programada { background-color: #ffc107; color: #333; } /* Amarillo para pendiente/programada */
        .status-Completada { background-color: #28a745; color: white; } /* Verde para completada */
        .status-Cancelada { background-color: #6c757d; color: white; } /* Gris para cancelada */
        .status-Reprogramada { background-color: #17a2b8; color: white; } /* Azul claro para reprogramada */

        /* Estilo para mensajes de éxito/error */
        .message-box {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<div class="message-box message-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
            unset($_SESSION['success_message']);
        }
        if (isset($_SESSION['error_message'])) {
            echo '<div class="message-box message-error">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
            unset($_SESSION['error_message']);
        }
        ?>

        <h1>Citas de Asesoría de <?php echo htmlspecialchars($nombre_profesor); ?></h1>

        <div class="citas-list">
            <?php if (!empty($citas)): ?>
                <?php foreach ($citas as $cita): ?>
                    <div class="cita-item">
                        <div class="cita-info">
                            <p><strong>Alumno:</strong> <?php echo htmlspecialchars($cita['alumno_nombre']); ?> (<?php echo htmlspecialchars($cita['alumno_correo']); ?>)</p>
                            <p><strong>Fecha:</strong> <?php echo htmlspecialchars($cita['fecha_cita']); ?></p>
                            <p><strong>Hora:</strong> <?php echo htmlspecialchars($cita['hora_inicio']) . ' - ' . htmlspecialchars($cita['hora_fin']); ?></p>
                            
                            <?php if ($cita['tema_asociado']): ?>
                                <p><strong>Tema:</strong> <?php echo htmlspecialchars($cita['tema_asociado']); ?></p>
                            <?php endif; ?>
                            
                            <?php if (!empty($cita['comentarios_adicionales'])): ?>
                                <p><strong>Comentarios adicionales:</strong> <?php echo nl2br(htmlspecialchars($cita['comentarios_adicionales'])); ?></p>
                            <?php endif; ?>

                            <p><strong>Estado:</strong> 
                                <span class="status-badge status-<?php echo htmlspecialchars($cita['estado']); ?>">
                                    <?php echo htmlspecialchars(ucfirst($cita['estado'])); ?>
                                </span>
                            </p>
                        </div>
                        <div class="cita-actions">
                            <?php if ($cita['estado'] === 'Programada'): ?>
                                <button class="confirm-btn" onclick="cambiarEstadoAsesoria(<?php echo $cita['cita_id']; ?>, 'Completada')">Marcar como Completada</button>
                                <button class="cancel-btn" onclick="cambiarEstadoAsesoria(<?php echo $cita['cita_id']; ?>, 'Cancelada')">Cancelar</button>
                                <?php elseif ($cita['estado'] === 'Completada'): ?>
                                <button class="status-info-btn" disabled>Completada</button>
                            <?php elseif ($cita['estado'] === 'Cancelada'): ?>
                                <button class="status-info-btn" disabled>Cancelada</button>
                            <?php elseif ($cita['estado'] === 'Reprogramada'): ?>
                                <button class="status-info-btn" disabled>Reprogramada</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No tienes citas de asesoría programadas.</p>
            <?php endif; ?>
        </div>

        <div class="nav-button-group" style="margin-top: 30px; text-align: center;">
            <a href="panel_profesor.php" class="button">Volver al Panel</a>
            <a href="logout.php" class="button logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script>
        function cambiarEstadoAsesoria(citaId, nuevoEstado) { // Renombrado de reservaId a citaId
            let mensajeConfirmacion;
            if (nuevoEstado === 'Completada') {
                mensajeConfirmacion = '¿Estás seguro de que quieres marcar esta asesoría como COMPLETA?';
            } else if (nuevoEstado === 'Cancelada') {
                mensajeConfirmacion = '¿Estás seguro de que quieres CANCELAR esta asesoría?';
            } else {
                mensajeConfirmacion = `¿Estás seguro de que quieres cambiar el estado de esta asesoría a "${nuevoEstado}"?`;
            }

            if (confirm(mensajeConfirmacion)) {
                $.ajax({
                    url: 'actualizar_estado_cita.php', // Renombrado a actualizar_estado_cita.php
                    type: 'POST',
                    data: {
                        cita_id: citaId, // Renombrado de reserva_id a cita_id
                        estado: nuevoEstado
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            alert(response.message);
                            location.reload(); // Recargar la página para ver el cambio
                        } else {
                            alert('Error al actualizar: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error de comunicación con el servidor.');
                        console.error("AJAX error:", status, error, xhr.responseText);
                    }
                });
            }
        }
    </script>
</body>
</html>