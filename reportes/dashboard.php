<?php
require_once '../config/session.php';
solo_admin();
require_once '../config/db.php';

$hasta_g = $_GET['hasta'] ?? date('Y-m-d');
$desde_g = $_GET['desde'] ?? date('Y-m-d', strtotime('-30 days'));
if ($desde_g > $hasta_g) [$desde_g, $hasta_g] = [$hasta_g, $desde_g];
$dt_desde = $desde_g . ' 00:00:00';
$dt_hasta = $hasta_g . ' 23:59:59';



$fuera_rango = $pdo->query("SELECT DISTINCT r.nombre, r.apellido, cc.tipo_control,
    cc.valor, cc.unidad, cc.fecha_hora
    FROM controles_clinicos cc
    JOIN residentes r ON r.id_residente=cc.id_residente
    JOIN rangos_control rc ON rc.tipo_control=cc.tipo_control
    WHERE cc.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
      AND (cc.valor < rc.valor_min OR cc.valor > rc.valor_max)
    ORDER BY cc.fecha_hora DESC LIMIT 5")->fetchAll();

$omisiones = $pdo->query("SELECT r.nombre, r.apellido, COUNT(*) omisiones
    FROM administracion_medicacion am
    JOIN residentes r ON r.id_residente=am.id_residente
    WHERE am.estado_adm IN('no_pudo','no_dado')
      AND am.fecha_hora >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY am.id_residente HAVING omisiones >= 3
    ORDER BY omisiones DESC")->fetchAll();

$inc_graves = $pdo->query("SELECT r.nombre, r.apellido, i.tipo_incidente, i.fecha_hora
    FROM incidentes i
    JOIN residentes r ON r.id_residente=i.id_residente
    WHERE i.gravedad='grave' AND i.estado='pendiente'
    ORDER BY i.fecha_hora DESC LIMIT 5")->fetchAll();

$traspasos_pend = $pdo->query("SELECT COUNT(*) FROM traspaso_turno WHERE estado='pendiente'")->fetchColumn();

$adhesion_semanas = $pdo->prepare("SELECT
    DATE_FORMAT(DATE_SUB(fecha_hora, INTERVAL WEEKDAY(fecha_hora) DAY),'%d/%m') semana,
    ROUND(SUM(estado_adm='administrado')*100/COUNT(*)) porc
    FROM administracion_medicacion
    WHERE fecha_hora BETWEEN ? AND ?
    GROUP BY semana ORDER BY MIN(fecha_hora)");
$adhesion_semanas->execute([$dt_desde, $dt_hasta]);
$adhesion_semanas = $adhesion_semanas->fetchAll();

$tareas_res = $pdo->prepare("SELECT r.nombre, r.apellido,
    COUNT(*) total,
    SUM(at.estado_tarea='realizada') realizadas,
    SUM(at.estado_tarea IN('no_realizada','no_pudo')) incumplidas
    FROM administracion_tareas at
    JOIN residentes r ON r.id_residente=at.id_residente
    WHERE at.fecha_hora BETWEEN ? AND ?
    GROUP BY at.id_residente ORDER BY r.apellido");
$tareas_res->execute([$dt_desde, $dt_hasta]);
$tareas_res = $tareas_res->fetchAll();

$inc_mes = $pdo->prepare("SELECT
    DATE_FORMAT(fecha_hora,'%m/%Y') mes,
    COUNT(*) total,
    SUM(gravedad='leve') leves,
    SUM(gravedad='moderado') moderados,
    SUM(gravedad='grave') graves
    FROM incidentes WHERE fecha_hora BETWEEN ? AND ?
    GROUP BY mes ORDER BY MIN(fecha_hora)");
$inc_mes->execute([$dt_desde, $dt_hasta]);
$inc_mes = $inc_mes->fetchAll();

$inc_grav = $pdo->prepare("SELECT
    SUM(gravedad='leve') leves,
    SUM(gravedad='moderado') moderados,
    SUM(gravedad='grave') graves,
    COUNT(*) total
    FROM incidentes WHERE fecha_hora BETWEEN ? AND ?");
$inc_grav->execute([$dt_desde, $dt_hasta]);
$inc_grav = $inc_grav->fetch();

$vis_tipo = $pdo->prepare("SELECT tipo_visita, COUNT(*) n
    FROM visitas
    WHERE COALESCE(hora_ingreso,fecha_programada,created_at) BETWEEN ? AND ?
    GROUP BY tipo_visita ORDER BY n DESC");
$vis_tipo->execute([$dt_desde, $dt_hasta]);
$vis_tipo = $vis_tipo->fetchAll();

$vis_dia = $pdo->prepare("SELECT
    DAYOFWEEK(COALESCE(hora_ingreso,fecha_programada,created_at)) n_dia,
    DAYNAME(COALESCE(hora_ingreso,fecha_programada,created_at)) dia_en,
    COUNT(*) total
    FROM visitas
    WHERE COALESCE(hora_ingreso,fecha_programada,created_at) BETWEEN ? AND ?
    GROUP BY n_dia, dia_en ORDER BY n_dia");
$vis_dia->execute([$dt_desde, $dt_hasta]);
$vis_dia = $vis_dia->fetchAll();
$dias_es = ['Sunday'=>'Dom','Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié',
    'Thursday'=>'Jue','Friday'=>'Vie','Saturday'=>'Sáb'];

$act_res = $pdo->prepare("SELECT r.nombre, r.apellido, COUNT(*) actividades
    FROM actividad_residentes ar
    JOIN residentes r ON r.id_residente=ar.id_residente
    JOIN actividades a ON a.id_actividad=ar.id_actividad
    WHERE a.fecha BETWEEN ? AND ? AND a.estado='activo'
    GROUP BY ar.id_residente ORDER BY actividades DESC");
$act_res->execute([$dt_desde, $dt_hasta]);
$act_res = $act_res->fetchAll();

$trp_semana = $pdo->prepare("SELECT
    DATE_FORMAT(DATE_SUB(COALESCE(fecha_cierre,created_at),
        INTERVAL WEEKDAY(COALESCE(fecha_cierre,created_at)) DAY),'%d/%m') semana,
    SUM(estado='cerrado') cerrados,
    SUM(estado='pendiente') pendientes
    FROM traspaso_turno
    WHERE COALESCE(fecha_cierre,created_at) BETWEEN ? AND ?
    GROUP BY semana ORDER BY MIN(COALESCE(fecha_cierre,created_at))");
$trp_semana->execute([$dt_desde, $dt_hasta]);
$trp_semana = $trp_semana->fetchAll();

$ctrl_evol = $pdo->prepare("SELECT
    r.nombre, r.apellido,
    DATE_FORMAT(cc.fecha_hora,'%d/%m') fecha,
    cc.valor, cc.tipo_control, cc.unidad
    FROM controles_clinicos cc
    JOIN residentes r ON r.id_residente=cc.id_residente
    WHERE cc.fecha_hora BETWEEN ? AND ?
    ORDER BY cc.tipo_control, cc.fecha_hora");
$ctrl_evol->execute([$dt_desde, $dt_hasta]);

$ctrl_por_tipo = [];
foreach ($ctrl_evol->fetchAll() as $c) {
    $tipo = $c['tipo_control'];
    $ctrl_por_tipo[$tipo]['labels'][] = $c['fecha'];
    $ctrl_por_tipo[$tipo]['values'][] = (float)$c['valor'];
    $ctrl_por_tipo[$tipo]['unidad']   = $c['unidad'];
}

$rangos_ctrl = $pdo->query("SELECT tipo_control, valor_min, valor_max FROM rangos_control")->fetchAll();
$rangos_ctrl_map = [];
foreach ($rangos_ctrl as $r) $rangos_ctrl_map[$r['tipo_control']] = $r;

$ocup_hab = $pdo->query("SELECT h.numero,
    (SELECT COUNT(*) FROM residentes r WHERE r.id_habitacion=h.id_habitacion AND r.estado='activo') ocupadas,
    (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion=h.id_habitacion AND c.estado!='mantenimiento') total
    FROM habitaciones h WHERE h.habilitada=1 ORDER BY h.numero")->fetchAll();

include '../config/menu.php';

$COLORES = ['#7eb8c9','#8cbf9e','#c4a97d','#a89ec0','#c98080','#82b5c0','#b5a87e','#9abfb5'];
?>

<h1>Dashboard</h1>

<div class="card no-imprimir" style="margin-bottom:20px">
    <b style="display:block;margin-bottom:8px">Rango global</b>
    <form method="get" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Desde</label>
            <input type="date" name="desde" value="<?= h($desde_g) ?>">
        </div>
        <div>
            <label style="font-size:13px;font-weight:600;display:block">Hasta</label>
            <input type="date" name="hasta" value="<?= h($hasta_g) ?>">
        </div>
        <button type="submit" class="btn">Aplicar a todos</button>
    </form>
    <div style="margin-top:8px;display:flex;gap:6px;flex-wrap:wrap">
        <a class="btn gray" href="?desde=<?=date('Y-m-d')?>&hasta=<?=date('Y-m-d')?>">Hoy</a>
        <a class="btn gray" href="?desde=<?=date('Y-m-d',strtotime('-6 days'))?>&hasta=<?=date('Y-m-d')?>">7 días</a>
        <a class="btn gray" href="?desde=<?=date('Y-m-d',strtotime('-29 days'))?>&hasta=<?=date('Y-m-d')?>">30 días</a>
  
    </div>
</div>



<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px">

<div class="card" style="margin:0">
    <b style="font-size:14px">Adhesión a la medicación</b>
    <?php if (empty($adhesion_semanas)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-adhesion" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Tareas por residente</b>
    <?php if (empty($tareas_res)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-tareas" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Incidentes por mes</b>
    <?php if (empty($inc_mes)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-incidentes" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Gravedad de incidentes</b>
    <?php if ((int)$inc_grav['total'] !== 0): ?>
    <canvas id="g-gravedad" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Visitas por tipo</b>
    <?php if (empty($vis_tipo)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-vis-tipo" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Visitas por día de la semana</b>
    <?php if (empty($vis_dia)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-vis-dia" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Actividades por residente</b>
    <?php if (empty($act_res)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-actividades" height="180"></canvas>
    <?php endif; ?>
</div>

<div class="card" style="margin:0">
    <b style="font-size:14px">Traspasos de turno</b>
    <?php if (empty($trp_semana)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-traspasos" height="180"></canvas>
    <?php endif; ?>
</div>

<?php
$cfg_ctl_dash = [
    'tension_sistolica'   => ['label'=>'Tensión sistólica',   'color'=>'#c98080'],
    'tension_diastolica'  => ['label'=>'Tensión diastólica',  'color'=>'#7eb8c9'],
    'glucemia'            => ['label'=>'Glucemia',             'color'=>'#c4a97d'],
    'saturacion_oxigeno'  => ['label'=>'Saturación O₂',       'color'=>'#8cbf9e'],
    'frecuencia_cardiaca' => ['label'=>'Frec. cardíaca',       'color'=>'#a89ec0'],
    'temperatura'         => ['label'=>'Temperatura',          'color'=>'#e67e22'],
    'peso'                => ['label'=>'Peso',                 'color'=>'#2D9B8A'],
];
foreach ($ctrl_por_tipo as $tipo_ctl => $g_ctl):
    $cfg_c = $cfg_ctl_dash[$tipo_ctl] ?? ['label'=>$tipo_ctl,'color'=>'#7eb8c9'];
    $cid   = 'g-ctl-'.$tipo_ctl;
?>
<div class="card" style="margin:0">
    <b style="font-size:14px"><?= h($cfg_c['label']) ?> (<?= h($g_ctl['unidad']) ?>)</b>
    <canvas id="<?= $cid ?>" height="180"></canvas>
</div>
<?php endforeach; ?>

<div class="card" style="margin:0">
    <b style="font-size:14px">Ocupación por habitación</b>
    <?php if (empty($ocup_hab)): ?>
        <p class="small" style="color:#aaa">Sin datos.</p>
    <?php else: ?>
    <canvas id="g-ocupacion" height="180"></canvas>
    <?php endif; ?>
</div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
var C = <?= json_encode($COLORES) ?>;
var opts_base = { plugins: { legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } } } };

<?php if (!empty($adhesion_semanas)): ?>
new Chart(document.getElementById('g-adhesion'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($adhesion_semanas,'semana')) ?>,
        datasets: [{ label: 'Adhesión %',
            data: <?= json_encode(array_column($adhesion_semanas,'porc')) ?>,
            borderColor: '#7eb8c9', backgroundColor: 'rgba(126,184,201,.15)',
            tension: .3, fill: true, pointRadius: 3 }]
    },
    options: { ...opts_base, scales: { y: { min:0, max:100, ticks:{ callback: v=>v+'%', font:{size:10} } } },
        plugins: { legend: { display: false } } }
});
<?php endif; ?>

<?php if (!empty($tareas_res)): ?>
new Chart(document.getElementById('g-tareas'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nombre'].' '.$r['apellido'], $tareas_res)) ?>,
        datasets: [
            { label: 'Realizadas',  data: <?= json_encode(array_column($tareas_res,'realizadas')) ?>,  backgroundColor: '#8cbf9e' },
            { label: 'Incumplidas', data: <?= json_encode(array_column($tareas_res,'incumplidas')) ?>, backgroundColor: '#c98080' }
        ]
    },
    options: { ...opts_base, scales: { x:{stacked:true,ticks:{font:{size:10}}}, y:{stacked:true,ticks:{stepSize:1,font:{size:10}}} } }
});
<?php endif; ?>

<?php if (!empty($inc_mes)): ?>
new Chart(document.getElementById('g-incidentes'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($inc_mes,'mes')) ?>,
        datasets: [
            { label: 'Leves',     data: <?= json_encode(array_column($inc_mes,'leves')) ?>,     backgroundColor: '#8cbf9e' },
            { label: 'Moderados', data: <?= json_encode(array_column($inc_mes,'moderados')) ?>, backgroundColor: '#c4a97d' },
            { label: 'Graves',    data: <?= json_encode(array_column($inc_mes,'graves')) ?>,    backgroundColor: '#c98080' }
        ]
    },
    options: { ...opts_base, scales: { x:{stacked:true,ticks:{font:{size:10}}}, y:{stacked:true,ticks:{stepSize:1,font:{size:10}}} } }
});
<?php endif; ?>

<?php if ((int)$inc_grav['total'] > 0): ?>
new Chart(document.getElementById('g-gravedad'), {
    type: 'doughnut',
    data: {
        labels: ['Leves','Moderados','Graves'],
        datasets: [{ data: [<?= (int)$inc_grav['leves'] ?>,<?= (int)$inc_grav['moderados'] ?>,<?= (int)$inc_grav['graves'] ?>],
            backgroundColor: ['#8cbf9e','#c4a97d','#c98080'], borderWidth: 1 }]
    },
    options: { ...opts_base, cutout: '60%' }
});
<?php endif; ?>

<?php if (!empty($vis_tipo)): ?>
new Chart(document.getElementById('g-vis-tipo'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($v) => ucfirst(str_replace('_',' ',$v['tipo_visita'])), $vis_tipo)) ?>,
        datasets: [{ data: <?= json_encode(array_column($vis_tipo,'n')) ?>,
            backgroundColor: ['#7eb8c9','#8cbf9e','#c4a97d','#a89ec0','#c98080'], borderWidth: 1 }]
    },
    options: { ...opts_base, cutout: '55%' }
});
<?php endif; ?>

<?php if (!empty($vis_dia)): ?>
new Chart(document.getElementById('g-vis-dia'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => $dias_es[$d['dia_en']] ?? $d['dia_en'], $vis_dia)) ?>,
        datasets: [{
            data: <?= json_encode(array_column($vis_dia,'total')) ?>,
            backgroundColor: <?= json_encode(array_slice($COLORES,0,count($vis_dia))) ?>
        }]
    },
    options: { ...opts_base, plugins:{ legend:{display:false} }, scales:{ y:{ ticks:{stepSize:1,font:{size:10}} } } }
});
<?php endif; ?>

<?php if (!empty($act_res)): ?>
new Chart(document.getElementById('g-actividades'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nombre'], $act_res)) ?>,
        datasets: [{ data: <?= json_encode(array_column($act_res,'actividades')) ?>,
            backgroundColor: <?= json_encode(array_slice($COLORES,0,count($act_res))) ?> }]
    },
    options: { ...opts_base, indexAxis:'y', plugins:{legend:{display:false}},
        scales:{ x:{ ticks:{stepSize:1,font:{size:10}} } } }
});
<?php endif; ?>

<?php if (!empty($trp_semana)): ?>
new Chart(document.getElementById('g-traspasos'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($trp_semana,'semana')) ?>,
        datasets: [
            { label: 'Cerrados',   data: <?= json_encode(array_column($trp_semana,'cerrados')) ?>,   backgroundColor: '#8cbf9e' },
            { label: 'Pendientes', data: <?= json_encode(array_column($trp_semana,'pendientes')) ?>, backgroundColor: '#c98080' }
        ]
    },
    options: { ...opts_base, scales:{ x:{stacked:true,ticks:{font:{size:10}}}, y:{stacked:true,ticks:{stepSize:1,font:{size:10}}} } }
});
<?php endif; ?>

<?php foreach ($ctrl_por_tipo as $tipo_ctl => $g_ctl):
    $cfg_c = $cfg_ctl_dash[$tipo_ctl] ?? ['label'=>$tipo_ctl,'color'=>'#7eb8c9'];
    $cid   = 'g-ctl-'.$tipo_ctl;
    $rng_c = $rangos_ctrl_map[$tipo_ctl] ?? null;
    $es_bar = $tipo_ctl === 'peso';
?>
new Chart(document.getElementById('<?= $cid ?>'), {
    type: '<?= $es_bar ? 'bar' : 'line' ?>',
    data: {
        labels: <?= json_encode($g_ctl['labels']) ?>,
        datasets: [
            { label: <?= json_encode($cfg_c['label']) ?>, data: <?= json_encode($g_ctl['values']) ?>,
              borderColor:'<?= $cfg_c['color'] ?>', backgroundColor:'<?= $es_bar ? $cfg_c['color'].'88' : $cfg_c['color'].'22' ?>',
              tension:.3, pointRadius:2, fill:<?= $es_bar ? 'false' : 'true' ?> }
            <?php if ($rng_c): ?>
            ,{ label:'Máx', data:Array(<?= count($g_ctl['labels']) ?>).fill(<?= $rng_c['valor_max'] ?>),
               borderColor:'rgba(231,76,60,.3)', borderDash:[5,5], pointRadius:0, fill:false, type:'line' }
            ,{ label:'Mín', data:Array(<?= count($g_ctl['labels']) ?>).fill(<?= $rng_c['valor_min'] ?>),
               borderColor:'rgba(39,174,96,.3)', borderDash:[5,5], pointRadius:0, fill:false, type:'line' }
            <?php endif; ?>
        ]
    },
    options: { ...opts_base, scales:{ y:{ticks:{font:{size:10}}}, x:{ticks:{font:{size:9},maxRotation:45}} } }
});
<?php endforeach; ?>

<?php if (!empty($ocup_hab)): ?>
new Chart(document.getElementById('g-ocupacion'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($h) => 'Hab.'.$h['numero'], $ocup_hab)) ?>,
        datasets: [
            { label: 'Ocupadas', data: <?= json_encode(array_column($ocup_hab,'ocupadas')) ?>,
              backgroundColor: '#c98080' },
            { label: 'Libres',   data: <?= json_encode(array_map(fn($h)=>max(0,(int)$h['total']-(int)$h['ocupadas']), $ocup_hab)) ?>,
              backgroundColor: '#8cbf9e' }
        ]
    },
    options: { ...opts_base, scales:{x:{stacked:true,ticks:{font:{size:10}}}, y:{stacked:true,ticks:{stepSize:1,font:{size:10}}}} }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>