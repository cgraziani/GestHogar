GESTHOGAR - XAMPP

1) Copiar la carpeta GestHogar en la carpeta htdocs de XAMPP.
2) Activar Apache y MySQL en XAMPP.
3) Abrir: http://localhost/GestHogar/instalar.php
   (si la base ya existe y la querés recrear desde cero:
    http://localhost/GestHogar/instalar.php?reemplazar=1 — borra los datos)
4) Luego abrir: http://localhost/GestHogar/auth/login.php

Base de datos: gesthogar_db

Usuarios de prueba:
- admin@gesthogar.com / password
- ana@gesthogar.com / password
- sofia@gesthogar.com / password
- laura@gesthogar.com / password
- daniela@gesthogar.com / password
- marta@gesthogar.com / password

Cambios principales:
- Calendario de 3 semanas: una hacia atras y dos hacia adelante.
- Admin puede cargar turnos en varios dias a la vez.
- Admin puede tocar un dia, ver detalle y modificar turnos.
- Cuidadoras y cocinera pueden ver el calendario, pero no modificarlo.
- 4 cuidadoras creadas con colores.
- Habitaciones cargadas y residente asignado a cada habitacion.
- Medicamentos con varios horarios por dia.


MEJORAS DE ESTA VERSION
- Interfaz más clara con tarjetas y fichas completas.
- Admin ve residentes, usuarios, cuidadoras, medicación, habitaciones y turnos.
- Ficha del residente con foto, habitación, familiar, datos médicos y medicación.
- Calendario grande separado por días y horas: 1 semana atrás y 2 adelante.
- La admin puede seleccionar varios días para cargar turnos y también editar cada turno.
- Cuidadoras y cocinera pueden ver el calendario para saber qué día y horario les toca.
- Cada cuidadora/cocinera tiene color elegible por admin desde Usuarios.
- Medicación con varios horarios por día.
- Cuidadoras solo pueden marcar Entregado / No pude / No se dio, con motivo obligatorio cuando corresponde.
- Cocinera puede cargar recetas, gastos diarios y foto de la comida/comprobante.
- Cocinera no ve medicamentos.


NUEVA FUNCIÓN MEDICACIÓN POR RESIDENTE
- Entrar a Medicación / Recetas / Tareas.
- Elegir un residente desde los botones superiores.
- Carla/admin puede crear recetas o tratamientos con fecha de inicio y fecha de vencimiento.
- Si la fecha de fin queda vacía, el tratamiento queda permanente.
- Cada receta puede tener foto adjunta en uploads/recetas/.
- Las cuidadoras confirman con verde, amarillo o rojo.
- El sistema guarda automáticamente quién administró o registró, fecha y hora.
- En la ficha de cada residente se ve el historial de medicación de los últimos 7 días.
