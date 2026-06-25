DROP DATABASE IF EXISTS `gesthogar_db`;
CREATE DATABASE `gesthogar_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `gesthogar_db`;

CREATE TABLE usuarios(
 id_usuario INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 email VARCHAR(120) UNIQUE NOT NULL,
 password VARCHAR(255) NOT NULL,
 rol ENUM('admin','cuidadora','cocinera','asistente') NOT NULL,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo',
 eliminado TINYINT DEFAULT 0,
 token_recuperacion VARCHAR(100),
 color VARCHAR(20) DEFAULT '#087f7a',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE habitaciones(
 id_habitacion INT AUTO_INCREMENT PRIMARY KEY,
 numero VARCHAR(20) NOT NULL,
 sector VARCHAR(80),
 piso VARCHAR(40),
 cama VARCHAR(40),
 estado ENUM('disponible','ocupada','mantenimiento') DEFAULT 'disponible',
 observaciones TEXT,
 eliminado TINYINT DEFAULT 0
);
CREATE TABLE residentes(
 id_residente INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL,
 apellido VARCHAR(100) NOT NULL,
 dni VARCHAR(20) UNIQUE NOT NULL,
 fecha_nacimiento DATE,
 movilidad_autonoma TINYINT DEFAULT 1,
 obs_medicas TEXT,
 contacto_familiar VARCHAR(120),
 telefono_familiar VARCHAR(50),
 id_habitacion INT NULL,
 foto VARCHAR(255),
 estado ENUM('activo','pausado','baja') DEFAULT 'activo',
 eliminado TINYINT DEFAULT 0,
 created_by INT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_habitacion) REFERENCES habitaciones(id_habitacion)
);
CREATE TABLE cuidadoras(
 id_cuidadora INT AUTO_INCREMENT PRIMARY KEY,
 id_usuario INT,
 nombre VARCHAR(100), apellido VARCHAR(100), dni VARCHAR(20), telefono VARCHAR(50), tipo_turno VARCHAR(50),
 estado ENUM('activo','pausado','baja') DEFAULT 'activo', eliminado TINYINT DEFAULT 0,
 FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario)
);
CREATE TABLE medicamentos(
 id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
 nombre VARCHAR(100) NOT NULL, presentacion VARCHAR(100), descripcion TEXT,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo', eliminado TINYINT DEFAULT 0
);
CREATE TABLE recetas_medicas(
 id_receta INT AUTO_INCREMENT PRIMARY KEY,
 id_residente INT NOT NULL,
 diagnostico VARCHAR(150) NOT NULL,
 indicaciones_generales TEXT,
 fecha_inicio DATE NOT NULL,
 fecha_fin DATE NULL,
 foto_receta VARCHAR(255),
 estado ENUM('activa','pausada','finalizada','vencida') DEFAULT 'activa',
 eliminado TINYINT DEFAULT 0,
 creado_por INT,
 creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_residente) REFERENCES residentes(id_residente),
 FOREIGN KEY(creado_por) REFERENCES usuarios(id_usuario)
);
CREATE TABLE medicacion_residente(
 id_plan INT AUTO_INCREMENT PRIMARY KEY,
 id_receta INT NULL,
 id_residente INT NOT NULL, id_medicamento INT NOT NULL, dosis VARCHAR(80), horario TIME, indicacion TEXT,
 fecha_inicio DATE NULL, fecha_fin DATE NULL,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo', eliminado TINYINT DEFAULT 0, created_by INT,
 FOREIGN KEY(id_receta) REFERENCES recetas_medicas(id_receta),
 FOREIGN KEY(id_residente) REFERENCES residentes(id_residente), FOREIGN KEY(id_medicamento) REFERENCES medicamentos(id_medicamento)
);
CREATE TABLE administracion_medicacion(
 id_adm INT AUTO_INCREMENT PRIMARY KEY,
 id_plan INT, id_residente INT, id_medicamento INT, id_cuidadora_usuario INT,
 fecha_hora DATETIME, estado_adm ENUM('pendiente','administrado','no_pudo','no_dado') DEFAULT 'pendiente', observaciones TEXT,
 FOREIGN KEY(id_plan) REFERENCES medicacion_residente(id_plan)
);
CREATE TABLE turnos(
 id_turno INT AUTO_INCREMENT PRIMARY KEY,
 id_cuidadora_usuario INT, novedades TEXT, fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo', eliminado TINYINT DEFAULT 0
);
CREATE TABLE cocina(
 id_cocina INT AUTO_INCREMENT PRIMARY KEY,
 id_usuario INT, tipo ENUM('receta','gasto') NOT NULL, titulo VARCHAR(150), descripcion TEXT, monto DECIMAL(10,2) DEFAULT 0, foto VARCHAR(255), fecha DATE,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo', eliminado TINYINT DEFAULT 0, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE tareas_residente(
 id_tarea INT AUTO_INCREMENT PRIMARY KEY,
 id_residente INT NOT NULL,
 titulo VARCHAR(150) NOT NULL,
 descripcion TEXT,
 horario TIME,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo',
 eliminado TINYINT DEFAULT 0,
 created_by INT,
 FOREIGN KEY(id_residente) REFERENCES residentes(id_residente)
);
CREATE TABLE administracion_tareas(
 id_adm_tarea INT AUTO_INCREMENT PRIMARY KEY,
 id_tarea INT,
 id_residente INT,
 id_cuidadora_usuario INT,
 fecha_hora DATETIME,
 estado_tarea ENUM('pendiente','realizada','no_realizada','no_pudo') DEFAULT 'pendiente',
 motivo TEXT,
 FOREIGN KEY(id_tarea) REFERENCES tareas_residente(id_tarea)
);
CREATE TABLE turnos_programados(
 id_turno_prog INT AUTO_INCREMENT PRIMARY KEY,
 id_cuidadora_usuario INT NOT NULL,
 fecha DATE NOT NULL,
 hora_inicio TIME NOT NULL,
 hora_fin TIME NOT NULL,
 nota TEXT,
 estado ENUM('activo','pausado','baja') DEFAULT 'activo',
 eliminado TINYINT DEFAULT 0,
 created_by INT,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY(id_cuidadora_usuario) REFERENCES usuarios(id_usuario)
);
CREATE TABLE papelera(id INT AUTO_INCREMENT PRIMARY KEY, tabla VARCHAR(60), registro_id INT, accion VARCHAR(60), usuario VARCHAR(100), fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP);

INSERT INTO usuarios(nombre,email,password,rol,estado,color) VALUES
('Carla Administradora','admin@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','admin','activo','#0f2f4a'),
('Ana Cuidadora','ana@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','cuidadora','activo','#e74c3c'),
('Sofia Cuidadora','sofia@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','cuidadora','activo','#8e44ad'),
('Laura Cuidadora','laura@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','cuidadora','activo','#2980b9'),
('Daniela Cuidadora','daniela@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','cuidadora','activo','#f39c12'),
('Marta Cocinera','marta@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','cocinera','activo','#087f7a'),
('Paula Asistente','paula@gesthogar.com','$2y$12$3DK5XAuSerZhzEak2T2BwO6cspWxmjBL/h/n/5OrKS8MOk2u3boZG','asistente','activo','#16a085');
INSERT INTO habitaciones(numero,sector,piso,cama,estado,observaciones) VALUES
('101','Ala Norte','Planta baja','Cama A','ocupada','Cerca del bano'),
('102','Ala Norte','Planta baja','Cama A','ocupada','Ventana al patio'),
('103','Ala Sur','Planta baja','Cama A','disponible','Lista para ingreso'),
('201','Ala Norte','Primer piso','Cama A','ocupada','Habitacion tranquila'),
('202','Ala Sur','Primer piso','Cama A','mantenimiento','Revisar luz');
INSERT INTO residentes(nombre,apellido,dni,fecha_nacimiento,movilidad_autonoma,obs_medicas,contacto_familiar,telefono_familiar,id_habitacion,foto,created_by) VALUES
('Rosa','Gomez','22345678','1940-03-12',1,'Hipertension. Alergia penicilina.','Laura Gomez','351555111',1,'uploads/residentes/ejemplo_residente.jpg',1),
('Carlos','Perez','18765432','1938-07-20',1,'Diabetes tipo 2.','Juan Perez','351555222',2,'uploads/residentes/ejemplo_residente.jpg',1),
('Elena','Ruiz','20555111','1942-11-05',1,'Control de presion semanal.','Marcela Ruiz','351555333',4,'uploads/residentes/ejemplo_residente.jpg',1);
INSERT INTO cuidadoras(id_usuario,nombre,apellido,dni,telefono,tipo_turno) VALUES
(2,'Ana','Lopez','30111222','351555333','24h/48h'),
(3,'Sofia','Martinez','32777888','351555444','12h diurno'),
(4,'Laura','Fernandez','33555999','351555666','24h/48h'),
(5,'Daniela','Acosta','34666111','351555777','Refuerzo tarde');
INSERT INTO medicamentos(nombre,presentacion,descripcion) VALUES ('Losartan','Comprimido 50mg','Presion arterial'),('Metformina','Comprimido 850mg','Diabetes'),('Paracetamol','Comprimido 500mg','Dolor o fiebre'),('Omeprazol','Capsula 20mg','Protector gastrico');
INSERT INTO recetas_medicas(id_residente,diagnostico,indicaciones_generales,fecha_inicio,fecha_fin,foto_receta,creado_por) VALUES
(1,'Tratamiento permanente de presión','Control diario de presión arterial. Medicación indicada por profesional médico.','2026-06-01',NULL,NULL,1),
(1,'Protector gástrico permanente','Administrar antes del desayuno.','2026-06-01',NULL,NULL,1),
(2,'Tratamiento permanente de diabetes','Controlar alimentación y horarios de medicación.','2026-06-01',NULL,NULL,1),
(3,'Tratamiento temporal por gripe','Administrar solo durante el período indicado y controlar fiebre.','2026-06-14',DATE_ADD(CURDATE(), INTERVAL 5 DAY),NULL,1);
INSERT INTO medicacion_residente(id_receta,id_residente,id_medicamento,dosis,horario,indicacion,fecha_inicio,fecha_fin,created_by) VALUES
(1,1,1,'1 comprimido','08:00','Despues del desayuno','2026-06-01',NULL,1),(1,1,1,'1 comprimido','20:00','Despues de la cena','2026-06-01',NULL,1),(2,1,4,'1 capsula','07:30','Antes del desayuno','2026-06-01',NULL,1),
(3,2,2,'1 comprimido','08:00','Despues del desayuno','2026-06-01',NULL,1),(3,2,2,'1 comprimido','14:00','Despues del almuerzo','2026-06-01',NULL,1),(3,2,2,'1 comprimido','21:00','Despues de la cena','2026-06-01',NULL,1),
(4,3,3,'1 comprimido','08:00','Gripe: si tiene fiebre o dolor','2026-06-14',DATE_ADD(CURDATE(), INTERVAL 5 DAY),1),(4,3,3,'1 comprimido','16:00','Gripe: repetir si corresponde','2026-06-14',DATE_ADD(CURDATE(), INTERVAL 5 DAY),1);
INSERT INTO tareas_residente(id_residente,titulo,descripcion,horario,created_by) VALUES
(1,'Controlar presion','Tomar presion y registrar si esta alta o normal','10:00',1),
(2,'Acompanamiento caminata','Acompanarlo a caminar 10 minutos por el patio','17:00',1),
(3,'Hidratacion','Ofrecer agua y registrar si acepto','11:00',1);
INSERT INTO administracion_medicacion(id_plan,id_residente,id_medicamento,id_cuidadora_usuario,fecha_hora,estado_adm,observaciones) VALUES
(1,1,1,2,DATE_ADD(CONCAT(CURDATE(),' 08:00:00'), INTERVAL -6 DAY),'administrado',''),
(2,1,1,3,DATE_ADD(CONCAT(CURDATE(),' 20:00:00'), INTERVAL -6 DAY),'administrado',''),
(3,1,4,2,DATE_ADD(CONCAT(CURDATE(),' 07:30:00'), INTERVAL -5 DAY),'no_pudo','Residente dormida, se avisó a Carla'),
(4,2,2,4,DATE_ADD(CONCAT(CURDATE(),' 08:00:00'), INTERVAL -4 DAY),'administrado',''),
(5,2,2,5,DATE_ADD(CONCAT(CURDATE(),' 14:00:00'), INTERVAL -3 DAY),'administrado',''),
(7,3,3,2,DATE_ADD(CONCAT(CURDATE(),' 08:00:00'), INTERVAL -2 DAY),'no_dado','No presentó fiebre, se registró por indicación');
INSERT INTO turnos_programados(id_cuidadora_usuario,fecha,hora_inicio,hora_fin,nota,created_by) VALUES
(2,DATE_ADD(CURDATE(), INTERVAL -7 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL -7 DAY),'20:00','08:00','Turno noche Sofia',1),
(6,DATE_ADD(CURDATE(), INTERVAL -7 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -7 DAY),'18:00','21:00','Cocina cena Marta',1),
(7,DATE_ADD(CURDATE(), INTERVAL -7 DAY),'10:00','18:00','Asistente Paula',1),
(3,DATE_ADD(CURDATE(), INTERVAL -6 DAY),'08:00','20:00','Turno diurno Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL -6 DAY),'20:00','08:00','Turno noche Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL -6 DAY),'14:00','22:00','Refuerzo tarde Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL -6 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -6 DAY),'18:00','21:00','Cocina cena Marta',1),
(4,DATE_ADD(CURDATE(), INTERVAL -5 DAY),'08:00','20:00','Turno diurno Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL -5 DAY),'20:00','08:00','Turno noche Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL -5 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -5 DAY),'18:00','21:00','Cocina cena Marta',1),
(5,DATE_ADD(CURDATE(), INTERVAL -4 DAY),'08:00','20:00','Turno diurno Daniela',1),
(2,DATE_ADD(CURDATE(), INTERVAL -4 DAY),'20:00','08:00','Turno noche Ana',1),
(6,DATE_ADD(CURDATE(), INTERVAL -4 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -4 DAY),'18:00','21:00','Cocina cena Marta',1),
(2,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'20:00','08:00','Turno noche Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'14:00','22:00','Refuerzo tarde Laura',1),
(6,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'18:00','21:00','Cocina cena Marta',1),
(7,DATE_ADD(CURDATE(), INTERVAL -3 DAY),'10:00','18:00','Asistente Paula',1),
(3,DATE_ADD(CURDATE(), INTERVAL -2 DAY),'08:00','20:00','Turno diurno Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL -2 DAY),'20:00','08:00','Turno noche Laura',1),
(6,DATE_ADD(CURDATE(), INTERVAL -2 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -2 DAY),'18:00','21:00','Cocina cena Marta',1),
(4,DATE_ADD(CURDATE(), INTERVAL -1 DAY),'08:00','20:00','Turno diurno Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL -1 DAY),'20:00','08:00','Turno noche Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL -1 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL -1 DAY),'18:00','21:00','Cocina cena Marta',1),
(5,CURDATE(),'08:00','20:00','Turno diurno Daniela',1),
(2,CURDATE(),'20:00','08:00','Turno noche Ana',1),
(3,CURDATE(),'14:00','22:00','Refuerzo tarde Sofia',1),
(6,CURDATE(),'09:00','13:00','Cocina almuerzo Marta',1),
(6,CURDATE(),'18:00','21:00','Cocina cena Marta',1),
(7,CURDATE(),'10:00','18:00','Asistente Paula',1),
(2,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'20:00','08:00','Turno noche Sofia',1),
(6,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 1 DAY),'18:00','21:00','Cocina cena Marta',1),
(3,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'08:00','20:00','Turno diurno Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'20:00','08:00','Turno noche Laura',1),
(6,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 2 DAY),'18:00','21:00','Cocina cena Marta',1),
(4,DATE_ADD(CURDATE(), INTERVAL 3 DAY),'08:00','20:00','Turno diurno Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL 3 DAY),'20:00','08:00','Turno noche Daniela',1),
(2,DATE_ADD(CURDATE(), INTERVAL 3 DAY),'14:00','22:00','Refuerzo tarde Ana',1),
(6,DATE_ADD(CURDATE(), INTERVAL 3 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 3 DAY),'18:00','21:00','Cocina cena Marta',1),
(5,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'08:00','20:00','Turno diurno Daniela',1),
(2,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'20:00','08:00','Turno noche Ana',1),
(6,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'18:00','21:00','Cocina cena Marta',1),
(7,DATE_ADD(CURDATE(), INTERVAL 4 DAY),'10:00','18:00','Asistente Paula',1),
(2,DATE_ADD(CURDATE(), INTERVAL 5 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL 5 DAY),'20:00','08:00','Turno noche Sofia',1),
(6,DATE_ADD(CURDATE(), INTERVAL 5 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 5 DAY),'18:00','21:00','Cocina cena Marta',1),
(3,DATE_ADD(CURDATE(), INTERVAL 6 DAY),'08:00','20:00','Turno diurno Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL 6 DAY),'20:00','08:00','Turno noche Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL 6 DAY),'14:00','22:00','Refuerzo tarde Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL 6 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 6 DAY),'18:00','21:00','Cocina cena Marta',1),
(4,DATE_ADD(CURDATE(), INTERVAL 7 DAY),'08:00','20:00','Turno diurno Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL 7 DAY),'20:00','08:00','Turno noche Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL 7 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 7 DAY),'18:00','21:00','Cocina cena Marta',1),
(5,DATE_ADD(CURDATE(), INTERVAL 8 DAY),'08:00','20:00','Turno diurno Daniela',1),
(2,DATE_ADD(CURDATE(), INTERVAL 8 DAY),'20:00','08:00','Turno noche Ana',1),
(6,DATE_ADD(CURDATE(), INTERVAL 8 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 8 DAY),'18:00','21:00','Cocina cena Marta',1),
(2,DATE_ADD(CURDATE(), INTERVAL 9 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL 9 DAY),'20:00','08:00','Turno noche Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL 9 DAY),'14:00','22:00','Refuerzo tarde Laura',1),
(6,DATE_ADD(CURDATE(), INTERVAL 9 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 9 DAY),'18:00','21:00','Cocina cena Marta',1),
(3,DATE_ADD(CURDATE(), INTERVAL 10 DAY),'08:00','20:00','Turno diurno Sofia',1),
(4,DATE_ADD(CURDATE(), INTERVAL 10 DAY),'20:00','08:00','Turno noche Laura',1),
(6,DATE_ADD(CURDATE(), INTERVAL 10 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 10 DAY),'18:00','21:00','Cocina cena Marta',1),
(4,DATE_ADD(CURDATE(), INTERVAL 11 DAY),'08:00','20:00','Turno diurno Laura',1),
(5,DATE_ADD(CURDATE(), INTERVAL 11 DAY),'20:00','08:00','Turno noche Daniela',1),
(6,DATE_ADD(CURDATE(), INTERVAL 11 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 11 DAY),'18:00','21:00','Cocina cena Marta',1),
(5,DATE_ADD(CURDATE(), INTERVAL 12 DAY),'08:00','20:00','Turno diurno Daniela',1),
(2,DATE_ADD(CURDATE(), INTERVAL 12 DAY),'20:00','08:00','Turno noche Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL 12 DAY),'14:00','22:00','Refuerzo tarde Sofia',1),
(6,DATE_ADD(CURDATE(), INTERVAL 12 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 12 DAY),'18:00','21:00','Cocina cena Marta',1),
(2,DATE_ADD(CURDATE(), INTERVAL 13 DAY),'08:00','20:00','Turno diurno Ana',1),
(3,DATE_ADD(CURDATE(), INTERVAL 13 DAY),'20:00','08:00','Turno noche Sofia',1),
(6,DATE_ADD(CURDATE(), INTERVAL 13 DAY),'09:00','13:00','Cocina almuerzo Marta',1),
(6,DATE_ADD(CURDATE(), INTERVAL 13 DAY),'18:00','21:00','Cocina cena Marta',1);
INSERT INTO cocina(id_usuario,tipo,titulo,descripcion,monto,foto,fecha) VALUES
(6,'receta','Sopa de verduras','Zapallo, zanahoria, papa y caldo liviano',0,'uploads/cocina/ejemplo_cocina.jpg',CURDATE()),
(6,'gasto','Compra verduleria','Verduras para cena y almuerzo',8500,'uploads/cocina/ejemplo_cocina.jpg',CURDATE());
