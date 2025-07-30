<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo establece $conexion correctamente

// Verificar si el usuario es un profesor y ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    header("Location: login_portal.php");
    exit();
}

$profesor_id = $_SESSION["profesor_id"];
$tema_link_id = $_GET['id'] ?? null; // Obtener el ID del ENLACE (de temas_profesores) de la URL

$tema_actual_data = null; // Almacenará los datos del tema actual (incluyendo nombre y su tema_id real)
$mensaje = '';
$tipo_mensaje = ''; // 'success' o 'error'

// Si no hay ID de enlace de tema, redirigir de vuelta
if (!$tema_link_id) {
    header("Location: gestionar_horarios_y_temas.php");
    exit();
}

// --- Lógica para obtener el tema a editar ---
try {
    // CAMBIO CLAVE AQUÍ: Unir con la tabla `temas` para obtener el nombre real del tema
    $stmt = $conexion->prepare("SELECT tp.profesor_id, tp.tema_id, t.nombre_tema FROM temas_profesores tp JOIN temas t ON tp.tema_id = t.id WHERE tp.id = ?");
    if ($stmt === false) {
        throw new Exception("Error al preparar la consulta para obtener tema: " . $conexion->error);
    }
    $stmt->bind_param("i", $tema_link_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $tema_actual_data = $result->fetch_assoc();
    $stmt->close();

    // Verificar si el tema existe y pertenece a este profesor
    if (!$tema_actual_data || $tema_actual_data['profesor_id'] !== $profesor_id) {
        // Redirigir si no existe el enlace o no pertenece a este profesor
        header("Location: gestionar_horarios_y_temas.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Error al obtener tema para edición en editar_tema.php: " . $e->getMessage());
    $mensaje = "Error al cargar el tema para edición: " . $e->getMessage();
    $tipo_mensaje = 'error';
}

// --- Lógica para procesar la actualización del tema ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_tema') {
    $nuevo_tema_texto = $_POST['tema'] ?? '';
    $tema_link_id_form = $_POST['tema_id'] ?? null; // Este es el ID del enlace de temas_profesores

    if (!empty($nuevo_tema_texto) && $tema_link_id_form == $tema_link_id) {
        try {
            // Re-obtener el tema_id real de la tabla `temas` usando el ID del enlace de temas_profesores
            $stmt_get_real_tema_id = $conexion->prepare("SELECT tema_id FROM temas_profesores WHERE id = ? AND profesor_id = ?");
            if ($stmt_get_real_tema_id === false) {
                throw new Exception("Error al preparar consulta para obtener tema_id real: " . $conexion->error);
            }
            $stmt_get_real_tema_id->bind_param("ii", $tema_link_id, $profesor_id);
            $stmt_get_real_tema_id->execute();
            $result_real_tema_id = $stmt_get_real_tema_id->get_result();
            $row_real_tema_id = $result_real_tema_id->fetch_assoc();
            $stmt_get_real_tema_id->close();

            if (!$row_real_tema_id) {
                throw new Exception("Enlace de tema no encontrado o no autorizado para la actualización.");
            }
            $actual_tema_id_from_temas_table = $row_real_tema_id['tema_id']; // Este es el ID del tema en la tabla `temas`

            // Verificar si el nuevo nombre de tema ya existe globalmente (excluyendo el tema actual por su ID en la tabla `temas`)
            $check_stmt = $conexion->prepare("SELECT COUNT(*) FROM temas WHERE nombre_tema = ? AND id != ?");
            if ($check_stmt === false) {
                throw new Exception("Error al preparar la consulta de verificación de unicidad: " . $conexion->error);
            }
            $check_stmt->bind_param("si", $nuevo_tema_texto, $actual_tema_id_from_temas_table);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $row = $check_result->fetch_row();
            $check_stmt->close();

            if ($row[0] > 0) {
                $mensaje = "El tema '" . htmlspecialchars($nuevo_tema_texto) . "' ya existe globalmente. Por favor, elige un nombre diferente.";
                $tipo_mensaje = 'error';
            } else {
                // CAMBIO CLAVE AQUÍ: Actualizar el `nombre_tema` en la tabla `temas`
                $stmt_update_tema = $conexion->prepare("UPDATE temas SET nombre_tema = ? WHERE id = ?");
                if ($stmt_update_tema === false) {
                    throw new Exception("Error al preparar la consulta de actualización del nombre del tema: " . $conexion->error);
                }
                $stmt_update_tema->bind_param("si", $nuevo_tema_texto, $actual_tema_id_from_temas_table);
                if ($stmt_update_tema->execute()) {
                    // Redirigir con mensaje de éxito
                    header("Location: gestionar_horarios_y_temas.php?message=" . urlencode("Tema actualizado correctamente.") . "&type=success");
                    exit();
                } else {
                    $mensaje = "Error al actualizar el tema: " . $stmt_update_tema->error;
                    $tipo_mensaje = 'error';
                }
                $stmt_update_tema->close();
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e->getMessage();
            $tipo_mensaje = 'error';
            error_log("Error al actualizar tema en editar_tema.php: " . $e->getMessage());
        }
    } else {
        $mensaje = "El campo de tema no puede estar vacío o ID de enlace no coincide.";
        $tipo_mensaje = 'error';
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Editar Tema</title>
    <link rel="stylesheet" href="css/base.css" />
    <link rel="stylesheet" href="css/gestionar.css" />
    <style>
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="card">
        <h2>✏️ Editar Tema de Asesoría</h2>

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>"><?php echo htmlspecialchars($mensaje); ?></div>
        <?php endif; ?>

        <?php if ($tema_actual_data): ?>
            <form action="editar_tema.php?id=<?php echo htmlspecialchars($tema_link_id); ?>" method="POST" class="form-section">
                <input type="hidden" name="action" value="update_tema">
                <input type="hidden" name="tema_id" value="<?php echo htmlspecialchars($tema_link_id); ?>"> <label for="tema">Tema de Asesoría:</label>
                <input type="text" id="tema" name="tema" value="<?php echo htmlspecialchars($tema_actual_data['nombre_tema']); ?>" required>
                
                <button type="submit" class="button-add">Guardar Cambios</button>
                <div class="back-link" style="margin-top: 15px;">
                    <a href="gestionar_horarios_y_temas.php" class="button-back">Cancelar y Volver</a>
                </div>
            </form>
        <?php else: ?>
            <p>No se encontró el tema o no tienes permisos para editarlo.</p>
            <div class="back-link">
                <a href="gestionar_horarios_y_temas.php" class="button-back">Volver a Gestionar</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>