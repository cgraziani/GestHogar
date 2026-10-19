<?php
require_once 'comun.php';
abrir_pagina_reporte();

$habs = $pdo->query("SELECT h.*,
    (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion=h.id_habitacion) total_camas,
    (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion=h.id_habitacion AND c.estado='disponible') libres,
    (SELECT COUNT(*) FROM camas c WHERE c.id_habitacion=h.id_habitacion AND c.estado='mantenimiento') camas_mant,
    (SELECT COUNT(*) FROM residentes r WHERE r.id_habitacion=h.id_habitacion AND r.estado='activo') alojados_real,
    (SELECT GROUP_CONCAT(CONCAT(r.nombre,' ',r.apellido) ORDER BY r.apellido SEPARATOR ', ')
       FROM residentes r WHERE r.id_habitacion=h.id_habitacion AND r.estado='activo') residentes
    FROM habitaciones h ORDER BY h.numero")->fetchAll();

$ocup_total = $pdo->query("SELECT
    (SELECT COUNT(*) FROM camas WHERE estado!='mantenimiento') total_camas,
    (SELECT COUNT(*) FROM residentes WHERE estado='activo' AND id_cama IS NOT NULL) ocupadas_real,
    (SELECT COUNT(*) FROM residentes WHERE estado='activo') activos_sin_cama")->fetch();

$ocupacion_real = $ocup_total['total_camas'] > 0
    ? round($ocup_total['ocupadas_real'] * 100 / $ocup_total['total_camas'])
    : 0;

$movimiento = $pdo->prepare("SELECT
    (SELECT COUNT(*) FROM residentes WHERE fecha_ingreso BETWEEN ? AND ?) ingresos,
    (SELECT COUNT(*) FROM residentes WHERE fecha_egreso  BETWEEN ? AND ?) egresos,
    (SELECT COUNT(*) FROM residentes WHERE fecha_egreso BETWEEN ? AND ? AND tipo_baja='fallecimiento') fallecimientos,
    (SELECT COUNT(*) FROM residentes WHERE fecha_egreso BETWEEN ? AND ? AND tipo_baja='traslado') traslados");
$movimiento->execute([$desde,$hasta,$desde,$hasta,$desde,$hasta,$desde,$hasta]);
$mov = $movimiento->fetch();

$estadia = $pdo->prepare("SELECT ROUND(AVG(DATEDIFF(fecha_egreso, fecha_ingreso))) dias_promedio
    FROM residentes WHERE fecha_egreso BETWEEN ? AND ? AND fecha_ingreso IS NOT NULL");
$estadia->execute([$desde, $hasta]);
$dias_prom = $estadia->fetchColumn();

$inc = $pdo->prepare("SELECT COUNT(*) total,
    SUM(estado='pendiente') pendientes,
    SUM(estado='resuelto')  resueltos,
    SUM(prioridad='alta' AND estado='pendiente') urgentes
    FROM incidentes_hogar WHERE fecha_reporte BETWEEN ? AND ?");
$inc->execute([$desde_dt, $hasta_dt]);
$inc = $inc->fetch();

$cols = ['Habitación','Sector','Piso','Camas','Ocupadas','Libres','Estado','Residentes'];
$filas = [];
$totCamas=0; $totOcupadas=0; $totLibres=0;
foreach ($habs as $h) {
    $totCamas    += (int)$h['total_camas'];
    $totOcupadas += (int)$h['alojados_real'];
    $totLibres   += max(0, (int)$h['total_camas'] - (int)$h['alojados_real'] - (int)$h['camas_mant']);

    $estado = $h['estado']==='mantenimiento'
        ? pill('Mantenimiento','pill-aviso')
        : (!$h['habilitada'] ? pill('No habilitada','pill-mal') : pill('Disponible','pill-ok'));

    $filas[] = [
        $h['numero'],
        $h['sector'] ?: '',
        $h['piso']   ?: '',
        (int)$h['total_camas'],
        (int)$h['alojados_real'],
        max(0, (int)$h['total_camas'] - (int)$h['alojados_real'] - (int)$h['camas_mant']),
        $estado,
        $h['residentes'] ?: 'Sin residentes',
    ];
}

$resumen = [
    ['Ocupación real',  $ocupacion_real.'%',           $ocup_total['ocupadas_real'].' de '.$ocup_total['total_camas'].' camas'],
    ['Camas libres',    max(0,$ocup_total['total_camas']-$ocup_total['ocupadas_real']), 'Disponibles ahora'],
    ['Ingresos', (int)$mov['ingresos']],
    ['Egresos',         (int)$mov['egresos'],           (int)$mov['fallecimientos'].' fallecimientos · '.(int)$mov['traslados'].' traslados'],
];

salida_reporte('Estado del hogar', 'estado-hogar', $cols, $filas);

echo '<h1>Reporte de estado del hogar</h1>';
barra_filtros(['residente' => false]);
resumen_reporte($resumen);

echo '<div class="grid">';
echo '<div class="card" style="text-align:center"><div class="stat">'.($dias_prom ?: '').'</div><b>Días de estadía promedio</b></div>';
echo '<div class="card" style="text-align:center"><div class="stat">'.(int)$inc['total'].'</div><b>Problemas de instalaciones</b><br><span class="small">'.(int)$inc['urgentes'].' urgentes sin resolver</span></div>';
echo '<div class="card" style="text-align:center"><div class="stat">'.(int)$mov['fallecimientos'].'</div><b>Fallecimientos</b></div>';
echo '<div class="card" style="text-align:center"><div class="stat">'.(int)$ocup_total['activos_sin_cama'].'</div><b>Activos sin cama asignada</b></div>';
echo '</div>';
?>

<?php if (!empty($habs)): ?>
<div class="card">
    <h3 style="margin-top:0">Ocupación por habitación</h3>
    <canvas id="grafico-ocupacion" height="120"></canvas>
</div>
<?php endif; ?>

<h2>Detalle por habitación</h2>
<?php tabla_reporte($cols, $filas); ?>

<?php
$prob = $pdo->prepare("SELECT ih.*, h.numero hab, u.nombre reporto
    FROM incidentes_hogar ih
    LEFT JOIN habitaciones h ON h.id_habitacion=ih.id_habitacion
    LEFT JOIN usuarios u     ON u.id_usuario=ih.reportado_por
    WHERE ih.fecha_reporte BETWEEN ? AND ?
    ORDER BY FIELD(ih.estado,'pendiente','en_curso','resuelto','descartado'),
             FIELD(ih.prioridad,'alta','media','baja'), ih.fecha_reporte DESC");
$prob->execute([$desde_dt, $hasta_dt]);
$prob = $prob->fetchAll();

if (!empty($prob)): ?>
<h2 style="margin-top:28px">Problemas de instalaciones en el período</h2>
<?php
$cols2 = ['Reportado','Tipo','Problema','Dónde','Prioridad','Estado','Qué se hizo'];
$filas2 = [];
foreach ($prob as $p) {
    $donde = $p['hab'] ? 'Hab. '.$p['hab'] : ($p['ubicacion'] ?: '');
    $est = ['pendiente'=>['Pendiente','pill-aviso'],'en_curso'=>['En curso','pill-neutro'],
            'resuelto'=>['Resuelto','pill-ok'],'descartado'=>['Descartado','pill-neutro']][$p['estado']]
           ?? [$p['estado'],'pill-neutro'];
    $pri = ['alta'=>['Alta','pill-mal'],'media'=>['Media','pill-aviso'],'baja'=>['Baja','pill-neutro']][$p['prioridad']]
           ?? [$p['prioridad'],'pill-neutro'];
    $filas2[] = [
        fecha_hora($p['fecha_reporte']), ucfirst($p['tipo']), $p['titulo'], $donde,
        pill($pri[0],$pri[1]), pill($est[0],$est[1]), $p['acciones'] ?: '',
    ];
}
tabla_reporte($cols2, $filas2);
endif; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($habs)): ?>
new Chart(document.getElementById('grafico-ocupacion'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($h) => 'Hab. '.$h['numero'], $habs)) ?>,
        datasets: [
            { label: 'Ocupadas', data: <?= json_encode(array_map(fn($h) => (int)$h['alojados_real'], $habs)) ?>, backgroundColor: '#2D9B8A' },
            { label: 'Libres',   data: <?= json_encode(array_map(fn($h) => max(0,(int)$h['total_camas']-(int)$h['alojados_real']-(int)$h['camas_mant']), $habs)) ?>, backgroundColor: '#e0f4f1' }
        ]
    },
    options: {
        scales: { x: { stacked: true }, y: { stacked: true, ticks: { stepSize: 1 } } },
        plugins: { legend: { position: 'bottom' } }
    }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
