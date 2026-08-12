-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: gesthogar
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `actividad_residentes`
--

DROP TABLE IF EXISTS `actividad_residentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `actividad_residentes` (
  `id_actividad_residente` int(11) NOT NULL AUTO_INCREMENT,
  `id_actividad` int(11) NOT NULL,
  `id_residente` int(11) NOT NULL,
  `observacion_participacion` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_actividad_residente`),
  KEY `fk_ar_actividad` (`id_actividad`),
  KEY `fk_ar_residente` (`id_residente`),
  KEY `fk_ar_created_by` (`created_by`),
  CONSTRAINT `fk_ar_actividad` FOREIGN KEY (`id_actividad`) REFERENCES `actividades` (`id_actividad`),
  CONSTRAINT `fk_ar_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_ar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actividad_residentes`
--

LOCK TABLES `actividad_residentes` WRITE;
/*!40000 ALTER TABLE `actividad_residentes` DISABLE KEYS */;
INSERT INTO `actividad_residentes` VALUES (1,1,1,'Participó activamente, de buen ánimo',2,'2026-06-26 20:06:14'),(2,1,2,'Participó parcialmente',2,'2026-06-26 20:06:14'),(3,2,1,'Completó la caminata sin problemas',3,'2026-06-26 20:06:14'),(4,2,3,'Necesitó descanso a mitad de camino',3,'2026-06-26 20:06:14');
/*!40000 ALTER TABLE `actividad_residentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `actividades`
--

DROP TABLE IF EXISTS `actividades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `actividades` (
  `id_actividad` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_actividad`),
  KEY `fk_act_usuario` (`id_usuario`),
  KEY `fk_act_created_by` (`created_by`),
  KEY `fk_act_updated_by` (`updated_by`),
  CONSTRAINT `fk_act_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_act_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `actividades`
--

LOCK TABLES `actividades` WRITE;
/*!40000 ALTER TABLE `actividades` DISABLE KEYS */;
INSERT INTO `actividades` VALUES (1,2,'Taller de manualidades','Armado de tarjetas con materiales reciclados','2026-06-18',NULL,'Buena participación general','activo',2,'2026-06-26 20:06:14',NULL,NULL),(2,3,'Caminata en el patio','Caminata asistida de 15 minutos','2026-06-20',NULL,'Todos participaron sin inconvenientes','activo',3,'2026-06-26 20:06:14',NULL,NULL);
/*!40000 ALTER TABLE `actividades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administracion_medicacion`
--

DROP TABLE IF EXISTS `administracion_medicacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administracion_medicacion` (
  `id_adm` int(11) NOT NULL AUTO_INCREMENT,
  `id_plan` int(11) DEFAULT NULL,
  `id_residente` int(11) DEFAULT NULL,
  `id_medicamento` int(11) DEFAULT NULL,
  `id_cuidadora_usuario` int(11) DEFAULT NULL COMMENT 'FK directo a usuarios',
  `fecha_hora` datetime DEFAULT NULL,
  `estado_adm` enum('pendiente','administrado','no_pudo','no_dado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_adm`),
  KEY `fk_admmed_plan` (`id_plan`),
  KEY `fk_admmed_residente` (`id_residente`),
  KEY `fk_admmed_medicamento` (`id_medicamento`),
  KEY `fk_admmed_usuario` (`id_cuidadora_usuario`),
  KEY `fk_admmed_updated_by` (`updated_by`),
  CONSTRAINT `fk_admmed_medicamento` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`),
  CONSTRAINT `fk_admmed_plan` FOREIGN KEY (`id_plan`) REFERENCES `medicacion_residente` (`id_plan`),
  CONSTRAINT `fk_admmed_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_admmed_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_admmed_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administracion_medicacion`
--

LOCK TABLES `administracion_medicacion` WRITE;
/*!40000 ALTER TABLE `administracion_medicacion` DISABLE KEYS */;
INSERT INTO `administracion_medicacion` VALUES (1,1,1,1,2,'2026-06-12 08:00:00','administrado','','2026-06-26 20:12:55',NULL,NULL),(2,2,1,1,3,'2026-06-12 20:00:00','administrado','','2026-06-26 20:12:55',NULL,NULL),(3,3,1,4,2,'2026-06-13 07:30:00','no_pudo','Residente dormida, se avisó a Carla','2026-06-26 20:12:55',NULL,NULL),(4,4,2,2,4,'2026-06-14 08:00:00','administrado','','2026-06-26 20:12:55',NULL,NULL),(5,5,2,2,5,'2026-06-15 14:00:00','administrado','','2026-06-26 20:12:55',NULL,NULL),(6,7,3,3,2,'2026-06-16 08:00:00','no_dado','No presentó fiebre, se registró por indicación','2026-06-26 20:12:55',NULL,NULL),(7,7,3,3,1,'2026-06-22 21:50:15','administrado','','2026-06-26 20:12:55',NULL,NULL);
/*!40000 ALTER TABLE `administracion_medicacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `administracion_tareas`
--

DROP TABLE IF EXISTS `administracion_tareas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `administracion_tareas` (
  `id_adm_tarea` int(11) NOT NULL AUTO_INCREMENT,
  `id_tarea` int(11) DEFAULT NULL,
  `id_residente` int(11) DEFAULT NULL,
  `id_cuidadora_usuario` int(11) DEFAULT NULL COMMENT 'FK directo a usuarios',
  `fecha_hora` datetime DEFAULT NULL,
  `estado_tarea` enum('pendiente','realizada','no_realizada','no_pudo') DEFAULT 'pendiente',
  `motivo` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_adm_tarea`),
  KEY `fk_admtar_tarea` (`id_tarea`),
  KEY `fk_admtar_residente` (`id_residente`),
  KEY `fk_admtar_usuario` (`id_cuidadora_usuario`),
  KEY `fk_admtar_updated_by` (`updated_by`),
  CONSTRAINT `fk_admtar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_admtar_tarea` FOREIGN KEY (`id_tarea`) REFERENCES `tareas_residente` (`id_tarea`),
  CONSTRAINT `fk_admtar_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_admtar_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `administracion_tareas`
--

LOCK TABLES `administracion_tareas` WRITE;
/*!40000 ALTER TABLE `administracion_tareas` DISABLE KEYS */;
INSERT INTO `administracion_tareas` VALUES (1,1,1,1,'2026-07-30 16:19:02','realizada','','2026-07-30 19:19:02',NULL,NULL),(2,1,1,1,'2026-08-11 15:38:17','realizada','','2026-08-11 18:38:17',NULL,NULL),(3,3,3,1,'2026-08-11 15:42:55','realizada','','2026-08-11 18:42:55',NULL,NULL);
/*!40000 ALTER TABLE `administracion_tareas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `camas`
--

DROP TABLE IF EXISTS `camas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `camas` (
  `id_cama` int(11) NOT NULL AUTO_INCREMENT,
  `id_habitacion` int(11) NOT NULL,
  `identificador` varchar(20) NOT NULL COMMENT 'Ej: Cama A, Cama B, Cama 1',
  `estado` enum('disponible','ocupada','mantenimiento') DEFAULT 'disponible',
  `observaciones` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cama`),
  KEY `fk_camas_habitacion` (`id_habitacion`),
  KEY `fk_camas_created_by` (`created_by`),
  KEY `fk_camas_updated_by` (`updated_by`),
  CONSTRAINT `fk_camas_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_camas_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`),
  CONSTRAINT `fk_camas_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `camas`
--

LOCK TABLES `camas` WRITE;
/*!40000 ALTER TABLE `camas` DISABLE KEYS */;
INSERT INTO `camas` VALUES (1,1,'Cama A','ocupada',NULL,1,'2026-06-26 20:06:14',1,'2026-08-11 17:39:51'),(2,2,'Cama A','ocupada',NULL,1,'2026-06-26 20:06:14',NULL,NULL),(3,3,'Cama A','disponible',NULL,1,'2026-06-26 20:06:14',NULL,NULL),(4,4,'Cama A','disponible',NULL,1,'2026-06-26 20:06:14',1,'2026-08-11 18:22:04'),(5,5,'Cama A','ocupada',NULL,1,'2026-06-26 20:06:14',1,'2026-08-11 17:29:09'),(6,1,'Cama B','disponible',NULL,NULL,'2026-06-26 20:12:55',NULL,'2026-08-11 17:42:29'),(7,2,'Cama B','disponible',NULL,NULL,'2026-06-26 20:12:55',NULL,'2026-08-11 17:42:29'),(8,3,'Cama B','disponible',NULL,NULL,'2026-06-26 20:12:55',NULL,'2026-06-29 20:26:32'),(9,4,'Cama B','disponible',NULL,NULL,'2026-06-26 20:12:55',1,'2026-08-11 18:21:59'),(10,5,'Cama B','disponible',NULL,NULL,'2026-06-26 20:12:55',NULL,'2026-06-29 20:26:42');
/*!40000 ALTER TABLE `camas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cocina`
--

DROP TABLE IF EXISTS `cocina`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cocina` (
  `id_cocina` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_cocina`),
  KEY `fk_coc_usuario` (`id_usuario`),
  KEY `fk_coc_created_by` (`created_by`),
  KEY `fk_coc_updated_by` (`updated_by`),
  CONSTRAINT `fk_coc_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_coc_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_coc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cocina`
--

LOCK TABLES `cocina` WRITE;
/*!40000 ALTER TABLE `cocina` DISABLE KEYS */;
INSERT INTO `cocina` VALUES (1,6,'receta','Sopa de verduras','Zapallo, zanahoria, papa y caldo liviano',NULL,'uploads/cocina/ejemplo_cocina.jpg','2026-06-18','activo',6,'2026-06-26 20:06:14',NULL,NULL),(2,6,'gasto','Compra verduleria','Verduras para cena y almuerzo',8500.00,'uploads/cocina/ejemplo_cocina.jpg','2026-06-18','activo',6,'2026-06-26 20:06:14',NULL,'2026-06-26 20:12:55');
/*!40000 ALTER TABLE `cocina` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `controles_clinicos`
--

DROP TABLE IF EXISTS `controles_clinicos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `controles_clinicos` (
  `id_control` int(11) NOT NULL AUTO_INCREMENT,
  `id_residente` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Quien realizó el control',
  `tipo_control` enum('tension_sistolica','tension_diastolica','temperatura','peso','glucemia','saturacion_oxigeno','frecuencia_cardiaca') NOT NULL,
  `valor` decimal(6,2) NOT NULL,
  `unidad` varchar(20) NOT NULL COMMENT 'mmHg, C, kg, mg/dL, %, lpm',
  `observaciones` text DEFAULT NULL,
  `fecha_hora` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_control`),
  KEY `fk_cc_residente` (`id_residente`),
  KEY `fk_cc_usuario` (`id_usuario`),
  KEY `idx_cc_residente_tipo_fecha` (`id_residente`,`tipo_control`,`fecha_hora`),
  CONSTRAINT `fk_cc_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_cc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `controles_clinicos`
--

LOCK TABLES `controles_clinicos` WRITE;
/*!40000 ALTER TABLE `controles_clinicos` DISABLE KEYS */;
/*!40000 ALTER TABLE `controles_clinicos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados_catalogo`
--

DROP TABLE IF EXISTS `estados_catalogo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados_catalogo` (
  `id_estado` int(11) NOT NULL AUTO_INCREMENT,
  `entidad` varchar(50) NOT NULL COMMENT 'residente, usuario, visita, etc.',
  `nombre_estado` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id_estado`),
  UNIQUE KEY `uq_entidad_estado` (`entidad`,`nombre_estado`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados_catalogo`
--

LOCK TABLES `estados_catalogo` WRITE;
/*!40000 ALTER TABLE `estados_catalogo` DISABLE KEYS */;
INSERT INTO `estados_catalogo` VALUES (1,'residente','activo','El residente se encuentra actualmente en el hogar'),(2,'residente','pausado','El residente está temporalmente ausente (ej: hospitalizado)'),(3,'residente','baja','El residente dejó el hogar de forma definitiva'),(4,'usuario','activo','El usuario puede iniciar sesión en el sistema'),(5,'usuario','pausado','El usuario está suspendido temporalmente'),(6,'usuario','baja','El usuario fue dado de baja del sistema'),(7,'medicacion_adm','pendiente','La medicación aún no fue registrada en este turno'),(8,'medicacion_adm','administrado','La medicación fue administrada correctamente'),(9,'medicacion_adm','no_pudo','La cuidadora no pudo administrar la medicación'),(10,'medicacion_adm','no_dado','La medicación no fue administrada en este turno'),(11,'incidente','pendiente','Incidente registrado, pendiente de revisión por el responsable'),(12,'incidente','revisado','El incidente fue revisado y tomado en cuenta'),(13,'tarea_adm','pendiente','La tarea aún no fue registrada en este turno'),(14,'tarea_adm','realizada','La tarea fue realizada correctamente'),(15,'tarea_adm','no_realizada','La tarea no fue realizada en este turno'),(16,'tarea_adm','no_pudo','La cuidadora no pudo realizar la tarea'),(17,'visita','en_curso','El visitante se encuentra actualmente en el hogar'),(18,'visita','finalizada','La visita fue finalizada y el visitante se retiró'),(19,'traspaso','pendiente','El traspaso fue registrado, esperando confirmación del entrante'),(20,'traspaso','cerrado','El traspaso fue confirmado y cerrado'),(21,'cocina','activo','Registro activo'),(22,'cocina','baja','Registro dado de baja');
/*!40000 ALTER TABLE `estados_catalogo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `habitaciones`
--

DROP TABLE IF EXISTS `habitaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `habitaciones` (
  `id_habitacion` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_habitacion`),
  KEY `fk_hab_created_by` (`created_by`),
  KEY `fk_hab_updated_by` (`updated_by`),
  CONSTRAINT `fk_hab_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_hab_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `habitaciones`
--

LOCK TABLES `habitaciones` WRITE;
/*!40000 ALTER TABLE `habitaciones` DISABLE KEYS */;
INSERT INTO `habitaciones` VALUES (1,'101',NULL,2,'Ala Norte','Planta baja','ocupada','Cerca del baño',1,'2026-06-26 20:06:14',1,'2026-08-11 17:34:10'),(2,'102',NULL,2,'Ala Norte','Planta baja','ocupada','Ventana al patio',1,'2026-06-26 20:06:14',1,'2026-08-11 17:34:13'),(3,'103',NULL,2,'Ala Sur','Planta baja','disponible','Lista para ingreso',1,'2026-06-26 20:06:14',1,'2026-08-11 18:34:43'),(4,'201',NULL,2,'Ala Norte','Primer piso','disponible','Habitación tranquila',1,'2026-06-26 20:06:14',1,'2026-08-11 18:31:21'),(5,'202',NULL,2,'Ala Sur','Primer piso','ocupada','Revisar luz',1,'2026-06-26 20:06:14',1,'2026-08-11 17:34:24');
/*!40000 ALTER TABLE `habitaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `historial_estados`
--

DROP TABLE IF EXISTS `historial_estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_estados` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `entidad` enum('usuario','residente') NOT NULL,
  `id_registro` int(11) NOT NULL COMMENT 'ID del usuario o residente afectado',
  `estado_anterior` varchar(30) NOT NULL,
  `estado_nuevo` varchar(30) NOT NULL,
  `cambiado_por` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `motivo` text DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  PRIMARY KEY (`id_historial`),
  KEY `fk_historial_cambiado_por` (`cambiado_por`),
  KEY `idx_historial_entidad_registro` (`entidad`,`id_registro`),
  CONSTRAINT `fk_historial_cambiado_por` FOREIGN KEY (`cambiado_por`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `historial_estados`
--

LOCK TABLES `historial_estados` WRITE;
/*!40000 ALTER TABLE `historial_estados` DISABLE KEYS */;
INSERT INTO `historial_estados` VALUES (1,'usuario',10,'activo','baja',1,'2026-06-24 18:30:25',NULL,NULL),(2,'usuario',8,'activo','baja',1,'2026-06-24 18:32:02',NULL,NULL),(3,'usuario',7,'activo','baja',1,'2026-06-24 19:50:12',NULL,NULL),(4,'usuario',10,'baja','activo',1,'2026-06-24 19:50:56',NULL,NULL),(5,'usuario',10,'activo','baja',1,'2026-06-24 19:51:21',NULL,NULL),(6,'usuario',7,'baja','activo',1,'2026-06-24 20:23:49',NULL,NULL),(7,'usuario',2,'activo','baja',1,'2026-06-24 23:04:15',NULL,NULL),(8,'usuario',2,'activo','baja',1,'2026-06-25 19:03:04',NULL,NULL),(9,'usuario',2,'activo','baja',1,'2026-06-25 19:06:19',NULL,NULL),(16,'usuario',8,'','activo',1,'2026-06-26 23:21:29',NULL,NULL),(17,'usuario',8,'activo','baja',1,'2026-06-26 23:21:57',NULL,NULL),(18,'usuario',8,'baja','activo',1,'2026-06-26 23:24:55',NULL,NULL),(19,'usuario',5,'activo','pausado',1,'2026-06-29 19:00:44',NULL,NULL),(20,'usuario',5,'pausado','activo',1,'2026-06-29 19:00:54',NULL,NULL),(21,'usuario',8,'activo','baja',1,'2026-06-29 19:01:00',NULL,NULL),(22,'usuario',7,'activo','baja',1,'2026-07-06 23:24:04',NULL,NULL),(23,'usuario',7,'baja','activo',1,'2026-07-06 23:24:28',NULL,NULL),(24,'residente',1,'activo','pausado',1,'2026-07-06 23:26:29',NULL,NULL),(25,'usuario',7,'activo','baja',1,'2026-07-06 23:37:50',NULL,NULL),(26,'usuario',7,'baja','activo',1,'2026-07-06 23:38:57',NULL,NULL),(27,'residente',3,'','servicio_suspendido',1,'2026-07-23 18:24:12','Internacion por neumonia',NULL),(28,'residente',1,'pausado','activo',1,'2026-07-23 18:34:37',NULL,NULL),(29,'usuario',7,'activo','inhabilitado',1,'2026-07-23 18:37:04',NULL,NULL),(30,'residente',3,'activo','servicio_suspendido',1,'2026-07-23 18:37:42','Internacion por neumonia',NULL),(31,'usuario',7,'activo','inhabilitado',1,'2026-07-23 18:45:24',NULL,NULL),(32,'usuario',7,'activo','inhabilitado',1,'2026-07-23 18:47:35',NULL,NULL),(33,'usuario',7,'activo','pausado',1,'2026-07-23 18:50:27',NULL,NULL),(34,'usuario',7,'pausado','activo',1,'2026-07-23 18:53:24',NULL,NULL),(35,'residente',1,'activo','pausado',1,'2026-07-23 19:04:53','Internacion por neumonia','2026-07-31'),(36,'residente',1,'pausado','activo',1,'2026-07-23 19:06:16',NULL,NULL),(37,'residente',1,'activo','pausado',1,'2026-07-23 19:06:44','Internacion por neumonia',NULL),(38,'residente',1,'pausado','activo',1,'2026-07-23 19:10:14',NULL,NULL),(39,'residente',1,'activo','baja',1,'2026-07-23 19:10:51',NULL,NULL),(40,'residente',1,'baja','activo',1,'2026-07-23 19:11:15',NULL,NULL),(41,'residente',1,'activo','baja',1,'2026-07-23 19:11:36',NULL,NULL),(42,'residente',1,'baja','activo',1,'2026-07-23 19:11:46',NULL,NULL),(43,'residente',4,'','activo',1,'2026-08-11 16:05:14',NULL,NULL),(44,'residente',4,'activo','baja',1,'2026-08-11 16:21:30',NULL,NULL),(45,'residente',5,'','activo',1,'2026-08-11 16:49:56',NULL,NULL),(46,'residente',6,'','activo',1,'2026-08-11 17:21:38',NULL,NULL),(47,'residente',6,'activo','baja',1,'2026-08-11 18:21:59',NULL,NULL),(48,'residente',3,'activo','baja',1,'2026-08-11 18:22:04',NULL,NULL),(49,'residente',3,'baja','activo',1,'2026-08-11 18:22:22',NULL,NULL);
/*!40000 ALTER TABLE `historial_estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidentes`
--

DROP TABLE IF EXISTS `incidentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidentes` (
  `id_incidente` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_incidente`),
  KEY `fk_inc_residente` (`id_residente`),
  KEY `fk_inc_usuario` (`id_usuario`),
  KEY `fk_inc_updated_by` (`updated_by`),
  CONSTRAINT `fk_inc_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_inc_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_inc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidentes`
--

LOCK TABLES `incidentes` WRITE;
/*!40000 ALTER TABLE `incidentes` DISABLE KEYS */;
INSERT INTO `incidentes` VALUES (1,2,2,'Carlos Perez sufrió un mareo leve al levantarse','Se lo ayudó a sentarse, se controló la presión','leve',NULL,'2026-06-19 09:15:00',0,'pendiente','2026-06-26 20:06:14',NULL,NULL),(2,1,3,'Rosa Gomez presentó tos persistente durante la noche','Se la incorporó, se le dio agua, mejoró a los 10 minutos','moderado',NULL,'2026-06-20 02:30:00',1,'pendiente','2026-06-26 20:06:14',NULL,NULL);
/*!40000 ALTER TABLE `incidentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicacion_residente`
--

DROP TABLE IF EXISTS `medicacion_residente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicacion_residente` (
  `id_plan` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_plan`),
  KEY `fk_medres_receta` (`id_receta`),
  KEY `fk_medres_residente` (`id_residente`),
  KEY `fk_medres_medicamento` (`id_medicamento`),
  KEY `fk_medres_created_by` (`created_by`),
  KEY `fk_medres_updated_by` (`updated_by`),
  CONSTRAINT `fk_medres_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_medres_medicamento` FOREIGN KEY (`id_medicamento`) REFERENCES `medicamentos` (`id_medicamento`),
  CONSTRAINT `fk_medres_receta` FOREIGN KEY (`id_receta`) REFERENCES `recetas_medicas` (`id_receta`),
  CONSTRAINT `fk_medres_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_medres_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicacion_residente`
--

LOCK TABLES `medicacion_residente` WRITE;
/*!40000 ALTER TABLE `medicacion_residente` DISABLE KEYS */;
INSERT INTO `medicacion_residente` VALUES (1,1,1,1,'1 comprimido','08:00:00','Después del desayuno','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',1,'2026-07-23 19:10:14'),(2,1,1,1,'1 comprimido','20:00:00','Después de la cena','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',1,'2026-07-23 19:10:14'),(3,2,1,4,'1 cápsula','07:30:00','Antes del desayuno','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',1,'2026-07-23 19:10:14'),(4,3,2,2,'1 comprimido','08:00:00','Después del desayuno','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(5,3,2,2,'1 comprimido','14:00:00','Después del almuerzo','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(6,3,2,2,'1 comprimido','21:00:00','Después de la cena','2026-06-01',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(7,4,3,3,'1 comprimido','08:00:00','Si tiene fiebre o dolor','2026-06-14','2026-06-23','activo',1,'2026-06-26 20:06:14',1,'2026-08-11 18:22:22'),(8,4,3,3,'1 comprimido','16:00:00','Repetir si corresponde','2026-06-14','2026-06-23','activo',1,'2026-06-26 20:06:14',1,'2026-08-11 18:22:22'),(9,5,1,5,'1 comprimido','08:00:00','','2026-06-29',NULL,'activo',1,'2026-06-29 20:18:24',1,'2026-07-23 19:10:14'),(10,5,1,5,'1 comprimido','16:00:00','','2026-06-29',NULL,'activo',1,'2026-06-29 20:18:24',1,'2026-07-23 19:10:14');
/*!40000 ALTER TABLE `medicacion_residente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicamentos`
--

DROP TABLE IF EXISTS `medicamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medicamentos` (
  `id_medicamento` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` text NOT NULL,
  `presentacion` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_medicamento`),
  KEY `fk_med_created_by` (`created_by`),
  KEY `fk_med_updated_by` (`updated_by`),
  CONSTRAINT `fk_med_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_med_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicamentos`
--

LOCK TABLES `medicamentos` WRITE;
/*!40000 ALTER TABLE `medicamentos` DISABLE KEYS */;
INSERT INTO `medicamentos` VALUES (1,'Losartan','Comprimido 50mg','Presión arterial','activo',1,'2026-06-26 20:06:14',NULL,NULL),(2,'Metformina','Comprimido 850mg','Diabetes','activo',1,'2026-06-26 20:06:14',NULL,NULL),(3,'Paracetamol','Comprimido 500mg','Dolor o fiebre','activo',1,'2026-06-26 20:06:14',NULL,NULL),(4,'Omeprazol','Cápsula 20mg','Protector gástrico','activo',1,'2026-06-26 20:06:14',NULL,NULL),(5,'Ibuprofeno','10mg','','activo',1,'2026-06-29 20:17:30',NULL,NULL);
/*!40000 ALTER TABLE `medicamentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recetas_medicas`
--

DROP TABLE IF EXISTS `recetas_medicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recetas_medicas` (
  `id_receta` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_receta`),
  KEY `fk_receta_residente` (`id_residente`),
  KEY `fk_receta_created_by` (`created_by`),
  KEY `fk_receta_updated_by` (`updated_by`),
  CONSTRAINT `fk_receta_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_receta_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_receta_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recetas_medicas`
--

LOCK TABLES `recetas_medicas` WRITE;
/*!40000 ALTER TABLE `recetas_medicas` DISABLE KEYS */;
INSERT INTO `recetas_medicas` VALUES (1,1,'Tratamiento permanente de presión','Control diario de presión arterial.','2026-06-01',NULL,NULL,'activa',1,'2026-06-26 20:06:14',NULL,NULL),(2,1,'Protector gástrico permanente','Administrar antes del desayuno.','2026-06-01',NULL,NULL,'activa',1,'2026-06-26 20:06:14',NULL,NULL),(3,2,'Tratamiento permanente de diabetes','Controlar alimentación y horarios.','2026-06-01',NULL,NULL,'activa',1,'2026-06-26 20:06:14',NULL,NULL),(4,3,'Tratamiento temporal por gripe','Solo durante el período indicado.','2026-06-14',NULL,NULL,'activa',1,'2026-06-26 20:06:14',NULL,NULL),(5,1,'Dolor de cabeza','','2026-06-29',NULL,NULL,'activa',1,'2026-06-29 20:18:24',NULL,NULL);
/*!40000 ALTER TABLE `recetas_medicas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `residentes`
--

DROP TABLE IF EXISTS `residentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `residentes` (
  `id_residente` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `token_visita` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id_residente`),
  UNIQUE KEY `dni` (`dni`),
  KEY `fk_res_habitacion` (`id_habitacion`),
  KEY `fk_res_cama` (`id_cama`),
  KEY `fk_res_created_by` (`created_by`),
  KEY `fk_res_updated_by` (`updated_by`),
  CONSTRAINT `fk_res_cama` FOREIGN KEY (`id_cama`) REFERENCES `camas` (`id_cama`),
  CONSTRAINT `fk_res_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_res_habitacion` FOREIGN KEY (`id_habitacion`) REFERENCES `habitaciones` (`id_habitacion`),
  CONSTRAINT `fk_res_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `residentes`
--

LOCK TABLES `residentes` WRITE;
/*!40000 ALTER TABLE `residentes` DISABLE KEYS */;
INSERT INTO `residentes` VALUES (1,'Rosa','Gomez','22345678','','1940-03-12','2026-01-10',NULL,1,0,'Laura Gomez','351555111',1,1,'uploads/residentes/ejemplo_residente.jpg','activo',1,'2026-06-26 20:06:14',1,'2026-08-11 17:39:51','26ed78df70d7df5db0cf4a091166db0f'),(2,'Carlos','Perez','18765432',NULL,'1938-07-20','2026-01-15',NULL,1,0,'Juan Perez','351555222',2,2,'uploads/residentes/ejemplo_residente.jpg','activo',1,'2026-06-26 20:06:14',NULL,'2026-08-11 15:37:39','6b2699dcde144da3a5f192d8cb84aeee'),(3,'Elena','Ruiz','20555111',NULL,'1942-11-05','2026-02-01',NULL,1,0,'Marcela Ruiz','351555333',4,4,'uploads/residentes/ejemplo_residente.jpg','activo',1,'2026-06-26 20:06:14',1,'2026-08-11 18:22:22','4ae9a22d0d9b9b946beeb67200b6d479'),(4,'Juana','Suarex','4596221','','1945-03-01','2026-08-11','2026-08-11',1,1,'camila graziani','3571592061',1,1,'','baja',1,'2026-08-11 16:05:14',1,'2026-08-11 16:21:30',NULL),(5,'Juana','Suarez','3842000','','1945-03-01','2026-08-11',NULL,1,1,'camila graziani','03571592061',5,5,'','activo',1,'2026-08-11 16:49:56',1,'2026-08-11 17:29:09','d8160e88d5178cfede0fa78b1d4523a8'),(6,'Domingo','Villarreal','4596222','','1955-05-30','2026-08-11','2026-08-11',1,0,'camila graziani','03571592061',4,9,'','baja',1,'2026-08-11 17:21:38',1,'2026-08-11 18:21:59','fc4547b2fc8e4a6122f847de68402a91');
/*!40000 ALTER TABLE `residentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tareas_residente`
--

DROP TABLE IF EXISTS `tareas_residente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tareas_residente` (
  `id_tarea` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_tarea`),
  KEY `fk_tar_residente` (`id_residente`),
  KEY `fk_tar_turno_prog` (`id_turno_prog`),
  KEY `fk_tar_created_by` (`created_by`),
  KEY `fk_tar_updated_by` (`updated_by`),
  CONSTRAINT `fk_tar_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_tar_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_tar_turno_prog` FOREIGN KEY (`id_turno_prog`) REFERENCES `turnos_programados` (`id_turno_prog`) ON DELETE SET NULL,
  CONSTRAINT `fk_tar_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tareas_residente`
--

LOCK TABLES `tareas_residente` WRITE;
/*!40000 ALTER TABLE `tareas_residente` DISABLE KEYS */;
INSERT INTO `tareas_residente` VALUES (1,1,'Controlar presion','Tomar presión y registrar resultado','fija',NULL,'10:00:00','activo',1,'2026-06-26 20:06:14',NULL,'2026-06-26 20:12:55'),(2,2,'Acompanamiento caminata','Acompañarlo 10 minutos por el patio','fija',NULL,'17:00:00','activo',1,'2026-06-26 20:06:14',NULL,'2026-06-26 20:12:55'),(3,3,'Hidratacion','Ofrecer agua y registrar si aceptó','fija',NULL,'11:00:00','activo',1,'2026-06-26 20:06:14',NULL,'2026-06-26 20:12:55');
/*!40000 ALTER TABLE `tareas_residente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `traspaso_turno`
--

DROP TABLE IF EXISTS `traspaso_turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `traspaso_turno` (
  `id_traspaso` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario_saliente` int(11) NOT NULL,
  `id_usuario_entrante` int(11) DEFAULT NULL,
  `novedades` text NOT NULL,
  `fecha_cierre` datetime NOT NULL,
  `fecha_apertura` datetime DEFAULT NULL,
  `estado` enum('pendiente','cerrado') DEFAULT 'pendiente',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_traspaso`),
  KEY `fk_tt_saliente` (`id_usuario_saliente`),
  KEY `fk_tt_entrante` (`id_usuario_entrante`),
  KEY `fk_tt_updated_by` (`updated_by`),
  CONSTRAINT `fk_tt_entrante` FOREIGN KEY (`id_usuario_entrante`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_tt_saliente` FOREIGN KEY (`id_usuario_saliente`) REFERENCES `usuarios` (`id_usuario`),
  CONSTRAINT `fk_tt_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `traspaso_turno`
--

LOCK TABLES `traspaso_turno` WRITE;
/*!40000 ALTER TABLE `traspaso_turno` DISABLE KEYS */;
INSERT INTO `traspaso_turno` VALUES (1,2,3,'Turno tranquilo. Rosa tomó toda su medicación. Carlos tuvo visita de su hijo. Sin incidentes.','2026-06-20 08:00:00','2026-06-20 08:05:00','pendiente','2026-06-26 20:06:14',NULL,'2026-06-26 20:12:55'),(2,3,4,'Elena Ruiz con visita de su hija por la tarde. Resto de residentes sin novedades.','2026-06-21 08:00:00',NULL,'pendiente','2026-06-26 20:06:14',NULL,NULL);
/*!40000 ALTER TABLE `traspaso_turno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turnos_programados`
--

DROP TABLE IF EXISTS `turnos_programados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turnos_programados` (
  `id_turno_prog` int(11) NOT NULL AUTO_INCREMENT,
  `id_cuidadora_usuario` int(11) NOT NULL COMMENT 'FK directo a usuarios',
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `nota` text DEFAULT NULL,
  `estado` enum('activo','pausado','baja') DEFAULT 'activo',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_turno_prog`),
  KEY `fk_tp_usuario` (`id_cuidadora_usuario`),
  KEY `fk_tp_created_by` (`created_by`),
  KEY `fk_tp_updated_by` (`updated_by`),
  CONSTRAINT `fk_tp_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_tp_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_tp_usuario` FOREIGN KEY (`id_cuidadora_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=96 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turnos_programados`
--

LOCK TABLES `turnos_programados` WRITE;
/*!40000 ALTER TABLE `turnos_programados` DISABLE KEYS */;
INSERT INTO `turnos_programados` VALUES (1,2,'2026-06-11','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(2,3,'2026-06-11','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(3,6,'2026-06-11','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(4,6,'2026-06-11','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(5,7,'2026-06-11','10:00:00','18:00:00','Asistente Paula','activo',1,'2026-06-19 01:09:12',NULL,NULL),(6,3,'2026-06-12','08:00:00','20:00:00','Turno diurno Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(7,4,'2026-06-12','20:00:00','08:00:00','Turno noche Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(8,5,'2026-06-12','14:00:00','22:00:00','Refuerzo tarde Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(9,6,'2026-06-12','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(10,6,'2026-06-12','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(11,4,'2026-06-13','08:00:00','20:00:00','Turno diurno Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(12,5,'2026-06-13','20:00:00','08:00:00','Turno noche Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(13,6,'2026-06-13','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(14,6,'2026-06-13','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(15,5,'2026-06-14','08:00:00','20:00:00','Turno diurno Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(16,2,'2026-06-14','20:00:00','08:00:00','Turno noche Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(17,6,'2026-06-14','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(18,6,'2026-06-14','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(19,2,'2026-06-15','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(20,3,'2026-06-15','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(21,4,'2026-06-15','14:00:00','22:00:00','Refuerzo tarde Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(22,6,'2026-06-15','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(23,6,'2026-06-15','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(24,7,'2026-06-15','10:00:00','18:00:00','Asistente Paula','activo',1,'2026-06-19 01:09:12',NULL,NULL),(25,3,'2026-06-16','08:00:00','20:00:00','Turno diurno Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(26,4,'2026-06-16','20:00:00','08:00:00','Turno noche Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(27,6,'2026-06-16','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(28,6,'2026-06-16','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(29,4,'2026-06-17','08:00:00','20:00:00','Turno diurno Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(30,5,'2026-06-17','20:00:00','08:00:00','Turno noche Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(31,6,'2026-06-17','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(32,6,'2026-06-17','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(33,5,'2026-06-18','08:00:00','20:00:00','Turno diurno Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(34,2,'2026-06-18','20:00:00','08:00:00','Turno noche Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(35,3,'2026-06-18','14:00:00','22:00:00','Refuerzo tarde Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(36,6,'2026-06-18','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(37,6,'2026-06-18','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(38,7,'2026-06-18','10:00:00','18:00:00','Asistente Paula','activo',1,'2026-06-19 01:09:12',NULL,NULL),(39,2,'2026-06-19','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(40,3,'2026-06-19','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(41,6,'2026-06-19','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(42,6,'2026-06-19','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(43,3,'2026-06-20','08:00:00','20:00:00','Turno diurno Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(44,4,'2026-06-20','20:00:00','08:00:00','Turno noche Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(45,6,'2026-06-20','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(46,6,'2026-06-20','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(47,4,'2026-06-21','08:00:00','20:00:00','Turno diurno Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(48,5,'2026-06-21','20:00:00','08:00:00','Turno noche Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(49,2,'2026-06-21','14:00:00','22:00:00','Refuerzo tarde Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(50,6,'2026-06-21','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(51,6,'2026-06-21','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(52,5,'2026-06-22','08:00:00','20:00:00','Turno diurno Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(53,2,'2026-06-22','20:00:00','08:00:00','Turno noche Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(54,6,'2026-06-22','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(55,6,'2026-06-22','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(56,7,'2026-06-22','10:00:00','18:00:00','Asistente Paula','activo',1,'2026-06-19 01:09:12',NULL,NULL),(57,2,'2026-06-23','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(58,3,'2026-06-23','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(59,6,'2026-06-23','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(60,6,'2026-06-23','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(61,3,'2026-06-24','08:00:00','20:00:00','Turno diurno Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(62,4,'2026-06-24','20:00:00','08:00:00','Turno noche Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(63,5,'2026-06-24','14:00:00','22:00:00','Refuerzo tarde Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(64,6,'2026-06-24','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(65,6,'2026-06-24','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(66,4,'2026-06-25','08:00:00','20:00:00','Turno diurno Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(67,5,'2026-06-25','20:00:00','08:00:00','Turno noche Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(68,6,'2026-06-25','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(69,6,'2026-06-25','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(70,5,'2026-06-26','08:00:00','20:00:00','Turno diurno Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(71,2,'2026-06-26','20:00:00','08:00:00','Turno noche Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(72,6,'2026-06-26','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(73,6,'2026-06-26','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(74,2,'2026-06-27','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(75,3,'2026-06-27','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(76,4,'2026-06-27','14:00:00','22:00:00','Refuerzo tarde Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(77,6,'2026-06-27','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(78,6,'2026-06-27','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(79,3,'2026-06-28','08:00:00','20:00:00','Turno diurno Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(80,4,'2026-06-28','20:00:00','08:00:00','Turno noche Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(81,6,'2026-06-28','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(82,6,'2026-06-28','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(83,4,'2026-06-29','08:00:00','20:00:00','Turno diurno Laura','activo',1,'2026-06-19 01:09:12',NULL,NULL),(84,5,'2026-06-29','20:00:00','08:00:00','Turno noche Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(85,6,'2026-06-29','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(86,6,'2026-06-29','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(87,5,'2026-06-30','08:00:00','20:00:00','Turno diurno Daniela','activo',1,'2026-06-19 01:09:12',NULL,NULL),(88,2,'2026-06-30','20:00:00','08:00:00','Turno noche Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(89,3,'2026-06-30','14:00:00','22:00:00','Refuerzo tarde Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(90,6,'2026-06-30','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(91,6,'2026-06-30','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(92,2,'2026-07-01','08:00:00','20:00:00','Turno diurno Ana','activo',1,'2026-06-19 01:09:12',NULL,NULL),(93,3,'2026-07-01','20:00:00','08:00:00','Turno noche Sofia','activo',1,'2026-06-19 01:09:12',NULL,NULL),(94,6,'2026-07-01','09:00:00','13:00:00','Cocina almuerzo Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL),(95,6,'2026-07-01','18:00:00','21:00:00','Cocina cena Marta','activo',1,'2026-06-19 01:09:12',NULL,NULL);
/*!40000 ALTER TABLE `turnos_programados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_usuarios_created_by` (`created_by`),
  KEY `fk_usuarios_updated_by` (`updated_by`),
  CONSTRAINT `fk_usuarios_created_by` FOREIGN KEY (`created_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_usuarios_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Carla','Administradora','admin@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','admin',NULL,NULL,NULL,'#0f2f4a',NULL,'activo',NULL,'2026-06-26 20:06:14',NULL,NULL),(2,'Ana','Lopez','ana@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cuidadora','30111222','351555333','24h/48h','#e74c3c',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(3,'Sofia','Martinez','sofia@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cuidadora','32777888','351555444','12h diurno','#8e44ad',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(4,'Laura','Fernandez','laura@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cuidadora','33555999','351555666','24h/48h','#2980b9',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(5,'Daniela','Acosta','daniela@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cuidadora','34666112','351555777','Refuerzo tarde','#f39c12',NULL,'activo',1,'2026-06-26 20:06:14',1,'2026-06-29 19:00:54'),(6,'Marta','Cocinera','marta@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','cocinera',NULL,NULL,NULL,'#087f7a',NULL,'activo',1,'2026-06-26 20:06:14',NULL,NULL),(7,'Paula','Asistente','paula@gesthogar.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','asistente','18765432','3571592062','Nocturno','#16a085',NULL,'activo',1,'2026-06-26 20:06:14',1,'2026-07-23 18:53:24'),(8,'camila','graziani','camigraziani12@gmail.com','$2y$10$JPMROeUAgS5m2M54KG0kou9YzxtqP3k8idQ.KVnJuchLI/vuEVKNC','asistente',NULL,'03571592061',NULL,'#087f7a',NULL,'baja',1,'2026-06-26 23:21:29',1,'2026-06-29 19:01:00');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `visitas`
--

DROP TABLE IF EXISTS `visitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `visitas` (
  `id_visita` int(11) NOT NULL AUTO_INCREMENT,
  `id_residente` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre_visitante` varchar(150) NOT NULL,
  `apellido_visitante` varchar(100) DEFAULT NULL,
  `parentesco` varchar(60) NOT NULL,
  `telefono_visitante` varchar(50) DEFAULT NULL,
  `fecha_programada` datetime DEFAULT NULL,
  `hora_ingreso` datetime NOT NULL,
  `hora_egreso` datetime DEFAULT NULL,
  `cerrado_por` int(11) DEFAULT NULL COMMENT 'Quien registró el egreso',
  `observaciones` text DEFAULT NULL,
  `motivo_cancelacion` text DEFAULT NULL,
  `estado` enum('solicitada','agendada','en_curso','finalizada','rechazada','cancelada') DEFAULT 'solicitada',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `fecha_solicitada` datetime DEFAULT NULL,
  `motivo_rechazo` varchar(255) DEFAULT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_visita`),
  KEY `fk_vis_residente` (`id_residente`),
  KEY `fk_vis_usuario` (`id_usuario`),
  KEY `fk_vis_cerrado_por` (`cerrado_por`),
  CONSTRAINT `fk_vis_cerrado_por` FOREIGN KEY (`cerrado_por`) REFERENCES `usuarios` (`id_usuario`) ON DELETE SET NULL,
  CONSTRAINT `fk_vis_residente` FOREIGN KEY (`id_residente`) REFERENCES `residentes` (`id_residente`),
  CONSTRAINT `fk_vis_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `visitas`
--

LOCK TABLES `visitas` WRITE;
/*!40000 ALTER TABLE `visitas` DISABLE KEYS */;
INSERT INTO `visitas` VALUES (1,1,2,'Laura Gomez',NULL,'Hija',NULL,NULL,'2026-06-19 16:00:00','2026-06-19 17:30:00',2,'Visita tranquila, charlaron en el patio',NULL,'finalizada','2026-06-26 20:06:14',NULL,NULL,NULL,NULL),(2,2,3,'Juan Perez',NULL,'Hijo',NULL,NULL,'2026-06-20 11:00:00','2026-06-20 12:00:00',3,'',NULL,'finalizada','2026-06-26 20:06:14',NULL,NULL,NULL,NULL),(3,3,2,'Marcela Ruiz',NULL,'Hija',NULL,NULL,'2026-06-21 15:30:00','2026-07-29 16:38:34',1,'Visita en curso',NULL,'finalizada','2026-06-26 20:06:14','2026-07-29 19:38:34',NULL,NULL,NULL),(4,1,1,'Joaquina','Lopez','Hijo/a',NULL,'2026-07-29 16:43:00','2026-07-29 16:45:07','2026-07-29 16:59:37',1,NULL,NULL,'finalizada','2026-07-29 19:43:43','2026-07-29 19:59:37',NULL,NULL,NULL),(5,1,1,'Jose','Gomez','Cónyuge',NULL,NULL,'2026-07-29 16:59:21','2026-07-29 16:59:34',1,NULL,NULL,'finalizada','2026-07-29 19:59:21','2026-07-29 19:59:34',NULL,NULL,NULL),(7,2,NULL,'camila','graziani','Nieto/a','03571592061',NULL,'2026-08-12 19:00:00',NULL,NULL,NULL,NULL,'agendada','2026-08-11 16:03:20','2026-08-11 16:03:47','2026-08-11 13:03:20',NULL,1),(8,5,NULL,'camila','graziani','Nieto/a','03571592061',NULL,'2026-08-12 14:00:00',NULL,NULL,NULL,NULL,'solicitada','2026-08-11 16:52:34',NULL,'2026-08-11 13:52:34',NULL,NULL);
/*!40000 ALTER TABLE `visitas` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-13 16:29:12
