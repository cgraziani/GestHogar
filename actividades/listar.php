<?php
require_once '../config/session.php';
require_once '../config/db.php';
puede_cuidar();

$upload_dir = __DIR__ . '/../uploads/actividades/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

if (isset($_GET['baja']) && es_admin()) {
    $pdo->prepare("UPDATE actividades SET estado='baja', updated_by=? WHERE id_actividad=?")
        ->execute([uid(), (int)$_GET['baja']]);
    header('Location: listar.php?toast=Actividad+dada+de+baja&tipo=ok'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_actividad'])) {
    $nombre  = trim($_POST['nombre'] ?? '');
    $desc    = trim($_POST['descripcion'] ?? '');
    $fecha   = trim($_POST['fecha'] ?? date('Y-m-d'));
    $obs     = trim($_POST['observaciones'] ?? '');
    $partic  = $_POST['participantes'] ?? [];

    $error = '';
    if (!$nombre)         $error = 'El nombre de la actividad es obligatorio.';
    elseif (!$fecha)      $error = 'La fecha es obligatoria.';
    elseif (empty($partic)) $error = 'Seleccioná al menos un residente participante.';

    $foto_path = null;
    if (!$error && isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        if (!empty($partic)) {
            $ids = implode(',', array_map('intval', $partic));
            $sin_auth = $pdo->query("
                SELECT nombre, apellido FROM residentes
                WHERE id_residente IN ($ids) AND autorizado_foto = 0
            ")->fetchAll();
            if (!empty($sin_auth)) {
                $nombres = array_map(fn($r) => $r['nombre'].' '.$r['apellido'], $sin_auth);
                $error = 'CB-14: No se puede subir la foto. Los siguientes residentes no tienen autorización familiar para fotografías: ' . implode(', ', $nombres) . '. Podés guardar la actividad sin foto.';
            }
        }
        if (!$error) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
                $error = 'Formato de foto inválido. Usá JPG, PNG o WebP.';
            } else {
                $fname = 'act_' . time() . '_' . uid() . '.' . $ext;
                move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $fname);
                $foto_path = 'uploads/actividades/' . $fname;
            }
        }
    }

    if ($error) {
        header('Location: listar.php?toast=' . urlencode($error) . '&tipo=error'); exit;
    }

    $pdo->prepare("
        INSERT INTO actividades (id_usuario, nombre, descripcion, fecha, foto, observaciones)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([uid(), $nombre, $desc ?: null, $fecha, $foto_path, $obs ?: null]);

    $id_act = $pdo->lastInsertId();

    foreach ($partic as $id_res) {
        $obs_part = trim($_POST['obs_participante'][$id_res] ?? '');
        $pdo->prepare("INSERT INTO actividad_residentes (id_actividad, id_residente, observacion_participacion) VALUES (?,?,?)")
            ->execute([$id_act, (int)$id_res, $obs_part ?: null]);
    }

    header('Location: listar.php?toast=Actividad+registrada+correctamente&tipo=ok'); exit;
}

include '../config/menu.php';

