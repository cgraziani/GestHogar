<?php
// ============================================================
// SECCIÓN 1: SEGURIDAD Y CONFIGURACIÓN
// Pueden acceder cuidadoras, asistentes y admin.
// ============================================================
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

// ============================================================
// SECCIÓN 2: GUARDAR NOVEDAD DEL TURNO (POST)
// Guarda las novedades del turno de la cuidadora logueada.
// Redirige con toast al terminar.
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO turnos(id_cuidadora_usuario,novedades) VALUES(?,?)')
        ->execute([uid(), $_POST['novedades']]);
    header('Location: listar.php?toast=Novedad+guardada+correctamente&tipo=ok');
    exit;
}

// ============================================================
// SECCIÓN 3: CARGA DE DATOS
// Admin ve todas las novedades. Cuidadora solo las propias.
// ============================================================
include '../config/menu.php';

$where = es_admin()
    ? 't.eliminado=0'
    : 't.eliminado=0 AND t.id_cuidadora_usuario=' . uid();

$rows = $pdo->query("SELECT t.*,u.nombre usuario
                     FROM turnos t
                     JOIN usuarios u ON t.id_cuidadora_usuario=u.id_usuario
                     WHERE $where
                     ORDER BY fecha_hora DESC")->fetchAll();
?>

<h1>Turnos y novedades</h1>

<!-- ============================================================
     SECCIÓN 4: FORMULARIO CARGAR NOVEDAD
     Cada cuidadora registra las novedades de su turno.
     El campo es obligatorio (required) para evitar envíos vacíos.
     ============================================================ -->
<div class="card">
    <h2>Registrar novedad del turno</h2>
    <p class="small">Describí las novedades del turno actual: estado de los residentes,
       incidentes, medicaciones especiales, visitas, etc.</p>
    <form method="post">
        <textarea name="novedades"
                  placeholder="Ej: Rosa Gomez tomó toda su medicación. Carlos Perez recibió visita de su hijo. Sin incidentes durante el turno."
                  rows="4"
                  required
                  minlength="10"></textarea>
        <small style="color:#888">Mínimo 10 caracteres. Quedará registrado con tu nombre, fecha y hora.</small>
        <br><br>
        <button class="btn">Guardar novedad</button>
    </form>
</div>

<!-- ============================================================
     SECCIÓN 5: HISTORIAL DE NOVEDADES CON DATATABLES
     Admin ve todas las novedades de todas las cuidadoras.
     Cuidadora ve solo las propias.
     ============================================================ -->
<h2>Historial de novedades</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Cuidadora</th>
            <th>Novedades del turno</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($r['fecha_hora'])) ?></td>
            <td><?= htmlspecialchars($r['usuario']) ?></td>
            <td><?= nl2br(htmlspecialchars($r['novedades'])) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>