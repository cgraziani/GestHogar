<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_estado = $_GET['estado'] ?? '';
$where = '';
if ($filtro_estado !== '') $where .= ' AND t.estado = '.$pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT t.*,
    us.nombre saliente, us.apellido saliente_ape,
    ue.nombre entrante, ue.apellido entrante_ape
    FROM traspaso_turno t
    LEFT JOIN usuarios us ON us.id_usuario=t.id_usuario_saliente
    LEFT JOIN usuarios ue ON ue.id_usuario=t.id_usuario_entrante
    WHERE COALESCE(t.fecha_apertura, t.fecha_cierre, t.created_at) BETWEEN ? AND ? $where
    ORDER BY COALESCE(t.fecha_apertura, t.fecha_cierre, t.created_at) DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$por_cui = $pdo->prepare("SELECT u.nombre, u.apellido,
    COUNT(*) total,
    SUM(t.estado='cerrado') cerrados,
    SUM(t.estado='pendiente') pendientes
    FROM traspaso_turno t
    JOIN usuarios u ON u.id_usuario=t.id_usuario_saliente
    WHERE COALESCE(t.fecha_apertura, t.fecha_cierre, t.created_at) BETWEEN ? AND ?
    GROUP BY t.id_usuario_saliente ORDER BY total DESC");
$por_cui->execute([$desde_dt, $hasta_dt]);
$datos_cui = $por_cui->fetchAll();

$por_semana = $pdo->prepare("SELECT
    DATE_FORMAT(DATE_SUB(COALESCE(fecha_apertura,fecha_cierre,created_at),
        INTERVAL WEEKDAY(COALESCE(fecha_apertura,fecha_cierre,created_at)) DAY), '%d/%m') semana,
    COUNT(*) total,
    SUM(estado='cerrado') cerrados
    FROM traspaso_turno
    WHERE COALESCE(fecha_apertura, fecha_cierre, created_at) BETWEEN ? AND ?
    GROUP BY semana ORDER BY MIN(COALESCE(fecha_apertura, fecha_cierre, created_at))");
$por_semana->execute([$desde_dt, $hasta_dt]);
$datos_semana = $por_semana->fetchAll();

$cols = ['Cierre','Apertura','Cuidadora saliente','Cuidadora entrante',
         'Novedades generales','Residentes','Medicación','Incidentes','Visitas','Estado'];

$filas = []; $pendientes = 0;
foreach ($datos as $d) {
    if ($d['estado']==='pendiente') $pendientes++;
    $filas[] = [
        $d['fecha_cierre']   ? fecha_hora($d['fecha_cierre'])   : pill('Sin registrar','pill-neutro'),
        $d['fecha_apertura'] ? fecha_hora($d['fecha_apertura']) : pill('Sin confirmar','pill-aviso'),
        trim(($d['saliente']??'').(' '.($d['saliente_ape']??''))) ?: '',
        trim(($d['entrante']??'').(' '.($d['entrante_ape']??''))) ?: '',
        $d['novedades'] ?: '',
        $d['nov_residentes']  ?: '',
        $d['nov_medicacion']  ?: '',
        $d['nov_incidentes']  ?: '',
        $d['nov_visitas']     ?: '',
        $d['estado']==='cerrado' ? pill('Cerrado','pill-ok') : pill('Pendiente','pill-aviso'),
    ];
}

$resumen = [
    ['Traspasos',    count($datos)],
    ['Cerrados',     count($datos)-$pendientes,    'Con novedades confirmadas'],
    ['Sin cerrar',   $pendientes,                  'Requieren atención'],
];

$sel = fn($v) => $filtro_estado===$v?'selected':'';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="pendiente" '.$sel('pendiente').'>Sin cerrar</option>'
       . '<option value="cerrado" '.$sel('cerrado').'>Cerrados</option>'
       . '</select></div>';

salida_reporte('Reporte de traspaso de turno', 'traspasos', $cols, $filas);

echo '<h1>Reporte de traspaso de turno</h1>';
barra_filtros(['residente' => false, 'extra' => $extra]);
resumen_reporte($resumen);
?>

<?php if (!empty($datos_semana)): ?>
<div class="card">
    <h3 style="margin-top:0">Traspasos por semana</h3>
    <canvas id="grafico-semana" height="120"></canvas>
</div>
<?php endif; ?>

<?php if (!empty($datos_cui)): ?>
<div class="card">
    <h3 style="margin-top:0">Traspasos por cuidadora</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Cuidadora</th><th>Total</th><th>Cerrados</th><th>Pendientes</th></tr></thead>
        <tbody>
        <?php foreach ($datos_cui as $c): ?>
            <tr>
                <td><?= h($c['nombre'].' '.$c['apellido']) ?></td>
                <td><?= (int)$c['total'] ?></td>
                <td><?= (int)$c['cerrados'] ?></td>
                <td><?= (int)$c['pendientes'] > 0 ? '<b style="color:#e74c3c">'.(int)$c['pendientes'].'</b>' : '0' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php tabla_reporte($cols, $filas); ?>

<?php if (!empty($datos_semana)): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('grafico-semana'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($datos_semana,'semana')) ?>,
        datasets: [
            { label:'Cerrados',   data:<?= json_encode(array_column($datos_semana,'cerrados')) ?>, backgroundColor:'#2D9B8A' },
            { label:'Pendientes', data:<?= json_encode(array_map(fn($s)=>$s['total']-$s['cerrados'], $datos_semana)) ?>, backgroundColor:'#e74c3c' }
        ]
    },
    options:{
        scales:{ x:{stacked:true}, y:{stacked:true, ticks:{stepSize:1}} },
        plugins:{ legend:{ position:'bottom' } }
    }
});
</script>
<?php endif; ?>

<?php include '../config/footer.php'; ?>
