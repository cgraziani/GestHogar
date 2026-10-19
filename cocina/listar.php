<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/accesos_prof.php';
$acceso_cocina = es_admin() || es_cocinera() || es_cuidadora()
    || (es_profesional() && prof_puede_restricciones($pdo));
if (!$acceso_cocina) acceso_denegado('No tenés permisos para acceder a esta sección.');

$yo = uid();
$MOMENTOS = momentos_comida();
$upload_dir = __DIR__ . '/../uploads/cocina/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

const TIPOS_RESTRICCION = [
    'alergia'      => 'Alergia',
    'intolerancia' => 'Intolerancia',
    'patologia'    => 'Patología',
    'textura'      => 'Textura / consistencia',
    'religiosa'    => 'Religiosa',
    'preferencia'  => 'Preferencia',
];
const SEVERIDADES = ['leve' => 'Leve', 'moderada' => 'Moderada', 'grave' => 'Grave'];

function volver_cocina($msg, $tipo = 'ok', $tab = 'comidas') {
    header('Location: listar.php?toast=' . urlencode($msg) . '&tipo=' . $tipo . '&tab=' . $tab);
    exit;
}

function detectar_conflictos(PDO $pdo, $texto, array $ids) {
    if (empty($ids)) return [];
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare("SELECT rr.*, r.nombre, r.apellido
                         FROM restricciones_residente rr
                         JOIN residentes r ON r.id_residente=rr.id_residente
                         WHERE rr.estado='activo' AND rr.id_residente IN ($marcas)");
    $st->execute($ids);
    $norm = function($s) {
        return strtr(mb_strtolower($s,'UTF-8'), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
    };
    $tn = $norm($texto);
    $out = [];
    foreach ($st->fetchAll() as $rr) {
        $al = trim($norm($rr['alimento']));
        if ($al !== '' && str_contains($tn, $al)) $out[] = $rr;
    }
    return $out;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_comida'])) {
    if (!(es_admin() || es_cocinera())) acceso_denegado();
    $titulo  = trim($_POST['titulo'] ?? '');
    $desc    = trim($_POST['descripcion'] ?? '');
    $fecha   = trim($_POST['fecha'] ?? date('Y-m-d'));
    $momento = $_POST['momento'] ?? 'almuerzo';
    $hora    = trim($_POST['hora'] ?? '') ?: null;
    $partic  = $_POST['residentes'] ?? [];
    $forzar  = isset($_POST['forzar']);

    if (!array_key_exists($momento, $MOMENTOS)) $momento = 'almuerzo';
    if ($titulo === '')        volver_cocina('El título de la comida es obligatorio.', 'error');
    if (strlen($titulo) > 200) volver_cocina('El título no puede superar los 200 caracteres.', 'error');
    if (empty($partic))        volver_cocina('Elegí al menos un residente al que se le sirve.', 'error');
    if (!$fecha || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) volver_cocina('La fecha no es válida.', 'error');

    $conflictos = detectar_conflictos($pdo, $titulo.' '.$desc, array_map('intval', $partic));
    if ($conflictos && !$forzar) {
        $_SESSION['cocina_conflictos'] = $conflictos;
        $_SESSION['cocina_datos']      = $_POST;
        header('Location: listar.php?conflicto=1&tab=comidas'); exit;
    }

    $foto_path = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'], true)) {
            $fname = 'comida_'.time().'_'.$yo.'.'.$ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir.$fname);
            $foto_path = 'uploads/cocina/'.$fname;
        }
    }

    try {
        $pdo->prepare("INSERT INTO cocina(id_usuario,tipo,momento,hora,titulo,descripcion,foto,fecha,estado,created_by)
                       VALUES(?,'receta',?,?,?,?,?,?,'activo',?)")
            ->execute([$yo, $momento, $hora, $titulo, $desc ?: null, $foto_path, $fecha, $yo]);
        $id_comida = $pdo->lastInsertId();
        $obs = $_POST['obs_residente'] ?? [];
        foreach ($partic as $id_res) {
            $pdo->prepare('INSERT INTO comida_residente(id_cocina,id_residente,observaciones) VALUES(?,?,?)')
                ->execute([$id_comida, (int)$id_res, trim($obs[$id_res] ?? '') ?: null]);
        }
        volver_cocina($conflictos ? 'Comida registrada pese a la restricción.' : 'Comida registrada correctamente.', 'ok', 'comidas');
    } catch (PDOException $e) {
        volver_cocina('Error al guardar la comida. Verificá los datos e intentá de nuevo.', 'error', 'comidas');
    }
}

