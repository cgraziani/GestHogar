<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)  $where .= ' AND at.id_residente='.$id_residente;
if ($filtro_estado === 'incumplidas') $where .= " AND at.estado_tarea IN('no_realizada','no_pudo')";
elseif ($filtro_estado !== '')        $where .= ' AND at.estado_tarea='.$pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT at.fecha_hora, at.estado_tarea, at.motivo,
    r.nombre rn, r.apellido ra,
    tr.titulo, tr.descripcion, tr.horario,
    CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) cuidadora
    FROM administracion_tareas at
    JOIN residentes r         ON r.id_residente=at.id_residente
    LEFT JOIN tareas_residente tr ON tr.id_tarea=at.id_tarea
    LEFT JOIN usuarios u      ON u.id_usuario=at.id_cuidadora_usuario
    WHERE at.fecha_hora BETWEEN ? AND ? $where
    ORDER BY at.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$por_res = $pdo->prepare("SELECT r.nombre, r.apellido,
    COUNT(*) total,
    SUM(at.estado_tarea='realizada') realizadas,
    SUM(at.estado_tarea IN('no_realizada','no_pudo')) incumplidas,
    ROUND(SUM(at.estado_tarea='realizada')*100/COUNT(*)) porc
    FROM administracion_tareas at
    JOIN residentes r ON r.id_residente=at.id_residente
    WHERE at.fecha_hora BETWEEN ? AND ?
    " . ($id_residente ? "AND at.id_residente=$id_residente" : "") . "
    GROUP BY at.id_residente ORDER BY porc ASC");
$por_res->execute([$desde_dt, $hasta_dt]);
$datos_res = $por_res->fetchAll();

$por_cui = $pdo->prepare("SELECT u.nombre, u.apellido,
    COUNT(*) total,
    SUM(at.estado_tarea='realizada') realizadas,
    SUM(at.estado_tarea IN('no_realizada','no_pudo')) incumplidas
    FROM administracion_tareas at
    LEFT JOIN usuarios u ON u.id_usuario=at.id_cuidadora_usuario
    WHERE at.fecha_hora BETWEEN ? AND ?
    GROUP BY at.id_cuidadora_usuario ORDER BY incumplidas DESC");
$por_cui->execute([$desde_dt, $hasta_dt]);
$datos_cui = $por_cui->fetchAll();

$sin_registro = $pdo->query("SELECT COUNT(*) FROM tareas_residente tr
    WHERE tr.estado='activo'
    AND NOT EXISTS (SELECT 1 FROM administracion_tareas a WHERE a.id_tarea=tr.id_tarea)")->fetchColumn();

$cols = ['Fecha y hora','Residente','Tarea','Horario pautado','Estado','Motivo','Registrado por'];
$filas = []; $hechas=0; $incumplidas=0;
foreach ($datos as $d) {
    if ($d['estado_tarea']==='realizada') $hechas++;
    if (in_array($d['estado_tarea'],['no_realizada','no_pudo'],true)) $incumplidas++;
    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'].' '.$d['ra'],
        $d['titulo'] ?: 'Tarea eliminada',
        $d['horario'] ?: '',
        pill_estado_medicacion($d['estado_tarea']),
        $d['motivo'] ?: '',
        $d['cuidadora'] ?: '',
    ];
}

$total = count($datos);
$cumplimiento = $total ? round($hechas*100/$total) : 0;
$resumen = [
    ['Registros',    $total,           'Tareas registradas en el período'],
    ['Realizadas',   $hechas,          'Se completaron'],
    ['Incumplidas',  $incumplidas,     'No se hicieron o no se pudo'],
    ['Cumplimiento', $cumplimiento.'%','Realizadas sobre el total'],
];

$sel = fn($v) => $filtro_estado===$v?'selected':'';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="incumplidas" '.$sel('incumplidas').'>Solo incumplidas</option>'
       . '<option value="realizada" '.$sel('realizada').'>Realizadas</option>'
       . '<option value="no_pudo" '.$sel('no_pudo').'>No se pudo</option>'
       . '<option value="no_realizada" '.$sel('no_realizada').'>No realizadas</option>'
       . '</select></div>';

salida_reporte('Reporte de tareas de rutina', 'tareas', $cols, $filas);

echo '<h1>Reporte de tareas de rutina</h1>';
barra_filtros(['residente'=>true,'extra'=>$extra]);
resumen_reporte($resumen);

if ($sin_registro > 0) {
    echo '<div class="card" style="border-left:4px solid #e6a700">'
       . '<b>'.(int)$sin_registro.' tarea'.($sin_registro>1?'s':'').' sin ningún registro de cumplimiento.</b></div>';
}
?>

<div class="grid" style="grid-template-columns:1fr 1fr">
    <?php if (!empty($datos_res)): ?>
    <div class="card">
        <h3 style="margin-top:0">Cumplimiento por residente</h3>
        <canvas id="grafico-res" height="200"></canvas>
    </div>
    <?php endif; ?>

    <?php if (!empty($datos_cui)): ?>
    <div class="card">
        <h3 style="margin-top:0">Registros por cuidadora</h3>
        <canvas id="grafico-cui" height="200"></canvas>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($datos_res)): ?>
<div class="card">
    <h3 style="margin-top:0">Detalle por residente</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Residente</th><th>Tareas</th><th>Realizadas</th><th>Incumplidas</th><th>Cumplimiento</th></tr></thead>
        <tbody>
        <?php foreach ($datos_res as $r): ?>
            <tr>
                <td><?= h($r['nombre'].' '.$r['apellido']) ?></td>
                <td><?= (int)$r['total'] ?></td>
                <td><?= (int)$r['realizadas'] ?></td>
                <td><?= (int)$r['incumplidas']>0 ? '<b style="color:#e74c3c">'.(int)$r['incumplidas'].'</b>' : '0' ?></td>
                <td><b style="color:<?= $r['porc']>=90?'#27ae60':($r['porc']>=70?'#e67e22':'#e74c3c') ?>"><?= (int)$r['porc'] ?>%</b></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php tabla_reporte($cols, $filas); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($datos_res)): ?>
new Chart(document.getElementById('grafico-res'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nombre'].' '.$r['apellido'], $datos_res)) ?>,
        datasets:[
            { label:'Realizadas',  data:<?= json_encode(array_column($datos_res,'realizadas')) ?>,  backgroundColor:'#2D9B8A' },
            { label:'Incumplidas', data:<?= json_encode(array_column($datos_res,'incumplidas')) ?>, backgroundColor:'#e74c3c' }
        ]
    },
    options:{ scales:{x:{stacked:true},y:{stacked:true,ticks:{stepSize:1}}}, plugins:{legend:{position:'bottom'}} }
});
<?php endif; ?>
<?php if (!empty($datos_cui)): ?>
new Chart(document.getElementById('grafico-cui'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($c) => ($c['nombre']??'').' '.($c['apellido']??''), $datos_cui)) ?>,
        datasets:[
            { label:'Realizadas',  data:<?= json_encode(array_column($datos_cui,'realizadas')) ?>,  backgroundColor:'#2D9B8A' },
            { label:'Incumplidas', data:<?= json_encode(array_column($datos_cui,'incumplidas')) ?>, backgroundColor:'#e74c3c' }
        ]
    },
    options:{ scales:{x:{stacked:true},y:{stacked:true,ticks:{stepSize:1}}}, plugins:{legend:{position:'bottom'}} }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>