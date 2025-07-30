<?php
session_start();
include 'conexion.php'; // Tu archivo de conexión a la BD

// Si ya hay una sesión activa, redirigir al panel correspondiente
if (isset($_SESSION["user_type"])) {
    if ($_SESSION["user_type"] === "profesor" && isset($_SESSION["profesor_id"])) {
        header("Location: panel_profesor.php");
        exit();
    } elseif ($_SESSION["user_type"] === "alumno" && isset($_SESSION["alumno_id"])) {
        header("Location: panel_alumno.php");
        exit();
    }
}

$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correo = $_POST["correo"];
    $contrasena_ingresada = $_POST["contrasena"]; // Contraseña ingresada por el usuario

    // --- Intentar login como Profesor ---
    // Asegúrate de que la columna 'contrasena' en tu tabla 'profesores' sea VARCHAR(255)
    $stmt_profesor = $conexion->prepare("SELECT id, nombre, contrasena FROM profesores WHERE correo = ?");
    if ($stmt_profesor === false) {
        error_log("Error al preparar consulta de profesor en login_portal: " . $conexion->error);
        $error_message = "Error interno del servidor.";
    } else {
        $stmt_profesor->bind_param("s", $correo);
        $stmt_profesor->execute();
        $stmt_profesor->store_result(); // Necesario para comprobar num_rows
        
        if ($stmt_profesor->num_rows == 1) {
            $stmt_profesor->bind_result($id, $nombre, $hashed_password);
            $stmt_profesor->fetch();
            if (password_verify($contrasena_ingresada, $hashed_password)) {
                // Contraseña correcta para profesor
                $_SESSION["profesor_id"] = $id;
                $_SESSION["user_name"] = $nombre;
                $_SESSION["user_type"] = "profesor"; // CLAVE: Establecer el tipo de usuario
                header("Location: panel_profesor.php");
                exit();
            }
        }
        $stmt_profesor->close();
    }

    // Si no es profesor, intentar login como Alumno
    if (empty($_SESSION["profesor_id"])) { // Solo si no se logueó como profesor
        // Asegúrate de que la columna 'contrasena' en tu tabla 'alumnos' sea VARCHAR(255)
        $stmt_alumno = $conexion->prepare("SELECT id, nombre, contrasena FROM alumnos WHERE correo = ?");
        if ($stmt_alumno === false) {
            error_log("Error al preparar consulta de alumno en login_portal: " . $conexion->error);
            $error_message = "Error interno del servidor.";
        } else {
            $stmt_alumno->bind_param("s", $correo);
            $stmt_alumno->execute();
            $stmt_alumno->store_result();
            
            if ($stmt_alumno->num_rows == 1) {
                $stmt_alumno->bind_result($id, $nombre, $hashed_password);
                $stmt_alumno->fetch();
                if (password_verify($contrasena_ingresada, $hashed_password)) {
                    // Contraseña correcta para alumno
                    $_SESSION["alumno_id"] = $id;
                    $_SESSION["user_name"] = $nombre;
                    $_SESSION["user_type"] = "alumno"; // CLAVE: Establecer el tipo de usuario
                    header("Location: panel_alumno.php");
                    exit();
                }
            }
            $stmt_alumno->close();
        }
    }

    // Si llegamos aquí, las credenciales no son correctas o hubo un error
    if (empty($error_message)) {
        $error_message = "Correo o contraseña incorrectos.";
    }
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="login-container">
        <h2>Iniciar Sesión</h2>
        <?php if ($error_message): ?>
            <p class="error-message"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form action="login_portal.php" method="post">
            <input type="email" name="correo" placeholder="Correo electrónico" required>
            <input type="password" name="contrasena" placeholder="Contraseña" required>
            <button type="submit">Acceder</button>
        </form>
        <div class="registro-link">
            <p>¿No tienes cuenta?</p>
            <p><a href="profesor.html">Registrarse como Profesor</a></p>
            <p><a href="alumno.php">Registrarse como Alumno</a></p>
        </div>
    </div>
</body>
</html>