if (isset($_GET['baja']) && (es_admin() || es_cocinera())) {
    $pdo->prepare("UPDATE cocina SET estado='baja', updated_by=? WHERE id_cocina=?")
        ->execute([$yo, (int)$_GET['baja']]);
    volver_cocina('Registro dado de baja.', 'warn', 'comidas');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nueva_restriccion'])) {
    require_once '../config/accesos_prof.php';
    if (!(es_admin() || es_cocinera() || (es_profesional() && prof_puede_restricciones($pdo)))) acceso_denegado();
    $id_res   = (int)$_POST['id_residente'];
    $alimento = trim($_POST['alimento'] ?? '');
    $tipo     = $_POST['tipo'] ?? 'intolerancia';
    $sev      = $_POST['severidad'] ?? 'moderada';
    if (!$id_res)          volver_cocina('Elegí un residente.', 'error', 'restricciones');
    if (!$alimento)        volver_cocina('Indicá qué alimento no puede consumir.', 'error', 'restricciones');
    if (strlen($alimento) > 100) volver_cocina('El nombre del alimento no puede superar los 100 caracteres.', 'error', 'restricciones');
    if (!array_key_exists($tipo, TIPOS_RESTRICCION)) $tipo = 'intolerancia';
    if (!array_key_exists($sev, SEVERIDADES))        $sev  = 'moderada';
    $q = $pdo->prepare('SELECT COUNT(*) FROM restricciones_residente WHERE id_residente=? AND alimento=? AND estado=\'activo\'');
    $q->execute([$id_res, $alimento]);
    if ($q->fetchColumn() > 0) {
        volver_cocina('Ya existe una restricción activa de "' . htmlspecialchars($alimento) . '" para ese residente.', 'warn', 'restricciones');
    }
    try {
        $pdo->prepare('INSERT INTO restricciones_residente(id_residente,tipo,alimento,severidad,detalle,created_by) VALUES(?,?,?,?,?,?)')
            ->execute([$id_res, $tipo, $alimento, $sev, trim($_POST['detalle'] ?? '') ?: null, $yo]);
        volver_cocina('Restricción registrada correctamente.', 'ok', 'restricciones');
    } catch (PDOException $e) {
        volver_cocina('Error al guardar la restricción. Intentá de nuevo.', 'error', 'restricciones');
    }
}

if (isset($_GET['baja_restr'])) {
    $pdo->prepare("UPDATE restricciones_residente SET estado='baja' WHERE id_restriccion=?")
        ->execute([(int)$_GET['baja_restr']]);
    volver_cocina('Restricción dada de baja.', 'warn', 'restricciones');
}

include '../config/menu.php';

