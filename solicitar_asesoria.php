<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo existe y funciona correctamente

// Verificar si el usuario es un alumno y ha iniciado sesión
if (!isset($_SESSION["alumno_id"]) || $_SESSION["user_type"] !== "alumno") {
    header("Location: login_portal.php");
    exit();
}

$id_alumno = $_SESSION["alumno_id"];

// Obtener datos del alumno logueado para pre-llenar el formulario
$nombre_alumno = '';
$correo_alumno = '';
$celular_alumno = '';
$idea_alumno = '';

$stmt_alumno = $conexion->prepare("SELECT nombre, correo, celular, idea_negocio FROM alumnos WHERE id = ?");
if ($stmt_alumno) {
    $stmt_alumno->bind_param("i", $id_alumno);
    $stmt_alumno->execute();
    $stmt_alumno->bind_result($nombre_alumno, $correo_alumno, $celular_alumno, $idea_alumno);
    $stmt_alumno->fetch();
    $stmt_alumno->close();
} else {
    error_log("Error al preparar la consulta de datos del alumno en solicitar_asesoria.php: " . $conexion->error);
}

// Cierre de conexión aquí si no se va a usar más.
// Si tus scripts get_profesores_by_tema.php, get_available_slots.php, etc.
// manejan su propia conexión, entonces está bien.
// $conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Nueva Solicitud de Asesoría</title>
    <link rel="stylesheet" href="css/solicitar_asesoria.css" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

    <style>
        /* Aseguramos que el contenedor principal del formulario tenga position: relative */
        /* Esto es CRUCIAL para que los dropdowns de Select2 posicionados absolutamente */
        /* se calculen correctamente en relación con este contenedor. */
        .form-container, #solicitudForm { /* Apunta a ambos por si acaso */
            position: relative; 
            /* Importante: Si algún contenedor padre tuviera `overflow: hidden;`,
               eso también podría ocultar el dropdown. Asegúrate de que no sea el caso.
               Puedes probar añadiendo `overflow: visible !important;` aquí si lo necesitas,
               pero generalmente `position: relative` es suficiente. */
        }

        /* Opcional: Ajustes específicos para los dropdowns de Select2 */
        /* Estos estilos pueden ayudar a asegurar que el dropdown no se corte */
        /* si hay otros elementos con z-index bajo */
        .select2-container--open .select2-dropdown {
            z-index: 1050; /* Un z-index alto para que esté por encima de otros elementos */
        }
    </style>
