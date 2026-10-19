<?php
require_once 'comun.php';
abrir_pagina_reporte();

$filtro_gravedad = $_GET['gravedad'] ?? '';
$filtro_estado   = $_GET['estado']   ?? '';
$where = '';
if ($id_residente)           $where .= ' AND i.id_residente = '.$id_residente;
if ($filtro_gravedad !== '') $where .= ' AND i.gravedad = '.$pdo->quote($filtro_gravedad);
if ($filtro_estado !== '')   $where .= ' AND i.estado = '.$pdo->quote($filtro_estado);

$sth = $pdo->prepare("SELECT i.*, r.nombre rn, r.apellido ra, u.nombre reporto
    FROM incidentes i
    JOIN residentes r    ON r.id_residente = i.id_residente
    LEFT JOIN usuarios u ON u.id_usuario = i.id_usuario
    WHERE i.fecha_hora BETWEEN ? AND ? $where
    ORDER BY FIELD(i.gravedad,'grave','moderado','leve'), i.fecha_hora DESC");
$sth->execute([$desde_dt, $hasta_dt]);
$datos = $sth->fetchAll();

$por_tipo = $pdo->prepare("SELECT tipo_incidente, COUNT(*) n,
    SUM(gravedad='grave') graves
    FROM incidentes
    WHERE fecha_hora BETWEEN ? AND ? $where
    GROUP BY tipo_incidente ORDER BY n DESC");
$por_tipo->execute([$desde_dt, $hasta_dt]);
$datos_tipo = $por_tipo->fetchAll();

$por_franja = $pdo->prepare("SELECT
    CASE
        WHEN HOUR(fecha_hora) BETWEEN 6  AND 13 THEN 'Mañana (06-14)'
        WHEN HOUR(fecha_hora) BETWEEN 14 AND 21 THEN 'Tarde (14-22)'
        ELSE 'Noche (22-06)'
    END franja,
    COUNT(*) n
    FROM incidentes
    WHERE fecha_hora BETWEEN ? AND ? $where
    GROUP BY franja ORDER BY n DESC");
$por_franja->execute([$desde_dt, $hasta_dt]);
$datos_franja = $por_franja->fetchAll();

$por_mes = $pdo->prepare("SELECT
    DATE_FORMAT(fecha_hora,'%m/%Y') mes,
    COUNT(*) total,
    SUM(gravedad='grave') graves,
    SUM(gravedad='moderado') moderados,
    SUM(gravedad='leve') leves
    FROM incidentes
    WHERE fecha_hora BETWEEN ? AND ? " . ($id_residente ? "AND id_residente=$id_residente" : "") . "
    GROUP BY mes ORDER BY MIN(fecha_hora)");
$por_mes->execute([$desde_dt, $hasta_dt]);
$datos_mes = $por_mes->fetchAll();

$por_res = $pdo->prepare("SELECT r.nombre, r.apellido, COUNT(*) n,
    SUM(i.gravedad='grave') graves
    FROM incidentes i
    JOIN residentes r ON r.id_residente=i.id_residente
    WHERE i.fecha_hora BETWEEN ? AND ? " . ($id_residente ? "AND i.id_residente=$id_residente" : "") . "
    GROUP BY i.id_residente ORDER BY n DESC");
$por_res->execute([$desde_dt, $hasta_dt]);
$datos_res = $por_res->fetchAll();

$etiq = ['caida'=>'Caída','lesion'=>'Lesión','descompensacion'=>'Descompensación',
    'error_medicacion'=>'Error de medicación','conducta'=>'Episodio de conducta',
    'fuga'=>'Salida sin aviso','atragantamiento'=>'Atragantamiento','otro'=>'Otro'];

$cols = ['Fecha y hora','Residente','Tipo','Gravedad','Descripción',
         'Acciones tomadas','Médico interviniente','Familiar notificado','Estado','Reportado por'];

$filas = []; $graves=0; $pendientes=0; $graves_pend=0;
foreach ($datos as $d) {
    if ($d['gravedad']==='grave')   $graves++;
    if ($d['estado']==='pendiente') $pendientes++;
    if ($d['gravedad']==='grave' && $d['estado']==='pendiente') $graves_pend++;
    $filas[] = [
        fecha_hora($d['fecha_hora']),
        $d['rn'].' '.$d['ra'],
        $etiq[$d['tipo_incidente']] ?? $d['tipo_incidente'],
        pill_gravedad($d['gravedad']),
        $d['descripcion'] ?: '',
        $d['acciones_tomadas'] ?: '',
        $d['medico_interviniente'] ?: '',
        $d['notificado_responsable'] ? 'Sí' : 'No',
        $d['estado']==='revisado' ? pill('Revisado','pill-ok') : pill('Pendiente','pill-aviso'),
        $d['reporto'] ?: '',
    ];
}

$resumen = [
    ['Incidentes',       count($datos)],
    ['Graves',           $graves,         'Requieren seguimiento'],
    ['Sin revisar',      $pendientes,     'Pendientes de revisión'],
    ['Graves sin revisar',$graves_pend,  'Prioridad máxima'],
];

$selG = fn($v) => $filtro_gravedad===$v?'selected':'';
$selE = fn($v) => $filtro_estado===$v?'selected':'';
$extra = '<div><label style="font-size:13px;font-weight:600;display:block">Gravedad</label>'
       . '<select name="gravedad"><option value="">Todas</option>'
       . '<option value="grave" '.$selG('grave').'>Grave</option>'
       . '<option value="moderado" '.$selG('moderado').'>Moderado</option>'
       . '<option value="leve" '.$selG('leve').'>Leve</option>'
       . '</select></div>'
       . '<div><label style="font-size:13px;font-weight:600;display:block">Estado</label>'
       . '<select name="estado"><option value="">Todos</option>'
       . '<option value="pendiente" '.$selE('pendiente').'>Sin revisar</option>'
       . '<option value="revisado" '.$selE('revisado').'>Revisados</option>'
       . '</select></div>';

salida_reporte('Reporte de incidentes', 'incidentes', $cols, $filas);

echo '<h1>Reporte de incidentes / accidentes</h1>';
barra_filtros(['residente' => true, 'extra' => $extra]);
resumen_reporte($resumen);
?>

<div class="grid" style="grid-template-columns:1fr 1fr">

    <?php if (!empty($datos_mes)): ?>
    <div class="card">
        <h3 style="margin-top:0">Incidentes por mes</h3>
        <canvas id="grafico-mes" height="200"></canvas>
    </div>
    <?php endif; ?>

    <?php if (!empty($datos_franja)): ?>
    <div class="card">
        <h3 style="margin-top:0">¿En qué momento del día ocurren?</h3>
        <canvas id="grafico-franja" height="200"></canvas>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($datos_tipo)): ?>
<div class="card">
    <h3 style="margin-top:0">Incidentes por tipo</h3>
    <canvas id="grafico-tipo" height="100"></canvas>
</div>
<?php endif; ?>

<?php if (!empty($datos_res)): ?>
<div class="card">
    <h3 style="margin-top:0">Incidentes por residente</h3>
    <table class="no-datatable" style="width:100%">
        <thead><tr><th>Residente</th><th>Total</th><th>Graves</th></tr></thead>
        <tbody>
        <?php foreach ($datos_res as $r): ?>
            <tr>
                <td><?= h($r['nombre'].' '.$r['apellido']) ?></td>
                <td><b><?= (int)$r['n'] ?></b></td>
                <td><?= (int)$r['graves'] > 0 ? '<b style="color:#e74c3c">'.(int)$r['graves'].'</b>' : '0' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php tabla_reporte($cols, $filas); ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
<?php if (!empty($datos_mes)): ?>
new Chart(document.getElementById('grafico-mes'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($datos_mes,'mes')) ?>,
        datasets: [
            { label:'Total',     data:<?= json_encode(array_column($datos_mes,'total')) ?>,    backgroundColor:'#e67e22' },
            { label:'Graves',    data:<?= json_encode(array_column($datos_mes,'graves')) ?>,   backgroundColor:'#e74c3c' },
            { label:'Moderados', data:<?= json_encode(array_column($datos_mes,'moderados')) ?>,backgroundColor:'#f39c12' }
        ]
    },
    options:{ plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>

<?php if (!empty($datos_franja)): ?>
new Chart(document.getElementById('grafico-franja'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($datos_franja,'franja')) ?>,
        datasets:[{ data:<?= json_encode(array_column($datos_franja,'n')) ?>,
            backgroundColor:['#3498db','#e67e22','#2c3e50'] }]
    },
    options:{ plugins:{ legend:{ position:'bottom' } } }
});
<?php endif; ?>

<?php if (!empty($datos_tipo)): ?>
new Chart(document.getElementById('grafico-tipo'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(fn($t) => $etiq[$t['tipo_incidente']] ?? $t['tipo_incidente'], $datos_tipo)) ?>,
        datasets:[{ data:<?= json_encode(array_column($datos_tipo,'n')) ?>,
            backgroundColor:'#e74c3c' }]
    },
    options:{ indexAxis:'y', plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ stepSize:1 } } } }
});
<?php endif; ?>
</script>

<?php include '../config/footer.php'; ?>
