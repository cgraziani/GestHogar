<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($id_residente)         $where .= ' AND v.id_residente = '.$id_residente;
if ($filtro_estado !== '') $where .= ' AND v.estado = '.$pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT v.*, r.nombre rn, r.apellido ra,
    u.nombre registro_por
    FROM visitas v
    JOIN residentes r   ON r.id_residente=v.id_residente
    LEFT JOIN usuarios u ON u.id_usuario=v.id_usuario
    WHERE COALESCE(v.hora_ingreso, v.fecha_programada, v.created_at) BETWEEN ? AND ? $where
    ORDER BY COALESCE(v.hora_ingreso, v.fecha_programada, v.created_at) DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$por_res = $pdo->prepare("SELECT r.nombre, r.apellido, COUNT(*) total,
    SUM(v.estado='finalizada') finalizadas
    FROM visitas v
    JOIN residentes r ON r.id_residente=v.id_residente
    WHERE COALESCE(v.hora_ingreso, v.fecha_programada, v.created_at) BETWEEN ? AND ?
    " . ($id_residente ? "AND v.id_residente=$id_residente" : "") . "
    GROUP BY v.id_residente ORDER BY total DESC");
$por_res->execute([$desde_dt, $hasta_dt]);
$datos_res = $por_res->fetchAll();

$por_tipo = $pdo->prepare("SELECT tipo_visita, COUNT(*) n
    FROM visitas
    WHERE COALESCE(hora_ingreso, fecha_programada, created_at) BETWEEN ? AND ?
    " . ($id_residente ? "AND id_residente=$id_residente" : "") . "
    GROUP BY tipo_visita ORDER BY n DESC");
$por_tipo->execute([$desde_dt, $hasta_dt]);
$datos_tipo = $por_tipo->fetchAll();

$por_dia = $pdo->prepare("SELECT
    DAYNAME(COALESCE(hora_ingreso, fecha_programada, created_at)) dia_en,
    DAYOFWEEK(COALESCE(hora_ingreso, fecha_programada, created_at)) n_dia,
    COUNT(*) total
    FROM visitas
    WHERE COALESCE(hora_ingreso, fecha_programada, created_at) BETWEEN ? AND ?
    " . ($id_residente ? "AND id_residente=$id_residente" : "") . "
    GROUP BY n_dia, dia_en ORDER BY n_dia");
$por_dia->execute([$desde_dt, $hasta_dt]);
$datos_dia = $por_dia->fetchAll();
$dias_es = ['Sunday'=>'Dom','Monday'=>'Lun','Tuesday'=>'Mar','Wednesday'=>'Mié',
    'Thursday'=>'Jue','Friday'=>'Vie','Saturday'=>'Sáb'];

$estados_pill = [
    'finalizada'=>['Finalizada','pill-ok'],'en_curso'=>['En curso','pill-aviso'],
    'agendada'=>['Agendada','pill-neutro'],'cancelada'=>['Cancelada','pill-mal'],
    'solicitada'=>['Solicitada','pill-aviso'],'rechazada'=>['Rechazada','pill-mal'],
];

$cols = ['Visitante','Parentesco','Residente','Tipo','Ingreso','Egreso','Permanencia','Estado','Observaciones'];
$filas = []; $en_curso=0; $finalizadas=0; $minutos_total=0; $con_perm=0;
foreach ($datos as $d) {
    if ($d['estado']==='en_curso')   $en_curso++;
    if ($d['estado']==='finalizada') $finalizadas++;
    $permanencia = '';
    if ($d['hora_ingreso'] && $d['hora_egreso']) {
        $min = (strtotime($d['hora_egreso']) - strtotime($d['hora_ingreso'])) / 60;
        if ($min > 0) { $minutos_total += $min; $con_perm++; }
        $permanencia = floor($min/60).'h '.str_pad((int)($min%60),2,'0',STR_PAD_LEFT).'m';
    }
    [$txt,$cls] = $estados_pill[$d['estado']] ?? [$d['estado'],'pill-neutro'];
    $filas[] = [
        trim($d['nombre_visitante'].(' '.($d['apellido_visitante']??''))),
        $d['parentesco'],
        $d['rn'].' '.$d['ra'],
        ucfirst(str_replace('_',' ',$d['tipo_visita'])),
        $d['hora_ingreso'] ? fecha_hora($d['hora_ingreso']) : '',
        $d['hora_egreso']  ? fecha_hora($d['hora_egreso'])  : '',
        $permanencia,
        pill($txt,$cls),
        $d['observaciones'] ?: '',
    ];
}
$promedio = $con_perm ? round($minutos_total/$con_perm) : 0;
$resumen = [
    ['Visitas',          count($datos)],
    ['Finalizadas',      $finalizadas,    'Con ingreso y egreso registrados'],
    ['En curso',         $en_curso,       'Familiares dentro del hogar ahora'],
    ['Permanencia media',$promedio ? floor($promedio/60).'h '.($promedio%60).'m' : '','Promedio por visita'],
];

$sel = fn($v) => $filtro_estado===$v?'selected':'';
$opts = '';
foreach ($estados_pill as $k=>$v) $opts .= '<option value="'.$k.'" '.$sel($k).'>'.$v[0].'</option>';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'.$opts.'</select></div>';

salida_reporte('Reporte de visitas', 'visitas', $cols, $filas);

echo '<h1>Reporte de visitas</h1>';
barra_filtros(['residente'=>true,'extra'=>$extra]);
resumen_reporte($resumen);
?>

<div class="grid" style="grid-template-columns:1fr 1fr">
    <?php if (!empty($datos_dia)): ?>
    <div class="card">
        <h3 style="margin-top:0">¿Qué días visitan más?</h3>
        <canvas id="grafico-dia" height="200"></canvas>
    </div>
    <?php endif; ?>

    <?php if (!empty($datos_tipo)): ?>
    <div class="card">
        <h3 style="margin-top:0">Visitas por tipo</h3>
        <canvas id="grafico-tipo" height="200"></canvas>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($datos_res)): ?>
<div class="card">
    <h3 style="margin-top:0">Visitas por residente</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Residente</th><th>Total visitas</th><th>Finalizadas</th></tr></thead>
        <tbody>
        <?php foreach ($datos_res as $r): ?>
            <tr>
                <td><?= h($r['nombre'].' '.$r['apellido']) ?></td>
                <td><b><?= (int)$r['total'] ?></b></td>
                <td><?= (int)$r['finalizadas'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php tabla_reporte($cols, $filas); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($datos_dia)): ?>
new Chart(document.getElementById('grafico-dia'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($d) => $dias_es[$d['dia_en']] ?? $d['dia_en'], $datos_dia)) ?>,
        datasets:[{ data:<?= json_encode(array_column($datos_dia,'total')) ?>, backgroundColor:'#2D9B8A' }]
    },
    options:{ plugins:{legend:{display:false}}, scales:{y:{ticks:{stepSize:1}}} }
});
<?php endif; ?>
<?php if (!empty($datos_tipo)): ?>
new Chart(document.getElementById('grafico-tipo'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($t) => ucfirst(str_replace('_',' ',$t['tipo_visita'])), $datos_tipo)) ?>,
        datasets:[{ data:<?= json_encode(array_column($datos_tipo,'n')) ?>,
            backgroundColor:['#2D9B8A','#3498db','#e67e22','#9b59b6','#1abc9c','#e74c3c','#f39c12','#2c3e50'] }]
    },
    options:{ plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
