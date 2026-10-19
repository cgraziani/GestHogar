<?php
require_once 'comun.php';
abrir_pagina_reporte();

$where = '';
if ($id_residente) {
    $where = ' AND EXISTS (SELECT 1 FROM actividad_residentes ar
                           WHERE ar.id_actividad=a.id_actividad
                           AND ar.id_residente='.$id_residente.')';
}

$sth = $pdo->prepare("SELECT a.*, CONCAT(u.nombre,' ',COALESCE(u.apellido,'')) organizo
    FROM actividades a
    LEFT JOIN usuarios u ON u.id_usuario=a.id_usuario
    WHERE a.estado='activo' AND a.fecha BETWEEN ? AND ? $where
    ORDER BY a.fecha DESC");
$sth->execute([$desde, $hasta]);
$datos = $sth->fetchAll();

$participantes = [];
if ($datos) {
    $ids = implode(',', array_map(fn($a) => (int)$a['id_actividad'], $datos));
    $sthP = $pdo->query("SELECT ar.id_actividad, ar.observacion_participacion,
        r.nombre, r.apellido, r.id_residente
        FROM actividad_residentes ar
        JOIN residentes r ON r.id_residente=ar.id_residente
        WHERE ar.id_actividad IN ($ids)
        ORDER BY r.apellido, r.nombre");
    foreach ($sthP->fetchAll() as $p) {
        $participantes[$p['id_actividad']][] = $p;
    }
}

$part_res = $pdo->prepare("SELECT r.nombre, r.apellido, COUNT(*) actividades
    FROM actividad_residentes ar
    JOIN residentes r ON r.id_residente=ar.id_residente
    JOIN actividades a ON a.id_actividad=ar.id_actividad
    WHERE a.fecha BETWEEN ? AND ? AND a.estado='activo'
    " . ($id_residente ? "AND ar.id_residente=$id_residente" : "") . "
    GROUP BY ar.id_residente ORDER BY actividades DESC");
$part_res->execute([$desde, $hasta]);
$datos_res = $part_res->fetchAll();

$por_tipo = $pdo->prepare("SELECT tipo, COUNT(*) n,
    AVG((SELECT COUNT(*) FROM actividad_residentes ar WHERE ar.id_actividad=a.id_actividad)) promedio_part
    FROM actividades a
    WHERE estado='activo' AND fecha BETWEEN ? AND ?
    GROUP BY tipo ORDER BY n DESC");
$por_tipo->execute([$desde, $hasta]);
$datos_tipo = $por_tipo->fetchAll();

$cols = ['Fecha','Actividad','Tipo','Descripción','Participantes',
         'Cantidad','Observaciones participación','Observaciones generales','Organizó'];

$filas = []; $total_part = 0;
foreach ($datos as $d) {
    $lista = $participantes[$d['id_actividad']] ?? [];
    $total_part += count($lista);
    $nombres = implode(', ', array_map(fn($p) => $p['nombre'].' '.$p['apellido'], $lista)) ?: 'Sin participantes';
    $obs = [];
    foreach ($lista as $p) {
        if (trim((string)$p['observacion_participacion']) !== '')
            $obs[] = $p['nombre'].': '.$p['observacion_participacion'];
    }
    $filas[] = [
        fecha_corta($d['fecha']),
        $d['nombre'],
        ucfirst($d['tipo'] ?? ''),
        $d['descripcion'] ?: '',
        $nombres,
        count($lista),
        $obs ? implode(' · ', $obs) : '',
        $d['observaciones'] ?: '',
        $d['organizo'] ?: '',
    ];
}

$promedio = count($datos) ? round($total_part/count($datos),1) : 0;
$resumen = [
    ['Actividades',    count($datos),  'Realizadas en el período'],
    ['Participaciones',$total_part,    'Suma de asistentes'],
    ['Promedio',       $promedio,      'Participantes por actividad'],
];

salida_reporte('Reporte de actividades', 'actividades', $cols, $filas);

echo '<h1>Reporte de actividades recreativas</h1>';
barra_filtros(['residente'=>true]);
resumen_reporte($resumen);
?>

<div class="grid" style="grid-template-columns:1fr 1fr">
    <?php if (!empty($datos_res)): ?>
    <div class="card">
        <h3 style="margin-top:0">Participación por residente</h3>
        <canvas id="grafico-res" height="200"></canvas>
    </div>
    <?php endif; ?>

    <?php if (!empty($datos_tipo)): ?>
    <div class="card">
        <h3 style="margin-top:0">Actividades por tipo</h3>
        <canvas id="grafico-tipo" height="200"></canvas>
    </div>
    <?php endif; ?>
</div>

<?php tabla_reporte($cols, $filas); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($datos_res)): ?>
new Chart(document.getElementById('grafico-res'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($r) => $r['nombre'].' '.$r['apellido'], $datos_res)) ?>,
        datasets:[{ label:'Actividades', data:<?= json_encode(array_column($datos_res,'actividades')) ?>, backgroundColor:'#2D9B8A' }]
    },
    options:{ indexAxis:'y', plugins:{legend:{display:false}}, scales:{x:{ticks:{stepSize:1}}} }
});
<?php endif; ?>
<?php if (!empty($datos_tipo)): ?>
new Chart(document.getElementById('grafico-tipo'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($t) => ucfirst($t['tipo']??'Sin tipo'), $datos_tipo)) ?>,
        datasets:[{ data:<?= json_encode(array_column($datos_tipo,'n')) ?>,
            backgroundColor:['#2D9B8A','#3498db','#e67e22','#9b59b6','#e74c3c'] }]
    },
    options:{ plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
