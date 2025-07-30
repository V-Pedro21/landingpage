<?php session_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Éxito en tu Solicitud!</title>
    <link rel="stylesheet" href="css/landing_page.css"> <style>
        .success-container {
            padding: 50px;
            text-align: center;
            background-color: #e6ffe6; /* Un verde claro */
            border-radius: 10px;
            margin: 50px auto;
            max-width: 600px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .success-container h1 {
            color: #28a745; /* Verde de éxito */
        }
        .success-container p {
            font-size: 1.1em;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <h1>¡Solicitud Enviada con Éxito!</h1>
        <?php
        if (isset($_SESSION['success_message'])) {
            echo '<p>' . $_SESSION['success_message'] . '</p>';
            unset($_SESSION['success_message']);
        } else {
            echo '<p>Tu solicitud de asesoría ha sido procesada. Pronto nos pondremos en contacto contigo.</p>';
        }
        ?>
        <a href="alumno.php" class="btn btn-primary">Volver a la Página Principal</a>
        <a href="login_portal.php" class="btn btn-secondary">Iniciar Sesión</a>
    </div>
</body>
</html>