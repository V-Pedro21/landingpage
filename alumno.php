<?php
session_start(); // Iniciar la sesión para manejar mensajes de éxito/error
include 'conexion.php'; // Asegúrate de que este archivo existe y funciona correctamente

// Mensajes de sesión (mantener tu lógica existente)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Incubadora UNPRG: Asesorías para Emprendedores</title>
    <link rel="stylesheet" href="css/alumno_registro.css" />
    <link rel="stylesheet" href="css/landing_page.css" />
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/es.js"></script>

    <style>
        /* Estilos básicos para el modal */
        .modal {
            display: none; /* Oculto por defecto */
            position: fixed; /* Posición fija para cubrir toda la ventana */
            z-index: 1000; /* Siempre encima de otros elementos */
            left: 0;
            top: 0;
            width: 100%; /* Ancho completo */
            height: 100%; /* Alto completo */
            overflow: auto; /* Habilitar scroll si el contenido es muy largo */
            background-color: rgba(0,0,0,0.6); /* Fondo semitransparente */
            justify-content: center; /* Centrar contenido horizontalmente */
            align-items: center; /* Centrar contenido verticalmente */
            padding: 20px; /* Espacio para que el modal no toque los bordes */
        }

        .modal-content {
            background-color: #fefefe;
            /* margin: 5% auto; */ /* No usar margin auto con flexbox */
            padding: 20px;
            border: 1px solid #888;
            width: 95%; /* Ancho del modal, ajusta según necesidad */
            max-width: 900px; /* Ancho máximo para el modal */
            height: 90%; /* Alto del modal */
            max-height: 700px; /* Altura máxima */
            border-radius: 8px;
            position: relative;
            box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
            display: flex; /* Para controlar la altura del iframe */
            flex-direction: column;
        }

        .close-button {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            position: absolute; /* Posicionar el botón de cerrar */
            top: 10px;
            right: 15px;
            z-index: 1001; /* Asegurarse de que esté sobre el iframe */
        }

        .close-button:hover,
        .close-button:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }

        #modal-body-content {
            flex-grow: 1; /* Permite que el contenido del modal (iframe) ocupe el espacio disponible */
            display: flex; /* Asegura que el iframe se estire */
            overflow: hidden; /* Oculta el desbordamiento si el iframe es más grande */
        }

        #modal-body-content iframe {
            width: 100%;
            height: 100%; /* El iframe tomará la altura disponible del modal-body-content */
            border: none;
        }

        /* Estilo para el resumen de la selección */
        #selectionSummary {
            margin-top: 20px;
            padding: 15px;
            border: 1px dashed #007bff;
            border-radius: 5px;
            background-color: #e6f3ff;
            display: none; /* Inicialmente oculto */
        }
        #selectionSummary h4 {
            color: #0056b3;
            margin-top: 0;
            margin-bottom: 10px;
        }
        #selectionSummary p {
            margin-bottom: 5px;
            font-size: 0.95em;
        }
        #selectionSummary .small-text {
            font-size: 0.85em;
            color: #666;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header class="main-header">
        <div class="container header-content">
            <div class="logo">
                <img src="img/unprg.png" alt="Logo UNPRG"> 
                <img src="img/incubadoraUNPRG.jpg" alt="Logo Incubadora"> 
            </div>
            <nav class="main-nav">
                <a href="#beneficios">Beneficios</a>
                <a href="#proceso">Cómo Funciona</a>
                <a href="#formulario-registro">Regístrate</a>
                <a href="#contacto">Contacto</a>
            </nav>
        </div>
    </header>

    <section class="hero-section">
        <div class="hero-content">
            <h1>Impulsa Tu Idea con la Incubadora UNPRG</h1>
            <p class="subtitle">Conecta con mentores expertos y transforma tu emprendimiento.</p>
            <a href="#formulario-registro" class="btn btn-primary">¡Quiero mi Asesoría!</a>
        </div>
    </section>

    <?php
    if ($success_message) {
        echo '<div class="container" style="background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; margin-top: 20px;">' . $success_message . '</div>';
    }
    if ($error_message) {
        echo '<div class="container" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 10px; margin-bottom: 20px; border-radius: 5px; margin-top: 20px;">' . $error_message . '</div>';
    }
    ?>

    <section id="beneficios" class="benefits-section">
        <div class="container">
            <h2>¿Por Qué Elegirnos?</h2>
            <p>Accede a un ecosistema de apoyo para tu crecimiento.</p>
            <div class="benefits-grid">
                <div class="benefit-item">
                    <div class="icon"><i class="fas fa-handshake"></i></div>
                    <h3>Mentoría Especializada</h3>
                    <p>Conecta con profesionales que entienden tu sector.</p>
                </div>
                <div class="benefit-item">
                    <div class="icon"><i class="fas fa-lightbulb"></i></div>
                    <h3>Ideas Innovadoras</h3>
                    <p>Transforma tu concepto en un negocio viable.</p>
                </div>
                <div class="benefit-item">
                    <div class="icon"><i class="fas fa-network-wired"></i></div>
                    <h3>Red de Contactos</h3>
                    <p>Expande tu red con otros emprendedores y expertos.</p>
                </div>
                <div class="benefit-item">
                    <div class="icon"><i class="fas fa-chart-line"></i></div>
                    <h3>Crecimiento Sostenible</h3>
                    <p>Estrategias para un desarrollo a largo plazo.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="proceso" class="process-section">
        <div class="container">
            <h2>Nuestro Proceso</h2>
            <p>Sigue estos sencillos pasos para obtener tu asesoría.</p>
            <div class="process-steps">
                <div class="step">
                    <div class="step-icon">1</div>
                    <h3>Regístrate y Elige Temas</h3>
                    <p>Completa el formulario y selecciona las áreas de tu interés.</p>
                </div>
                <div class="step">
                    <div class="step-icon">2</div>
                    <h3>Selecciona a tu Mentor</h3>
                    <p>Te mostraremos los expertos adecuados para tus temas.</p>
                </div>
                <div class="step">
                    <div class="step-icon">3</div>
                    <h3>Coordina tu Asesoría</h3>
                    <p>Elige un horario disponible que se ajuste a ti.</p>
                </div>
                <div class="step">
                    <div class="step-icon">4</div>
                    <h3>¡Impulsa tu Proyecto!</h3>
                    <p>Recibe la guía necesaria y lleva tu idea al siguiente nivel.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="formulario-registro" class="form-section">
        <div class="container">
            <form action="registrar_alumno.php" method="POST">
                <h2>📝 Registro de Alumno y Solicitud de Asesoría</h2>

                <label for="nombre">Nombre Completo:</label>
                <input type="text" id="nombre" name="nombre" required />

                <label for="correo">Correo Electrónico UNPRG:</label>
                <input type="email" id="correo" name="correo" placeholder="ejemplo@unprg.edu.pe" required />

                <label for="celular">Número de Celular:</label>
                <input type="tel" id="celular" name="celular" pattern="[0-9]{9}" placeholder="987654321" required title="El número de celular debe tener 9 dígitos." />

                <label for="contrasena">Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required minlength="6" />

                <label for="idea">Breve Idea de Negocio o Proyecto:</label>
                <textarea id="idea" name="idea" rows="4" placeholder="Describe tu idea, ¿qué problema resuelve?, ¿quiénes son tus clientes? (Opcional)"></textarea>

                <h3>Solicitud de Asesoría</h3>
                <p>Cuéntanos en qué necesitas ayuda y encontraremos al experto ideal para ti.</p>

                <label for="temasSelect">Selecciona tus Temas de Interés:</label>
                <select id="temasSelect" name="temas_seleccionados[]" multiple="multiple" style="width: 100%;"></select>
                <p class="small-text">Puedes seleccionar uno o varios temas buscando por nombre.</p>
                
                <div id="selectionSummary" style="margin-top: 20px; padding: 15px; border: 1px dashed #007bff; border-radius: 5px; background-color: #e6f3ff; display: none;">
                    <h4>Asesorías Seleccionadas:</h4>
                    <div id="selectedAsesoriasList">
                        </div>
                    <p class="small-text">Si deseas cambiar, haz clic en el botón de abajo.</p>
                </div>

                <button type="button" id="openAsesoriasModalBtn" class="btn btn-secondary" style="margin-top: 15px;">
                    <i class="fas fa-search"></i> Buscar Profesor y Horario
                </button>

                <input type="hidden" id="selectedProfesorId" name="profesor_id" required>
                <input type="hidden" id="selectedHorarioData" name="horario_seleccionado" required>

                <label for="mensaje_alumno" style="margin-top: 20px;">Mensaje adicional (Opcional):</label>
                <textarea id="mensaje_alumno" name="mensaje_alumno" rows="4" placeholder="Ej: Me gustaría enfocarse en la validación de mercado y estrategias de pricing."></textarea>
                
                <br /><br />
                <button type="submit" id="submitButton" disabled>Registrar Alumno y Solicitar Asesoría</button>
                <div class="login-link">
                    <p>¿Ya tienes cuenta? <a href="login_portal.php">Iniciar Sesión</a></p>
                </div>
            </form>
        </div>
    </section>

    <section class="testimonials-section">
        <div class="container">
            <h2>Lo que Dicen Nuestros Emprendedores</h2>
            <div class="testimonial-item">
                <p>"Gracias a la incubadora, mi proyecto despegó. La mentoría fue clave."</p>
                <span>- Juan Pérez, Fundador de "Tech Solutions"</span>
            </div>
            </div>
    </section>

    <section id="contacto" class="main-footer">
        <div class="container footer-content">
            <div class="footer-info">
                <h3>Contacto</h3>
                <p><i class="fas fa-map-marker-alt"></i> Av. Juan XXIII N° 391, Lambayeque</p>
                <p><i class="fas fa-phone-alt"></i> +51 987 654 321</p>
                <p><i class="fas fa-envelope"></i> incubadora@unprg.edu.pe</p>
            </div>
            <div class="footer-links">
                <h3>Enlaces Útiles</h3>
                <p><a href="#beneficios">Nuestros Servicios</a></p>
                <p><a href="#">Política de Privacidad</a></p>
                <p><a href="#">Términos y Condiciones</a></p>
            <div class="footer-social">
                <h3>Síguenos</h3>
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-linkedin-in"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Incubadora UNPRG. Todos los derechos reservados.</p>
        </div>
    </section>

    <div id="asesoriasModal" class="modal">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div id="modal-body-content">
                <p>Cargando opciones de asesoría...</p>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Inicializar Select2 para la selección de temas
            $('#temasSelect').select2({
                placeholder: "Busca y selecciona uno o más temas",
                allowClear: true, // Permite deseleccionar
                language: "es", // Usar el idioma español
                width: 'resolve', // Ajusta el ancho para que Select2 use el 100% si el parent es 100%
                dropdownParent: $('#formulario-registro'), // Ayuda si el Select2 se corta en contenedores pequeños
                ajax: {
                    url: 'get_all_unique_topics.php', // Este es el script que devuelve todos los temas de la base de datos
                    dataType: 'json',
                    delay: 250,
                    data: function (params) { 
                        return { q: params.term }; 
                    },
                    processResults: function (data) { 
                        return { 
                            results: data.map(item => ({ 
                                id: item.id,
                                text: item.text // Asegúrate que 'tema' es el campo de texto del tema
                            })) 
                        }; 
                    },
                    cache: true
                }
            });

            const temasSelect = $('#temasSelect');
            const openAsesoriasModalBtn = $('#openAsesoriasModalBtn'); // Botón para abrir el modal
            const selectedProfesorIdInput = $('#selectedProfesorId');
            const selectedHorarioDataInput = $('#selectedHorarioData');
            const selectedProfesorNameSpan = $('#selectedProfesorName');
            const selectedHorarioDisplaySpan = $('#selectedHorarioDisplay');
            const selectionSummary = $('#selectionSummary');
            const submitButton = $('#submitButton');
            const form = $('form');
            const emailInput = $('#correo');

            const asesoriasModal = $('#asesoriasModal');
            const closeButton = $('.close-button');
            const modalBodyContent = $('#modal-body-content');

            const selectedAsesoriasList = $('#selectedAsesoriasList'); // Nueva referencia al contenedor de la lista

            // Deshabilitar botón de envío al inicio
            submitButton.prop('disabled', true);

            // Validación de dominio de correo al escribir
            emailInput.on('input', () => {
                if (emailInput.val().endsWith('@unprg.edu.pe')) {
                    emailInput[0].setCustomValidity(''); // Campo válido
                } else {
                    emailInput[0].setCustomValidity('Por favor, usa un correo con el dominio @unprg.edu.pe');
                }
            });

            // Evento para abrir el modal de búsqueda de asesorías
            openAsesoriasModalBtn.click(function() {
                const temasSeleccionados = temasSelect.val();
                if (!temasSeleccionados || temasSeleccionados.length === 0) {
                    alert("Por favor, selecciona al menos un tema de interés antes de buscar asesorías.");
                    temasSelect.select2('open');
                    return;
                }

                const url = `buscar_asesorias.php?temas_id=${temasSeleccionados.join(',')}`;
                
                modalBodyContent.html(`<iframe src="${url}"></iframe>`);
                asesoriasModal.css('display', 'flex'); // Usar 'flex' para centrar con justify/align-items
            });


            // Evento para cerrar el modal
            closeButton.click(function() {
                asesoriasModal.css('display', 'none');
                modalBodyContent.empty(); // Limpiar el contenido del iframe al cerrar
            });

            // Cerrar el modal haciendo clic fuera de él
            $(window).click(function(event) {
                if ($(event.target).is(asesoriasModal)) {
                    asesoriasModal.css('display', 'none');
                    modalBodyContent.empty();
                }
            });

            // Listener para recibir mensajes del iframe (buscar_asesorias.php)
            window.addEventListener('message', function(event) {
                // Comentamos la verificación de origen para depuración. Re-habilitar en producción con cuidado.
                // if (event.origin !== window.location.origin) {
                //     console.warn("Mensaje de origen desconocido bloqueado:", event.origin);
                //     return;
                // }

                console.log('Mensaje recibido en alumno.php (ORIGEN IGNORADO):', event.data);

                if (event.data.type === 'asesoriaSelected') {
                    const selectedSlots = event.data.slots;

                    // *** CRUCIAL: Verifica el contenido de selectedSlots ***
                    console.log('Contenido de selectedSlots:', selectedSlots); 
                     console.log('Número de slots recibidos:', selectedSlots ? selectedSlots.length : 0);

                    if (selectedSlots && selectedSlots.length > 0) {
                        // Limpia la lista anterior antes de añadir las nuevas
                        selectedAsesoriasList.empty(); 
                        let firstProfesorId = null; // Para guardar el ID del primer profesor

                        selectedSlots.forEach((slot, index) => {
                            // Si es el primer slot, guarda el ID del profesor
                            if (index === 0) {
                                firstProfesorId = slot.profesorId;
                            }
                            // Agrega cada slot como un párrafo o elemento de lista
                            selectedAsesoriasList.append(
                                `<p><strong>Profesor:</strong> ${slot.profesorName}<br>` +
                                `<strong>Horario:</strong> ${slot.horarioDisplay}</p>`
                            );
                        });

                        // Si necesitas enviar un solo profesor_id al backend, usa el del primer slot.
                        // Si tu backend procesa el 'horario_seleccionado' como un JSON de todos los slots, esto es suficiente.
                        $('#selectedProfesorId').val(firstProfesorId); // Asignar el ID del primer profesor
                        $('#selectedHorarioData').val(JSON.stringify(selectedSlots)); // Contiene todos los slots como JSON

                        // Mostrar el resumen y habilitar el botón de envío
                        selectionSummary.show();
                        submitButton.prop('disabled', false);
                        asesoriasModal.css('display', 'none');
                        modalBodyContent.empty();
                    } else {
                        console.warn("Mensaje de asesoriaSelected recibido pero sin slots válidos.");
                    }
                }
            });

            // Validación de formulario antes de enviar
            form.on('submit', (e) => {
                // Validación del dominio de correo
                if (!emailInput.val().endsWith('@unprg.edu.pe')) {
                    e.preventDefault();
                    alert('Por favor, usa un correo con el dominio @unprg.edu.pe');
                    emailInput.focus();
                    return;
                }

                // Validación de que se haya seleccionado al menos un tema
                const temasSeleccionados = temasSelect.val();
                if (!temasSeleccionados || temasSeleccionados.length === 0) {
                    e.preventDefault();
                    alert("Por favor, selecciona al menos un tema de asesoría.");
                    temasSelect.select2('open');
                    return;
                }

                // Validar que se haya seleccionado un profesor y horario desde la página de búsqueda (los campos ocultos)
                if (!selectedProfesorIdInput.val() || !selectedHorarioDataInput.val()) {
                    e.preventDefault();
                    alert("Por favor, busca y selecciona un profesor y horario de asesoría antes de registrarte.");
                    openAsesoriasModalBtn.focus(); // Enfocar el botón para guiar al usuario
                    return;
                }
            });
        });
    </script>
</body>
</html>