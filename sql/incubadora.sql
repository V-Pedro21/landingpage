-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 30-07-2025 a las 16:28:37
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `incubadora`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `alumnos`
--

CREATE TABLE `alumnos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `celular` varchar(20) DEFAULT NULL,
  `idea_negocio` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `alumnos`
--

INSERT INTO `alumnos` (`id`, `nombre`, `correo`, `contrasena`, `celular`, `idea_negocio`, `fecha_registro`) VALUES
(5, 'pedro velasquez', 'pvelasquez@unprg.edu.pe', '$2y$10$8uO5ueFCUM2Yzan9cK1Sju92eiD9xb11Z0SuwS4dhrYZ45xphOdpK', '931027076', 'app para construccion', '2025-07-20 06:38:43'),
(6, 'Joel Sandoval', 'jsandoval@unprg.edu.pe', '$2y$10$dgUzy2gDagKCMcd/rsd/7.IiNYOuAj2SeMYwtcCYgcVHg5oyPJaZu', '987654321', 'hacer planos ', '2025-07-20 07:00:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asesorias_reservadas`
--

CREATE TABLE `asesorias_reservadas` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `horario_tipo` enum('recurrente','puntual') NOT NULL,
  `horario_id` int(11) NOT NULL,
  `fecha_reserva` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('pendiente','confirmada','cancelada') DEFAULT 'pendiente',
  `fecha_solicitud` datetime DEFAULT current_timestamp(),
  `tema_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `citas`
--

CREATE TABLE `citas` (
  `id` int(11) NOT NULL,
  `fecha_cita` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL,
  `estado` enum('Programada','Completada','Cancelada','Reprogramada') NOT NULL DEFAULT 'Programada',
  `notas_cita` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  `comentarios_adicionales` text DEFAULT NULL,
  `mensaje_profesor` text DEFAULT NULL,
  `fecha_respuesta` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `citas`
--

INSERT INTO `citas` (`id`, `fecha_cita`, `hora_inicio`, `hora_fin`, `profesor_id`, `alumno_id`, `tema_id`, `estado`, `notas_cita`, `fecha_creacion`, `comentarios_adicionales`, `mensaje_profesor`, `fecha_respuesta`) VALUES
(5, '2025-07-25', '11:02:00', '11:32:00', 6, 5, 1, '', NULL, '2025-07-20 06:38:43', NULL, NULL, NULL),
(6, '2025-07-25', '11:32:00', '12:02:00', 6, 5, 1, '', NULL, '2025-07-20 06:38:43', NULL, NULL, NULL),
(7, '2025-07-22', '20:32:00', '21:02:00', 5, 5, 1, '', NULL, '2025-07-20 06:38:43', NULL, NULL, NULL),
(8, '2025-07-22', '20:02:00', '20:32:00', 5, 6, 1, 'Programada', NULL, '2025-07-20 07:00:53', NULL, NULL, NULL),
(9, '2025-07-22', '10:32:00', '11:02:00', 5, 6, 1, 'Completada', NULL, '2025-07-20 07:00:53', NULL, NULL, NULL),
(10, '2025-07-25', '11:02:00', '11:32:00', 6, 5, 1, 'Programada', NULL, '2025-07-20 07:34:17', 'lo mas nuevo ', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_puntual`
--

