<?php
session_start();
include 'conexion.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $correo = $_POST["correo_login"];
  $contrasena = $_POST["contrasena_login"];

  // Validar formato del correo institucional
  if (!preg_match('/^[a-zA-Z0-9._%+-]+@unprg\.edu\.pe$/', $correo)) {
    echo "<script>alert('❌ Solo se permiten correos institucionales (@unprg.edu.pe)'); window.location='profesor.html';</script>";
    exit();
  }

  // Buscar profesor en la base de datos
  $stmt = $conexion->prepare("SELECT id, contrasena FROM profesores WHERE correo = ?");
  $stmt->bind_param("s", $correo);
  $stmt->execute();
  $stmt->store_result();

  if ($stmt->num_rows > 0) {
    $stmt->bind_result($id, $contrasena_hash);
    $stmt->fetch();

    // Verificar contraseña
    if (password_verify($contrasena, $contrasena_hash)) {
      $_SESSION["profesor_id"] = $id;
      header("Location: panel_profesor.php");
      exit();
    } else {
      echo "<script>alert('❌ Contraseña incorrecta'); window.location='profesor.html';</script>";
    }
  } else {
    echo "<script>alert('❌ Profesor no encontrado'); window.location='profesor.html';</script>";
  }

  $stmt->close();
  $conexion->close();
}
?>

