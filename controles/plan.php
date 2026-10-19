<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once __DIR__ . '/../config/accesos_prof.php';
if (!(es_admin() || (es_profesional() && prof_puede_controles($pdo))))
    acceso_denegado('Solo la administradora y los médicos/enfermeros pueden acceder al plan de controles.');

const TIPOS_CONTROL = [
    'tension_sistolica'   => ['Tensión sistólica',    'mmHg'],
    'tension_diastolica'  => ['Tensión diastólica',   'mmHg'],
    'frecuencia_cardiaca' => ['Frecuencia cardíaca',  'lpm'],
    'temperatura'         => ['Temperatura',          '°C'],
    'peso'                => ['Peso',                 'kg'],
    'glucemia'            => ['Glucemia',             'mg/dL'],
    'saturacion_oxigeno'  => ['Saturación de oxígeno','%'],
];

const FRECUENCIAS = [
    'diario'        => 'Diario',
    'dos_veces_dia' => 'Dos veces por día',
    'semanal'       => 'Semanal',
    'quincenal'     => 'Quincenal',
    'mensual'       => 'Mensual',
    'a_demanda'     => 'A demanda',
];

function volver_plan($id_residente, $mensaje, $tipo = 'ok') {
    header('Location: plan.php?residente=' . (int)$id_residente
         . '&toast=' . urlencode($mensaje) . '&tipo=' . $tipo);
    exit;
}

