<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
include 'conexion.php'; // Asegúrate de que este archivo establece $conexion correctamente

if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determinar si es un nuevo registro o una actualización de un profesor existente
    $profesor_id = null;
    if (isset($_POST['profesor_id_existente']) && !empty($_POST['profesor_id_existente'])) {
        $profesor_id = (int)$_POST['profesor_id_existente'];
    } elseif (isset($_SESSION['profesor_id']) && $_SESSION["user_type"] === "profesor") {
        // En caso de que no se envíe el hidden input pero esté logueado
        $profesor_id = (int)$_SESSION['profesor_id'];
    }

    // Datos comunes para ambos flujos (añadir temas/horarios)
    $temas_experiencia = $_POST["temas_experiencia"] ?? []; 

    $dias_semana_recurrente = $_POST["dia_semana_recurrente"] ?? [];
    $horas_inicio_recurrente = $_POST["hora_inicio_recurrente"] ?? [];
    $horas_fin_recurrente = $_POST["hora_fin_recurrente"] ?? [];
    $fechas_inicio_recurrente = $_POST["fecha_inicio_recurrente"] ?? []; 
    $fechas_fin_recurrente = $_POST["fecha_fin_recurrente"] ?? [];     

    $fechas_puntual = $_POST["fecha_puntual"] ?? [];
    $horas_inicio_puntual = $_POST["hora_inicio_puntual"] ?? [];
    $horas_fin_puntual = $_POST["hora_fin_puntual"] ?? [];

    $conexion->begin_transaction();

    try {
        if ($profesor_id === null) {
            // Este es un NUEVO REGISTRO de profesor (viene de profesor.html)
            $nombre = $_POST["nombre"] ?? '';
            $correo = $_POST["correo"] ?? '';
            $contrasena_plana = $_POST["contrasena"] ?? '';
            $contrasena_hasheada = password_hash($contrasena_plana, PASSWORD_DEFAULT);

            // Validar si el correo @unprg.edu.pe
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !str_ends_with($correo, '@unprg.edu.pe')) {
                throw new Exception("Por favor, usa un correo con el dominio @unprg.edu.pe");
            }

            $stmt_profesor = $conexion->prepare("INSERT INTO profesores (nombre, correo, contrasena) VALUES (?, ?, ?)");
            if ($stmt_profesor === false) {
                throw new Exception("Error al preparar la consulta de profesor: " . $conexion->error);
            }
            $stmt_profesor->bind_param("sss", $nombre, $correo, $contrasena_hasheada);
            
            if (!$stmt_profesor->execute()) {
                if ($conexion->errno == 1062) { // Error de duplicado de correo
                    throw new Exception("El correo electrónico ya está registrado. Por favor, usa otro o inicia sesión.");
                } else {
                    throw new Exception("Error al registrar profesor: " . $stmt_profesor->error);
                }
            }
            $profesor_id = $stmt_profesor->insert_id;
            $stmt_profesor->close();

            // Mensaje para nuevo registro
            $_SESSION['success_message'] = "✅ Profesor registrado correctamente. Ahora puedes iniciar sesión.";
            $redirect_url = 'login_portal.php';

        } else {
            // Se están AÑADIENDO HORARIOS/TEMAS a un profesor existente (viene de profesor_add_horario_temas.php)
            // No es necesario procesar nombre, correo, contraseña de nuevo
            $_SESSION['success_message'] = "✅ Horarios y temas añadidos/actualizados correctamente.";
            $redirect_url = 'panel_profesor.php';
        }

        // 2. Procesar los Temas de Experiencia y asociarlos en `temas_profesores`
        // Esta lógica es la misma para ambos casos (nuevo registro o añadir más)
        if (!empty($temas_experiencia)) {
            $tema_ids_para_asociar = [];
            $stmt_find_topic = $conexion->prepare("SELECT id FROM temas WHERE tema = ?");
            $stmt_insert_topic = $conexion->prepare("INSERT INTO temas (tema) VALUES (?)");

            if ($stmt_find_topic === false || $stmt_insert_topic === false) {
                throw new Exception("Error al preparar consultas de temas (buscar/insertar): " . $conexion->error);
            }

            foreach ($temas_experiencia as $tema_valor) {
                $current_tema_id = null;
                $tema_limpio = trim($tema_valor);

                if (empty($tema_limpio)) {
                    continue;
                }

                // Si el valor recibido es un ID numérico (de un tema existente seleccionado)
                if (is_numeric($tema_limpio)) { 
                    $current_tema_id = (int)$tema_limpio;
                } else { // Si es un nuevo nombre de tema (escrito por el usuario)
                    $stmt_find_topic->bind_param("s", $tema_limpio);
                    $stmt_find_topic->execute();
                    $result_find = $stmt_find_topic->get_result();
                    
                    if ($row = $result_find->fetch_assoc()) {
                        $current_tema_id = $row['id']; // Tema ya existe
                    } else {
                        $stmt_insert_topic->bind_param("s", $tema_limpio);
                        if ($stmt_insert_topic->execute()) {
                            $current_tema_id = $conexion->insert_id; // Tema nuevo insertado
                        } else {
                            // En caso de duplicado (ej. dos usuarios intentando crear el mismo tema al mismo tiempo)
                            if ($conexion->errno == 1062) { 
                                // Reintentar buscarlo, ya debería existir
                                $stmt_find_topic->bind_param("s", $tema_limpio);
                                $stmt_find_topic->execute();
                                $result_recheck = $stmt_find_topic->get_result();
                                if ($row_recheck = $result_recheck->fetch_assoc()) {
                                    $current_tema_id = $row_recheck['id'];
                                } else {
                                    throw new Exception("Error crítico: Tema '$tema_limpio' no se pudo insertar y tampoco se encontró después de un error de duplicado.");
                                }
                            } else {
                                throw new Exception("Error al insertar nuevo tema '$tema_limpio': " . $stmt_insert_topic->error);
                            }
                        }
                    }
                }

                if ($current_tema_id !== null) {
                    $tema_ids_para_asociar[] = $current_tema_id;
                }
            }
            $stmt_find_topic->close();
            $stmt_insert_topic->close();

            // Si hay temas para asociar, insertar en la tabla pivote `temas_profesores`
            if (!empty($tema_ids_para_asociar)) {
                // Usamos ON DUPLICATE KEY UPDATE para evitar errores si la relación ya existe
                $stmt_asociacion = $conexion->prepare("INSERT INTO temas_profesores (profesor_id, tema_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE profesor_id=profesor_id");
                
                if ($stmt_asociacion === false) {
                    throw new Exception("Error al preparar la consulta de asociación de temas: " . $conexion->error);
                }

                foreach (array_unique($tema_ids_para_asociar) as $tema_id_asociar) {
                    $stmt_asociacion->bind_param("ii", $profesor_id, $tema_id_asociar);
                    if (!$stmt_asociacion->execute()) {
                        throw new Exception("Error al asociar profesor $profesor_id con tema $tema_id_asociar: " . $stmt_asociacion->error);
                    }
                }
                $stmt_asociacion->close();
            }
        }


        // 3. Procesar Horarios Recurrentes (la misma lógica)
        $stmt_recurrente = $conexion->prepare("INSERT INTO disponibilidad_recurrente (profesor_id, dia_semana, hora_inicio, hora_fin, fecha_inicio_validez, fecha_fin_validez) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt_recurrente === false) { throw new Exception("Error al preparar la consulta de horario recurrente: " . $conexion->error); }
        for ($i = 0; $i < count($dias_semana_recurrente); $i++) {
            $dia = $dias_semana_recurrente[$i] ?? null;
            $inicio = $horas_inicio_recurrente[$i] ?? null;
            $fin = $horas_fin_recurrente[$i] ?? null;
            $fecha_inicio_validez = empty($fechas_inicio_recurrente[$i]) ? null : $fechas_inicio_recurrente[$i];
            $fecha_fin_validez = empty($fechas_fin_recurrente[$i]) ? null : $fechas_fin_recurrente[$i];
            if (!empty($dia) && !empty($inicio) && !empty($fin)) {
                $stmt_recurrente->bind_param("iissss", $profesor_id, $dia, $inicio, $fin, $fecha_inicio_validez, $fecha_fin_validez);
                if (!$stmt_recurrente->execute()) { throw new Exception("Error al insertar horario recurrente para día $dia: " . $stmt_recurrente->error); }
            }
        }
        $stmt_recurrente->close();

        // 4. Procesar Horarios Puntuales (la misma lógica)
        $stmt_puntual = $conexion->prepare("INSERT INTO disponibilidad_puntual (profesor_id, fecha, hora_inicio, hora_fin, tipo) VALUES (?, ?, ?, ?, 'disponible')");
        if ($stmt_puntual === false) { throw new Exception("Error al preparar la consulta de horario puntual: " . $conexion->error); }
        for ($i = 0; $i < count($fechas_puntual); $i++) {
            $fecha = $fechas_puntual[$i] ?? null;
            $inicio = $horas_inicio_puntual[$i] ?? null;
            $fin = $horas_fin_puntual[$i] ?? null;
            if (!empty($fecha) && !empty($inicio) && !empty($fin)) {
                $stmt_puntual->bind_param("isss", $profesor_id, $fecha, $inicio, $fin);
                if (!$stmt_puntual->execute()) { throw new Exception("Error al insertar horario puntual para fecha $fecha: " . $stmt_puntual->error); }
            }
        }
        $stmt_puntual->close();

        // Si todo fue exitoso, confirmar la transacción
        $conexion->commit();
        echo "<script>window.location='$redirect_url';</script>";
        exit();

    } catch (Exception $e) {
        // Si algo falla, revertir la transacción
        $conexion->rollback();
        $_SESSION['error_message'] = "❌ Error al guardar la información: " . htmlspecialchars($e->getMessage()) . ".";
        error_log("Error en el registro/adición de profesor: " . $e->getMessage()); 
        // Redirige a la página de origen (profesor.html para nuevo, profesor_add_horario_temas.php para añadir)
        $return_url = ($profesor_id === null) ? 'profesor.html' : 'profesor_add_horario_temas.php';
        echo "<script>window.location='$return_url';</script>";
        exit();
    } finally {
        if (isset($conexion) && $conexion->ping()) { $conexion->close(); }
    }
} else {
    // Si se accede directamente por GET sin POST, redirige al panel o formulario de añadir
    header("Location: panel_profesor.php"); // O a profesor.html si no está logueado
    exit();
}
?>