<?php
require_once '../config/session.php';
puede_cuidar();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo->prepare("INSERT INTO traspaso_turno(id_usuario_saliente, novedades, fecha_cierre, estado) VALUES(?,?,NOW(),'pendiente')")
        ->execute([uid(), $_POST['novedades']]);
    header('Location: listar.php?toast=Novedad+guardada+correctamente&tipo=ok');
    exit;
}

if (isset($_GET['confirmar']) && es_cuidadora()) {
    $pdo->prepare("UPDATE traspaso_turno SET id_usuario_entrante=?, fecha_apertura=NOW(), estado='cerrado', updated_by=? WHERE id_traspaso=? AND estado='pendiente'")
        ->execute([uid(), uid(), (int)$_GET['confirmar']]);
    header('Location: listar.php?toast=Traspaso+confirmado&tipo=ok');
    exit;
}

include '../config/menu.php';

if (es_admin()) {
    $rows = $pdo->query("SELECT t.*,
                                us.nombre usuario_saliente,
                                ue.nombre usuario_entrante
                         FROM traspaso_turno t
                         JOIN usuarios us ON us.id_usuario = t.id_usuario_saliente
                         LEFT JOIN usuarios ue ON ue.id_usuario = t.id_usuario_entrante
                         ORDER BY t.fecha_cierre DESC")->fetchAll();
} else {
    $st = $pdo->prepare("SELECT t.*,
                                us.nombre usuario_saliente,
                                ue.nombre usuario_entrante
                         FROM traspaso_turno t
                         JOIN usuarios us ON us.id_usuario = t.id_usuario_saliente
                         LEFT JOIN usuarios ue ON ue.id_usuario = t.id_usuario_entrante
                         WHERE t.id_usuario_saliente=? OR t.id_usuario_entrante=?
                         ORDER BY t.fecha_cierre DESC");
    $st->execute([uid(), uid()]);
    $rows = $st->fetchAll();
}

$pendientes = $pdo->prepare("SELECT * FROM traspaso_turno WHERE estado='pendiente' AND id_usuario_entrante IS NULL LIMIT 3");
$pendientes->execute();
$pendientes = $pendientes->fetchAll();
?>

<h1>Turnos y novedades</h1>

<?php if ($pendientes): ?>
<div class="alert" style="background:#fff8e1;border-left:4px solid #f9a825;padding:12px 16px;border-radius:6px;margin-bottom:16px">
Hay <?= count($pendientes) ?> traspaso(s) pendiente(s) de confirmación.
</div>
<?php endif; ?>

<div class="card">
    <h2>Registrar novedad del turno</h2>
    <form method="post">
        <textarea name="novedades"
                  rows="4" required minlength="10"></textarea>
        <br><br>
        <button class="btn">Cerrar turno y guardar novedad</button>
    </form>
</div>

<h2>Historial de traspasos</h2>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha cierre</th>
            <th>Cuidadora saliente</th>
            <th>Cuidadora entrante</th>
            <th>Novedades</th>
            <th>Estado</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($r['fecha_cierre'])) ?></td>
            <td><?= htmlspecialchars($r['usuario_saliente']) ?></td>
            <td><?= htmlspecialchars($r['usuario_entrante'] ?? '') ?></td>
            <td><?= nl2br(htmlspecialchars($r['novedades'])) ?></td>
            <td>
                <span class="pill <?= $r['estado'] === 'cerrado' ? 'estado-activo' : 'estado-pendiente' ?>">
                    <?= $r['estado'] ?>
                </span>
            </td>
            <td>
                <?php if ($r['estado'] === 'pendiente' && !es_admin() && $r['id_usuario_saliente'] != uid()): ?>
                    <a class="btn green"
                       href="?confirmar=<?= $r['id_traspaso'] ?>"
                       onclick="return confirm('¿Confirmás que leíste las novedades del turno anterior?')">
                       Confirmar lectura
                    </a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php include '../config/footer.php'; ?>
