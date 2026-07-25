-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 23-07-2026 a las 20:57:04
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
-- Base de datos: `gesthogar`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividades`
--

CREATE TABLE `actividades` (
  `id_actividad` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Quien creó la actividad',
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `estado` enum('activo','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `actividades`
--

INSERT INTO `actividades` (`id_actividad`, `id_usuario`, `nombre`, `descripcion`, `fecha`, `foto`, `observaciones`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 2, 'Taller de manualidades', 'Armado de tarjetas con materiales reciclados', '2026-06-18', NULL, 'Buena participación general', 'activo', 2, '2026-06-26 20:06:14', NULL, NULL),
(2, 3, 'Caminata en el patio', 'Caminata asistida de 15 minutos', '2026-06-20', NULL, 'Todos participaron sin inconvenientes', 'activo', 3, '2026-06-26 20:06:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `actividad_residentes`
--

CREATE TABLE `actividad_residentes` (
  `id_actividad_residente` int(11) NOT NULL,
  `id_actividad` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `observacion_participacion` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `actividad_residentes`
--

INSERT INTO `actividad_residentes` (`id_actividad_residente`, `id_actividad`, `id_residente`, `observacion_participacion`, `created_by`, `created_at`) VALUES
(1, 1, 1, 'Participó activamente, de buen ánimo', 2, '2026-06-26 20:06:14'),
(2, 1, 2, 'Participó parcialmente', 2, '2026-06-26 20:06:14'),
(3, 2, 1, 'Completó la caminata sin problemas', 3, '2026-06-26 20:06:14'),
(4, 2, 3, 'Necesitó descanso a mitad de camino', 3, '2026-06-26 20:06:14');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administracion_medicacion`
--

