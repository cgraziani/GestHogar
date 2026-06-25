<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

if (isset($_GET['estado']) && isset($_GET['id'])) {
    $estado = $_GET['estado'];
    if (in_array($estado, ['disponible', 'mantenimiento', 'ocupada'])) {
        $pdo->prepare('UPDATE habitaciones SET estado=? WHERE id_habitacion=?')
            ->execute([$estado, $_GET['id']]);
        $labels = ['disponible' => 'disponible', 'mantenimiento' => 'en+mantenimiento', 'ocupada' => 'ocupada'];
        header('Location: listar.php?toast=Habitaci%C3%B3n+marcada+como+' . $labels[$estado] . '&tipo=ok');
        exit;
    }
}

if (isset($_GET['eliminar'])) {
    $pdo->prepare('UPDATE habitaciones SET eliminado=1 WHERE id_habitacion=?')
        ->execute([$_GET['eliminar']]);
    header('Location: listar.php?toast=Habitaci%C3%B3n+enviada+a+la+papelera&tipo=warn');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        $_POST['numero'],
        $_POST['sector'],
        $_POST['piso'],
        $_POST['cama'],
        $_POST['estado'],
        $_POST['observaciones']
    ];
    if ($_POST['id']) {
        $pdo->prepare('UPDATE habitaciones SET numero=?,sector=?,piso=?,cama=?,estado=?,observaciones=? WHERE id_habitacion=?')
            ->execute([...$data, $_POST['id']]);
        header('Location: listar.php?toast=Habitaci%C3%B3n+modificada+correctamente&tipo=ok');
    } else {
        $pdo->prepare('INSERT INTO habitaciones(numero,sector,piso,cama,estado,observaciones) VALUES(?,?,?,?,?,?)')
            ->execute($data);
        header('Location: listar.php?toast=Habitaci%C3%B3n+creada+correctamente&tipo=ok');
    }
    exit;
}

$id = (int)($_GET['id'] ?? 0);
$h  = ['numero' => '', 'sector' => '', 'piso' => '', 'cama' => '', 'estado' => 'disponible', 'observaciones' => ''];
if ($id) {
    $st = $pdo->prepare('SELECT * FROM habitaciones WHERE id_habitacion=?');
    $st->execute([$id]);
    $h = $st->fetch();
}

include '../config/menu.php';

$rows = $pdo->query("SELECT h.*, CONCAT(r.nombre,' ',r.apellido) residente
                     FROM habitaciones h
                     LEFT JOIN residentes r ON r.id_habitacion=h.id_habitacion AND r.eliminado=0
                     WHERE h.eliminado=0
                     ORDER BY h.numero")->fetchAll();
?>

<h1>Habitaciones</h1>

<div style="margin-bottom:16px">
    <button class="btn" onclick="abrirModal(0, null)">+ Crear habitación</button>
</div>

<table class="datatable">
    <thead>
        <tr>
            <th>Habitación</th>
            <th>Sector / Piso / Cama</th>
            <th>Residente asignado</th>
            <th>Estado</th>
            <th>Observaciones</th>
            <th>Acciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
        <tr>
            <td><b><?= htmlspecialchars($r['numero']) ?></b></td>
            <td><?= htmlspecialchars($r['sector']) ?> · <?= htmlspecialchars($r['piso']) ?> · <?= htmlspecialchars($r['cama']) ?></td>
            <td><?= htmlspecialchars($r['residente'] ?: 'Sin residente') ?></td>
            <td>
                <span class="pill estado-<?= $r['estado'] === 'disponible' ? 'activo' : ($r['estado'] === 'mantenimiento' ? 'pausado' : 'baja') ?>">
                    <?= ucfirst($r['estado']) ?>
                </span>
            </td>
            <td><span class="small"><?= htmlspecialchars($r['observaciones']) ?></span></td>
            <td>
                <button class="btn" onclick="abrirModal(<?= $r['id_habitacion'] ?>, <?= htmlspecialchars(json_encode($r)) ?>)">
                    Modificar
                </button>

                <?php if ($r['estado'] === 'disponible'): ?>
                    <a class="btn orange"
                       href="?estado=mantenimiento&id=<?= $r['id_habitacion'] ?>"
                       onclick="return confirm('¿Marcar la habitación <?= htmlspecialchars($r['numero']) ?> como en mantenimiento?')">
                       Mantenimiento
                    </a>
                <?php elseif ($r['estado'] === 'mantenimiento'): ?>
                    <a class="btn green"
                       href="?estado=disponible&id=<?= $r['id_habitacion'] ?>">
                       Marcar disponible
                    </a>
                <?php endif; ?>

                <a class="btn red"
                   href="?eliminar=<?= $r['id_habitacion'] ?>"
                   onclick="return confirm('¿Enviar la habitación <?= htmlspecialchars($r['numero']) ?> a la papelera?')">
                   Papelera
                </a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<div id="modal-habitacion"
     style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);border-radius:12px;padding:28px;
                width:100%;max-width:480px;position:relative;color:var(--text-main)">

        <button onclick="cerrarModal()"
                style="position:absolute;top:12px;right:16px;background:none;
                       border:none;font-size:22px;cursor:pointer">×</button>

        <h2 id="modal-titulo" style="margin-top:0">Crear habitación</h2>

        <form method="post" id="form-habitacion">
            <input type="hidden" name="id" id="f-id">

            <label>Número de habitación <span style="color:red">*</span></label>
            <input name="numero" id="f-numero" required>

            <label>Sector</label>
            <input name="sector" id="f-sector">

            <label>Piso</label>
            <input name="piso" id="f-piso">

            <label>Cama</label>
            <input name="cama" id="f-cama">

            <label>Estado</label>
            <select name="estado" id="f-estado">
                <option value="disponible">Disponible</option>
                <option value="ocupada">Ocupada</option>
                <option value="mantenimiento">Mantenimiento</option>
            </select>

            <label>Observaciones</label>
            <input name="observaciones" id="f-obs" placeholder="Observaciones opcionales">

            <br><br>
            <button type="submit" class="btn">Guardar</button>
            <button type="button" class="btn gray" onclick="cerrarModal()">Cancelar</button>
        </form>
    </div>
</div>

<script>
function abrirModal(id, datos) {
    document.getElementById('modal-habitacion').style.display = 'flex';
    document.getElementById('f-id').value     = id || '';
    document.getElementById('f-numero').value = datos ? datos.numero        : '';
    document.getElementById('f-sector').value = datos ? datos.sector        : '';
    document.getElementById('f-piso').value   = datos ? datos.piso          : '';
    document.getElementById('f-cama').value   = datos ? datos.cama          : '';
    document.getElementById('f-estado').value = datos ? datos.estado        : 'disponible';
    document.getElementById('f-obs').value    = datos ? datos.observaciones : '';
    document.getElementById('modal-titulo').textContent = id ? 'Modificar habitación' : 'Crear habitación';
}

function cerrarModal() {
    document.getElementById('modal-habitacion').style.display = 'none';
}

document.getElementById('modal-habitacion').addEventListener('click', function(e) {
    if (e.target === this) cerrarModal();
});

<?php if ($id && $h): ?>
abrirModal(<?= $id ?>, <?= json_encode($h) ?>);
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
