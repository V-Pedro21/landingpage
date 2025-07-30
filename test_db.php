<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'conexion.php'; // IMPORTANT: This path must match the one in get_profesor_data.php etc.

if ($conexion->connect_error) {
    echo "Error de conexión en test_db.php: " . $conexion->connect_error;
} else {
    echo "Conexión a la base de datos exitosa.";
    $conexion->close();
}
?>