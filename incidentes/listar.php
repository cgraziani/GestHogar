<?php
require_once '../config/session.php';
require_once '../config/db.php';
puede_cuidar();

const TIPOS_INCIDENTE = [
    'caida'            => 'Caída',
    'lesion'           => 'Lesión / herida',
    'descompensacion'  => 'Descompensación',
    'atragantamiento'  => 'Atragantamiento',
    'error_medicacion' => 'Error de medicación',
    'conducta'         => 'Episodio de conducta',
    'fuga'             => 'Salida sin aviso',
    'otro'             => 'Otro',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_incidente'])) {
    $id_res    = (int)$_POST['id_residente'];
    $desc      = trim($_POST['descripcion'] ?? '');
    $acciones  = trim($_POST['acciones_tomadas'] ?? '');
    $gravedad  = trim($_POST['gravedad'] ?? '');

    $errores = [];
    if (!$id_res)   $errores[] = 'Seleccioná un residente.';
    if (!$desc)     $errores[] = 'La descripción del incidente es obligatoria.';
    if (!$acciones) $errores[] = 'Las acciones tomadas son obligatorias.';
    if (!in_array($gravedad, ['leve','moderado','grave'])) $errores[] = 'La gravedad es obligatoria.';
    if (!empty($errores)) {
        $_SESSION['error_form'] = implode(' | ', $errores);
        header('Location: listar.php'); exit;
    }
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

    $tipo_inc = $_POST['tipo_incidente'] ?? 'otro';
    if (!array_key_exists($tipo_inc, TIPOS_INCIDENTE)) $tipo_inc = 'otro';

    $notificado = $gravedad === 'grave' ? 1 : 0;

    try {
        $pdo->prepare("
            INSERT INTO incidentes
                (id_residente, id_usuario, tipo_incidente, descripcion, acciones_tomadas, gravedad,
                 medico_interviniente, fecha_hora, notificado_responsable)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $id_res, uid(), $tipo_inc, $desc, $acciones, $gravedad,
            $medico ?: null, $fecha, $notificado
        ]);
        $msg  = $gravedad === 'grave'
            ? 'Incidente grave registrado. Se activó la alerta en el dashboard.'
            : 'Incidente registrado correctamente.';
        $tipo = $gravedad === 'grave' ? 'warn' : 'ok';
        header('Location: listar.php?toast='.urlencode($msg).'&tipo='.$tipo);
    } catch (PDOException $e) {
        $_SESSION['error_form'] = 'Error al guardar el incidente. Verificá los datos e intentá de nuevo.';
        header('Location: listar.php');
    }
    exit;
}

if (isset($_GET['revisar']) && es_admin()) {
    $pdo->prepare("UPDATE incidentes SET estado='revisado', updated_by=? WHERE id_incidente=? AND estado='pendiente'")
        ->execute([uid(), (int)$_GET['revisar']]);
    header('Location: listar.php?toast=Incidente+marcado+como+revisado&tipo=ok');
    exit;
}

include '../config/menu.php';

$residentes = $pdo->query("SELECT id_residente, nombre, apellido FROM residentes ORDER BY apellido, nombre")->fetchAll();
$medicos_lista = $pdo->query("SELECT id_medico, nombre, apellido FROM medicos WHERE estado='activo' ORDER BY apellido")->fetchAll();
$usuarios_lista = $pdo->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado='activo' ORDER BY nombre")->fetchAll();

$id_residente  = isset($_GET['residente'])  ? (int)$_GET['residente']        : 0;
$filtro_grav   = $_GET['gravedad']          ?? '';
$filtro_estado = $_GET['estado_inc']        ?? '';
$filtro_medico = $_GET['medico']            ?? '';
$filtro_reg    = isset($_GET['registrado']) ? (int)$_GET['registrado']       : 0;
$filtro_desde  = $_GET['desde']            ?? '';
$filtro_hasta  = $_GET['hasta']            ?? '';

$where = ' WHERE 1=1';
if ($id_residente)  $where .= ' AND i.id_residente='.$id_residente;
if ($filtro_grav)   $where .= ' AND i.gravedad='.$pdo->quote($filtro_grav);
if ($filtro_estado) $where .= ' AND i.estado='.$pdo->quote($filtro_estado);
if ($filtro_medico) $where .= ' AND i.medico_interviniente LIKE '.$pdo->quote('%'.$filtro_medico.'%');
if ($filtro_reg)    $where .= ' AND i.id_usuario='.$filtro_reg;
if ($filtro_desde)  $where .= ' AND DATE(i.fecha_hora)>='.$pdo->quote($filtro_desde);
if ($filtro_hasta)  $where .= ' AND DATE(i.fecha_hora)<='.$pdo->quote($filtro_hasta);

