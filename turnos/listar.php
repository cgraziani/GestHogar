<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare('INSERT INTO turnos(id_cuidadora_usuario,novedades) VALUES(?,?)')
        ->execute([uid(), $_POST['novedades']]);
    header('Location: listar.php?toast=Novedad+guardada+correctamente&tipo=ok');
    exit;
}

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

<div class="card">
    <h2>Registrar novedad del turno</h2>
    <form method="post">
        <textarea name="novedades"
                  rows="4"
                  required
                  minlength="10"></textarea>
        <br><br>
        <button class="btn">Guardar novedad</button>
    </form>
</div>

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