if (isset($_GET['baja_control'])) {
    $id_res  = (int)($_GET['residente'] ?? 0);
    $tipo_ctl = $_GET['baja_control'] ?? '';
    if ($id_res && $tipo_ctl) {
        $pdo->prepare("UPDATE controles_residente SET estado='baja' WHERE id_residente=? AND tipo_control=?")
            ->execute([$id_res, $tipo_ctl]);
        volver_plan($id_res, 'Control dado de baja. Ya no aparecerá en la agenda del turno.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_plan'])) {
    $id_res = (int)$_POST['id_residente'];
    if (!$id_res) volver_plan(0, 'Elegí un residente.', 'error');

    if (es_profesional()) {
        $med_id = $pdo->prepare("SELECT id_medico FROM medicos WHERE id_usuario=?");
        $med_id->execute([uid()]);
        $med_id = $med_id->fetchColumn();
        $asig = $pdo->prepare("SELECT COUNT(*) FROM visitas_medicas WHERE id_medico=? AND id_residente=?");
        $asig->execute([$med_id, $id_res]);
        if (!$asig->fetchColumn()) volver_plan(0, 'No tenés acceso a este residente.', 'error');
    }

    $elegidos = $_POST['control'] ?? [];
    $frec     = $_POST['frecuencia'] ?? [];
    $obs      = $_POST['obs'] ?? [];

    foreach (array_keys(TIPOS_CONTROL) as $tipo) {
        $activo = isset($elegidos[$tipo]);
        $f      = $frec[$tipo] ?? 'diario';
        if (!array_key_exists($f, FRECUENCIAS)) $f = 'diario';
        $o = trim($obs[$tipo] ?? '') ?: null;

        $pdo->prepare("INSERT INTO controles_residente
                (id_residente, tipo_control, frecuencia, observaciones, estado, created_by)
                VALUES (?,?,?,?,?,?)
                ON DUPLICATE KEY UPDATE frecuencia=VALUES(frecuencia),
                                        observaciones=VALUES(observaciones),
                                        estado=VALUES(estado)")
            ->execute([$id_res, $tipo, $f, $o, $activo ? 'activo' : 'baja', uid()]);
    }

    volver_plan($id_res, 'Plan de controles guardado');
}

include '../config/menu.php';

$residentes = es_profesional()
    ? query_residentes($pdo)
    : $pdo->query("SELECT id_residente, nombre, apellido FROM residentes
        WHERE estado IN('activo','pausado') ORDER BY apellido, nombre")->fetchAll();

$id_residente = isset($_GET['residente']) ? (int)$_GET['residente'] : 0;

$plan = [];
if ($id_residente) {
    $st = $pdo->prepare('SELECT * FROM controles_residente WHERE id_residente=?');
    $st->execute([$id_residente]);
    foreach ($st->fetchAll() as $p) $plan[$p['tipo_control']] = $p;
}

$resumen = [];
foreach ($pdo->query("SELECT id_residente, COUNT(*) n FROM controles_residente
                      WHERE estado='activo' GROUP BY id_residente")->fetchAll() as $r) {
    $resumen[$r['id_residente']] = $r['n'];
}
?>

<h1>Controles clínicos</h1>

<div style="display:flex;gap:8px;margin-bottom:20px">
    <a class="tab-btn active" href="plan.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">
        Plan de controles
    </a>
    <a class="tab-btn" href="listar.php<?= $id_residente ? '?residente='.$id_residente : '' ?>">
        Historial de controles
    </a>
</div>
<style>
.tab-btn{padding:8px 18px;border:2px solid var(--color-primary,#2D9B8A);background:transparent;
    color:var(--color-primary,#2D9B8A);border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;transition:.2s}
.tab-btn.active{background:var(--color-primary,#2D9B8A);color:#fff}
</style>


<div class="card no-imprimir">
    <form method="get" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap">
        <div class="campo">
            <label style="font-size:13px;font-weight:600">Residente</label>
            <select name="residente" class="buscador" required style="min-width:260px">
                <option value="">Elegir residente...</option>
                <?php foreach ($residentes as $r): ?>
                    <option value="<?= $r['id_residente'] ?>" <?= $id_residente == $r['id_residente'] ? 'selected' : '' ?>>
                        <?= h($r['nombre'] . ' ' . $r['apellido']) ?>
                        <?= isset($resumen[$r['id_residente']]) ? ' (' . $resumen[$r['id_residente']] . ' control(es))' : ' (sin plan)' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn">Ver plan</button>
        <a class="btn gray" href="listar.php">Ir a registrar controles</a>
    </form>
</div>

<?php if ($id_residente): ?>
<form method="post">
    <input type="hidden" name="guardar_plan" value="1">
    <input type="hidden" name="id_residente" value="<?= $id_residente ?>">

    <table class="no-datatable" style="width:100%">
        <thead>
            <tr>
                <th style="width:50px">Activo</th>
                <th>Control</th>
                <th>Unidad</th>
                <th>Frecuencia</th>
                <th>Observaciones</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach (TIPOS_CONTROL as $tipo => [$etiqueta, $unidad]):
            $actual = $plan[$tipo] ?? null;
            $activo = $actual && $actual['estado'] === 'activo';
        ?>
            <tr>
                <td style="text-align:center">
                    <input type="checkbox" name="control[<?= $tipo ?>]" value="1" <?= $activo ? 'checked' : '' ?> style="width:auto">
                </td>
                <td><b><?= h($etiqueta) ?></b></td>
                <td><span class="small"><?= h($unidad) ?></span></td>
                <td>
                    <select name="frecuencia[<?= $tipo ?>]">
                        <?php foreach (FRECUENCIAS as $k => $v): ?>
                            <option value="<?= $k ?>" <?= ($actual && $actual['frecuencia'] === $k) ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input name="obs[<?= $tipo ?>]" value="<?= h($actual['observaciones'] ?? '') ?>" style="width:100%">
                </td>
                <td>
                    <?php if ($activo): ?>
                        <span class="pill pill-ok">Activo</span>
                        <a class="btn red" style="font-size:11px;padding:4px 8px"
                           href="?residente=<?= $id_residente ?>&baja_control=<?= $tipo ?>"
                           onclick="return confirmarLink(this,'¿Dar de baja este control? Ya no aparecerá en la agenda del turno.','Dar de baja','red')">
                           Dar de baja
                        </a>
                    <?php elseif ($actual): ?>
                        <span class="pill pill-neutro">Inactivo</span>
                    <?php else: ?>
                        <span class="small" style="color:#aaa">Sin configurar</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div style="margin-top:16px;display:flex;gap:10px">
        <button type="submit" class="btn">Guardar plan</button>
        <a class="btn gray" href="listar.php?residente=<?= $id_residente ?>">Ver controles registrados</a>
    </div>
</form>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