CREATE TABLE `disponibilidad_puntual` (
  `id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `tipo` enum('disponible','bloqueado') NOT NULL DEFAULT 'disponible',
  `notas` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `disponibilidad_puntual`
--

INSERT INTO `disponibilidad_puntual` (`id`, `profesor_id`, `fecha`, `hora_inicio`, `hora_fin`, `tipo`, `notas`) VALUES
(1, 5, '2025-07-22', '10:02:00', '11:02:00', 'disponible', NULL),
(4, 6, '2025-07-25', '11:02:00', '12:02:00', 'disponible', NULL),
(5, 5, '2025-08-18', '12:46:00', '14:46:00', 'disponible', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `disponibilidad_recurrente`
--

CREATE TABLE `disponibilidad_recurrente` (
  `id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `dia_semana` int(11) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `fecha_inicio_validez` date DEFAULT NULL,
  `fecha_fin_validez` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `disponibilidad_recurrente`
--

INSERT INTO `disponibilidad_recurrente` (`id`, `profesor_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `fecha_inicio_validez`, `fecha_fin_validez`) VALUES
(3, 5, 2, '20:02:00', '21:02:00', '2025-07-22', '2025-07-23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios_profesores`
--

CREATE TABLE `horarios_profesores` (
  `id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('disponible','ocupado','cancelado') NOT NULL DEFAULT 'disponible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `profesores`
--

CREATE TABLE `profesores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `contrasena` varchar(255) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `profesores`
--

INSERT INTO `profesores` (`id`, `nombre`, `correo`, `contrasena`, `fecha_registro`) VALUES
(5, 'Jose Millones Angeles', 'jmillones@unprg.edu.pe', '$2y$10$j1HMame6IdslTCBsutDcsuhoLlR64Cn5jcRpt3fZhg9YP./Q0iotK', '2025-07-20 00:03:02'),
(6, 'Abad Perez Coronel', 'aperez@unprg.edu.pe', '$2y$10$MivUqp6DcW4.jrKEdn1dDObwuLXAE9.ezfvLnUQTcTHw.5i6RgMG2', '2025-07-20 02:03:37');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_asesoria`
--

CREATE TABLE `solicitudes_asesoria` (
  `id` int(11) NOT NULL,
  `alumno_id` int(11) NOT NULL,
  `profesor_id` int(11) NOT NULL,
  `fecha_asesoria` date DEFAULT NULL,
  `hora_inicio_asesoria` time DEFAULT NULL,
  `hora_fin_asesoria` time DEFAULT NULL,
  `tema_principal_id` int(11) NOT NULL,
  `mensaje_alumno` text DEFAULT NULL,
  `estado` enum('pendiente','aceptada','rechazada','completada','cancelada') NOT NULL DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_respuesta` datetime DEFAULT NULL,
  `comentario_profesor` text DEFAULT NULL,
  `mensaje_profesor` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_tema`
--

CREATE TABLE `solicitud_tema` (
  `solicitud_id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temas`
--

CREATE TABLE `temas` (
  `id` int(11) NOT NULL,
  `tema` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `temas`
--

INSERT INTO `temas` (`id`, `tema`) VALUES
(1, 'base de datos'),
(2, 'marketing'),
(3, 'seguridad wed'),
(4, 'manejo básico de Windows'),
(5, 'paginas virtuales');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `temas_profesores`
--

CREATE TABLE `temas_profesores` (
  `profesor_id` int(11) NOT NULL,
  `tema_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `temas_profesores`
--

INSERT INTO `temas_profesores` (`profesor_id`, `tema_id`) VALUES
(5, 1),
(5, 2),
(5, 3),
(5, 5),
(6, 1),
(6, 4);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `asesorias_reservadas`
--
ALTER TABLE `asesorias_reservadas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`),
  ADD KEY `profesor_id` (`profesor_id`);

--
-- Indices de la tabla `citas`
--
ALTER TABLE `citas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `alumno_id` (`alumno_id`),
  ADD KEY `tema_id` (`tema_id`);

--
-- Indices de la tabla `disponibilidad_puntual`
--
ALTER TABLE `disponibilidad_puntual`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profesor_id` (`profesor_id`);

--
-- Indices de la tabla `disponibilidad_recurrente`
--
ALTER TABLE `disponibilidad_recurrente`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profesor_id` (`profesor_id`);

--
-- Indices de la tabla `horarios_profesores`
--
ALTER TABLE `horarios_profesores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profesor_id` (`profesor_id`);

--
-- Indices de la tabla `profesores`
--
ALTER TABLE `profesores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `correo` (`correo`);

--
-- Indices de la tabla `solicitudes_asesoria`
--
ALTER TABLE `solicitudes_asesoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `alumno_id` (`alumno_id`),
  ADD KEY `profesor_id` (`profesor_id`),
  ADD KEY `tema_principal_id` (`tema_principal_id`);

--
-- Indices de la tabla `solicitud_tema`
--
ALTER TABLE `solicitud_tema`
  ADD PRIMARY KEY (`solicitud_id`,`tema_id`),
  ADD KEY `tema_id` (`tema_id`);

--
-- Indices de la tabla `temas`
--
ALTER TABLE `temas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `temas_profesores`
--
ALTER TABLE `temas_profesores`
  ADD PRIMARY KEY (`profesor_id`,`tema_id`),
  ADD KEY `tema_id` (`tema_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `alumnos`
--
ALTER TABLE `alumnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `asesorias_reservadas`
--
ALTER TABLE `asesorias_reservadas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `citas`
--
ALTER TABLE `citas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `disponibilidad_puntual`
--
ALTER TABLE `disponibilidad_puntual`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `disponibilidad_recurrente`
--
ALTER TABLE `disponibilidad_recurrente`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `horarios_profesores`
--
ALTER TABLE `horarios_profesores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `profesores`
--
ALTER TABLE `profesores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `solicitudes_asesoria`
--
ALTER TABLE `solicitudes_asesoria`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `temas`
--
ALTER TABLE `temas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asesorias_reservadas`
--
ALTER TABLE `asesorias_reservadas`
  ADD CONSTRAINT `asesorias_reservadas_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`),
  ADD CONSTRAINT `asesorias_reservadas_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`);

--
-- Filtros para la tabla `citas`
--
ALTER TABLE `citas`
  ADD CONSTRAINT `citas_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `citas_ibfk_3` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `citas_ibfk_4` FOREIGN KEY (`tema_id`) REFERENCES `temas` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `disponibilidad_puntual`
--
ALTER TABLE `disponibilidad_puntual`
  ADD CONSTRAINT `disponibilidad_puntual_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `disponibilidad_recurrente`
--
ALTER TABLE `disponibilidad_recurrente`
  ADD CONSTRAINT `disponibilidad_recurrente_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `horarios_profesores`
--
ALTER TABLE `horarios_profesores`
  ADD CONSTRAINT `horarios_profesores_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitudes_asesoria`
--
ALTER TABLE `solicitudes_asesoria`
  ADD CONSTRAINT `solicitudes_asesoria_ibfk_1` FOREIGN KEY (`alumno_id`) REFERENCES `alumnos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `solicitudes_asesoria_ibfk_2` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `solicitudes_asesoria_ibfk_3` FOREIGN KEY (`tema_principal_id`) REFERENCES `temas` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `solicitud_tema`
--
ALTER TABLE `solicitud_tema`
  ADD CONSTRAINT `solicitud_tema_ibfk_1` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_asesoria` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `solicitud_tema_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `temas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `temas_profesores`
--
ALTER TABLE `temas_profesores`
  ADD CONSTRAINT `temas_profesores_ibfk_1` FOREIGN KEY (`profesor_id`) REFERENCES `profesores` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `temas_profesores_ibfk_2` FOREIGN KEY (`tema_id`) REFERENCES `temas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