$incidentes = $pdo->query("
    SELECT i.*, r.nombre res_nombre, r.apellido res_apellido,
           CONCAT(u.nombre, ' ', COALESCE(u.apellido,'')) registrado_por
    FROM incidentes i
    JOIN residentes r ON r.id_residente = i.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
    $where
    ORDER BY i.fecha_hora DESC
")->fetchAll();

$graves_sin_revisar = array_filter($incidentes, fn($i) => $i['gravedad']==='grave' && $i['estado']==='pendiente');

function gravedad_class($g) {
    return match($g) { 'grave'=>'pill-mal', 'moderado'=>'pill-aviso', default=>'pill-neutro' };
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

<?php if (!empty($_SESSION['error_form'])): ?>
<div style="background:#fdecea;border-left:4px solid #E05C5C;padding:12px 16px;border-radius:6px;margin-bottom:16px">
<?= htmlspecialchars($_SESSION['error_form']); unset($_SESSION['error_form']); ?>
</div>
<?php endif; ?>

<?php if (count($graves_sin_revisar) > 0): ?>
<div style="background:#fdecea;border-left:5px solid #c0392b;padding:12px 18px;border-radius:8px;margin-bottom:16px;display:flex;gap:12px;align-items:center">
    <span style="font-size:24px"></span>
    <div>
        <b style="color:#c0392b">Hay <?= count($graves_sin_revisar) ?> incidente<?= count($graves_sin_revisar)>1?'s':'' ?> grave<?= count($graves_sin_revisar)>1?'s':'' ?> sin revisar</b><br>
        <span style="font-size:13px;color:#555">Carla debe marcarlos como revisados desde este panel.</span>
    </div>
</div>
<?php endif; ?>


<div class="card no-imprimir" style="margin-bottom:16px">
    <form method="get">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:10px">
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Residente</label>
                <select name="residente" class="buscador" style="width:100%">
                    <option value="0">Todos</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Registrado por</label>
                <select name="registrado" class="buscador" style="width:100%">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios_lista as $u): ?>
                        <option value="<?= $u['id_usuario'] ?>" <?= $filtro_reg==$u['id_usuario']?'selected':'' ?>>
                            <?= h($u['nombre'].' '.($u['apellido']??'')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Gravedad</label>
                <select name="gravedad" style="width:130px">
                    <option value="">Todas</option>
                    <option value="leve"     <?= $filtro_grav==='leve'?'selected':'' ?>>Leve</option>
                    <option value="moderado" <?= $filtro_grav==='moderado'?'selected':'' ?>>Moderado</option>
                    <option value="grave"    <?= $filtro_grav==='grave'?'selected':'' ?>>Grave</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Estado</label>
                <select name="estado_inc" style="width:130px">
                    <option value="">Todos</option>
                    <option value="activo"   <?= $filtro_estado==='activo'?'selected':'' ?>>Pendiente</option>
                    <option value="revisado" <?= $filtro_estado==='revisado'?'selected':'' ?>>Revisado</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Desde</label>
                <input type="date" name="desde" value="<?= h($filtro_desde) ?>" style="width:140px">
            </div>
            <div>
                <label style="font-size:12px;font-weight:600;display:block;margin-bottom:3px">Hasta</label>
                <input type="date" name="hasta" value="<?= h($filtro_hasta) ?>" style="width:140px">
            </div>
            <div style="display:flex;gap:8px">
                <button type="submit" class="btn">Filtrar</button>
                <a href="listar.php" class="btn gray">Limpiar</a>
            </div>
        </div>
    </form>
</div>

<?php if (empty($incidentes)): ?>
    <div style="text-align:center;padding:40px;color:#aaa;background:var(--bg-surface);border-radius:10px">
        <div style="font-size:40px;margin-bottom:8px"></div>
        <b>No hay incidentes registrados<?= $id_residente ? ' para este residente' : '' ?></b>
    </div>
<?php else: ?>
<table class="datatable">
    <thead>
        <tr>
            <th>Fecha y hora</th>
            <th>Residente</th>
            <th>Tipo</th>
            <th>Descripción</th>
            <th>Acciones tomadas</th>
            <th>Gravedad</th>
            <th>Médico</th>
            <th>Registrado por</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($incidentes as $i):
        $pendiente = $i['estado'] === 'activo' || $i['estado'] === 'pendiente';
        $grave_pend = $i['gravedad']==='grave' && $pendiente;
    ?>
        <tr style="<?= $grave_pend ? 'background:#fdecea' : '' ?>">
            <td><?= date('d/m/Y H:i', strtotime($i['fecha_hora'])) ?></td>
            <td><?= h($i['res_nombre'].' '.$i['res_apellido']) ?></td>
            <td><span class="badge"><?= h(TIPOS_INCIDENTE[$i['tipo_incidente']] ?? $i['tipo_incidente']) ?></span></td>
            <td style="max-width:220px;font-size:13px"><?= h($i['descripcion']) ?></td>
            <td style="max-width:180px;font-size:13px"><?= h($i['acciones_tomadas']) ?></td>
            <td><span class="pill <?= gravedad_class($i['gravedad']) ?>"><?= gravedad_label($i['gravedad']) ?></span></td>
            <td><span class="small"><?= h($i['medico_interviniente'] ?: '') ?></span></td>
            <td><span class="small"><?= h($i['registrado_por'] ?? '') ?></span></td>
            <td>
                <?php if ($pendiente): ?>
                    <span class="pill pill-aviso" style="display:block;margin-bottom:4px">Pendiente</span>
                    <?php if (es_admin()): ?>
                    <a class="btn green" style="font-size:11px;padding:3px 8px"
                       href="?revisar=<?= $i['id_incidente'] ?>"
                       onclick="return confirmarLink(this,'¿Marcar este incidente como revisado?','Revisar','green')">
                        Marcar revisado
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="pill pill-ok"> Revisado</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<div id="modal-incidente" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:10px;padding:24px 28px;width:100%;max-width:580px;position:relative">
        <button onclick="document.getElementById('modal-incidente').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:var(--text-main)"></button>
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
                    <label style="font-size:13px;font-weight:600">Tipo de incidente *</label>
                    <select name="tipo_incidente" required style="width:100%;box-sizing:border-box">
                        <?php foreach (TIPOS_INCIDENTE as $k => $v): ?>
                            <option value="<?= $k ?>"><?= $v ?></option>
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
