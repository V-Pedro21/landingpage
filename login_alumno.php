<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo existe y funciona correctamente

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $correo = $_POST["correo_login"];
  $contrasena = $_POST["contrasena_login"];

  $stmt = $conexion->prepare("SELECT id, contrasena FROM alumnos WHERE correo = ?");
  if ($stmt === false) {
    // Error al preparar la consulta
    error_log("Error al preparar la consulta en login_alumno.php: " . $conexion->error);
    echo "<script>alert('❌ Error interno del servidor al intentar iniciar sesión.'); window.location='alumno.php';</script>";
    exit();
  }
  $stmt->bind_param("s", $correo);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $contrasena_hash);
    $stmt->fetch();

    if (password_verify($contrasena, $contrasena_hash)) {
      $_SESSION["alumno_id"] = $id;
      header("Location: panel_alumno.php"); // Redirige al panel del alumno
      exit();
    } else {
      echo "<script>alert('❌ Contraseña incorrecta'); window.location='alumno.php';</script>";
    }
  } else {
    echo "<script>alert('❌ Correo no registrado'); window.location='alumno.php';</script>";
  }

  $stmt->close();
  $conexion->close();
}
?>