$residentes = $pdo->query("
    SELECT id_residente, nombre, apellido, autorizado_foto
    FROM residentes WHERE estado='activo' ORDER BY apellido, nombre
")->fetchAll();

$actividades = $pdo->query("
    SELECT a.*, u.nombre reg_por,
           GROUP_CONCAT(CONCAT(r.nombre,' ',r.apellido) SEPARATOR ', ') participantes
    FROM actividades a
    LEFT JOIN usuarios u ON u.id_usuario = a.id_usuario
    LEFT JOIN actividad_residentes ar ON ar.id_actividad = a.id_actividad
    LEFT JOIN residentes r ON r.id_residente = ar.id_residente
    WHERE a.estado = 'activo'
    GROUP BY a.id_actividad
    ORDER BY a.fecha DESC
")->fetchAll();
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <h1 style="margin:0">Actividades recreativas</h1>
    <button class="btn" onclick="document.getElementById('modal-actividad').style.display='flex'">
        + Registrar actividad
    </button>
</div>


<?php if (empty($actividades)): ?>
    <div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <b>No hay actividades registradas</b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>Descripción</th>
            <th>Participantes</th>
            <th>Foto</th>
            <th>Observaciones</th>
            <th>Registrado por</th>
            <?php if (es_admin()): ?><th>Acciones</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($actividades as $a): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
            <td><b><?= htmlspecialchars($a['nombre']) ?></b></td>
            <td><?= htmlspecialchars($a['descripcion'] ?? '') ?></td>
            <td style="font-size:13px"><?= htmlspecialchars($a['participantes'] ?? '') ?></td>
            <td>
                <?php if ($a['foto']): ?>
                    <a href="<?= BASE_URL ?>/<?= htmlspecialchars($a['foto']) ?>" target="_blank">
                        <img src="<?= BASE_URL ?>/<?= htmlspecialchars($a['foto']) ?>"
                             style="width:50px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #ddd">
                    </a>
                <?php else: ?>
                    <span style="color:#aaa;font-size:12px">Sin foto</span>
                <?php endif; ?>
            </td>
            <td style="font-size:13px"><?= htmlspecialchars($a['observaciones'] ?? '') ?></td>
            <td><?= htmlspecialchars($a['reg_por'] ?? '') ?></td>
            <?php if (es_admin()): ?>
            <td>
                <a class="btn red" href="?baja=<?= $a['id_actividad'] ?>"
                   onclick="return confirmarLink(this,'¿Dar de baja esta actividad?','Dar de baja','red')">
                   Dar de baja
                </a>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div id="modal-actividad" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:620px;position:relative">
        <button onclick="document.getElementById('modal-actividad').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">×</button>
        <h2 style="margin:0 0 16px">Registrar actividad recreativa</h2>

        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="nueva_actividad" value="1">

            <div style="display:grid;grid-template-columns:1fr 160px;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Nombre de la actividad *</label>
                    <input name="nombre" required style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Descripción <span style="color:#aaa;font-weight:400">(opcional)</span></label>
                <input name="descripcion" style="width:100%;box-sizing:border-box">
            </div>

            <div style="margin-top:12px">
                <label style="font-size:13px;font-weight:600">Residentes participantes *</label>
                <div style="border:1px solid var(--border-color,#ddd);border-radius:8px;padding:10px;max-height:200px;overflow-y:auto;margin-top:4px">
                    <?php foreach ($residentes as $r): ?>
                    <label style="display:flex;align-items:center;gap:8px;padding:5px 0;font-size:13px;cursor:pointer;border-bottom:1px solid #f0f0f0">
                        <input type="checkbox" name="participantes[]" value="<?= $r['id_residente'] ?>"
                               onchange="mostrarObsParticipante(this)"
                               style="width:auto">
                        <?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>
                        <?php if (!$r['autorizado_foto']): ?>
                            <span style="font-size:11px;background:#fff3cd;color:#856404;padding:1px 6px;border-radius:8px">Sin autorización de foto</span>
                        <?php else: ?>
                            <span style="font-size:11px;background:#d4edda;color:#155724;padding:1px 6px;border-radius:8px">Autorizado</span>
                        <?php endif; ?>
                    </label>
                    <div id="obs-<?= $r['id_residente'] ?>" style="display:none;padding:4px 0 8px 24px">
                        <input name="obs_participante[<?= $r['id_residente'] ?>]"
                               placeholder="Observación de participación (opcional)"
                               style="width:100%;box-sizing:border-box;font-size:12px">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Observaciones generales <span style="color:#aaa;font-weight:400">(opcional)</span></label>
                <textarea name="observaciones" rows="2"
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Foto <span style="color:#aaa;font-weight:400">(opcional, solo si todos los participantes tienen autorización)</span></label>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" style="width:100%;box-sizing:border-box">
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Guardar actividad</button>
                <button type="button" class="btn gray"
                        onclick="document.getElementById('modal-actividad').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarObsParticipante(cb) {
    const div = document.getElementById('obs-' + cb.value);
    if (div) div.style.display = cb.checked ? 'block' : 'none';
}
window.addEventListener('click', function(e) {
    const m = document.getElementById('modal-actividad');
    if (e.target === m) m.style.display = 'none';
});
</script>

<?php include '../config/footer.php'; ?>
