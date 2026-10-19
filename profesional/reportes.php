<?php
require_once '../config/session.php';
require_once '../config/db.php';
require_once '../config/accesos_prof.php';
if (!es_profesional()) acceso_denegado();

$med = $pdo->prepare("SELECT * FROM medicos WHERE id_usuario=? AND estado='activo'");
$med->execute([uid()]);
$med = $med->fetch();
if (!$med) acceso_denegado();

$tipo      = $med['tipo_profesional'];
$id_medico = $med['id_medico'];

$puede_med    = in_array($tipo, TIPOS_PUEDE_MEDICACION);
$puede_ctrl   = in_array($tipo, TIPOS_PUEDE_CONTROLES);
$puede_tareas = in_array($tipo, TIPOS_PUEDE_TAREAS);
$puede_rep    = !in_array($tipo, ['peluquero']); 

$residentes = $pdo->prepare("SELECT DISTINCT r.id_residente, r.nombre, r.apellido
    FROM visitas_medicas vm JOIN residentes r ON r.id_residente=vm.id_residente
    WHERE vm.id_medico=? AND r.estado='activo' ORDER BY r.apellido, r.nombre");
$residentes->execute([$id_medico]);
$residentes = $residentes->fetchAll();

$id_res = (int)($_GET['residente'] ?? 0);
if ($id_res) {
    $ids_asig = array_column($residentes, 'id_residente');
    if (!in_array($id_res, $ids_asig)) $id_res = 0;
}

$reporte = $_GET['reporte'] ?? '';
$desde   = $_GET['desde']   ?? date('Y-m-d', strtotime('-30 days'));
$hasta   = $_GET['hasta']   ?? date('Y-m-d');

$datos = [];
if ($id_res && $reporte) {
    switch ($reporte) {
        case 'evolucion':
            $st = $pdo->prepare("SELECT cc.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) reg_por
                FROM controles_clinicos cc
                LEFT JOIN usuarios u ON u.id_usuario=cc.id_usuario
                WHERE cc.id_residente=? AND DATE(cc.fecha_hora) BETWEEN ? AND ?
                ORDER BY cc.fecha_hora ASC");
            $st->execute([$id_res,$desde,$hasta]);
            $datos = $st->fetchAll();

            $graficos = [];
            $rangos_map = [];
            foreach ($pdo->query("SELECT tipo_control, valor_min, valor_max FROM rangos_control")->fetchAll() as $r)
                $rangos_map[$r['tipo_control']] = $r;

            foreach ($datos as $d) {
                $tipo = $d['tipo_control'];
                $graficos[$tipo]['labels'][] = date('d/m H:i', strtotime($d['fecha_hora']));
                $graficos[$tipo]['values'][] = (float)$d['valor'];
                $graficos[$tipo]['unidad']   = $d['unidad'];
            }
            break;
        case 'medicacion':
            $st = $pdo->prepare("SELECT am.*, m.nombre med_nombre, m.presentacion,
                CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora
                FROM administracion_medicacion am
                JOIN medicacion_residente mr ON mr.id_plan=am.id_plan
                JOIN medicamentos m ON m.id_medicamento=mr.id_medicamento
                LEFT JOIN usuarios u ON u.id_usuario=am.id_cuidadora_usuario
                WHERE am.id_residente=? AND DATE(am.fecha_hora) BETWEEN ? AND ?
                ORDER BY am.fecha_hora DESC");
            $st->execute([$id_res,$desde,$hasta]);
            $datos = $st->fetchAll();
            break;
        case 'incidentes':
            $st = $pdo->prepare("SELECT i.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) reg_por
                FROM incidentes i
                LEFT JOIN usuarios u ON u.id_usuario=i.id_usuario
                WHERE i.id_residente=? AND DATE(i.fecha_hora) BETWEEN ? AND ?
                ORDER BY i.fecha_hora DESC");
            $st->execute([$id_res,$desde,$hasta]);
            $datos = $st->fetchAll();
            break;
        case 'tareas':
            $st = $pdo->prepare("SELECT at.*, tr.titulo, tr.tipo,
                CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora
                FROM administracion_tareas at
                JOIN tareas_residente tr ON tr.id_tarea=at.id_tarea
                LEFT JOIN usuarios u ON u.id_usuario=at.id_cuidadora_usuario
                WHERE at.id_residente=? AND DATE(at.fecha_hora) BETWEEN ? AND ?
                ORDER BY at.fecha_hora DESC");
            $st->execute([$id_res,$desde,$hasta]);
            $datos = $st->fetchAll();
            break;
        case 'controles':
            $st = $pdo->prepare("SELECT cr.tipo_control, cr.frecuencia,
                (SELECT cc.valor FROM controles_clinicos cc WHERE cc.id_residente=cr.id_residente
                 AND cc.tipo_control=cr.tipo_control ORDER BY cc.fecha_hora DESC LIMIT 1) ultimo_valor,
                (SELECT cc.unidad FROM controles_clinicos cc WHERE cc.id_residente=cr.id_residente
                 AND cc.tipo_control=cr.tipo_control ORDER BY cc.fecha_hora DESC LIMIT 1) unidad,
                (SELECT cc.fecha_hora FROM controles_clinicos cc WHERE cc.id_residente=cr.id_residente
                 AND cc.tipo_control=cr.tipo_control ORDER BY cc.fecha_hora DESC LIMIT 1) ultima_fecha
                FROM controles_residente cr
                WHERE cr.id_residente=? AND cr.estado='activo'");
            $st->execute([$id_res]);
            $datos = $st->fetchAll();
            break;
    }
}

$LABELS_CTL = ['tension_sistolica'=>'T. sistólica','tension_diastolica'=>'T. diastólica',
    'glucemia'=>'Glucemia','temperatura'=>'Temperatura','peso'=>'Peso',
    'saturacion_oxigeno'=>'Saturación O₂','frecuencia_cardiaca'=>'Frec. cardíaca'];

$res_sel = null;
if ($id_res) {
    foreach ($residentes as $r) {
        if ($r['id_residente'] == $id_res) { $res_sel = $r; break; }
    }
}

include '../config/menu.php';
?>

<h1>Reportes</h1>

<style>
.filtros-rep {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) minmax(160px, 2fr) minmax(140px, 1fr) minmax(140px, 1fr) auto;
    gap: 10px;
    align-items: end;
}
.filtros-rep .campo > label {
    display: block; font-size: 12px; font-weight: 600; margin: 0 0 4px;
}
.filtros-rep input,
.filtros-rep select {
    height: 44px; margin: 0; padding: 0 12px; box-sizing: border-box;
    font: inherit; font-size: 14px;
}
.filtros-rep .ts-wrapper { min-width: 0; margin: 0; }
.filtros-rep .ts-control {
    height: 44px; min-height: 44px; box-sizing: border-box;
    display: flex; align-items: center; flex-wrap: nowrap;
    padding: 0 34px 0 12px !important;
    border: 1px solid var(--border-input, #d0d7e2) !important;
    border-radius: 10px !important;
}
.filtros-rep .btn {
    height: 44px; padding: 0 22px;
    display: inline-flex; align-items: center; justify-content: center;
}
@media (max-width: 900px) {
    .filtros-rep { grid-template-columns: 1fr 1fr; }
    .filtros-rep .campo:first-child { grid-column: 1 / -1; }
    .filtros-rep .btn { grid-column: 1 / -1; }
}
</style>
<div class="card" style="margin-bottom:16px">
    <form method="get">
        <div class="filtros-rep">
            <div class="campo">
                <label for="f-residente">Residente</label>
                <select id="f-residente" name="residente" class="buscador">
                    <option value="">Elegir residente</option>
                    <?php foreach ($residentes as $r): ?>
                        <option value="<?= $r['id_residente'] ?>" <?= $id_res==$r['id_residente']?'selected':'' ?>>
                            <?= h($r['nombre'].' '.$r['apellido']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="f-reporte">Reporte</label>
                <select id="f-reporte" name="reporte">
                    <option value="">Elegir reporte</option>
                    <option value="evolucion"  <?= $reporte==='evolucion' ?'selected':'' ?>>Evolución de salud</option>
                    <?php if ($puede_med): ?>
                    <option value="medicacion" <?= $reporte==='medicacion'?'selected':'' ?>>Medicación</option>
                    <?php endif; ?>
                    <option value="incidentes" <?= $reporte==='incidentes'?'selected':'' ?>>Incidentes</option>
                    <?php if ($puede_tareas): ?>
                    <option value="tareas"     <?= $reporte==='tareas'    ?'selected':'' ?>>Tareas de rutina</option>
                    <?php endif; ?>
                    <option value="controles"  <?= $reporte==='controles' ?'selected':'' ?>>Plan de controles</option>
                </select>
            </div>
            <div class="campo">
                <label for="f-desde">Desde</label>
                <input id="f-desde" type="date" name="desde" value="<?= h($desde) ?>">
            </div>
            <div class="campo">
                <label for="f-hasta">Hasta</label>
                <input id="f-hasta" type="date" name="hasta" value="<?= h($hasta) ?>">
            </div>
            <button type="submit" class="btn">Ver reporte</button>
        </div>
    </form>
</div>

<?php if (!$id_res): ?>
<div style="text-align:center;padding:48px;color:var(--text-muted);background:var(--bg-surface);border-radius:10px">
    <div style="font-size:36px;margin-bottom:10px"></div>
    <b>Elegí un residente para ver sus reportes</b>
</div>
<?php elseif (!$reporte): ?>
<div style="text-align:center;padding:48px;color:var(--text-muted);background:var(--bg-surface);border-radius:10px">
    <div style="font-size:36px;margin-bottom:10px"></div>
    <b>Elegí un reporte para <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></b>
</div>

<?php elseif ($reporte === 'evolucion'): ?>
<h2 style="margin-bottom:16px">Evolución de salud: <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></h2>

<?php if (!empty($graficos)): ?>

<?php
$cfg_graficos = [
    'tension_sistolica'   => ['color'=>'#c98080','label'=>'Tensión sistólica','tipo'=>'line'],
    'tension_diastolica'  => ['color'=>'#7eb8c9','label'=>'Tensión diastólica','tipo'=>'line'],
    'glucemia'            => ['color'=>'#c4a97d','label'=>'Glucemia','tipo'=>'line'],
    'saturacion_oxigeno'  => ['color'=>'#8cbf9e','label'=>'Saturación O₂','tipo'=>'line'],
    'frecuencia_cardiaca' => ['color'=>'#a89ec0','label'=>'Frec. cardíaca','tipo'=>'line'],
    'temperatura'         => ['color'=>'#e67e22','label'=>'Temperatura','tipo'=>'line'],
    'peso'                => ['color'=>'#2D9B8A','label'=>'Peso','tipo'=>'bar'],
];

$tiene_sist = isset($graficos['tension_sistolica']);
$tiene_dias = isset($graficos['tension_diastolica']);
$tension_combinada = $tiene_sist && $tiene_dias;
$procesados = [];
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(480px,1fr));gap:16px">

<?php if ($tension_combinada): $procesados[] = 'tension_sistolica'; $procesados[] = 'tension_diastolica'; ?>
<div class="card" style="margin:0">
    <b style="display:block;margin-bottom:8px">Tensión arterial (mmHg)</b>
    <canvas id="g-tension" height="200"></canvas>
    <script>
    new Chart(document.getElementById('g-tension'), {
        type: 'line',
        data: {
            labels: <?= json_encode($graficos['tension_sistolica']['labels']) ?>,
            datasets: [
                { label: 'Sistólica', data: <?= json_encode($graficos['tension_sistolica']['values']) ?>,
                  borderColor:'#c98080', backgroundColor:'rgba(201,128,128,.1)', tension:.3, pointRadius:3 },
                { label: 'Diastólica', data: <?= json_encode($graficos['tension_diastolica']['values']) ?>,
                  borderColor:'#7eb8c9', backgroundColor:'rgba(126,184,201,.1)', tension:.3, pointRadius:3 }
                <?php if (isset($rangos_map['tension_sistolica'])): ?>
                ,{ label: 'Límite sistólica', data: Array(<?= count($graficos['tension_sistolica']['labels']) ?>).fill(<?= $rangos_map['tension_sistolica']['valor_max'] ?>),
                   borderColor:'rgba(201,128,128,.3)', borderDash:[5,5], pointRadius:0, fill:false }
                ,{ label: 'Límite diastólica', data: Array(<?= count($graficos['tension_diastolica']['labels']) ?>).fill(<?= $rangos_map['tension_diastolica']['valor_max'] ?>),
                   borderColor:'rgba(126,184,201,.3)', borderDash:[5,5], pointRadius:0, fill:false }
                <?php endif; ?>
            ]
        },
        options: { plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } },
            scales:{ y:{ ticks:{ font:{size:10} } }, x:{ ticks:{ font:{size:9}, maxRotation:45 } } } }
    });
    </script>
</div>
<?php endif; ?>

<?php foreach ($graficos as $tipo => $g):
    if (in_array($tipo, $procesados)) continue;
    $cfg = $cfg_graficos[$tipo] ?? ['color'=>'#7eb8c9','label'=>$tipo,'tipo'=>'line'];
    $canvas_id = 'g-'.$tipo;
    $rng = $rangos_map[$tipo] ?? null;
?>
<div class="card" style="margin:0">
    <b style="display:block;margin-bottom:8px"><?= h($cfg['label']) ?> (<?= h($g['unidad']) ?>)</b>
    <canvas id="<?= $canvas_id ?>" height="200"></canvas>
    <script>
    new Chart(document.getElementById('<?= $canvas_id ?>'), {
        type: '<?= $cfg['tipo'] ?>',
        data: {
            labels: <?= json_encode($g['labels']) ?>,
            datasets: [
                { label: <?= json_encode($cfg['label']) ?>, data: <?= json_encode($g['values']) ?>,
                  borderColor:'<?= $cfg['color'] ?>', backgroundColor:'<?= $cfg['tipo']==='bar' ? $cfg['color'].'99' : "rgba(0,0,0,.05)" ?>',
                  tension:.3, pointRadius:3, fill:<?= $cfg['tipo']==='line' ? 'true' : 'false' ?> }
                <?php if ($rng): ?>
                ,{ label: 'Máx normal', data: Array(<?= count($g['labels']) ?>).fill(<?= $rng['valor_max'] ?>),
                   borderColor:'rgba(231,76,60,.35)', borderDash:[5,5], pointRadius:0, fill:false, type:'line' }
                ,{ label: 'Mín normal', data: Array(<?= count($g['labels']) ?>).fill(<?= $rng['valor_min'] ?>),
                   borderColor:'rgba(39,174,96,.35)', borderDash:[5,5], pointRadius:0, fill:false, type:'line' }
                <?php endif; ?>
            ]
        },
        options: { plugins:{ legend:{ position:'bottom', labels:{ boxWidth:12, font:{size:11} } } },
            scales:{ y:{ ticks:{ font:{size:10} } }, x:{ ticks:{ font:{size:9}, maxRotation:45 } } } }
    });
    </script>
</div>
<?php endforeach; ?>

</div>
<?php endif; ?>

<?php elseif ($reporte === 'medicacion' && $puede_med): ?>
<div class="card">
    <h2 style="margin-top:0">Medicación: <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></h2>
    <?php if (!empty($datos)): ?>
    <table class="datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Fecha</th><th>Medicamento</th><th>Estado</th><th>Observaciones</th><th>Cuidadora</th></tr></thead>
        <tbody>
        <?php
        $est_labels = ['administrado'=>'Administrado','no_pudo'=>'No se pudo','no_dado'=>'No administrado'];
        $est_cls    = ['administrado'=>'pill-ok','no_pudo'=>'pill-aviso','no_dado'=>'pill-mal'];
        foreach ($datos as $d): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($d['fecha_hora'])) ?></td>
            <td><b><?= h($d['med_nombre']) ?></b> <span class="small"><?= h($d['presentacion']) ?></span></td>
            <td><span class="pill <?= $est_cls[$d['estado_adm']] ?? 'pill-neutro' ?>">
                <?= $est_labels[$d['estado_adm']] ?? $d['estado_adm'] ?></span></td>
            <td><span class="small"><?= h($d['observaciones'] ?? '') ?></span></td>
            <td><span class="small"><?= h($d['cuidadora'] ?? '') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($reporte === 'incidentes'): ?>
<div class="card">
    <h2 style="margin-top:0">Incidentes: <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></h2>
    <?php if (!empty($datos)): ?>
    <table class="datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Fecha</th><th>Tipo</th><th>Descripción</th><th>Acciones</th><th>Gravedad</th><th>Estado</th></tr></thead>
        <tbody>
        <?php
        $grav_cls = ['leve'=>'pill-neutro','moderado'=>'pill-aviso','grave'=>'pill-mal'];
        foreach ($datos as $d): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($d['fecha_hora'])) ?></td>
            <td><span class="badge"><?= h($d['tipo_incidente']) ?></span></td>
            <td style="font-size:12px"><?= h($d['descripcion']) ?></td>
            <td style="font-size:12px"><?= h($d['acciones_tomadas'] ?? '') ?></td>
            <td><span class="pill <?= $grav_cls[$d['gravedad']] ?? 'pill-neutro' ?>"><?= h(ucfirst($d['gravedad'])) ?></span></td>
            <td><span class="pill <?= $d['estado']==='revisado'?'pill-ok':'pill-aviso' ?>">
                <?= $d['estado']==='revisado' ? 'Revisado' : 'Pendiente' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($reporte === 'tareas' && $puede_tareas): ?>
<div class="card">
    <h2 style="margin-top:0">Tareas de rutina: <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></h2>
    <?php if (!empty($datos)): ?>
    <table class="datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Fecha</th><th>Tarea</th><th>Tipo</th><th>Estado</th><th>Motivo</th><th>Cuidadora</th></tr></thead>
        <tbody>
        <?php
        $t_cls = ['realizada'=>'pill-ok','no_realizada'=>'pill-mal','no_pudo'=>'pill-aviso'];
        $t_lbl = ['realizada'=>'Realizada','no_realizada'=>'No realizada','no_pudo'=>'No se pudo'];
        foreach ($datos as $d): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($d['fecha_hora'])) ?></td>
            <td><b><?= h($d['titulo']) ?></b></td>
            <td><span class="badge"><?= h($d['tipo']) ?></span></td>
            <td><span class="pill <?= $t_cls[$d['estado_tarea']] ?? 'pill-neutro' ?>">
                <?= $t_lbl[$d['estado_tarea']] ?? $d['estado_tarea'] ?></span></td>
            <td><span class="small"><?= h($d['motivo'] ?? '') ?></span></td>
            <td><span class="small"><?= h($d['cuidadora'] ?? '') ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php elseif ($reporte === 'controles'): ?>
<div class="card">
    <h2 style="margin-top:0">Plan de controles: <?= h($res_sel['nombre'].' '.$res_sel['apellido']) ?></h2>
    <?php if (!empty($datos)): ?>
    <table class="no-datatable" style="width:100%;font-size:13px">
        <thead><tr><th>Control</th><th>Frecuencia</th><th>Último valor</th><th>Última medición</th></tr></thead>
        <tbody>
        <?php foreach ($datos as $d): ?>
        <tr>
            <td><b><?= h($LABELS_CTL[$d['tipo_control']] ?? $d['tipo_control']) ?></b></td>
            <td><?= h($d['frecuencia']) ?></td>
            <td><?= $d['ultimo_valor'] ? '<b>'.h($d['ultimo_valor']).' '.h($d['unidad']).'</b>' : '' ?></td>
            <td><?= $d['ultima_fecha'] ? date('d/m/Y H:i', strtotime($d['ultima_fecha'])) : '' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
