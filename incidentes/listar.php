<?php
require_once '../config/session.php';
require_once '../config/db.php';
puede_cuidar();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_incidente'])) {
    $id_res    = (int)$_POST['id_residente'];
    $desc      = trim($_POST['descripcion'] ?? '');
    $acciones  = trim($_POST['acciones_tomadas'] ?? '');
    $gravedad  = trim($_POST['gravedad'] ?? '');
    $medico    = trim($_POST['medico_interviniente'] ?? '');
    $fecha     = trim($_POST['fecha_hora'] ?? date('Y-m-d H:i:s'));

    $error = '';
    if (!$id_res)                                     $error = 'Seleccioná un residente.';
    elseif (strlen($desc) < 10)                       $error = 'La descripción debe tener al menos 10 caracteres.';
    elseif (strlen($acciones) < 5)                    $error = 'El campo Acciones tomadas es obligatorio.';
    elseif (!in_array($gravedad, ['leve','moderado','grave'])) $error = 'Seleccioná la gravedad del incidente.';

    if ($error) {
        header('Location: listar.php?toast=' . urlencode($error) . '&tipo=error');
        exit;
    }

    $notificado = $gravedad === 'grave' ? 1 : 0;

    $pdo->prepare("
        INSERT INTO incidentes
            (id_residente, id_usuario, descripcion, acciones_tomadas, gravedad,
             medico_interviniente, fecha_hora, notificado_responsable)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ")->execute([
        $id_res, uid(), $desc, $acciones, $gravedad,
        $medico ?: null, $fecha, $notificado
    ]);

    $msg = $gravedad === 'grave'
        ? 'Incidente grave registrado. Se activó la alerta en el dashboard de Carla.'
        : 'Incidente registrado correctamente.';
    $tipo = $gravedad === 'grave' ? 'warn' : 'ok';
    header('Location: listar.php?toast=' . urlencode($msg) . '&tipo=' . $tipo);
    exit;
}

if (isset($_GET['revisar']) && es_admin()) {
    $pdo->prepare("UPDATE incidentes SET estado='revisado', updated_by=? WHERE id_incidente=? AND estado='pendiente'")
        ->execute([uid(), (int)$_GET['revisar']]);
    header('Location: listar.php?toast=Incidente+marcado+como+revisado&tipo=ok');
    exit;
}

include '../config/menu.php';

$residentes = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$whereRes     = $id_residente ? ' AND i.id_residente=' . $id_residente : '';