</head>
<body>
    <div class="container form-container"> 
        <form action="procesar_nueva_solicitud.php" method="POST" id="solicitudForm">
            <h2>✨ Nueva Solicitud de Asesoría</h2>

            <?php if (isset($_SESSION['success_message'])): ?>
                <p class="alert alert-success">
                    <?= htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
                </p>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_message'])): ?>
                <p class="alert alert-danger">
                    <?= htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
                </p>
            <?php endif; ?>

            <label for="nombre">Nombre Completo:</label>
            <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre_alumno) ?>" required readonly />

            <label for="correo">Correo Electrónico UNPRG:</label>
            <input type="email" id="correo" name="correo" value="<?= htmlspecialchars($correo_alumno) ?>" placeholder="ejemplo@unprg.edu.pe" required pattern=".+@unprg\.edu\.pe$" title="Por favor, usa un correo con el dominio @unprg.edu.pe" readonly />

            <label for="celular">Número de Celular:</label>
            <input type="tel" id="celular" name="celular" value="<?= htmlspecialchars($celular_alumno) ?>" pattern="[0-9]{9}" placeholder="987654321" required title="El número de celular debe tener 9 dígitos." readonly />

            <label for="idea">Breve Idea de Negocio o Proyecto:</label>
            <textarea id="idea" name="idea" rows="4" placeholder="Describe tu idea, ¿qué problema resuelve?, ¿quiénes son tus clientes? (Opcional)" readonly><?= htmlspecialchars($idea_alumno) ?></textarea>

            <h3>Detalles de la Asesoría</h3>
            <p>Cuéntanos en qué necesitas ayuda y selecciona al experto ideal para ti.</p>

            <label for="temasSelect">Selecciona tus Temas de Interés:</label>
            <select class="form-control" id="temasSelect" name="temas_seleccionados[]" multiple="multiple" style="width: 100%;" required>
            </select>
            <p class="small-text">Puedes seleccionar uno o varios temas buscando por nombre.</p>
            
            <div id="profesorContainer" style="display: none;">
                <label for="profesorSelect">Profesores Disponibles (según tus temas):</label>
                <select class="form-control" id="profesorSelect" name="profesor_id" style="width: 100%;" required>
                    <option value="">-- Selecciona un Profesor --</option>
                </select>
            </div>

            <div id="horariosProfesorContainer" style="display: none;">
                <h4>Horarios Disponibles del Profesor:</h4>
                <select class="form-control" id="horarioSelect" name="horario_seleccionado" style="width: 100%;" required>
                    <option value="">-- Selecciona un Horario --</option>
                </select>
                <input type="hidden" id="selectedProfesorId" name="selected_profesor_id">
            </div>

            <label for="mensaje_alumno">Mensaje adicional (Opcional):</label>
            <textarea class="form-control" id="mensaje_alumno" name="mensaje_alumno" rows="4" placeholder="Ej: Me gustaría enfocarme en..."></textarea>

            <br /><br />
            <button type="submit">Enviar Nueva Solicitud de Asesoría</button>
            <div class="nav-button-group" style="margin-top: 20px;">
                <a href="panel_alumno.php" class="home-button">Volver al Panel</a>
            </div>
        </form>
    </div>

    <script>
        $(document).ready(function() {
            // Inicializar Select2 para la selección de temas
            $('#temasSelect').select2({
                placeholder: "Busca y selecciona uno o más temas",
                allowClear: true,
                language: "es",
                width: 'resolve', // Permite que Select2 resuelva su ancho automáticamente
                dropdownParent: $('#solicitudForm'), // Asegura que el dropdown sea hijo del formulario
                ajax: {
                    url: 'get_all_unique_topics.php',
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { 
                        return { 
                            q: params.term 
                        }; 
                    },
                    processResults: function (data) { 
                        return { 
                            results: data 
                        }; 
                    },
                    cache: true
                }
            });

            const temasSelect = $('#temasSelect');
            const profesorContainer = $('#profesorContainer');
            const profesorSelect = $('#profesorSelect');
            const horariosProfesorContainer = $('#horariosProfesorContainer');
            const horarioSelect = $('#horarioSelect');
            const form = $('#solicitudForm');
            const emailInput = $('#correo');
            const selectedProfesorIdInput = $('#selectedProfesorId'); // Nuevo input hidden

            // Inicializar Select2 para el profesor
            profesorSelect.select2({
                placeholder: "Selecciona un profesor",
                allowClear: true,
                language: "es",
                width: 'resolve',
                dropdownParent: $('#solicitudForm')
            });

            // Inicializar Select2 para los horarios disponibles
            horarioSelect.select2({
                placeholder: "Selecciona un horario",
                allowClear: true,
                language: "es",
                width: 'resolve',
                dropdownParent: $('#solicitudForm')
            });

            // Función para cargar profesores basada en los temas seleccionados
            async function cargarProfesoresPorTemas() {
                const temasSeleccionados = temasSelect.val();

                profesorSelect.html('<option value="">Cargando profesores...</option>').trigger('change');
                profesorContainer.hide();
                horariosProfesorContainer.hide();
                horarioSelect.html('<option value="">-- Selecciona un Horario --</option>').trigger('change');
                selectedProfesorIdInput.val(''); // Limpiar el profesor ID oculto

                if (!temasSeleccionados || temasSeleccionados.length === 0) {
                    profesorSelect.html('<option value="">-- Selecciona un Profesor --</option>').trigger('change');
                    return;
                }

                try {
                    const response = await fetch(`get_profesores_by_tema.php?${$.param({'temas_id': temasSeleccionados})}`);
                    const profesoresData = await response.json();

                    profesorSelect.empty();
                    profesorSelect.append('<option value="">-- Selecciona un Profesor --</option>');
                    if (profesoresData.length > 0) {
                        profesoresData.forEach(profesor => {
                            const option = $('<option></option>');
                            option.val(profesor.id);
                            option.text(profesor.text); // 'text' es la clave correcta para Select2
                            profesorSelect.append(option);
                        });
                        profesorContainer.show();
                    } else {
                        profesorSelect.append('<option value="">-- No hay profesores disponibles para estos temas --</option>');
                        profesorContainer.show();
                    }
                    profesorSelect.trigger('change');
                } catch (error) {
                    console.error('Error al cargar profesores:', error);
                    profesorSelect.empty().append('<option value="">Error al cargar profesores.</option>');
                    profesorContainer.show();
                    profesorSelect.trigger('change');
                    alert("Hubo un error al cargar los profesores. Por favor, inténtalo de nuevo.");
                }
            }

            // Función para cargar horarios del profesor seleccionado (usando el nuevo get_available_slots.php)
            async function cargarHorarios() {
                const profesorId = profesorSelect.val();
                selectedProfesorIdInput.val(profesorId); // Guardar el profesor ID en el input oculto
                horariosProfesorContainer.hide();
                horarioSelect.html('<option value="">Cargando horarios...</option>').trigger('change');

                if (!profesorId) {
                    horarioSelect.html('<option value="">-- Selecciona un Horario --</option>').trigger('change');
                    return;
                }

                try {
                    const response = await fetch(`get_available_slots.php?profesor_id=${profesorId}`); 
                    const horariosData = await response.json();

                    horarioSelect.empty();
                    horarioSelect.append('<option value="">-- Selecciona un Horario --</option>');
                    if (horariosData && horariosData.length > 0) {
                        $.each(horariosData, function(index, slot) {
                            // Codificamos el objeto slot completo en JSON para el 'value'
                            // Esto nos permite pasar toda la info relevante (fecha, hora inicio, hora fin, profesor ID)
                            // como un solo valor para que el backend lo decodifique fácilmente.
                            const slotValue = JSON.stringify({
                                profesor_id: profesorId, // Aseguramos que el profesor_id va en el slotValue
                                date: slot.date,
                                start_time: slot.start_time,
                                end_time: slot.end_time
                            });
                            const option = new Option(slot.display, slotValue, false, false);
                            horarioSelect.append(option);
                        });
                        horariosProfesorContainer.show();
                    } else {
                        horarioSelect.append('<option value="">-- No hay horarios disponibles para este profesor. --</option>');
                        horariosProfesorContainer.show();
                    }
                    horarioSelect.trigger('change');
                } catch (error) {
                    console.error('Error al cargar horarios:', error);
                    horarioSelect.empty().append('<option value="">Error al cargar horarios.</option>');
                    horariosProfesorContainer.show();
                    horarioSelect.trigger('change');
                    alert("Hubo un error al cargar los horarios disponibles. Por favor, inténtalo de nuevo.");
                }
            }

            // Event Listeners
            temasSelect.on('change', cargarProfesoresPorTemas);
            profesorSelect.on('change', cargarHorarios);

            // Validación de formulario antes de enviar
            form.on('submit', (e) => {
                if (!emailInput.val().endsWith('@unprg.edu.pe')) {
                    e.preventDefault();
                    alert('Por favor, usa un correo con el dominio @unprg.edu.pe');
                    emailInput.focus();
                    return;
                }

                const temasSeleccionados = temasSelect.val();
                if (!temasSeleccionados || temasSeleccionados.length === 0) {
                    e.preventDefault();
                    alert("Por favor, selecciona al menos un tema de asesoría.");
                    temasSelect.select2('open');
                    return;
                }

                if (!profesorSelect.val()) {
                    e.preventDefault();
                    alert("Por favor, selecciona un profesor.");
                    profesorSelect.select2('open');
                    return;
                }

                if (!horarioSelect.val()) {
                    e.preventDefault();
                    alert("Por favor, selecciona un horario disponible.");
                    horarioSelect.select2('open');
                    return;
                }

                // Aquí, el valor de horarioSelect.val() ya es el JSON string que necesitamos.
                // Aseguramos que el tema_id principal se envía.
                
                // Si solo quieres enviar el primer tema seleccionado como tema_id principal de la asesoría
                const primerTemaId = temasSeleccionados[0];
                const hiddenTemaInput = $('<input type="hidden" name="primer_tema_id">').val(primerTemaId);
                form.append(hiddenTemaInput);
            });
        });
    </script>
</body>
</html>