$residentes = es_profesional()
    ? query_residentes($pdo)
    : $pdo->query("SELECT id_residente, nombre, apellido FROM residentes
        WHERE estado='activo' ORDER BY apellido, nombre")->fetchAll();

$restr_por_res = [];
foreach ($pdo->query("SELECT id_residente, tipo, alimento, severidad
                      FROM restricciones_residente WHERE estado='activo'")->fetchAll() as $rr) {
    $restr_por_res[$rr['id_residente']][] = $rr;
}

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$comidas = $pdo->prepare("SELECT c.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) preparo,
    (SELECT GROUP_CONCAT(CONCAT(r.nombre,' ',r.apellido) ORDER BY r.apellido SEPARATOR ', ')
       FROM comida_residente cr JOIN residentes r ON r.id_residente=cr.id_residente
      WHERE cr.id_cocina=c.id_cocina) comensales,
    (SELECT COUNT(*) FROM comida_residente cr WHERE cr.id_cocina=c.id_cocina) n_comensales
    FROM cocina c
    LEFT JOIN usuarios u ON u.id_usuario=c.id_usuario
    WHERE c.estado='activo' AND c.tipo='receta' AND c.fecha=?
    ORDER BY FIELD(c.momento,'desayuno','media_manana','almuerzo','merienda','cena','colacion'), c.hora");
$comidas->execute([$fecha_filtro]);
$comidas = $comidas->fetchAll();

$id_residente   = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;
$where_restr    = $id_residente ? ' AND rr.id_residente='.$id_residente : '';
$restricciones  = $pdo->query("SELECT rr.*, r.nombre rn, r.apellido ra, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cargo_por
    FROM restricciones_residente rr
    JOIN residentes r ON r.id_residente=rr.id_residente
    LEFT JOIN usuarios u ON u.id_usuario=rr.created_by
    WHERE rr.estado='activo' $where_restr
    ORDER BY FIELD(rr.severidad,'grave','moderada','leve'), r.apellido")->fetchAll();

$tab_activa = $_GET['tab'] ?? 'comidas';

$conflictos = $_SESSION['cocina_conflictos'] ?? null;
$datos_prev = $_SESSION['cocina_datos']      ?? null;
unset($_SESSION['cocina_conflictos'], $_SESSION['cocina_datos']);
?>

<h1>Cocina y alimentación</h1>

<?php if ($conflictos): ?>
<div class="card" style="border-left:5px solid var(--border-estado-problema)">
    <h2 style="margin-top:0">Hay residentes con restricción para esta comida</h2>
    <ul>
    <?php foreach ($conflictos as $c): ?>
        <li>
            <b><?= h($c['nombre'].' '.$c['apellido']) ?></b>:
            <?= h($c['tipo']) ?> a <b><?= h($c['alimento']) ?></b>
            <span class="pill <?= $c['severidad']==='grave'?'pill-mal':'pill-aviso' ?>"><?= h($c['severidad']) ?></span>
        </li>
    <?php endforeach; ?>
    </ul>
    <form method="post" style="margin-top:12px">
        <?php foreach (['titulo','descripcion','fecha','momento','hora'] as $campo): ?>
            <input type="hidden" name="<?= $campo ?>" value="<?= h($datos_prev[$campo] ?? '') ?>">
        <?php endforeach; ?>
        <?php foreach (($datos_prev['residentes'] ?? []) as $idr): ?>
            <input type="hidden" name="residentes[]" value="<?= (int)$idr ?>">
        <?php endforeach; ?>
        <input type="hidden" name="nueva_comida" value="1">
        <input type="hidden" name="forzar" value="1">
        <button type="submit" class="btn red">Servir igual y registrarlo</button>
        <a class="btn gray" href="listar.php">Cancelar y corregir</a>
    </form>
</div>
<?php endif; ?>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0 0">
    <button class="tab-btn <?= $tab_activa==='comidas'?'active':'' ?>"
            onclick="mostrarTab('tab-comidas',this)">Comidas del día</button>
    <button class="tab-btn <?= $tab_activa==='restricciones'?'active':'' ?>"
            onclick="mostrarTab('tab-restricciones',this)">Restricciones alimentarias</button>
</div>

<style>
.tab-btn { padding:8px 18px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;transition:.2s }
.tab-btn.active { background:var(--color-primary,#2D9B8A);color:#fff }
.tab-content { display:none }
.tab-content.active { display:block }
</style>

<div id="tab-comidas" class="tab-content <?= $tab_activa==='comidas'?'active':'' ?>">

    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin:16px 0">
        <form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="tab" value="comidas">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Día</label>
                <input type="date" name="fecha" value="<?= h($fecha_filtro) ?>">
            </div>
            <button type="submit" class="btn gray">Ver</button>
        </form>
        <?php if (es_admin() || es_cocinera()): ?>
        <button class="btn" onclick="document.getElementById('modal-comida').style.display='flex'">
            + Registrar comida
        </button>
        <?php endif; ?>
    </div>

    <?php if (empty($comidas)): ?>
        <div class="card" style="text-align:center;color:var(--text-muted);padding:40px">
            
            <b>No hay comidas registradas para el <?= date('d/m/Y', strtotime($fecha_filtro)) ?>.</b>
        </div>
    <?php else: ?>
    <table class="datatable">
        <thead><tr>
            <th>Momento</th><th>Hora</th><th>Comida</th><th>Descripción</th>
            <th>Comensales</th><th>Foto</th><th>Preparó</th>
            <?php if (es_admin() || es_cocinera()): ?><th></th><?php endif; ?>
        </tr></thead>
        <tbody>
        <?php foreach ($comidas as $c): ?>
            <tr>
                <td><span class="badge"><?= h($MOMENTOS[$c['momento']][0] ?? $c['momento'] ?? '') ?></span></td>
                <td><b><?= $c['hora'] ? substr($c['hora'],0,5) : ($MOMENTOS[$c['momento']][1] ?? '') ?></b></td>
                <td><b><?= h($c['titulo']) ?></b></td>
                <td><span class="small"><?= h($c['descripcion'] ?: '') ?></span></td>
                <td><b><?= (int)$c['n_comensales'] ?></b><br><span class="small"><?= h($c['comensales'] ?: '') ?></span></td>
                <td>
                    <?php if ($c['foto']): ?>
                        <a href="<?= BASE_URL ?>/<?= h($c['foto']) ?>" target="_blank">
                            <img src="<?= BASE_URL ?>/<?= h($c['foto']) ?>" style="width:48px;height:48px;object-fit:cover;border-radius:6px">
                        </a>
                    <?php else: ?><span class="small"></span><?php endif; ?>
                </td>
                <td><span class="small"><?= h($c['preparo'] ?: '') ?></span></td>
                <?php if (es_admin() || es_cocinera()): ?>
                <td>
                    <a class="btn red" href="?baja=<?= (int)$c['id_cocina'] ?>&fecha=<?= h($fecha_filtro) ?>&tab=comidas"
                       onclick="return confirmarLink(this,'¿Dar de baja este registro?','Dar de baja','red')">Dar de baja</a>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div id="tab-restricciones" class="tab-content <?= $tab_activa==='restricciones'?'active':'' ?>">

    <?php if (es_admin() || es_cocinera()): ?>
    <div class="card" style="margin-top:16px">
        <h3 style="margin-top:0">Agregar restricción</h3>
        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;align-items:end">
            <input type="hidden" name="nueva_restriccion" value="1">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Residente *</label>
                <select name="id_residente" class="buscador" required>
                    <option value="">Elegir...</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Tipo</label>
                <select name="tipo">
                    <?php foreach (TIPOS_RESTRICCION as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Alimento *</label>
                <input name="alimento" required>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Severidad</label>
                <select name="severidad">
                    <?php foreach (SEVERIDADES as $k=>$v): ?><option value="<?= $k ?>" <?= $k==='moderada'?'selected':'' ?>><?= $v ?></option><?php endforeach; ?>
                </select>
            </div>
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Detalle</label>
                <input name="detalle">
            </div>
            <div><button type="submit" class="btn">Agregar</button></div>
        </form>
    </div>
    <?php endif; ?>

    <div style="margin:16px 0;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
        <form method="get" style="display:flex;gap:10px;align-items:flex-end">
            <input type="hidden" name="tab" value="restricciones">
            <div>
                <label style="font-size:13px;font-weight:600;display:block">Filtrar por residente</label>
                <select name="residente" class="buscador" style="min-width:220px">
                    <option value="0">Todos</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_residente==$r['id_residente']?'selected':'' ?>>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn gray">Filtrar</button>
            <?php if ($id_residente): ?>
                <a href="?tab=restricciones" class="btn gray">Ver todos</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($restricciones)): ?>
        <div class="card" style="text-align:center;color:var(--text-muted)">
            No hay restricciones cargadas<?= $id_residente?' para este residente':'' ?>.
        </div>
    <?php else: ?>
    <table class="datatable">
        <thead><tr>
            <th>Residente</th><th>Tipo</th><th>No puede consumir</th>
            <th>Severidad</th><th>Detalle</th><th>Cargó</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($restricciones as $x): ?>
            <tr>
                <td><?= h($x['rn'].' '.$x['ra']) ?></td>
                <td><span class="badge"><?= h(TIPOS_RESTRICCION[$x['tipo']] ?? $x['tipo']) ?></span></td>
                <td><b><?= h($x['alimento']) ?></b></td>
                <td>
                    <span class="pill <?= $x['severidad']==='grave'?'pill-mal':($x['severidad']==='moderada'?'pill-aviso':'pill-neutro') ?>">
                        <?= h(SEVERIDADES[$x['severidad']] ?? $x['severidad']) ?>
                    </span>
                </td>
                <td><span class="small"><?= h($x['detalle'] ?: '') ?></span></td>
                <td><span class="small"><?= h($x['cargo_por'] ?: '') ?></span></td>
                <td>
                    <a class="btn red" href="?baja_restr=<?= (int)$x['id_restriccion'] ?>&tab=restricciones"
                       onclick="return confirmarLink(this,'¿Dar de baja esta restricción?','Dar de baja','red')">Dar de baja</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php if (es_admin() || es_cocinera()): ?>
<div id="modal-comida" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--bg-surface);color:var(--text-main);border-radius:12px;padding:24px 28px;width:100%;max-width:680px;position:relative;max-height:90vh;overflow-y:auto">
        <button onclick="document.getElementById('modal-comida').style.display='none'"
                style="position:absolute;top:12px;right:16px;background:none;border:none;font-size:22px;cursor:pointer;color:inherit">×</button>
        <h3 style="margin:0 0 16px">Registrar comida</h3>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="nueva_comida" value="1">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px">
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Momento *</label>
                    <select name="momento" id="c-momento" required onchange="sugerirHora()">
                        <?php foreach ($MOMENTOS as $k=>[$et,$hs]): ?>
                            <option value="<?= $k ?>" data-hora="<?= $hs ?>" <?= $k==='almuerzo'?'selected':'' ?>><?= $et ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Hora</label>
                    <input type="time" name="hora" id="c-hora" value="12:00">
                </div>
                <div>
                    <label style="font-size:13px;font-weight:600;display:block">Fecha *</label>
                    <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600;display:block">¿Qué se preparó? *</label>
                <input name="titulo" required style="width:100%;box-sizing:border-box">
            </div>
            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600;display:block">Descripción</label>
                <input name="descripcion" style="width:100%;box-sizing:border-box">
            </div>
            <div style="margin-top:14px">
                <label style="font-size:13px;font-weight:600;display:block">¿A qué residentes se sirve? *</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:8px;max-height:230px;overflow-y:auto;border:1px solid var(--border-color);border-radius:8px;padding:10px">
                <?php foreach ($residentes as $r): ?>
                    <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px">
                        <input type="checkbox" name="residentes[]" value="<?= $r['id_residente'] ?>" style="width:auto;margin-top:3px">
                        <span>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                            <?php foreach ($restr_por_res[$r['id_residente']] ?? [] as $rr): ?>
                                <br><span class="pill <?= $rr['severidad']==='grave'?'pill-mal':'pill-aviso' ?>" style="font-size:10px">sin <?= h($rr['alimento']) ?></span>
                            <?php endforeach; ?>
                        </span>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <div style="margin-top:10px">
                <label style="font-size:13px;font-weight:600;display:block">Foto del plato (opcional)</label>
                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp" style="width:100%;box-sizing:border-box">
            </div>
            <div style="margin-top:18px;display:flex;gap:10px">
                <button type="submit" class="btn">Registrar</button>
                <button type="button" class="btn gray" onclick="document.getElementById('modal-comida').style.display='none'">Cancelar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
function sugerirHora() {
    var op = document.getElementById('c-momento').selectedOptions[0];
    document.getElementById('c-hora').value = op.dataset.hora;
}
var mc = document.getElementById('modal-comida');
if (mc) mc.addEventListener('click', function(e){ if(e.target===this) this.style.display='none'; });

function mostrarTab(id, btn) {
    document.querySelectorAll('.tab-content').forEach(function(el){ el.classList.remove('active'); });
    document.querySelectorAll('.tab-btn').forEach(function(el){ el.classList.remove('active'); });
    document.getElementById(id).classList.add('active');
    btn.classList.add('active');
    setTimeout(function(){ $.fn.dataTable && $.fn.dataTable.tables({visible:true,api:true}).columns.adjust(); }, 10);
}

<?php if ($tab_activa === 'restricciones'): ?>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.querySelectorAll('.tab-btn')[1];
    if (btn) mostrarTab('tab-restricciones', btn);
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