CREATE TABLE `administracion_medicacion` (
  `id_adm` int(11) NOT NULL,
  `id_plan` int(11) DEFAULT NULL,
  `id_residente` int(11) DEFAULT NULL,
  `id_medicamento` int(11) DEFAULT NULL,
  `id_cuidadora_usuario` int(11) DEFAULT NULL COMMENT 'FK directo a usuarios',
  `fecha_hora` datetime DEFAULT NULL,
  `estado_adm` enum('pendiente','administrado','no_pudo','no_dado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `administracion_medicacion`
--

INSERT INTO `administracion_medicacion` (`id_adm`, `id_plan`, `id_residente`, `id_medicamento`, `id_cuidadora_usuario`, `fecha_hora`, `estado_adm`, `observaciones`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, 1, 1, 2, '2026-06-12 08:00:00', 'administrado', '', '2026-06-26 20:12:55', NULL, NULL),
(2, 2, 1, 1, 3, '2026-06-12 20:00:00', 'administrado', '', '2026-06-26 20:12:55', NULL, NULL),
(3, 3, 1, 4, 2, '2026-06-13 07:30:00', 'no_pudo', 'Residente dormida, se avisó a Carla', '2026-06-26 20:12:55', NULL, NULL),
(4, 4, 2, 2, 4, '2026-06-14 08:00:00', 'administrado', '', '2026-06-26 20:12:55', NULL, NULL),
(5, 5, 2, 2, 5, '2026-06-15 14:00:00', 'administrado', '', '2026-06-26 20:12:55', NULL, NULL),
(6, 7, 3, 3, 2, '2026-06-16 08:00:00', 'no_dado', 'No presentó fiebre, se registró por indicación', '2026-06-26 20:12:55', NULL, NULL),
(7, 7, 3, 3, 1, '2026-06-22 21:50:15', 'administrado', '', '2026-06-26 20:12:55', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `administracion_tareas`
--

CREATE TABLE `administracion_tareas` (
  `id_adm_tarea` int(11) NOT NULL,
  `id_tarea` int(11) DEFAULT NULL,
  `id_residente` int(11) DEFAULT NULL,
  `id_cuidadora_usuario` int(11) DEFAULT NULL COMMENT 'FK directo a usuarios',
  `fecha_hora` datetime DEFAULT NULL,
  `estado_tarea` enum('pendiente','realizada','no_realizada','no_pudo') DEFAULT 'pendiente',
  `motivo` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `camas`
--

CREATE TABLE `camas` (
  `id_cama` int(11) NOT NULL,
  `id_habitacion` int(11) NOT NULL,
  `identificador` varchar(20) NOT NULL COMMENT 'Ej: Cama A, Cama B, Cama 1',
  `estado` enum('disponible','ocupada','mantenimiento') DEFAULT 'disponible',
  `observaciones` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `camas`
--

INSERT INTO `camas` (`id_cama`, `id_habitacion`, `identificador`, `estado`, `observaciones`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, 'Cama A', 'disponible', NULL, 1, '2026-06-26 20:06:14', 1, '2026-07-06 23:26:29'),
(2, 2, 'Cama A', 'ocupada', NULL, 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 3, 'Cama A', 'disponible', NULL, 1, '2026-06-26 20:06:14', NULL, NULL),
(4, 4, 'Cama A', 'ocupada', NULL, 1, '2026-06-26 20:06:14', NULL, NULL),
(5, 5, 'Cama A', 'disponible', NULL, 1, '2026-06-26 20:06:14', NULL, NULL),
(6, 1, 'Cama B', 'ocupada', NULL, NULL, '2026-06-26 20:12:55', NULL, '2026-06-29 20:26:15'),
(7, 2, 'Cama B', 'ocupada', NULL, NULL, '2026-06-26 20:12:55', NULL, '2026-06-29 20:26:20'),
(8, 3, 'Cama B', 'disponible', NULL, NULL, '2026-06-26 20:12:55', NULL, '2026-06-29 20:26:32'),
(9, 4, 'Cama B', 'disponible', NULL, NULL, '2026-06-26 20:12:55', NULL, '2026-06-29 20:26:38'),
(10, 5, 'Cama B', 'disponible', NULL, NULL, '2026-06-26 20:12:55', NULL, '2026-06-29 20:26:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cocina`
--

CREATE TABLE `cocina` (
  `id_cocina` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `tipo` enum('receta','gasto') NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL COMMENT 'NULL para recetas, requerido para gastos',
  `foto` varchar(255) DEFAULT NULL,
  `fecha` date DEFAULT NULL,
  `estado` enum('activo','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cocina`
--

INSERT INTO `cocina` (`id_cocina`, `id_usuario`, `tipo`, `titulo`, `descripcion`, `monto`, `foto`, `fecha`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 6, 'receta', 'Sopa de verduras', 'Zapallo, zanahoria, papa y caldo liviano', NULL, 'uploads/cocina/ejemplo_cocina.jpg', '2026-06-18', 'activo', 6, '2026-06-26 20:06:14', NULL, NULL),
(2, 6, 'gasto', 'Compra verduleria', 'Verduras para cena y almuerzo', 8500.00, 'uploads/cocina/ejemplo_cocina.jpg', '2026-06-18', 'activo', 6, '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `controles_clinicos`
--

CREATE TABLE `controles_clinicos` (
  `id_control` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Quien realizó el control',
  `tipo_control` enum('tension_sistolica','tension_diastolica','temperatura','peso','glucemia','saturacion_oxigeno','frecuencia_cardiaca') NOT NULL,
  `valor` decimal(6,2) NOT NULL,
  `unidad` varchar(20) NOT NULL COMMENT 'mmHg, C, kg, mg/dL, %, lpm',
  `observaciones` text DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_catalogo`
--

CREATE TABLE `estados_catalogo` (
  `id_estado` int(11) NOT NULL,
  `entidad` varchar(50) NOT NULL COMMENT 'residente, usuario, visita, etc.',
  `nombre_estado` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `estados_catalogo`
--

INSERT INTO `estados_catalogo` (`id_estado`, `entidad`, `nombre_estado`, `descripcion`) VALUES
(1, 'residente', 'activo', 'El residente se encuentra actualmente en el hogar'),
(2, 'residente', 'pausado', 'El residente está temporalmente ausente (ej: hospitalizado)'),
(3, 'residente', 'baja', 'El residente dejó el hogar de forma definitiva'),
(4, 'usuario', 'activo', 'El usuario puede iniciar sesión en el sistema'),
(5, 'usuario', 'pausado', 'El usuario está suspendido temporalmente'),
(6, 'usuario', 'baja', 'El usuario fue dado de baja del sistema'),
(7, 'medicacion_adm', 'pendiente', 'La medicación aún no fue registrada en este turno'),
(8, 'medicacion_adm', 'administrado', 'La medicación fue administrada correctamente'),
(9, 'medicacion_adm', 'no_pudo', 'La cuidadora no pudo administrar la medicación'),
(10, 'medicacion_adm', 'no_dado', 'La medicación no fue administrada en este turno'),
(11, 'incidente', 'pendiente', 'Incidente registrado, pendiente de revisión por el responsable'),
(12, 'incidente', 'revisado', 'El incidente fue revisado y tomado en cuenta'),
(13, 'tarea_adm', 'pendiente', 'La tarea aún no fue registrada en este turno'),
(14, 'tarea_adm', 'realizada', 'La tarea fue realizada correctamente'),
(15, 'tarea_adm', 'no_realizada', 'La tarea no fue realizada en este turno'),
(16, 'tarea_adm', 'no_pudo', 'La cuidadora no pudo realizar la tarea'),
(17, 'visita', 'en_curso', 'El visitante se encuentra actualmente en el hogar'),
(18, 'visita', 'finalizada', 'La visita fue finalizada y el visitante se retiró'),
(19, 'traspaso', 'pendiente', 'El traspaso fue registrado, esperando confirmación del entrante'),
(20, 'traspaso', 'cerrado', 'El traspaso fue confirmado y cerrado'),
(21, 'cocina', 'activo', 'Registro activo'),
(22, 'cocina', 'baja', 'Registro dado de baja');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `habitaciones`
--

CREATE TABLE `habitaciones` (
  `id_habitacion` int(11) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `capacidad` int(11) DEFAULT 1,
  `sector` varchar(80) DEFAULT NULL,
  `piso` varchar(40) DEFAULT NULL,
  `estado` enum('disponible','ocupada','mantenimiento') DEFAULT 'disponible',
  `observaciones` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `habitaciones`
--

INSERT INTO `habitaciones` (`id_habitacion`, `numero`, `descripcion`, `capacidad`, `sector`, `piso`, `estado`, `observaciones`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, '101', NULL, 1, 'Ala Norte', 'Planta baja', 'disponible', 'Cerca del baño', 1, '2026-06-26 20:06:14', 1, '2026-07-06 23:26:29'),
(2, '102', NULL, 1, 'Ala Norte', 'Planta baja', 'ocupada', 'Ventana al patio', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, '103', NULL, 1, 'Ala Sur', 'Planta baja', 'disponible', 'Lista para ingreso', 1, '2026-06-26 20:06:14', NULL, NULL),
(4, '201', NULL, 1, 'Ala Norte', 'Primer piso', 'disponible', 'Habitación tranquila', 1, '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55'),
(5, '202', NULL, 1, 'Ala Sur', 'Primer piso', 'disponible', 'Revisar luz', 1, '2026-06-26 20:06:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `historial_estados`
--

CREATE TABLE `historial_estados` (
  `id_historial` int(11) NOT NULL,
  `entidad` enum('usuario','residente') NOT NULL,
  `id_registro` int(11) NOT NULL COMMENT 'ID del usuario o residente afectado',
  `estado_anterior` varchar(30) NOT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `cambiado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `historial_estados`
--

INSERT INTO `historial_estados` (`id_historial`, `entidad`, `id_registro`, `estado_anterior`, `estado_nuevo`, `cambiado_por`, `created_at`, `motivo`, `fecha_fin`) VALUES
(1, 'usuario', 10, 'activo', 'baja', 1, '2026-06-24 18:30:25', NULL, NULL),
(2, 'usuario', 8, 'activo', 'baja', 1, '2026-06-24 18:32:02', NULL, NULL),
(3, 'usuario', 7, 'activo', 'baja', 1, '2026-06-24 19:50:12', NULL, NULL),
(4, 'usuario', 10, 'baja', 'activo', 1, '2026-06-24 19:50:56', NULL, NULL),
(5, 'usuario', 10, 'activo', 'baja', 1, '2026-06-24 19:51:21', NULL, NULL),
(6, 'usuario', 7, 'baja', 'activo', 1, '2026-06-24 20:23:49', NULL, NULL),
(7, 'usuario', 2, 'activo', 'baja', 1, '2026-06-24 23:04:15', NULL, NULL),
(8, 'usuario', 2, 'activo', 'baja', 1, '2026-06-25 19:03:04', NULL, NULL),
(9, 'usuario', 2, 'activo', 'baja', 1, '2026-06-25 19:06:19', NULL, NULL),
(16, 'usuario', 8, '', 'activo', 1, '2026-06-26 23:21:29', NULL, NULL),
(17, 'usuario', 8, 'activo', 'baja', 1, '2026-06-26 23:21:57', NULL, NULL),
(18, 'usuario', 8, 'baja', 'activo', 1, '2026-06-26 23:24:55', NULL, NULL),
(19, 'usuario', 5, 'activo', 'pausado', 1, '2026-06-29 19:00:44', NULL, NULL),
(20, 'usuario', 5, 'pausado', 'activo', 1, '2026-06-29 19:00:54', NULL, NULL),
(21, 'usuario', 8, 'activo', 'baja', 1, '2026-06-29 19:01:00', NULL, NULL),
(22, 'usuario', 7, 'activo', 'baja', 1, '2026-07-06 23:24:04', NULL, NULL),
(23, 'usuario', 7, 'baja', 'activo', 1, '2026-07-06 23:24:28', NULL, NULL),
(24, 'residente', 1, 'activo', 'pausado', 1, '2026-07-06 23:26:29', NULL, NULL),
(25, 'usuario', 7, 'activo', 'baja', 1, '2026-07-06 23:37:50', NULL, NULL),
(26, 'usuario', 7, 'baja', 'activo', 1, '2026-07-06 23:38:57', NULL, NULL),
(27, 'residente', 3, '', 'servicio_suspendido', 1, '2026-07-23 18:24:12', 'Internacion por neumonia', NULL),
(28, 'residente', 1, 'pausado', 'activo', 1, '2026-07-23 18:34:37', NULL, NULL),
(29, 'usuario', 7, 'activo', 'inhabilitado', 1, '2026-07-23 18:37:04', NULL, NULL),
(30, 'residente', 3, 'activo', 'servicio_suspendido', 1, '2026-07-23 18:37:42', 'Internacion por neumonia', NULL),
(31, 'usuario', 7, 'activo', 'inhabilitado', 1, '2026-07-23 18:45:24', NULL, NULL),
(32, 'usuario', 7, 'activo', 'inhabilitado', 1, '2026-07-23 18:47:35', NULL, NULL),
(33, 'usuario', 7, 'activo', 'pausado', 1, '2026-07-23 18:50:27', NULL, NULL),
(34, 'usuario', 7, 'pausado', 'activo', 1, '2026-07-23 18:53:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidentes`
--

CREATE TABLE `incidentes` (
  `id_incidente` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Cuidadora que registró',
  `descripcion` text NOT NULL,
  `acciones_tomadas` text NOT NULL,
  `gravedad` enum('leve','moderado','grave') NOT NULL,
  `medico_interviniente` varchar(150) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `notificado_responsable` tinyint(4) DEFAULT 0 COMMENT 'Antes: notificado_carla',
  `estado` enum('pendiente','revisado') DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL COMMENT 'Quien lo marcó como revisado',
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `incidentes`
--

INSERT INTO `incidentes` (`id_incidente`, `id_residente`, `id_usuario`, `descripcion`, `acciones_tomadas`, `gravedad`, `medico_interviniente`, `fecha_hora`, `notificado_responsable`, `estado`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 2, 2, 'Carlos Perez sufrió un mareo leve al levantarse', 'Se lo ayudó a sentarse, se controló la presión', 'leve', NULL, '2026-06-19 09:15:00', 0, 'pendiente', '2026-06-26 20:06:14', NULL, NULL),
(2, 1, 3, 'Rosa Gomez presentó tos persistente durante la noche', 'Se la incorporó, se le dio agua, mejoró a los 10 minutos', 'moderado', NULL, '2026-06-20 02:30:00', 1, 'pendiente', '2026-06-26 20:06:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicacion_residente`
--

CREATE TABLE `medicacion_residente` (
  `id_plan` int(11) NOT NULL,
  `id_receta` int(11) DEFAULT NULL COMMENT 'Nullable: puede no haber receta',
  `id_residente` int(11) NOT NULL,
  `id_medicamento` int(11) NOT NULL,
  `dosis` varchar(80) DEFAULT NULL,
  `horario` time DEFAULT NULL,
  `indicacion` text DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `medicacion_residente`
--

INSERT INTO `medicacion_residente` (`id_plan`, `id_receta`, `id_residente`, `id_medicamento`, `dosis`, `horario`, `indicacion`, `fecha_inicio`, `fecha_fin`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, 1, 1, '1 comprimido', '08:00:00', 'Después del desayuno', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(2, 1, 1, 1, '1 comprimido', '20:00:00', 'Después de la cena', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 2, 1, 4, '1 cápsula', '07:30:00', 'Antes del desayuno', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(4, 3, 2, 2, '1 comprimido', '08:00:00', 'Después del desayuno', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(5, 3, 2, 2, '1 comprimido', '14:00:00', 'Después del almuerzo', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(6, 3, 2, 2, '1 comprimido', '21:00:00', 'Después de la cena', '2026-06-01', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(7, 4, 3, 3, '1 comprimido', '08:00:00', 'Si tiene fiebre o dolor', '2026-06-14', '2026-06-23', 'pausado', 1, '2026-06-26 20:06:14', 1, '2026-07-23 18:24:12'),
(8, 4, 3, 3, '1 comprimido', '16:00:00', 'Repetir si corresponde', '2026-06-14', '2026-06-23', 'pausado', 1, '2026-06-26 20:06:14', 1, '2026-07-23 18:24:12'),
(9, 5, 1, 5, '1 comprimido', '08:00:00', '', '2026-06-29', NULL, 'activo', 1, '2026-06-29 20:18:24', NULL, NULL),
(10, 5, 1, 5, '1 comprimido', '16:00:00', '', '2026-06-29', NULL, 'activo', 1, '2026-06-29 20:18:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `medicamentos`
--

CREATE TABLE `medicamentos` (
  `id_medicamento` int(11) NOT NULL,
  `nombre` text NOT NULL,
  `presentacion` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `medicamentos`
--

INSERT INTO `medicamentos` (`id_medicamento`, `nombre`, `presentacion`, `descripcion`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 'Losartan', 'Comprimido 50mg', 'Presión arterial', 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(2, 'Metformina', 'Comprimido 850mg', 'Diabetes', 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 'Paracetamol', 'Comprimido 500mg', 'Dolor o fiebre', 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(4, 'Omeprazol', 'Cápsula 20mg', 'Protector gástrico', 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(5, 'Ibuprofeno', '10mg', '', 'activo', 1, '2026-06-29 20:17:30', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas_medicas`
--

CREATE TABLE `recetas_medicas` (
  `id_receta` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `diagnostico` varchar(150) NOT NULL,
  `indicaciones_generales` text DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL,
  `foto_receta` varchar(255) DEFAULT NULL,
  `estado` enum('activa','pausada','finalizada','vencida') DEFAULT 'activa',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `recetas_medicas`
--

INSERT INTO `recetas_medicas` (`id_receta`, `id_residente`, `diagnostico`, `indicaciones_generales`, `fecha_inicio`, `fecha_fin`, `foto_receta`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, 'Tratamiento permanente de presión', 'Control diario de presión arterial.', '2026-06-01', NULL, NULL, 'activa', 1, '2026-06-26 20:06:14', NULL, NULL),
(2, 1, 'Protector gástrico permanente', 'Administrar antes del desayuno.', '2026-06-01', NULL, NULL, 'activa', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 2, 'Tratamiento permanente de diabetes', 'Controlar alimentación y horarios.', '2026-06-01', NULL, NULL, 'activa', 1, '2026-06-26 20:06:14', NULL, NULL),
(4, 3, 'Tratamiento temporal por gripe', 'Solo durante el período indicado.', '2026-06-14', NULL, NULL, 'activa', 1, '2026-06-26 20:06:14', NULL, NULL),
(5, 1, 'Dolor de cabeza', '', '2026-06-29', NULL, NULL, 'activa', 1, '2026-06-29 20:18:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `residentes`
--

CREATE TABLE `residentes` (
  `id_residente` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) NOT NULL,
  `dni` varchar(9) NOT NULL,
  `diagnostico` text DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `fecha_egreso` date DEFAULT NULL COMMENT 'NULL si sigue en el hogar',
  `movilidad_autonoma` tinyint(4) DEFAULT 1,
  `autorizado_foto` tinyint(4) DEFAULT 0 COMMENT 'Autorización firmada en admisión',
  `contacto_familiar` varchar(200) DEFAULT NULL,
  `telefono_familiar` varchar(50) DEFAULT NULL,
  `id_habitacion` int(11) DEFAULT NULL,
  `id_cama` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `residentes`
--

INSERT INTO `residentes` (`id_residente`, `nombre`, `apellido`, `dni`, `diagnostico`, `fecha_nacimiento`, `fecha_ingreso`, `fecha_egreso`, `movilidad_autonoma`, `autorizado_foto`, `contacto_familiar`, `telefono_familiar`, `id_habitacion`, `id_cama`, `foto`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 'Rosa', 'Gomez', '22345678', NULL, '1940-03-12', '2026-01-10', NULL, 1, 0, 'Laura Gomez', '351555111', 1, 1, 'uploads/residentes/ejemplo_residente.jpg', 'activo', 1, '2026-06-26 20:06:14', 1, '2026-07-23 18:34:37'),
(2, 'Carlos', 'Perez', '18765432', NULL, '1938-07-20', '2026-01-15', NULL, 1, 0, 'Juan Perez', '351555222', 2, 2, 'uploads/residentes/ejemplo_residente.jpg', 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 'Elena', 'Ruiz', '20555111', NULL, '1942-11-05', '2026-02-01', NULL, 1, 0, 'Marcela Ruiz', '351555333', 4, 4, 'uploads/residentes/ejemplo_residente.jpg', 'activo', 1, '2026-06-26 20:06:14', 1, '2026-07-23 18:52:53');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tareas_residente`
--

CREATE TABLE `tareas_residente` (
  `id_tarea` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `titulo` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` enum('fija','puntual') DEFAULT 'fija' COMMENT 'Fija=rutina diaria, Puntual=turno específico',
  `id_turno_prog` int(11) DEFAULT NULL COMMENT 'Solo para tipo puntual',
  `horario` time DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tareas_residente`
--

INSERT INTO `tareas_residente` (`id_tarea`, `id_residente`, `titulo`, `descripcion`, `tipo`, `id_turno_prog`, `horario`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 1, 'Controlar presion', 'Tomar presión y registrar resultado', 'fija', NULL, '10:00:00', 'activo', 1, '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55'),
(2, 2, 'Acompanamiento caminata', 'Acompañarlo 10 minutos por el patio', 'fija', NULL, '17:00:00', 'activo', 1, '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55'),
(3, 3, 'Hidratacion', 'Ofrecer agua y registrar si aceptó', 'fija', NULL, '11:00:00', 'activo', 1, '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `traspaso_turno`
--

CREATE TABLE `traspaso_turno` (
  `id_traspaso` int(11) NOT NULL,
  `id_usuario_saliente` int(11) NOT NULL,
  `id_usuario_entrante` int(11) DEFAULT NULL,
  `novedades` text NOT NULL,
  `fecha_cierre` datetime NOT NULL,
  `fecha_apertura` datetime DEFAULT NULL,
  `estado` enum('pendiente','cerrado') DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `traspaso_turno`
--

INSERT INTO `traspaso_turno` (`id_traspaso`, `id_usuario_saliente`, `id_usuario_entrante`, `novedades`, `fecha_cierre`, `fecha_apertura`, `estado`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 2, 3, 'Turno tranquilo. Rosa tomó toda su medicación. Carlos tuvo visita de su hijo. Sin incidentes.', '2026-06-20 08:00:00', '2026-06-20 08:05:00', 'pendiente', '2026-06-26 20:06:14', NULL, '2026-06-26 20:12:55'),
(2, 3, 4, 'Elena Ruiz con visita de su hija por la tarde. Resto de residentes sin novedades.', '2026-06-21 08:00:00', NULL, 'pendiente', '2026-06-26 20:06:14', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos_programados`
--

CREATE TABLE `turnos_programados` (
  `id_turno_prog` int(11) NOT NULL,
  `id_cuidadora_usuario` int(11) NOT NULL COMMENT 'FK directo a usuarios',
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `nota` text DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `turnos_programados`
--

INSERT INTO `turnos_programados` (`id_turno_prog`, `id_cuidadora_usuario`, `fecha`, `hora_inicio`, `hora_fin`, `nota`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 2, '2026-06-11', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(2, 3, '2026-06-11', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(3, 6, '2026-06-11', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(4, 6, '2026-06-11', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(5, 7, '2026-06-11', '10:00:00', '18:00:00', 'Asistente Paula', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(6, 3, '2026-06-12', '08:00:00', '20:00:00', 'Turno diurno Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(7, 4, '2026-06-12', '20:00:00', '08:00:00', 'Turno noche Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(8, 5, '2026-06-12', '14:00:00', '22:00:00', 'Refuerzo tarde Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(9, 6, '2026-06-12', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(10, 6, '2026-06-12', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(11, 4, '2026-06-13', '08:00:00', '20:00:00', 'Turno diurno Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(12, 5, '2026-06-13', '20:00:00', '08:00:00', 'Turno noche Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(13, 6, '2026-06-13', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(14, 6, '2026-06-13', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(15, 5, '2026-06-14', '08:00:00', '20:00:00', 'Turno diurno Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(16, 2, '2026-06-14', '20:00:00', '08:00:00', 'Turno noche Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(17, 6, '2026-06-14', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(18, 6, '2026-06-14', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(19, 2, '2026-06-15', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(20, 3, '2026-06-15', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(21, 4, '2026-06-15', '14:00:00', '22:00:00', 'Refuerzo tarde Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(22, 6, '2026-06-15', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(23, 6, '2026-06-15', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(24, 7, '2026-06-15', '10:00:00', '18:00:00', 'Asistente Paula', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(25, 3, '2026-06-16', '08:00:00', '20:00:00', 'Turno diurno Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(26, 4, '2026-06-16', '20:00:00', '08:00:00', 'Turno noche Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(27, 6, '2026-06-16', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(28, 6, '2026-06-16', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(29, 4, '2026-06-17', '08:00:00', '20:00:00', 'Turno diurno Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(30, 5, '2026-06-17', '20:00:00', '08:00:00', 'Turno noche Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(31, 6, '2026-06-17', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(32, 6, '2026-06-17', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(33, 5, '2026-06-18', '08:00:00', '20:00:00', 'Turno diurno Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(34, 2, '2026-06-18', '20:00:00', '08:00:00', 'Turno noche Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(35, 3, '2026-06-18', '14:00:00', '22:00:00', 'Refuerzo tarde Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(36, 6, '2026-06-18', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(37, 6, '2026-06-18', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(38, 7, '2026-06-18', '10:00:00', '18:00:00', 'Asistente Paula', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(39, 2, '2026-06-19', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(40, 3, '2026-06-19', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(41, 6, '2026-06-19', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(42, 6, '2026-06-19', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(43, 3, '2026-06-20', '08:00:00', '20:00:00', 'Turno diurno Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(44, 4, '2026-06-20', '20:00:00', '08:00:00', 'Turno noche Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(45, 6, '2026-06-20', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(46, 6, '2026-06-20', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(47, 4, '2026-06-21', '08:00:00', '20:00:00', 'Turno diurno Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(48, 5, '2026-06-21', '20:00:00', '08:00:00', 'Turno noche Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(49, 2, '2026-06-21', '14:00:00', '22:00:00', 'Refuerzo tarde Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(50, 6, '2026-06-21', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(51, 6, '2026-06-21', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(52, 5, '2026-06-22', '08:00:00', '20:00:00', 'Turno diurno Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(53, 2, '2026-06-22', '20:00:00', '08:00:00', 'Turno noche Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(54, 6, '2026-06-22', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(55, 6, '2026-06-22', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(56, 7, '2026-06-22', '10:00:00', '18:00:00', 'Asistente Paula', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(57, 2, '2026-06-23', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(58, 3, '2026-06-23', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(59, 6, '2026-06-23', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(60, 6, '2026-06-23', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(61, 3, '2026-06-24', '08:00:00', '20:00:00', 'Turno diurno Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(62, 4, '2026-06-24', '20:00:00', '08:00:00', 'Turno noche Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(63, 5, '2026-06-24', '14:00:00', '22:00:00', 'Refuerzo tarde Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(64, 6, '2026-06-24', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(65, 6, '2026-06-24', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(66, 4, '2026-06-25', '08:00:00', '20:00:00', 'Turno diurno Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(67, 5, '2026-06-25', '20:00:00', '08:00:00', 'Turno noche Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(68, 6, '2026-06-25', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(69, 6, '2026-06-25', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(70, 5, '2026-06-26', '08:00:00', '20:00:00', 'Turno diurno Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(71, 2, '2026-06-26', '20:00:00', '08:00:00', 'Turno noche Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(72, 6, '2026-06-26', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(73, 6, '2026-06-26', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(74, 2, '2026-06-27', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(75, 3, '2026-06-27', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(76, 4, '2026-06-27', '14:00:00', '22:00:00', 'Refuerzo tarde Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(77, 6, '2026-06-27', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(78, 6, '2026-06-27', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(79, 3, '2026-06-28', '08:00:00', '20:00:00', 'Turno diurno Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(80, 4, '2026-06-28', '20:00:00', '08:00:00', 'Turno noche Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(81, 6, '2026-06-28', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(82, 6, '2026-06-28', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(83, 4, '2026-06-29', '08:00:00', '20:00:00', 'Turno diurno Laura', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(84, 5, '2026-06-29', '20:00:00', '08:00:00', 'Turno noche Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(85, 6, '2026-06-29', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(86, 6, '2026-06-29', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(87, 5, '2026-06-30', '08:00:00', '20:00:00', 'Turno diurno Daniela', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(88, 2, '2026-06-30', '20:00:00', '08:00:00', 'Turno noche Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(89, 3, '2026-06-30', '14:00:00', '22:00:00', 'Refuerzo tarde Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(90, 6, '2026-06-30', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(91, 6, '2026-06-30', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(92, 2, '2026-07-01', '08:00:00', '20:00:00', 'Turno diurno Ana', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(93, 3, '2026-07-01', '20:00:00', '08:00:00', 'Turno noche Sofia', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(94, 6, '2026-07-01', '09:00:00', '13:00:00', 'Cocina almuerzo Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL),
(95, 6, '2026-07-01', '18:00:00', '21:00:00', 'Cocina cena Marta', 'activo', 1, '2026-06-19 01:09:12', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `apellido` varchar(50) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(150) NOT NULL,
  `rol` enum('admin','cuidadora','cocinera','asistente') NOT NULL,
  `dni` varchar(20) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `tipo_turno` varchar(50) DEFAULT NULL COMMENT 'Solo obligatorio para cuidadora/asistente',
  `color` varchar(20) DEFAULT '#087f7a' COMMENT 'Color en calendario',
  `token_recuperacion` varchar(100) DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `email`, `password`, `rol`, `dni`, `telefono`, `tipo_turno`, `color`, `token_recuperacion`, `estado`, `created_by`, `created_at`, `updated_by`, `updated_at`) VALUES
(1, 'Carla', 'Administradora', 'admin@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NULL, NULL, NULL, '#0f2f4a', NULL, 'activo', NULL, '2026-06-26 20:06:14', NULL, NULL),
(2, 'Ana', 'Lopez', 'ana@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuidadora', '30111222', '351555333', '24h/48h', '#e74c3c', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(3, 'Sofia', 'Martinez', 'sofia@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuidadora', '32777888', '351555444', '12h diurno', '#8e44ad', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(4, 'Laura', 'Fernandez', 'laura@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuidadora', '33555999', '351555666', '24h/48h', '#2980b9', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(5, 'Daniela', 'Acosta', 'daniela@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cuidadora', '34666112', '351555777', 'Refuerzo tarde', '#f39c12', NULL, 'activo', 1, '2026-06-26 20:06:14', 1, '2026-06-29 19:00:54'),
(6, 'Marta', 'Cocinera', 'marta@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cocinera', NULL, NULL, NULL, '#087f7a', NULL, 'activo', 1, '2026-06-26 20:06:14', NULL, NULL),
(7, 'Paula', 'Asistente', 'paula@gesthogar.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'asistente', '18765432', '3571592062', 'Nocturno', '#16a085', NULL, 'activo', 1, '2026-06-26 20:06:14', 1, '2026-07-23 18:53:24'),
(8, 'camila', 'graziani', 'camigraziani12@gmail.com', '$2y$10$JPMROeUAgS5m2M54KG0kou9YzxtqP3k8idQ.KVnJuchLI/vuEVKNC', 'asistente', NULL, '03571592061', NULL, '#087f7a', NULL, 'baja', 1, '2026-06-26 23:21:29', 1, '2026-06-29 19:01:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visitas`
--

CREATE TABLE `visitas` (
  `id_visita` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Quien agendó o registró el ingreso',
  `nombre_visitante` varchar(150) NOT NULL,
  `apellido_visitante` varchar(150) NOT NULL DEFAULT '',
  `parentesco` varchar(60) NOT NULL,
  `telefono_visitante` varchar(40) DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `hora_ingreso` datetime DEFAULT NULL,
  `hora_egreso` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL COMMENT 'Quien registró el egreso',
  `observaciones` text DEFAULT NULL,
  `motivo_cancelacion` varchar(255) DEFAULT NULL,
  `estado` enum('agendada','en_curso','finalizada','cancelada') DEFAULT 'agendada',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `visitas`
--

INSERT INTO `visitas` (`id_visita`, `id_residente`, `id_usuario`, `nombre_visitante`, `apellido_visitante`, `parentesco`, `telefono_visitante`, `fecha_programada`, `hora_ingreso`, `hora_egreso`, `cerrado_por`, `observaciones`, `motivo_cancelacion`, `estado`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Laura', 'Gomez', 'Hija', '351555111', '2026-06-19 16:00:00', '2026-06-19 16:00:00', '2026-06-19 17:30:00', 2, 'Visita tranquila, charlaron en el patio', NULL, 'finalizada', '2026-06-26 20:06:14', NULL),
(2, 2, 3, 'Juan', 'Perez', 'Hijo', '351555222', '2026-06-20 11:00:00', '2026-06-20 11:00:00', '2026-06-20 12:00:00', 3, '', NULL, 'finalizada', '2026-06-26 20:06:14', NULL),
(3, 3, 2, 'Marcela', 'Ruiz', 'Hija', '351555333', '2026-06-21 15:30:00', '2026-06-21 15:30:00', NULL, NULL, 'Visita en curso', NULL, 'en_curso', '2026-06-26 20:06:14', NULL);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD PRIMARY KEY (`id_actividad`),
  ADD KEY `fk_act_usuario` (`id_usuario`),
  ADD KEY `fk_act_created_by` (`created_by`),
  ADD KEY `fk_act_updated_by` (`updated_by`);

--
-- Indices de la tabla `actividad_residentes`
--
ALTER TABLE `actividad_residentes`
  ADD PRIMARY KEY (`id_actividad_residente`),
  ADD KEY `fk_ar_actividad` (`id_actividad`),
  ADD KEY `fk_ar_residente` (`id_residente`),
  ADD KEY `fk_ar_created_by` (`created_by`);

--
-- Indices de la tabla `administracion_medicacion`
--
ALTER TABLE `administracion_medicacion`
  ADD PRIMARY KEY (`id_adm`),
  ADD KEY `fk_admmed_plan` (`id_plan`),
  ADD KEY `fk_admmed_residente` (`id_residente`),
  ADD KEY `fk_admmed_medicamento` (`id_medicamento`),
  ADD KEY `fk_admmed_usuario` (`id_cuidadora_usuario`),
  ADD KEY `fk_admmed_updated_by` (`updated_by`);

--
-- Indices de la tabla `administracion_tareas`
--
ALTER TABLE `administracion_tareas`
  ADD PRIMARY KEY (`id_adm_tarea`),
  ADD KEY `fk_admtar_tarea` (`id_tarea`),
  ADD KEY `fk_admtar_residente` (`id_residente`),
  ADD KEY `fk_admtar_usuario` (`id_cuidadora_usuario`),
  ADD KEY `fk_admtar_updated_by` (`updated_by`);

--
-- Indices de la tabla `camas`
--
ALTER TABLE `camas`
  ADD PRIMARY KEY (`id_cama`),
  ADD KEY `fk_camas_habitacion` (`id_habitacion`),
  ADD KEY `fk_camas_created_by` (`created_by`),
  ADD KEY `fk_camas_updated_by` (`updated_by`);

--
-- Indices de la tabla `cocina`
--
ALTER TABLE `cocina`
  ADD PRIMARY KEY (`id_cocina`),
  ADD KEY `fk_coc_usuario` (`id_usuario`),
  ADD KEY `fk_coc_created_by` (`created_by`),
  ADD KEY `fk_coc_updated_by` (`updated_by`);

--
-- Indices de la tabla `controles_clinicos`
--
ALTER TABLE `controles_clinicos`
  ADD PRIMARY KEY (`id_control`),
  ADD KEY `fk_cc_residente` (`id_residente`),
  ADD KEY `fk_cc_usuario` (`id_usuario`),
  ADD KEY `idx_cc_residente_tipo_fecha` (`id_residente`,`tipo_control`,`fecha_hora`);

--
-- Indices de la tabla `estados_catalogo`
--
ALTER TABLE `estados_catalogo`
  ADD PRIMARY KEY (`id_estado`),
  ADD UNIQUE KEY `uq_entidad_estado` (`entidad`,`nombre_estado`);

--
-- Indices de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD PRIMARY KEY (`id_habitacion`),
  ADD KEY `fk_hab_created_by` (`created_by`),
  ADD KEY `fk_hab_updated_by` (`updated_by`);

--
-- Indices de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD PRIMARY KEY (`id_historial`),
  ADD KEY `fk_historial_cambiado_por` (`cambiado_por`),
  ADD KEY `idx_historial_entidad_registro` (`entidad`,`id_registro`);

--
-- Indices de la tabla `incidentes`
--
ALTER TABLE `incidentes`
  ADD PRIMARY KEY (`id_incidente`),
  ADD KEY `fk_inc_residente` (`id_residente`),
  ADD KEY `fk_inc_usuario` (`id_usuario`),
  ADD KEY `fk_inc_updated_by` (`updated_by`);

--
-- Indices de la tabla `medicacion_residente`
--
ALTER TABLE `medicacion_residente`
  ADD PRIMARY KEY (`id_plan`),
  ADD KEY `fk_medres_receta` (`id_receta`),
  ADD KEY `fk_medres_residente` (`id_residente`),
  ADD KEY `fk_medres_medicamento` (`id_medicamento`),
  ADD KEY `fk_medres_created_by` (`created_by`),
  ADD KEY `fk_medres_updated_by` (`updated_by`);

--
-- Indices de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD PRIMARY KEY (`id_medicamento`),
  ADD KEY `fk_med_created_by` (`created_by`),
  ADD KEY `fk_med_updated_by` (`updated_by`);

--
-- Indices de la tabla `recetas_medicas`
--
ALTER TABLE `recetas_medicas`
  ADD PRIMARY KEY (`id_receta`),
  ADD KEY `fk_receta_residente` (`id_residente`),
  ADD KEY `fk_receta_created_by` (`created_by`),
  ADD KEY `fk_receta_updated_by` (`updated_by`);

--
-- Indices de la tabla `residentes`
--
ALTER TABLE `residentes`
  ADD PRIMARY KEY (`id_residente`),
  ADD UNIQUE KEY `dni` (`dni`),
  ADD KEY `fk_res_habitacion` (`id_habitacion`),
  ADD KEY `fk_res_cama` (`id_cama`),
  ADD KEY `fk_res_created_by` (`created_by`),
  ADD KEY `fk_res_updated_by` (`updated_by`);

--
-- Indices de la tabla `tareas_residente`
--
ALTER TABLE `tareas_residente`
  ADD PRIMARY KEY (`id_tarea`),
  ADD KEY `fk_tar_residente` (`id_residente`),
  ADD KEY `fk_tar_turno_prog` (`id_turno_prog`),
  ADD KEY `fk_tar_created_by` (`created_by`),
  ADD KEY `fk_tar_updated_by` (`updated_by`);

--
-- Indices de la tabla `traspaso_turno`
--
ALTER TABLE `traspaso_turno`
  ADD PRIMARY KEY (`id_traspaso`),
  ADD KEY `fk_tt_saliente` (`id_usuario_saliente`),
  ADD KEY `fk_tt_entrante` (`id_usuario_entrante`),
  ADD KEY `fk_tt_updated_by` (`updated_by`);

--
-- Indices de la tabla `turnos_programados`
--
ALTER TABLE `turnos_programados`
  ADD PRIMARY KEY (`id_turno_prog`),
  ADD KEY `fk_tp_usuario` (`id_cuidadora_usuario`),
  ADD KEY `fk_tp_created_by` (`created_by`),
  ADD KEY `fk_tp_updated_by` (`updated_by`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_usuarios_created_by` (`created_by`),
  ADD KEY `fk_usuarios_updated_by` (`updated_by`);

--
-- Indices de la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD PRIMARY KEY (`id_visita`),
  ADD KEY `fk_vis_residente` (`id_residente`),
  ADD KEY `fk_vis_usuario` (`id_usuario`),
  ADD KEY `fk_vis_cerrado_por` (`cerrado_por`),
  ADD KEY `idx_vis_fecha` (`fecha_programada`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `actividades`
--
ALTER TABLE `actividades`
  MODIFY `id_actividad` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `actividad_residentes`
--
ALTER TABLE `actividad_residentes`
  MODIFY `id_actividad_residente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `administracion_medicacion`
--
ALTER TABLE `administracion_medicacion`
  MODIFY `id_adm` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `administracion_tareas`
--
ALTER TABLE `administracion_tareas`
  MODIFY `id_adm_tarea` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `camas`
--
ALTER TABLE `camas`
  MODIFY `id_cama` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `cocina`
--
ALTER TABLE `cocina`
  MODIFY `id_cocina` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `controles_clinicos`
--
ALTER TABLE `controles_clinicos`
  MODIFY `id_control` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estados_catalogo`
--
ALTER TABLE `estados_catalogo`
  MODIFY `id_estado` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  MODIFY `id_habitacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  MODIFY `id_historial` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT de la tabla `incidentes`
--
ALTER TABLE `incidentes`
  MODIFY `id_incidente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `medicacion_residente`
--
ALTER TABLE `medicacion_residente`
  MODIFY `id_plan` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  MODIFY `id_medicamento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `recetas_medicas`
--
ALTER TABLE `recetas_medicas`
  MODIFY `id_receta` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `residentes`
--
ALTER TABLE `residentes`
  MODIFY `id_residente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tareas_residente`
--
ALTER TABLE `tareas_residente`
  MODIFY `id_tarea` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `traspaso_turno`
--
ALTER TABLE `traspaso_turno`
  MODIFY `id_traspaso` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `turnos_programados`
--
ALTER TABLE `turnos_programados`
  MODIFY `id_turno_prog` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `visitas`
--
ALTER TABLE `visitas`
  MODIFY `id_visita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `actividades`
--
ALTER TABLE `actividades`
  ADD CONSTRAINT `fk_act_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_act_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_act_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `actividad_residentes`
--
ALTER TABLE `actividad_residentes`
  ADD CONSTRAINT `fk_ar_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `actividades` (`id_actividad`),
  ADD CONSTRAINT `fk_ar_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`);

--
-- Filtros para la tabla `administracion_medicacion`
--
ALTER TABLE `administracion_medicacion`
  ADD CONSTRAINT `fk_admmed_medicamento` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`),
  ADD CONSTRAINT `fk_admmed_plan` FOREIGN KEY (`id_plan`) REFERENCES `medicacion_residente` (`id_plan`),
  ADD CONSTRAINT `fk_admmed_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_admmed_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_admmed_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `administracion_tareas`
--
ALTER TABLE `administracion_tareas`
  ADD CONSTRAINT `fk_admtar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_admtar_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tareas_residente` (`id_tarea`),
  ADD CONSTRAINT `fk_admtar_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_admtar_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `camas`
--
ALTER TABLE `camas`
  ADD CONSTRAINT `fk_camas_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_camas_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`),
  ADD CONSTRAINT `fk_camas_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `cocina`
--
ALTER TABLE `cocina`
  ADD CONSTRAINT `fk_coc_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coc_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_coc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `controles_clinicos`
--
ALTER TABLE `controles_clinicos`
  ADD CONSTRAINT `fk_cc_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_cc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `habitaciones`
--
ALTER TABLE `habitaciones`
  ADD CONSTRAINT `fk_hab_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_hab_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `historial_estados`
--
ALTER TABLE `historial_estados`
  ADD CONSTRAINT `fk_historial_cambiado_por` FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `incidentes`
--
ALTER TABLE `incidentes`
  ADD CONSTRAINT `fk_inc_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_inc_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `medicacion_residente`
--
ALTER TABLE `medicacion_residente`
  ADD CONSTRAINT `fk_medres_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_medres_medicamento` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`),
  ADD CONSTRAINT `fk_medres_receta` FOREIGN KEY (`id_receta`) REFERENCES `recetas_medicas` (`id_receta`),
  ADD CONSTRAINT `fk_medres_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_medres_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `medicamentos`
--
ALTER TABLE `medicamentos`
  ADD CONSTRAINT `fk_med_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_med_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `recetas_medicas`
--
ALTER TABLE `recetas_medicas`
  ADD CONSTRAINT `fk_receta_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_receta_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_receta_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `residentes`
--
ALTER TABLE `residentes`
  ADD CONSTRAINT `fk_res_cama` FOREIGN KEY (`id_cama`) REFERENCES `camas` (`id_cama`),
  ADD CONSTRAINT `fk_res_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_res_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`),
  ADD CONSTRAINT `fk_res_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `tareas_residente`
--
ALTER TABLE `tareas_residente`
  ADD CONSTRAINT `fk_tar_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_tar_turno_prog` FOREIGN KEY (`id_turno_prog`) REFERENCES `turnos_programados` (`id_turno_prog`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tar_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `traspaso_turno`
--
ALTER TABLE `traspaso_turno`
  ADD CONSTRAINT `fk_tt_entrante` FOREIGN KEY (`id_usuario_entrante`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_tt_saliente` FOREIGN KEY (`id_usuario_saliente`) REFERENCES `usuarios` (`id_usuario`),
  ADD CONSTRAINT `fk_tt_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `turnos_programados`
--
ALTER TABLE `turnos_programados`
  ADD CONSTRAINT `fk_tp_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tp_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_usuarios_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL;

--
-- Filtros para la tabla `visitas`
--
ALTER TABLE `visitas`
  ADD CONSTRAINT `fk_vis_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_vis_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  ADD CONSTRAINT `fk_vis_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
