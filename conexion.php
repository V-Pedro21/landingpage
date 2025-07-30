<?php
// conexion.php
$host = "localhost"; // O la IP de tu servidor de base de datos
$usuario = "root";   // Tu usuario de MySQL
$contrasena = "";    // Tu contraseña de MySQL
$base_de_datos = "incubadora"; // ¡IMPORTANTE! Reemplaza con el nombre real de tu base de datos

$conexion = new mysqli($host, $usuario, $contrasena, $base_de_datos);

if ($conexion->connect_error) {
    die("Error de conexión a la base de datos: " . $conexion->connect_error);
}

// Opcional: Establecer el conjunto de caracteres a UTF-8
$conexion->set_charset("utf8");

?>