<?php
// Activar reporte de errores para depuración
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Conexión con la base de datos
$conexion = new mysqli("localhost", "root", "", "incubadora");

if ($conexion->connect_error) {
  die("Error de conexión: " . $conexion->connect_error);
}

// Recibir datos del formulario
$nombre     = $_POST["nombre"];
$correo     = $_POST["correo"];
$contrasena = $_POST["contrasena"];
$celular    = $_POST["celular"];
$idea       = $_POST["idea"];
$ayuda      = $_POST["ayuda"];
$profesor   = $_POST["profesor"];
$horario    = $_POST["horario"];

// Verificar si el correo ya está registrado
$verificar = $conexion->prepare("SELECT id FROM alumnos WHERE correo = ?");
$verificar->bind_param("s", $correo);
$verificar->execute();
$verificar->store_result();

if ($verificar->num_rows > 0) {
  echo "<script>alert('❌ Usted ya está registrado con ese correo.'); window.location.href='alumno.html';</script>";
} else {
  // Insertar nuevo alumno
  $sql = "INSERT INTO alumnos (nombre, correo, contrasena, celular, idea, ayuda, profesor, horario)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("ssssssss", $nombre, $correo, $contrasena, $celular, $idea, $ayuda, $profesor, $horario);

  if ($stmt->execute()) {
    echo "<script>alert('✅ Registro exitoso.'); window.location.href='alumno.html';</script>";
  } else {
    echo "<script>alert('❌ Error al registrar: " . $stmt->error . "'); window.location.href='alumno.html';</script>";
  }

  $stmt->close();
}

$verificar->close();
$conexion->close();
?>