$incidentes = $pdo->query("
    SELECT i.*, r.nombre res_nombre, r.apellido res_apellido,
           u.nombre registrado_por
    FROM incidentes i
    JOIN residentes r ON r.id_residente = i.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
    WHERE 1=1 $whereRes
    ORDER BY i.fecha_hora DESC
")->fetchAll();

$graves_sin_revisar = array_filter($incidentes, fn($i) => $i['gravedad']==='grave' && $i['estado']==='pendiente');

function gravedad_class($g) {
    return match($g) { 'grave'=>'estado-problema', 'moderado'=>'estado-alerta', default=>'estado-pendiente' };
}
function gravedad_label($g) {
    return match($g) { 'grave'=>'Grave', 'moderado'=>'Moderado', default=>'Leve' };
}
?>

<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:16px">
    <h1 style="margin:0">Incidentes
        <?php if (count($graves_sin_revisar) > 0): ?>
            <span style="background:#c0392b;color:#fff;font-size:13px;padding:3px 10px;border-radius:12px;margin-left:10px;vertical-align:middle">
                <?= count($graves_sin_revisar) ?> grave<?= count($graves_sin_revisar)>1?'s':'' ?> sin revisar
            </span>
        <?php endif; ?>
    </h1>
    <button class="btn" onclick="document.getElementById('modal-incidente').style.display='flex'">
        + Registrar incidente
    </button>
</div>

<?php if (count($graves_sin_revisar) > 0): ?>
<div style="background:#fdecea;border-left:5px solid #c0392b;padding:12px 18px;border-radius:8px;margin-bottom:16px;display:flex;gap:12px;align-items:center">
    <div>
        <b style="color:#c0392b">Hay <?= count($graves_sin_revisar) ?> incidente<?= count($graves_sin_revisar)>1?'s':'' ?> grave<?= count($graves_sin_revisar)>1?'s':'' ?> sin revisar</b><br>
        <span style="font-size:13px;color:#555">Carla debe marcarlos como revisados desde este panel.</span>
    </div>
</div>
<?php endif; ?>

<div style="background:#e8f4fd;border-left:4px solid #2980b9;padding:10px 16px;border-radius:8px;margin-bottom:16px;font-size:13px">
Los incidentes son <b>registros inmutables</b>: una vez guardados no se pueden editar ni eliminar. Solo pueden marcarse como <b>Revisado</b> por la administradora.
</div>

<div style="margin-bottom:16px;display:flex;gap:10px;align-items:center">
    <label style="font-size:13px;font-weight:600">Filtrar por residente:</label>
    <select class="buscador" onchange="window.location='listar.php?residente='+this.value"
            style="min-width:240px;padding:8px 12px;border:1.5px solid var(--border-color,#ddd);border-radius:8px;font-size:14px">
        <option value="0" <?= $id_residente==0?'selected':'' ?>>Todos</option>
        <?php foreach ($residentes as $r): ?>
            <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                <?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php if ($id_residente): ?><a href="listar.php" class="btn gray">Limpiar</a><?php endif; ?>
</div>

<?php if (empty($incidentes)): ?>
    <div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <b>No hay incidentes registrados<?= $id_residente ? ' para este residente' : '' ?></b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Residente</th>
            <th>Descripción</th>
            <th>Acciones tomadas</th>
            <th>Gravedad</th>
            <th>Médico</th>
            <th>Registrado por</th>
            <th>Estado</th>
            <?php if (es_admin()): ?><th>Acción</th><?php endif; ?>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($incidentes as $i): ?>
        <tr style="<?= $i['gravedad']==='grave' && $i['estado']==='pendiente' ? 'background:#fdecea' : '' ?>">
            <td><?= date('d/m/Y H:i', strtotime($i['fecha_hora'])) ?></td>
            <td><?= htmlspecialchars($i['res_nombre'].' '.$i['res_apellido']) ?></td>
            <td style="max-width:250px"><?= htmlspecialchars($i['descripcion']) ?></td>
            <td style="max-width:200px"><?= htmlspecialchars($i['acciones_tomadas']) ?></td>
            <td><span class="pill <?= gravedad_class($i['gravedad']) ?>"><?= gravedad_label($i['gravedad']) ?></span></td>
            <td><?= htmlspecialchars($i['medico_interviniente'] ?: '') ?></td>
            <td><?= htmlspecialchars($i['registrado_por'] ?? '') ?></td>
            <td>
                <?php if ($i['estado'] === 'activo'): ?>
                    <span class="pill estado-pendiente">Pendiente</span>
                <?php else: ?>
                    <span class="pill estado-ok">Revisado</span>
                <?php endif; ?>
            </td>
            <?php if (es_admin()): ?>
            <td>
                <?php if ($i['estado'] === 'activo'): ?>
                    <a class="btn green" href="?revisar=<?= $i['id_incidente'] ?>"
                       onclick="return confirmarLink(this,'¿Marcar este incidente como revisado?','Revisar','green')">
                       Marcar revisado
                    </a>
                <?php else: ?>
                    <span style="font-size:12px;color:#aaa;font-style:italic">Inmutable</span>
                <?php endif; ?>
            </td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div id="modal-incidente" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:580px;position:relative">
        <button onclick="document.getElementById('modal-incidente').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)">×</button>
        <h2 style="margin:0 0 8px">Registrar incidente</h2>
        <p style="font-size:13px;color:#888;margin:0 0 16px">El registro es inmutable. Una vez guardado no se puede modificar.</p>

        <form method="post">
            <input type="hidden" name="nuevo_incidente" value="1">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600">Residente *</label>
                    <select name="id_residente" required class="buscador" style="width:100%;box-sizing:border-box">
                        <option value="">Seleccioná...</option>
                        <?php foreach ($residentes as $r): ?>
                            <option value="<?= $r['id_residente'] ?>"><?= htmlspecialchars($r['nombre'].' '.$r['apellido']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Gravedad *</label>
                    <select name="gravedad" required id="sel-gravedad" onchange="mostrarAvisoGrave()"
                            style="width:100%;box-sizing:border-box">
                        <option value="">Seleccioná...</option>
                        <option value="leve">Leve</option>
                        <option value="moderado">Moderado</option>
                        <option value="grave">Grave</option>
                    </select>
                </div>
            </div>

            <div id="aviso-grave" style="display:none;background:#fdecea;border-left:4px solid #c0392b;padding:10px 14px;border-radius:6px;margin-top:10px;font-size:13px;color:#c0392b">
Al guardar este incidente como <b>Grave</b>, se activará una alerta automática en el dashboard de Carla.
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Descripción del incidente * <span style="color:#aaa;font-weight:400">(mínimo 10 caracteres)</span></label>
                <textarea name="descripcion" rows="3" required minlength="10"
                          placeholder="Describí qué ocurrió, cuándo y en qué circunstancias..."
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600">Acciones tomadas *</label>
                <textarea name="acciones_tomadas" rows="2" required
                          style="width:100%;box-sizing:border-box;resize:vertical"></textarea>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:10px">
                <div>
                    <label style="font-size:13px;font-weight:600">Médico interviniente <span style="color:#aaa;font-weight:400">(opcional)</span></label>
                    <input name="medico_interviniente" placeholder="Nombre del médico" style="width:100%;box-sizing:border-box">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600">Fecha y hora del incidente</label>
                    <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>"
                           style="width:100%;box-sizing:border-box">
                </div>
            </div>

            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn red">Registrar incidente</button>
                <button type="button" class="btn gray"
                        onclick="document.getElementById('modal-incidente').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function mostrarAvisoGrave() {
    const g = document.getElementById('sel-gravedad').value;
    document.getElementById('aviso-grave').style.display = g === 'grave' ? 'block' : 'none';
}
</script>

<?php include '../config/footer.php'; ?>
