<?php
session_start();
include 'conexion.php'; // Asegúrate de que este archivo contenga tu conexión a la BD

// Redirigir si el profesor no ha iniciado sesión
if (!isset($_SESSION["profesor_id"]) || $_SESSION["user_type"] !== "profesor") {
    header("Location: login_portal.php");
    exit();
}

$profesor_id = $_SESSION["profesor_id"];
$nombre_profesor = $_SESSION["user_name"] ?? 'Profesor'; // Obtener nombre de la sesión si está disponible

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Añadir Horarios y Temas - <?php echo htmlspecialchars($nombre_profesor); ?></title>
    <link rel="stylesheet" href="css/profesor_registro.css" /> <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>
    <style>
        /* Puedes añadir estilos específicos si quieres para diferenciarlo del registro inicial */
        body {
            background-color: #f0f8ff; /* Un azul muy claro para diferenciar */
        }
        .container {
            border: 2px solid #0056b3;
            box-shadow: 0px 6px 20px rgba(0,0,0,0.2);
        }
        h2 {
            color: #004085;
        }
    </style>
</head>
<body>
    <form action="registrar_profesor.php" method="POST">
        <h2>➕ Añadir Nuevos Horarios y Temas</h2>
        <p>Estás añadiendo información adicional para: <strong><?php echo htmlspecialchars($nombre_profesor); ?></strong></p>
        
        <input type="hidden" name="profesor_id_existente" value="<?php echo htmlspecialchars($profesor_id); ?>">

        <h3>Áreas de Especialidad (Temas):</h3>
        <select id="temasProfesorSelect" name="temas_experiencia[]" multiple="multiple" style="width: 100%;">
            </select>
        <p class="small-text">Selecciona temas existentes o escribe nuevos y presiona Enter para agregarlos.</p>

        <h3>Horarios de Disponibilidad (Avanzado):</h3>

        <h4>Disponibilidad Recurrente Semanal:</h4>
        <div id="horarios-recurrente-container">
        </div>
        <button type="button" onclick="agregarHorarioRecurrente()">➕ Añadir Horario Recurrente</button>
        <p class="small-text">Define patrones de horario que se repiten cada semana (ej. "Lunes 9:00-10:00").</p>

        <h4>Disponibilidad Puntual (Única Fecha):</h4>
        <div id="horarios-puntual-container">
        </div>
        <button type="button" onclick="agregarHorarioPuntual()">➕ Añadir Horario Puntual</button>
        <p class="small-text">Define bloques de horario para una fecha específica (ej. "25/07/2025 14:00-15:00").</p>
        
        <br /><br />
        <button type="submit">Guardar Horarios y Temas</button>
        <div class="nav-button-group" style="margin-top: 20px;">
            <a href="panel_profesor.php" class="button">Volver al Panel</a>
        </div>
    </form>

    <script>
        // Copia exacta de las funciones JS de profesor.html
        let recurrenteCount = 0;
        let puntualCount = 0;

        function agregarHorarioRecurrente() {
            recurrenteCount++;
            const container = document.getElementById("horarios-recurrente-container");
            const horarioDiv = document.createElement("div");
            horarioDiv.classList.add("horario-item", "recurrente-item");
            horarioDiv.innerHTML = `
                <label>Día:</label>
                <select name="dia_semana_recurrente[]" required>
                    <option value="">Selecciona un día</option>
                    <option value="1">Lunes</option>
                    <option value="2">Martes</option>
                    <option value="3">Miércoles</option>
                    <option value="4">Jueves</option>
                    <option value="5">Viernes</option>
                    <option value="6">Sábado</option>
                    <option value="7">Domingo</option>
                </select>
                <label>Inicio:</label>
                <input type="time" name="hora_inicio_recurrente[]" required>
                <label>Fin:</label>
                <input type="time" name="hora_fin_recurrente[]" required>
                <label>Desde (opc.):</label>
                <input type="date" name="fecha_inicio_recurrente[]">
                <label>Hasta (opc.):</label>
                <input type="date" name="fecha_fin_recurrente[]">
                <button type="button" onclick="this.parentNode.remove()">🗑️</button>
            `;
            container.appendChild(horarioDiv);
            const inicioInput = horarioDiv.querySelector('input[name="hora_inicio_recurrente[]"]');
            const finInput = horarioDiv.querySelector('input[name="hora_fin_recurrente[]"]');
            inicioInput.addEventListener('input', () => autoFillEndTimeAndValidate(inicioInput, finInput));
            finInput.addEventListener('input', () => autoFillEndTimeAndValidate(inicioInput, finInput));
        }

        function agregarHorarioPuntual() {
            puntualCount++;
            const container = document.getElementById("horarios-puntual-container");
            const horarioDiv = document.createElement("div");
            horarioDiv.classList.add("horario-item", "puntual-item");
            horarioDiv.innerHTML = `
                <label>Fecha:</label>
                <input type="date" name="fecha_puntual[]" required>
                <label>Inicio:</label>
                <input type="time" name="hora_inicio_puntual[]" required>
                <label>Fin:</label>
                <input type="time" name="hora_fin_puntual[]" required>
                <button type="button" onclick="this.parentNode.remove()">🗑️</button>
            `;
            container.appendChild(horarioDiv);
            const fechaInput = horarioDiv.querySelector('input[name="fecha_puntual[]"]');
            setMinDateForInput(fechaInput);
            const inicioInput = horarioDiv.querySelector('input[name="hora_inicio_puntual[]"]');
            const finInput = horarioDiv.querySelector('input[name="hora_fin_puntual[]"]');
            inicioInput.addEventListener('input', () => autoFillEndTimeAndValidate(inicioInput, finInput));
            finInput.addEventListener('input', () => autoFillEndTimeAndValidate(inicioInput, finInput));
        }

        function autoFillEndTimeAndValidate(inputInicio, inputFin) {
            if (inputInicio.value && !inputFin.value) {
                const [hours, minutes] = inputInicio.value.split(':').map(Number);
                const endDate = new Date();
                endDate.setHours(hours + 1);
                endDate.setMinutes(minutes);
                inputFin.value = `${String(endDate.getHours()).padStart(2, '0')}:${String(endDate.getMinutes()).padStart(2, '0')}`;
            }

            if (inputInicio.value && inputFin.value) {
                if (inputFin.value <= inputInicio.value) {
                    inputFin.setCustomValidity('La hora de fin debe ser posterior a la hora de inicio.');
                } else {
                    inputFin.setCustomValidity('');
                }
            } else {
                inputFin.setCustomValidity('');
            }
        }

        function setMinDateForInput(inputElement) {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            inputElement.min = `${year}-${month}-${day}`;
        }

        document.addEventListener("DOMContentLoaded", () => {
            // Inicializar Select2 para Temas de Experiencia (múltiples)
            $('#temasProfesorSelect').select2({
                placeholder: "Busca y selecciona uno o más temas",
                allowClear: true,
                tags: true, // Permite añadir nuevos temas
                tokenSeparators: [','], // Separar por coma
                language: "es", // Idioma español
                width: 'resolve',
                ajax: {
                    url: 'get_all_unique_topics.php', // Este archivo PHP debe devolver los temas
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(item => ({ id: item.id, text: item.text }))
                        };
                    },
                    cache: true
                }
            });

            document.querySelector('form').addEventListener('submit', (e) => {
                // Validación de al menos UN tema de experiencia (opcional si permites solo añadir horarios)
                // Si siempre debe haber al menos un tema, descomenta:
                /*
                if ($('#temasProfesorSelect').val().length === 0) {
                    e.preventDefault();
                    alert("Por favor, selecciona al menos un tema de especialidad o añade uno nuevo.");
                    $('#temasProfesorSelect').select2('open');
                    return;
                }
                */

                // Validación de al menos UN horario (recurrente o puntual)
                const selectedTopics = $('#temasProfesorSelect').val(); // Obtiene los IDs de los temas seleccionados
                const hasTopics = selectedTopics && selectedTopics.length > 0;
                const hasRecurrent = document.querySelectorAll('.recurrente-item').length > 0;
                const hasPuntual = document.querySelectorAll('.puntual-item').length > 0;

                if (!hasTopics && !hasRecurrent && !hasPuntual) {
                    e.preventDefault();
                    alert("Por favor, selecciona o añade al menos un tema, O añade al menos un horario de disponibilidad.");
                    return;
                }

                // Ahora, solo si hay horarios agregados, se validan los campos de cada horario.
                // Si no hay horarios agregados, no hay nada que validar en ellos.
                if (hasRecurrent || hasPuntual) {
                    let validHorarios = true;

                    document.querySelectorAll('.recurrente-item').forEach(item => {
                        const dia = item.querySelector('select[name="dia_semana_recurrente[]"]');
                        const inicio = item.querySelector('input[name="hora_inicio_recurrente[]"]');
                        const fin = item.querySelector('input[name="hora_fin_recurrente[]"]');
                        
                        if (!dia.value || !inicio.value || !fin.value) { 
                            validHorarios = false; 
                            // Opcional: añade una clase para resaltar el campo problemático
                            dia.reportValidity(); // Muestra el mensaje de validación del navegador
                            inicio.reportValidity();
                            fin.reportValidity();
                        }
                        autoFillEndTimeAndValidate(inicio, fin); 
                        if (!inicio.checkValidity() || !fin.checkValidity()) { 
                            validHorarios = false; 
                        }
                    });

                    document.querySelectorAll('.puntual-item').forEach(item => {
                        const fecha = item.querySelector('input[name="fecha_puntual[]"]');
                        const inicio = item.querySelector('input[name="hora_inicio_puntual[]"]');
                        const fin = item.querySelector('input[name="hora_fin_puntual[]"]');

                        if (!fecha.value || !inicio.value || !fin.value) { 
                            validHorarios = false; 
                            fecha.reportValidity();
                            inicio.reportValidity();
                            fin.reportValidity();
                        }
                        autoFillEndTimeAndValidate(inicio, fin); 
                        if (!inicio.checkValidity() || !fin.checkValidity()) { 
                            validHorarios = false; 
                        }
                    });

                    if (!validHorarios) {
                        e.preventDefault();
                        alert("Por favor, completa y corrige todos los campos de horario.");
                        return; // Detiene el envío si los horarios están incompletos/inválidos
                    }
                }
                
                // Si llegamos aquí, significa que:
                // 1. Hay temas seleccionados O hay horarios añadidos.
                // 2. Y si hay horarios añadidos, estos son válidos.
                // Por lo tanto, el formulario puede enviarse.
            });
        });
    </script>
</body>
</html>